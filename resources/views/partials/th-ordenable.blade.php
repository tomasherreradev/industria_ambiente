@props([
    'columna',
    'etiqueta',
    'sortActual' => request('sort'),
    'dirActual' => null,
    'width' => null,
    'centrado' => false,
    'extraClass' => '',
])
@php
    $dirActual = $dirActual ?? (strtolower((string) request('dir', 'asc')) === 'desc' ? 'desc' : 'asc');
    $nuevaDir = ($sortActual === $columna && $dirActual === 'asc') ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery([
        'sort' => $columna,
        'dir' => $nuevaDir,
        'page' => null,
    ]);
    $iconoClase = $sortActual !== $columna ? 'text-muted opacity-50' : 'text-primary';
    $icono = $sortActual === $columna ? ($dirActual === 'desc' ? '↓' : '↑') : '↕';
@endphp
<th @if($width) width="{{ $width }}" @endif class="{{ trim(($centrado ? 'text-center ' : '') . $extraClass) }}">
    <a href="{{ $url }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1 {{ $centrado ? 'justify-content-center w-100' : '' }}">
        {{ $etiqueta }}
        <span class="{{ $iconoClase }}" style="font-size: 0.75rem;" aria-hidden="true">{{ $icono }}</span>
    </a>
</th>
