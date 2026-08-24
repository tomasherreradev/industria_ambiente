@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm border-warning">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start gap-3">
                        <x-heroicon-o-lock-closed class="text-warning flex-shrink-0" style="width: 42px; height: 42px;" />
                        <div>
                            <h1 class="h4 mb-2">Presupuesto en edición por otro usuario</h1>
                            <p class="mb-3">
                                La cotización <strong>#{{ $cotizacion->coti_num }}</strong> está siendo editada en este momento.
                                Solo una persona puede modificarla a la vez mientras está en etapa de edición.
                            </p>
                            <ul class="list-unstyled mb-4">
                                <li><strong>Usuario:</strong> {{ $titular['nombre'] }}@if(!empty($titular['usu_codigo'])) ({{ $titular['usu_codigo'] }})@endif</li>
                                @if(!empty($titular['desde']))
                                    <li><strong>Desde:</strong> {{ $titular['desde'] }}</li>
                                @endif
                                <li class="text-muted small mt-2">Si cerró la pestaña sin guardar, el bloqueo se libera solo en unos minutos.</li>
                            </ul>
                            <a href="{{ route('ventas.index') }}" class="btn btn-primary">
                                <x-heroicon-o-arrow-left class="me-1" style="width: 16px; height: 16px;" />
                                Volver al listado de ventas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
