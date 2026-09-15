@extends('layouts.app')

@section('title', 'Historial de Cambios de Precios')

@section('content')
@include('partials.ucrud-styles')

<div class="container-fluid py-4 ucrud ucrud-layout--fluid">
    @include('partials.ucrud-form-header', [
        'title' => 'Historial de cambios de precios',
        'subtitle' => 'Registro de ajustes masivos y revertidos.',
        'actions' => '<a href="' . route('items.cambios-masivos-precios') . '" class="ucrud-btn ucrud-btn--primary">Nuevo cambio masivo</a><a href="' . route('items.index') . '" class="ucrud-btn ucrud-btn--ghost">Volver</a>',
    ])

    @if(session('success'))
        <div id="flash-success" data-message="{{ session('success') }}" style="display:none"></div>
    @endif

    @if($errors->any())
        <div class="ucrud-alert ucrud-alert--danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="ucrud-filters">
        <p class="ucrud-filters__title">Filtros</p>
        <form method="GET" action="{{ route('items.historial-precios') }}" class="ucrud-filters__grid">
                <div class="ucrud-field">
                    <label for="operacion_id">Operación</label>
                    <select name="operacion_id" id="operacion_id" class="ucrud-select" style="width:100%;">
                        <option value="">Todas las operaciones</option>
                        @foreach($operaciones as $operacion)
                            <option value="{{ $operacion->operacion_id }}" {{ request('operacion_id') == $operacion->operacion_id ? 'selected' : '' }}>
                                {{ substr($operacion->operacion_id, 0, 8) }}... - {{ $operacion->cantidad }} cambios - {{ ($operacion->fecha instanceof \Carbon\Carbon) ? $operacion->fecha->format('d/m/Y H:i') : \Carbon\Carbon::parse($operacion->fecha)->format('d/m/Y H:i') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="ucrud-field">
                    <label for="solo_activos">Estado</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="solo_activos" id="solo_activos" value="1" {{ request('solo_activos') ? 'checked' : '' }}>
                        <label class="form-check-label" for="solo_activos">Solo cambios activos</label>
                    </div>
                </div>
                <div class="ucrud-filters__actions">
                    <button type="submit" class="ucrud-btn ucrud-btn--primary">Filtrar</button>
                    <a href="{{ route('items.historial-precios') }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
                </div>
            </form>
    </div>

    <div class="ucrud-panel">
            <div class="ucrud-tablewrap">
                <table class="ucrud-table ucrud-table--sticky-actions">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Fecha</th>
                            <th>Ítem</th>
                            <th style="width: 120px;">Precio Anterior</th>
                            <th style="width: 120px;">Precio Nuevo</th>
                            <th style="width: 100px;">Tipo</th>
                            <th style="width: 100px;">Valor Aplicado</th>
                            <th>Descripción</th>
                            <th>Usuario</th>
                            <th style="width: 100px;">Estado</th>
                            <th style="width: 120px;">Operación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($historial as $cambio)
                            <tr>
                                <td class="align-middle">
                                    @php
                                        $fechaCambio = $cambio->fecha_cambio instanceof \Carbon\Carbon 
                                            ? $cambio->fecha_cambio 
                                            : \Carbon\Carbon::parse($cambio->fecha_cambio);
                                    @endphp
                                    <small>{{ $fechaCambio->format('d/m/Y') }}<br>{{ $fechaCambio->format('H:i') }}</small>
                                </td>
                                <td class="align-middle">
                                    @if($cambio->item)
                                        <strong>{{ $cambio->item->cotio_descripcion }}</strong>
                                    @else
                                        <span class="text-muted">Ítem no disponible</span>
                                    @endif
                                    <br>
                                    <small class="text-muted">ID: {{ $cambio->item_id }}</small>
                                </td>
                                <td class="align-middle">${{ number_format($cambio->precio_anterior, 2, ',', '.') }}</td>
                                <td class="align-middle">
                                    <strong>${{ number_format($cambio->precio_nuevo, 2, ',', '.') }}</strong>
                                </td>
                                <td class="align-middle">
                                    @if($cambio->tipo_cambio === 'porcentaje')
                                        <span class="ucrud-chip ucrud-chip--cyan">%</span>
                                    @else
                                        <span class="ucrud-chip ucrud-chip--slate">Fijo</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if($cambio->tipo_cambio === 'porcentaje')
                                        {{ $cambio->valor_aplicado > 0 ? '+' : '' }}{{ number_format($cambio->valor_aplicado, 2) }}%
                                    @else
                                        ${{ $cambio->valor_aplicado > 0 ? '+' : '' }}{{ number_format($cambio->valor_aplicado, 2) }}
                                    @endif
                                </td>
                                <td class="align-middle">
                                    <small>{{ $cambio->descripcion ?? '-' }}</small>
                                </td>
                                <td class="align-middle">
                                    <small>{{ $cambio->usuario->usu_descripcion ?? 'N/A' }}</small>
                                </td>
                                <td class="align-middle">
                                    @if($cambio->revertido)
                                        <span class="ucrud-chip ucrud-chip--rose">Revertido</span>
                                        @if($cambio->fecha_reversion)
                                            <br><small class="text-muted">{{ ($cambio->fecha_reversion instanceof \Carbon\Carbon) ? $cambio->fecha_reversion->format('d/m/Y H:i') : \Carbon\Carbon::parse($cambio->fecha_reversion)->format('d/m/Y H:i') }}</small>
                                        @endif
                                    @else
                                        <span class="ucrud-chip ucrud-chip--green">Activo</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    <a href="{{ route('items.historial-precios', ['operacion_id' => $cambio->operacion_id]) }}" class="text-decoration-none">
                                        <code class="small">{{ substr($cambio->operacion_id, 0, 8) }}...</code>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">No hay cambios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @if($historial->hasPages())
            <div class="ucrud-pagination">{{ $historial->links() }}</div>
        @endif
    </div>

    @if($operaciones->isNotEmpty())
        <div class="ucrud-panel mt-3">
            <div class="ucrud-detail-block">
                <h2 class="ucrud-detail-block__title">Operaciones masivas</h2>
            </div>
                <div class="ucrud-tablewrap">
                    <table class="ucrud-table ucrud-table--sticky-actions">
                        <thead>
                            <tr>
                                <th>ID Operación</th>
                                <th>Fecha</th>
                                <th>Cantidad de Cambios</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($operaciones as $operacion)
                                @php
                                    $cambiosOperacion = \App\Models\CotioItemPrecioHistorial::porOperacion($operacion->operacion_id)->get();
                                    $todosRevertidos = $cambiosOperacion->every(fn($c) => $c->revertido);
                                    $algunosRevertidos = $cambiosOperacion->some(fn($c) => $c->revertido) && !$todosRevertidos;
                                @endphp
                                <tr>
                                    <td><code>{{ substr($operacion->operacion_id, 0, 8) }}...</code></td>
                                    <td>{{ ($operacion->fecha instanceof \Carbon\Carbon) ? $operacion->fecha->format('d/m/Y H:i') : \Carbon\Carbon::parse($operacion->fecha)->format('d/m/Y H:i') }}</td>
                                    <td>{{ $operacion->cantidad }} cambios</td>
                                    <td>
                                        @if($todosRevertidos)
                                            <span class="ucrud-chip ucrud-chip--rose">Revertida</span>
                                        @elseif($algunosRevertidos)
                                            <span class="ucrud-chip ucrud-chip--amber">Parcial</span>
                                        @else
                                            <span class="ucrud-chip ucrud-chip--green">Activa</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="ucrud-actions">
                                        <a href="{{ route('items.historial-precios', ['operacion_id' => $operacion->operacion_id]) }}" class="ucrud-iconbtn" title="Ver detalles">
                                            <x-heroicon-o-eye style="width: 16px; height: 16px;" />
                                        </a>
                                        @if(!$todosRevertidos)
                                            <form action="{{ route('items.revertir-cambios', $operacion->operacion_id) }}" method="POST" class="d-inline js-revertir-form">
                                                @csrf
                                                <button type="submit" class="ucrud-iconbtn ucrud-iconbtn--danger" title="Revertir">
                                                    <x-heroicon-o-arrow-path style="width: 16px; height: 16px;" />
                                                </button>
                                            </form>
                                        @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const flash = document.getElementById('flash-success');
        if (flash && flash.dataset.message) {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: flash.dataset.message,
                timer: 3000,
                showConfirmButton: false
            });
        }

        document.querySelectorAll('.js-revertir-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: '¿Revertir esta operación?',
                    text: 'Se restaurarán todos los precios a sus valores anteriores. Esta acción no se puede deshacer.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, revertir',
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
@endpush
@endsection

