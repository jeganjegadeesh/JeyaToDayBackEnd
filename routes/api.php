<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RetailerController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CashPaymentController;

// Public
Route::post('/login', [AuthController::class, 'login']);

// Protected
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Profile
    Route::get('/profile',          [ProfileController::class, 'show']);
    Route::put('/profile',          [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // Products - read for all
    Route::get('/products/categories', [ProductController::class, 'categories']);
    Route::get('/products',            [ProductController::class, 'index']);
    Route::get('/products/{product}',  [ProductController::class, 'show']);

    // Reports - read for all
    Route::get('/reports/today',             [ReportController::class, 'today']);
    Route::get('/reports/summary',           [ReportController::class, 'summary']);
    Route::get('/reports/retailer/{id}',     [ReportController::class, 'retailerReport']);
    Route::get('/reports/chart/admin',       [ReportController::class, 'adminChart']);
    Route::get('/reports/chart/retailer',    [ReportController::class, 'retailerChart']);

    // Pending bill preview - for all
    Route::get('/bill/pending/{retailerId}',
        [BillController::class, 'pending']);

    // Admin Only
    Route::middleware('role:admin')->group(function () {

        // Products
        Route::post('/products',             [ProductController::class, 'store']);
        Route::put('/products/{product}',    [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);

        // Retailers
        Route::apiResource('/retailers', RetailerController::class);

        // Users
        Route::get('/users',           [UserController::class, 'index']);
        Route::post('/users',          [UserController::class, 'store']);
        Route::get('/users/{user}',    [UserController::class, 'show']);
        Route::put('/users/{user}',    [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        // Stock
        Route::post('/stock/give',
            [StockController::class, 'give']);
        Route::get('/stock/history',
            [StockController::class, 'history']);
        Route::get('/stock/today/{retailerId}',
            [StockController::class, 'todayStock']);

        // Returns
        Route::post('/returns',
            [ReturnController::class, 'store']);
        Route::get('/returns',
            [ReturnController::class, 'index']);
        Route::get('/returns/today/{retailerId}',
            [ReturnController::class, 'todayReturn']);

        // Cash Payments
        Route::get('/cash-payments',
            [CashPaymentController::class, 'index']);
        Route::post('/cash-payments',
            [CashPaymentController::class, 'store']);
        Route::delete('/cash-payments/{cashPayment}',
            [CashPaymentController::class, 'destroy']);
        Route::get('/cash-payments/total',
            [CashPaymentController::class, 'total']);

        // Bills
        Route::post('/bill/generate',
            [BillController::class, 'generate']);
        Route::get('/bill/history',
            [BillController::class, 'history']);
        Route::get('/bill/summary',
            [BillController::class, 'summary']);
        Route::get('/bill/{bill}',
            [BillController::class, 'show']);
        Route::delete('/bill/{bill}',
            [BillController::class, 'destroy']);
    });

    // Retailer Routes
    Route::middleware('role:retailer')->group(function () {
        Route::get('/retailer/stock',
            [StockController::class, 'history']);
        Route::get('/retailer/returns',
            [ReturnController::class, 'index']);
        Route::get('/retailer/bills',
            [BillController::class, 'history']);
    });
});