<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['app' => 'AJ Project API', 'status' => 'ok']);
});
