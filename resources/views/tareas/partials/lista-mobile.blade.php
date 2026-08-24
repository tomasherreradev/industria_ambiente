@php
    $totalActivas = count($gruposPrioritarios) + count($gruposCoordinados) + count($gruposEnRevision);
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
                        <input class="form-check-input" type="checkbox" id="toggleVencidasMobile" checked>
                        <label class="form-check-label" for="toggleVencidasMobile">Mostrar vencidas</label>
                    </div>
                </div>
            @endif
            @if($totalFinalizadas > 0)
                <div class="muestras-mobile-filtro">
                    <div class="form-check form-switch mb-0 muestras-mobile-switch">
                        <input class="form-check-input" type="checkbox" id="toggleFinalizadasMobile">
                        <label class="form-check-label" for="toggleFinalizadasMobile">Mostrar finalizadas</label>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="muestras-mobile-cards">
        @if($totalVencidas > 0)
            <div class="muestras-mobile-section muestras-vencidas-section-mobile">
                @foreach($gruposVencidas as $grupoData)
                    @include('tareas.partials.grupo-card-mobile', [
                        'grupoData' => $grupoData,
                        'cotizaciones' => $cotizaciones,
                        'collapsePrefix' => 'mv',
                    ])
                @endforeach
            </div>
        @endif

        @foreach($gruposPrioritarios as $grupoData)
            @include('tareas.partials.grupo-card-mobile', [
                'grupoData' => $grupoData,
                'cotizaciones' => $cotizaciones,
                'collapsePrefix' => 'mp',
            ])
        @endforeach

        @foreach($gruposCoordinados as $grupoData)
            @include('tareas.partials.grupo-card-mobile', [
                'grupoData' => $grupoData,
                'cotizaciones' => $cotizaciones,
                'collapsePrefix' => 'mc',
            ])
        @endforeach

        @foreach($gruposEnRevision as $grupoData)
            @include('tareas.partials.grupo-card-mobile', [
                'grupoData' => $grupoData,
                'cotizaciones' => $cotizaciones,
                'collapsePrefix' => 'mr',
            ])
        @endforeach

        @if($totalFinalizadas > 0)
            <div class="muestras-mobile-section muestras-finalizadas-section-mobile" style="display: none;">
                @foreach($gruposFinalizados as $grupoData)
                    @include('tareas.partials.grupo-card-mobile', [
                        'grupoData' => $grupoData,
                        'cotizaciones' => $cotizaciones,
                        'collapsePrefix' => 'mf',
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

    var toggleVencidasMobile = document.getElementById('toggleVencidasMobile');
    var sectionVencidasMobile = document.querySelector('.muestras-vencidas-section-mobile');
    if (toggleVencidasMobile && sectionVencidasMobile) {
        var mostrarVencidas = localStorage.getItem('mostrarMuestrasVencidas') !== 'false';
        toggleVencidasMobile.checked = mostrarVencidas;
        sectionVencidasMobile.style.display = mostrarVencidas ? 'block' : 'none';
        toggleVencidasMobile.addEventListener('change', function () {
            sectionVencidasMobile.style.display = this.checked ? 'block' : 'none';
            localStorage.setItem('mostrarMuestrasVencidas', this.checked ? 'true' : 'false');
            var desktopToggle = document.getElementById('toggleVencidas');
            if (desktopToggle) {
                desktopToggle.checked = this.checked;
                desktopToggle.dispatchEvent(new Event('change'));
            }
        });
    }

    var toggleFinalizadasMobile = document.getElementById('toggleFinalizadasMobile');
    var sectionFinalizadasMobile = document.querySelector('.muestras-finalizadas-section-mobile');
    if (toggleFinalizadasMobile && sectionFinalizadasMobile) {
        var mostrarFinalizadas = localStorage.getItem('mostrarMuestrasFinalizadas') === 'true';
        toggleFinalizadasMobile.checked = mostrarFinalizadas;
        sectionFinalizadasMobile.style.display = mostrarFinalizadas ? 'block' : 'none';
        toggleFinalizadasMobile.addEventListener('change', function () {
            sectionFinalizadasMobile.style.display = this.checked ? 'block' : 'none';
            localStorage.setItem('mostrarMuestrasFinalizadas', this.checked ? 'true' : 'false');
            var desktopToggle = document.getElementById('toggleFinalizadas');
            if (desktopToggle) {
                desktopToggle.checked = this.checked;
                desktopToggle.dispatchEvent(new Event('change'));
            }
        });
    }
});
</script>
