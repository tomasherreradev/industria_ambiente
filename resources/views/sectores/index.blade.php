@extends('layouts.app')

@section('title', 'Laboratorios')

@section('content')
<link rel="stylesheet" href="{{ asset('css/usuarios-crud.css') }}?v={{ filemtime(public_path('css/usuarios-crud.css')) }}">

<div class="container py-4 ucrud" data-ucrud-root>

    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Laboratorios
                <span class="ucrud-count">{{ $sectores->total() }}</span>
            </h1>
            <p class="ucrud-subtitle">Sectores de laboratorio disponibles para asignar a los usuarios.</p>
        </div>

        <div class="ucrud-header__actions">
            @include('partials.ucrud-nav', ['activo' => 'laboratorios'])

            <a href="{{ route('sectores.create') }}" class="ucrud-btn ucrud-btn--primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                Nuevo laboratorio
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="ucrud-alert ucrud-alert--success" role="status">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <path d="m8.5 12.5 2.5 2.5 4.5-5"/>
            </svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="ucrud-panel">
        @if($sectores->isEmpty())
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 3h6"/>
                        <path d="M10 3v6.4L4.6 18a2 2 0 0 0 1.7 3h11.4a2 2 0 0 0 1.7-3L14 9.4V3"/>
                        <path d="M7.2 15h9.6"/>
                    </svg>
                </div>
                <p class="ucrud-empty__title">No hay laboratorios cargados</p>
                <p class="ucrud-empty__text">Creá el primer laboratorio para poder asignarlo a los usuarios.</p>
            </div>
        @else
            {{-- Escritorio --}}
            <div class="d-none d-lg-block ucrud-tablewrap">
                <table class="ucrud-table">
                    <thead>
                        <tr>
                            <th>Laboratorio</th>
                            <th>Código</th>
                            <th>Rol</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sectores as $i => $sector)
                            @php
                                $nombre = trim((string) $sector->usu_descripcion);
                                $codigo = trim((string) $sector->usu_codigo);
                                $partes = array_values(array_filter(preg_split('/\s+/', $nombre)));
                                $iniciales = mb_strtoupper(
                                    mb_substr($partes[0] ?? $codigo, 0, 1)
                                    . (count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '')
                                );
                                $tono = crc32($codigo) % 360;
                            @endphp
                            <tr class="ucrud-animate-in" style="--i: {{ $i }}">
                                <td>
                                    <div class="ucrud-user">
                                        <span class="ucrud-avatar" style="background: linear-gradient(135deg, hsl({{ $tono }} 55% 58%), hsl({{ ($tono + 28) % 360 }} 60% 45%));" aria-hidden="true">{{ $iniciales }}</span>
                                        <span class="ucrud-user__name">{{ $nombre }}</span>
                                    </div>
                                </td>
                                <td><span class="ucrud-code">{{ $codigo }}</span></td>
                                <td>
                                    @if($sector->rol)
                                        <span class="ucrud-chip ucrud-chip--cyan">{{ $sector->rol }}</span>
                                    @else
                                        <span class="ucrud-chip ucrud-chip--muted">Sin rol</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="ucrud-actions">
                                        <a class="ucrud-iconbtn"
                                           href="{{ url('/sectores/' . $sector->usu_codigo) }}"
                                           title="Editar laboratorio"
                                           aria-label="Editar {{ $nombre }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M12 20h9"/>
                                                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="d-block d-lg-none">
                @foreach($sectores as $i => $sector)
                    @php
                        $nombre = trim((string) $sector->usu_descripcion);
                        $codigo = trim((string) $sector->usu_codigo);
                        $partes = array_values(array_filter(preg_split('/\s+/', $nombre)));
                        $iniciales = mb_strtoupper(
                            mb_substr($partes[0] ?? $codigo, 0, 1)
                            . (count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '')
                        );
                        $tono = crc32($codigo) % 360;
                        $activo = (bool) trim((string) $sector->usu_estado);
                    @endphp
                    <a href="{{ url('/sectores/' . $sector->usu_codigo) }}" class="ucrud-card ucrud-animate-in" style="--i: {{ $i }}">
                        <span class="ucrud-avatar" style="background: linear-gradient(135deg, hsl({{ $tono }} 55% 58%), hsl({{ ($tono + 28) % 360 }} 60% 45%));" aria-hidden="true">{{ $iniciales }}</span>
                        <span class="ucrud-card__body">
                            <span class="ucrud-user__name d-block">{{ $nombre }}</span>
                            <span class="ucrud-card__meta">
                                <span class="ucrud-code">{{ $codigo }}</span>
                                <span class="ucrud-chip {{ $activo ? 'ucrud-chip--green' : 'ucrud-chip--muted' }}">{{ $activo ? 'Activo' : 'Inactivo' }}</span>
                            </span>
                        </span>
                        <span class="ucrud-card__chevron" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m9 6 6 6-6 6"/>
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>

            @if($sectores->hasPages())
                <div class="ucrud-pagination">
                    {{ $sectores->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/usuarios-crud.js') }}?v={{ filemtime(public_path('js/usuarios-crud.js')) }}"></script>
@endpush
