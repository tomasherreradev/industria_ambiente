@php
    use App\Support\AsignacionSectorLaboratorio;

    $responsables = $responsables ?? collect();
    $gruposSector = AsignacionSectorLaboratorio::sectoresConResponsablesAsignados($responsables);
    $puedeEditar = $puedeEditar ?? false;
@endphp

@if($gruposSector->isEmpty())
    <span class="badge bg-secondary rounded-pill">Sin asignar</span>
@else
    @foreach($gruposSector as $grupo)
        @php
            $tooltipUsuarios = $grupo['usuarios']
                ->map(fn ($u) => e(trim((string) ($u->usu_descripcion ?? $u->usu_codigo))))
                ->join('<br>');
        @endphp
        <span class="d-inline-flex align-items-center me-2 mb-1">
            <span
                class="badge bg-primary rounded-pill sector-asignacion-badge"
                data-bs-toggle="tooltip"
                data-bs-html="true"
                data-bs-placement="bottom"
                data-bs-title="{!! $tooltipUsuarios !!}"
            >
                {{ $grupo['sector_nombre'] }}
            </span>
            @if($puedeEditar && ($grupo['sector_codigo'] ?? '') !== '')
                <button
                    type="button"
                    class="btn btn-sm btn-link text-danger p-0 ms-1 js-quitar-sector-analisis"
                    style="line-height: 1; font-size: 1.1rem; text-decoration: none;"
                    data-sector-codigo="{{ $grupo['sector_codigo'] }}"
                    data-cotio-numcoti="{{ $cotioNumcoti }}"
                    data-cotio-item="{{ $cotioItem }}"
                    data-cotio-subitem="{{ $cotioSubitem }}"
                    data-instance-number="{{ $instanceNumber }}"
                    data-sector-nombre="{{ $grupo['sector_nombre'] }}"
                    title="Quitar sector"
                    aria-label="Quitar {{ $grupo['sector_nombre'] }}"
                >&times;</button>
            @endif
        </span>
    @endforeach
@endif
