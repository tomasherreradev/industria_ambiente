@php
    $precinto = trim((string) ($instancia->nro_precinto ?? ''));
@endphp
@if($precinto !== '')
 · Precinto {{ $precinto }}
@endif
