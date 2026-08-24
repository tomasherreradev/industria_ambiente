@php
    $metodoAnalisisInfo = $metodoAnalisisInfo ?? \App\Support\EtiquetaMetodoAnalisis::resolver($tarea);
@endphp
@if($metodoAnalisisInfo['codigo'] !== '')
    <span class="ms-1 small text-muted" title="Método de análisis">
        — {{ $metodoAnalisisInfo['etiqueta'] }}
        @if($metodoAnalisisInfo['etiqueta'] !== $metodoAnalisisInfo['codigo'])
            <span class="opacity-75">({{ $metodoAnalisisInfo['codigo'] }})</span>
        @endif
    </span>
@endif
