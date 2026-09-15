@extends('layouts.app')

@section('title', 'Cambios Masivos de Precios')

@section('content')
@include('partials.ucrud-styles')

<div class="container-fluid py-4 ucrud ucrud-layout--fluid">
    @include('partials.ucrud-form-header', [
        'title' => 'Cambios masivos de precios',
        'subtitle' => 'Aplicá ajustes por porcentaje o valor fijo.',
        'actions' => '<a href="' . route('items.historial-precios') . '" class="ucrud-btn ucrud-btn--info">Ver historial</a><a href="' . route('items.index') . '" class="ucrud-btn ucrud-btn--ghost">Volver</a>',
    ])

    @if(session('success'))
        <div id="flash-success" data-message="{{ session('success') }}" style="display:none"></div>
    @endif

    @if($errors->any())
        <div class="ucrud-alert ucrud-alert--danger" role="alert">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="ucrud-panel">
        <div class="ucrud-form">
            <form method="POST" action="{{ route('items.aplicar-cambios-masivos') }}" id="formCambiosMasivos">
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="tipo_cambio" class="form-label">Tipo de cambio <span class="text-danger">*</span></label>
                        <select name="tipo_cambio" id="tipo_cambio" class="form-select @error('tipo_cambio') is-invalid @enderror" required>
                            <option value="">Seleccione...</option>
                            <option value="porcentaje" {{ old('tipo_cambio') == 'porcentaje' ? 'selected' : '' }}>Porcentaje (%)</option>
                            <option value="valor_fijo" {{ old('tipo_cambio') == 'valor_fijo' ? 'selected' : '' }}>Valor fijo</option>
                        </select>
                        @error('tipo_cambio')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted">
                            <span id="help-porcentaje" style="display:none;">Ejemplo: 10 = aumentar 10%, -5 = disminuir 5%</span>
                            <span id="help-valor_fijo" style="display:none;">Ejemplo: 50 = sumar $50, -30 = restar $30</span>
                        </small>
                    </div>

                    <div class="col-md-6">
                        <label for="valor" class="form-label">Valor <span class="text-danger">*</span></label>
                        <input type="number" name="valor" id="valor" step="0.01"
                               class="form-control @error('valor') is-invalid @enderror"
                               value="{{ old('valor') }}" required min="-999999" max="999999">
                        @error('valor')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted"><span id="unidad-valor"></span></small>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="filtro_tipo" class="form-label">Filtrar por tipo</label>
                        <select name="filtro_tipo" id="filtro_tipo" class="form-select">
                            <option value="todos" {{ old('filtro_tipo', 'todos') == 'todos' ? 'selected' : '' }}>Todos</option>
                            <option value="muestras" {{ old('filtro_tipo') == 'muestras' ? 'selected' : '' }}>Solo agrupadores (muestras)</option>
                            <option value="componentes" {{ old('filtro_tipo') == 'componentes' ? 'selected' : '' }}>Solo componentes</option>
                        </select>
                        <small class="form-text text-muted">Solo se actualizarán ítems que tengan precio definido</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="descripcion" class="form-label">Descripción (opcional)</label>
                    <textarea name="descripcion" id="descripcion"
                              class="form-control @error('descripcion') is-invalid @enderror"
                              rows="3" maxlength="500"
                              placeholder="Ej: Aumento por inflación, actualización 2025…">{{ old('descripcion') }}</textarea>
                    @error('descripcion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="ucrud-alert ucrud-alert--warning" role="alert">
                    <span><strong>Advertencia:</strong> esta operación modificará precios de múltiples determinaciones. Los cambios quedan en el historial y pueden revertirse.</span>
                </div>

                <div class="ucrud-form__actions">
                    <a href="{{ route('items.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                    <button type="submit" class="ucrud-btn ucrud-btn--primary" id="btnAplicar">Aplicar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const flash = document.getElementById('flash-success');
    if (flash && flash.dataset.message) {
        Swal.fire({ icon: 'success', title: 'Éxito', text: flash.dataset.message, timer: 3000, showConfirmButton: false });
    }

    const tipoCambio = document.getElementById('tipo_cambio');
    const valor = document.getElementById('valor');
    const unidadValor = document.getElementById('unidad-valor');
    const helpPorcentaje = document.getElementById('help-porcentaje');
    const helpValorFijo = document.getElementById('help-valor_fijo');

    function actualizarAyuda() {
        const tipo = tipoCambio.value;
        helpPorcentaje.style.display = tipo === 'porcentaje' ? 'inline' : 'none';
        helpValorFijo.style.display = tipo === 'valor_fijo' ? 'inline' : 'none';
        if (tipo === 'porcentaje') {
            unidadValor.textContent = 'Ingrese el porcentaje (puede ser negativo para disminuir)';
        } else if (tipo === 'valor_fijo') {
            unidadValor.textContent = 'Ingrese el valor a sumar/restar (puede ser negativo)';
        } else {
            unidadValor.textContent = '';
        }
    }

    tipoCambio.addEventListener('change', actualizarAyuda);
    actualizarAyuda();

    document.getElementById('formCambiosMasivos').addEventListener('submit', function (e) {
        e.preventDefault();
        const tipo = tipoCambio.value;
        const valorInput = parseFloat(valor.value);
        if (!tipo || isNaN(valorInput)) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Completá todos los campos requeridos.' });
            return;
        }
        let mensaje = tipo === 'porcentaje'
            ? `¿Aplicar un cambio del ${valorInput > 0 ? '+' : ''}${valorInput}% a los precios?`
            : `¿${valorInput >= 0 ? 'Sumar' : 'Restar'} $${Math.abs(valorInput)} a los precios?`;

        Swal.fire({
            title: '¿Confirmar cambios masivos?',
            text: mensaje,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, aplicar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then(function (result) {
            if (result.isConfirmed) {
                e.target.submit();
            }
        });
    });
});
</script>
@endpush
