@extends('layouts.app')

@section('title', 'Notificaciones')

@section('content')
@include('partials.operativo-styles')
<link rel="stylesheet" href="{{ asset('css/notificaciones.css') }}?v={{ filemtime(public_path('css/notificaciones.css')) }}">

<div class="container py-4 ucrud notif-page" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Notificaciones
                @if($totalNoLeidas > 0)
                    <span class="ucrud-count">{{ $totalNoLeidas }} sin leer</span>
                @endif
            </h1>
            <p class="ucrud-subtitle">Historial de avisos y cambios relevantes para tu bandeja.</p>
        </div>

        @if($totalNoLeidas > 0)
            <div class="ucrud-header__actions">
                <form action="{{ route('notificaciones.leer-todas') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="ucrud-btn ucrud-btn--ghost">
                        <x-heroicon-o-check style="width: 16px; height: 16px;" />
                        Marcar todas como leídas
                    </button>
                </form>
            </div>
        @endif
    </header>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="ucrud-panel">
        @if($notificaciones->isEmpty())
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-bell style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">No hay notificaciones</p>
                <p class="ucrud-empty__text">Cuando haya novedades en tu bandeja, las verás acá.</p>
            </div>
        @else
            <div class="notif-list">
                @foreach($notificaciones as $notificacion)
                    <div class="notif-card {{ $notificacion->leida ? '' : 'notif-card--unread' }}">
                        <div class="notif-card__icon">
                            <x-heroicon-o-bell />
                        </div>

                        <div class="notif-card__main">
                            <p class="notif-card__text">{{ $notificacion->resumenCorto(160) }}</p>
                            <div class="notif-card__meta">
                                <span>{{ $notificacion->created_at->locale('es')->translatedFormat('d/m/Y H:i') }}</span>
                                <span>·</span>
                                <span>{{ $notificacion->created_at->locale('es')->diffForHumans() }}</span>
                                @if(! $notificacion->leida)
                                    <span class="notif-card__badge">Sin leer</span>
                                @endif
                                @if($notificacion->sender)
                                    <span>·</span>
                                    <span>{{ $notificacion->sender->usu_descripcion ?? $notificacion->sender_codigo }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="notif-card__actions">
                            <a href="{{ route('notificaciones.show', $notificacion->id) }}" class="ucrud-btn ucrud-btn--primary ucrud-btn--sm">
                                Ver detalle
                            </a>
                            @if(! $notificacion->leida)
                                <form action="{{ route('notificaciones.leida', $notificacion->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">
                                        Marcar leída
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if($notificaciones->hasPages())
        <div class="ucrud-panel ucrud-pagination mt-3">
            {{ $notificaciones->links() }}
        </div>
    @endif
</div>
@endsection
