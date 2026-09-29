@extends('layouts.app')

@section('title', 'Documentación — Mediciones')

@section('content')
<link rel="stylesheet" href="{{ asset('css/informes-index.css') }}?v={{ filemtime(public_path('css/informes-index.css')) }}">

@php
    $vistaActiva = $vistaActiva ?? 'no_subidos';
    $estadisticas = $estadisticas ?? [
        'sin_subir' => 0,
        'subidos' => 0,
        'cotizaciones_sin_subir' => 0,
        'cotizaciones_subidos' => 0,
    ];
    $hayFiltros = request()->filled('search')
        || request()->filled('matriz')
        || request()->filled('estado')
        || request()->filled('fecha_inicio_muestreo')
        || request()->filled('fecha_fin_muestreo');
    $medicionesPorCotizacion = $medicionesPorCotizacion ?? collect();
@endphp

<div class="container py-4 informes-page mediciones-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="inf-title mb-1">Mediciones</h1>
            <p class="inf-subtitle mb-0">
                @if($vistaActiva === 'subidos')
                    Informes PDF cargados — pendientes de aprobación o ya enviados a informes
                @else
                    Mediciones en documentación sin informe PDF subido
                @endif
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card text-white bg-primary stats-card-informes {{ $vistaActiva === 'no_subidos' ? 'active' : '' }}"
                 data-vista="no_subidos" role="button" tabindex="0" aria-label="Ver sin informe subido">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">No subidos</h6>
                            <h3 class="card-text mb-0 fw-bold">{{ number_format($estadisticas['sin_subir'] ?? 0, 0, ',', '.') }}</h3>
                        </div>
                        <x-heroicon-o-arrow-up-tray style="width: 28px; height: 28px;" class="opacity-50" />
                    </div>
                    <small class="opacity-75">{{ $estadisticas['cotizaciones_sin_subir'] ?? 0 }} cotizaciones con pendientes</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-white bg-success stats-card-informes {{ $vistaActiva === 'subidos' ? 'active' : '' }}"
                 data-vista="subidos" role="button" tabindex="0" aria-label="Ver informes subidos">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Subidos</h6>
                            <h3 class="card-text mb-0 fw-bold">{{ number_format($estadisticas['subidos'] ?? 0, 0, ',', '.') }}</h3>
                        </div>
                        <x-heroicon-o-check-circle style="width: 28px; height: 28px;" class="opacity-50" />
                    </div>
                    <small class="opacity-75">{{ $estadisticas['cotizaciones_subidos'] ?? 0 }} cotizaciones con al menos un PDF</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-semibold">Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('mediciones.index') }}" id="filterFormMediciones">
                <input type="hidden" name="vista" id="vistaInputMediciones" value="{{ $vistaActiva }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label small fw-semibold text-muted">Buscar</label>
                        <input type="text" class="form-control" id="search" name="search"
                               placeholder="Número, empresa o establecimiento"
                               value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <label for="estado" class="form-label small fw-semibold text-muted">Estado cotización</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Aprobadas (default)</option>
                            <option value="A" @selected(request('estado') === 'A')>Aprobado</option>
                            <option value="E" @selected(request('estado') === 'E')>En espera</option>
                            <option value="S" @selected(request('estado') === 'S')>Rechazado</option>
                            @if(request()->query('verTodas'))
                                <option value="" selected>Todos (verTodas)</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="matriz" class="form-label small fw-semibold text-muted">Matriz</label>
                        <select class="form-select" id="matriz" name="matriz">
                            <option value="">Todas</option>
                            @foreach($matrices as $matriz)
                                <option value="{{ $matriz->matriz_codigo }}" @selected(request('matriz') == $matriz->matriz_codigo)>
                                    {{ $matriz->matriz_descripcion }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_inicio_muestreo" class="form-label small fw-semibold text-muted">Alta desde</label>
                        <input type="date" class="form-control" id="fecha_inicio_muestreo" name="fecha_inicio_muestreo"
                               value="{{ request('fecha_inicio_muestreo') }}">
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_fin_muestreo" class="form-label small fw-semibold text-muted">Alta hasta</label>
                        <input type="date" class="form-control" id="fecha_fin_muestreo" name="fecha_fin_muestreo"
                               value="{{ request('fecha_fin_muestreo') }}">
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        @if($hayFiltros)
                            <a href="{{ route('mediciones.index', ['vista' => $vistaActiva]) }}" class="btn btn-outline-secondary">
                                Limpiar
                            </a>
                        @endif
                        <button type="submit" class="btn btn-primary px-4">Filtrar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-4">
        <div class="inf-segmented" role="tablist" aria-label="Bandeja de mediciones">
            <button type="button" class="inf-segmented__btn {{ $vistaActiva === 'no_subidos' ? 'active' : '' }}"
                    data-vista="no_subidos" role="tab">
                <x-heroicon-o-arrow-up-tray style="width: 18px; height: 18px;" />
                No subidos
                @if(($estadisticas['sin_subir'] ?? 0) > 0)
                    <span class="badge rounded-pill bg-primary ms-1">{{ $estadisticas['sin_subir'] }}</span>
                @endif
            </button>
            <button type="button" class="inf-segmented__btn {{ $vistaActiva === 'subidos' ? 'active' : '' }}"
                    data-vista="subidos" role="tab">
                <x-heroicon-o-check-circle style="width: 18px; height: 18px;" />
                Subidos
                @if(($estadisticas['subidos'] ?? 0) > 0)
                    <span class="badge rounded-pill bg-success ms-1">{{ $estadisticas['subidos'] }}</span>
                @endif
            </button>
        </div>
    </div>

    @if($medicionesPorCotizacion->isEmpty())
        <div class="inf-empty-state">
            <div class="inf-empty-state__icon">
                @if($vistaActiva === 'subidos')
                    <x-heroicon-o-check-circle style="width: 48px; height: 48px;" />
                @else
                    <x-heroicon-o-arrow-up-tray style="width: 48px; height: 48px;" />
                @endif
            </div>
            <h5 class="fw-semibold mb-2">
                @if($vistaActiva === 'subidos')
                    No hay informes PDF subidos
                @else
                    No hay mediciones pendientes de informe
                @endif
            </h5>
            <p class="text-muted mb-0">
                @if($hayFiltros)
                    Probá ajustar los filtros o limpiarlos para ver más resultados.
                @elseif($vistaActiva === 'subidos')
                    Los PDF cargados desde cada medición aparecerán aquí.
                @else
                    Todas las mediciones visibles ya tienen informe subido, o aún no hay muestras en documentación.
                @endif
            </p>
        </div>
    @else
        @include('mediciones.partials.lista')
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function cambiarVistaMediciones(vista) {
        const input = document.getElementById('vistaInputMediciones');
        if (input) {
            input.value = vista;
        }
        const form = document.getElementById('filterFormMediciones');
        if (form) {
            form.submit();
            return;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('vista', vista);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    document.querySelectorAll('.mediciones-page [data-vista]').forEach(el => {
        el.addEventListener('click', function () {
            const vista = this.getAttribute('data-vista');
            if (vista) {
                cambiarVistaMediciones(vista);
            }
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const vista = this.getAttribute('data-vista');
                if (vista) {
                    cambiarVistaMediciones(vista);
                }
            }
        });
    });
});
</script>
@endpush
