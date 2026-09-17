@php
    $cotizacion = $cotizacion ?? null;
    $valor = fn (string $campo, $default = '') => old($campo, $cotizacion?->$campo ?? $default);
    $fechaInput = fn (string $campo) => $valor($campo)
        ? (\Illuminate\Support\Carbon::parse($valor($campo))->format('Y-m-d'))
        : '';
@endphp

<div id="cuotasPanel" class="row mb-3 border rounded p-3 bg-light {{ ($cotizacion?->coti_cuotas ?? false) || trim($cotizacion?->coti_cond_pago ?? '') === 'CUOTAS' || old('coti_cond_pago') === 'CUOTAS' ? '' : 'd-none' }}">
    <div class="col-12 mb-2">
        <h6 class="mb-1">Abono / cuotas mensuales</h6>
        <small class="text-muted">
            Definí cuándo comienza y termina la ejecución del trabajo (monitoreo, muestreos, etc.).
            Si la cotización se aprueba antes, las cuotas se facturan desde el mes de inicio de ejecución.
        </small>
    </div>

    <div class="col-md-3">
        <label for="coti_cuota_fecha_inicio" class="form-label">Inicio de ejecución</label>
        <input type="date" class="form-control form-control-sm" id="coti_cuota_fecha_inicio" name="coti_cuota_fecha_inicio"
               value="{{ $fechaInput('coti_cuota_fecha_inicio') }}">
        <small class="text-muted">Ej: ene/2027 aunque se apruebe en sep/2026</small>
    </div>
    <div class="col-md-3">
        <label for="coti_cuota_fecha_fin" class="form-label">Fin de ejecución</label>
        <input type="date" class="form-control form-control-sm" id="coti_cuota_fecha_fin" name="coti_cuota_fecha_fin"
               value="{{ $fechaInput('coti_cuota_fecha_fin') }}">
        <small class="text-muted">Opcional; sugiere la cantidad de cuotas</small>
    </div>
    <div class="col-md-3">
        <label for="coti_cuota_desc" class="form-label">Descripción</label>
        <input type="text" class="form-control form-control-sm" id="coti_cuota_desc" name="coti_cuota_desc"
               value="{{ $valor('coti_cuota_desc') }}" placeholder="Ej: Monitoreo mensual 2027">
    </div>
    <div class="col-md-3">
        <label for="coti_cuota_cant" class="form-label">Cantidad de cuotas</label>
        <input type="number" class="form-control form-control-sm" id="coti_cuota_cant" name="coti_cuota_cant"
               value="{{ $valor('coti_cuota_cant', 1) }}" min="1">
    </div>

    <div class="col-md-2 mt-2">
        <label for="coti_cuota_interes" class="form-label">
            Interés (%)
            <span class="text-muted" title="Porcentaje de interés total aplicado sobre el monto de la cotización antes de dividir en cuotas." style="cursor:help;">&#9432;</span>
        </label>
        <div class="input-group input-group-sm">
            <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="coti_cuota_interes"
                   name="coti_cuota_interes" value="{{ $valor('coti_cuota_interes', 0) }}" placeholder="0.00">
            <span class="input-group-text">%</span>
        </div>
    </div>
    <div class="col-md-2 mt-2">
        <label for="coti_cuota_monto_total" class="form-label">Monto total</label>
        <input type="number" step="0.01" class="form-control form-control-sm bg-light" id="coti_cuota_monto_total"
               name="coti_cuota_monto_total" value="{{ $valor('coti_cuota_monto_total') }}" placeholder="0.00" readonly>
    </div>
    <div class="col-md-2 mt-2">
        <label for="coti_cuota_monto_indiv" class="form-label">Monto por cuota</label>
        <input type="number" step="0.01" class="form-control form-control-sm bg-light" id="coti_cuota_monto_indiv"
               name="coti_cuota_monto_indiv" value="{{ $valor('coti_cuota_monto_indiv') }}" placeholder="0.00" readonly>
    </div>
    <div class="col-md-6 mt-2 d-flex align-items-end gap-3 flex-wrap">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="coti_cuota_fact_fin_mes" name="coti_cuota_fact_fin_mes" value="1"
                {{ old('coti_cuota_fact_fin_mes', $cotizacion?->coti_cuota_fact_fin_mes) ? 'checked' : '' }}>
            <label class="form-check-label" for="coti_cuota_fact_fin_mes">Fact. fin de mes</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="coti_cuota_fact_inicio_mes" name="coti_cuota_fact_inicio_mes" value="1"
                {{ old('coti_cuota_fact_inicio_mes', $cotizacion?->coti_cuota_fact_inicio_mes) ? 'checked' : '' }}>
            <label class="form-check-label" for="coti_cuota_fact_inicio_mes">Fact. inicio de mes</label>
        </div>
    </div>
</div>
