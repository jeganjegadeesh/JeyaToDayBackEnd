<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

// Strip subfolder from REQUEST_URI before Laravel processes it
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Remove /JeyaToDayBackEnd prefix
$prefix = '/JeyaToDayBackEnd';
if (str_starts_with($requestUri, $prefix)) {
    $requestUri = substr($requestUri, strlen($prefix));
}

// Remove /public prefix if still present
$publicPrefix = '/public';
if (str_starts_with($requestUri, $publicPrefix)) {
    $requestUri = substr($requestUri, strlen($publicPrefix));
}

// Make sure it starts with /
if (empty($requestUri)) {
    $requestUri = '/';
}

// Override the server variables
$_SERVER['REQUEST_URI'] = $requestUri;
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

$app->handleRequest(Request::capture());