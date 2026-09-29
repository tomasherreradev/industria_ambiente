<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización #{{ $cotizacion->coti_num }}</title>
    <style>
        @page {
            margin: 52mm 0 30mm 0;
        }
        body {
            font-family: 'Calibri', Arial, sans-serif;
            font-size: 9pt;
            color: #333;
            margin: 0;
            line-height: 1.3;
        }
        /* Header / Footer a ancho completo; márgenes laterales solo en el contenido */
        .pdf-header,
        .pdf-footer {
            position: fixed;
            left: 0;
            right: 0;
            z-index: 10;
            overflow: hidden;
            padding: 0;
            margin: 0;
        }
        .pdf-header {
            top: -52mm;
            height: 46mm;
        }
        .pdf-footer {
            bottom: -30mm;
            height: 28mm;
        }
        .pdf-header img {
            display: block;
            width: 100%;
            height: auto;
            max-height: 46mm;
            object-fit: contain;
            object-position: top center;
        }
        .pdf-footer img {
            display: block;
            width: 100%;
            height: auto;
            max-height: 28mm;
            object-fit: contain;
            object-position: bottom center;
        }
        /*
         * Saltos entre bloques: solo antes de la 2.ª página.
         * No usar page-break-after: always en .page: Dompdf suele ignorar :last-of-type
         * y deja page-break-after en la última página → hoja en blanco al final.
         */
        .page + .page {
            page-break-before: always;
        }
        .page {
            padding: 0 10mm;
        }
        .wrapper {
            padding: 0;
        }
        .quote-meta-bar {
            width: 100%;
            display: table;
            table-layout: fixed;
            margin-bottom: 5pt;
        }
        .quote-meta-left {
            display: table-cell;
            vertical-align: top;
            width: 58%;
            font-size: 8.5pt;
        }
        .quote-meta-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 42%;
        }
        .quote-meta-line {
            margin: 0 0 2pt 0;
        }
        .compact-client-ref {
            font-size: 7.5pt;
            color: #555;
            margin-top: 2pt;
        }
        .quote-meta-separator {
            height: 2pt;
            background: #1a56a8;
            margin: 0 0 8pt 0;
        }
        .doc-control-table {
            border-collapse: collapse;
            font-size: 6pt;
            text-transform: uppercase;
            color: #000;
            margin-left: auto;
        }
        .doc-control-table .doc-ctrl-cell {
            border: 1px solid #000;
            padding: 2pt 4pt;
            text-align: center;
            vertical-align: middle;
            line-height: 1.2;
        }
        .doc-control-table .doc-ctrl-mid-top,
        .doc-control-table .doc-ctrl-mid-bot {
            font-size: 5.6pt;
            min-width: 70pt;
        }
        header { display:none; }
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
            color: #444;
            font-size: 7.5pt;
            background: #f0f2f5;
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
            border: none;
        }
        .items-table th {
            background: #e8eefc;
            color: #0d2b5f;
            padding: 4px 5px;
            border: 1px solid #d0d7eb;
            text-align: left;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .items-table td {
            border: 1px solid #ddd;
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
            line-height: 1.35;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        .item-cadena-tag {
            display: inline-block;
            font-size: 7pt;
            font-weight: normal;
            line-height: 1.2;
            vertical-align: middle;
            position: relative;
            top: 1px;
            border: 1px solid #0dcaf0;
            color: #000;
            background: #cff4fc;
            padding: 1px 6px;
            border-radius: 10px;
            margin-left: 6px;
        }
        .item-cadena-tag-rel {
            border-color: #0d6efd;
            color: #0d6efd;
            background: #e7f1ff;
            margin-left: 4px;
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
        .component-inline .comp-metodo {
            font-size: 7pt;
            color: #777;
            font-style: italic;
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
        .notes .note-item-imprimible {
            margin: 4pt 0;
            font-size: 8pt;
            line-height: 1.35;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        .item-notas-imprimibles {
            margin-top: 4px;
            font-size: 7pt;
            line-height: 1.3;
            color: #444;
        }
        .item-notas-imprimibles-inline {
            margin-top: 2px;
        }
        .item-nota-imprimible-line {
            margin: 2px 0 0 0;
            font-size: 7pt;
            line-height: 1.3;
            font-style: italic;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        .component-inline .item-notas-imprimibles-inline {
            padding-left: 10px;
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
        /* Sin flex: Dompdf suele fallar con flexbox y puede dejar de renderizar el bloque siguiente */
        .summary-highlight {
            background: #f8fbff;
            border: 1px solid #d0d7eb;
            border-radius: 4px;
            padding: 6px 10px;
            margin-top: 8px;
            display: table;
            width: 100%;
            table-layout: fixed;
            font-size: 8.5pt;
        }
        .summary-highlight .sh-left,
        .summary-highlight .sh-right {
            display: table-cell;
            vertical-align: middle;
        }
        .summary-highlight .sh-right {
            text-align: right;
            width: 38%;
        }
        .summary-highlight strong {
            font-size: 10pt;
            color: #0d2b5f;
        }
        footer { display:none; }
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
        .contactos-list {
            margin-top: 4px;
        }
        .contactos-list .contacto-row {
            padding: 3px 0;
            border-bottom: 1px solid #eee;
            font-size: 8pt;
        }
        .contactos-list .contacto-row:last-child {
            border-bottom: none;
        }
        .contacto-tipo {
            font-weight: bold;
            color: #0d6efd;
            margin-right: 6px;
        }
        .closing-saludo-row {
            display: table;
            width: 100%;
            margin-top: 16px;
            font-size: 9pt;
        }
        .closing-saludo-row .left {
            display: table-cell;
            width: 65%;
            vertical-align: bottom;
        }
        .closing-saludo-row .right {
            display: table-cell;
            width: 35%;
            text-align: right;
            font-weight: bold;
            vertical-align: bottom;
        }
        .legal-nota-general-line {
            margin: 6pt 0 0 0;
            font-size: 8pt;
            line-height: 1.35;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            white-space: pre-wrap;
        }
        .legal-item-notes-title {
            font-weight: bold;
            margin: 10px 0 4px 0;
        }
        .legal-note-body {
            margin: 2px 0 6px 12px;
        }
    </style>
</head>
<body>
@php
    $divisaCodigo = $cotizacion->divisa_codigo ?? 'PES';
    $currencyPrefix = in_array(strtoupper((string) $divisaCodigo), ['PES', 'ARS'], true) ? '$' : (string) $divisaCodigo;
    $formatCurrency = function ($value) use ($currencyPrefix) {
        return $currencyPrefix . ' ' . number_format((float) $value, 2, ',', '.');
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

    $destPdf = \App\Support\CotizacionClienteEtiqueta::destinatarioPdfCamposPrincipales($cotizacion);
    $dRazon = $destPdf['dRazon'];
    $etiquetaSucursalEstablecimiento = \App\Support\CotizacionClienteEtiqueta::etiquetaSucursalEstablecimiento($cotizacion);
    $dCuit = $destPdf['dCuit'];
    $dDir = $destPdf['dDir'];
    $dLoc = $destPdf['dLoc'];
    $dPart = $destPdf['dPart'];
    $dContactoBase = $destPdf['dContactoBase'];
    $dLocalidadMerged = $dLoc;
    if ($dPart !== '') {
        $dLocalidadMerged = $dLocalidadMerged !== '' ? $dLocalidadMerged . ' - ' . $dPart : $dPart;
    }
    $correoDest = $correo;
    $contactoDest = $dContactoBase !== '' ? $dContactoBase : $contacto;
    $telDest = $telefono;
    if (!empty($contactosOrdenados)) {
        if ($correoDest === '' && !empty(trim((string) ($contactosOrdenados[0]['correo'] ?? '')))) {
            $correoDest = trim((string) $contactosOrdenados[0]['correo']);
        }
        if ($contactoDest === '' && !empty(trim((string) ($contactosOrdenados[0]['nombre'] ?? '')))) {
            $contactoDest = trim((string) $contactosOrdenados[0]['nombre']);
        }
        if ($telDest === '' && !empty(trim((string) ($contactosOrdenados[0]['telefono'] ?? '')))) {
            $telDest = trim((string) $contactosOrdenados[0]['telefono']);
        }
        if ($telDest === '' && count($contactosOrdenados) > 1) {
            $tels = array_filter(array_map('trim', array_column($contactosOrdenados, 'telefono')));
            $telDest = implode(' / ', $tels);
        }
    }
    $dash = '—';
@endphp

<div class="pdf-header">
    <img src="{{ public_path('assets/img/header_pf.png') }}" alt="Header">
</div>
<div class="pdf-footer">
    <img src="{{ public_path('assets/img/footer_pdf.png') }}" alt="Footer">
</div>

<div class="page">
    <div class="wrapper">

        @include('ventas.partials.pdf-quote-meta')

        <h2 class="section-title">Sr.(es).</h2>
        <table class="info-grid">
            <tr>
                <td class="label">Razón Social</td>
                <td class="value">{{ $dRazon !== '' ? $dRazon : $dash }}</td>
                <td class="label">CUIT</td>
                <td class="value">{{ $dCuit !== '' ? $dCuit : $dash }}</td>
            </tr>
            <tr>
                <td class="label">Dirección</td>
                <td class="value">{{ $dDir !== '' ? $dDir : $dash }}</td>
                <td class="label">Localidad</td>
                <td class="value">{{ $dLocalidadMerged !== '' ? $dLocalidadMerged : $dash }}</td>
            </tr>
            <tr>
                <td class="label">Sucursal / Establecimiento</td>
                <td class="value">{{ trim($etiquetaSucursalEstablecimiento) !== '' ? $etiquetaSucursalEstablecimiento : $dash }}</td>
                <td class="label">Correo</td>
                <td class="value">{{ $correoDest !== '' ? $correoDest : $dash }}</td>
            </tr>
            <tr>
                <td class="label">Contacto</td>
                <td class="value">{{ $contactoDest !== '' ? $contactoDest : $dash }}</td>
                <td class="label">Teléfono</td>
                <td class="value">{{ $telDest !== '' ? $telDest : $dash }}</td>
            </tr>
        </table>

        {{-- Etiquetas/flags: Cadena de Custodia (normal / rel.) --}}
        @php
            $pdfCadenaRel = (bool) ($cotizacion->coti_req_cadena_custodia_relacionada ?? false);
        @endphp

        <h2 class="section-title">Detalle de ensayos y componentes</h2>
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
                        <td>#{{ $loop->iteration }}</td>
                        <td style="word-wrap: break-word; overflow-wrap: break-word; word-break: break-word;">
                            <div class="descripcion-item">
                                {{ $item['descripcion'] }}
                                @if(!empty($item['req_cadena_custodia']))
                                    <span class="item-cadena-tag">Cadena</span>
                                    @if($pdfCadenaRel)
                                        <span class="item-cadena-tag item-cadena-tag-rel">Cadena rel.</span>
                                    @endif
                                @endif
                            </div>
                            @include('partials.cotizacion-item-notas-imprimibles', [
                                'notas' => $item['notas'] ?? [],
                                'wrapperClass' => 'item-notas-imprimibles-inline',
                            ])
                            @if($item['componentes']->isNotEmpty())
                                @foreach($item['componentes'] as $componente)
                                    <div class="component-inline">
                                        <span>• {{ $componente['descripcion'] }}</span>
                                        @if((float) ($componente['cantidad'] ?? 1) > 1)
                                            <span class="text-muted"> (cant. {{ number_format((float) $componente['cantidad'], 0, ',', '.') }})</span>
                                        @endif

                                        @if(!empty($componente['metodo']))
                                            <span class="comp-metodo">[{{ $componente['metodo'] }}]</span>
                                        @endif
                                        @include('partials.cotizacion-item-notas-imprimibles', [
                                            'notas' => $componente['notas'] ?? [],
                                            'wrapperClass' => 'item-notas-imprimibles-inline',
                                        ])
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
                        <th style="width: 60%;">Descripción</th>
                        <th style="width: 10%; text-align: right;">Unidad</th>
                        <th style="width: 12%; text-align: right;">Cantidad</th>
                        <th style="width: 18%; text-align: right;">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($componentesSueltos as $componente)
                        <tr>
                            <td>
                                {{ $componente['descripcion'] }}
                                @if(!empty($componente['metodo']))
                                    <span class="comp-metodo">[{{ $componente['metodo'] }}]</span>
                                @endif
                            </td>
                            <td style="text-align: right;" class="compact-text">{{ $componente['unidad'] ?: '—' }}</td>
                            <td style="text-align: right;">{{ number_format($componente['cantidad'], 2, ',', '.') }}</td>
                            <td style="text-align: right;"><strong>{{ $formatCurrency($componente['total']) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    </div>
</div>

<div class="page">
    <div class="wrapper">

        @include('ventas.partials.pdf-quote-meta')

        <h2 class="section-title">Resumen económico</h2>
        <table class="resumen-table">
            @if($componentesSueltos->isNotEmpty())
            <tr>
                <th>Componentes adicionales (sin encabezado de ensayo)</th>
                <td>{{ $formatCurrency($totales['subtotal_componentes']) }}</td>
            </tr>
            @endif
            <tr>
                <th>Subtotal (antes de descuento global)</th>
                <td>{{ $formatCurrency($totales['subtotal']) }}</td>
            </tr>
            @if($cotizacion->coti_mostrar_descuento ?? true)
            <tr>
                <th>
                    Descuento global
                    @if($totales['descuento_porcentaje'] > 0)
                        ({{ number_format($totales['descuento_porcentaje'], 2, ',', '.') }}%)
                    @endif
                </th>
                <td>- {{ $formatCurrency($totales['descuento_monto']) }}</td>
            </tr>
            @endif
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
            <div class="sh-left">
                Condición de pago: <strong>{{ $condicionPagoDescripcion }}</strong>
                <span class="compact-text"> | Validez: {{ $cotizacion->coti_fechafin ? $formatDate($cotizacion->coti_fechafin) : '30 días' }}</span>
                <span class="compact-text"> | Divisa: {{ $divisaCodigo }}</span>
            </div>
            <div class="sh-right">
                Total: <strong>{{ $formatCurrency($totales['total']) }}</strong>
            </div>
        </div>

        @php
            $tieneDatosFacturacionPdf = trim((string) ($cotizacion->coti_empresa ?? '')) !== '' || trim((string) ($cotizacion->coti_direccioncli ?? '')) !== '' || trim((string) ($cotizacion->coti_cuit ?? '')) !== '';
        @endphp
        @if($tieneDatosFacturacionPdf)
        <h2 class="section-title">Datos de facturación</h2>
        <table class="info-grid">
            <tr>
                <td class="label">Razón Social</td>
                <td class="value">{{ trim((string) ($cotizacion->coti_empresa ?? '')) !== '' ? trim($cotizacion->coti_empresa) : $dash }}</td>
                <td class="label">CUIT</td>
                <td class="value">{{ trim((string) ($cotizacion->coti_cuit ?? '')) !== '' ? trim($cotizacion->coti_cuit) : $dash }}</td>
            </tr>
            <tr>
                <td class="label">Dirección</td>
                <td class="value">{{ trim((string) ($cotizacion->coti_direccioncli ?? '')) !== '' ? trim($cotizacion->coti_direccioncli) : $dash }}</td>
                <td class="label">Localidad</td>
                <td class="value">{{ ($facturacionLocalidadLinePdf ?? '') !== '' ? $facturacionLocalidadLinePdf : $dash }}</td>
            </tr>
            <tr>
                <td class="label">Código Postal</td>
                <td class="value">{{ trim((string) ($cotizacion->coti_codigopostal ?? '')) !== '' ? trim($cotizacion->coti_codigopostal) : $dash }}</td>
                <td class="label"></td>
                <td class="value"></td>
            </tr>
        </table>
        @endif

        <h2 class="section-title">Notas y condiciones</h2>
        <div class="notes">
            <ol>
                <li>Los precios se expresan en {{ strtoupper($divisaCodigo) === 'PES' || strtoupper($divisaCodigo) === 'ARS' ? 'Pesos' : $divisaCodigo }} y no incluyen I.V.A. (Salvo aclaración específica en la cotización)</li>
                <li>Industria y Ambiente S.A., apegado a la Ley Nacional 24.766, declara mantener la confidencialidad de los resultados totales o parciales obtenidos en los análisis realizados.</li>
                <li>La Empresa contratante deberá garantizar la seguridad tanto física del personal de Industria y Ambiente S.A. como de la seguridad patrimonial del equipamiento a utilizar en los trabajos contratados. En el caso de sufrir pérdidas o roturas en los equipos utilizados, la empresa deberá retribuir al Laboratorio el monto de los bienes dañados o extraídos. En caso de requerir, el personal del Laboratorio podrá exigir la presencia de personal de vigilancia para realizar las tareas.</li>
                <li>Los timbrados, tasas, aportes y sellados que surjan de los trabajos contratados correrán por cuenta de vuestra Empresa. Salvo que los mismos estén expresamente aclarados en el presente presupuesto.</li>
                <li>La empresa deberá brindar los medios para acceder a los puntos de análisis y/o mediciones en forma segura. El personal de Industria y Ambiente S.A. se reserva el derecho de NO realizar las tareas pactadas en caso del NO cumplimiento de las normas mínimas de Higiene y Seguridad en el trabajo.</li>
                <li>La empresa deberá cumplir con todas las características constructivas de las Instalaciones a medir y/o analizar según lo especificado en la Legislación vigente. En caso de no poseerlas, se evaluará puntualmente la situación y se acordará con la empresa contratante los pasos a seguir.</li>
                <li>En el caso de concurrir a planta con la previa coordinación y no poder realizar el trabajo por única culpa de la empresa contratante, la misma deberá abonar el monto correspondiente a un viático adicional al establecido en el presupuesto. El valor viático, corresponde a media Jornada de 2 técnicos. (En caso del interior del país o en el 3er cordón de la provincia de Bs. As., se cotizara oportunamente)</li>
                <li>Para la extracción de las muestras ver procedimientos y normas de TOMA DE MUESTRA de nuestra página web: www.industriayambiente.com.ar</li>
                <li>En caso de aceptación del presente presupuesto, favor de enviar una Orden de Compra mencionando el Nro. de Cotización del presente presupuesto.</li>
            </ol>
            @php
                $notasGeneralesPresupuesto = \App\Support\CotizacionNotasGenerales::listadoParaVista($cotizacion->coti_notas ?? null);
            @endphp
            @if(count($notasGeneralesPresupuesto) > 0)
                @foreach($notasGeneralesPresupuesto as $notaGeneral)
                    <p class="legal-nota-general-line">{{ $notaGeneral }}</p>
                @endforeach
            @endif
        </div>

        <div class="closing-saludo-row">
            <div class="left">Sin otro particular saluda a ud. atte.</div>
            <div class="right">{{ !empty(trim((string) ($nombreCreadorCoti ?? ''))) ? $nombreCreadorCoti : '—' }}</div>
        </div>

    </div>
</div>
</body>
</html>
