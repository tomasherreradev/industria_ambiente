@include('partials.operativo-calendario-assets')

@php
    $cotizaciones = $cotizaciones ?? collect();
    $startOfWeek = $startOfWeek ?? now()->startOfWeek();
    $viewType = request('view', 'calendario');
    $viewTasks = request('view_tasks', false);
    $userToView = $viewTasks ? ($userToView ?? 'Usuario seleccionado') : null;
    $sortedCotizaciones = $cotizaciones->flatten()->sortBy('coti_fechaalta')->groupBy(function ($cotizacion) {
        return \Carbon\Carbon::parse($cotizacion->coti_fechaalta ?? $cotizacion->coti_fechafin)->format('Y-m-d');
    });
@endphp

<div class="ucrud-panel op-calendario" id="calendar-container">
    <div class="op-calendario__header">
        <div>
            <h2 class="op-calendario__title">
                @if($viewTasks)
                    Cotizaciones de {{ $userToView }}
                @else
                    Calendario de cotizaciones
                @endif
            </h2>
            @if($viewTasks)
                <span class="op-calendario__meta">{{ $cotizaciones->flatten()->count() }} cotizaciones</span>
            @endif
        </div>

        <div class="op-calendario__toolbar">
            @if($viewTasks)
                <a href="{{ route('cotizaciones.index', ['view' => 'calendario'] + request()->except(['user_to_view', 'view_tasks'])) }}"
                   class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            @endif

            <button id="current-week-btn" type="button" class="ucrud-btn ucrud-btn--primary ucrud-btn--sm">
                <i class="fas fa-calendar-day"></i> Hoy
            </button>
        </div>
    </div>

    @if($sortedCotizaciones->isNotEmpty())
        <div id="calendar"></div>
    @else
        <div class="ucrud-alert ucrud-alert--info mb-0" role="status">
            <span>{{ $viewTasks ? 'No hay cotizaciones asignadas a este usuario en el período seleccionado.' : 'No hay cotizaciones programadas para mostrar en el calendario.' }}</span>
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');

    if (!calendarEl) {
        return;
    }

    const events = [
        @foreach($sortedCotizaciones as $date => $dayCotizaciones)
            @foreach($dayCotizaciones as $cotizacion)
                @php
                    $estado = trim($cotizacion->coti_estado);
                    $statusClass = ['A' => 'success', 'E' => 'warning', 'S' => 'danger'][$estado] ?? 'secondary';
                    $empresa = \App\Support\CotizacionClienteEtiqueta::paraLista($cotizacion);
                    if ($empresa === '—') {
                        $empresa = 'Sin empresa';
                    }
                    $localidadPartes = \App\Support\CotizacionClienteEtiqueta::direccionDestinatarioPartes($cotizacion);
                    $localidad = $localidadPartes['localidad'] !== '' ? $localidadPartes['localidad'] : 'Sin localidad';
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
                        empresa: @json($empresa),
                        localidad: @json($localidad),
                        contacto: @json($contacto),
                        importe: '{{ $importe }}',
                        fechaFin: '{{ $cotizacion->coti_fechafin }}',
                        fechaAlta: '{{ $fechaAlta }}',
                        statusClass: '{{ $statusClass }}'
                    },
                    url: '{{ url('/cotizaciones/' . $cotizacion->coti_num) }}',
                    classNames: ['fc-event-' + '{{ $statusClass }}']
                },
            @endforeach
        @endforeach
    ];

    const calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'es',
        initialView: 'timeGridWeek',
        initialDate: '{{ $startOfWeek->format('Y-m-d') }}',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        allDaySlot: false,
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
            new bootstrap.Tooltip(info.el, {
                title: `<strong>${props.empresa}</strong><br>Estado: ${props.status}<br>Fecha alta: ${props.fechaAlta}<br>Fecha fin: ${props.fechaFin}<br>Localidad: ${props.localidad}<br>Contacto: ${props.contacto}<br>Importe: $${props.importe}`,
                placement: 'top',
                html: true,
                container: 'body'
            });
        }
    });

    calendar.render();

    document.getElementById('current-week-btn')?.addEventListener('click', function() {
        calendar.today();
    });
});
</script>
