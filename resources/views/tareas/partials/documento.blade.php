<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="d-none d-md-table-header-group">
                    <tr>
                        <th>Cotización</th>
                        <th>Cliente</th>
                        <th>Muestra</th>
                        <th>Categoría</th>
                        <th class="text-nowrap">Fecha Fin</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Definir el orden de los estados
                        $estadoOrden = [
                            'pendiente' => 2, // Pendientes por coordinar - segundo lugar (después de prioritarias)
                            'en revision muestreo' => 3, // En revisión (turquesas) - tercer lugar
                            'coordinado muestreo' => 4, // Coordinadas (amarillas) - cuarto lugar
                            'muestreado' => 5, // Finalizadas al final
                            'suspension' => 1, // Suspendidas tienen prioridad especial
                            // Agrega otros estados posibles aquí
                        ];

                        // Ordenar $tareasAgrupadas según el criterio
                        $tareasAgrupadas = $tareasAgrupadas->sortBy(function ($grupo) use ($estadoOrden) {
                            $instancia = $grupo['instancia_muestra'];
                            $estado = strtolower($instancia->cotio_estado ?? 'pendiente');
                            $esPriori = $instancia->es_priori ?? false;

                            // Prioritarias NO finalizadas primero
                            if ($esPriori && $estado !== 'muestreado') {
                                return 1;
                            }

                            // Luego, ordenar por estado según el orden definido
                            return $estadoOrden[$estado] ?? 6; // Estados desconocidos al final
                        });
                    @endphp

                    @foreach($tareasAgrupadas as $key => $grupo)
                        @php
                            // Extraer componentes de la clave
                            [$numCoti, $instanceNumber, $itemId] = explode('_', $key);
                            $cotizacion = $cotizaciones->get($numCoti);
                            $muestra = $grupo['muestra'];
                            $instanciaMuestra = $grupo['instancia_muestra'];
                            $analisis = $grupo['analisis'];
                            
                            // Determinar estado
                            $estado = strtolower($instanciaMuestra->cotio_estado ?? ($analisis->first()->cotio_estado ?? 'pendiente'));
                            $badgeClass = match ($estado) {
                                'coordinado muestreo' => 'table-warning',
                                'en revision muestreo' => 'table-info',
                                'muestreado' => 'table-success',
                                'suspension' => 'table-danger',
                                default => 'table-secondary'
                            };
                        @endphp
                        
                        <tr class="align-middle {{ $badgeClass }}">
                            <!-- Versión móvil -->
                            <td class="d-md-none py-2">
                                <a class="text-decoration-none" href="{{ Auth::user()->rol == 'laboratorio' ? route('ordenes.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A']) : route('tareas.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A']) }}">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong class="d-block">#{{ $numCoti }}</strong>
                                            <small class="text-muted">{{ Str::limit($cotizacion->coti_empresa ?? 'Sin cliente', 20) }}</small>
                                        </div>
                                        <span class="badge {{ $badgeClass }} align-self-start text-dark">
                                            {{ ucfirst($estado) }}
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="small text-muted mb-1">
                                            <strong>Muestra: </strong> {{ $instanceNumber }}
                                        </div>
                                        @if($muestra)
                                            <div class="small text-muted mb-1">
                                                <strong>Muestra:</strong> {{ Str::limit($muestra->cotio_descripcion, 30) }}
                                            </div>
                                        @endif
                                        <div class="small text-primary">
                                            @if($instanciaMuestra->fecha_fin_muestreo ?? false)
                                                Vence: {{ \Carbon\Carbon::parse($instanciaMuestra->fecha_fin_muestreo)->format('d/m/Y') }}
                                            @else
                                                Sin fecha de vencimiento
                                            @endif
                                        </div>
                                    </a>
                                </div>
                            </td>
                            
                            <!-- Versión desktop -->
                            <td class="d-none d-md-table-cell">
                                <div class="d-flex flex-column">
                                    <strong>#{{ $numCoti }}</strong>
                                    <strong class="small">{{ $cotizacion->coti_empresa ?? 'Sin cliente' }}</strong>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                {{ $cotizacion->coti_establecimiento ?? '' }}
                                <div class="small text-muted">{{ $cotizacion->coti_localidad ?? '' }}</div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                {{ $instanceNumber }}
                                @if($muestra)
                                    <div class="small text-muted">
                                        {{ Str::limit($muestra->cotio_descripcion, 25) }}
                                    </div>
                                @endif
                            </td>
                            <td class="d-none d-md-table-cell">
                                {{ $instanciaMuestra->cotio_descripcion ?? 'N/A' }}
                                <div class="small text-muted">Muestra #{{ $instanciaMuestra->instance_number }}</div>
                            </td>
                            <td class="d-none d-md-table-cell text-nowrap">
                                @if($instanciaMuestra->fecha_fin_muestreo ?? false)
                                    {{ \Carbon\Carbon::parse($instanciaMuestra->fecha_fin_muestreo)->format('d/m/Y') }}
                                @else
                                    <span class="text-muted">Sin fecha de vencimiento</span>
                                @endif
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="badge bg-dark">
                                    {{ ucfirst($estado) }}
                                </span>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <a href="{{ Auth::user()->rol == 'laboratorio' ? route('ordenes.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A']) : route('tareas.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A']) }}">
                                    <x-heroicon-o-eye class="me-1" style="width: 16px; height: 16px;" />
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    @if($tareasPaginadas instanceof \Illuminate\Pagination\LengthAwarePaginator)
        {{ $tareasPaginadas->onEachSide(1)->links('pagination::bootstrap-4') }}
    @endif
</div>

<style>
    .table-hover tbody tr:hover {
        background-color: rgba(0,0,0,0.05) !important;
    }
    @media (max-width: 767.98px) {
        .table-responsive {
            border: 0;
        }
        .table tbody tr {
            display: block;
            margin-bottom: 8px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .table tbody td {
            display: block;
            border: none;
            padding: 8px 12px;
        }
        .table tbody td:before {
            content: attr(data-label);
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
    }
    .pagination .page-link {
        padding: 0.3rem 0.6rem;
        font-size: 0.875rem;
    }
    .table-warning {
        background-color: rgba(255, 243, 205, 0.8) !important;
    }
    .table-info {
        background-color: rgba(209, 236, 241, 0.8) !important;
    }
    .table-success {
        background-color: rgba(212, 237, 218, 0.8) !important;
    }
    .table-secondary {
        background-color: rgba(233, 236, 239, 0.8) !important;
    }
    .badge {
        font-size: 0.85em;
    }
</style>