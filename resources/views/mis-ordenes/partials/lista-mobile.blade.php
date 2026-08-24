@php
    $totalActivas = count($gruposRevisionResultados) + count($gruposPrioritarios) + count($gruposCoordinados) + count($gruposEnRevision);
    $totalVencidas = count($gruposVencidas);
    $totalFinalizadas = count($gruposFinalizados);
@endphp

<div class="muestras-mobile-list">
    <p class="muestras-mobile-stats">
        <strong>{{ $totalActivas }}</strong> activa{{ $totalActivas === 1 ? '' : 's' }}
        @if($totalVencidas > 0)
            · <span class="text-danger"><strong>{{ $totalVencidas }}</strong> vencida{{ $totalVencidas === 1 ? '' : 's' }}</span>
        @endif
    </p>

    @if($totalFinalizadas > 0 || $totalVencidas > 0)
        <div class="muestras-mobile-filtros">
            @if($totalVencidas > 0)
                <div class="muestras-mobile-filtro">
                    <div class="form-check form-switch mb-0 muestras-mobile-switch">
                        <input class="form-check-input" type="checkbox" id="toggleVencidasMobileOrdenes">
                        <label class="form-check-label" for="toggleVencidasMobileOrdenes">Mostrar vencidas</label>
                    </div>
                </div>
            @endif
            @if($totalFinalizadas > 0)
                <div class="muestras-mobile-filtro">
                    <div class="form-check form-switch mb-0 muestras-mobile-switch">
                        <input class="form-check-input" type="checkbox" id="toggleFinalizadasMobileOrdenes">
                        <label class="form-check-label" for="toggleFinalizadasMobileOrdenes">Mostrar finalizadas</label>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="muestras-mobile-cards">
        @if($totalVencidas > 0)
            <div class="muestras-mobile-section ordenes-vencidas-section-mobile">
                @foreach($gruposVencidas as $grupoData)
                    @include('mis-ordenes.partials.grupo-card-mobile', [
                        'grupoData' => $grupoData,
                        'cotizaciones' => $cotizaciones,
                        'collapsePrefix' => 'mov',
                        'soloMisAsignaciones' => $soloMisAsignaciones ?? false,
                    ])
                @endforeach
            </div>
        @endif

        @foreach($gruposRevisionResultados as $grupoData)
            @include('mis-ordenes.partials.grupo-card-mobile', [
                'grupoData' => $grupoData,
                'cotizaciones' => $cotizaciones,
                'collapsePrefix' => 'mrr',
                'soloMisAsignaciones' => $soloMisAsignaciones ?? false,
            ])
        @endforeach

        @foreach($gruposPrioritarios as $grupoData)
            @include('mis-ordenes.partials.grupo-card-mobile', [
                'grupoData' => $grupoData,
                'cotizaciones' => $cotizaciones,
                'collapsePrefix' => 'mop',
                'soloMisAsignaciones' => $soloMisAsignaciones ?? false,
            ])
        @endforeach

        @foreach($gruposCoordinados as $grupoData)
            @include('mis-ordenes.partials.grupo-card-mobile', [
                'grupoData' => $grupoData,
                'cotizaciones' => $cotizaciones,
                'collapsePrefix' => 'moc',
                'soloMisAsignaciones' => $soloMisAsignaciones ?? false,
            ])
        @endforeach

        @foreach($gruposEnRevision as $grupoData)
            @include('mis-ordenes.partials.grupo-card-mobile', [
                'grupoData' => $grupoData,
                'cotizaciones' => $cotizaciones,
                'collapsePrefix' => 'mor',
                'soloMisAsignaciones' => $soloMisAsignaciones ?? false,
            ])
        @endforeach

        @if($totalFinalizadas > 0)
            <div class="muestras-mobile-section ordenes-finalizadas-section-mobile" style="display: none;">
                @foreach($gruposFinalizados as $grupoData)
                    @include('mis-ordenes.partials.grupo-card-mobile', [
                        'grupoData' => $grupoData,
                        'cotizaciones' => $cotizaciones,
                        'collapsePrefix' => 'mof',
                        'soloMisAsignaciones' => $soloMisAsignaciones ?? false,
                    ])
                @endforeach
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.muestra-card__toggle').forEach(function (btn) {
        var target = document.querySelector(btn.getAttribute('data-bs-target'));
        if (!target) return;
        target.addEventListener('show.bs.collapse', function () {
            btn.setAttribute('aria-expanded', 'true');
            btn.classList.add('is-open');
        });
        target.addEventListener('hide.bs.collapse', function () {
            btn.setAttribute('aria-expanded', 'false');
            btn.classList.remove('is-open');
        });
    });

    var toggleVencidasMobile = document.getElementById('toggleVencidasMobileOrdenes');
    var sectionVencidasMobile = document.querySelector('.ordenes-vencidas-section-mobile');
    if (toggleVencidasMobile && sectionVencidasMobile) {
        var mostrarVencidas = localStorage.getItem('mostrarOrdenesVencidas') !== 'false';
        toggleVencidasMobile.checked = mostrarVencidas;
        sectionVencidasMobile.style.display = mostrarVencidas ? 'block' : 'none';
        toggleVencidasMobile.addEventListener('change', function () {
            sectionVencidasMobile.style.display = this.checked ? 'block' : 'none';
            localStorage.setItem('mostrarOrdenesVencidas', this.checked ? 'true' : 'false');
            var desktopToggle = document.getElementById('toggleOrdenesVencidas');
            if (desktopToggle) {
                desktopToggle.checked = this.checked;
                desktopToggle.dispatchEvent(new Event('change'));
            }
        });
    }

    var toggleFinalizadasMobile = document.getElementById('toggleFinalizadasMobileOrdenes');
    var sectionFinalizadasMobile = document.querySelector('.ordenes-finalizadas-section-mobile');
    if (toggleFinalizadasMobile && sectionFinalizadasMobile) {
        var mostrarFinalizadas = localStorage.getItem('mostrarOrdenesFinalizadas') === 'true';
        toggleFinalizadasMobile.checked = mostrarFinalizadas;
        sectionFinalizadasMobile.style.display = mostrarFinalizadas ? 'block' : 'none';
        toggleFinalizadasMobile.addEventListener('change', function () {
            sectionFinalizadasMobile.style.display = this.checked ? 'block' : 'none';
            localStorage.setItem('mostrarOrdenesFinalizadas', this.checked ? 'true' : 'false');
        });
    }
});
</script>
