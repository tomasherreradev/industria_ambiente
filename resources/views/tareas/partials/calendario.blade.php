<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">

<div class="tareas-calendario-wrap">
    <div class="calendar-header d-none d-md-flex">
        <h2 class="mb-0">Calendario de Tareas de Muestreo</h2>
        <div class="view-switcher">
            <button type="button" id="current-week-btn" class="btn btn-sm btn-primary">
                <x-heroicon-o-calendar-days style="width: 16px; height: 16px;" class="me-1" />
                Hoy
            </button>
            <a href="{{ route('mis-tareas', ['view' => 'lista']) }}" class="btn btn-sm btn-outline-secondary">
                <x-heroicon-o-list-bullet style="width: 16px; height: 16px;" class="me-1" />
                Vista de Lista
            </a>
        </div>
    </div>

    @if($events->isNotEmpty())
        <div id="calendar" class="tareas-calendario-widget"></div>
    @else
        <div class="alert alert-info mb-0">
            No hay tareas de muestreo programadas para mostrar en el calendario.
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const mobileQuery = window.matchMedia('(max-width: 767.98px)');

    function isMobileView() {
        return mobileQuery.matches;
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'es',
        initialView: isMobileView() ? 'timeGridDay' : 'timeGridWeek',
        height: isMobileView() ? 'auto' : 800,
        contentHeight: isMobileView() ? 520 : undefined,
        expandRows: isMobileView(),
        nowIndicator: true,
        allDaySlot: true,
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        slotLabelFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        },
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        },
        headerToolbar: isMobileView()
            ? {
                left: 'prev,next',
                center: 'title',
                right: 'today',
            }
            : {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay',
            },
        footerToolbar: isMobileView()
            ? {
                center: 'dayGridMonth,timeGridWeek,timeGridDay',
            }
            : false,
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día',
        },
        events: @json($events),
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            if (info.event.url) {
                window.location.href = info.event.url;
            }
        },
        eventDidMount: function (info) {
            if (info.event.extendedProps.analisis_count > 0) {
                const titleEl = info.el.querySelector('.fc-event-title');
                if (titleEl) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-primary badge-analisis-count';
                    badge.textContent = info.event.extendedProps.analisis_count;
                    titleEl.appendChild(badge);
                }
            }

            const responsables = info.event.extendedProps.responsables || [];
            const responsablesHTML = responsables.length > 0
                ? `<div class="responsables-list"><strong>Muestreadores:</strong> ${responsables}</div>`
                : '';

            new bootstrap.Tooltip(info.el, {
                title: `
                    <div class="fc-tooltip">
                        <strong>${info.event.extendedProps.empresa}</strong><br>
                        ${info.event.extendedProps.descripcion}<br>
                        <small>Estado: ${info.event.extendedProps.estado}</small>
                        ${info.event.extendedProps.analisis_count > 0
                            ? `<br><small>Tareas asociadas: ${info.event.extendedProps.analisis_count}</small>`
                            : ''}
                        ${responsablesHTML}
                    </div>
                `,
                placement: 'top',
                html: true,
                container: 'body',
            });
        },
    });

    calendar.render();

    const goToday = function () {
        calendar.today();
    };

    const desktopTodayBtn = document.getElementById('current-week-btn');
    if (desktopTodayBtn) {
        desktopTodayBtn.addEventListener('click', goToday);
    }

    mobileQuery.addEventListener('change', function () {
        calendar.setOption('height', isMobileView() ? 'auto' : 800);
        calendar.setOption('contentHeight', isMobileView() ? 520 : undefined);
        calendar.setOption('expandRows', isMobileView());
        calendar.setOption('headerToolbar', isMobileView()
            ? { left: 'prev,next', center: 'title', right: 'today' }
            : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' });
        calendar.setOption('footerToolbar', isMobileView()
            ? { center: 'dayGridMonth,timeGridWeek,timeGridDay' }
            : false);
        calendar.changeView(isMobileView() ? 'timeGridDay' : 'timeGridWeek');
    });
});
</script>

<style>
    .tareas-calendario-wrap {
        max-width: 1200px;
        margin: 0 auto;
    }

    .tareas-calendario-wrap .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 15px 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .tareas-calendario-widget {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        padding: 0.75rem;
    }

    .view-switcher {
        display: flex;
        gap: 10px;
    }

    .fc-event {
        cursor: pointer;
        font-size: 0.85em;
        padding: 2px 4px;
    }

    .fc-event-warning { background-color: #ffc107 !important; border-color: #ffc107 !important; color: #212529 !important; }
    .fc-event-success { background-color: #28a745 !important; border-color: #28a745 !important; }
    .fc-event-info { background-color: #17a2b8 !important; border-color: #17a2b8 !important; }
    .fc-event-danger { background-color: #dc3545 !important; border-color: #dc3545 !important; }
    .fc-event-primary { background-color: #4e73df !important; border-color: #4e73df !important; }

    .badge-analisis-count {
        font-size: 0.7em;
        margin-left: 5px;
        vertical-align: middle;
    }

    .responsables-list {
        font-size: 0.85em;
        margin-top: 3px;
    }

    .fc-tooltip {
        max-width: 300px;
    }
</style>
