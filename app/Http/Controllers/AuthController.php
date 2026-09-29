<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Support\PerfilUsuarioResumen;


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

    public function show(Request $request, $id)
    {
        $user = $this->findUserByCodigo($id);
        $this->authorizeOwnProfile($user);

        $codigoCanonico = trim((string) $user->usu_codigo);
        if (trim((string) $id) !== $codigoCanonico) {
            return redirect()->route('auth.show', ['id' => $codigoCanonico]);
        }

        $authUser = Auth::user();
        $esPropioPerfil = $authUser && trim((string) $authUser->usu_codigo) === $codigoCanonico;
        $sessionId = (string) $request->session()->getId();
        $expectedSession = (string) ($user->current_session_id ?? '');
        $sesionEsActual = $expectedSession !== '' && hash_equals($expectedSession, $sessionId);

        $perfil = PerfilUsuarioResumen::construir($user);

        return view('auth.show', compact('user', 'perfil', 'esPropioPerfil', 'sesionEsActual', 'codigoCanonico'));
    }

    public function edit($id)
    {
        $user = $this->findUserByCodigo($id);
        return view('auth.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = $this->findUserByCodigo($id);
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
    
    public function showSecurity(Request $request, $id)
    {
        $user = $this->findUserByCodigo($id);
        $this->authorizeOwnProfile($user);

        $codigoCanonico = trim((string) $user->usu_codigo);
        if (trim((string) $id) !== $codigoCanonico) {
            return redirect()->route('auth.security', ['id' => $codigoCanonico]);
        }

        $authUser = Auth::user();
        $esPropioPerfil = trim((string) $authUser->usu_codigo) === $codigoCanonico;
        $sessionId = (string) $request->session()->getId();
        $expectedSession = (string) ($user->current_session_id ?? '');
        $sesionEsActual = $expectedSession !== '' && hash_equals($expectedSession, $sessionId);
        $dispositivo = $this->describeUserAgent($request->userAgent());

        return view('auth.security', [
            'user' => $user,
            'esPropioPerfil' => $esPropioPerfil,
            'sesionEsActual' => $sesionEsActual,
            'dispositivo' => $dispositivo,
            'ipActual' => $request->ip(),
        ]);
    }

    public function updateSecurityPassword(Request $request, $id)
    {
        $user = $this->findUserByCodigo($id);
        $this->authorizeOwnProfile($user);

        if (trim((string) Auth::user()->usu_codigo) !== trim((string) $user->usu_codigo)) {
            abort(403, 'Solo podés cambiar tu propia contraseña desde esta pantalla.');
        }

        $validated = $request->validate([
            'usu_clave_actual' => 'required|string',
            'usu_clave' => 'required|string|min:4|confirmed',
        ], [
            'usu_clave.min' => 'La nueva contraseña debe tener al menos 4 caracteres.',
            'usu_clave.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
        ]);

        if (md5($validated['usu_clave_actual']) !== $user->usu_clave) {
            return back()
                ->withErrors(['usu_clave_actual' => 'La contraseña actual no es correcta.'])
                ->withInput($request->except('usu_clave_actual', 'usu_clave', 'usu_clave_confirmation'));
        }

        $user->usu_clave = md5($validated['usu_clave']);
        $user->save();

        return redirect()
            ->route('auth.security', ['id' => trim((string) $user->usu_codigo)])
            ->with('success', 'Contraseña actualizada correctamente.');
    }

    public function showHelp($id)
    {
        $user = $this->findUserByCodigo($id);
        $this->authorizeOwnProfile($user);

        $codigoCanonico = trim((string) $user->usu_codigo);
        if (trim((string) $id) !== $codigoCanonico) {
            return redirect()->route('auth.help', ['id' => $codigoCanonico]);
        }

        return view('auth.help', [
            'user' => $user,
            'soporteEmail' => 'app@initsoluciones.com.ar',
        ]);
    }

    protected function findUserByCodigo(string $id): User
    {
        $user = User::whereRaw('TRIM(usu_codigo) = ?', [trim($id)])->first();
        if (! $user) {
            abort(404);
        }

        return $user;
    }

    protected function authorizeOwnProfile(User $user): void
    {
        $authUser = Auth::user();
        if (! $authUser) {
            abort(403);
        }

        $isAdmin = (int) ($authUser->usu_nivel ?? 0) >= 900;
        if ($isAdmin) {
            return;
        }

        if (trim((string) $authUser->usu_codigo) !== trim((string) $user->usu_codigo)) {
            abort(403, 'No tenés permiso para ver la seguridad de este usuario.');
        }
    }

    /**
     * @return array{browser: string, platform: string, label: string}
     */
    protected function describeUserAgent(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        $platform = 'Desconocido';
        if (str_contains($ua, 'Windows')) {
            $platform = 'Windows';
        } elseif (str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh')) {
            $platform = 'macOS';
        } elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
            $platform = 'iOS';
        } elseif (str_contains($ua, 'Android')) {
            $platform = 'Android';
        } elseif (str_contains($ua, 'Linux')) {
            $platform = 'Linux';
        }

        $browser = 'Navegador';
        if (preg_match('/Edg\//', $ua)) {
            $browser = 'Edge';
        } elseif (str_contains($ua, 'Chrome/')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'Firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/')) {
            $browser = 'Safari';
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
            'label' => $browser.' · '.$platform,
        ];
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
