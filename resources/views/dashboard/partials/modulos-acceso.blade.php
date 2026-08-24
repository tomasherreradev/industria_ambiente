@php
    $modulos = $modulosAcceso ?? config('admin_modulos_acceso', []);
    $usuarioNivelMaximo = Auth::check() && (int) (Auth::user()->usu_nivel ?? 0) === 999;
    $modulos = collect($modulos)->filter(function ($modulo) use ($usuarioNivelMaximo) {
        $route = $modulo['route'] ?? '';
        if ($route === 'items.index') {
            return userPuedeCargarItems();
        }
        if ($route === 'leyes-normativas.index') {
            return $usuarioNivelMaximo;
        }

        return true;
    })->values()->all();
    $favoritosCount = min(9, count($modulos));
    $modulosFavoritos = array_slice($modulos, 0, $favoritosCount);
    $modulosResto = array_slice($modulos, $favoritosCount);
@endphp

@if(count($modulos) > 0)
<link rel="stylesheet" href="{{ asset('css/dashboard-modulos-acceso.css') }}?v={{ filemtime(public_path('css/dashboard-modulos-acceso.css')) }}">

<div class="dashboard-apps-launcher mb-4" id="dashboardAppsLauncher">
    <button type="button"
            class="dashboard-apps-launcher__trigger"
            id="dashboardAppsLauncherTrigger"
            aria-expanded="false"
            aria-controls="dashboardAppsLauncherPanel">
        <span class="dashboard-apps-launcher__trigger-icon" aria-hidden="true">
            <x-heroicon-o-squares-2x2 />
        </span>
        <span class="dashboard-apps-launcher__trigger-text">
            <span class="dashboard-apps-launcher__trigger-title">Acceso a módulos</span>
            <span class="dashboard-apps-launcher__trigger-subtitle d-none d-sm-block">
                Atajos a todas las áreas del sistema
            </span>
        </span>
        <span class="badge rounded-pill dashboard-apps-launcher__trigger-badge">{{ count($modulos) }}</span>
        <span class="dashboard-apps-launcher__trigger-chevron" aria-hidden="true">
            <x-heroicon-o-chevron-down style="width: 1.25rem; height: 1.25rem;" />
        </span>
    </button>

    <div class="dashboard-apps-launcher__panel-wrap" id="dashboardAppsLauncherPanelWrap">
        <div class="dashboard-apps-launcher__panel" id="dashboardAppsLauncherPanel">
            <div class="dashboard-apps-launcher__panel-inner">
                @if(count($modulosFavoritos) > 0)
                    <section class="dashboard-apps-favorites" aria-label="Módulos favoritos">
                        <div class="dashboard-apps-section-header">
                            <span class="dashboard-apps-section-header__title">Tus favoritos</span>
                        </div>
                        <div class="dashboard-apps-grid">
                            @foreach($modulosFavoritos as $index => $modulo)
                                @include('dashboard.partials.modulo-acceso-tile', [
                                    'modulo' => $modulo,
                                    'tileIndex' => $index,
                                ])
                            @endforeach
                        </div>
                    </section>
                @endif

                @if(count($modulosResto) > 0)
                    <section class="dashboard-apps-more" aria-label="Más módulos">
                        @if(count($modulosFavoritos) > 0)
                            <div class="dashboard-apps-section-header">
                                <span class="dashboard-apps-section-header__title">Más módulos</span>
                            </div>
                        @endif
                        <div class="dashboard-apps-grid">
                            @foreach($modulosResto as $index => $modulo)
                                @include('dashboard.partials.modulo-acceso-tile', [
                                    'modulo' => $modulo,
                                    'tileIndex' => $favoritosCount + $index,
                                ])
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const launcher = document.getElementById('dashboardAppsLauncher');
    const trigger = document.getElementById('dashboardAppsLauncherTrigger');

    if (!launcher || !trigger) {
        return;
    }

    function setOpen(open) {
        launcher.classList.toggle('is-open', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    trigger.addEventListener('click', function (event) {
        event.stopPropagation();
        setOpen(!launcher.classList.contains('is-open'));
    });

    document.addEventListener('click', function (event) {
        if (!launcher.classList.contains('is-open')) {
            return;
        }
        if (!launcher.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && launcher.classList.contains('is-open')) {
            setOpen(false);
            trigger.focus();
        }
    });
});
</script>
@endpush
@endif
