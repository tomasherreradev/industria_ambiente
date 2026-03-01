<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Asegurar que los helpers estén cargados ANTES de iniciar Laravel
// Esto es crítico para que las funciones estén disponibles cuando Blade compile las vistas
$helpersPath = __DIR__.'/../app/helpers.php';
if (file_exists($helpersPath)) {
    require_once $helpersPath;
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
