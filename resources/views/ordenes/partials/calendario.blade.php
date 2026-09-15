{{-- Incluido dentro de ordenes/index: no usar @extends aquí (rompe @section del layout padre). --}}
@include('partials.operativo-calendario-assets')

@php
    $eventCount = collect($events)->where('cotio_subitem', 0)->count();
@endphp

<div class="ucrud-panel op-calendario" id="calendar-container">
    <div class="op-calendario__header">
        <div>
            <h2 class="op-calendario__title">Calendario de órdenes</h2>
            @if(request('matriz'))
                <span class="op-calendario__meta">
                    {{ $eventCount }} {{ $eventCount === 1 ? 'orden' : 'órdenes' }}
                </span>
            @endif
        </div>

        <div class="op-calendario__toolbar">
            @if($viewTasks ?? false)
                <a href="{{ route('ordenes.index', ['view' => 'calendario'] + request()->except(['user_to_view', 'view_tasks'])) }}"
                   class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            @endif

            <form action="{{ route('ordenes.index') }}" method="GET" class="op-calendario__filter-form">
                <input type="hidden" name="view" value="calendario">
                @if(request('view_tasks'))
                    <input type="hidden" name="view_tasks" value="{{ request('view_tasks') }}">
                @endif
                @if(request('user_to_view'))
                    <input type="hidden" name="user_to_view" value="{{ request('user_to_view') }}">
                @endif
                @foreach(request()->except(['matriz', 'view', 'view_tasks', 'user_to_view']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach

                <select name="matriz" class="ucrud-select">
                    <option value="">Todas las matrices</option>
                    @foreach($matrices as $matriz)
                        <option value="{{ $matriz->matriz_codigo }}" @selected(request('matriz') == $matriz->matriz_codigo)>
                            {{ $matriz->matriz_descripcion }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
            </form>

            <button id="current-week-btn" type="button" class="ucrud-btn ucrud-btn--primary ucrud-btn--sm">
                <i class="fas fa-calendar-day"></i> Hoy
            </button>
        </div>
    </div>

    @if($events->isNotEmpty())
        <div id="calendar"></div>
    @else
        <div class="ucrud-alert ucrud-alert--info mb-0" role="status">
            <span>
                @if($viewTasks ?? false)
                    No hay tareas asignadas a {{ $userToView }} en el período seleccionado.
                @elseif(request('matriz'))
                    @php
                        $matrizSeleccionada = $matrices->firstWhere('matriz_codigo', request('matriz'));
                    @endphp
                    No hay órdenes programadas para la matriz
                    {{ $matrizSeleccionada ? $matrizSeleccionada->matriz_descripcion : 'seleccionada' }}.
                @else
                    No hay órdenes programadas para mostrar en el calendario.
                @endif
            </span>
        </div>
    @endif
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.min.js'></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    
    if(calendarEl) {
        // Obtener la vista guardada del localStorage o usar por defecto
        const savedView = localStorage.getItem('calendar_view') || 'timeGridWeek';
        
        const calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'es',
            initialView: savedView,
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

                if(info.event.extendedProps) {
                    new bootstrap.Tooltip(info.el, {
                        title: `
                            <strong>${info.event.extendedProps.empresa || 'Sin empresa'}</strong><br>
                            ${info.event.extendedProps.descripcion || 'Sin descripción'}<br>
                            <small>Estado: ${info.event.extendedProps.estado || 'No especificado'}</small>
                            ${info.event.extendedProps.responsables ? 
                              `<br><small>Responsables: ${info.event.extendedProps.responsables}</small>` : ''}
                            ${info.event.extendedProps.analisis_count > 0 ? 
                              `<br><small>Análisis: ${info.event.extendedProps.analisis_count}</small>` : ''}
                        `,
                        placement: 'top',
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
            },
            viewDidMount: function(info) {
                // Guardar la vista actual en localStorage
                localStorage.setItem('calendar_view', info.view.type);
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
    if (dropdownElement && typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
        new bootstrap.Dropdown(dropdownElement);
    }
});

</script>