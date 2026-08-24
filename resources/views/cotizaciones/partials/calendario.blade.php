@extends('layouts.app')

@section('title', 'Calendario de Cotizaciones')

@section('content')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

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
        padding: 5px 8px;
        border-radius: 4px;
        margin: 2px;
    }
    .fc-event-success { background-color: #28a745; border-color: #28a745; color: white; }
    .fc-event-warning { background-color: #ffc107; border-color: #ffc107; color: black; }
    .fc-event-danger { background-color: #dc3545; border-color: #dc3545; color: white; }
    .fc-event-secondary { background-color: #6c757d; border-color: #6c757d; color: white; }
    .view-switcher {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .quick-filter .btn {
        font-size: 0.85em;
    }
    .fc-toolbar-title {
        font-size: 1.25em;
    }
    .badge-count {
        font-size: 0.7em;
        margin-left: 5px;
        vertical-align: middle;
    }
    .user-selector {
        position: relative;
        display: inline-block;
    }
    .user-selector-btn {
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 5px 10px;
        cursor: pointer;
    }
    .user-selector-dropdown {
        position: absolute;
        right: 0;
        z-index: 1000;
        min-width: 200px;
        padding: 5px 0;
        margin: 2px 0 0;
        font-size: 14px;
        text-align: left;
        list-style: none;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid rgba(0,0,0,.15);
        border-radius: 4px;
        box-shadow: 0 6px 12px rgba(0,0,0,.175);
    }
    .user-selector-item {
        display: block;
        padding: 3px 20px;
        clear: both;
        font-weight: 400;
        line-height: 1.42857143;
        color: #333;
        white-space: nowrap;
    }
    .user-selector-item:hover {
        background-color: #f5f5f5;
    }
    @media (max-width: 768px) {
        #calendar {
            height: 600px;
        }
        .calendar-header {
            flex-direction: column;
            gap: 10px;
        }
        .view-switcher {
            width: 100%;
            justify-content: space-between;
        }
        .quick-filter {
            width: 100%;
        }
        .fc .fc-toolbar {
            flex-direction: column;
            gap: 10px;
        }
    }
    .dropdown-menu {
        max-height: 300px;
        overflow-y: auto;
    }
    .tooltip-inner {
        max-width: 300px;
        text-align: left;
    }
</style>

@php
    $cotizaciones = $cotizaciones ?? collect();
    $startOfWeek = $startOfWeek ?? now()->startOfWeek();
    $endOfWeek = $startOfWeek->copy()->endOfWeek();
    $viewType = request('view', 'calendario');
    $viewTasks = request('view_tasks', false);
    $userToView = $viewTasks ? ($userToView ?? 'Usuario seleccionado') : null;
    $usuarios = $usuarios ?? collect();
    // Flatten and sort cotizaciones by coti_fechaalta, then group by date
    $sortedCotizaciones = $cotizaciones->flatten()->sortBy('coti_fechaalta')->groupBy(function ($cotizacion) {
        return \Carbon\Carbon::parse($cotizacion->coti_fechaalta ?? $cotizacion->coti_fechafin)->format('Y-m-d');
    });
@endphp

<div id="calendar-container">
    <div class="calendar-header">
        <div>
            <h2 class="mb-0">
                @if($viewTasks)
                    Cotizaciones de: <strong>{{ $userToView }}</strong>
                    <small class="text-muted">({{ $cotizaciones->flatten()->count() }} cotizaciones)</small>
                @else
                    Calendario de Cotizaciones
                @endif
            </h2>
        </div>

        <div class="view-switcher">
            @if($viewTasks)
                <a href="{{ route('cotizaciones.index', ['view' => 'calendario'] + request()->except(['user_to_view', 'view_tasks'])) }}"
                   class="btn btn-sm btn-outline-secondary me-2">
                   <i class="fas fa-arrow-left"></i> Volver
                </a>
            @endif

            <button id="current-week-btn" class="btn btn-sm btn-primary">
                <i class="fas fa-calendar-day"></i> Hoy
            </button>

            <div class="btn-group">
                <a href="{{ route('cotizaciones.index', ['view' => 'lista'] + request()->except('view')) }}"
                   class="btn btn-sm {{ $viewType === 'lista' ? 'btn-primary' : 'btn-outline-secondary' }}">
                   <i class="fas fa-list"></i> Lista
                </a>
                <a href="{{ route('cotizaciones.index', ['view' => 'calendario'] + request()->except('view')) }}"
                   class="btn btn-sm {{ $viewType === 'calendario' ? 'btn-primary' : 'btn-outline-secondary' }}">
                   <i class="fas fa-calendar"></i> Calendario
                </a>
            </div>
        </div>
    </div>


    @if($sortedCotizaciones->isNotEmpty())
    <div id="calendar"></div>
    @else
    <div class="alert alert-info">
        @if($viewTasks)
            No hay cotizaciones asignadas a este usuario en el período seleccionado.
        @else
            No hay cotizaciones programadas para mostrar en el calendario.
        @endif
    </div>
    @endif
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.min.js'></script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');

    if (calendarEl) {
        // Prepare events from PHP
        const events = [
            @foreach($sortedCotizaciones as $date => $dayCotizaciones)
                @foreach($dayCotizaciones as $cotizacion)
                    @php
                        // @dd($cotizacion);
                        $estado = trim($cotizacion->coti_estado);
                        $statusClass = [
                            'A' => 'success',
                            'E' => 'warning',
                            'S' => 'danger'
                        ][$estado] ?? 'secondary';
                        $empresa = \App\Support\CotizacionClienteEtiqueta::paraLista($cotizacion);
                        if ($empresa === '—') {
                            $empresa = 'Sin empresa';
                        }
                        $localidad = $cotizacion->coti_localidad ?? 'Sin localidad';
                        $contacto = $cotizacion->coti_contacto ?? 'Sin contacto';
                        $importe = number_format(floatval($cotizacion->coti_importe), 2, ',', '.');
                        $fechaAlta = $cotizacion->coti_fechaalta ?? $cotizacion->coti_fechafin ?? $date;
                    @endphp
                    {
                        id: '{{ $cotizacion->coti_num }}',
                        title: '#{{ $cotizacion->coti_num }} - {{ Str::limit($empresa, 25) }}',
                        start: '{{ $date }}',
                        extendedProps: {
                            status: '{{ $estado }}',
                            empresa: '{{ $empresa }}',
                            localidad: '{{ $localidad }}',
                            contacto: '{{ $contacto }}',
                            importe: '{{ $importe }}',
                            fechaFin: '{{ $cotizacion->coti_fechafin }}',
                            fechaAlta: '{{ $fechaAlta }}',
                            statusClass: '{{ $statusClass }}'
                        },
                        url: '{{ url("/cotizaciones/" . $cotizacion->coti_num) }}',
                        classNames: ['fc-event-' + '{{ $statusClass }}']
                    },
                @endforeach
            @endforeach
        ];

        const calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'es',
            initialView: 'timeGridWeek',
            initialDate: '{{ $startOfWeek->format("Y-m-d") }}',
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
            eventOrder: 'fechaAlta', // Sort events by fechaAlta within each day
            eventDisplay: 'block',
            dayMaxEvents: true,
            events: events,
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                if (info.event.url) {
                    window.location.href = info.event.url;
                }
            },
            eventDidMount: function(info) {
                info.el.style.height = 'auto';
                const props = info.event.extendedProps;
                const cotizacionesCount = {{ $dayCotizaciones->count() }};
                if (cotizacionesCount > 1) {
                    const titleEl = info.el.querySelector('.fc-event-title');
                    if (titleEl) {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-light text-dark badge-count';
                        badge.textContent = cotizacionesCount;
                        titleEl.appendChild(badge);
                    }
                }
                new bootstrap.Tooltip(info.el, {
                    title: `
                        <strong>${props.empresa}</strong><br>
                        Estado: <span class="badge bg-${props.statusClass}">${props.status}</span><br>
                        Fecha alta: ${props.fechaAlta}<br>
                        Fecha fin: ${props.fechaFin}<br>
                        Localidad: ${props.localidad}<br>
                        Contacto: ${props.contacto}<br>
                        Importe: $${props.importe}
                    `,
                    placement: 'top',
                    html: true,
                    container: 'body'
                });
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

        // Quick filters
        const filterButtons = document.querySelectorAll('[data-filter]');
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                filterButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                const filter = this.dataset.filter;
                calendar.getEvents().forEach(event => {
                    const show = filter === 'all' ||
                        (filter === 'today' && event.start.toISOString().split('T')[0] === new Date().toISOString().split('T')[0]) ||
                        (filter === 'pending' && event.extendedProps.status === 'E') ||
                        (filter === 'completed' && event.extendedProps.status === 'A');
                    event.setProp('display', show ? 'auto' : 'none');
                });
            });
        });

        // Today button
        document.getElementById('current-week-btn').addEventListener('click', function() {
            calendar.today();
        });
    }

    // Initialize Bootstrap dropdown
    const dropdownElement = document.getElementById('userDropdownMenu');
    if (dropdownElement) {
        new bootstrap.Dropdown(dropdownElement);
    }
});
</script>
@endsection