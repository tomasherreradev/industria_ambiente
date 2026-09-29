<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Clientes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function puedeEditarUsuarios(?User $editor): bool
    {
        return $editor && trim((string) $editor->usu_codigo) === 'amendoza';
    }

    private function puedeEliminarUsuarios(?User $editor): bool
    {
        if (! $editor) {
            return false;
        }

        if ((int) ($editor->usu_nivel ?? 0) >= 900) {
            return true;
        }

        return trim((string) $editor->usu_codigo) === 'amendoza';
    }

    public function showUsers(Request $request)
    {
        // Empezar la consulta sin ejecutarla aún
        $query = User::query()->where(function ($q) {
            $q->whereNull('rol')->orWhere('rol', '!=', 'sector');
        });
    
        // Buscar por nombre (usu_descripcion)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(usu_descripcion) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(usu_codigo) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }
    
        // Filtrar por rol
        if ($request->filled('rol')) {
            $query->where('rol', $request->rol);
        }
    
        // Filtrar por estado
        if ($request->filled('estado')) {
            $query->where('usu_estado', $request->estado);
        }
    
        // Paginar resultados
        $usuarios = $query->orderBy('usu_descripcion')->paginate(20)->withQueryString();

        $puedeEditarUsuarios = $this->puedeEditarUsuarios(Auth::user());
        $puedeEliminarUsuarios = $this->puedeEliminarUsuarios(Auth::user());

        return view('users.index', compact('usuarios', 'puedeEditarUsuarios', 'puedeEliminarUsuarios'));
    }
    



    
    public function createUser()
    {
        if (!$this->puedeEditarUsuarios(Auth::user())) {
            abort(403);
        }

        $sectores = User::where('rol', 'sector')->orderBy('usu_descripcion')->get();

        return view('users.create', compact('sectores'));
    }

    public function storeUser(Request $request)
    {
        if (!$this->puedeEditarUsuarios(Auth::user())) {
            abort(403);
        }

        Log::info('Starting user creation', ['request_data' => $request->except(['password', 'password_confirmation'])]);

        $editor = Auth::user();
        $esAdmin = $editor && (int) ($editor->usu_nivel ?? 0) >= 900;
        $roles = $this->rolesPrincipalesValidos();

        try {
            $rules = [
                'usu_descripcion' => 'required|string|max:255',
                'usu_codigo' => 'required|string|max:50|unique:usu,usu_codigo',
                'rol' => ['required', 'string', 'max:50', Rule::in($roles)],
                'sector_codigo' => 'nullable|string|max:50',
                'sectores_codigos' => 'nullable|array',
                'sectores_codigos.*' => 'string|max:50',
                'dni' => 'nullable|string|max:20',
                'email' => 'nullable|string|max:255',
                'departamento' => 'nullable|string|max:255',
                'sector_trabajo' => 'nullable|string|max:100',
                'password' => 'required|string|min:4|confirmed',
                'usu_estado' => 'required|boolean',
            ];
            if ($esAdmin) {
                $rules['usu_nivel'] = 'nullable|integer|min:0|max:9999';
                $rules['roles_adicionales'] = 'nullable|array';
                $rules['roles_adicionales.*'] = ['string', 'max:50', Rule::in($roles)];
            }
            $rules['admin_lab'] = 'nullable|boolean';
            $rules['bandeja_solo_informes'] = 'nullable|boolean';
            $rules['puede_cargar_items'] = 'nullable|boolean';
            $rules['puede_gestionar_ordenes'] = 'nullable|boolean';
            $rules['puede_autorizar_facturacion'] = 'nullable|boolean';

            $validated = $request->validate($rules);
            Log::debug('Validation passed for new user');

            $usuario = new User();
            $usuario->usu_descripcion = $validated['usu_descripcion'];
            $usuario->usu_codigo = $validated['usu_codigo'];
            $usuario->rol = $validated['rol'];
            $usuario->usu_clave = md5($request->password);
            $usuario->usu_estado = (bool) $validated['usu_estado'];
            $usuario->dni = $validated['dni'] ?? null;
            $usuario->email = $validated['email'] ?? null;
            $usuario->departamento = $validated['departamento'] ?? null;
            $usuario->sector_trabajo = $validated['sector_trabajo'] ?? null;

            if ($esAdmin && $request->filled('usu_nivel')) {
                $usuario->usu_nivel = (int) $request->input('usu_nivel');
            } else {
                $usuario->usu_nivel = 500;
            }

            $rolFinal = (string) $usuario->rol;
            $sectoresSeleccionados = [];
            if ($rolFinal === 'laboratorio' || $rolFinal === 'coordinador_lab') {
                $sectoresSeleccionados = (array) $request->input('sectores_codigos', []);
                $usuario->sector_codigo = !empty($sectoresSeleccionados) ? $sectoresSeleccionados[0] : null;
            } else {
                if ($request->filled('sector_codigo')) {
                    $sectorUser = User::where('usu_codigo', $request->sector_codigo)->first();
                    if (! $sectorUser) {
                        return redirect()
                            ->back()
                            ->withInput()
                            ->withErrors(['sector_codigo' => 'El sector seleccionado no corresponde a un laboratorio válido.']);
                    }
                    $usuario->sector_codigo = $sectorUser->usu_codigo;
                } else {
                    $usuario->sector_codigo = null;
                }
            }

            $usuario->save();

            // Guardar sectores N:N
            if ($rolFinal === 'laboratorio' || $rolFinal === 'coordinador_lab') {
                $usuario->syncSectores($sectoresSeleccionados);
            } else {
                if ($usuario->sector_codigo) {
                    $usuario->syncSectores([$usuario->sector_codigo]);
                } else {
                    $usuario->syncSectores([]);
                }
            }

            if ($esAdmin) {
                $principal = (string) $usuario->rol;
                $adicionales = array_values(array_filter(
                    array_map('strval', (array) $request->input('roles_adicionales', [])),
                    fn ($r) => $r !== '' && $r !== $principal
                ));
                $usuario->syncRoles($adicionales);
            }

            $rolesAdicionalesGuardados = $esAdmin
                ? array_values(array_filter(
                    array_map('strval', (array) $request->input('roles_adicionales', [])),
                    fn ($r) => $r !== '' && $r !== (string) $usuario->rol
                ))
                : [];
            $usuario->admin_lab = $this->usuarioTieneRolCoordinadorLab($rolFinal, $rolesAdicionalesGuardados)
                && $request->boolean('admin_lab');
            $usuario->bandeja_solo_informes = $this->usuarioTieneRolCoordinadorLabOMediciones($rolFinal, $rolesAdicionalesGuardados)
                && $request->boolean('bandeja_solo_informes');
            $usuario->puede_cargar_items = $request->boolean('puede_cargar_items');
            $usuario->puede_gestionar_ordenes = $this->usuarioTieneRolCoordinadorLab($rolFinal, $rolesAdicionalesGuardados)
                && $request->boolean('puede_gestionar_ordenes');
            $usuario->puede_autorizar_facturacion = $request->boolean('puede_autorizar_facturacion');
            $usuario->save();

            Log::info('User created successfully', ['user_id' => $usuario->usu_codigo]);

            return redirect()
                ->route('users.showUsers')
                ->with('success', 'Usuario creado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed while creating user', [
                'errors' => $e->errors(),
                'input' => $request->except(['password', 'password_confirmation']),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error creating user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al crear el usuario. Por favor, intente nuevamente.']);
        }
    }
    

    public function showUser($usu_codigo)
    {
        $sectores = User::where('rol', 'sector')->orderBy('usu_descripcion')->get();
        $usuario = User::findOrFail($usu_codigo);
        $rolesAdicionales = DB::table('user_roles')
            ->where('usu_codigo', $usuario->usu_codigo)
            ->pluck('rol')
            ->all();
        $sectoresAsignados = DB::table('user_sectors')
            ->where('usu_codigo', $usuario->usu_codigo)
            ->pluck('sector_codigo')
            ->all();

        $puedeEditarUsuarios = $this->puedeEditarUsuarios(Auth::user());
        $puedeEliminarUsuarios = $this->puedeEliminarUsuarios(Auth::user());

        return view('users.show', compact('usuario', 'sectores', 'rolesAdicionales', 'sectoresAsignados', 'puedeEditarUsuarios', 'puedeEliminarUsuarios'));
    }

    public function update(Request $request, $usu_codigo)
    {
        if (!$this->puedeEditarUsuarios(Auth::user())) {
            abort(403);
        }

        $editor = Auth::user();
        $esAdmin = $editor && (int) ($editor->usu_nivel ?? 0) >= 900;

        $rolesPrincipalesValidos = $this->rolesPrincipalesValidos();

        $rules = [
            'usu_descripcion' => 'required|string|max:255',
            'usu_estado' => 'required|boolean',
            'rol' => ['nullable', 'string', 'max:50', Rule::in(array_merge([''], $rolesPrincipalesValidos))],
            'sector_codigo' => 'nullable|string|max:50',
            'sectores_codigos' => 'nullable|array',
            'sectores_codigos.*' => 'string|max:50',
            'dni' => 'nullable|string|max:20',
            'email' => 'nullable|string|max:255',
            'departamento' => 'nullable|string|max:255',
            'sector_trabajo' => 'nullable|string|max:100',
        ];

        if ($esAdmin) {
            $rules['usu_nivel'] = 'nullable|integer|min:0|max:9999';
            $rules['password'] = 'nullable|string|min:4|confirmed';
            $rules['roles_adicionales'] = 'nullable|array';
            $rules['roles_adicionales.*'] = ['string', 'max:50', Rule::in($rolesPrincipalesValidos)];
            $rules['limpiar_sesion'] = 'nullable|boolean';
        }
        $rules['admin_lab'] = 'nullable|boolean';
        $rules['bandeja_solo_informes'] = 'nullable|boolean';
        $rules['puede_cargar_items'] = 'nullable|boolean';
        $rules['puede_gestionar_ordenes'] = 'nullable|boolean';
        $rules['puede_autorizar_facturacion'] = 'nullable|boolean';

        $validated = $request->validate($rules);

        $usuario = User::findOrFail($usu_codigo);
        $usuario->usu_descripcion = $validated['usu_descripcion'];
        $usuario->usu_estado = (bool) $validated['usu_estado'];
        $rolInput = $request->input('rol');
        $rolFinal = ($rolInput === '' || $rolInput === null) ? null : (string) $rolInput;
        $usuario->rol = $rolFinal;

        if ($rolFinal === 'laboratorio' || $rolFinal === 'coordinador_lab') {
            $sectoresSeleccionados = (array) $request->input('sectores_codigos', []);
            $usuario->syncSectores($sectoresSeleccionados);
            $usuario->sector_codigo = !empty($sectoresSeleccionados) ? $sectoresSeleccionados[0] : null;
        } else {
            if ($request->filled('sector_codigo')) {
                $usuarioSector = User::where('usu_codigo', $request->sector_codigo)->first();
                if (! $usuarioSector) {
                    return redirect()->back()->withErrors(['sector_codigo' => 'El sector seleccionado no corresponde a un usuario (laboratorio) válido.'])->withInput();
                }
                $usuario->sector_codigo = $usuarioSector->usu_codigo;
                $usuario->syncSectores([$usuarioSector->usu_codigo]);
            } else {
                $usuario->sector_codigo = null;
                $usuario->syncSectores([]);
            }
        }

        $usuario->dni = $validated['dni'] ?? null;
        $usuario->email = $validated['email'] ?? null;
        $usuario->departamento = $validated['departamento'] ?? null;
        $usuario->sector_trabajo = $validated['sector_trabajo'] ?? null;

        if ($esAdmin) {
            if ($request->filled('usu_nivel')) {
                $usuario->usu_nivel = (int) $request->input('usu_nivel');
            }
            if ($request->filled('password')) {
                $usuario->usu_clave = md5($request->input('password'));
            }
            if ($request->boolean('limpiar_sesion')) {
                $usuario->current_session_id = null;
            }
            $principal = (string) ($usuario->rol ?? '');
            $adicionales = array_values(array_filter(
                array_map('strval', (array) $request->input('roles_adicionales', [])),
                fn ($r) => $r !== '' && $r !== $principal
            ));
            $usuario->syncRoles($adicionales);
        }

        $rolesAdicionalesGuardados = $esAdmin
            ? array_values(array_filter(
                array_map('strval', (array) $request->input('roles_adicionales', [])),
                fn ($r) => $r !== '' && $r !== (string) ($usuario->rol ?? '')
            ))
            : $usuario->rolesAdicionales()->all();
        $usuario->admin_lab = $this->usuarioTieneRolCoordinadorLab($rolFinal, $rolesAdicionalesGuardados)
            && $request->boolean('admin_lab');
        $usuario->bandeja_solo_informes = $this->usuarioTieneRolCoordinadorLabOMediciones($rolFinal, $rolesAdicionalesGuardados)
            && $request->boolean('bandeja_solo_informes');
        $usuario->puede_cargar_items = $request->boolean('puede_cargar_items');
        $usuario->puede_gestionar_ordenes = $this->usuarioTieneRolCoordinadorLab($rolFinal, $rolesAdicionalesGuardados)
            && $request->boolean('puede_gestionar_ordenes');
        $usuario->puede_autorizar_facturacion = $request->boolean('puede_autorizar_facturacion');

        $usuario->save();

        return redirect()
            ->route('users.showUser', ['usu_codigo' => $usuario->usu_codigo])
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy($usu_codigo)
    {
        if (! $this->puedeEliminarUsuarios(Auth::user())) {
            abort(403);
        }

        $editor = Auth::user();
        $usuario = User::findOrFail($usu_codigo);

        if (trim((string) $usuario->usu_codigo) === trim((string) $editor->usu_codigo)) {
            return redirect()
                ->back()
                ->with('error', 'No podés eliminar tu propio usuario.');
        }

        if ((string) $usuario->rol === 'sector') {
            return redirect()
                ->back()
                ->with('error', 'Los laboratorios (sectores) se gestionan desde la sección de Laboratorios.');
        }

        try {
            DB::transaction(function () use ($usuario) {
                $usuario->syncRoles([]);
                $usuario->syncSectores([]);
                $usuario->delete();
            });
        } catch (\Throwable $e) {
            Log::error('Error al eliminar usuario', [
                'usu_codigo' => $usuario->usu_codigo,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'No se pudo eliminar el usuario porque tiene registros asociados en el sistema.');
        }

        return redirect()
            ->route('users.showUsers')
            ->with('success', 'Usuario eliminado correctamente.');
    }

    public function showSectores(Request $request)
    {
        $sectores = User::where('rol', 'sector')->paginate(20);
        return view('sectores.index', compact('sectores'));
    }

    public function showSector($sector_codigo)
    {
        $sector = User::findOrFail($sector_codigo);
        return view('sectores.show', compact('sector'));
    }


    public function updateSector(Request $request, $sector_codigo)
    {
        $request->validate([
            'usu_descripcion' => 'required|string|max:255',
            'usu_codigo' => 'required|string|max:50',
        ]);
    
        $sector = User::findOrFail($sector_codigo);
        $sector->usu_descripcion = $request->usu_descripcion;
        $sector->usu_codigo = $request->usu_codigo;
        $sector->rol = 'sector';
        $sector->save();
    
        return redirect()->route('sectores.showSectores')->with('success', 'Sector actualizado correctamente.');
    }

    public function createSector()
    {
        return view('sectores.create');
    }

    public function storeSector(Request $request)
    {
        $request->validate([
            'usu_descripcion' => 'required|string|max:255',
            'usu_codigo' => 'required|string|max:50',
        ]);
    
        $sector = new User();
        $sector->usu_descripcion = $request->usu_descripcion;
        $sector->usu_codigo = $request->usu_codigo;
        $sector->rol = 'sector';
        $sector->save();
    
        return redirect()->route('sectores.showSectores')->with('success', 'Sector creado correctamente.');
    }

    public function getUsuarioInfo($codigo)
    {
        try {
            $usuario = User::where('usu_codigo', $codigo)->first();
            
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'usu_codigo' => $usuario->usu_codigo,
                'usu_descripcion' => $usuario->usu_descripcion,
                'rol' => $usuario->rol,
                'usu_estado' => $usuario->usu_estado
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información del usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportar(Request $request)
    {
        try {
            $rol = $request->get('rol');
            
            $nombreArchivo = 'usuarios_' . now()->format('Y_m_d_H_i') . '.xlsx';
            
            return Excel::download(
                new UsersExport($rol),
                $nombreArchivo
            );
            
        } catch (\Exception $e) {
            Log::error('Error al exportar usuarios: ' . $e->getMessage());
            return back()->with('error', 'Error al exportar los usuarios: ' . $e->getMessage());
        }
    }

    /**
     * @return list<string>
     */
    private function rolesPrincipalesValidos(): array
    {
        return [
            'laboratorio', 'muestreador', 'coordinador_lab', 'coordinador_muestreo', 'facturador',
            'ventas', 'firmador', 'coordinador_consul', 'coordinador_mediciones', 'asp', 'clarke_fire', 'cliente',
            'cadena_custodia',
        ];
    }

    private function usuarioTieneRolCoordinadorLab(?string $rolPrincipal, array $rolesAdicionales = []): bool
    {
        if (trim((string) $rolPrincipal) === 'coordinador_lab') {
            return true;
        }

        return in_array('coordinador_lab', $rolesAdicionales, true);
    }

    private function usuarioTieneRolCoordinadorLabOMediciones(?string $rolPrincipal, array $rolesAdicionales = []): bool
    {
        $rolPrincipal = trim((string) $rolPrincipal);
        if (in_array($rolPrincipal, ['coordinador_lab', 'coordinador_mediciones'], true)) {
            return true;
        }

        return ! empty(array_intersect(['coordinador_lab', 'coordinador_mediciones'], $rolesAdicionales));
    }
}