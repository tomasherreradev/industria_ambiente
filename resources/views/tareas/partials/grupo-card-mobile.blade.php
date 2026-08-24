@php
    $grupo = $grupoData['grupo'];
    $key = $grupoData['key'];
    $isHermana = $grupo['is_hermana'];
    if ($isHermana) {
        [$numCoti, $itemId, $subitemId] = explode('_', $key);
        $suffixId = $subitemId;
    } else {
        [$numCoti, $instanceNumber, $itemId] = explode('_', $key);
        $suffixId = $instanceNumber;
    }

    $cotizacion = $cotizaciones->get($numCoti);
    $instanciaRef = $grupo['instancias'][0]['instancia_muestra'] ?? null;
    $descripcion = $instanciaRef->cotio_descripcion ?? 'Sin descripción';
    $empresa = \App\Support\CotizacionClienteEtiqueta::paraLista($cotizacion);
    if ($empresa === '—') {
        $empresa = 'Sin cliente';
    }
    $estadoGrupo = $grupoData['estado_grupo'];
    $collapseId = ($collapsePrefix ?? 'mcard') . '-' . $numCoti . '-' . $itemId . '-' . $suffixId;

    $fechaInicio = $instanciaRef && $instanciaRef->fecha_inicio_muestreo
        ? \Carbon\Carbon::parse($instanciaRef->fecha_inicio_muestreo)->format('d/m/Y H:i')
        : null;
    $fechaFin = $grupoData['fecha_fin']
        ? $grupoData['fecha_fin']->format('d/m/Y H:i')
        : null;

    if ($grupoData['es_vencida'] ?? false) {
        $pillText = 'Vencida';
        $pillClass = 'muestra-card__pill--danger';
        $toneClass = 'muestra-card--vencida';
    } elseif ($grupoData['es_prioritario'] ?? false) {
        $pillText = 'Prioridad';
        $pillClass = 'muestra-card__pill--warning';
        $toneClass = 'muestra-card--prioridad';
    } elseif ($estadoGrupo === 'en revision muestreo') {
        $pillText = 'En revisión';
        $pillClass = 'muestra-card__pill--info';
        $toneClass = 'muestra-card--revision';
    } elseif ($estadoGrupo === 'muestreado') {
        $pillText = 'Finalizada';
        $pillClass = 'muestra-card__pill--success';
        $toneClass = 'muestra-card--finalizada';
    } elseif ($estadoGrupo === 'suspension') {
        $pillText = 'Suspensión';
        $pillClass = 'muestra-card__pill--danger';
        $toneClass = 'muestra-card--vencida';
    } else {
        $pillText = 'Coordinada';
        $pillClass = 'muestra-card__pill--primary';
        $toneClass = 'muestra-card--activa';
    }

    $primeraInstancia = $instanciaRef;
    $rutaVerPrincipal = $primeraInstancia
        ? (Auth::user()->rol == 'laboratorio'
            ? route('ordenes.all.show', [$primeraInstancia->cotio_numcoti, $primeraInstancia->cotio_item, $primeraInstancia->cotio_subitem, $primeraInstancia->instance_number])
            : route('tareas.all.show', [$primeraInstancia->cotio_numcoti, $primeraInstancia->cotio_item, $primeraInstancia->cotio_subitem, $primeraInstancia->instance_number]))
        : '#';

    $mapsQuery = $cotizacion ? urlencode(\App\Support\CotizacionClienteEtiqueta::direccionDestinatarioMapsQuery($cotizacion)) : '';
    $direccion = $cotizacion ? (\App\Support\CotizacionClienteEtiqueta::direccionDestinatarioTexto($cotizacion) ?: 'N/A') : 'N/A';
    $idInterno = $primeraInstancia ? ('#' . $primeraInstancia->instance_number) : 'N/A';
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
                @if($cotizacion)
                    <span class="muestra-card__quick-item">
                        <x-heroicon-o-document-text style="width: 14px; height: 14px;" />
                        Cotización {{ $cotizacion->coti_num }}
                    </span>
                @endif
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
                        <dt>Fecha y hora</dt>
                        <dd>{{ $fechaInicio }}</dd>
                    </div>
                @endif
                <div class="muestra-card__detail">
                    <dt>Dirección</dt>
                    <dd>{{ $direccion }}</dd>
                </div>
                <div class="muestra-card__detail">
                    <dt>Cotización Nº</dt>
                    <dd>{{ $cotizacion->coti_num ?? 'N/A' }}</dd>
                </div>
                <div class="muestra-card__detail">
                    <dt>ID interno</dt>
                    <dd>{{ $idInterno }}</dd>
                </div>
            </dl>

            @if($grupo['instancias']->count() > 0)
                <p class="muestra-card__section-label">Muestras asociadas</p>
                <div class="muestra-card__samples">
                    @foreach($grupo['instancias'] as $instancia)
                        @php
                            $instanciaMuestra = $instancia['instancia_muestra'];
                            $rutaVer = Auth::user()->rol == 'laboratorio'
                                ? route('ordenes.all.show', [$instanciaMuestra->cotio_numcoti, $instanciaMuestra->cotio_item, $instanciaMuestra->cotio_subitem, $instanciaMuestra->instance_number])
                                : route('tareas.all.show', [$instanciaMuestra->cotio_numcoti, $instanciaMuestra->cotio_item, $instanciaMuestra->cotio_subitem, $instanciaMuestra->instance_number]);
                            $estadoMuestra = strtolower($instanciaMuestra->cotio_estado ?? 'pendiente');
                        @endphp
                        <div class="muestra-card__sample">
                            <div class="muestra-card__sample-icon">
                                <x-heroicon-o-beaker style="width: 18px; height: 18px;" />
                            </div>
                            <div class="muestra-card__sample-info">
                                <strong>{{ $instanciaMuestra->cotio_descripcion ?? 'Muestra' }}</strong>
                                <span>ID {{ $instanciaMuestra->instance_number }} · {{ ucfirst($estadoMuestra) }}</span>
                            </div>
                            <a href="{{ $rutaVer }}" class="btn btn-sm btn-outline-secondary muestra-card__sample-btn">Ver</a>
                        </div>
                        @if($instanciaMuestra->vehiculo)
                            <div class="muestra-card__vehicle">
                                <x-heroicon-o-truck style="width: 14px; height: 14px;" />
                                {{ $instanciaMuestra->vehiculo->marca }} {{ $instanciaMuestra->vehiculo->modelo }} ({{ $instanciaMuestra->vehiculo->patente }})
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="muestra-card__actions">
                @if($grupo['instancias']->count() === 1)
                    <a href="{{ $rutaVerPrincipal }}" class="btn btn-primary muestra-card__btn-main">
                        <x-heroicon-o-eye style="width: 18px; height: 18px;" class="me-1" />
                        Ver muestra
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
