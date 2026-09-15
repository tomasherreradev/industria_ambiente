@extends('layouts.app')

@section('title', 'Inventario de Muestreo')

@section('content')
<link rel="stylesheet" href="{{ asset('css/usuarios-crud.css') }}?v={{ filemtime(public_path('css/usuarios-crud.css')) }}">

@php
    $hayFiltros = request()->filled('search') || request()->filled('estado') || request()->filled('n_serie_lote');
@endphp

<div class="container py-4 ucrud" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Inventario de Muestreo
                <span class="ucrud-count">{{ $inventarios->total() }}</span>
            </h1>
            <p class="ucrud-subtitle">Equipamiento de campo, fichas y calibraciones de muestreo.</p>
        </div>

        <div class="ucrud-header__actions">
            <a href="{{ route('inventarios-muestreo.create') }}" class="ucrud-btn ucrud-btn--primary">
                <x-heroicon-o-plus style="width: 16px; height: 16px;" />
                Crear inventario
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

    <div class="ucrud-filters">
        <p class="ucrud-filters__title">Filtros</p>
        <form method="GET" action="{{ route('inventarios-muestreo.index') }}" class="ucrud-filters__grid">
            <div class="ucrud-field">
                <label for="search">Buscar equipo</label>
                <input type="text" class="ucrud-input" style="padding-left: .9rem;" id="search" name="search"
                       placeholder="Equipo, marca, modelo…" value="{{ request('search') }}">
            </div>
            <div class="ucrud-field">
                <label for="estado">Estado</label>
                <select class="ucrud-select" style="width: 100%;" id="estado" name="estado">
                    <option value="">Todos</option>
                    <option value="libre" @selected(request('estado') == 'libre')>Libre</option>
                    <option value="ocupado" @selected(request('estado') == 'ocupado')>Ocupado</option>
                </select>
            </div>
            <div class="ucrud-field">
                <label for="n_serie_lote">N° de serie/lote</label>
                <input type="text" class="ucrud-input" style="padding-left: .9rem;" id="n_serie_lote"
                       name="n_serie_lote" value="{{ request('n_serie_lote') }}">
            </div>
            <div class="ucrud-filters__actions">
                @if($hayFiltros)
                    <a href="{{ route('inventarios-muestreo.index') }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
                @endif
                <button type="submit" class="ucrud-btn ucrud-btn--primary">Buscar</button>
            </div>
        </form>
    </div>

    <div class="ucrud-panel">
        @if($inventarios->isEmpty())
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-wrench-screwdriver style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">No hay inventarios disponibles</p>
                <p class="ucrud-empty__text">
                    {{ $hayFiltros ? 'Probá ajustar los filtros de búsqueda.' : 'Creá el primer registro de inventario.' }}
                </p>
            </div>
        @else
            <div class="d-none d-lg-block ucrud-tablewrap">
                <table class="ucrud-table ucrud-table--sticky-actions">
                    <thead>
                        <tr>
                            <th>Equipamiento</th>
                            <th>Marca/modelo</th>
                            <th>N° serie/lote</th>
                            <th>Código ficha</th>
                            <th>Observaciones</th>
                            <th>Activo</th>
                            <th>Calibración (venc.)</th>
                            <th>Certificado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inventarios as $i => $inventario)
                            <tr class="ucrud-animate-in" style="--i: {{ $i }}">
                                <td><span class="ucrud-user__name">{{ $inventario->equipamiento }}</span></td>
                                <td>{{ $inventario->marca_modelo }}</td>
                                <td><span class="ucrud-code">{{ $inventario->n_serie_lote }}</span></td>
                                <td><span class="ucrud-code">{{ $inventario->codigo_ficha }}</span></td>
                                <td>{{ $inventario->observaciones ?? '—' }}</td>
                                <td>
                                    <span class="ucrud-chip {{ $inventario->activo ? 'ucrud-chip--green' : 'ucrud-chip--rose' }}">
                                        {{ $inventario->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td>{{ $inventario->fecha_calibracion }}</td>
                                <td>
                                    @if($inventario->certificado)
                                        <a href="{{ asset('storage/' . $inventario->certificado) }}" target="_blank" rel="noopener noreferrer"
                                           class="ucrud-iconbtn" title="Ver certificado" aria-label="Ver certificado">
                                            <x-heroicon-o-document-text style="width: 16px; height: 16px;" />
                                        </a>
                                    @else
                                        <span class="ucrud-dim">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="ucrud-actions">
                                        <a class="ucrud-iconbtn"
                                           href="{{ url('/inventarios-muestreo/' . $inventario->id . '/edit') }}"
                                           title="Editar inventario"
                                           aria-label="Editar inventario">
                                            <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                                        </a>
                                        <form action="{{ url('/inventarios-muestreo/' . $inventario->id) }}" method="POST" class="d-inline js-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ucrud-iconbtn ucrud-iconbtn--danger"
                                                    title="Eliminar inventario" aria-label="Eliminar inventario">
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
                @foreach($inventarios as $i => $inventario)
                    <div class="ucrud-card-row ucrud-animate-in" style="--i: {{ $i }}">
                        <div class="ucrud-card__body">
                            <span class="ucrud-user__name d-block">{{ $inventario->equipamiento }}</span>
                            <span class="ucrud-card__meta">
                                <span class="ucrud-code">{{ $inventario->marca_modelo }}</span>
                                <span class="ucrud-chip {{ $inventario->activo ? 'ucrud-chip--green' : 'ucrud-chip--rose' }}">
                                    {{ $inventario->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </span>
                        </div>
                        <div class="ucrud-actions">
                            <a class="ucrud-iconbtn"
                               href="{{ url('/inventarios-muestreo/' . $inventario->id . '/edit') }}"
                               title="Editar" aria-label="Editar">
                                <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                            </a>
                            <form action="{{ url('/inventarios-muestreo/' . $inventario->id) }}" method="POST" class="d-inline js-delete-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ucrud-iconbtn ucrud-iconbtn--danger" title="Eliminar" aria-label="Eliminar">
                                    <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($inventarios->hasPages())
                <div class="ucrud-pagination">
                    {{ $inventarios->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: '¿Estás seguro?',
                text: 'No podrás revertir esta acción.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
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
