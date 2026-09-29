@php
    $isInformes = userHasRole('informes');
    $vistaActiva = $vistaActiva ?? 'pendientes';
@endphp

<div class="row g-3">
    @foreach($informesPorCotizacion as $numCoti => $informeData)
        @php
            $coti = $informeData['cotizacion'];
            $muestras = $informeData['muestras'];
            $matrizNombre = optional(optional($coti)->matriz)->matriz_descripcion ?? '—';
            $pendientesCoti = $muestras->filter(fn ($m) => !$m->firmado)->count();
            $firmadosCoti = $informeData['informes_firmados'] ?? $muestras->filter(fn ($m) => $m->firmado)->count();
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
                                · {{ $muestras->count() }} {{ $muestras->count() === 1 ? 'muestra' : 'muestras' }}
                            </p>
                            <div class="inf-coti-card__stats">
                                @if($pendientesCoti > 0)
                                    <span class="inf-badge-firma inf-badge-firma--pendiente">
                                        {{ $pendientesCoti }} pendiente{{ $pendientesCoti === 1 ? '' : 's' }}
                                    </span>
                                @endif
                                @if($firmadosCoti > 0)
                                    <span class="inf-badge-firma inf-badge-firma--firmado">
                                        {{ $firmadosCoti }} firmado{{ $firmadosCoti === 1 ? '' : 's' }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-sm btn-light"
                                data-bs-toggle="collapse" data-bs-target="#muestras-inf-{{ $numCoti }}"
                                aria-expanded="false" aria-controls="muestras-inf-{{ $numCoti }}">
                            <x-heroicon-o-chevron-down style="width: 16px; height: 16px;" class="inf-chevron" />
                            Detalle
                        </button>
                        <a href="{{ route('informes.pdf-masivo', ['cotizacion' => $numCoti]) }}"
                           class="btn btn-sm btn-outline-secondary" target="_blank" title="Descargar PDF masivo">
                            <x-heroicon-o-document-arrow-down style="width: 16px; height: 16px;" />
                            PDF masivo
                        </a>
                    </div>
                </div>

                <div class="collapse" id="muestras-inf-{{ $numCoti }}">
                    <div class="inf-coti-card__muestras">
                        @foreach($muestras as $muestra)
                            @php
                                $descUpper = strtoupper(trim($muestra->cotio_descripcion ?? ''));
                                $isSpecialMuestra = str_contains($descUpper, 'CONSULTORIA')
                                    || str_contains($descUpper, 'ASP')
                                    || str_contains($descUpper, 'APARATOS SOMETIDOS A PRESIÓN')
                                    || str_contains($descUpper, 'APARATOS SOMETIDOS A PRESION')
                                    || str_contains($descUpper, 'CLARKE');
                                $tipoInforme = $muestra->tipo_informe ?? 'final';
                                $puedeEditarInformeEnBandeja = ! $muestra->firmado && ! $muestra->listo_para_firmar;
                            @endphp
                            <div class="inf-muestra-row">
                                <div class="inf-muestra-row__id">
                                    <x-heroicon-o-beaker style="width: 16px; height: 16px;" class="text-muted" />
                                    <span>{{ $muestra->cotio_identificacion ?: '—' }}</span>
                                </div>
                                <div class="inf-muestra-row__desc">
                                    {{ $muestra->cotio_descripcion }}
                                    <span class="text-muted">#{{ $muestra->instance_number }}</span>
                                    <span class="inf-badge-tipo inf-badge-tipo--{{ $tipoInforme === 'final' ? 'final' : 'parcial' }} ms-1">
                                        {{ $tipoInforme === 'final' ? 'Final' : 'Parcial' }}
                                    </span>
                                    @if($muestra->firmado)
                                        <span class="inf-badge-firma inf-badge-firma--firmado ms-1">Firmado</span>
                                    @elseif($muestra->listo_para_firmar)
                                        <span class="inf-badge-firma inf-badge-firma--listo ms-1">Listo para firmar</span>
                                    @else
                                        <span class="inf-badge-firma inf-badge-firma--pendiente ms-1">En bandeja</span>
                                    @endif
                                </div>
                                @if($muestra->identificador_documento_firma)
                                    <div class="inf-muestra-row__firma d-none d-lg-block" title="ID documento firma">
                                        {{ $muestra->identificador_documento_firma }}
                                    </div>
                                @else
                                    <div class="inf-muestra-row__firma d-none d-lg-block">—</div>
                                @endif
                                <div class="inf-muestra-row__actions">
                                    @if($isInformes && !$isSpecialMuestra && $puedeEditarInformeEnBandeja)
                                        <button type="button" class="btn btn-sm btn-outline-primary preview-informe-btn"
                                                data-cotizacion="{{ $numCoti }}"
                                                data-item="{{ $muestra->cotio_item }}"
                                                data-instance="{{ $muestra->instance_number }}"
                                                title="Vista previa y editar">
                                            <x-heroicon-o-eye style="width: 15px; height: 15px;" />
                                        </button>
                                    @endif
                                    @if(userCanEditInformeProtocoloPdf() && !$isSpecialMuestra && $puedeEditarInformeEnBandeja)
                                        <a href="{{ route('informes.protocolo-pdf.edit', [
                                            'cotio_numcoti' => $numCoti,
                                            'cotio_item' => $muestra->cotio_item,
                                            'instance_number' => $muestra->instance_number,
                                        ]) }}"
                                           class="btn btn-sm btn-warning" title="Editar protocolo PDF">
                                            <x-heroicon-o-pencil-square style="width: 14px; height: 14px;" />
                                        </a>
                                    @endif
                                    <a href="{{ route('informes.pdf', [
                                        'cotio_numcoti' => $numCoti,
                                        'cotio_item' => $muestra->cotio_item,
                                        'instance_number' => $muestra->instance_number,
                                    ]) }}"
                                       class="btn btn-sm btn-outline-secondary"
                                       target="_blank"
                                       title="{{ $muestra->firmado ? 'Descargar PDF firmado' : 'Descargar PDF' }}">
                                        <x-heroicon-o-document-arrow-down style="width: 15px; height: 15px;" />
                                    </a>
                                    @if(userPuedeFirmarInforme($muestra))
                                        <a href="{{ route('informes.firmar', [
                                            'cotio_numcoti' => $numCoti,
                                            'cotio_item' => $muestra->cotio_item,
                                            'instance_number' => $muestra->instance_number,
                                        ]) }}"
                                           class="btn btn-sm btn-primary"
                                           title="Firmar informe">
                                            <x-heroicon-o-pencil style="width: 15px; height: 15px;" />
                                            Firmar
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

{{-- Modal vista previa (sin cambios funcionales) --}}
@include('informes.partials.lista-modal-preview')
