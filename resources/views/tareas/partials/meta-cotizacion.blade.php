@if($cotizacion)
    <div class="mt-3 small text-dark tarea-meta-grid">
        <div class="row g-2">
            <div class="col-md-4 tarea-meta-item">
                <div class="tarea-meta-icon">
                    <x-heroicon-o-calendar style="width: 14px; height: 14px;" />
                </div>
                <div class="tarea-meta-content">
                    <span class="tarea-meta-label">{{ $fechaLabel ?? 'Fecha y hora' }}</span>
                    <span class="tarea-meta-value">
                        @if(!empty($grupo['instancias'][0]['instancia_muestra']->fecha_inicio_muestreo))
                            {{ \Carbon\Carbon::parse($grupo['instancias'][0]['instancia_muestra']->fecha_inicio_muestreo)->format('d/m/Y H:i') }}
                        @else
                            N/A
                        @endif
                    </span>
                </div>
            </div>
            <div class="col-md-4 tarea-meta-item">
                <div class="tarea-meta-icon">
                    <x-heroicon-o-map-pin style="width: 14px; height: 14px;" />
                </div>
                <div class="tarea-meta-content">
                    <span class="tarea-meta-label">Dirección</span>
                    <span class="tarea-meta-value">{{ \App\Support\CotizacionClienteEtiqueta::direccionDestinatarioTexto($cotizacion) ?: 'N/A' }}</span>
                </div>
            </div>
            <div class="col-md-4 tarea-meta-item">
                <div class="tarea-meta-icon">
                    <x-heroicon-o-user-circle style="width: 14px; height: 14px;" />
                </div>
                <div class="tarea-meta-content">
                    <span class="tarea-meta-label">Cotización N°</span>
                    <span class="tarea-meta-value">{{ $cotizacion->coti_num ?? 'N/A' }}</span>
                </div>
            </div>
        </div>
    </div>
@endif
