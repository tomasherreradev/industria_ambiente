@php
    use App\Support\PerfilUsuarioResumen;

    $rolEtq = PerfilUsuarioResumen::etiquetaRol(trim((string) ($usuario->rol ?? '')));
    $activo = (bool) ($usuario->usu_estado ?? false);
@endphp

<div class="ucrud-panel h-100">
    <div class="ucrud-detail-block">
        <h2 class="ucrud-detail-block__title d-flex align-items-center gap-2">
            <x-heroicon-o-information-circle style="width: 18px; height: 18px;" />
            Resumen
        </h2>
        <div class="ucrud-detail-grid mt-2">
            <div>
                <div class="ucrud-detail-item__label">Estado</div>
                <div class="ucrud-detail-item__value">
                    <span class="ucrud-role-chip {{ $activo ? '' : 'ucrud-role-chip--extra' }}">
                        {{ $activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
            </div>
            @if($rolEtq !== '')
                <div class="ucrud-detail-item--wide">
                    <div class="ucrud-detail-item__label">Rol principal</div>
                    <div class="ucrud-detail-item__value">{{ $rolEtq }}</div>
                </div>
            @endif
            @if((int) Auth::user()->usu_nivel >= 900)
                <div>
                    <div class="ucrud-detail-item__label">Nivel</div>
                    <div class="ucrud-detail-item__value">{{ (int) ($usuario->usu_nivel ?? 0) }}</div>
                </div>
            @endif
        </div>
    </div>
    <div class="ucrud-detail-block">
        <h2 class="ucrud-detail-block__title d-flex align-items-center gap-2">
            <x-heroicon-o-light-bulb style="width: 18px; height: 18px;" />
            Tips
        </h2>
        <ul class="small text-muted mb-0 ps-3">
            <li class="mb-2">Los roles definen el menú y las pantallas visibles.</li>
            <li class="mb-2">Analista y coordinador de lab usan sectores múltiples.</li>
            <li>Inactivo impide iniciar sesión sin borrar el usuario.</li>
        </ul>
    </div>
</div>
