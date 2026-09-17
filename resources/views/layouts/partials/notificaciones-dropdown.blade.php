@php
    $notificacionesRecientes = App\Models\SimpleNotification::where('coordinador_codigo', auth()->user()->usu_codigo)
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();
@endphp

<ul class="dropdown-menu dropdown-menu-end app-topbar__dropdown app-topbar__dropdown--notifs" aria-labelledby="notificationsDropdown">
    <li class="notif-dropdown__header">
        <p class="notif-dropdown__title">Notificaciones</p>
        <form action="{{ route('notificaciones.leer-todas') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="notif-dropdown__mark-all">Marcar todas</button>
        </form>
    </li>

    <li class="p-0">
        <ul class="notif-dropdown__list">
            @forelse($notificacionesRecientes as $notificacion)
                <li>
                    <a href="{{ route('notificaciones.show', $notificacion->id) }}"
                       class="notif-dropdown__item {{ $notificacion->leida ? '' : 'notif-dropdown__item--unread' }}">
                        <span class="notif-dropdown__icon">
                            <x-heroicon-o-bell />
                        </span>
                        <span class="notif-dropdown__body">
                            <span class="notif-dropdown__text"
                                  data-bs-toggle="tooltip"
                                  data-bs-placement="bottom"
                                  title="{{ $notificacion->mensaje }}">
                                {{ $notificacion->resumenCorto(100) }}
                            </span>
                            <span class="notif-dropdown__time">
                                {{ $notificacion->created_at->locale('es')->diffForHumans() }}
                            </span>
                        </span>
                        @if(! $notificacion->leida)
                            <span class="notif-dropdown__dot" aria-hidden="true"></span>
                        @endif
                    </a>
                </li>
            @empty
                <li class="notif-dropdown__empty">
                    <x-heroicon-o-bell />
                    <p class="mb-0">No hay notificaciones</p>
                </li>
            @endforelse
        </ul>
    </li>

    <li class="notif-dropdown__footer">
        <a href="{{ route('notificaciones.index') }}" class="notif-dropdown__footer-link">
            Ver todas las notificaciones
        </a>
    </li>
</ul>
