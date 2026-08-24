@php
    $metodoMuestreoVal = trim((string) old('metodo_muestreo', $item?->metodo_muestreo ?? ''));
    $metodoAnalisisVal = trim((string) old('metodo', $item?->metodo ?? ''));
@endphp

<div class="mb-3">
    <label for="metodo_muestreo" class="form-label">Método de muestreo</label>
    <select name="metodo_muestreo" id="metodo_muestreo" class="form-select select2-metodo" data-placeholder="Buscar método de muestreo...">
        <option value="">Sin método de muestreo</option>
        @foreach($metodos as $met)
            @php $codigo = trim((string) $met->metodo_codigo); @endphp
            <option value="{{ $codigo }}" {{ $metodoMuestreoVal === $codigo ? 'selected' : '' }}>
                {{ $codigo }} - {{ trim($met->metodo_descripcion) }}
            </option>
        @endforeach
    </select>
    @error('metodo_muestreo')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="metodo" class="form-label">Método de análisis</label>
    <select name="metodo" id="metodo" class="form-select select2-metodo" data-placeholder="Buscar método de análisis...">
        <option value="">Sin método de análisis</option>
        @foreach($metodos as $met)
            @php $codigo = trim((string) $met->metodo_codigo); @endphp
            <option value="{{ $codigo }}" {{ $metodoAnalisisVal === $codigo ? 'selected' : '' }}>
                {{ $codigo }} - {{ trim($met->metodo_descripcion) }}
            </option>
        @endforeach
    </select>
    @error('metodo')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ === 'undefined' || !$.fn.select2) {
        return;
    }

    const select2MetodoOpts = {
        width: '100%',
        allowClear: true,
        language: {
            noResults: function() {
                return 'No se encontraron resultados';
            },
            searching: function() {
                return 'Buscando...';
            }
        }
    };

    $('#metodo_muestreo').select2({
        ...select2MetodoOpts,
        placeholder: $('#metodo_muestreo').data('placeholder') || 'Buscar método de muestreo...'
    });

    $('#metodo').select2({
        ...select2MetodoOpts,
        placeholder: $('#metodo').data('placeholder') || 'Buscar método de análisis...'
    });
});
</script>
@endpush
@endonce
