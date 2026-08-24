<div class="d-none d-lg-block">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Cotización</th>
                <th>Cliente</th>
                <th>Estado</th>
                <th>Fecha Aprob.</th>
                <th>Matriz</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizaciones as $coti)
                <tr>
                    <td>{{ $coti->coti_num }}</td>
                    <td>{{ \App\Support\CotizacionClienteEtiqueta::paraLista($coti) }}</td>
                    <td
                    <?php 
                        $estado = trim($coti->coti_estado);
                    
                        if ($estado == 'A') {
                            echo 'class="bg-success text-white"';
                        } elseif ($estado == 'E') {
                            echo 'class="bg-warning text-white"';
                        } elseif ($estado == 'S') {
                            echo 'class="bg-danger text-white"';
                        } else {
                            echo 'class="bg-secondary text-white"';
                        }
                    ?>
                    >{{ $coti->coti_estado }}</td>
                    <td>{{ $coti->coti_fechaaprobado }}</td>
                    <td>{{ $coti->matriz->matriz_descripcion ?? 'N/A' }}</td>
                    <td>
                        <a class="btn btn-sm btn-primary" href="{{ url('/cotizaciones/'.$coti->coti_num) }}">
                            Ver detalles
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="d-block d-lg-none">
    <div class="row">
        @foreach($cotizaciones as $coti)
            <div class="col-12 mb-3">
                <a class="card shadow-sm text-decoration-none border-0" href="{{ url('/cotizaciones/'.$coti->coti_num) }}">
                    <div class="card-body">
                        <h5 class="card-title mb-3 text-primary fw-bold">
                            Cotización #{{ $coti->coti_num }}
                        </h5>

                        <div class="mb-2">
                            <strong>Cliente:</strong><br>
                            <span class="text-muted">{{ \App\Support\CotizacionClienteEtiqueta::paraLista($coti) }}</span>
                        </div>

                        <div class="mb-2">
                            <strong>Estado:</strong><br>
                            <span class="badge 
                                @if(trim($coti->coti_estado) == 'A') bg-success
                                @elseif(trim($coti->coti_estado) == 'E') bg-warning text-dark
                                @elseif(trim($coti->coti_estado) == 'S') bg-danger
                                @else bg-secondary @endif">
                                {{ $coti->coti_estado }}
                            </span>
                        </div>

                        <div class="mb-2">
                            <strong>Fecha Aprob.:</strong><br>
                            <span class="text-muted">{{ $coti->coti_fechaaprobado ?? 'Sin fecha' }}</span>
                        </div>

                        <div class="mb-2">
                            <strong>Matriz:</strong><br>
                            <span class="text-muted">{{ $coti->matriz->matriz_descripcion ?? 'N/A' }}</span>
                        </div>

                        <div class="mb-2">
                            <strong>Responsable:</strong><br>
                            <span class="text-muted">{{ $coti->responsable->usu_descripcion ?? 'Sin asignar' }}</span>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>


<div class="d-flex justify-content-center mt-4">
    {{ $cotizaciones->links() }}
</div>