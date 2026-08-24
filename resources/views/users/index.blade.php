@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
<link rel="stylesheet" href="{{ asset('css/usuarios-crud.css') }}?v={{ filemtime(public_path('css/usuarios-crud.css')) }}">

@php
    $puedeEditarUsuarios = $puedeEditarUsuarios ?? false;
    $puedeEliminarUsuarios = $puedeEliminarUsuarios ?? false;
    $miCodigo = trim((string) (Auth::user()->usu_codigo ?? ''));

    // Etiqueta legible + tono del chip para cada rol.
    $rolesMeta = [
        'laboratorio'            => ['Analista', 'blue'],
        'muestreador'            => ['Muestreador', 'green'],
        'coordinador_lab'        => ['Coord. Laboratorio', 'violet'],
        'coordinador_muestreo'   => ['Coord. Muestreo', 'violet'],
        'coordinador_consul'     => ['Coord. Consultoría', 'violet'],
        'coordinador_mediciones' => ['Coord. Mediciones', 'violet'],
        'ventas'                 => ['Vendedor', 'amber'],
        'firmador'               => ['Firmador', 'cyan'],
        'facturador'             => ['Facturador', 'rose'],
        'asp'                    => ['ASP', 'slate'],
        'clarke_fire'            => ['Clarke Fire', 'slate'],
        'cliente'                => ['Usuario Cliente', 'slate'],
    ];

    // Opciones del filtro (mismas que antes).
    $rolesFiltro = [
        'laboratorio', 'muestreador', 'coordinador_lab', 'coordinador_muestreo', 'ventas',
        'firmador', 'facturador', 'coordinador_consul', 'coordinador_mediciones', 'asp', 'clarke_fire',
    ];

    $hayFiltros = request('search') || request('rol');
@endphp

<div class="container py-4 ucrud" data-ucrud-root>

    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Usuarios
                <span class="ucrud-count">{{ $usuarios->total() }}</span>
            </h1>
            <p class="ucrud-subtitle">Gestioná las cuentas del sistema, sus roles y laboratorios asignados.</p>
        </div>

        <div class="ucrud-header__actions">
            @include('partials.ucrud-nav', ['activo' => 'usuarios'])

            @if($puedeEditarUsuarios)
                <a href="{{ route('users.createUser') }}" class="ucrud-btn ucrud-btn--primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    Nuevo usuario
                </a>
            @endif
        </div>
    </header>

    <form action="{{ url('/users') }}" method="GET" class="ucrud-toolbar">
        <label class="ucrud-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7"/>
                <path d="m20 20-3.1-3.1"/>
            </svg>
            <input type="text" name="search" class="ucrud-input" placeholder="Buscar por nombre o código…" value="{{ request('search') }}" aria-label="Buscar usuarios">
        </label>

        <select name="rol" class="ucrud-select" aria-label="Filtrar por rol" onchange="this.form.submit()">
            <option value="">Todos los roles</option>
            @foreach($rolesFiltro as $valor)
                <option value="{{ $valor }}" @selected(request('rol') === $valor)>{{ $rolesMeta[$valor][0] }}</option>
            @endforeach
        </select>

        <button type="submit" class="ucrud-btn ucrud-btn--primary">Buscar</button>

        @if($hayFiltros)
            <a href="{{ url('/users') }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
        @endif
    </form>

    @if(session('success'))
        <div class="ucrud-alert ucrud-alert--success" role="status">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <path d="m8.5 12.5 2.5 2.5 4.5-5"/>
            </svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="ucrud-alert ucrud-alert--danger" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
                <path d="M12 9v4M12 17h.01"/>
            </svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="ucrud-panel">
        @if($usuarios->isEmpty())
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M17 8h6"/>
                    </svg>
                </div>
                <p class="ucrud-empty__title">No hay usuarios para mostrar</p>
                <p class="ucrud-empty__text">
                    {{ $hayFiltros ? 'Probá ajustar la búsqueda o quitar los filtros aplicados.' : 'Todavía no hay usuarios cargados en el sistema.' }}
                </p>
            </div>
        @else
            {{-- Escritorio --}}
            <div class="d-none d-lg-block ucrud-tablewrap">
                <table class="ucrud-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Código</th>
                            <th>Rol</th>
                            <th>Laboratorio</th>
                            <th>Sector</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $i => $usu)
                            @php
                                $nombre = trim((string) $usu->usu_descripcion);
                                $codigo = trim((string) $usu->usu_codigo);
                                $partes = array_values(array_filter(preg_split('/\s+/', $nombre)));
                                $iniciales = mb_strtoupper(
                                    mb_substr($partes[0] ?? $codigo, 0, 1)
                                    . (count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '')
                                );
                                $tono = crc32($codigo) % 360;
                                $rolMeta = $rolesMeta[(string) $usu->rol] ?? null;
                                $esYo = $codigo === $miCodigo;
                            @endphp
                            <tr class="ucrud-animate-in" style="--i: {{ $i }}">
                                <td>
                                    <div class="ucrud-user">
                                        <span class="ucrud-avatar" style="background: linear-gradient(135deg, hsl({{ $tono }} 62% 60%), hsl({{ ($tono + 28) % 360 }} 66% 47%));" aria-hidden="true">{{ $iniciales }}</span>
                                        <span class="text-truncate">
                                            <span class="ucrud-user__name d-block">{{ $nombre }}</span>
                                            @if($esYo)
                                                <span class="ucrud-user__meta">Tu cuenta</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td><span class="ucrud-code">{{ $codigo }}</span></td>
                                <td>
                                    @if($rolMeta)
                                        <span class="ucrud-chip ucrud-chip--{{ $rolMeta[1] }}">{{ $rolMeta[0] }}</span>
                                    @elseif($usu->rol)
                                        <span class="ucrud-chip ucrud-chip--slate">{{ $usu->rol }}</span>
                                    @else
                                        <span class="ucrud-chip ucrud-chip--muted">Sin rol</span>
                                    @endif
                                </td>
                                <td>
                                    @if($usu->sector_codigo)
                                        {{ $usu->sector_codigo }}
                                    @else
                                        <span class="ucrud-dim">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($usu->sector_trabajo)
                                        {{ $usu->sector_trabajo }}
                                    @else
                                        <span class="ucrud-dim">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="ucrud-actions">
                                        <a class="ucrud-iconbtn"
                                           href="{{ url('/users/' . $usu->usu_codigo) }}"
                                           title="{{ $puedeEditarUsuarios ? 'Editar usuario' : 'Ver usuario' }}"
                                           aria-label="{{ $puedeEditarUsuarios ? 'Editar' : 'Ver' }} {{ $nombre }}">
                                            @if($puedeEditarUsuarios)
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M12 20h9"/>
                                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                                                </svg>
                                            @else
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
                                                    <circle cx="12" cy="12" r="3"/>
                                                </svg>
                                            @endif
                                        </a>

                                        @if($puedeEliminarUsuarios && ! $esYo)
                                            <form action="{{ route('users.destroy', ['usu_codigo' => $usu->usu_codigo]) }}"
                                                  method="POST"
                                                  class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar definitivamente a {{ $nombre }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="ucrud-iconbtn ucrud-iconbtn--danger"
                                                        title="Eliminar usuario"
                                                        aria-label="Eliminar {{ $nombre }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M3 6h18"/>
                                                        <path d="M8 6V4h8v2"/>
                                                        <path d="M18.5 6 17.6 20H6.4L5.5 6"/>
                                                        <path d="M10 11v5M14 11v5"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="d-block d-lg-none">
                @foreach($usuarios as $i => $usu)
                    @php
                        $nombre = trim((string) $usu->usu_descripcion);
                        $codigo = trim((string) $usu->usu_codigo);
                        $partes = array_values(array_filter(preg_split('/\s+/', $nombre)));
                        $iniciales = mb_strtoupper(
                            mb_substr($partes[0] ?? $codigo, 0, 1)
                            . (count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '')
                        );
                        $tono = crc32($codigo) % 360;
                        $rolMeta = $rolesMeta[(string) $usu->rol] ?? null;
                    @endphp
                    <a href="{{ url('/users/' . $usu->usu_codigo) }}" class="ucrud-card ucrud-animate-in" style="--i: {{ $i }}">
                        <span class="ucrud-avatar" style="background: linear-gradient(135deg, hsl({{ $tono }} 62% 60%), hsl({{ ($tono + 28) % 360 }} 66% 47%));" aria-hidden="true">{{ $iniciales }}</span>
                        <span class="ucrud-card__body">
                            <span class="ucrud-user__name d-block">{{ $nombre }}</span>
                            <span class="ucrud-card__meta">
                                <span class="ucrud-code">{{ $codigo }}</span>
                                @if($rolMeta)
                                    <span class="ucrud-chip ucrud-chip--{{ $rolMeta[1] }}">{{ $rolMeta[0] }}</span>
                                @elseif($usu->rol)
                                    <span class="ucrud-chip ucrud-chip--slate">{{ $usu->rol }}</span>
                                @else
                                    <span class="ucrud-chip ucrud-chip--muted">Sin rol</span>
                                @endif
                                @if($usu->sector_codigo)
                                    <span class="ucrud-user__meta">{{ $usu->sector_codigo }}</span>
                                @endif
                            </span>
                        </span>
                        <span class="ucrud-card__chevron" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m9 6 6 6-6 6"/>
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>

            @if($usuarios->hasPages())
                <div class="ucrud-pagination">
                    {{ $usuarios->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/usuarios-crud.js') }}?v={{ filemtime(public_path('js/usuarios-crud.js')) }}"></script>
@endpush
