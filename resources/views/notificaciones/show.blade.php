@extends('layouts.app')

@section('title', 'Detalle de notificación')

@section('content')
@include('partials.operativo-styles')
<link rel="stylesheet" href="{{ asset('css/notificaciones.css') }}?v={{ filemtime(public_path('css/notificaciones.css')) }}">

<div class="container py-4 ucrud notif-page" data-ucrud-root>
    @include('partials.ucrud-form-header', [
        'title' => $detalleSolicitud ? 'Solicitud de cambio en orden de trabajo' : 'Detalle de notificación',
        'subtitle' => $notificacion->created_at->locale('es')->translatedFormat('l, j \d\e F \d\e Y, H:i'),
        'backUrl' => route('notificaciones.index'),
        'backLabel' => 'Volver a notificaciones',
    ])

    <div class="ucrud-panel notif-detail-panel">
        <div class="notif-detail__head {{ $notificacion->leida ? '' : 'notif-detail__head--unread' }}">
            <h2 class="notif-detail__title">
                @if($detalleSolicitud)
                    {{ $detalleSolicitud['contexto'] }}
                @else
                    Notificación del sistema
                @endif
            </h2>
            <p class="notif-detail__date mb-0">
                {{ $notificacion->created_at->locale('es')->diffForHumans() }}
                · {{ $notificacion->created_at->locale('es')->translatedFormat('d/m/Y H:i') }}
            </p>
        </div>

        <div class="notif-detail__body">
            @if($detalleSolicitud)
                <dl class="row notif-detail__dl mb-0">
                    <dt class="col-sm-3">Orden / muestra</dt>
                    <dd class="col-sm-9">{{ $detalleSolicitud['contexto'] }}</dd>

                    <dt class="col-sm-3">Solicitado por</dt>
                    <dd class="col-sm-9">
                        {{ $detalleSolicitud['solicitante_nombre'] }}
                        <span class="text-muted">({{ $detalleSolicitud['solicitante_codigo'] }})</span>
                    </dd>

                    <dt class="col-sm-3">Motivo</dt>
                    <dd class="col-sm-9">
                        <div class="notif-detail__motivo">{{ $detalleSolicitud['motivo'] }}</div>
                    </dd>
                </dl>
            @else
                <p class="notif-detail__message">{{ $notificacion->mensaje }}</p>
                @if($notificacion->sender)
                    <p class="notif-detail__sender mb-0">
                        Enviado por {{ $notificacion->sender->usu_descripcion ?? $notificacion->sender_codigo }}
                    </p>
                @endif
            @endif
        </div>

        @if($notificacion->url)
            <div class="notif-detail__actions">
                <a href="{{ $notificacion->url }}" class="ucrud-btn ucrud-btn--primary">
                    <x-heroicon-o-arrow-right style="width: 16px; height: 16px;" />
                    Ir al recurso relacionado
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
