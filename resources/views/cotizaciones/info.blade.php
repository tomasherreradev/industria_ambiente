<div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center p-2" style="cursor: pointer; background-color: #000000 !important;" onclick="toggleInfo('{{ $cotizacion->coti_num }}')">
        <h5 class="mb-0">Información de la Cotización</h5>
        <small><strong>Fecha Aprob.:</strong> {{ $cotizacion->coti_fechaaprobado ?? 'Pendiente' }}</small>
        <div>
            <a href="{{ route('cotizaciones.qr.all', ['cotizacion' => $cotizacion->coti_num]) }}" 
                class="text-decoration-none text-white"
                title="Imprimir todos los QR de esta cotización"
                onclick="event.preventDefault(); printAllQr('{{ $cotizacion->coti_num }}')">
                <x-heroicon-o-printer class="text-white" style="width: 30px; height: 30px;"/>
             </a>
        </div>
        <x-heroicon-o-chevron-up id="chevron-{{ $cotizacion->coti_num }}" class="text-white" style="width: 20px; height: 20px;" />
    </div>
    <div id="info-{{ $cotizacion->coti_num }}" class="card-body" style="display: none;">
        @php
            $empresaRelacionada = null;
            if ($cotizacion->coti_cli_empresa) {
                $empresaRelacionada = \App\Models\ClienteEmpresaRelacionada::find($cotizacion->coti_cli_empresa);
            }
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
                {{ $cotizacion->coti_empresa }} - {{ $cotizacion->coti_establecimiento }}
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

            @if(!empty($cotizacion->coti_notas))
                <div class="mt-3">
                    <strong>Observaciones:</strong>
                    <div class="alert alert-info mt-2 mb-0">
                        {{ $cotizacion->coti_notas }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    
    <style>
        .collapsing {
            overflow: hidden;
            transition: height 0.3s ease;
            height: 0;
        }
    
        .collapse.show {
            height: auto;
            transition: height 0.3s ease;
        }
    </style>
    


<script>
    function toggleInfo(id) {
        const infoDiv = document.getElementById('info-' + id);
        const chevronIcon = document.getElementById('chevron-' + id);
        
        if (!infoDiv || !chevronIcon) return;

        // Si el contenido está oculto, lo mostramos y cambiamos el ícono
        if (infoDiv.style.display === 'none' || getComputedStyle(infoDiv).display === 'none') {
            infoDiv.style.display = 'block';
            let height = infoDiv.scrollHeight + 'px';
            infoDiv.style.height = '0';
            requestAnimationFrame(() => {
                infoDiv.style.transition = 'height 0.3s ease';
                infoDiv.style.height = height;
            });

            // Cambiar el ícono a chevron-down
            chevronIcon.setAttribute('x', '0');
            chevronIcon.setAttribute('y', '0');
            chevronIcon.setAttribute('transform', 'rotate(180)');
            
            infoDiv.addEventListener('transitionend', function handler() {
                infoDiv.style.height = 'auto';
                infoDiv.removeEventListener('transitionend', handler);
            });

        } else {
            infoDiv.style.height = infoDiv.scrollHeight + 'px';
            requestAnimationFrame(() => {
                infoDiv.style.transition = 'height 0.3s ease';
                infoDiv.style.height = '0';
            });

            // Cambiar el ícono a chevron-up
            chevronIcon.setAttribute('x', '0');
            chevronIcon.setAttribute('y', '0');
            chevronIcon.setAttribute('transform', 'rotate(0)');
            
            infoDiv.addEventListener('transitionend', function handler() {
                infoDiv.style.display = 'none';
                infoDiv.removeEventListener('transitionend', handler);
            });
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
    