@php
    $esAdmin = Auth::check() && (Auth::user()->usu_nivel ?? 0) >= 900;
    $linkClass = $linkClass ?? '';
@endphp

@if($esAdmin || userHasRole('coordinador_consul'))
    <a class="nav-link {{ $linkClass }}" href="{{ route('consultoria.index') }}">
        Consultoría
    </a>
@endif

@if($esAdmin || userHasRole('coordinador_mediciones'))
    <a class="nav-link {{ $linkClass }}" href="{{ route('mediciones.index') }}">
        Mediciones
    </a>
@endif

@if($esAdmin || userHasRole('asp'))
    <a class="nav-link {{ $linkClass }}" href="{{ route('asp.index') }}">
        ASP
    </a>
@endif

@if($esAdmin || userHasRole('clarke_fire'))
    <a class="nav-link {{ $linkClass }}" href="{{ route('clarke-fire.index') }}">
        Clarke Fire
    </a>
@endif
