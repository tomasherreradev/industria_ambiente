@php
    $filasRefs = $refsFacturacion['filas'] ?? [];
    $ocValor = ($refsFacturacion['oc'] ?? '') !== '' ? $refsFacturacion['oc'] : '—';
    $ocObligatorio = ! empty($refsFacturacion['oc_obligatorio']);
    $ocPendiente = $ocObligatorio && ($refsFacturacion['oc'] ?? '') === '';
@endphp

<div class="d-flex flex-wrap align-items-center gap-x-3 gap-y-1 small fact-refs-inline">
    <span class="badge {{ ! empty($refsFacturacion['puede_facturar']) ? 'bg-success' : 'bg-warning text-dark' }}">
        {{ ! empty($refsFacturacion['puede_facturar']) ? 'Referencias OK' : 'Referencias pendientes' }}
    </span>
    <span class="fact-ref-inline-item">
        <span class="text-muted">O.C.</span>
        <strong>{{ $ocValor }}</strong>
        @if ($ocObligatorio)
            <span class="text-danger" title="Obligatorio para facturar">*</span>
        @endif
        @if ($ocPendiente)
            <span class="badge bg-light text-danger border ms-1">Falta</span>
        @endif
    </span>
    @foreach ($filasRefs as $fila)
        @php
            $tipo = $fila['tipo'] ?? 'REF';
            $valor = trim((string) ($fila['valor'] ?? ''));
            $oblig = ! empty($fila['obligatorio_factura']);
            $pendiente = $oblig && $valor === '';
        @endphp
        <span class="fact-ref-inline-item">
            <span class="text-muted">{{ $tipo }}</span>
            <strong>{{ $valor !== '' ? $valor : '—' }}</strong>
            @if ($oblig)
                <span class="text-danger" title="Obligatorio para facturar">*</span>
            @endif
            @if ($pendiente)
                <span class="badge bg-light text-danger border ms-1">Falta</span>
            @endif
        </span>
    @endforeach
    @if (count($filasRefs) === 0 && ! $ocObligatorio && ($refsFacturacion['oc'] ?? '') === '')
        <span class="text-muted">Sin referencias definidas en la cotización</span>
    @endif
</div>
