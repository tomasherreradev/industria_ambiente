@php
    $grupo = $grupoData['grupo'];
    $key = $grupoData['key'];
    $numCoti = $key;
    $cotizacion = $cotizaciones->get($numCoti) ?? $grupo['cotizado'] ?? null;
    $instanciaRef = $grupo['instancias'][0]['instancia_muestra'] ?? null;
    $cantidadMuestras = $grupo['instancias']->count();
    $descripcion = $instanciaRef->cotio_descripcion ?? ('Cotización Nº ' . $numCoti);
    if ($cantidadMuestras > 1) {
        $descripcion = 'Cotización Nº ' . $numCoti . ' (' . $cantidadMuestras . ' muestras)';
    }
    $empresa = \App\Support\CotizacionClienteEtiqueta::paraLista($cotizacion);
    if ($empresa === '—') {
        $empresa = 'Sin cliente';
    }
    $estadoGrupo = $grupoData['estadoGrupo'] ?? 'pendiente';
    $collapseId = ($collapsePrefix ?? 'mord') . '-' . $numCoti;

    $fechaInicio = $instanciaRef && $instanciaRef->fecha_inicio_ot
        ? \Carbon\Carbon::parse($instanciaRef->fecha_inicio_ot)->format('d/m/Y H:i')
        : null;
    $fechaFin = !empty($grupoData['fecha_fin'])
        ? $grupoData['fecha_fin']->format('d/m/Y H:i')
        : null;

    if ($grupoData['es_vencida'] ?? false) {
        $pillText = 'Vencida';
        $pillClass = 'muestra-card__pill--danger';
        $toneClass = 'muestra-card--vencida';
    } elseif ($estadoGrupo === 'revisión de resultados') {
        $pillText = 'Rev. resultados';
        $pillClass = 'muestra-card__pill--primary';
        $toneClass = 'muestra-card--activa';
    } elseif ($grupoData['hasPriority'] ?? false) {
        $pillText = 'Prioridad';
        $pillClass = 'muestra-card__pill--warning';
        $toneClass = 'muestra-card--prioridad';
    } elseif ($estadoGrupo === 'en revision analisis') {
        $pillText = 'En revisión';
        $pillClass = 'muestra-card__pill--info';
        $toneClass = 'muestra-card--revision';
    } elseif ($estadoGrupo === 'analizado') {
        $pillText = 'Analizado';
        $pillClass = 'muestra-card__pill--success';
        $toneClass = 'muestra-card--finalizada';
    } else {
        $pillText = 'Coordinada';
        $pillClass = 'muestra-card__pill--primary';
        $toneClass = 'muestra-card--activa';
    }

    $misOrdenesDetalleQuery = !empty($soloMisAsignaciones) ? ['solo_mis_asignaciones' => 1] : [];
    $rutaDetalle = static function ($instanciaMuestra) use ($misOrdenesDetalleQuery) {
        return route('ordenes.all.show', array_merge([
            'cotio_numcoti' => $instanciaMuestra->cotio_numcoti,
            'cotio_item' => $instanciaMuestra->cotio_item,
            'cotio_subitem' => $instanciaMuestra->cotio_subitem,
            'instance' => $instanciaMuestra->instance_number,
        ], $misOrdenesDetalleQuery));
    };

    $primeraInstancia = $instanciaRef;
    $rutaVerPrincipal = $primeraInstancia ? $rutaDetalle($primeraInstancia) : '#';
    $mapsQuery = $cotizacion ? urlencode(\App\Support\CotizacionClienteEtiqueta::direccionDestinatarioMapsQuery($cotizacion)) : '';
    $direccion = $cotizacion ? (\App\Support\CotizacionClienteEtiqueta::direccionDestinatarioTexto($cotizacion) ?: 'N/A') : 'N/A';
@endphp

<article class="muestra-card {{ $toneClass }}">
    <button type="button"
            class="muestra-card__toggle"
            data-bs-toggle="collapse"
            data-bs-target="#{{ $collapseId }}"
            aria-expanded="false"
            aria-controls="{{ $collapseId }}">
        <div class="muestra-card__summary">
            <span class="muestra-card__pill {{ $pillClass }}">{{ $pillText }}</span>
            <h3 class="muestra-card__title">{{ $descripcion }}</h3>
            <p class="muestra-card__company">{{ $empresa }}</p>
            <div class="muestra-card__quick-meta">
                @if($fechaFin)
                    <span class="muestra-card__quick-item">
                        <x-heroicon-o-clock style="width: 14px; height: 14px;" />
                        {{ ($grupoData['es_vencida'] ?? false) ? 'Venció' : 'Vence' }}: {{ $fechaFin }}
                    </span>
                @elseif($fechaInicio)
                    <span class="muestra-card__quick-item">
                        <x-heroicon-o-calendar style="width: 14px; height: 14px;" />
                        {{ $fechaInicio }}
                    </span>
                @endif
                <span class="muestra-card__quick-item">
                    <x-heroicon-o-document-text style="width: 14px; height: 14px;" />
                    Cotización {{ $numCoti }}
                </span>
            </div>
        </div>
        <span class="muestra-card__chevron" aria-hidden="true">
            <x-heroicon-o-chevron-down style="width: 20px; height: 20px;" />
        </span>
    </button>

    <div id="{{ $collapseId }}" class="collapse muestra-card__collapse">
        <div class="muestra-card__body">
            <dl class="muestra-card__details">
                @if($fechaInicio)
                    <div class="muestra-card__detail">
                        <dt>Fecha OT</dt>
                        <dd>{{ $fechaInicio }}</dd>
                    </div>
                @endif
                <div class="muestra-card__detail">
                    <dt>Dirección</dt>
                    <dd>{{ $direccion }}</dd>
                </div>
                <div class="muestra-card__detail">
                    <dt>Cotización Nº</dt>
                    <dd>{{ $numCoti }}</dd>
                </div>
                <div class="muestra-card__detail">
                    <dt>Muestras</dt>
                    <dd>{{ $cantidadMuestras }}</dd>
                </div>
            </dl>

            @if($grupo['instancias']->count() > 0)
                <p class="muestra-card__section-label">Muestras / análisis</p>
                <div class="muestra-card__samples">
                    @foreach($grupo['instancias'] as $instancia)
                        @php
                            $instanciaMuestra = $instancia['instancia_muestra'];
                            $analisisCount = ($instancia['analisis'] ?? collect())->count();
                            $estadoMuestra = strtolower($instanciaMuestra->cotio_estado_analisis ?? 'pendiente');
                            $sampleToneClass = match ($estadoMuestra) {
                                'coordinado', 'coordinado analisis' => 'muestra-card__sample--coordinado',
                                'en revision analisis' => 'muestra-card__sample--revision',
                                'analizado' => 'muestra-card__sample--finalizada',
                                'suspension' => 'muestra-card__sample--suspension',
                                default => 'muestra-card__sample--pendiente',
                            };
                        @endphp
                        <div class="muestra-card__sample {{ $sampleToneClass }}">
                            <div class="muestra-card__sample-icon">
                                <x-heroicon-o-beaker style="width: 18px; height: 18px;" />
                            </div>
                            <div class="muestra-card__sample-info">
                                <strong>{{ $instanciaMuestra->cotio_descripcion ?? 'Muestra' }}</strong>
                                <span>
                                    OT {{ $instanciaMuestra->otn ?? '—' }}
                                    · {{ $analisisCount }} análisis
                                    · {{ ucfirst($estadoMuestra) }}
                                </span>
                            </div>
                            <a href="{{ $rutaDetalle($instanciaMuestra) }}" class="btn btn-sm btn-outline-secondary muestra-card__sample-btn">Ver</a>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="muestra-card__actions">
                @if($grupo['instancias']->count() === 1)
                    <a href="{{ $rutaVerPrincipal }}" class="btn btn-primary muestra-card__btn-main">
                        <x-heroicon-o-eye style="width: 18px; height: 18px;" class="me-1" />
                        Ver orden
                    </a>
                @endif
                @if($mapsQuery)
                    <a href="https://www.google.com/maps/search/?api=1&query={{ $mapsQuery }}"
                       target="_blank"
                       rel="noopener"
                       class="btn {{ $grupo['instancias']->count() === 1 ? 'btn-outline-primary' : 'btn-primary muestra-card__btn-main' }}">
                        <x-heroicon-o-map style="width: 18px; height: 18px;" class="me-1" />
                        Abrir en Maps
                    </a>
                @endif
            </div>
        </div>
    </div>
</article>
