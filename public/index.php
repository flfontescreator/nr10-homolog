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

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// Remove o prefixo do subdiretório para que o roteamento funcione
// quando a aplicação está publicada abaixo de um caminho (ex.: /clientes/...).
$docRoot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$publicDir = rtrim(str_replace('\\', '/', __DIR__), '/');

$basePath = '';

if ($docRoot !== '' && str_starts_with($publicDir, $docRoot.'/')) {
    $segments = explode('/', trim(substr($publicDir, strlen($docRoot)), '/'));

    if (end($segments) === 'public') {
        array_pop($segments);
    }

    $basePath = '/'.implode('/', $segments);
}

if ($basePath !== '/' && isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], $basePath)) {
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($basePath)) ?: '/';
    $_SERVER['SCRIPT_NAME'] = str_replace($basePath, '', $_SERVER['SCRIPT_NAME'] ?? '');
}

$app->handleRequest(Request::capture());
