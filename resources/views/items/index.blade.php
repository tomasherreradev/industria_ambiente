@extends('layouts.app')

@section('title', 'Determinaciones')

@section('content')
<link rel="stylesheet" href="{{ asset('css/usuarios-crud.css') }}?v={{ filemtime(public_path('css/usuarios-crud.css')) }}">

@php
    $hayFiltros = $search || $tipo || $matrizCodigo;
@endphp

<div class="container-fluid py-4 ucrud" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Determinaciones
                <span class="ucrud-count">{{ $items->total() }}</span>
            </h1>
            <p class="ucrud-subtitle">Catálogo de ítems, métodos, matrices y precios.</p>
        </div>

        <div class="ucrud-header__actions">
            <a href="{{ route('items.exportar', array_filter(['q' => $search, 'tipo' => $tipo, 'matriz' => $matrizCodigo])) }}"
               class="ucrud-btn ucrud-btn--outline-success">
                <x-heroicon-o-arrow-down-tray style="width: 16px; height: 16px;" />
                <span class="d-none d-sm-inline">Exportar</span>
            </a>
            <a href="{{ route('items.importar') }}" class="ucrud-btn ucrud-btn--success">
                <x-heroicon-o-arrow-down-tray style="width: 16px; height: 16px;" />
                <span class="d-none d-sm-inline">Importar Excel</span>
                <span class="d-sm-none">Importar</span>
            </a>
            <a href="{{ route('items.cambios-masivos-precios') }}" class="ucrud-btn ucrud-btn--warning">
                <x-heroicon-o-arrow-trending-up style="width: 16px; height: 16px;" />
                <span class="d-none d-md-inline">Cambios masivos</span>
                <span class="d-md-none">Precios</span>
            </a>
            <a href="{{ route('items.historial-precios') }}" class="ucrud-btn ucrud-btn--info">
                <x-heroicon-o-clock style="width: 16px; height: 16px;" />
                <span class="d-none d-sm-inline">Historial</span>
            </a>
            <a href="{{ route('items.create') }}" class="ucrud-btn ucrud-btn--primary">
                <x-heroicon-o-plus style="width: 16px; height: 16px;" />
                <span class="d-none d-sm-inline">Nueva determinación</span>
                <span class="d-sm-none">Nueva</span>
            </a>
        </div>
    </header>

    @if(session('success'))
        <div id="flash-success" data-message="{{ session('success') }}" style="display:none"></div>
    @endif

    <form method="GET" action="{{ route('items.index') }}" class="ucrud-toolbar ucrud-toolbar--wide">
        <label class="ucrud-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7"/>
                <path d="m20 20-3.1-3.1"/>
            </svg>
            <input type="text" name="q" value="{{ $search }}" class="ucrud-input" placeholder="Buscar descripción…" aria-label="Buscar determinaciones">
        </label>

        <select name="tipo" class="ucrud-select" aria-label="Filtrar por tipo">
            <option value="">Todos los tipos</option>
            <option value="agrupador" @selected($tipo === 'agrupador')>Agrupador</option>
            <option value="componente" @selected($tipo === 'componente')>Componente</option>
        </select>

        <select name="matriz" class="ucrud-select" aria-label="Filtrar por matriz">
            <option value="">Todas las matrices</option>
            @foreach($matrices as $matriz)
                <option value="{{ $matriz->matriz_codigo }}" @selected($matrizCodigo === $matriz->matriz_codigo)>
                    {{ $matriz->matriz_codigo }} - {{ $matriz->matriz_descripcion }}
                </option>
            @endforeach
        </select>

        <button type="submit" class="ucrud-btn ucrud-btn--primary">Buscar</button>

        @if($hayFiltros)
            <a href="{{ route('items.index') }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
        @endif
    </form>

    <div class="ucrud-panel">
        @if($items->isEmpty())
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-beaker style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">No hay ítems registrados</p>
                <p class="ucrud-empty__text">
                    {{ $hayFiltros ? 'Probá ajustar los filtros de búsqueda.' : 'Creá la primera determinación.' }}
                </p>
            </div>
        @else
            <div class="ucrud-tablewrap">
                <table class="ucrud-table ucrud-table--sticky-actions">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Determinación</th>
                            <th>Tipo</th>
                            <th>Límite detección</th>
                            <th>Unidad</th>
                            <th>Método muestreo</th>
                            <th>Método análisis</th>
                            <th>Matriz</th>
                            <th>Componentes</th>
                            <th>Precio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $i => $item)
                            @php
                                $agrupadoresReales = ! $item->es_muestra
                                    ? $item->agrupadores->where('es_muestra', true)
                                    : collect();
                            @endphp
                            <tr class="ucrud-animate-in" style="--i: {{ $i }}">
                                <td><span class="ucrud-code">{{ $item->id }}</span></td>
                                <td>
                                    <span class="ucrud-user__name d-block">{{ $item->cotio_descripcion }}</span>
                                    @if($agrupadoresReales->isNotEmpty())
                                        <span class="ucrud-user__meta">
                                            Usado en: {{ $agrupadoresReales->pluck('cotio_descripcion')->join(', ') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="ucrud-chip {{ $item->es_muestra ? 'ucrud-chip--blue' : 'ucrud-chip--slate' }}">
                                        {{ $item->es_muestra ? 'Agrupador' : 'Componente' }}
                                    </span>
                                </td>
                                <td>{{ $item->limites_establecidos ?? '—' }}</td>
                                <td>{{ $item->unidad_medida ?? '—' }}</td>
                                <td>{{ optional($item->metodoMuestreo)->metodo_descripcion ?? ($item->metodo_muestreo ? trim($item->metodo_muestreo) : '—') }}</td>
                                <td>{{ optional($item->metodoAnalitico)->metodo_descripcion ?? ($item->metodo ? trim($item->metodo) : '—') }}</td>
                                <td>
                                    @if($item->matrices->isNotEmpty())
                                        {{ $item->matrices->pluck('matriz_descripcion')->join(', ') }}
                                    @else
                                        <span class="ucrud-dim">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->es_muestra)
                                        <span class="ucrud-chip ucrud-chip--cyan">{{ $item->componentesAsociados->count() }}</span>
                                    @else
                                        <span class="ucrud-dim">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->precio !== null)
                                        $ {{ number_format($item->precio, 2, ',', '.') }}
                                    @else
                                        <span class="ucrud-dim">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="ucrud-actions">
                                        <a href="{{ route('items.edit', $item) }}" class="ucrud-iconbtn" title="Editar" aria-label="Editar ítem">
                                            <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                                        </a>
                                        <form action="{{ route('items.delete', $item) }}" method="POST" class="d-inline js-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ucrud-iconbtn ucrud-iconbtn--danger" title="Eliminar" aria-label="Eliminar ítem">
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

            @if($items->hasPages())
                <div class="ucrud-pagination">
                    {{ $items->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const flash = document.getElementById('flash-success');
    if (flash && flash.dataset.message) {
        Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: flash.dataset.message,
            timer: 2000,
            showConfirmButton: false
        });
    }

    document.querySelectorAll('.js-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: '¿Eliminar este ítem?',
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
