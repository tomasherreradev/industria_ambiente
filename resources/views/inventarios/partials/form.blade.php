@php
    $inventario = $inventario ?? null;
    $isEdit = $inventario !== null;
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="equipamiento" class="form-label">Equipamiento</label>
        <input type="text" class="form-control" id="equipamiento" name="equipamiento" required
               placeholder="Ej: Cámara de muestreo"
               value="{{ old('equipamiento', $inventario->equipamiento ?? '') }}">
    </div>
    <div class="col-md-6">
        <label for="marca_modelo" class="form-label">Marca y modelo</label>
        <input type="text" class="form-control" id="marca_modelo" name="marca_modelo" required
               placeholder="Ej: ABC123"
               value="{{ old('marca_modelo', $inventario->marca_modelo ?? '') }}">
    </div>
    <div class="col-md-6">
        <label for="n_serie_lote" class="form-label">N° de serie o lote</label>
        <input type="text" class="form-control" id="n_serie_lote" name="n_serie_lote" required
               placeholder="Ej: ABC123"
               value="{{ old('n_serie_lote', $inventario->n_serie_lote ?? '') }}">
    </div>
    <div class="col-md-6">
        <label for="codigo_ficha" class="form-label">Código de ficha</label>
        <input type="text" class="form-control" id="codigo_ficha" name="codigo_ficha" required
               placeholder="Ej: ABC123"
               value="{{ old('codigo_ficha', $inventario->codigo_ficha ?? '') }}">
    </div>
    <div class="col-md-4">
        <label for="activo" class="form-label">Activo</label>
        <select class="form-select" id="activo" name="activo">
            @php $activoVal = old('activo', $isEdit ? ($inventario->activo ? 'true' : 'false') : 'true'); @endphp
            <option value="true" @selected($activoVal === 'true' || $activoVal === true || $activoVal === '1' || $activoVal === 1)>Activo</option>
            <option value="false" @selected($activoVal === 'false' || $activoVal === false || $activoVal === '0' || $activoVal === 0)>Inactivo</option>
        </select>
    </div>
    <div class="col-md-4">
        <label for="fecha_calibracion" class="form-label">Fecha de calibración</label>
        <input type="date" class="form-control" id="fecha_calibracion" name="fecha_calibracion" required
               value="{{ old('fecha_calibracion', $inventario->fecha_calibracion ?? '') }}">
    </div>
    <div class="col-md-4">
        <label for="{{ $isEdit ? 'certificado' : 'certificado_calibracion' }}" class="form-label">Certificado (PDF)</label>
        <input type="file" class="form-control" id="{{ $isEdit ? 'certificado' : 'certificado_calibracion' }}"
               name="{{ $isEdit ? 'certificado' : 'certificado_calibracion' }}" accept=".pdf">
        <small class="text-muted">Tamaño máximo: 5MB</small>
        @if($isEdit && $inventario->certificado)
            <div class="mt-2">
                <a href="{{ asset('storage/' . $inventario->certificado) }}" target="_blank" rel="noopener noreferrer" class="ucrud-btn ucrud-btn--ghost" style="padding:.4rem .75rem;font-size:.8rem;">
                    Ver certificado actual
                </a>
            </div>
        @endif
    </div>
    <div class="col-12">
        <label for="observaciones" class="form-label">Observaciones</label>
        <textarea class="form-control" id="observaciones" name="observaciones" rows="3"
                  placeholder="Observaciones">{{ old('observaciones', $inventario->observaciones ?? '') }}</textarea>
    </div>
</div>
