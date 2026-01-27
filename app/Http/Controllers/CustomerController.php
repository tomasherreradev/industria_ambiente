<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function index()
    {
        // Verificar que el usuario tenga rol de cliente
        if (Auth::check() && trim(Auth::user()->rol) === 'cliente') {
            return view('customers.index');
        }
        
        // Si no es cliente, redirigir al login
        return redirect('/login')->with('error', 'Acceso no autorizado');
    }
}

