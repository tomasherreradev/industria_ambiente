@include('partials.operativo-calendario-assets')

<div class="ucrud-panel op-calendario tareas-calendario-wrap">
    <div class="op-calendario__header">
        <div>
            <h2 class="op-calendario__title">Calendario de OTs</h2>
        </div>
        <div class="op-calendario__toolbar">
            <button type="button" id="current-week-btn" class="ucrud-btn ucrud-btn--primary ucrud-btn--sm">
                <x-heroicon-o-calendar-days style="width: 16px; height: 16px;" />
                Hoy
            </button>
        </div>
    </div>

    @if($events->isNotEmpty())
        <div class="op-calendario__view-tabs" id="ordenes-calendar-view-tabs" role="group" aria-label="Vista del calendario">
            <button type="button" class="op-calendario__view-tab" data-cal-view="dayGridMonth">Mes</button>
            <button type="button" class="op-calendario__view-tab" data-cal-view="timeGridWeek">Semana</button>
            <button type="button" class="op-calendario__view-tab is-active" data-cal-view="timeGridDay">Día</button>
        </div>
        <div id="calendar" class="tareas-calendario-widget"></div>
    @else
        <div class="ucrud-alert ucrud-alert--info mb-0" role="status">
            <span>No hay órdenes programadas para mostrar en el calendario.</span>
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
        },
    });

    function syncMobileViewTabs(activeView) {
        const tabsRoot = document.getElementById('ordenes-calendar-view-tabs');
        if (!tabsRoot) {
            return;
        }
        tabsRoot.querySelectorAll('[data-cal-view]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.calView === activeView);
        });
    }

    calendar.render();

    const viewTabsRoot = document.getElementById('ordenes-calendar-view-tabs');
    if (viewTabsRoot) {
        viewTabsRoot.querySelectorAll('[data-cal-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const view = btn.dataset.calView;
                if (!view) {
                    return;
                }
                calendar.changeView(view);
                syncMobileViewTabs(view);
            });
        });
    }

    calendar.on('datesSet', function () {
        if (isMobileView()) {
            syncMobileViewTabs(calendar.view.type);
        }
    });

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
        calendar.setOption('initialView', isMobileView() ? 'timeGridDay' : 'timeGridWeek');
        calendar.setOption('headerToolbar', isMobileView()
            ? { left: 'prev,next', center: 'title', right: 'today' }
            : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' });
        calendar.changeView(isMobileView() ? 'timeGridDay' : 'timeGridWeek');
        if (isMobileView()) {
            syncMobileViewTabs(calendar.view.type);
        }
    });
});
</script>

<style>
    .badge-analisis-count {
        font-size: 0.7em;
        margin-left: 5px;
        vertical-align: middle;
    }
</style>
