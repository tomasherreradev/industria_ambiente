<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización #{{ $cotizacion->coti_num }}</title>
    <style>
        @page {
            margin: 1.5cm 1cm 1.5cm 1cm;
        }
        body {
            font-family: 'Calibri', Arial, sans-serif;
            font-size: 9pt;
            color: #333;
            margin: 0;
            line-height: 1.3;
        }
        .page {
            page-break-after: always;
        }
        .page:last-of-type {
            page-break-after: auto;
        }
        .wrapper {
            padding: 0.3cm 0 0 0;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            border-bottom: 1px solid #0d6efd;
            padding-bottom: 6px;
        }
        .logo {
            width: 120px;
        }
        .logo img {
            max-width: 100%;
            height: auto;
        }
        .company-info {
            text-align: right;
            font-size: 8pt;
        }
        .company-info h1 {
            font-size: 12pt;
            margin: 0 0 2px 0;
            color: #0d6efd;
            text-transform: uppercase;
        }
        .company-info p {
            margin: 1px 0;
        }
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #0d6efd;
            margin: 10px 0 5px;
            text-transform: uppercase;
        }
        .info-grid {
            width: 100%;
            border: 1px solid #e0e0e0;
            border-collapse: collapse;
            font-size: 8pt;
        }
        .info-grid td {
            padding: 4px 6px;
            border: 1px solid #e0e0e0;
            vertical-align: top;
        }
        .info-grid .label {
            font-weight: bold;
            width: 25%;
            color: #555;
            font-size: 7.5pt;
        }
        .info-grid .value {
            width: 25%;
            font-size: 8pt;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-top: 5px;
            table-layout: fixed;
        }
        .items-table th {
            background: #f2f5ff;
            color: #0d2b5f;
            padding: 4px 5px;
            border: 1px solid #d0d7eb;
            text-align: left;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .items-table td {
            border: 1px solid #d0d7eb;
            padding: 3px 5px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            max-width: 0;
        }
        .items-table tr:nth-child(even) td {
            background: #fafbff;
        }
        .descripcion-item {
            font-weight: bold;
            margin-bottom: 2px;
            font-size: 8.5pt;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        .component-inline {
            font-size: 7.5pt;
            color: #555;
            margin-top: 2px;
            padding-left: 8px;
        }
        .component-inline span {
            margin-right: 6px;
        }
        .resumen-table {
            width: 50%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-top: 8px;
        }
        .resumen-table th,
        .resumen-table td {
            border: 1px solid #d0d7eb;
            padding: 4px 6px;
        }
        .resumen-table th {
            background: #f2f5ff;
            text-align: left;
            color: #0d2b5f;
            font-size: 8pt;
        }
        .resumen-table td {
            text-align: right;
        }
        .notes {
            font-size: 8pt;
            line-height: 1.4;
            margin-top: 8px;
        }
        .notes ol {
            padding-left: 16px;
            margin: 4px 0;
        }
        .notes li {
            margin-bottom: 3px;
        }
        .billing-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        .billing-table th,
        .billing-table td {
            border: 1px solid #d0d7eb;
            padding: 4px 6px;
        }
        .billing-table th {
            background: #f2f5ff;
            color: #0d2b5f;
            text-align: left;
            font-size: 7.5pt;
        }
        .summary-highlight {
            background: #f8fbff;
            border: 1px solid #d0d7eb;
            border-radius: 4px;
            padding: 6px 10px;
            margin-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8.5pt;
        }
        .summary-highlight strong {
            font-size: 10pt;
            color: #0d2b5f;
        }
        footer {
            margin-top: 12px;
            font-size: 7pt;
            text-align: center;
            color: #777;
        }
        .compact-text {
            font-size: 7.5pt;
            color: #666;
        }
        .note-inline {
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px dashed #d0d7eb;
            font-size: 7.5pt;
            color: #555;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            max-width: 100%;
        }
        .note-label {
            font-weight: bold;
            color: #0d2b5f;
            margin-right: 4px;
        }
        .note-text {
            font-style: italic;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            display: inline-block;
            max-width: 100%;
        }
    </style>
</head>
<body>
@php
    $formatCurrency = function ($value) {
        return '$ ' . number_format((float) $value, 2, ',', '.');
    };
    $formatDate = function ($date) {
        if (!$date) {
            return '—';
        }
        return \Carbon\Carbon::parse($date)->format('d/m/Y');
    };
    $cliente = $cotizacion->cliente;
    $contacto = trim((string) ($cotizacion->coti_contacto ?? ''));
    $correo = trim((string) ($cotizacion->coti_mail1 ?? optional($cliente)->cli_email ?? ''));
    $telefono = trim((string) ($cotizacion->coti_telefono ?? optional($cliente)->cli_telefono ?? ''));
@endphp

<div class="page">
    <div class="wrapper">
        <header>
            <div class="logo">
                @if(file_exists(public_path('assets/img/logo.png')))
                    <img src="{{ public_path('assets/img/logo.png') }}" alt="Industria y Ambiente S.A.">
                @else
                    <strong>Industria y Ambiente S.A.</strong>
                @endif
            </div>
            <div class="company-info">
                <h1>Industria y Ambiente S.A.</h1>
                <p>Cotización: <strong>#{{ $cotizacion->coti_num }}</strong></p>
                <p>Fecha: {{ $formatDate($cotizacion->coti_fechaalta) }}</p>
            </div>
        </header>

        <h2 class="section-title">Datos del Cliente</h2>
        <table class="info-grid">
            <tr>
                @if($tieneEmpresaRelacionada && $empresaRelacionada)
                        <td class="label">Para</td>
                        <td class="value">{{ $empresaRelacionada['razon_social'] }}</td>
                @elseif(!empty($cotizacion->coti_para))
                        <td class="label">Para</td>
                        <td class="value">{{ $cotizacion->coti_para}}</td>
                @else 
                    <td class="label">Razón Social</td>
                    <td class="value">{{ trim((string) ($cotizacion->coti_empresa ?? optional($cliente)->cli_razonsocial ?? '')) }}</td>
                @endif
                
                <td class="label">CUIT</td>
                <td class="value">{{ trim((string) ($cotizacion->coti_cuit ?? optional($cliente)->cli_cuit ?? '')) ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Dirección</td>
                <td class="value">{{ trim((string) ($cotizacion->coti_direccioncli ?? optional($cliente)->cli_direccion ?? '')) }}</td>
                <td class="label">Localidad</td>
                <td class="value">{{ trim((string) ($cotizacion->coti_localidad ?? optional($cliente)->cli_localidad ?? '')) }}</td>
            </tr>
            <tr>
                <td class="label">Sucursal / Establecimiento</td>
                <td class="value">{{ trim((string) ($cotizacion->coti_establecimiento ?? '')) ?: '—' }}</td>
                <td class="label">Código Postal</td>
                <td class="value">{{ trim((string) ($cotizacion->coti_codigopostal ?? optional($cliente)->cli_codigopostal ?? '')) ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Contacto</td>
                <td class="value">{{ $contacto ?: '—' }}</td>
                <td class="label">Correo</td>
                <td class="value">{{ $correo ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Teléfono</td>
                <td class="value">{{ $telefono ?: '—' }}</td>
                <td class="label">Matriz</td>
                <td class="value">{{ optional($cotizacion->matriz)->matriz_descripcion ?? '—' }}</td>
            </tr>
            @if(!empty($cliente->cli_partido))
            <tr>
                <td class="label">Partido</td>
                <td class="value">{{ trim((string) $cliente->cli_partido) }}</td>
                <td></td>
                <td></td>
            </tr>
            @endif
        </table>

        @if($tieneEmpresaRelacionada && $empresaRelacionada)
        <h2 class="section-title">Empresa Relacionada</h2>
        <table class="info-grid">
            <tr>
                <td class="label">Razón Social</td>
                <td class="value">{{ $empresaRelacionada['razon_social'] ?: '—' }}</td>
                <td class="label">CUIT</td>
                <td class="value">{{ $empresaRelacionada['cuit'] ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Direcciones</td>
                <td class="value">{{ $empresaRelacionada['direcciones'] ?: '—' }}</td>
                <td class="label">Localidad</td>
                <td class="value">{{ $empresaRelacionada['localidad'] ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Partido</td>
                <td class="value">{{ $empresaRelacionada['partido'] ?: '—' }}</td>
                <td class="label">Contacto</td>
                <td class="value">{{ $empresaRelacionada['contacto'] ?: '—' }}</td>
            </tr>
        </table>
        @endif

        @if($cotizacion->coti_cadena_custodia || $cotizacion->coti_muestreo)
        <h2 class="section-title">Características del Servicio</h2>
        <table class="info-grid">
            <tr>
                @if($cotizacion->coti_cadena_custodia)
                    <td class="label">Cadena de Custodia</td>
                    <td class="value">Requerida</td>
                @else
                    <td class="label">Cadena de Custodia</td>
                    <td class="value">No requerida</td>
                @endif
                @if($cotizacion->coti_muestreo)
                    <td class="label">Servicio de Muestreo</td>
                    <td class="value">Requerido</td>
                @else
                    <td class="label">Servicio de Muestreo</td>
                    <td class="value">No requerido</td>
                @endif
            </tr>
        </table>
        @endif

        <h2 class="section-title">Detalle de Ensayos y Componentes</h2>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">Item</th>
                    <th style="width: 50%;">Descripción</th>
                    <th style="width: 8%; text-align: right;">Cant.</th>
                    <th style="width: 18%; text-align: right;">Precio Unit.</th>
                    <th style="width: 19%; text-align: right;">Importe</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>#{{ $item['item'] }}</td>
                        <td style="word-wrap: break-word; overflow-wrap: break-word; word-break: break-word;">
                            <div class="descripcion-item">{{ $item['descripcion'] }}</div>
                            @if($item['componentes']->isNotEmpty())
                                @foreach($item['componentes'] as $componente)
                                    <div class="component-inline">
                                        <span>• {{ $componente['descripcion'] }}</span>
                                        @if($componente['metodo'])
                                            <span class="compact-text">[{{ $componente['metodo'] }}]</span>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                            @if(!empty($item['notas']))
                                @foreach($item['notas'] as $nota)
                                    <div class="note-inline">
                                        <span class="note-label">Nota:</span>
                                        <span class="note-text">{{ $nota['contenido'] ?? '' }}</span>
                                    </div>
                                @endforeach
                            @endif
                        </td>
                        <td style="text-align: right;">{{ number_format($item['cantidad'], 2, ',', '.') }}</td>
                        <td style="text-align: right;">{{ $formatCurrency($item['precio_unitario']) }}</td>
                        <td style="text-align: right;"><strong>{{ $formatCurrency($item['total']) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 8px; font-style: italic; color: #666;">
                            No se registraron ensayos para esta cotización.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($componentesSueltos->isNotEmpty())
            <h2 class="section-title">Componentes Adicionales</h2>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 45%;">Descripción</th>
                        <th style="width: 15%;">Método</th>
                        <th style="width: 10%; text-align: right;">Unidad</th>
                        <th style="width: 12%; text-align: right;">Cantidad</th>
                        <th style="width: 18%; text-align: right;">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($componentesSueltos as $componente)
                        <tr>
                            <td>{{ $componente['descripcion'] }}</td>
                            <td class="compact-text">{{ $componente['metodo'] ?: '—' }}</td>
                            <td style="text-align: right;" class="compact-text">{{ $componente['unidad'] ?: '—' }}</td>
                            <td style="text-align: right;">{{ number_format($componente['cantidad'], 2, ',', '.') }}</td>
                            <td style="text-align: right;"><strong>{{ $formatCurrency($componente['total']) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2 class="section-title">Resumen Económico</h2>
        <table class="resumen-table">
            <tr>
                <th>Subtotal ensayos</th>
                <td>{{ $formatCurrency($totales['subtotal_items']) }}</td>
            </tr>
            @if($componentesSueltos->isNotEmpty())
            <tr>
                <th>Subtotal componentes adicionales</th>
                <td>{{ $formatCurrency($totales['subtotal_componentes']) }}</td>
            </tr>
            @endif
            <tr>
                <th>Total antes de descuento</th>
                <td>{{ $formatCurrency($totales['subtotal']) }}</td>
            </tr>
            <tr>
                <th>Descuento global @if($totales['descuento_porcentaje'] > 0) ({{ number_format($totales['descuento_porcentaje'], 2, ',', '.') }}%) @endif</th>
                <td>- {{ $formatCurrency($totales['descuento_monto']) }}</td>
            </tr>
            <tr>
                <th>Total cotización</th>
                <td><strong>{{ $formatCurrency($totales['total']) }}</strong></td>
            </tr>
            <tr>
                <th>Total de muestras previstas</th>
                <td>{{ number_format($totales['total_muestras'], 0, ',', '.') }}</td>
            </tr>
        </table>

        <div class="summary-highlight">
            <div>
                Condición de pago: <strong>{{ $condicionPagoDescripcion }}</strong>
                <span class="compact-text"> | Validez: {{ $cotizacion->coti_fechafin ? $formatDate($cotizacion->coti_fechafin) : '30 días' }}</span>
            </div>
            <div>
                Total: <strong>{{ $formatCurrency($totales['total']) }}</strong>
            </div>
        </div>

        <footer>
            Documento confidencial. Su uso está limitado al cliente destinatario. Industria y Ambiente S.A.
        </footer>
    </div>
</div>

<div class="page">
    <div class="wrapper">
        <header>
            <div class="logo">
                @if(file_exists(public_path('assets/img/logo.png')))
                    <img src="{{ public_path('assets/img/logo.png') }}" alt="Industria y Ambiente S.A.">
                @else
                    <strong>Industria y Ambiente S.A.</strong>
                @endif
            </div>
            <div class="company-info">
                <h1>Resumen de Facturación</h1>
                <p>Cotización: <strong>#{{ $cotizacion->coti_num }}</strong></p>
                <p>Cliente: {{ trim((string) ($cotizacion->coti_empresa ?? optional($cliente)->cli_razonsocial ?? '')) }}</p>
                <p>Fecha: {{ $formatDate($cotizacion->coti_fechaalta) }}</p>
            </div>
        </header>

        <h2 class="section-title">Datos de Facturación</h2>
        <table class="billing-table">
            <tr>
                <th style="width: 35%;">Razón social facturación</th>
                <td>{{ trim((string) ($cotizacion->coti_empresa ?? optional($cliente)->cli_razonsocial ?? '')) }}</td>
            </tr>
            <tr>
                <th>Dirección de facturación</th>
                <td>{{ trim((string) ($cotizacion->coti_direccioncli ?? optional($cliente)->cli_direccion ?? '')) }}</td>
            </tr>
            <tr>
                <th>Correo de envío</th>
                <td>{{ $correo ?: '—' }}</td>
            </tr>
            <tr>
                <th>Descuento aplicado</th>
                <td>
                    @if($totales['descuento_porcentaje'] > 0)
                        {{ number_format($totales['descuento_porcentaje'], 2, ',', '.') }}% ({{ $formatCurrency($totales['descuento_monto']) }})
                    @else
                        Sin descuento global registrado
                    @endif
                </td>
            </tr>
            <tr>
                <th>Condición de pago</th>
                <td>{{ $condicionPagoDescripcion }}</td>
            </tr>
            <tr>
                <th>Lista de precios</th>
                <td>{{ optional($cotizacion->listaPrecio)->lp_descripcion ?? 'Lista estándar' }}</td>
            </tr>
            <tr>
                <th>Validez de la oferta</th>
                <td>{{ $cotizacion->coti_fechafin ? $formatDate($cotizacion->coti_fechafin) : '30 días desde la emisión' }}</td>
            </tr>
            <tr>
                <th>Total a facturar</th>
                <td><strong>{{ $formatCurrency($totales['total']) }}</strong></td>
            </tr>
        </table>

        <h2 class="section-title">Notas y Condiciones</h2>
        <div class="notes">
            <ol>
                <li>Industria y Ambiente S.A. mantiene la confidencialidad total de los resultados y conclusiones obtenidos.</li>
                <li>El cliente garantiza la seguridad del personal y equipamiento durante las tareas en sus instalaciones.</li>
                <li>Los impuestos, tasas y sellados relacionados con los trabajos correrán por cuenta del cliente.</li>
                <li>El cliente debe ofrecer acceso seguro a los puntos de medición y cumplir con las normas de Higiene y Seguridad.</li>
                <li>Los trabajos se ejecutarán según la legislación vigente y las especificaciones particulares definidas en la cotización.</li>
            </ol>
        </div>

        <footer>
            Para confirmar la presente cotización comuníquese con su ejecutivo comercial o responda este correo. Industria y Ambiente S.A.
        </footer>
    </div>
</div>
</body>
</html>
