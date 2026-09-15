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
        <input type="text" class="form-control" id="marca_modelo" name="marca_modelo"
               placeholder="Ej: ABC123"
               value="{{ old('marca_modelo', $inventario->marca_modelo ?? '') }}">
    </div>
    <div class="col-md-6">
        <label for="n_serie_lote" class="form-label">N° de serie o lote</label>
        <input type="text" class="form-control" id="n_serie_lote" name="n_serie_lote"
               placeholder="Ej: ABC123"
               value="{{ old('n_serie_lote', $inventario->n_serie_lote ?? '') }}">
    </div>
    <div class="col-md-6">
        <label for="fecha_calibracion" class="form-label">Fecha de calibración (vencimiento)</label>
        <input type="date" class="form-control" id="fecha_calibracion" name="fecha_calibracion"
               value="{{ old('fecha_calibracion', $inventario->fecha_calibracion ?? '') }}">
    </div>
    <div class="col-md-4">
        <label for="activo" class="form-label">Activo</label>
        @php $activoVal = old('activo', $isEdit ? (int) $inventario->activo : 1); @endphp
        <select class="form-select" id="activo" name="activo">
            <option value="1" @selected((string) $activoVal === '1')>Activo</option>
            <option value="0" @selected((string) $activoVal === '0')>Inactivo</option>
        </select>
    </div>
    <div class="col-md-8">
        <label for="certificado" class="form-label">Certificado de calibración (PDF)</label>
        <input type="file" class="form-control" id="certificado" name="certificado" accept=".pdf">
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
