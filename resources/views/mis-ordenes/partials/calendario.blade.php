@extends('layouts.app')

@section('title', 'Calendario de Muestras')
@section('content')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<style>
    #calendar-container {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }
    #calendar {
        height: 800px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 15px 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .fc-event {
        cursor: pointer;
        font-size: 0.85em;
        padding: 2px 4px;
    }
    .fc-event-warning { background-color: #ffc107; border-color: #ffc107; }
    .fc-event-success { background-color: #28a745; border-color: #28a745; }
    .fc-event-info { background-color: #17a2b8; border-color: #17a2b8; }
    .fc-event-danger { background-color: #dc3545; border-color: #dc3545; }
    .fc-event-primary { background-color: #0d6efd; border-color: #0d6efd; }
    .view-switcher {
        display: flex;
        gap: 10px;
    }
    .badge-analisis-count {
        font-size: 0.7em;
        margin-left: 5px;
        vertical-align: middle;
    }
</style>

<div id="calendar-container">
    <div class="calendar-header">
        <h2 class="mb-0">Calendario de Muestras</h2>
        <div class="view-switcher">
            <button id="current-week-btn" class="btn btn-sm btn-primary">
                <i class="fas fa-calendar-day"></i> Hoy
            </button>
            <a href="{{ route('mis-ordenes', ['view' => 'lista']) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-list"></i> Vista de Lista
            </a>
        </div>
    </div>

    @if($events->isNotEmpty())
    <div id="calendar"></div>
    @else
    <div class="alert alert-info">
        No hay muestras programadas para mostrar en el calendario.
    </div>
    @endif
</div>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
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
            events: @json($events),
            eventClick: function(info) {
                window.location.href = info.event.url;
            },
            eventDidMount: function(info) {
                // Añadir contador de análisis si existe
                if(info.event.extendedProps.analisis_count > 0) {
                    const titleEl = info.el.querySelector('.fc-event-title');
                    if(titleEl) {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-primary badge-analisis-count';
                        badge.textContent = info.event.extendedProps.analisis_count;
                        titleEl.appendChild(badge);
                    }
                }

                new bootstrap.Tooltip(info.el, {
                    title: `
                        <strong>${info.event.extendedProps.empresa}</strong><br>
                        ${info.event.extendedProps.descripcion}<br>
                        <small>OT: ${info.event.extendedProps.otn != null && info.event.extendedProps.otn !== '' ? info.event.extendedProps.otn : '—'}</small><br>
                        <small>Estado: ${info.event.extendedProps.estado}</small>
                        ${info.event.extendedProps.analisis_count > 0 ? 
                          `<br><small>Análisis: ${info.event.extendedProps.analisis_count}</small>` : ''}
                    `,
                    placement: 'top',
                    html: true,
                    container: 'body'
                });
            }
        });
        
        calendar.render();
        
        document.getElementById('current-week-btn').addEventListener('click', function() {
            calendar.today();
        });
    }
});
</script>
@endsection