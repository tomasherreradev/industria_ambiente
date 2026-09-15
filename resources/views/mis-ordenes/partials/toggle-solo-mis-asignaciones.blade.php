@if(!empty($puedeAlternarVistaAsignaciones))
    @php
        $soloActivo = !empty($soloMisAsignaciones);
        $queryBase = request()->except(['solo_mis_asignaciones', 'page']);
        $urlTodos = isset($urlMisOrdenesTodos)
            ? $urlMisOrdenesTodos
            : route('mis-ordenes', array_merge($queryBase, array_filter(['view' => request('view', 'lista')])));
        $urlSolo = isset($urlMisOrdenesSolo)
            ? $urlMisOrdenesSolo
            : route('mis-ordenes', array_merge($queryBase, array_filter([
                'view' => request('view', 'lista'),
                'solo_mis_asignaciones' => 1,
            ])));
    @endphp
    <div class="d-flex align-items-center gap-2 {{ $wrapperClass ?? '' }}">
        <div class="ucrud-view-switch ucrud-view-switch--text" role="group" aria-label="Alcance de análisis visibles">
            <a href="{{ $urlTodos }}"
               class="ucrud-view-switch__btn {{ $soloActivo ? '' : 'active' }}"
               title="Ver todos los análisis de las muestras">
                Todos
            </a>
            <a href="{{ $urlSolo }}"
               class="ucrud-view-switch__btn {{ $soloActivo ? 'active' : '' }}"
               title="Ver solo análisis asignados a mi usuario">
                Solo mis asignaciones
            </a>
        </div>
        @if($soloActivo)
            <span class="badge bg-info text-dark d-none d-md-inline">Filtrado</span>
        @endif
    </div>
@endif
