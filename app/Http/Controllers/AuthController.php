<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;


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
            Log::info('Login exitoso', [
                'usu_codigo' => $user->usu_codigo,
                'rol' => $user->rol,
                'nivel' => $user->usu_nivel
            ]);
    
            $userRole = trim($user->rol);
            
            if ($user->usu_nivel >= 900) {
                return redirect()->intended('/dashboard');
            } elseif($userRole == 'muestreador') {
                return redirect()->intended('/mis-tareas');
            } elseif($userRole == 'laboratorio') {
                return redirect()->intended('/mis-ordenes');
            } elseif($userRole == 'coordinador_lab') {
                return redirect()->intended('/dashboard/analisis');
            } elseif($userRole == 'coordinador_muestreo') {
                return redirect()->intended('/dashboard/muestreo');
            } elseif($userRole == 'facturador') {
                return redirect()->intended('/facturacion');
            } elseif($userRole == 'ventas') {
                return redirect()->intended('/ventas');
            } elseif($userRole == 'firmador') {
                return redirect()->intended('/informes');
            } elseif($userRole == 'cliente') {
                return redirect()->intended('/customers');
            } else {
                Log::notice('Usuario logueado pero sin rol específico', ['usu_codigo' => $user->usu_codigo, 'rol' => "'{$userRole}'"]);
                return redirect()->intended('/login');
            }
        } 
    
        Log::error('Contraseña incorrecta', ['usu_codigo' => $request->usu_codigo]);
        return back()->withErrors(['usu_clave' => 'Contraseña incorrecta']);
    }

    
    public function logout(Request $request)
    {
        Auth::logout();
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
        $isAdmin = $user->usu_nivel >= 900;
    
        // Filtrar solo los campos que se deben actualizar
        $data = $request->only(['usu_descripcion', 'usu_clave', 'usu_codigo']);
    
        if ($isAdmin) {
            $data = array_merge($data, $request->only([
                'usu_correo', 'usu_nivel', 'usu_estado', 'rol'
            ]));
        }
    
        // Verificar si se envió una nueva clave, si es así, encriptarla con MD5
        if (!empty($data['usu_clave'])) {
            $data['usu_clave'] = md5($data['usu_clave']);  // Encriptado con MD5
        } else {
            unset($data['usu_clave']); // Si no se proporciona clave, no se actualiza
        }
    
        // Llenar y guardar el usuario con los nuevos datos
        $user->fill($data);
        $user->save();
    
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
    
}
