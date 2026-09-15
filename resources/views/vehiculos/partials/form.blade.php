@php
    $vehiculo = $vehiculo ?? null;
    $isEdit = $vehiculo !== null;
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label for="marca" class="form-label">Marca</label>
        <input type="text" class="form-control" id="marca" name="marca" {{ $isEdit ? '' : 'required' }}
               placeholder="Ej: Toyota"
               value="{{ old('marca', $vehiculo->marca ?? '') }}">
    </div>
    <div class="col-md-4">
        <label for="modelo" class="form-label">Modelo</label>
        <input type="text" class="form-control" id="modelo" name="modelo" {{ $isEdit ? '' : 'required' }}
               placeholder="Ej: Hilux"
               value="{{ old('modelo', $vehiculo->modelo ?? '') }}">
    </div>
    <div class="col-md-4">
        <label for="anio" class="form-label">Año</label>
        <input type="number" class="form-control" id="anio" name="anio"
               placeholder="Ej: 2020"
               value="{{ old('anio', $vehiculo->anio ?? '') }}">
    </div>
    <div class="col-md-4">
        <label for="patente" class="form-label">Patente <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="patente" name="patente" required
               placeholder="Ej: AB1234"
               value="{{ old('patente', $vehiculo->patente ?? '') }}">
    </div>
    <div class="col-md-4">
        <label for="tipo" class="form-label">Tipo</label>
        <input type="text" class="form-control" id="tipo" name="tipo"
               placeholder="Ej: Automóvil"
               value="{{ old('tipo', $vehiculo->tipo ?? '') }}">
    </div>
    @if($isEdit)
        <div class="col-md-4">
            <label for="estado" class="form-label">Estado</label>
            <select class="form-select" id="estado" name="estado">
                @php $estado = old('estado', $vehiculo->estado); @endphp
                <option value="libre" @selected($estado === 'libre')>Libre</option>
                <option value="ocupado" @selected($estado === 'ocupado')>Ocupado</option>
                <option value="mantenimiento" @selected($estado === 'mantenimiento')>Mantenimiento</option>
                <option value="desconocido" @selected($estado === 'desconocido')>Desconocido</option>
            </select>
        </div>
    @endif
    <div class="col-md-4">
        <label for="ultimo_mantenimiento" class="form-label">Último mantenimiento</label>
        <input type="date" class="form-control" id="ultimo_mantenimiento" name="ultimo_mantenimiento"
               value="{{ old('ultimo_mantenimiento', $vehiculo->ultimo_mantenimiento ?? '') }}">
    </div>
    <div class="col-md-4">
        <label for="estado_gral" class="form-label">Estado general</label>
        <input type="text" class="form-control" id="estado_gral" name="estado_gral"
               placeholder="Ej: óptimas condiciones"
               value="{{ old('estado_gral', $vehiculo->estado_gral ?? '') }}">
    </div>
    <div class="col-12">
        <label for="descripcion" class="form-label">Descripción</label>
        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"
                  placeholder="Ej: Auto para tareas de muestreo">{{ old('descripcion', $vehiculo->descripcion ?? '') }}</textarea>
    </div>
</div>
