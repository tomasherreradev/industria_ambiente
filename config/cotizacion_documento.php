<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tabla de control — Cotización (presupuesto)
    |--------------------------------------------------------------------------
    */
    'codigo'        => 'R048',
    /** @deprecated Ya no se usa en presupuestos: la fecha de emisión sale de coti_fechaalta. */
    'fecha_emision' => env('COTIZACION_DOC_FECHA_EMISION', '22/12/2023'),
    'dp'            => 'PR10',

    /*
    |--------------------------------------------------------------------------
    | Tabla de control — Protocolo de Ensayo (informe / PDF de muestra)
    |--------------------------------------------------------------------------
    */
    'informe_codigo'        => env('INFORME_DOC_CODIGO',        'R014'),
    'informe_version'       => env('INFORME_DOC_VERSION',       '2'),
    'informe_fecha_emision' => env('INFORME_DOC_FECHA_EMISION', '20/02/2020'),
    'informe_dp'            => env('INFORME_DOC_DP',            'PL06'),
];
