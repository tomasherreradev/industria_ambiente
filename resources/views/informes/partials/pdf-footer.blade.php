@php
    $versionInforme = config('cotizacion_documento.informe_version', '2');
    $fechaInforme = config('cotizacion_documento.informe_fecha_emision', '20/02/2020');
@endphp

<div class="pdf-footer">
    <div class="pdf-footer-notes">
        <div class="footer-version">Versión: {{ $versionInforme }} &nbsp;&nbsp; Fecha de emisión: {{ $fechaInforme }}</div>
        <div class="footer-legal">Los resultados del presente informe se relacionan solamente con los ítems sometidos a ensayo.</div>
        <div class="footer-legal">Prohibida su reproducción total o parcial. La reproducción de la misma deberá ser autorizada por Industria y Ambiente S.A.</div>
    </div>
    <img src="{{ public_path('assets/img/footer_pdf.png') }}" alt="Footer">
</div>
