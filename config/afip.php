<?php

$afipProduction = filter_var(env('AFIP_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN);

return [
    /*
    |--------------------------------------------------------------------------
    | AFIP Configuration
    |--------------------------------------------------------------------------
    |
    | Leer siempre vía config() en la app (no env() fuera de este archivo): con
    | "php artisan config:cache", env() devuelve null en controladores y rompe
    | el modo desarrollo solo con token (CUIT 20409378472 sin PEM).
    |
    */

    'cuit' => env('AFIP_CUIT', '20409378472'),

    'production' => $afipProduction,

    /** Sin default: si no está en .env, null → modo solo token permitido con CUIT de testing del SDK. */
    'cert_path' => env('AFIP_CERT_PATH'),

    'key_path' => env('AFIP_KEY_PATH'),

    'passphrase' => env('AFIP_PASSPHRASE', ''),

    'access_token' => env('AFIPSDK_ACCESS_TOKEN'),

    'punto_venta' => (int) (env('AFIP_PTO_VTA') ?: env('AFIP_PUNTO_VENTA', 1)),

    'cbte_tipo' => (int) env('AFIP_CBTE_TIPO', 1),

    /**
     * WSAA + WSFE directo a ARCA (sin app.afipsdk.com). Requiere PEM + extensión soap.
     */
    'direct_wsfe' => filter_var(env('AFIP_DIRECT_WSFE', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | AFIP URLs
    |--------------------------------------------------------------------------
    */
    'wsaa_wsdl' => $afipProduction
        ? 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl'
        : 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl',

    'wsfe_wsdl' => $afipProduction
        ? 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL'
        : 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL',
];
