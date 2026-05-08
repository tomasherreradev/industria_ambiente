<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pendientes diarios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @page { margin: 12mm; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 6px 0; }
        .meta { margin: 0 0 10px 0; color: #444; font-size: 11px; }
        .meta code { font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px 6px; vertical-align: top; }
        th { background: #f2f2f2; text-align: left; }
        .right { text-align: right; }
        .muted { color: #666; font-size: 11px; }
        .no-print { margin-top: 10px; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
@php
    $hoy = \Carbon\Carbon::now()->format('d/m/Y H:i');
    $estado = request('estado') ?: 'Todos';
    $desde = request('fecha_inicio_ot') ?: '—';
    $hasta = request('fecha_fin_ot') ?: '—';
@endphp

<h1>Pendientes diarios — Mis análisis</h1>
<div class="meta">
    Generado: <strong>{{ $hoy }}</strong> |
    Estado: <strong>{{ $estado }}</strong> |
    Desde: <strong>{{ $desde }}</strong> |
    Hasta: <strong>{{ $hasta }}</strong>
    <div class="muted">Incluye fecha de ingreso al laboratorio (fecha_carga_ot).</div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 90px;">Coti</th>
            <th style="width: 150px;">Ingreso lab</th>
            <th>Muestra</th>
            <th>Análisis</th>
        </tr>
    </thead>
    <tbody>
        @php $rows = 0; @endphp
        @foreach($ordenesAgrupadas as $cotiNum => $grupo)
            @foreach(($grupo['instancias'] ?? collect()) as $inst)
                @php
                    $instM = $inst['instancia_muestra'] ?? null;
                    $muestra = $inst['muestra'] ?? null;
                    $analisis = $inst['analisis'] ?? collect();
                @endphp
                @foreach($analisis as $a)
                    @php $rows++; @endphp
                    <tr>
                        <td class="right">#{{ $cotiNum }}</td>
                        <td>
                            @if($instM && $instM->fecha_carga_ot)
                                {{ \Carbon\Carbon::parse($instM->fecha_carga_ot)->format('d/m/Y H:i') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            {{ $muestra?->cotio_descripcion ?? $instM?->cotio_descripcion ?? '—' }}
                            @if($instM?->otn)
                                <span class="muted">(OT: {{ $instM->otn }})</span>
                            @endif
                            <div class="muted">
                                Item {{ $instM?->cotio_item ?? '—' }} / Inst {{ $instM?->instance_number ?? '—' }}
                            </div>
                        </td>
                        <td>{{ $a->cotio_descripcion ?? '—' }}</td>
                    </tr>
                @endforeach
            @endforeach
        @endforeach

        @if($rows === 0)
            <tr>
                <td colspan="4" class="muted">No hay análisis para imprimir con los filtros actuales.</td>
            </tr>
        @endif
    </tbody>
</table>

<div class="no-print">
    <button type="button" onclick="window.print()">Imprimir</button>
    <button type="button" onclick="window.close()">Cerrar</button>
</div>

<script>
    window.addEventListener('load', () => {
        // auto imprimir si el usuario lo quiere; por ahora solo deja el botón
    });
</script>
</body>
</html>

