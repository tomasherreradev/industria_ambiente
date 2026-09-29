@extends('layouts.app')

@section('content')
@include('partials.ucrud-styles')
<link rel="stylesheet" href="{{ asset('css/dashboard-home.css') }}?v={{ filemtime(public_path('css/dashboard-home.css')) }}">
<link rel="stylesheet" href="{{ asset('css/dashboard-sub.css') }}?v={{ filemtime(public_path('css/dashboard-sub.css')) }}">

@php
    $estadoFiltroActual = $estadoFiltro ?? request('estado', 'all');
    if ($estadoFiltroActual === null || $estadoFiltroActual === '') {
        $estadoFiltroActual = 'all';
    }
    $metodoFiltroActual = $metodoFiltro ?? request('metodo', '');
    $urlFiltroQuery = function (?string $estado = null, ?string $metodo = null) use ($estadoFiltroActual, $metodoFiltroActual): string {
        $query = collect(request()->query())->all();

        if ($estado !== null) {
            if ($estado === 'all') {
                unset($query['estado']);
            } else {
                $query['estado'] = $estado;
            }
        } elseif ($estadoFiltroActual === 'all') {
            unset($query['estado']);
        } elseif ($estadoFiltroActual !== '') {
            $query['estado'] = $estadoFiltroActual;
        }

        if ($metodo !== null) {
            if ($metodo === '') {
                unset($query['metodo']);
            } else {
                $query['metodo'] = $metodo;
            }
        } elseif ($metodoFiltroActual === '') {
            unset($query['metodo']);
        } else {
            $query['metodo'] = $metodoFiltroActual;
        }

        $qs = http_build_query($query);

        return request()->url().($qs !== '' ? '?'.$qs : '');
    };
    $urlFiltroEstado = fn (string $estado): string => $urlFiltroQuery($estado, null);
    $urlFiltroMetodo = fn (?string $metodo): string => $urlFiltroQuery(null, $metodo);
    $cardEstadoActivo = fn (string $estado) => $estadoFiltroActual === $estado ? ' dash-kpi--active' : '';
    $estadosFiltroOpciones = [
        ['key' => 'all', 'label' => 'Todos', 'dot' => 'bg-secondary'],
        ['key' => 'pendientes_coordinar', 'label' => 'Pendientes por coordinar', 'dot' => 'bg-primary'],
        ['key' => 'coordinado analisis', 'label' => 'Pendientes de análisis', 'dot' => 'bg-warning'],
        ['key' => 'en revision analisis', 'label' => 'Pendientes de revisión', 'dot' => 'bg-info'],
        ['key' => 'analizado', 'label' => 'Finalizados', 'dot' => 'bg-success'],
    ];
    $estadoFiltroProximos = ['key' => 'proximos', 'label' => 'Próximos 3 días', 'dot' => 'bg-dark'];
    $estadoFiltroActivoMeta = collect($estadosFiltroOpciones)->firstWhere('key', $estadoFiltroActual)
        ?? ($estadoFiltroActual === 'proximos' ? $estadoFiltroProximos : ['key' => 'all', 'label' => 'Todos', 'dot' => 'bg-secondary']);
@endphp
<div class="container-fluid px-3 px-lg-4 py-4 ucrud dash-home dash-sub">
    @include('dashboard.partials.sub-hero', [
        'title' => 'Dashboard de análisis',
        'subtitle' => 'Seguimiento de OT, responsables y estados del laboratorio.',
        'backUrl' => route('dashboard'),
    ])

    <div class="dash-home__kpi-grid mb-3">
        <a href="{{ $urlFiltroEstado('pendientes_coordinar') }}" class="dash-kpi dash-kpi--primary{{ $cardEstadoActivo('pendientes_coordinar') }}">
            <span class="dash-kpi__icon"><x-heroicon-o-magnifying-glass /></span>
            <span>
                <span class="dash-kpi__label">Por coordinar</span>
                <span class="dash-kpi__value">{{ number_format($pendientesPorCoordinar, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Pendientes de coordinación</span>
            </span>
        </a>
        <a href="{{ $urlFiltroEstado('coordinado analisis') }}" class="dash-kpi dash-kpi--informes{{ $cardEstadoActivo('coordinado analisis') }}">
            <span class="dash-kpi__icon"><x-heroicon-o-clock /></span>
            <span>
                <span class="dash-kpi__label">De análisis</span>
                <span class="dash-kpi__value">{{ number_format($pendientesDeAnalisis, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">En cola de análisis</span>
            </span>
        </a>
        <a href="{{ $urlFiltroEstado('en revision analisis') }}" class="dash-kpi dash-kpi--muestreo{{ $cardEstadoActivo('en revision analisis') }}">
            <span class="dash-kpi__icon"><x-heroicon-o-arrow-path /></span>
            <span>
                <span class="dash-kpi__label">En revisión</span>
                <span class="dash-kpi__value">{{ number_format($pendientesDeRevision, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Pendientes de revisión</span>
            </span>
        </a>
        <a href="{{ $urlFiltroEstado('analizado') }}" class="dash-kpi dash-kpi--analisis{{ $cardEstadoActivo('analizado') }}">
            <span class="dash-kpi__icon"><x-heroicon-o-check-circle /></span>
            <span>
                <span class="dash-kpi__label">Finalizados</span>
                <span class="dash-kpi__value">{{ number_format($finalizados, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Análisis completados</span>
            </span>
        </a>
    </div>

    {{-- Contenido principal --}}
    <div class="row g-4">
        {{-- Análisis asignados --}}
        <div class="col-lg-8">
            <div class="dash-panel h-100">
                <div class="dash-panel__head">
                    <div>
                        <h2 class="dash-panel__title">Análisis asignados</h2>
                        <p class="dash-panel__subtitle mb-0">
                            Análisis asignados a mi o a mi equipo
                            @if(isset($esDiaUno) && $esDiaUno)
                                <span class="ucrud-chip ucrud-chip--cyan ms-1">Mes actual (día 1)</span>
                            @endif
                        </p>
                    </div>
                    <div class="dash-filter-bar">
                            <!-- Filtro de Estado -->
                            <div class="dropdown">
                                <button class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm dropdown-toggle d-inline-flex align-items-center gap-2" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="rounded-circle flex-shrink-0 {{ $estadoFiltroActivoMeta['dot'] }}" style="width:10px;height:10px;"></span>
                                    <span id="filterDropdownLabel">{{ $estadoFiltroActivoMeta['label'] }}</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterDropdown">
                                    @foreach($estadosFiltroOpciones as $opcion)
                                        <li>
                                            <a class="dropdown-item filter-option d-flex align-items-center gap-2 {{ $estadoFiltroActual === $opcion['key'] ? 'active' : '' }}"
                                               href="{{ $urlFiltroEstado($opcion['key']) }}"
                                               data-estado="{{ $opcion['key'] }}"
                                               data-label="{{ $opcion['label'] }}"
                                               data-dot="{{ $opcion['dot'] }}">
                                                <span class="rounded-circle flex-shrink-0 {{ $opcion['dot'] }}" style="width:10px;height:10px;"></span>
                                                <span>{{ $opcion['label'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item filter-option d-flex align-items-center gap-2 {{ $estadoFiltroActual === $estadoFiltroProximos['key'] ? 'active' : '' }}"
                                           href="{{ $urlFiltroEstado($estadoFiltroProximos['key']) }}"
                                           data-estado="{{ $estadoFiltroProximos['key'] }}"
                                           data-label="{{ $estadoFiltroProximos['label'] }}"
                                           data-dot="{{ $estadoFiltroProximos['dot'] }}">
                                            <span class="rounded-circle flex-shrink-0 {{ $estadoFiltroProximos['dot'] }}" style="width:10px;height:10px;"></span>
                                            <span>{{ $estadoFiltroProximos['label'] }}</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <!-- Filtro de Método de Análisis -->
                            <div class="dropdown">
                                <button class="ucrud-btn ucrud-btn--outline-primary ucrud-btn--sm dropdown-toggle" type="button" id="metodoFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-flask me-1"></i> 
                                    @if(request()->get('metodo'))
                                        Método: {{ $metodosDisponibles->firstWhere('metodo_codigo', request()->get('metodo'))->metodo_descripcion ?? 'Seleccionado' }}
                                    @else
                                        Método
                                    @endif
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="metodoFilterDropdown">
                                    <li><a class="dropdown-item metodo-filter-option" href="{{ $urlFiltroMetodo('') }}">
                                        <i class="fas fa-times me-2 text-muted"></i> Todos los métodos
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    @foreach($metodosDisponibles as $metodo)
                                        <li>
                                            <a class="dropdown-item metodo-filter-option {{ request()->get('metodo') == $metodo->metodo_codigo ? 'active' : '' }}"
                                               href="{{ $urlFiltroMetodo($metodo->metodo_codigo) }}">
                                                {{ $metodo->metodo_descripcion ?? $metodo->metodo_codigo }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            
                            <!-- Botón para exportar -->
                            <button type="button" class="ucrud-btn ucrud-btn--success ucrud-btn--sm" data-bs-toggle="modal" data-bs-target="#modalExportar">
                                <x-heroicon-o-arrow-down-tray style="width: 14px; height: 14px;" />
                                Exportar
                            </button>
                    </div>
                </div>
                <div class="p-0">
                    <div class="accordion p-2" id="analisisAccordion">
                        @forelse($analisisAgrupados as $grupo => $analisisGrupo)
                            @php
                                $primerAnalisis = $analisisGrupo->first();
                                // dd($primerAnalisis);   
                                $muestra = $primerAnalisis->muestra;
                                $accordionId = 'muestra-' . str_replace(['-', '.'], '', $grupo);

                                $estado = $primerAnalisis->id
                                    ? ($primerAnalisis->cotio_estado_analisis ?? 'pendiente_coordinar')
                                    : 'pendiente_coordinar';
                                $badgeColor = match($estado) {
                                    'coordinado analisis' => 'bg-warning',
                                    'en revision analisis' => 'bg-info',
                                    'analizado' => 'bg-success text-white',
                                    'suspension' => 'bg-danger text-white',
                                    'pendiente_coordinar' => 'bg-primary text-white',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <div class="accordion-item border-0">
                                <h2 class="accordion-header" id="heading{{ $accordionId }}">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $accordionId }}" aria-expanded="false" aria-controls="collapse{{ $accordionId }}">
                                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                            <div>
                                                <strong>Muestra: </strong>
                                                @if($muestra)
                                                        {{ $muestra->cotio_descripcion ?? 'N/A' }} (#{{ $muestra->otn ? $muestra->otn : $muestra->instance_number ?? 'N/A' }})
                                                        <span class="text-muted small">
                                                            <strong>Cotización:</strong> 
                                                        <a href="{{ route('cotizaciones.ver-detalle', $muestra->cotio_numcoti) }}" class="text-muted">{{ $muestra->cotio_numcoti ?? 'N/A' }}</a>
                                                        @if($muestra->cotizacion && $muestra->cotizacion->coti_cuotas)
                                                                    <span class="badge bg-[#0dcaf0] text-white px-1 py-0 rounded-pill ms-1" style="font-size: 0.6rem; background-color: #0dcaf0;">CUOTAS</span>
                                                        @endif
                                                        </span>
                                                        @if($muestra->enable_inform ?? false)
                                                            <a href="{{ route('informes.show', [
                                                                'cotio_numcoti' => $muestra->cotio_numcoti,
                                                                'cotio_item' => $muestra->cotio_item,
                                                                'instance_number' => $muestra->instance_number,
                                                            ]) }}"
                                                               class="badge bg-success rounded-pill text-decoration-none ms-1"
                                                               style="font-size: 0.7em;"
                                                               title="Ver informe">
                                                                En informes
                                                            </a>
                                                        @else
                                                            <span class="badge bg-secondary rounded-pill ms-1" style="font-size: 0.7em;" title="La muestra aún no fue enviada a informes">
                                                                Sin informe
                                                            </span>
                                                        @endif
                                                @else
                                                    'N/A'
                                                @endif
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                {{-- @if($muestra && $muestra->es_priori)
                                                    <span class="badge bg-warning text-dark" style="font-size: 0.7em;">
                                                        <i class="fas fa-star me-1"></i>PRIORIDAD
                                                    </span>
                                                @endif --}}
                                                <span class="small {{ $badgeColor }}" style="padding: 2px 5px; border-radius: 5px;">
                                                    {{ $estado === 'pendiente_coordinar' ? 'Pendiente por coordinar' : ucfirst($estado) }}
                                                </span>
                                                <span class="badge bg-primary rounded-pill">
                                                    @if($analisisGrupo->isEmpty() || ($analisisGrupo->count() == 1 && !$analisisGrupo->first()->id))
                                                        Sin análisis
                                                    @else
                                                        {{ count($analisisGrupo) }} análisis
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse{{ $accordionId }}" class="accordion-collapse collapse" aria-labelledby="heading{{ $accordionId }}" data-bs-parent="#analisisAccordion">
                                    <div class="accordion-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="ps-4">Cotización</th>
                                                        <th>Orden</th>
                                                        <th>Tipo Análisis</th>
                                                        <th>Fecha Límite</th>
                                                        <th>Responsables</th>
                                                        <th class="pe-4">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @if($analisisGrupo->isEmpty() || ($analisisGrupo->count() == 1 && !$analisisGrupo->first()->id))
                                                        {{-- Muestra pendiente por coordinar sin análisis activos --}}
                                                        <tr>
                                                            <td class="ps-4 fw-bold">
                                                                @if($muestra && $muestra->cotizacion)
                                                                    <a href="/cotizaciones/{{ $muestra->cotizacion->coti_num }}" class="text-primary">#{{ $muestra->cotizacion->coti_num ?? 'N/A' }}</a>
                                                                    @if($muestra->cotizacion->coti_cuotas)
                                                                                <span class="badge bg-[#0dcaf0] text-white px-1 py-0 rounded-pill ms-1" style="font-size: 0.6rem; background-color: #0dcaf0;">CUOTAS</span>
                                                                    @endif
                                                                @else
                                                                    N/A
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($muestra)
                                                                    <a href="{{ route('categoria.verOrden', [
                                                                        'cotizacion' => $muestra->cotio_numcoti,
                                                                        'item' => $muestra->cotio_item,
                                                                        'instance' => $muestra->instance_number
                                                                    ]) }}" class="text-primary">
                                                                        {{ $muestra->cotio_descripcion ?? 'N/A' }}
                                                                    </a>
                                                                @else
                                                                    N/A
                                                                @endif
                                                            </td>   
                                                            <td colspan="2" class="text-center text-muted">
                                                                <em>Pendiente por coordinar - Sin análisis activos</em>
                                                            </td>
                                                        </tr>
                                                    @else
                                                        @foreach($analisisGrupo as $item)
                                                        <tr>
                                                            <td class="ps-4 fw-bold">
                                                                <a href="/cotizaciones/{{ $item->cotizacion->coti_num }}" class="text-primary">#{{ $item->cotizacion->coti_num ?? 'N/A' }}</a>
                                                                @if($item->cotizacion && $item->cotizacion->coti_cuotas)
                                                                    <span class="badge bg-[#0dcaf0] text-white px-1 py-0 rounded-pill ms-1" style="font-size: 0.6rem; background-color: #0dcaf0;">CUOTAS</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <a href="{{ route('categoria.verOrden', [
                                                                    'cotizacion' => $item->cotizacion->coti_num,
                                                                    'item' => $item->cotio_item,
                                                                    'instance' => $item->instance_number
                                                                ]) }}" class="text-primary">
                                                                @if($muestra)
                                                                    {{ $muestra->cotio_descripcion ?? 'N/A' }}
                                                                @endif
                                                            </a>
                                                            </td>   
                                                            <td style="max-width: 150px;" title="{{ $item->cotio_descripcion }}">
                                                                {{ $item->cotio_descripcion ?? 'N/A' }} 
                                                            </td>
                                                            <td>
                                                                @if($item->fecha_fin_ot)
                                                                    <span class="d-block">{{ $item->fecha_fin_ot->format('d/m/Y') }}</span>
                                                                    <small class="text-muted">{{ $item->fecha_fin_ot->format('H:i') }}</small>
                                                                @else
                                                                    <span class="text-muted">Sin fecha</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($item->responsablesAnalisis && $item->responsablesAnalisis->count() > 0)
                                                                    <div class="avatar-group">
                                                                        @foreach($item->responsablesAnalisis as $responsable)
                                                                        <span class="avatar avatar-xs" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ $responsable->usu_descripcion }}">
                                                                            {{ $responsable->usu_codigo }}
                                                                        </span>
                                                                        @endforeach
                                                                    </div>
                                                                @else
                                                                    <span class="text-muted">Sin asignar</span>
                                                                @endif
                                                            </td>
                                                            <td class="pe-4">
                                                                @php
                                                                    $estadoClase = '';
                                                                    $estadoAnalisis = $item->cotio_estado_analisis ?? 'pendiente_coordinar';
                                                                    if (str_contains($estadoAnalisis, 'coordinado analisis')) {
                                                                        $estadoClase = 'warning text-dark';
                                                                    } elseif (str_contains($estadoAnalisis, 'en revision analisis')) {
                                                                        $estadoClase = 'info text-dark';
                                                                    } elseif (str_contains($estadoAnalisis, 'analizado')) {
                                                                        $estadoClase = 'success';
                                                                    } elseif (str_contains($estadoAnalisis, 'suspension')) {
                                                                        $estadoClase = 'danger';
                                                                    } else {
                                                                        $estadoClase = 'primary';
                                                                    }
                                                                @endphp
                                                                <span class="badge rounded-pill bg-{{ $estadoClase }}">
                                                                    {{ Str::title(str_replace(['_', 'analisis'], [' ', ''], $estadoAnalisis)) }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-flask fa-2x mb-2"></i>
                            <p class="mb-0">No hay análisis asignados actualmente</p>
                        </div>
                        @endforelse
                    </div>
                    <div class="dash-panel__foot">
                        <div class="text-muted small">
                            Mostrando {{ count($analisisAgrupados) }} muestras con sus análisis
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar con información complementaria --}}
        <div class="col-lg-4">
            <div class="dash-panel mb-3">
                <div class="dash-panel__head">
                    <div>
                        <h2 class="dash-panel__title">Próximos a vencer</h2>
                        <p class="dash-panel__subtitle">Próximos 3 días</p>
                    </div>
                </div>
                <div class="dash-sidebar-list">
                        @forelse($analisisProximosAgrupados as $grupo => $analisisGrupo)
                            @php
                                $primerAnalisis = $analisisGrupo->first();
                                $muestra = $primerAnalisis->muestra;
                                $fechaMasProxima = $primerAnalisis->fecha_fin_ot
                                    ?? ($muestra ? $muestra->fecha_fin_ot : null);
                            @endphp
                            <div class="dash-sidebar-list__item">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div>
                                        <strong>Muestra: </strong>
                                        @if($muestra)
                                            <a href="{{ route('categoria.verOrden', [
                                                'cotizacion' => $muestra->cotio_numcoti,
                                                'item' => $muestra->cotio_item,
                                                'instance' => $muestra->instance_number
                                            ]) }}" class="text-primary">
                                                {{ $muestra->cotio_descripcion ?? 'N/A' }}
                                            </a>
                                            @if($muestra->enable_inform ?? false)
                                                <span class="badge bg-success rounded-pill ms-1" style="font-size: 0.65em;">En informes</span>
                                            @else
                                                <span class="badge bg-secondary rounded-pill ms-1" style="font-size: 0.65em;">Sin informe</span>
                                            @endif
                                        @else
                                            'N/A'
                                        @endif
                                    </div>
                                    <small class="text-muted">{{ $fechaMasProxima ? $fechaMasProxima->format('d/m H:i') : 'Sin fecha' }}</small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                    <span class="badge bg-primary rounded-pill">{{ count($analisisGrupo) }} análisis</span>
                                    @php
                                        $estadoClase = '';
                                        if(str_contains($primerAnalisis->cotio_estado_analisis, 'coordinado analisis')) {
                                            $estadoClase = 'warning text-dark';
                                        } elseif(str_contains($primerAnalisis->cotio_estado_analisis, 'en revision analisis')) {
                                            $estadoClase = 'info text-dark';
                                        } elseif(str_contains($primerAnalisis->cotio_estado_analisis, 'analizado')) {
                                            $estadoClase = 'success';
                                        }
                                    @endphp
                                    <span class="badge bg-{{ $estadoClase }} small">
                                        {{ Str::title(str_replace(['_', 'analisis'], [' ', ''], $primerAnalisis->cotio_estado_analisis)) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                        <div class="ucrud-empty py-4">
                            <p class="ucrud-empty__text mb-0">No hay análisis próximos a vencer</p>
                        </div>
                        @endforelse
                </div>
            </div>

            <div class="mb-3">
                @include('dashboard.partials.sub-segment-chart', [
                    'chartId' => 'subAnalisis',
                    'title' => 'Estados de análisis',
                    'subtitle' => 'Clic en segmento o leyenda para filtrar el listado',
                ])
                <div class="dash-stat-extra mx-3 mb-2">
                    <span>Anulaciones / suspensiones</span>
                    <strong>{{ number_format($anulados, 0, ',', '.') }}</strong>
                </div>
            </div>

            <div class="dash-panel">
                <div class="dash-panel__head">
                    <div>
                        <h2 class="dash-panel__title">Herramientas en uso</h2>
                        <p class="dash-panel__subtitle">Equipamiento asignado</p>
                    </div>
                </div>
                <div class="p-0">
                    @forelse($herramientasEnUso as $herramienta)
                    <div class="dash-resource">
                        <div class="dash-resource__icon"><x-heroicon-o-wrench-screwdriver /></div>
                        <div class="dash-resource__body">
                            <p class="dash-resource__title">{{ $herramienta->equipamiento }}</p>
                            <p class="dash-resource__meta mb-0">Serial: {{ $herramienta->serial }}</p>
                        </div>
                        <span class="ucrud-chip ucrud-chip--slate">{{ $herramienta->cotio_instancias_count }} uso(s)</span>
                    </div>
                    @empty
                    <div class="ucrud-empty py-4">
                        <p class="ucrud-empty__text mb-0">No hay herramientas en uso actualmente</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal para Exportar --}}
<div class="modal fade" id="modalExportar" tabindex="-1" aria-labelledby="modalExportarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalExportarLabel">
                    <i class="fas fa-file-excel me-2"></i>Exportar Análisis a Excel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('dashboard.analisis.exportar') }}" method="POST" id="formExportar">
                @csrf
                <div class="modal-body">
                    <p class="text-muted mb-3">Seleccione el período para exportar los análisis:</p>
                    
                    <div class="mb-3">
                        <label for="fecha_desde" class="form-label">Fecha Desde <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fecha_hasta" class="form-label">Fecha Hasta <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" required>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>El archivo incluirá: N° Cotización, Muestra, Descripción, Estado, Fechas de Inicio y Fin OT, Responsables de Análisis y Cliente.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Exportar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@php
    $dashboardSegmentChartsConfig = [
        'subAnalisis' => [
            'labels' => ['Por coordinar', 'De análisis', 'Finalizados', 'En revisión'],
            'data' => [
                (int) $pendientesPorCoordinar,
                (int) $pendientesDeAnalisis,
                (int) $finalizados,
                (int) $pendientesDeRevision,
            ],
            'colors' => [
                ['base' => '#4e73df', 'hover' => '#6789e3'],
                ['base' => '#f0ad4e', 'hover' => '#f7c774'],
                ['base' => '#1cc88a', 'hover' => '#3dd9a4'],
                ['base' => '#36b9cc', 'hover' => '#5ccfe0'],
            ],
            'segmentLinks' => [
                $urlFiltroEstado('pendientes_coordinar'),
                $urlFiltroEstado('coordinado analisis'),
                $urlFiltroEstado('analizado'),
                $urlFiltroEstado('en revision analisis'),
            ],
        ],
    ];
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    window.dashboardSegmentCharts = @json($dashboardSegmentChartsConfig);
</script>
<script src="{{ asset('js/dashboard-home-charts.js') }}?v={{ filemtime(public_path('js/dashboard-home-charts.js')) }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                placement: tooltipTriggerEl.getAttribute('data-bs-placement') || 'bottom',
            });
        });

        const currentEstado = @json($estadoFiltroActual);
        const filterDropdown = document.getElementById('filterDropdown');
        const filterDropdownLabel = document.getElementById('filterDropdownLabel');
        const filterDot = filterDropdown?.querySelector('.rounded-circle');
        const opcionActiva = document.querySelector(`.filter-option[data-estado="${currentEstado}"]`);

        if (filterDropdown && opcionActiva && filterDropdownLabel && filterDot) {
            filterDropdownLabel.textContent = opcionActiva.dataset.label || 'Todos';
            filterDot.className = `rounded-circle flex-shrink-0 ${opcionActiva.dataset.dot || 'bg-secondary'}`;
            filterDot.style.width = '10px';
            filterDot.style.height = '10px';
        }

        // Actualizar el texto del botón dropdown de método según el método actual
        const currentMetodo = @json($metodoFiltroActual);
        const metodoFilterDropdown = document.getElementById('metodoFilterDropdown');
        if (metodoFilterDropdown && currentMetodo) {
            const metodoSeleccionado = document.querySelector(`.metodo-filter-option[href*="metodo=${currentMetodo}"]`);
            if (metodoSeleccionado) {
                metodoFilterDropdown.innerHTML = `<i class="fas fa-flask me-1"></i> Método: ${metodoSeleccionado.textContent.trim()}`;
            }
        }

        // Validación del formulario de exportación
        const formExportar = document.getElementById('formExportar');
        if (formExportar) {
            formExportar.addEventListener('submit', function(e) {
                const fechaDesde = document.getElementById('fecha_desde').value;
                const fechaHasta = document.getElementById('fecha_hasta').value;
                
                if (!fechaDesde || !fechaHasta) {
                    e.preventDefault();
                    alert('Por favor, complete ambas fechas.');
                    return false;
                }
                
                if (new Date(fechaDesde) > new Date(fechaHasta)) {
                    e.preventDefault();
                    alert('La fecha desde debe ser anterior o igual a la fecha hasta.');
                    return false;
                }
            });
        }

        // Establecer valores por defecto en el modal (mes actual)
        const modalExportar = document.getElementById('modalExportar');
        if (modalExportar) {
            modalExportar.addEventListener('show.bs.modal', function() {
                const hoy = new Date();
                const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                const ultimoDiaMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
                
                document.getElementById('fecha_desde').value = primerDiaMes.toISOString().split('T')[0];
                document.getElementById('fecha_hasta').value = ultimoDiaMes.toISOString().split('T')[0];
            });
        }
    });
</script>
@endpush
@endsection