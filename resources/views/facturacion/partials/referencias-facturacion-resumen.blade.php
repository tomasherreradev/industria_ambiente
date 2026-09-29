@if (empty($refsFacturacion['puede_facturar']) && ! empty($refsFacturacion['mensaje_bloqueo']))
    <div class="alert alert-warning mb-3">{{ $refsFacturacion['mensaje_bloqueo'] }}</div>
@endif
<div class="row g-3 small">
    <div class="col-md-4">
        <span class="text-muted d-block text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.06em;">Remito (1.ª línea)</span>
        <span class="fw-semibold">{{ ($refsFacturacion['remito'] ?? '') !== '' ? $refsFacturacion['remito'] : '—' }}</span>
    </div>
    <div class="col-md-5">
        <span class="text-muted d-block text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.06em;">Orden de compra (O.C.)</span>
        <span class="fw-semibold">{{ ($refsFacturacion['oc'] ?? '') !== '' ? $refsFacturacion['oc'] : '—' }}</span>
        @if (! empty($refsFacturacion['oc_obligatorio']))
            <span class="badge bg-dark ms-1">Obligatoria para facturar</span>
        @endif
    </div>
</div>
@php $filasRefs = $refsFacturacion['filas'] ?? []; @endphp
@if (count($filasRefs) > 0)
    <hr class="my-3">
    <span class="text-muted d-block mb-2 small">Referencias adicionales (hasta 4)</span>
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0 bg-white">
            <thead class="table-light">
                <tr>
                    <th style="width:110px;">Tipo</th>
                    <th>Valor</th>
                    <th style="width:140px;">Oblig. facturar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filasRefs as $fila)
                    <tr>
                        <td class="fw-medium">{{ $fila['tipo'] ?? '' }}</td>
                        <td>{{ ($fila['valor'] ?? '') !== '' ? $fila['valor'] : '—' }}</td>
                        <td>
                            @if (! empty($fila['obligatorio_factura']))
                                <span class="badge bg-warning text-dark">Sí</span>
                            @else
                                <span class="text-muted">No</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
