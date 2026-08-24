<div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white p-3"
         style="cursor: pointer; background-color: #000000 !important;"
         onclick="toggleInfo('{{ $cotizacion->coti_num }}')">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div class="flex-grow-1 min-w-0">
                <h5 class="mb-0 d-flex align-items-center gap-2 flex-wrap">
                    Información de la Cotización
                    @php
                        $cotizacion->loadMissing('tareas');
                        $cotizacionTienePrioridad = $cotizacion->tareas
                            ->where('cotio_subitem', 0)
                            ->contains(fn ($t) => \App\Support\PrioridadListado::cotioEnsayoEsPrioritaria($t, $cotizacion));
                    @endphp
                    @if($cotizacionTienePrioridad)
                        <span class="badge bg-warning text-dark">
                            <x-heroicon-o-star style="width: 12px; height: 12px;" class="me-1" />
                            Prioridad
                        </span>
                    @endif
                </h5>
                <small class="d-block mt-1 text-white-50">
                    <strong class="text-white">Fecha Aprob.:</strong> {{ $cotizacion->coti_fechaaprobado ?? 'Pendiente' }}
                </small>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0" onclick="event.stopPropagation();">
                <a href="{{ route('cotizaciones.qr.all', ['cotizacion' => $cotizacion->coti_num]) }}"
                   class="text-decoration-none text-white"
                   title="Imprimir todos los QR de esta cotización"
                   onclick="event.preventDefault(); printAllQr('{{ $cotizacion->coti_num }}')">
                    <x-heroicon-o-printer class="text-white" style="width: 24px; height: 24px;" />
                </a>
                <x-heroicon-o-chevron-up id="chevron-{{ $cotizacion->coti_num }}" class="text-white" style="width: 20px; height: 20px;" />
            </div>
        </div>
    </div>

    <div id="info-{{ $cotizacion->coti_num }}" class="card-body collapse-content">
        @php
            $empresaRelacionada = null;
            $idEmpresaRelInfo = $cotizacion->coti_empresa_rel ?? $cotizacion->coti_cli_empresa;
            if ($idEmpresaRelInfo) {
                $empresaRelacionada = \App\Models\ClienteEmpresaRelacionada::find($idEmpresaRelInfo);
                if ($empresaRelacionada) {
                    $cotizacion->setRelation('empresaRelacionadaListaResuelta', $empresaRelacionada);
                }
            }
            $cotizacion->loadMissing(['cliente', 'sucursal']);
            $lineaClienteInfo = \App\Support\CotizacionClienteEtiqueta::paraLista($cotizacion);
            $establecimientoInfo = trim((string) ($cotizacion->coti_establecimiento ?? ''));
        @endphp

        @if($empresaRelacionada)
            <div class="mb-2">
                <strong>Para:</strong>
                {{ $empresaRelacionada->razon_social }}
                @if($empresaRelacionada->cuit)
                    <small class="text-muted">(CUIT: {{ $empresaRelacionada->cuit }})</small>
                @endif
            </div>
        @elseif(!empty($cotizacion->coti_para))
            <div class="mb-2">
                <strong>Para:</strong>
                {{ $cotizacion->coti_para }}
            </div>
        @endif

        <div class="mb-2">
            <strong>Cliente:</strong>
            {{ $lineaClienteInfo }}@if($establecimientoInfo !== '') — {{ $establecimientoInfo }}@endif
        </div>

        <div class="mb-2">
            <strong>Dirección:</strong>
            {{ $cotizacion->coti_direccioncli }}, {{ $cotizacion->coti_localidad }}, {{ $cotizacion->coti_partido }}
        </div>

        <div class="mb-2">
            <strong>Contacto:</strong>
            {{ $cotizacion->coti_mail1 ?? 'Sin contacto' }}
        </div>

        <div class="mb-2">
            <strong>Estado:</strong>
            <span class="badge
                @if($cotizacion->coti_estado === 'Pendiente') bg-warning text-dark
                @elseif($cotizacion->coti_estado === 'Aprobada') bg-success
                @else bg-secondary @endif">
                {{ $cotizacion->coti_estado }}
            </span>
        </div>

        <div class="mb-2">
            <strong>Encargado Principal:</strong>
            {{ $cotizacion->responsable->usu_descripcion ?? 'Sin asignar' }}
        </div>

        @php $notasGeneralesInfo = \App\Support\CotizacionNotasGenerales::listadoParaVista($cotizacion->coti_notas ?? null); @endphp
        @if(count($notasGeneralesInfo) > 0)
            <div class="mt-3">
                <strong>Notas generales del presupuesto:</strong>
                <div class="alert alert-info mt-2 mb-0">
                    @foreach($notasGeneralesInfo as $notaGeneralInfo)
                        <p class="mb-2">{{ $notaGeneralInfo }}</p>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

@once
<style>
    .collapse-content {
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        transition: max-height 0.45s ease, opacity 0.35s ease;
    }

    .collapse-content.show {
        max-height: 12000px;
        overflow: visible;
        opacity: 1;
    }

    .rotate-180 {
        transform: rotate(180deg);
        transition: transform 0.3s ease;
    }
</style>

<script>
    function toggleInfo(id) {
        const content = document.getElementById('info-' + id);
        if (content) {
            content.classList.toggle('show');
        }

        const chevronIcon = document.getElementById('chevron-' + id);
        if (chevronIcon) {
            chevronIcon.classList.toggle('rotate-180');
        }
    }

    function printAllQr(cotiNum) {
        const url = `${window.location.origin}/cotizaciones/${cotiNum}/qr/all?autoprint=1`;
        const printWindow = window.open(url, '_blank', 'noopener,noreferrer');

        setTimeout(() => {
            if (printWindow && !printWindow.closed) {
                printWindow.close();
            }
        }, 5000);
    }
</script>
@endonce
