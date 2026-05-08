<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsultoriaController extends Controller
{
    public function index(Request $request, MuestrasController $muestrasController): View
    {
        return $muestrasController->portalListaPorCanalEnsayo(
            $request,
            'consultoria',
            'Consultoría',
            'consultoria.index'
        );
    }
}
