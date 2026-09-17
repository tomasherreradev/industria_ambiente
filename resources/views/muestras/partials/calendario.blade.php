@include('partials.operativo-calendario-assets')

<div class="ucrud-panel op-calendario" id="calendar-container">
    <div class="op-calendario__header">
        <div>
            <h2 class="op-calendario__title">
                @if($viewTasks)
                    Tareas de {{ $userToView }}
                @else
                    Calendario de muestras
                @endif
            </h2>
            @if($viewTasks)
                <span class="op-calendario__meta">{{ count($events) }} tareas</span>
            @endif
        </div>

        <div class="op-calendario__toolbar">
            @if($viewTasks)
                <a href="{{ route('muestras.index', ['view' => 'calendario'] + request()->except(['user_to_view', 'view_tasks'])) }}"
                   class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            @endif

            <button id="current-week-btn" type="button" class="ucrud-btn ucrud-btn--primary ucrud-btn--sm">
                <i class="fas fa-calendar-day"></i> Hoy
            </button>

            @if(auth()->user()->usu_nivel >= 900)
                <div class="dropdown">
                    <button class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm dropdown-toggle"
                            type="button"
                            id="userDropdownMenu"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                        <i class="fas fa-user"></i> Ver tareas de
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdownMenu">
                        @foreach($usuarios as $usuario)
                        <li>
                            <a class="dropdown-item d-flex justify-content-between align-items-center" 
                            href="{{ route('muestras.index', [
                                'view' => 'calendario',
                                'view_tasks' => true,
                                'user_to_view' => $usuario->usu_codigo
                            ] + request()->except(['user_to_view', 'view_tasks'])) }}">
                                {{ $usuario->usu_descripcion }}
                                @if($viewTasks && $userToView == $usuario->usu_descripcion)
                                    <i class="fas fa-check ms-2"></i>
                                @endif
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    @if($events->isNotEmpty())
        <div id="calendar"></div>
    @else
        <div class="ucrud-alert ucrud-alert--info mb-0" role="status">
            <span>{{ $viewTasks ? 'No hay tareas asignadas a este usuario en el período seleccionado.' : 'No hay muestras programadas para mostrar en el calendario.' }}</span>
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.min.js'></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    
    if(calendarEl) {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'es',
            initialView: 'timeGridWeek',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día'
            },
            slotMinTime: '07:00:00',
            slotMaxTime: '20:00:00',
            allDaySlot: false,
            eventOrder: 'start',
            eventDisplay: 'block',
            dayMaxEvents: true,
            events: @json($events),
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                if (info.event.url) {
                    window.location.href = info.event.url;
                }
            },
            eventDidMount: function(info) {
                info.el.style.height = 'auto';
                
                if(info.event.extendedProps.analisis_count > 0) {
                    const titleEl = info.el.querySelector('.fc-event-title');
                    if(titleEl) {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-light text-dark badge-analisis-count';
                        badge.textContent = info.event.extendedProps.analisis_count;
                        titleEl.appendChild(badge);
                    }
                }

                if (info.event.extendedProps.coti_cuotas) {
                    const titleEl2 = info.el.querySelector('.fc-event-title');
                    if (titleEl2) {
                        const cuotasBadge = document.createElement('span');
                        cuotasBadge.className = 'badge bg-info text-white ms-1';
                        cuotasBadge.style.fontSize = '0.65em';
                        cuotasBadge.textContent = 'Cuotas';
                        cuotasBadge.title = 'Cotización con plan de cuotas';
                        titleEl2.appendChild(cuotasBadge);
                    }
                }

                if(info.event.extendedProps) {
                    new bootstrap.Tooltip(info.el, {
                        title: `
                            <strong>${info.event.extendedProps.empresa || 'Sin empresa'}</strong><br>
                            ${info.event.extendedProps.descripcion || 'Sin descripción'}<br>
                            <small>Estado: ${info.event.extendedProps.estado || 'No especificado'}</small>
                            ${info.event.extendedProps.coti_cuotas ? '<br><span class=\\"badge bg-info\\">Cuotas</span>' : ''}
                            ${info.event.extendedProps.responsables ? 
                              `<br><small>Responsables: ${info.event.extendedProps.responsables}</small>` : ''}
                            ${info.event.extendedProps.analisis_count > 0 ? 
                              `<br><small>Análisis: ${info.event.extendedProps.analisis_count}</small>` : ''}
                        `,
                        placement: 'bottom',
                        html: true,
                        container: 'body'
                    });
                }
            },
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            views: {
                timeGridWeek: {
                    dayMaxEventRows: false,
                    allDaySlot: false
                },
                timeGridDay: {
                    dayMaxEventRows: false,
                    allDaySlot: false
                },
                dayGridMonth: {
                    dayMaxEventRows: 4
                }
            }
        });
        
        calendar.render();
        
        document.getElementById('current-week-btn').addEventListener('click', function() {
            calendar.today();
        });
    }
});
document.addEventListener('DOMContentLoaded', function() {
    const dropdownElement = document.getElementById('userDropdownMenu');
    if (dropdownElement) {
        new bootstrap.Dropdown(dropdownElement);
    }
});

</script>