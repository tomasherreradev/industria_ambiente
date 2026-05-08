{{-- Incluido dentro de ordenes/index: no usar @extends aquí (rompe @section del layout padre). --}}
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
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
        padding: 2px 4px;
    }
    .fc-event-warning { background-color: #ffc107; border-color: #ffc107; }
    .fc-event-success { background-color: #28a745; border-color: #28a745; }
    .fc-event-info { background-color: #17a2b8; border-color: #17a2b8; }
    .fc-event-danger { background-color: #dc3545; border-color: #dc3545; }
    .fc-event-primary { background-color: #0d6efd; border-color: #0d6efd; }
    .fc-event-secondary { background-color: #6c757d; border-color: #6c757d; }
    .view-switcher {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .badge-analisis-count {
        font-size: 0.7em;
        margin-left: 5px;
        vertical-align: middle;
    }
    .fc-toolbar-title {
        font-size: 1.25em;
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
    }


    .dropdown-menu {
        max-height: 300px;
        overflow-y: auto;
    }

</style>

<div id="calendar-container">
    <div class="calendar-header">
        <div>
            <h2 class="mb-0">
                {{-- @if($viewTasks)
                    Tareas de: <strong>{{ $userToView }}</strong>
                    <small class="text-muted">({{ count($events) }} tareas)</small>
                @else --}}
                    Calendario de Órdenes
                    @if(request('matriz'))
                        <small class="text-muted">
                            ({{ collect($events)->where('cotio_subitem', 0)->count() }}
                            {{ collect($events)->where('cotio_subitem', 0)->count() === 1 ? 'orden' : 'ordenes' }})
                        </small>                        
                    @endif
                {{-- @endif --}}
            </h2>
        </div>
        
        <div class="view-switcher">
            @if($viewTasks)
                <a href="{{ route('ordenes.index', ['view' => 'calendario'] + request()->except(['user_to_view', 'view_tasks'])) }}" 
                   class="btn btn-sm btn-outline-secondary me-2">
                   <i class="fas fa-arrow-left"></i> Volver
                </a>
            @endif

            {{-- añadir filtro por matriz en el calendario --}}
            <form action="{{ route('ordenes.index') }}" method="GET" class="d-flex align-items-center gap-2" style="margin: 0px !important;">
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
                
                <select name="matriz" class="form-select form-select-sm" style="width: 100%; max-width: 150px;">
                    <option value="">Todas las matrices</option>
                    @foreach($matrices as $matriz)
                        <option value="{{ $matriz->matriz_codigo }}" {{ request('matriz') == $matriz->matriz_codigo ? 'selected' : '' }}>
                            {{ $matriz->matriz_descripcion }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
            </form>
          
          
            
            <button id="current-week-btn" class="btn btn-sm btn-primary">
                <i class="fas fa-calendar-day"></i> Hoy
            </button>
            
            <div class="btn-group">
                <a href="{{ route('ordenes.index', ['view' => 'lista'] + request()->except('view')) }}" 
                   class="btn btn-sm {{ $viewType === 'lista' ? 'btn-primary' : 'btn-outline-secondary' }}">
                   <i class="fas fa-list"></i> Lista
                </a>
                <a href="{{ route('ordenes.index', ['view' => 'calendario'] + request()->except('view')) }}" 
                   class="btn btn-sm {{ $viewType === 'calendario' ? 'btn-primary' : 'btn-outline-secondary' }}">
                   <i class="fas fa-calendar"></i> Calendario
                </a>
            </div>
            
            @if(auth()->user()->usu_nivel >= 900)
                <div class="dropdown ms-2">
                    {{-- <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                            type="button" 
                            id="userDropdownMenu" 
                            data-bs-toggle="dropdown" 
                            aria-expanded="false">
                        <i class="fas fa-user"></i> Ver tareas de
                    </button> --}}
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdownMenu">
                        @foreach($usuarios as $usuario)
                        <li>
                            <a class="dropdown-item d-flex justify-content-between align-items-center" 
                            href="{{ route('ordenes.index', [
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

    <div id="calendar"></div>
    
    @if($events->isEmpty())
    <div class="alert alert-info mt-3">
        @if($viewTasks)
            <i class="fas fa-info-circle me-2"></i>
            No hay tareas asignadas a <strong>{{ $userToView }}</strong> en el período seleccionado.
        @else
            <i class="fas fa-info-circle me-2"></i>
            @if(request('matriz'))
                @php
                    $matrizSeleccionada = $matrices->firstWhere('matriz_codigo', request('matriz'));
                @endphp
                No hay muestras programadas para la matriz 
                <strong>{{ $matrizSeleccionada ? $matrizSeleccionada->matriz_descripcion : 'seleccionada' }}</strong> 
                en el período actual.
            @else
                No hay muestras programadas para mostrar en el calendario.
            @endif
        @endif
        <br>
        <small class="text-muted">El calendario se mostrará cuando haya eventos programados en las fechas visibles.</small>
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