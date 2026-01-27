@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Detalle de Factura #{{ $factura->numero_factura }}</h2>
        <div>
            <a href="{{ route('facturacion.index') }}" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Volver al Listado
            </a>
            @if($factura->cotizacion)
                <a href="{{ route('facturacion.show', $factura->cotizacion_id) }}" class="btn btn-primary">
                    <i class="fas fa-eye me-2"></i>Ver Cotización
                </a>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="row">
        <!-- Información de la Factura -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Información de la Factura</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-5">ID:</dt>
                        <dd class="col-sm-7">{{ $factura->id }}</dd>

                        <dt class="col-sm-5">Número de Factura:</dt>
                        <dd class="col-sm-7"><strong>{{ $factura->numero_factura }}</strong></dd>

                        <dt class="col-sm-5">CAE:</dt>
                        <dd class="col-sm-7"><code>{{ $factura->cae }}</code></dd>

                        <dt class="col-sm-5">Fecha de Emisión:</dt>
                        <dd class="col-sm-7">{{ $factura->fecha_emision->format('d/m/Y H:i:s') }}</dd>

                        <dt class="col-sm-5">Fecha Venc. CAE:</dt>
                        <dd class="col-sm-7">
                            {{ $factura->fecha_vencimiento_cae ? \Carbon\Carbon::parse($factura->fecha_vencimiento_cae)->format('d/m/Y') : 'N/A' }}
                        </dd>

                        <dt class="col-sm-5">Monto Total:</dt>
                        <dd class="col-sm-7"><strong class="text-success fs-5">{{ $factura->monto_total_formateado }}</strong></dd>

                        <dt class="col-sm-5">Fecha de Creación:</dt>
                        <dd class="col-sm-7">{{ $factura->created_at->format('d/m/Y H:i:s') }}</dd>

                        <dt class="col-sm-5">Última Actualización:</dt>
                        <dd class="col-sm-7">{{ $factura->updated_at->format('d/m/Y H:i:s') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Información de la Cotización -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Información de la Cotización</h5>
                </div>
                <div class="card-body">
                    @if($factura->cotizacion)
                        <dl class="row">
                            <dt class="col-sm-5">Número:</dt>
                            <dd class="col-sm-7"><strong>#{{ $factura->cotizacion->coti_num }}</strong></dd>

                            <dt class="col-sm-5">Empresa:</dt>
                            <dd class="col-sm-7">
                                @php
                                    $empresaRelacionadaFact = null;
                                    if ($factura->cotizacion->coti_cli_empresa) {
                                        $empresaRelacionadaFact = \App\Models\ClienteEmpresaRelacionada::find($factura->cotizacion->coti_cli_empresa);
                                    }
                                @endphp
                                @if($empresaRelacionadaFact)
                                    {{ $empresaRelacionadaFact->razon_social }}
                                @else
                                    {{ $factura->cotizacion->coti_empresa ?? 'N/A' }}
                                @endif
                            </dd>

                            <dt class="col-sm-5">CUIT:</dt>
                            <dd class="col-sm-7">{{ $factura->cotizacion->coti_cuit ?? 'N/A' }}</dd>

                            <dt class="col-sm-5">Email:</dt>
                            <dd class="col-sm-7">{{ $factura->cotizacion->coti_mail ?? 'N/A' }}</dd>

                            <dt class="col-sm-5">Dirección:</dt>
                            <dd class="col-sm-7">{{ $factura->cotizacion->coti_direccioncli ?? 'N/A' }}</dd>

                            <dt class="col-sm-5">Localidad:</dt>
                            <dd class="col-sm-7">{{ $factura->cotizacion->coti_localidad ?? 'N/A' }}</dd>

                            <dt class="col-sm-5">Partido:</dt>
                            <dd class="col-sm-7">{{ $factura->cotizacion->coti_partido ?? 'N/A' }}</dd>
                        </dl>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No se encontró información de la cotización asociada.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Items Facturados -->
    <div class="card mt-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Items Facturados</h5>
        </div>
        <div class="card-body">
            @if(isset($resumenItems))
                <div class="alert alert-info d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <strong>Importe bruto:</strong> ${{ number_format($resumenItems['total_bruto'] ?? 0, 2, ',', '.') }}
                    </div>
                    <div>
                        <strong>Descuento aplicado:</strong> 
                        @if(($resumenItems['descuento_porcentaje'] ?? 0) > 0)
                            {{ number_format($resumenItems['descuento_porcentaje'], 2, ',', '.') }}% ({{ '$' . number_format($resumenItems['descuento_monto'] ?? 0, 2, ',', '.') }})
                        @else
                            Sin descuento
                        @endif
                    </div>
                    <div>
                        <strong>Importe neto:</strong> ${{ number_format($resumenItems['total_neto'] ?? 0, 2, ',', '.') }}
                    </div>
                </div>
            @endif

            @if(!empty($items) && is_array($items))
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th>Identificación/Resultado</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php 
                                $totalCalculado = 0; 
                                $totalBruto = 0;
                            @endphp
                            @foreach($items as $item)
                                @php
                                    if (!is_array($item)) {
                                        continue;
                                    }
                                    $tipo = $item['tipo'] ?? 'N/A';
                                    $cantidad = $item['cantidad'] ?? 0;
                                    $precioUnitario = $item['precio_unitario'] ?? 0;
                                    $subtotalItem = $item['subtotal'] ?? $precioUnitario;
                                    $totalCalculado += $subtotalItem;
                                    $totalBruto += $item['subtotal_bruto'] ?? $subtotalItem;
                                @endphp
                                <tr>
                                    <td>
                                        @if($tipo === 'muestra')
                                            <span class="badge bg-primary">Muestra</span>
                                        @elseif($tipo === 'analisis')
                                            <span class="badge bg-info">Análisis</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($tipo) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $item['descripcion'] ?? 'N/A' }}</td>
                                    <td>
                                        @if($tipo === 'muestra')
                                            <small class="text-muted">ID: {{ $item['identificacion'] ?? 'N/A' }}</small>
                                        @elseif($tipo === 'analisis')
                                            <small class="text-muted">Resultado: {{ $item['resultado'] ?? 'N/A' }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $cantidad }}</td>
                                    <td>${{ number_format($precioUnitario, 2, ',', '.') }}</td>
                                    <td><strong>${{ number_format($subtotalItem, 2, ',', '.') }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @if($totalBruto > $totalCalculado)
                                <tr>
                                    <th colspan="5" class="text-end text-muted">Total bruto:</th>
                                    <th class="text-muted">${{ number_format($totalBruto, 2, ',', '.') }}</th>
                                </tr>
                                <tr>
                                    <th colspan="5" class="text-end text-muted">Descuento aplicado:</th>
                                    <th class="text-muted">-${{ number_format($totalBruto - $totalCalculado, 2, ',', '.') }}</th>
                                </tr>
                            @endif
                            <tr class="table-success">
                                <th colspan="5" class="text-end">Total:</th>
                                <th><strong>${{ number_format($totalCalculado, 2, ',', '.') }}</strong></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No se encontraron items para esta factura o el formato de datos no es válido.
                </div>
            @endif
        </div>
    </div>

    <!-- Información Técnica (Solo para administradores) -->
    {{-- <div class="card mt-4">
        <div class="card-header bg-secondary text-white">
            <h6 class="mb-0">Información Técnica</h6>
        </div>
        <div class="card-body">
            <small class="text-muted">
                <strong>Instancia ID de Factura:</strong> {{ $factura->id }}<br>
                <strong>Cotización ID:</strong> {{ $factura->cotizacion_id }}<br>
                <strong>Items Raw:</strong><br>
                <pre class="bg-light p-2 rounded" style="font-size: 11px; max-height: 200px; overflow-y: auto;">{{ $factura->items }}</pre>
            </small>
        </div>
    </div> --}}
</div>
@endsection