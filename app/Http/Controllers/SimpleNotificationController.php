<?php

// app/Http/Controllers/SimpleNotificationController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SimpleNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SimpleNotificationController extends Controller
{


    public function marcarLeidas(Request $request)
    {
        // Log::info('metodo marcarLeidas');
        try {
            $notificaciones = SimpleNotification::where('coordinador_codigo', Auth::user()->usu_codigo)
            ->where('leida', false)
            ->get();

        foreach ($notificaciones as $notificacion) {
            $notificacion->update(['leida' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notificaciones marcadas como leídas'
        ]);
        } catch (\Throwable $e) {
            Log::error('Error al marcar las notificaciones como leídas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar las notificaciones como leídas'
            ]);
        }
    }

    public function index()
    {
        $baseQuery = SimpleNotification::where('coordinador_codigo', Auth::user()->usu_codigo)
            ->where(function ($query) {
                $query->whereNull('sender_codigo')
                    ->orWhere('sender_codigo', '!=', Auth::user()->usu_codigo);
            });

        $notificaciones = (clone $baseQuery)
            ->orderBy('created_at', 'desc')
            ->with(['instancia', 'sender'])
            ->paginate(10);

        $totalNoLeidas = (clone $baseQuery)->where('leida', false)->count();

        return view('notificaciones.index', compact('notificaciones', 'totalNoLeidas'));
    }

    public function show($id)
    {
        $notificacion = SimpleNotification::where('coordinador_codigo', Auth::user()->usu_codigo)
            ->with(['instancia', 'sender'])
            ->findOrFail($id);

        if (! $notificacion->leida) {
            $notificacion->update(['leida' => true]);
        }

        $detalleSolicitud = $notificacion->detalleSolicitudCambio();

        return view('notificaciones.show', compact('notificacion', 'detalleSolicitud'));
    }
    public function marcarComoLeida($id)
    {
        $notificacion = SimpleNotification::findOrFail($id);
        $notificacion->update(['leida' => true]);
        
        return back()->with('success', 'Notificación marcada como leída');
    }

    public function marcarTodasComoLeidas()
    {
        SimpleNotification::where('coordinador_codigo', Auth::user()->usu_codigo)
            ->where('leida', false)
            ->update(['leida' => true]);
            
        return back()->with('success', 'Todas las notificaciones marcadas como leídas');
    }


}