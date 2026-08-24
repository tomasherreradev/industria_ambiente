@foreach($cotizaciones as $coti)
@php
    $estado = trim($coti->coti_estado);
    $badgeClass = match ($estado) {
        'A' => 'bg-success',
        'E' => 'bg-warning',
        'S' => 'bg-danger',
        default => 'bg-secondary'
    };
    $estadoText = match ($estado) {
        'A' => 'Aprobado',
        'E' => 'En espera',
        'S' => 'Rechazado',
        default => $estado
    };
@endphp
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex flex-md-row justify-content-between align-items-center align-items-md-center">
                <button class="btn btn-link text-decoration-none p-0 text-start d-flex align-items-center" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#tabla-{{ $coti->coti_num }}" 
                        aria-expanded="false" 
                        aria-controls="tabla-{{ $coti->coti_num }}"
                        onclick="toggleChevron('chevron-{{ $coti->coti_num }}')"
                        >
                    <h4 class="mb-0 text-primary me-2">
                        Cotización Nº {{ $coti->coti_num }}
                        <span class="badge {{ $badgeClass }} ms-2 d-none d-lg-inline">{{ $estadoText }}</span>
                    </h4>
                    <x-heroicon-o-chevron-up id="chevron-{{ $coti->coti_num }}" class="text-primary chevron-icon" style="width: 20px; height: 20px;" />
                </button>
                <a class="btn btn-outline-primary mt-md-0"
                   href="https://www.google.com/maps/search/?api=1&query={{ $coti->coti_direccioncli }}, {{ $coti->coti_localidad }}, {{ $coti->coti_partido }}">
                    <span class="d-none d-md-inline">Ver en Maps</span>
                    <x-heroicon-o-map class="d-md-none" style="width: 18px; height: 18px;" />
                </a>
            </div>

            <div class="mt-2 small">
                <div class="d-lg-none"><strong>Estado:</strong> <span class="badge {{ $badgeClass }}">{{ $estadoText }}</span></div>
                <div><strong>Fecha Alta:</strong> {{ $coti->coti_fechaalta }}</div>
                <div><strong>Fecha Aprobación:</strong> {{ $coti->coti_fechaaprobado ?: 'Pendiente' }}</div>
                <div><strong>Dirección:</strong> {{ $coti->coti_direccioncli }}, {{ $coti->coti_localidad }}</div>
                <div><strong>Cliente:</strong> {{ \App\Support\CotizacionClienteEtiqueta::lineaClienteConEstablecimiento($coti) }}</div>
                <div><strong>Responsable:</strong> {{ $coti->responsable->usu_descripcion ?? 'Sin asignar' }}</div>
            </div>
        </div>

        <div id="tabla-{{ $coti->coti_num }}" class="collapse">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <a class="btn btn-sm btn-primary" href="{{ url('/show/'.$coti->coti_num) }}">
                        Asignar muestras
                    </a>
                </div>
            </div>
        </div>
    </div>
@endforeach

<div class="d-flex justify-content-center mt-4">
    {{ $cotizaciones->links() }}
</div>

<style>
    .chevron-icon {
        transition: transform 0.3s ease;
    }
    .chevron-icon.rotated {
        transform: rotate(180deg);
    }
</style>

<script>
    function toggleChevron(iconId) {
        const icon = document.getElementById(iconId);
        if (icon) {
            icon.classList.toggle('rotated');
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.collapse.show').forEach(collapseElement => {
            const targetId = collapseElement.id;
            const iconId = `chevron-${targetId.replace('tabla-', '')}`;
            const icon = document.getElementById(iconId);
            if (icon) {
                icon.classList.add('rotated');
            }
        });
    });
</script>