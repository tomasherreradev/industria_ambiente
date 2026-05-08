@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Volumetría para costeos (En Desarrollo)</h1>
            <p class="text-muted mb-0">
                Conteo de <strong>análisis con OT activa</strong> (líneas <code>cotio_subitem &gt; 0</code>) por año calendario,
                según criterios configurables en <code>config/admin_volumen_costeos.php</code>.
            </p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Volver al panel</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="{{ route('admin.volumen-costeos') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Año</label>
                    <select name="anio" class="form-select" onchange="this.form.submit()">
                        @foreach($aniosDisponibles as $y)
                            <option value="{{ $y }}" {{ (int) $anio === (int) $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estado del análisis</label>
                    <select name="solo_analizados" class="form-select" onchange="this.form.submit()">
                        <option value="1" {{ $soloAnalizados ? 'selected' : '' }}>Solo <code>analizado</code></option>
                        <option value="0" {{ ! $soloAnalizados ? 'selected' : '' }}>Todos los estados (con OT activa)</option>
                    </select>
                </div>
                <div class="col-md-5 text-muted small">
                    Fecha de referencia del año: <code>COALESCE(fecha_carga_ot, fecha_fin_ot, updated_at)</code> de la instancia.
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive card shadow-sm">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Categoría</th>
                    <th class="text-end" style="width: 12rem;">Cantidad (año {{ $anio }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resultados as $clave => $fila)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $fila['label'] }}</div>
                            @if(!empty($fila['help']))
                                <div class="small text-muted">{{ $fila['help'] }}</div>
                            @endif
                            <div class="small text-muted mt-1">Clave interna: <code>{{ $clave }}</code></div>
                        </td>
                        <td class="text-end align-middle fs-5 fw-bold">{{ number_format($fila['cantidad'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="alert alert-info mt-4 mb-0" role="alert">
        <strong>Cómo afinar los resultados:</strong> editá los patrones <code>LIKE</code> en
        <code>config/admin_volumen_costeos.php</code> (por ejemplo para alinear “efluente” con el texto real de muestras
        o para mapear cromatografía a códigos de matriz concretos). Si más adelante necesitán reglas por
        <code>cotio_codigoprod</code> o por sector formal por ítem, se puede extender el controlador sin cambiar la pantalla.
    </div>
</div>
@endsection
