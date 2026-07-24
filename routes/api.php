<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\CashPaymentController;
use App\Http\Controllers\Api\CashReportController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GiveStockController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RawMaterialController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RetailerController;
use App\Http\Controllers\Api\RetailerLoanController;
use App\Http\Controllers\Api\RetailerPortalController;
use App\Http\Controllers\Api\ReturnStockController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------
// Public
// ---------------------------------------------------------------------
Route::post('/login', [AuthController::class, 'login']);

// ---------------------------------------------------------------------
// Authenticated (any role)
// ---------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'changePassword']);
    Route::get('/company', [CompanyController::class, 'show']);

    // -------------------------------------------------------------
    // Admin & Manager screens (section 3) - both roles share access,
    // destructive actions are further gated to 'admin' only below.
    // -------------------------------------------------------------
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::apiResource('products', ProductController::class)->except('destroy');
        Route::apiResource('retailers', RetailerController::class)->except('destroy');
        Route::apiResource('give-stock', GiveStockController::class)->except('destroy');
        Route::apiResource('return-stock', ReturnStockController::class)->except('destroy');
        Route::apiResource('cash-payments', CashPaymentController::class)->except('destroy');
        Route::apiResource('raw-materials', RawMaterialController::class)->except('destroy');
        Route::apiResource('expenses', ExpenseController::class)->except('destroy');
        Route::apiResource('retailer-loans', RetailerLoanController::class)->except('destroy');

        Route::get('/bills', [BillController::class, 'index']);
        Route::get('/bills/{bill}', [BillController::class, 'show']);
        Route::post('/bills/preview', [BillController::class, 'preview']);
        Route::post('/bills/generate', [BillController::class, 'generate']);
        Route::post('/bills/{bill}/settle', [BillController::class, 'settle']);

        Route::get('/reports/sales', [ReportController::class, 'sales']);
        Route::get('/reports/stock', [ReportController::class, 'stock']);
        Route::get('/reports/cash', [CashReportController::class, 'index']);
    });

    // -------------------------------------------------------------
    // Admin only (section 2.2: "Only Admin users can perform delete
    // operations", plus Users & Company management)
    // -------------------------------------------------------------
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class)->except(['store', 'index', 'show']);
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::post('/users/{user}/restore', [UserController::class, 'restore']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);

        Route::post('/company', [CompanyController::class, 'store']);
        Route::put('/company/{company}', [CompanyController::class, 'update']);

        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::post('/products/{product}/restore', [ProductController::class, 'restore']);
        Route::delete('/retailers/{retailer}', [RetailerController::class, 'destroy']);
        Route::post('/retailers/{retailer}/restore', [RetailerController::class, 'restore']);
        Route::delete('/give-stock/{giveStock}', [GiveStockController::class, 'destroy']);
        Route::delete('/return-stock/{returnStock}', [ReturnStockController::class, 'destroy']);
        Route::delete('/cash-payments/{cashPayment}', [CashPaymentController::class, 'destroy']);
        Route::delete('/bills/{bill}', [BillController::class, 'destroy']);
        Route::delete('/raw-materials/{rawMaterial}', [RawMaterialController::class, 'destroy']);
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
        Route::delete('/retailer-loans/{retailerLoan}', [RetailerLoanController::class, 'destroy']);
    });

    // -------------------------------------------------------------
    // Retailer screens (section 4) - read-only, own data only
    // -------------------------------------------------------------
    Route::middleware('role:retailer')->group(function () {
        Route::get('/dashboard/retailer', [DashboardController::class, 'retailer']);
        Route::get('/my/received-stock', [RetailerPortalController::class, 'receivedStock']);
        Route::get('/my/returned-stock', [RetailerPortalController::class, 'returnedStock']);
        Route::get('/my/payments', [RetailerPortalController::class, 'payments']);
        Route::get('/my/bills', [RetailerPortalController::class, 'bills']);
        Route::get('/my/bills/{bill}', [RetailerPortalController::class, 'billShow']);
        Route::get('/my/reports/sales', [ReportController::class, 'mySales']);
        Route::get('/my/reports/stock', [ReportController::class, 'myStock']);
    });
});