@php
    /** @var \App\Models\CondicionPago|null $condicion */
    $isEdit = isset($condicion) && $condicion;
@endphp

<div class="row g-3">
    <div class="col-12 col-md-4">
        <label class="form-label">Código</label>
        <input type="text"
               name="pag_codigo"
               class="form-control @error('pag_codigo') is-invalid @enderror"
               value="{{ old('pag_codigo', $isEdit ? $condicion->pag_codigo : '') }}"
               required>
        @error('pag_codigo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 col-md-8">
        <label class="form-label">Descripción</label>
        <input type="text"
               name="pag_descripcion"
               class="form-control @error('pag_descripcion') is-invalid @enderror"
               value="{{ old('pag_descripcion', $isEdit ? $condicion->pag_descripcion : '') }}">
        @error('pag_descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Días</label>
        <input type="number"
               name="pag_dias"
               min="0"
               class="form-control @error('pag_dias') is-invalid @enderror"
               value="{{ old('pag_dias', $isEdit ? $condicion->pag_dias : '') }}">
        @error('pag_dias') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label">Cuotas</label>
        <input type="number"
               name="pag_cuotas"
               min="0"
               class="form-control @error('pag_cuotas') is-invalid @enderror"
               value="{{ old('pag_cuotas', $isEdit ? $condicion->pag_cuotas : '') }}">
        @error('pag_cuotas') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label">Cliente/Proveedor</label>
        <input type="text"
               name="pag_clienteproveedor"
               class="form-control @error('pag_clienteproveedor') is-invalid @enderror"
               value="{{ old('pag_clienteproveedor', $isEdit ? $condicion->pag_clienteproveedor : '') }}">
        @error('pag_clienteproveedor') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Descuento 1 (%)</label>
        <input type="number"
               step="0.01"
               name="pag_descuento1"
               min="0"
               class="form-control @error('pag_descuento1') is-invalid @enderror"
               value="{{ old('pag_descuento1', $isEdit ? $condicion->pag_descuento1 : '') }}">
        @error('pag_descuento1') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label">Descuento 2 (%)</label>
        <input type="number"
               step="0.01"
               name="pag_descuento2"
               min="0"
               class="form-control @error('pag_descuento2') is-invalid @enderror"
               value="{{ old('pag_descuento2', $isEdit ? $condicion->pag_descuento2 : '') }}">
        @error('pag_descuento2') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label">Interés (%)</label>
        <input type="number"
               step="0.01"
               name="pag_interes"
               class="form-control @error('pag_interes') is-invalid @enderror"
               value="{{ old('pag_interes', $isEdit ? $condicion->pag_interes : '') }}">
        @error('pag_interes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 col-md-4">
        <div class="form-check form-switch mt-4">
            <input class="form-check-input"
                   type="checkbox"
                   role="switch"
                   id="pag_vencimiento"
                   name="pag_vencimiento"
                   value="1"
                   {{ old('pag_vencimiento', $isEdit ? (int) $condicion->pag_vencimiento : 0) ? 'checked' : '' }}>
            <label class="form-check-label" for="pag_vencimiento">Vencimiento</label>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="form-check form-switch mt-4">
            <input class="form-check-input"
                   type="checkbox"
                   role="switch"
                   id="pag_anticipo"
                   name="pag_anticipo"
                   value="1"
                   {{ old('pag_anticipo', $isEdit ? (int) $condicion->pag_anticipo : 0) ? 'checked' : '' }}>
            <label class="form-check-label" for="pag_anticipo">Anticipo</label>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="form-check form-switch mt-4">
            <input class="form-check-input"
                   type="checkbox"
                   role="switch"
                   id="pag_estado"
                   name="pag_estado"
                   value="1"
                   {{ old('pag_estado', $isEdit ? (int) $condicion->pag_estado : 1) ? 'checked' : '' }}>
            <label class="form-check-label" for="pag_estado">Activa</label>
        </div>
    </div>
</div>

