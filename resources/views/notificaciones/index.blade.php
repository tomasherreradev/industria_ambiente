<!-- resources/views/notificaciones/index.blade.php -->
@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Mis Notificaciones</h1>
        <form action="{{ route('notificaciones.leer-todas') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary">
                Marcar todas como leídas
            </button>
        </form>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mensaje</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notificaciones as $notificacion)
                        <tr class="{{ $notificacion->leida ? '' : 'table-active' }}">
                            <td>
                                <a href="{{ route('notificaciones.show', $notificacion->id) }}" class="text-decoration-none text-dark">
                                    {{ $notificacion->resumenCorto(120) }}
                                </a>
                            </td>
                            <td>{{ $notificacion->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('notificaciones.show', $notificacion->id) }}" class="btn btn-sm btn-primary">
                                    Ver detalle
                                </a>
                                @if(!$notificacion->leida)
                                <form action="{{ route('notificaciones.leida', $notificacion->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        Marcar como leída
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">
                                No hay notificaciones
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($notificaciones->hasPages())
        <div class="mt-3">
            {{ $notificaciones->links() }}
        </div>
    @endif
</div>
@endsection
