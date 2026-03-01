@extends('layouts.app')

@section('title', 'Determinaciones')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
        <h1 class="h4 mb-0">Determinaciones</h1>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('items.importar') }}" class="btn btn-success">
                <x-heroicon-o-arrow-down-tray style="width: 16px; height: 16px;" />
                <span class="d-none d-sm-inline">Importar desde Excel</span>
                <span class="d-sm-none">Importar</span>
            </a>
            <a href="{{ route('items.cambios-masivos-precios') }}" class="btn btn-warning">
                <x-heroicon-o-arrow-trending-up style="width: 16px; height: 16px;" />
                <span class="d-none d-md-inline">Cambios Masivos de Precios</span>
                <span class="d-md-none">Cambios Precios</span>
            </a>
            <a href="{{ route('items.historial-precios') }}" class="btn btn-info">
                <x-heroicon-o-clock style="width: 16px; height: 16px;" />
                <span class="d-none d-sm-inline">Historial de Precios</span>
                <span class="d-sm-none">Historial</span>
            </a>
            <a href="{{ route('items.create') }}" class="btn btn-primary">
                <x-heroicon-o-plus style="width: 16px; height: 16px;" class="d-sm-none" />
                <span class="d-none d-sm-inline">Nueva determinación</span>
                <span class="d-sm-none">Nueva</span>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div id="flash-success" data-message="{{ session('success') }}" style="display:none"></div>
    @endif

    <form method="GET" action="{{ route('items.index') }}" class="row g-2 mb-3">
        <div class="col-auto">
            <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Buscar descripción">
        </div>
        <div class="col-auto">
            <select name="tipo" class="form-select">
                <option value="">Todos los tipos</option>
                <option value="agrupador" {{ $tipo === 'agrupador' ? 'selected' : '' }}>Agrupador</option>
                <option value="componente" {{ $tipo === 'componente' ? 'selected' : '' }}>Componente</option>
            </select>
        </div>
        <div class="col-auto">
            <select name="matriz" class="form-select">
                <option value="">Todas las matrices</option>
                @foreach($matrices as $matriz)
                    <option value="{{ $matriz->matriz_codigo }}" {{ $matrizCodigo === $matriz->matriz_codigo ? 'selected' : '' }}>
                        {{ $matriz->matriz_codigo }} - {{ $matriz->matriz_descripcion }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">Buscar</button>
        </div>
        @if($search || $tipo || $matrizCodigo)
        <div class="col-auto">
            <a href="{{ route('items.index') }}" class="btn btn-outline-danger">Limpiar</a>
        </div>
        @endif
    </form>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Determinación</th>
                            <th style="width: 180px;">Tipo</th>
                            <th>Límite de detección</th>
                            <th>Unidad de medida</th>
                            <th>Método Muestreo</th>
                            <th>Método</th>
                            <th>Matriz</th>
                            <th>Componentes asociados</th>
                            <th>Precio</th>
                            <th style="width: 180px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td class="align-middle">{{ $item->id }}</td>
                                <td class="align-middle">
                                    {{ $item->cotio_descripcion }}
                                    {{-- Para componentes, mostrar solo agrupadores reales (es_muestra = true) en los que se usan --}}
                                    @php
                                        $agrupadoresReales = !$item->es_muestra
                                            ? $item->agrupadores->where('es_muestra', true)
                                            : collect();
                                    @endphp
                                    @if($agrupadoresReales->isNotEmpty())
                                        <br>
                                        <small class="text-muted">
                                            (Usado en: {{ $agrupadoresReales->pluck('cotio_descripcion')->join(', ') }})
                                        </small>
                                    @endif
                                </td>
                                <td class="align-middle">{{ $item->es_muestra ? 'Agrupador' : 'Componente' }}</td>
                                <td class="align-middle">{{ $item->limites_establecidos ?? '-' }}</td>
                                <td class="align-middle">{{ $item->unidad_medida ?? '-' }}</td>
                                <td class="align-middle">{{ optional($item->metodoMuestreo)->metodo_descripcion ?? '-' }}</td>
                                <td class="align-middle">{{ optional($item->metodoAnalitico)->metodo_descripcion ?? '-' }}</td>
                                <td class="align-middle">
                                    @if($item->matrices->isNotEmpty())
                                        {{ $item->matrices->pluck('matriz_descripcion')->join(', ') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if($item->es_muestra)
                                        <span class="badge bg-info text-dark">{{ $item->componentesAsociados->count() }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if($item->precio !== null)
                                        $ {{ number_format($item->precio, 2, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-center" style="vertical-align: middle;">
                                    <a href="{{ route('items.edit', $item) }}" class="btn btn-sm btn-outline-primary">
                                        <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                                    </a>
                                    <form action="{{ route('items.delete', $item) }}" method="POST" class="d-inline js-delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">No hay ítems registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($items->hasPages())
            <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
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

        document.querySelectorAll('.js-delete-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: '¿Eliminar este ítem?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
    </script>
</div>
@endsection


