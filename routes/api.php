<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LandlordController;
use App\Http\Controllers\Api\TenantController;

Route::prefix('auth')->group(function () {
    Route::post('/request-otp', [AuthController::class, 'requestOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
});

Route::middleware('auth:sanctum')->group(function () {

    // Landlord
    Route::post('/landlords', [LandlordController::class, 'store']);
    Route::get('/landlords/me', [LandlordController::class, 'me']);

    // Tenant
    Route::post('/tenants', [TenantController::class, 'store']);
    Route::get('/tenants/me', [TenantController::class, 'me']);

});