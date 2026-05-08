@php
    $rutasOcultas = ['consultoria.index', 'asp.index', 'clarke-fire.index'];
    $mostrarBadge = !request()->routeIs(...$rutasOcultas);
@endphp
@if($mostrarBadge && ($canal = $coti->canal_especial))
    @php
        $canalLabel = '';
        switch($canal) {
            case 'consultoria': $canalLabel = 'Consultoría'; break;
            case 'asp':         $canalLabel = 'ASP'; break;
            case 'clarke_fire': $canalLabel = 'Clarke Fire'; break;
            case 'mediciones':  $canalLabel = 'Mediciones'; break;
            default:            $canalLabel = ucwords(str_replace('_', ' ', $canal));
        }

        $canalColorMap = [
            'consultoria' => '#6f42c1', // Morado
            'asp'         => '#fd7e14', // Naranja
            'clarke_fire' => '#e83e8c', // Rosa
            'mediciones'  => '#20c997', // Teal
        ];
        $canalColor = $canalColorMap[$canal] ?? '#6c757d';
    @endphp
    <span class="badge text-white px-2 py-1 rounded-pill ms-1" 
          style="font-size: 0.65rem; background-color: {{ $canalColor }};"
          data-bs-toggle="tooltip"
          title="Canal Especial: {{ $canalLabel }}">
        {{ strtoupper($canalLabel) }}
    </span>
@endif
