@extends('layouts.app')

@section('title', 'Vehículos')

@section('content')
<link rel="stylesheet" href="{{ asset('css/usuarios-crud.css') }}?v={{ filemtime(public_path('css/usuarios-crud.css')) }}">

@php
    $hayFiltros = request()->hasAny(['marca', 'modelo', 'anio', 'patente', 'tipo', 'estado']);
@endphp

<div class="container py-4 ucrud" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Gestión de vehículos
                <span class="ucrud-count">{{ $vehiculos->total() }}</span>
            </h1>
            <p class="ucrud-subtitle">Flota disponible para tareas de muestreo y campo.</p>
        </div>

        <div class="ucrud-header__actions">
            <a href="{{ route('vehiculos.create') }}" class="ucrud-btn ucrud-btn--primary">
                <x-heroicon-o-plus style="width: 16px; height: 16px;" />
                Nuevo vehículo
            </a>
        </div>
    </header>

    <div class="ucrud-filters">
        <p class="ucrud-filters__title">Filtros</p>
        <form action="{{ url('/vehiculos') }}" method="GET" class="ucrud-filters__grid">
            <div class="ucrud-field">
                <label for="marca">Marca</label>
                <input type="text" name="marca" id="marca" class="ucrud-input" style="padding-left: .9rem;"
                       placeholder="Ej: Toyota" value="{{ request('marca') }}">
            </div>
            <div class="ucrud-field">
                <label for="modelo">Modelo</label>
                <input type="text" name="modelo" id="modelo" class="ucrud-input" style="padding-left: .9rem;"
                       placeholder="Ej: Hilux" value="{{ request('modelo') }}">
            </div>
            <div class="ucrud-field">
                <label for="anio">Año</label>
                <input type="number" name="anio" id="anio" class="ucrud-input" style="padding-left: .9rem;"
                       placeholder="Ej: 2020" value="{{ request('anio') }}">
            </div>
            <div class="ucrud-field">
                <label for="patente">Patente</label>
                <input type="text" name="patente" id="patente" class="ucrud-input" style="padding-left: .9rem;"
                       placeholder="Ej: AB1234" value="{{ request('patente') }}">
            </div>
            <div class="ucrud-field">
                <label for="estado">Estado</label>
                <select name="estado" id="estado" class="ucrud-select" style="width: 100%;">
                    <option value="">Todos</option>
                    <option value="libre" @selected(request('estado') == 'libre')>Libre</option>
                    <option value="ocupado" @selected(request('estado') == 'ocupado')>Ocupado</option>
                    <option value="mantenimiento" @selected(request('estado') == 'mantenimiento')>Mantenimiento</option>
                </select>
            </div>
            <div class="ucrud-filters__actions">
                @if($hayFiltros)
                    <a href="{{ url('/vehiculos') }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
                @endif
                <button type="submit" class="ucrud-btn ucrud-btn--primary">Aplicar filtros</button>
            </div>
        </form>
    </div>

    <div class="ucrud-panel">
        @if($vehiculos->isEmpty())
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-truck style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">No se encontraron vehículos</p>
                <p class="ucrud-empty__text">
                    {{ $hayFiltros ? 'Probá ajustar los filtros de búsqueda.' : 'Registrá el primer vehículo de la flota.' }}
                </p>
            </div>
        @else
            <div class="d-none d-lg-block ucrud-tablewrap">
                <table class="ucrud-table ucrud-table--sticky-actions">
                    <thead>
                        <tr>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Año</th>
                            <th>Patente</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Último mantenimiento</th>
                            <th>Estado general</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vehiculos as $i => $vehiculo)
                            @php
                                $tareaActiva = $vehiculo->estado !== 'libre' && $vehiculo->estado !== 'mantenimiento'
                                    ? $vehiculo->tareas->sortByDesc('created_at')->first()
                                    : null;
                            @endphp
                            <tr class="ucrud-animate-in" style="--i: {{ $i }}">
                                <td>{{ $vehiculo->marca ?? '—' }}</td>
                                <td>{{ $vehiculo->modelo ?? '—' }}</td>
                                <td>{{ $vehiculo->anio ?? '—' }}</td>
                                <td><span class="ucrud-code">{{ $vehiculo->patente }}</span></td>
                                <td>{{ $vehiculo->tipo ?? '—' }}</td>
                                <td>
                                    @if($vehiculo->estado === 'libre')
                                        <span class="ucrud-chip ucrud-chip--green">Libre</span>
                                    @elseif($vehiculo->estado === 'mantenimiento')
                                        <span class="ucrud-chip ucrud-chip--amber">Mantenimiento</span>
                                    @else
                                        <span class="ucrud-chip ucrud-chip--rose"
                                              @if($tareaActiva) data-bs-toggle="tooltip" data-bs-placement="bottom"
                                              title="Cotización: {{ $tareaActiva->cotio_numcoti }} - {{ $tareaActiva->cotio_descripcion }}" @endif>
                                            Ocupado
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $vehiculo->ultimo_mantenimiento ?? '—' }}</td>
                                <td>{{ $vehiculo->estado_gral ?? '—' }}</td>
                                <td>
                                    <div class="ucrud-actions">
                                        <a href="{{ route('vehiculos.show', $vehiculo->id) }}"
                                           class="ucrud-iconbtn" title="Editar" aria-label="Editar vehículo">
                                            <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                                        </a>
                                        <form action="{{ route('vehiculos.destroy', $vehiculo->id) }}" method="POST"
                                              class="d-inline js-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ucrud-iconbtn ucrud-iconbtn--danger"
                                                    title="Eliminar" aria-label="Eliminar vehículo">
                                                <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-block d-lg-none">
                @foreach($vehiculos as $i => $vehiculo)
                    @php
                        $tareaActiva = $vehiculo->estado === 'ocupado'
                            ? $vehiculo->tareas->sortByDesc('created_at')->first()
                            : null;
                    @endphp
                    <a href="{{ route('vehiculos.show', $vehiculo->id) }}" class="ucrud-card ucrud-animate-in" style="--i: {{ $i }}">
                        <span class="ucrud-card__body">
                            <span class="ucrud-user__name d-block">{{ $vehiculo->marca }} {{ $vehiculo->modelo }} ({{ $vehiculo->anio }})</span>
                            <span class="ucrud-card__meta">
                                <span class="ucrud-code">{{ $vehiculo->patente }}</span>
                                @if($vehiculo->estado === 'libre')
                                    <span class="ucrud-chip ucrud-chip--green">Libre</span>
                                @elseif($vehiculo->estado === 'mantenimiento')
                                    <span class="ucrud-chip ucrud-chip--amber">Mantenimiento</span>
                                @else
                                    <span class="ucrud-chip ucrud-chip--rose">Ocupado</span>
                                @endif
                                @if($vehiculo->tipo)
                                    <span class="ucrud-user__meta">{{ $vehiculo->tipo }}</span>
                                @endif
                            </span>
                            @if($tareaActiva)
                                <span class="ucrud-user__meta d-block mt-1">
                                    En uso: Cot. {{ $tareaActiva->cotio_numcoti }} — {{ Str::limit($tareaActiva->cotio_descripcion, 50) }}
                                </span>
                            @endif
                        </span>
                        <span class="ucrud-card__chevron" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m9 6 6 6-6 6"/>
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>

            @if($vehiculos->hasPages())
                <div class="ucrud-pagination">
                    {{ $vehiculos->appends(request()->query())->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    document.querySelectorAll('.js-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: '¿Eliminar este vehículo?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>
@endpush
