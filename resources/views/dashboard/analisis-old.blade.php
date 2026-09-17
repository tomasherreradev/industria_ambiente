@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Panel de Análisis</h1>
        <div class="text-muted">{{ fechaActualLargaEs() }}</div>
    </div>

    {{-- Resumen General --}}
    <div class="row mb-4 g-4">
        <div class="col-xl-3 col-md-6">
            <a href="{{ request()->fullUrlWithQuery(['estado' => 'all']) }}" class="text-decoration-none">
                <div class="card bg-primary bg-gradient text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Total Análisis</h5>
                                <p class="card-text display-6 fw-bold">{{ $totalAnalisis }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-magnifying-glass style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Asignados a mi equipo</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ request()->fullUrlWithQuery(['estado' => 'coordinado analisis']) }}" class="text-decoration-none">
                <div class="card bg-warning bg-gradient text-dark h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Pendientes</h5>
                                <p class="card-text display-6 fw-bold">{{ $pendientes }}</p>
                            </div>
                            <div class="bg-dark bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-clock style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Por procesar</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ request()->fullUrlWithQuery(['estado' => 'en revision analisis']) }}" class="text-decoration-none">
                <div class="card bg-info bg-gradient text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">En Proceso</h5>
                                <p class="card-text display-6 fw-bold">{{ $enProceso }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-arrow-path style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">En revisión</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ request()->fullUrlWithQuery(['estado' => 'analizado']) }}" class="text-decoration-none">
                <div class="card bg-success bg-gradient text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Finalizados</h5>
                                <p class="card-text display-6 fw-bold">{{ $finalizados }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-check-circle style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Completados</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- Contenido principal --}}
    <div class="row g-4">
        {{-- Análisis asignados --}}
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Análisis Asignados</h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterDropdown">
                                <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'all']) }}">Todos</a></li>
                                <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'coordinado analisis']) }}">Pendientes</a></li>
                                <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'en revision analisis']) }}">En proceso</a></li>
                                <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'analizado']) }}">Finalizados</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'proximos']) }}">Próximos 3 días</a></li>
                            </ul>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">Análisis asignados a mi o a mi equipo</p>
                </div>
                <div class="card-body p-0">
                    <div class="accordion" id="analisisAccordion">
                        @forelse($analisisAgrupados as $grupo => $analisisGrupo)
                            @php
                                $primerAnalisis = $analisisGrupo->first();
                                $muestra = $primerAnalisis->muestra;
                                $accordionId = 'muestra-' . str_replace(['-', '.'], '', $grupo);
                            @endphp
                            <div class="accordion-item border-0">
                                <h2 class="accordion-header" id="heading{{ $accordionId }}">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $accordionId }}" aria-expanded="false" aria-controls="collapse{{ $accordionId }}">
                                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                            <div>
                                                <strong>Muestra: </strong>
                                                @if($muestra)
                                                    <a href="{{ route('categoria.verOrden', [
                                                        'cotizacion' => $muestra->cotio_numcoti,
                                                        'item' => $muestra->cotio_item,
                                                        'instance' => $muestra->instance_number
                                                    ]) }}" class="text-primary">
                                                        {{ $muestra->cotio_descripcion ?? 'N/A' }} {{ $muestra->instance_number ?? '' }}
                                                        <span class="text-muted small">
                                                            <strong>Cotización:</strong> <a href="{{ route('cotizaciones.ver-detalle', $muestra->cotio_numcoti) }}" class="text-muted">{{ $muestra->cotio_numcoti ?? 'N/A' }}</a>
                                                        </span>
                                                    </a>
                                                @else
                                                    'N/A'
                                                @endif
                                            </div>
                                            <span class="badge bg-primary rounded-pill">{{ count($analisisGrupo) }} análisis</span>
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
                                                        <th>Muestra</th>
                                                        <th>Tipo Análisis</th>
                                                        <th>Fecha Límite</th>
                                                        <th>Responsables</th>
                                                        <th class="pe-4">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($analisisGrupo as $item)
                                                    <tr>
                                                        <td class="ps-4 fw-bold">
                                                            <a href="/show/{{ $item->cotizacion->coti_num }}" class="text-primary">#{{ $item->cotizacion->coti_num ?? 'N/A' }}</a>
                                                        </td>
                                                        <td>
                                                            <span class="text-muted">Muestra {{ $item->instance_number }}</span>
                                                        </td>
                                                        <td style="max-width: 150px;" title="{{ $item->cotio_descripcion }}">
                                                            {{ $item->cotio_descripcion ?? 'N/A' }}
                                                        </td>
                                                        <td>
                                                            <span class="d-block">{{ $item->fecha_fin->format('d/m/Y') }}</span>
                                                            <small class="text-muted">{{ $item->fecha_fin->format('H:i') }}</small>
                                                        </td>
                                                        <td>
                                                            @if($item->responsablesAnalisis->count() > 0)
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
                                                                if(str_contains($item->cotio_estado_analisis, 'coordinado analisis')) {
                                                                    $estadoClase = 'warning text-dark';
                                                                } elseif(str_contains($item->cotio_estado_analisis, 'en revision analisis')) {
                                                                    $estadoClase = 'info text-dark';
                                                                } elseif(str_contains($item->cotio_estado_analisis, 'analizado')) {
                                                                    $estadoClase = 'success';
                                                                }
                                                            @endphp
                                                            <span class="badge rounded-pill bg-{{ $estadoClase }}">
                                                                {{ Str::title(str_replace(['_', 'analisis'], [' ', ''], $item->cotio_estado_analisis)) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    @endforeach
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
                    <div class="card-footer bg-white border-top-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                Mostrando {{ count($analisisAgrupados) }} muestras con sus análisis
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar con información complementaria --}}
        <div class="col-lg-4">
            {{-- Análisis próximos --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Próximos a Vencer</h5>
                    <p class="text-muted small mb-0">Próximos 3 días</p>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @forelse($analisisProximosAgrupados as $grupo => $analisisGrupo)
                            @php
                                $primerAnalisis = $analisisGrupo->first();
                                $muestra = $primerAnalisis->muestra;
                                $fechaMasProxima = $analisisGrupo->min('fecha_fin');
                            @endphp
                            <div class="list-group-item border-0 px-0 py-2">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div>
                                        <strong>Muestra: </strong>
                                        @if($muestra)
                                            <a href="{{ route('categoria.verOrden', [
                                                'cotizacion' => $muestra->cotio_numcoti,
                                                'item' => $muestra->cotio_item,
                                                'instance' => $muestra->instance_number
                                            ]) }}" class="text-primary">
                                                {{ $muestra->cotio_descripcion ?? 'N/A' }} {{ $muestra->instance_number ?? '' }}
                                            </a>
                                        @else
                                            'N/A'
                                        @endif
                                    </div>
                                    <small class="text-muted">{{ $fechaMasProxima->format('d/m H:i') }}</small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
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
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                            <p class="mb-0 small">No hay análisis próximos a vencer</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Distribución por estado --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Estados de Análisis</h5>
                    <p class="text-muted small mb-0">Distribución por estado</p>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 200px;">
                        <canvas id="estadoAnalisisChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>Coordinado Análisis</span>
                                <span class="badge bg-warning text-dark rounded-pill">{{ $pendientes }}</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>En Revisión</span>
                                <span class="badge bg-info text-dark rounded-pill">{{ $enProceso }}</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>Analizado</span>
                                <span class="badge bg-success rounded-pill">{{ $finalizados }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Herramientas en uso --}}
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Herramientas en Uso</h5>
                    <p class="text-muted small mb-0">Equipamiento asignado</p>
                </div>
                <div class="card-body">
                    @forelse($herramientasEnUso as $herramienta)
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 bg-light rounded p-2 me-3">
                            <x-heroicon-o-beaker style="width: 20px; height: 20px;"/>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">{{ $herramienta->equipamiento }}</h6>
                            <small class="text-muted">Serial: {{ $herramienta->serial }}</small>
                        </div>
                        <span class="badge bg-light text-dark">
                            {{ $herramienta->cotio_instancias_count }} uso(s)
                        </span>
                    </div>
                    @empty
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-box-open fa-2x mb-2"></i>
                        <p class="mb-0 small">No hay herramientas en uso actualmente</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                placement: tooltipTriggerEl.getAttribute('data-bs-placement') || 'bottom',
            });
        });

        // Gráfico de estados de análisis
        const estadoAnalisisCtx = document.getElementById('estadoAnalisisChart').getContext('2d');
        new Chart(estadoAnalisisCtx, {
            type: 'doughnut',
            data: {
                labels: ['Coordinado Análisis', 'En Revisión Análisis', 'Analizado'],
                datasets: [{
                    data: [@json($pendientes), @json($enProceso), @json($finalizados)],
                    backgroundColor: [
                        '#f6c23e', // Amarillo para pendientes
                        '#36b9cc', // Azul claro para en proceso
                        '#1cc88a'  // Verde para finalizados
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw} (${Math.round(context.parsed)}%)`;
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });

        // Actualizar el texto del botón dropdown según el estado actual
        const currentEstado = '{{ $estadoFiltro }}';
        const filterDropdown = document.getElementById('filterDropdown');
        const filterOptions = document.querySelectorAll('.filter-option');
        
        filterOptions.forEach(option => {
            if (option.getAttribute('href').includes(`estado=${currentEstado}`)) {
                filterDropdown.innerHTML = `<i class="fas fa-filter me-1"></i> ${option.textContent}`;
            }
        });
    });
</script>
@endsection