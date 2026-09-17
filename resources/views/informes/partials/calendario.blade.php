@extends('layouts.app')

@section('content')

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />

<div id="calendar-container">
    <div class="calendar-header mb-4">
        <div>
            <h2 class="mb-0">
                Calendario de Informes
                <small class="text-muted">({{ count($events) }} eventos)</small>
            </h2>
        </div>
        
        <div class="view-switcher">
            <button id="current-week-btn" class="btn btn-sm btn-primary me-2">
                <i class="fas fa-calendar-day"></i> Hoy
            </button>
            
            <div class="btn-group">
                <a href="{{ route('informes.index', ['view' => 'lista'] + request()->except('view')) }}" 
                   class="btn btn-sm {{ $viewType === 'lista' ? 'btn-primary' : 'btn-outline-secondary' }}">
                   <i class="fas fa-list"></i> Lista
                </a>
                <a href="{{ route('informes.index', ['view' => 'calendario'] + request()->except('view')) }}" 
                   class="btn btn-sm {{ $viewType === 'calendario' ? 'btn-primary' : 'btn-outline-secondary' }}">
                   <i class="fas fa-calendar"></i> Calendario
                </a>
            </div>
        </div>
    </div>

    <div id="calendar"></div>
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
                        badge.className = 'badge bg-light text-dark badge-analisis-count ms-2';
                        badge.textContent = info.event.extendedProps.analisis_count;
                        titleEl.appendChild(badge);
                    }
                }

                // Agregar clase según el tipo de informe
                if(info.event.extendedProps.tipo_informe === 'final') {
                    info.el.classList.add('informe-final');
                } else {
                    info.el.classList.add('informe-parcial');
                }

                console.log(info.event.extendedProps);

                // Tooltip con más información
                if(info.event.extendedProps) {
                    new bootstrap.Tooltip(info.el, {
                        title: `
                            <strong>${info.event.extendedProps.empresa || 'Sin empresa'}</strong><br>
                            ${info.event.extendedProps.muestra || 'Sin descripción'}<br>
                            <small>Identificación: ${(info.event.extendedProps.identificacion || '').trim() !== '' ? info.event.extendedProps.identificacion : '—'}</small><br>
                            <small>Legislación (categoría): ${info.event.extendedProps.ley_categoria || '—'}</small><br>
                            ${info.event.extendedProps.instancia || 'Sin instancia'}
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
</script>

<style>
    #calendar-container {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .view-switcher {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .informe-final {
        background-color: #28a745;
        border-color: #218838;
    }
    
    .informe-parcial {
        background-color: #ffc107;
        border-color: #e0a800;
        color: #212529;
    }
    
    .badge-analisis-count {
        font-size: 0.7em;
        padding: 0.2em 0.4em;
    }
    
    @media (max-width: 768px) {
        .calendar-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .view-switcher {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>
@endsection