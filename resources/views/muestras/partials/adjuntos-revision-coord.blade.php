@if($instanciaActual)
@php
    $adjuntosVentasLista = $adjuntosVentas ?? collect();
    $adjuntosInstanciaLista = $adjuntosInstancia ?? collect();
    $hayAdjuntos = $adjuntosVentasLista->isNotEmpty() || $adjuntosInstanciaLista->isNotEmpty();
@endphp

<div id="adjuntosRevisionCoord">
    @if($instanciaActual->cotio_estado != 'muestreado')
        <div class="mb-3">
            <input type="file"
                   class="form-control"
                   id="adjuntos_instancia_input"
                   accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,application/pdf,image/*"
                   multiple>
            <small class="text-muted d-block mt-1">PDF o imágenes. Máximo 10 MB por archivo.</small>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="btnSubirAdjuntosInstancia"
                    data-instancia-id="{{ $instanciaActual->id }}">
                <x-heroicon-o-arrow-up-tray style="width: 16px; height: 16px;" class="me-1" />
                Subir archivos
            </button>
        </div>
    @endif

    <ul class="list-group list-group-flush border rounded" id="listaAdjuntosInstancia">
        @foreach($adjuntosVentasLista as $adjunto)
            <li class="list-group-item d-flex justify-content-between align-items-center adjunto-ventas-item" data-adjunto-id="{{ $adjunto->id }}">
                <div class="d-flex align-items-center gap-2 text-truncate me-2">
                    @if($adjunto->esImagen())
                        <x-heroicon-o-photo style="width: 18px; height: 18px;" class="text-info flex-shrink-0" />
                    @else
                        <x-heroicon-o-document style="width: 18px; height: 18px;" class="text-danger flex-shrink-0" />
                    @endif
                    <a href="{{ route('muestras.adjuntos-ventas.descargar', $adjunto) }}" target="_blank" rel="noopener" class="text-truncate">
                        {{ $adjunto->original_name }}
                    </a>
                </div>
                @if($instanciaActual->cotio_estado != 'muestreado')
                    <button type="button"
                            class="btn btn-sm btn-outline-danger d-inline-flex align-items-center justify-content-center btn-eliminar-adjunto-ventas"
                            style="width: 2rem; height: 2rem; padding: 0;"
                            data-adjunto-id="{{ $adjunto->id }}"
                            title="Eliminar"
                            aria-label="Eliminar adjunto">
                        <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                    </button>
                @endif
            </li>
        @endforeach

        @foreach($adjuntosInstanciaLista as $adjunto)
            <li class="list-group-item d-flex justify-content-between align-items-center adjunto-instancia-item" data-adjunto-id="{{ $adjunto->id }}">
                <div class="d-flex align-items-center gap-2 text-truncate me-2">
                    @if($adjunto->esImagen())
                        <x-heroicon-o-photo style="width: 18px; height: 18px;" class="text-info flex-shrink-0" />
                    @else
                        <x-heroicon-o-document style="width: 18px; height: 18px;" class="text-danger flex-shrink-0" />
                    @endif
                    <a href="{{ route('muestras.adjuntos.descargar', $adjunto) }}" target="_blank" rel="noopener" class="text-truncate">
                        {{ $adjunto->original_name }}
                    </a>
                </div>
                @if($instanciaActual->cotio_estado != 'muestreado')
                    <button type="button"
                            class="btn btn-sm btn-outline-danger d-inline-flex align-items-center justify-content-center btn-eliminar-adjunto-instancia"
                            style="width: 2rem; height: 2rem; padding: 0;"
                            data-adjunto-id="{{ $adjunto->id }}"
                            title="Eliminar"
                            aria-label="Eliminar adjunto">
                        <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                    </button>
                @endif
            </li>
        @endforeach

        @unless($hayAdjuntos)
            <li class="list-group-item text-muted adjuntos-vacio">No hay archivos adjuntos.</li>
        @endunless
    </ul>
</div>
@endif
