{{-- Muestra un tilde / distintivo cuando la cotización tiene plan de abonos (cuotas) --}}
@if(! empty($coti->coti_cuotas))
    <span class="badge bg-info text-white ms-1 align-middle" title="Cotización con cuotas">
        <x-heroicon-o-check class="d-inline" style="width: 12px; height: 12px; vertical-align: -0.1em;" />
        <span class="d-none d-sm-inline">Cuotas</span>
    </span>
@endif
