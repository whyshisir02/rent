<?php 
use Illuminate\Support\Facades\DB;
use App\Models\HouseBill;
use App\Models\VerificationDocument;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LandlordController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\VerificationDocumentController;
use App\Http\Controllers\Api\ProfileVerificationController;

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

    // Verification Documents (upload + list own)
    Route::get(
        '/verification-documents',
        [VerificationDocumentController::class, 'index']
    );
    Route::post(
        '/verification-documents',
        [VerificationDocumentController::class, 'store']
    );
    Route::get(
        '/documents/download/{type}/{id}',
        [VerificationDocumentController::class, 'download']
    )->where('type', 'document|house-bill');

    Route::get('/documents/{path}', [VerificationDocumentController::class, 'streamFile'])
        ->where('path', '.*');

    // Admin-only: list all users for verification review
    Route::middleware('admin')->group(function () {
        Route::get(
            '/admin/users',
            [VerificationDocumentController::class, 'adminUserIndex']
        );

        Route::get(
            '/admin/users/{user}/verification',
            [VerificationDocumentController::class, 'adminShowVerification']
        );
    });

    // Admin-only: approve/reject profile verification status
    Route::middleware('admin')->group(function () {
        Route::patch(
            '/users/{user}/verification',
            [ProfileVerificationController::class, 'update']
        );
    });

});