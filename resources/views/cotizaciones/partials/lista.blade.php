<div class="d-none d-lg-block">
    <div class="ucrud-panel">
        <div class="ucrud-tablewrap">
            <table class="ucrud-table ucrud-table--sticky-actions">
                <thead>
                    <tr>
                        <th>Cotización</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Fecha aprob.</th>
                        <th>Matriz</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cotizaciones as $coti)
                        @php
                            $estado = trim($coti->coti_estado);
                            $estadoClase = match ($estado) {
                                'A' => 'estado-aprobado',
                                'E' => 'estado-espera',
                                'S' => 'estado-rechazado',
                                default => 'estado-otro',
                            };
                        @endphp
                        <tr>
                            <td><span class="ucrud-code">#{{ $coti->coti_num }}</span></td>
                            <td>{{ \App\Support\CotizacionClienteEtiqueta::paraLista($coti) }}</td>
                            <td class="{{ $estadoClase }}">{{ $coti->coti_estado }}</td>
                            <td>{{ $coti->coti_fechaaprobado ?: '—' }}</td>
                            <td>{{ $coti->matriz->matriz_descripcion ?? 'N/A' }}</td>
                            <td>
                                <div class="ucrud-actions">
                                    <a class="ucrud-iconbtn" href="{{ url('/cotizaciones/'.$coti->coti_num) }}" title="Ver detalles">
                                        <x-heroicon-o-document-magnifying-glass style="width: 15px; height: 15px;" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-block d-lg-none">
    <div class="row g-3">
        @foreach($cotizaciones as $coti)
            @php
                $estado = trim($coti->coti_estado);
            @endphp
            <div class="col-12">
                <a class="op-mobile-card text-decoration-none d-block" href="{{ url('/cotizaciones/'.$coti->coti_num) }}">
                    <div class="card-body">
                        <h5 class="card-title mb-2 text-primary fw-bold">Cotización #{{ $coti->coti_num }}</h5>
                        <p class="mb-2 small text-muted">{{ \App\Support\CotizacionClienteEtiqueta::paraLista($coti) }}</p>
                        <span class="ucrud-chip ucrud-chip--{{ $estado === 'A' ? 'green' : ($estado === 'E' ? 'amber' : ($estado === 'S' ? 'rose' : 'slate')) }}">
                            {{ $coti->coti_estado }}
                        </span>
                        <div class="small text-muted mt-2">
                            {{ $coti->coti_fechaaprobado ?? 'Sin fecha' }} · {{ $coti->matriz->matriz_descripcion ?? 'N/A' }}
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>

<div class="ucrud-panel ucrud-pagination mt-3">
    {{ $cotizaciones->links() }}
</div>
