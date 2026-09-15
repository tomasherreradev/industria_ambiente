@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <a href="{{ route('notificaciones.index') }}" class="btn btn-outline-secondary btn-sm">← Volver a notificaciones</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center {{ $notificacion->leida ? 'bg-light' : 'bg-primary text-white' }}">
            <h1 class="h5 mb-0">
                @if($detalleSolicitud)
                    Solicitud de cambio en orden de trabajo
                @else
                    Notificación
                @endif
            </h1>
            <small class="{{ $notificacion->leida ? 'text-muted' : 'text-white-50' }}">
                {{ $notificacion->created_at->format('d/m/Y H:i') }}
            </small>
        </div>
        <div class="card-body">
            @if($detalleSolicitud)
                <dl class="row mb-0">
                    <dt class="col-sm-3">Orden / muestra</dt>
                    <dd class="col-sm-9">{{ $detalleSolicitud['contexto'] }}</dd>

                    <dt class="col-sm-3">Solicitado por</dt>
                    <dd class="col-sm-9">
                        {{ $detalleSolicitud['solicitante_nombre'] }}
                        <span class="text-muted">({{ $detalleSolicitud['solicitante_codigo'] }})</span>
                    </dd>

                    <dt class="col-sm-3">Motivo</dt>
                    <dd class="col-sm-9">
                        <div class="border rounded bg-light p-3 mb-0" style="white-space: pre-wrap;">{{ $detalleSolicitud['motivo'] }}</div>
                    </dd>
                </dl>
            @else
                <p class="mb-0" style="white-space: pre-wrap;">{{ $notificacion->mensaje }}</p>
                @if($notificacion->sender)
                    <hr>
                    <p class="text-muted small mb-0">
                        Enviado por {{ $notificacion->sender->usu_descripcion ?? $notificacion->sender_codigo }}
                    </p>
                @endif
            @endif

            @if($notificacion->url)
                <div class="mt-4 pt-3 border-top">
                    <a href="{{ $notificacion->url }}" class="btn btn-primary">
                        <i class="fas fa-external-link-alt me-1"></i> Ir a la orden de trabajo
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
