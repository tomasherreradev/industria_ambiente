@extends('layouts.app')

@section('content')
<div class="container py-3 py-md-4">
    <header class="d-flex flex-md-row justify-content-between align-items-center align-items-md-center">
        <h1 class="mb-md-4">{{ $portalTitulo }}</h1>

        <div class="d-flex gap-2 align-items-center mb-2">
            <button class="btn btn-sm btn-outline-primary me-2" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapseSearchCanalPortal" aria-expanded="false" aria-controls="collapseSearchCanalPortal"
                    id="searchToggleBtnCanalPortal">
                <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" class="me-1"/>
                <span class="d-none d-sm-inline">Buscar</span>
            </button>
        </div>
    </header>

    <div class="collapse mb-4" id="collapseSearchCanalPortal">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route($portalRouteName) }}" class="row g-3">
                    <input type="hidden" name="view" value="lista">

                    <div class="col-md-4">
                        <label for="search" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="search" name="search"
                               placeholder="Número, empresa o establecimiento"
                               value="{{ request('search') }}">
                    </div>

                    <div class="col-md-2">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="A" {{ request('estado') == 'A' ? 'selected' : '' }}>Aprobado</option>
                            <option value="E" {{ request('estado') == 'E' ? 'selected' : '' }}>En espera</option>
                            <option value="S" {{ request('estado') == 'S' ? 'selected' : '' }}>Rechazado</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="matriz" class="form-label">Matriz</label>
                        <select class="form-select" id="matriz" name="matriz">
                            <option value="">Todas</option>
                            @foreach($matrices as $matriz)
                                <option value="{{ $matriz->matriz_codigo }}"
                                    {{ request('matriz') == $matriz->matriz_codigo ? 'selected' : '' }}>
                                    {{ $matriz->matriz_descripcion }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="fecha_inicio_muestreo" class="form-label">Alta desde</label>
                        <input type="date" class="form-control" id="fecha_inicio_muestreo"
                               name="fecha_inicio_muestreo" value="{{ request('fecha_inicio_muestreo') }}">
                    </div>

                    <div class="col-md-2">
                        <label for="fecha_fin_muestreo" class="form-label">Alta hasta</label>
                        <input type="date" class="form-control" id="fecha_fin_muestreo"
                               name="fecha_fin_muestreo" value="{{ request('fecha_fin_muestreo') }}">
                    </div>

                    <div class="col-12">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <x-heroicon-o-magnifying-glass class="me-1" style="width: 16px; height: 16px;" />
                                Buscar
                            </button>
                            <a href="{{ route($portalRouteName) }}" class="btn btn-outline-secondary">
                                Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(($muestras ?? collect())->isEmpty())
        <div class="alert alert-warning mb-0">
            No hay cotizaciones con ensayos de esta área según los filtros.
        </div>
    @else
        @include('muestras.partials.lista')
    @endif
</div>
@endsection
