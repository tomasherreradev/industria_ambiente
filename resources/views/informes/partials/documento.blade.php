<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th width="100">Cotización</th>
                        <th>Cliente</th>
                        <th width="120" class="text-center">Fecha Muestreo</th>
                        <th width="100" class="text-center">Muestras</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($informesPorCotizacion as $numCoti => $informeData)
                    @php
                        $coti = $informeData['cotizacion'];
                        $finales = $informeData['informes_finales'];
                        $parciales = $informeData['total_muestras'] - $finales;
                    @endphp
                        <tr>
                            <td class="fw-bold">#{{ $numCoti }}</td>
                            <td>{{ \App\Support\CotizacionClienteEtiqueta::paraLista($coti) }}</td>
                            <td class="text-center">
                                @if($informeData['muestras']->first()->fecha_muestreo)
                                    {{ \Carbon\Carbon::parse($informeData['muestras']->first()->fecha_muestreo)->format('d/m/Y') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-center">{{ $informeData['total_muestras'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-4">
    {{ $pagination->links() }}
</div>

<style>
    @media print {
        body {
            padding: 20px;
            font-size: 12px;
        }
        
        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
        }
        
        .table th,
        .table td {
            padding: 0.5rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        
        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table-bordered {
            border: 1px solid #dee2e6;
        }
        
        .table-bordered th,
        .table-bordered td {
            border: 1px solid #dee2e6;
        }
        
        .table-bordered thead th,
        .table-bordered thead td {
            border-bottom-width: 2px;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        
        .bg-success {
            background-color: #28a745 !important;
        }
        
        .bg-warning {
            background-color: #ffc107 !important;
        }
    }
</style>