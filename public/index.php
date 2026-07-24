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

// --- cPanel/shared-hosting sub-folder support -------------------------------
// If this app is deployed inside a sub-folder of public_html (rather than the
// document root being pointed straight at /public), the root index.php in
// this project forwards the request here. That means REQUEST_URI still has
// the sub-folder (and possibly /public) in front of it, e.g.
// "/aj_ice_cream_api_clean/api/login" instead of "/api/login". Strip it so
// Laravel's router matches routes normally. This is folder-name-agnostic —
// it reads the prefix from SCRIPT_NAME, so renaming the folder needs no code
// change. When the doc root is pointed directly at /public (VPS/subdomain
// setups), SCRIPT_NAME has no extra prefix and this is a no-op.
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

if ($scriptDir !== '' && str_starts_with($requestUri, $scriptDir)) {
    $requestUri = substr($requestUri, strlen($scriptDir));
}
if (str_starts_with($requestUri, '/public')) {
    $requestUri = substr($requestUri, strlen('/public'));
}
if ($requestUri === '') {
    $requestUri = '/';
}
$_SERVER['REQUEST_URI'] = $requestUri;
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

$app->handleRequest(Request::capture());
