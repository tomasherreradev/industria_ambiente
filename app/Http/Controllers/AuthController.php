<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;


class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }
    

    public function login(Request $request) 
    {
        Log::info('Intento de login iniciado', ['usu_codigo' => $request->usu_codigo]);
    
        $request->validate([
            'usu_codigo' => 'required',
            'usu_clave' => 'required',
        ]);
    
        $user = User::where('usu_codigo', $request->usu_codigo)->first();
    
        if (!$user) {
            Log::warning('Usuario no encontrado', ['usu_codigo' => $request->usu_codigo]);
            return back()->withErrors(['usu_codigo' => 'Usuario no encontrado']);
        }
    
        $inputPassword = md5($request->usu_clave); 
        $storedPassword = $user->usu_clave;
    
        Log::debug('Comparando contraseñas', [
            'inputPassword' => $inputPassword,
            'storedPassword' => $storedPassword,
            'usu_codigo' => $request->usu_codigo
        ]);
    
        if ($inputPassword === $storedPassword) {
            Auth::login($user, true);
            // Forzar "último login gana": regenerar session y persistir el session_id vigente.
            $request->session()->regenerate();
            try {
                $user->current_session_id = $request->session()->getId();
                $user->save();
            } catch (\Throwable $e) {
                Log::warning('No se pudo guardar current_session_id', [
                    'usu_codigo' => $user->usu_codigo,
                    'error' => $e->getMessage(),
                ]);
            }
            Log::info('Login exitoso', [
                'usu_codigo' => $user->usu_codigo,
                'rol' => $user->rol,
                'nivel' => $user->usu_nivel
            ]);
    
            // Redirección basada en roles (incluye rol principal y roles adicionales)
            if ($user->usu_nivel >= 900) {
                return redirect()->intended('/dashboard');
            } elseif($user->hasRole('muestreador')) {
                return redirect()->intended('/mis-tareas');
            } elseif($user->hasRole('laboratorio')) {
                return redirect()->intended('/mis-ordenes');
            } elseif(($user->bandeja_solo_informes ?? false) && $user->hasAnyRole(['coordinador_lab', 'coordinador_mediciones'])) {
                return redirect()->intended('/informes');
            } elseif($user->hasRole('coordinador_lab')) {
                return redirect()->intended('/dashboard/analisis');
            } elseif($user->hasRole('coordinador_muestreo')) {
                return redirect()->intended('/dashboard/muestreo');
            } elseif($user->hasRole('facturador')) {
                return redirect()->intended('/facturacion');
            } elseif($user->hasRole('ventas')) {
                return redirect()->intended('/ventas');
            } elseif($user->hasRole('firmador')) {
                return redirect()->intended('/informes');
            } elseif($user->hasRole('cliente')) {
                return redirect()->intended('/customers');
            } elseif($user->hasRole('cadena_custodia')) {
                return redirect()->intended('/muestras');
            } elseif($user->hasRole('coordinador_consul')) {
                return redirect()->intended(route('consultoria.index'));
            } elseif($user->hasRole('coordinador_mediciones')) {
                return redirect()->intended(route('mediciones.index'));
            } elseif($user->hasRole('asp')) {
                return redirect()->intended(route('asp.index'));
            } elseif($user->hasRole('clarke_fire')) {
                return redirect()->intended(route('clarke-fire.index'));
            } else {
                Log::notice('Usuario logueado pero sin rol específico', ['usu_codigo' => $user->usu_codigo, 'rol_principal' => $user->rol]);
                return redirect()->intended('/login');
            }
        } 
    
        Log::error('Contraseña incorrecta', ['usu_codigo' => $request->usu_codigo]);
        return back()->withErrors(['usu_clave' => 'Contraseña incorrecta']);
    }

    
    public function logout(Request $request)
    {
        // Limpiar marca de sesión vigente (best effort).
        try {
            $u = Auth::user();
            if ($u) {
                $u->current_session_id = null;
                $u->save();
            }
        } catch (\Throwable $e) {
            // no-op
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return view('auth.show', compact('user'));
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('auth.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $editor = Auth::user();
        $isAdmin = (int) ($editor->usu_nivel ?? 0) >= 900;
        $rolesValidos = [
            'laboratorio', 'muestreador', 'coordinador_lab', 'coordinador_muestreo', 'facturador',
            'ventas', 'firmador', 'coordinador_consul', 'coordinador_mediciones', 'asp', 'clarke_fire',
            'cliente', 'cadena_custodia',
        ];

        $rules = [
            'usu_descripcion' => 'required|string|max:255',
            'usu_clave' => 'nullable|string|min:4',
        ];

        if ($isAdmin) {
            $rules['usu_nivel'] = 'nullable|integer|min:0|max:9999';
            $rules['usu_estado'] = 'nullable|in:0,1';
            $rules['rol'] = ['nullable', 'string', 'max:50', Rule::in($rolesValidos)];
            $rules['roles_adicionales'] = 'nullable|array';
            $rules['roles_adicionales.*'] = ['string', 'max:50', Rule::in($rolesValidos)];
        }

        $validated = $request->validate($rules);

        $data = ['usu_descripcion' => $validated['usu_descripcion']];

        if ($isAdmin) {
            if (array_key_exists('usu_nivel', $validated) && $validated['usu_nivel'] !== null) {
                $data['usu_nivel'] = (int) $validated['usu_nivel'];
            }
            if (array_key_exists('usu_estado', $validated)) {
                $data['usu_estado'] = (bool) $validated['usu_estado'];
            }
            if (!empty($validated['rol'])) {
                $data['rol'] = $validated['rol'];
            }
        }

        if (!empty($validated['usu_clave'])) {
            $data['usu_clave'] = md5($validated['usu_clave']);
        }

        $user->fill($data);
        $user->save();

        if ($isAdmin) {
            $principal = (string) ($user->rol ?? '');
            $adicionales = array_values(array_filter(
                array_map('strval', (array) $request->input('roles_adicionales', [])),
                fn ($r) => $r !== '' && $r !== $principal
            ));
            $user->syncRoles($adicionales);
        }

        return redirect()->route('auth.show', $user->usu_codigo)->with('success', 'Perfil actualizado correctamente.');
    }
    
    public function showSecurity($id)
    {
        $user = User::findOrFail($id);
        return view('auth.security', compact('user'));
    }
    
    public function showHelp($id)
    {
        $user = User::findOrFail($id);
        return view('auth.help', compact('user'));
    }

    /**
     * Ruta rápida para actualizar contraseñas de usuarios.
     * GET /actualizar-pass?ventas=ventas&facturador=facturador&firmador=firmador
     */
    public function actualizarPassRapido(Request $request)
    {
        $actualizaciones = [
            'ventas' => 'ventas',
            'facturador' => 'facturador',
            'firmador' => 'firmador',
        ];

        // Permitir sobrescribir con parámetros de la request
        foreach (['ventas', 'facturador', 'firmador'] as $codigo) {
            if ($request->has($codigo)) {
                $actualizaciones[$codigo] = $request->input($codigo);
            }
        }

        $resultados = [];
        foreach ($actualizaciones as $usuCodigo => $pass) {
            $user = User::where('usu_codigo', $usuCodigo)->first();
            if ($user) {
                $user->usu_clave = md5($pass);
                $user->save();
                $resultados[$usuCodigo] = 'OK';
            } else {
                $resultados[$usuCodigo] = 'No encontrado';
            }
        }

        return response()->json([
            'message' => 'Contraseñas actualizadas',
            'resultados' => $resultados,
        ]);
    }
}
