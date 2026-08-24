@php
    $activo = $activo ?? 'usuarios';
@endphp

<nav class="ucrud-segmented" data-ucrud-segmented aria-label="Cambiar entre usuarios y laboratorios">
    <span class="ucrud-segmented__pill" aria-hidden="true"></span>

    <a href="{{ url('/users') }}"
       class="ucrud-segmented__item {{ $activo === 'usuarios' ? 'is-active' : '' }}"
       @if($activo === 'usuarios') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        <span>Usuarios</span>
    </a>

    <a href="{{ url('/sectores') }}"
       class="ucrud-segmented__item {{ $activo === 'laboratorios' ? 'is-active' : '' }}"
       @if($activo === 'laboratorios') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 3h6"/>
            <path d="M10 3v6.4L4.6 18a2 2 0 0 0 1.7 3h11.4a2 2 0 0 0 1.7-3L14 9.4V3"/>
            <path d="M7.2 15h9.6"/>
        </svg>
        <span>Laboratorios</span>
    </a>
</nav>
