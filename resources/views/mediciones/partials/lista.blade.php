<div class="row g-3">
    @foreach($medicionesPorCotizacion as $numCoti => $bloque)
        @php
            $coti = $bloque['cotizacion'];
            $muestras = $bloque['muestras'];
            $matrizNombre = optional(optional($coti)->matriz)->matriz_descripcion ?? '—';
            $sinSubirCoti = $bloque['sin_subir'] ?? 0;
            $subidosCoti = $bloque['subidos'] ?? 0;
        @endphp
        <div class="col-12">
            <article class="inf-coti-card">
                <div class="inf-coti-card__main">
                    <div class="inf-coti-card__info">
                        <span class="inf-coti-card__badge">{{ etiquetaNumeroCotizacion($numCoti) }}</span>
                        <div>
                            <h6 class="inf-coti-card__title mb-0">
                                {{ \App\Support\CotizacionClienteEtiqueta::paraLista($coti) }}
                            </h6>
                            <p class="inf-coti-card__meta mb-0">
                                {{ $matrizNombre }}
                                · {{ $muestras->count() }} {{ $muestras->count() === 1 ? 'medición' : 'mediciones' }} en esta bandeja
                            </p>
                            <div class="inf-coti-card__stats">
                                @if($sinSubirCoti > 0)
                                    <span class="inf-badge-firma inf-badge-firma--pendiente">
                                        {{ $sinSubirCoti }} sin PDF
                                    </span>
                                @endif
                                @if($subidosCoti > 0)
                                    <span class="inf-badge-firma inf-badge-firma--firmado">
                                        {{ $subidosCoti }} con PDF
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-sm btn-light"
                                data-bs-toggle="collapse" data-bs-target="#med-muestras-{{ $numCoti }}"
                                aria-expanded="false">
                            <x-heroicon-o-chevron-down style="width: 16px; height: 16px;" class="inf-chevron" />
                            Detalle
                        </button>
                        <a href="{{ route('mediciones.show', $numCoti) }}" class="btn btn-sm btn-primary">
                            <x-heroicon-o-pencil style="width: 15px; height: 15px;" />
                            Gestionar
                        </a>
                    </div>
                </div>

                <div class="collapse" id="med-muestras-{{ $numCoti }}">
                    <div class="inf-coti-card__muestras">
                        @foreach($muestras as $muestra)
                            @php
                                $tienePdf = trim((string) ($muestra->archivo_informe ?? '')) !== '';
                            @endphp
                            <div class="inf-muestra-row">
                                <div class="inf-muestra-row__id">
                                    <x-heroicon-o-beaker style="width: 16px; height: 16px;" class="text-muted" />
                                    <span>{{ $muestra->cotio_identificacion ?: '—' }}</span>
                                </div>
                                <div class="inf-muestra-row__desc">
                                    {{ $muestra->cotio_descripcion }}
                                    <span class="text-muted">#{{ $muestra->instance_number }}</span>
                                    @if($tienePdf)
                                        <span class="inf-badge-firma inf-badge-firma--firmado ms-1">Informe subido</span>
                                        @if($muestra->aprobado_informe)
                                            <span class="inf-badge-firma inf-badge-firma--listo ms-1">Aprobado → informes</span>
                                        @else
                                            <span class="inf-badge-firma inf-badge-firma--pendiente ms-1">Pendiente de aprobación</span>
                                        @endif
                                    @else
                                        <span class="inf-badge-firma inf-badge-firma--pendiente ms-1">Sin informe PDF</span>
                                    @endif
                                </div>
                                <div class="inf-muestra-row__actions">
                                    <a href="{{ route('mediciones.ver', ['cotizacion' => $numCoti, 'item' => $muestra->cotio_item, 'instance' => $muestra->instance_number]) }}"
                                       class="btn btn-sm btn-outline-primary" title="Abrir medición">
                                        <x-heroicon-o-eye style="width: 15px; height: 15px;" />
                                    </a>
                                    @if($tienePdf)
                                        <a href="{{ route('mediciones.informe.ver', ['cotizacion' => $numCoti, 'item' => $muestra->cotio_item, 'instance' => $muestra->instance_number]) }}"
                                           class="btn btn-sm btn-outline-secondary" title="Descargar PDF" target="_blank">
                                            <x-heroicon-o-document-arrow-down style="width: 15px; height: 15px;" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>
        </div>
    @endforeach
</div>

@if(isset($pagination) && $pagination->hasPages())
    <div class="d-flex justify-content-center mt-4">
        {{ $pagination->links() }}
    </div>
@endif
