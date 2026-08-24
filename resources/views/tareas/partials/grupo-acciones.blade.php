@php
    $primeraInstancia = $grupo['instancias'][0]['instancia_muestra'] ?? null;
    $rutaVer = $primeraInstancia
        ? (Auth::user()->rol == 'laboratorio'
            ? route('ordenes.all.show', [$primeraInstancia->cotio_numcoti, $primeraInstancia->cotio_item, $primeraInstancia->cotio_subitem, $primeraInstancia->instance_number])
            : route('tareas.all.show', [$primeraInstancia->cotio_numcoti, $primeraInstancia->cotio_item, $primeraInstancia->cotio_subitem, $primeraInstancia->instance_number]))
        : '#';
    $mapsQuery = $cotizacion ? urlencode(\App\Support\CotizacionClienteEtiqueta::direccionDestinatarioMapsQuery($cotizacion)) : '';
    $btnMapsClass = $mapsBtnClass ?? 'btn-outline-primary';
@endphp
<div class="d-flex gap-2 mt-2 mt-md-0 tarea-grupo-actions">
    @if($grupo['instancias']->count() === 1 && $primeraInstancia)
        <a href="{{ $rutaVer }}" class="btn btn-primary btn-sm btn-ver-principal d-md-none">
            <x-heroicon-o-eye class="me-1" style="width: 16px; height: 16px;" />
            Ver muestra
        </a>
    @endif
    @if($cotizacion && $mapsQuery)
        <a class="btn {{ $btnMapsClass }} btn-sm"
           href="https://www.google.com/maps/search/?api=1&query={{ $mapsQuery }}"
           target="_blank"
           rel="noopener">
            <x-heroicon-o-map class="me-1" style="width: 16px; height: 16px;" />
            <span>Maps</span>
        </a>
    @endif
</div>
