<style>
    @page {
        margin: 54mm 10mm 22mm 10mm;
    }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 8pt;
        line-height: 1.15;
        margin: 0;
        color: #000;
    }

    .pdf-header {
        position: fixed;
        left: 0;
        right: 0;
        top: -54mm;
        height: 34mm;
        z-index: 10;
    }

    .pdf-header img {
        width: 100%;
        height: 100%;
        display: block;
    }

    .pdf-footer {
        position: fixed;
        left: 0;
        right: 0;
        bottom: -22mm;
        height: 22mm;
        z-index: 10;
        box-sizing: border-box;
        padding: 2mm 3mm 0 3mm;
        border-top: 1px solid #000;
        text-align: center;
        font-size: 6pt;
        line-height: 1.5;
        color: #000;
    }

    .pdf-footer .footer-version {
        font-weight: 700;
        margin-bottom: 1mm;
    }

    .pdf-footer .footer-legal {
        font-style: italic;
    }

    .pdf-title-bar {
        position: fixed;
        left: 10mm;
        right: 10mm;
        top: -13mm;
        z-index: 11;
        width: calc(100% - 20mm);
        box-sizing: border-box;
    }

    .pdf-title-bar-table {
        width: 100%;
        border-collapse: collapse;
        border-spacing: 0;
        table-layout: fixed;
    }

    .pdf-title-bar-table td {
        border: 0;
        padding: 0;
        vertical-align: middle;
    }

    .pdf-title-cell {
        padding-right: 3mm;
    }

    .pdf-title-text {
        font-weight: 700;
        font-size: 12.5pt;
        line-height: 1.15;
        text-align: left;
        text-decoration: underline;
        text-transform: uppercase;
    }

    .content {
        padding: 0;
        margin-top: -2mm;
    }

    .informe-muestra-block {
        page-break-after: always;
    }

    .informe-muestra-block:last-child {
        page-break-after: auto;
    }

    .box {
        border: 1px solid #000;
        padding: 3mm;
        margin-bottom: 4mm;
    }

    table.kv {
        width: 100%;
        border-collapse: collapse;
        font-size: 7.5pt;
    }

    table.kv td {
        padding: 1.1mm 0.9mm;
        vertical-align: top;
    }

    .k {
        font-weight: 700;
        white-space: nowrap;
    }

    .label {
        font-weight: 700;
        font-size: 7.6pt;
        margin: 3mm 0 1.5mm 0;
        text-decoration: underline;
    }

    table.data {
        width: 100%;
        border-collapse: collapse;
        font-size: 7.2pt;
    }

    table.data th,
    table.data td {
        border: 1px solid #000;
        padding: 1.3mm 1.2mm;
    }

    table.data th {
        background: #b8e0f4;
        font-weight: 700;
        text-align: center;
        color: #000;
    }

    table.data td {
        vertical-align: top;
    }

    .obs-box {
        border: 1px solid #bbb;
        padding: 2mm 3mm;
        margin-top: 1mm;
        min-height: 20mm;
        font-size: 7.2pt;
        line-height: 1.4;
    }

    .notas-box {
        font-size: 8pt;
        line-height: 1.2;
        margin-top: 4mm;
        margin-bottom: 3mm;
        text-align: justify;
        text-indent: 0;
        padding: 0;
        max-width: 170mm;
        width: 100%;
        display: block;
        overflow-wrap: anywhere;
        word-wrap: break-word;
    }

    .instrumental-section {
        page-break-inside: avoid;
    }

    .muted {
        color: #444;
        font-size: 7.2pt;
    }

    .center {
        text-align: center;
    }

    .right {
        text-align: right;
    }

    .no-data {
        font-style: italic;
        color: #555;
        padding: 2mm 0;
    }

    .map-wrap {
        margin-top: 2mm;
        text-align: center;
    }

    .page-break {
        page-break-after: always;
    }

    .map-img {
        width: 85%;
        height: auto;
        border: 1px solid #000;
        max-height: 105mm;
        display: inline-block;
    }

    .map-caption {
        font-size: 6.8pt;
        text-align: center;
        margin-top: 1mm;
    }
</style>
