<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Clientes;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;

class UserController extends Controller
{
    public function showUsers(Request $request)
    {
        // Empezar la consulta sin ejecutarla aún
        $query = User::where('rol', '!=', 'sector');
    
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
    
        return view('users.index', compact('usuarios'));
    }
    



    
    public function createUser()
    {
        $sectores = User::where('rol', 'sector')->get();
        // $clientes = Clientes::where('cli_estado', true)->get();
        return view('users.create', compact('sectores'));
    }

    public function storeUser(Request $request)
    {
        Log::info('Starting user creation', ['request_data' => $request->except('password')]);
    
        try {
            $validatedData = $request->validate([
                'usu_descripcion' => 'required|string|max:255',
                'usu_codigo' => 'required|string|max:50|unique:usu,usu_codigo',
                'rol' => 'required|string',
                'sector_codigo' => 'nullable|string',
                'password' => 'required|string|min:4',
            ]);
            Log::debug('Validation passed for new user');
    
            $usuario = new User();
            $usuario->usu_descripcion = $request->usu_descripcion;
            $usuario->usu_codigo = $request->usu_codigo;
            $usuario->rol = $request->rol;
            $usuario->sector_codigo = $request->sector_codigo;
            $usuario->usu_clave = md5($request->password);
            $usuario->usu_estado = true;
    
            $usuario->save();
            Log::info('User created successfully', ['user_id' => $usuario->usu_codigo]);
    
            return redirect()
                ->route('users.showUsers')
                ->with('success', 'Usuario creado correctamente.');
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed while creating user', [
                'errors' => $e->errors(),
                'input' => $request->except('password')
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error creating user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al crear el usuario. Por favor, intente nuevamente.']);
        }
    }
    

    public function showUser($usu_codigo)
    {
        $sectores = User::where('rol', 'sector')->get();
        $usuario = User::findOrFail($usu_codigo);
        return view('users.show', compact('usuario', 'sectores'));
    }

    public function update(Request $request, $usu_codigo)
    {
        $request->validate([
            'usu_descripcion' => 'required|string|max:255',
            'usu_estado' => 'required|boolean',
            'rol' => 'nullable|string|max:50',
            'sector_codigo' => 'nullable|string|max:50',
        ]);
    
        $usuario = User::findOrFail($usu_codigo);
        $usuario->usu_descripcion = $request->usu_descripcion;
        $usuario->usu_estado = $request->usu_estado;
        $usuario->rol = $request->rol;
    
        if ($request->sector_codigo) {
            // Buscar al usuario por el código que llegó como "sector_codigo"
            $usuarioSector = User::where('usu_codigo', $request->sector_codigo)->first();
    
            if ($usuarioSector) {
                // Asignar el usu_codigo encontrado al campo sector_codigo
                $usuario->sector_codigo = $usuarioSector->usu_codigo;
                $usuario->rol = 'laboratorio'; // O ajustá según tu lógica de negocio
            } else {
                return redirect()->back()->withErrors(['sector_codigo' => 'El código ingresado no corresponde a ningún usuario.']);
            }
        } else {
            $usuario->sector_codigo = null;
        }
    
        $usuario->save();
    
        return redirect()->route('users.showUsers')->with('success', 'Usuario actualizado correctamente.');
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
    
}