<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RetailerController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\BillController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Requires Sanctum Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    /*
    |----------------------------------------------------------------------
    | Admin Only Routes
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {

        // Products
        Route::get('/products/categories', [ProductController::class, 'categories']);
        Route::apiResource('/products', ProductController::class);

        // Retailers
        Route::apiResource('/retailers', RetailerController::class);

        // Stock
        Route::post('/stock/give',                [StockController::class, 'give']);
        Route::get('/stock/history',              [StockController::class, 'history']);
        Route::get('/stock/today/{retailerId}',   [StockController::class, 'todayStock']);

        // Returns
        Route::post('/returns',                   [ReturnController::class, 'store']);
        Route::get('/returns',                    [ReturnController::class, 'index']);
        Route::get('/returns/today/{retailerId}', [ReturnController::class, 'todayReturn']);

        // Bills
        Route::post('/bill/generate',  [BillController::class, 'generate']);
        Route::get('/bill/history',    [BillController::class, 'history']);
        Route::get('/bill/summary',    [BillController::class, 'summary']);
        Route::get('/bill/{bill}',     [BillController::class, 'show']);
    });
});