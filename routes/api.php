<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\DeploymentController;
use App\Http\Controllers\Api\V1\SignedFileController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Services\RequestAnalyticsService;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Request Analytics routes for debugging and analysis
Route::middleware(['api'])->group(function () {
    // Get current request analytics
    Route::get('/analytics/request', function (Request $request) {
        $analytics = app(RequestAnalyticsService::class);

        return response()->json([
            'success' => true,
            'data' => $analytics->getAnalytics(),
            'timestamp' => now()->toISOString(),
        ]);
    });


    // Get analytics for specific IP
    Route::get('/analytics/ip/{ip}', function (string $ip) {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', $ip);

        $analytics = new RequestAnalyticsService($request);

        return response()->json([
            'success' => true,
            'ip' => $ip,
            'data' => $analytics->getAnalytics(),
            'timestamp' => now()->toISOString(),
        ]);
    });
});



// <<==================== Actual Api Endpoints ===================>>

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    // Password reset flow
    Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::post('password/verify-token', [AuthController::class, 'verifyResetToken'])->name('auth.verify-email');
});


// Protected routes - Require authentication
Route::middleware(['jwt.verify'])->group(function () {

    // <<==================== AUTH & PROFILE ROUTES ===================>>
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        // Route::put('profile', [AuthController::class, 'updateProfile'])->name('auth.update-profile');

        // Update password - ALL authenticated users can do this
        Route::put('password', [AuthController::class, 'updatePassword'])->name('auth.update-password');
        Route::put('password/force-change', [AuthController::class, 'forceChangePassword'])->name('auth.force-change-password');

        // Security information
        Route::get('security/info', [AuthController::class, 'getPasswordSecurityInfo'])->name('auth.security-info');
        Route::get('security/logs', [AuthController::class, 'getSecurityLogs'])->name('auth.security-logs');
    });

    // Upload routes
    Route::prefix('uploads')->group(function () {
        Route::post('/temp', [UploadController::class, 'uploadTemporary']);
        Route::get('/limits', [UploadController::class, 'getUploadLimits']);
    });
});

// Signed URL routes
Route::prefix('files')->group(function () {
    // Generate signed URLs (requires auth)
    Route::middleware(['jwt.verify'])->group(function () {
        Route::post('/signed/temp', [SignedFileController::class, 'generateTemporarySignedUrl']);
        Route::post('/signed/public', [SignedFileController::class, 'generatePublicSignedUrl']);
        Route::post('/signed/private', [SignedFileController::class, 'generatePrivateSignedUrl']);
    });

    // Serve signed files (no auth required - URL itself is the auth)
    Route::get('/signed/{signature}', [SignedFileController::class, 'serveSigned'])
        ->name('api.v1.files.signed');
});

// Deployment webhooks and manual deploy routes
Route::prefix('webhooks')->group(function () {
    Route::get('/github', [DeploymentController::class, 'verifyWebhook']);

    Route::post('/github', [DeploymentController::class, 'handleWebhook'])
        ->middleware(['throttle:10,1', 'github-webhook']);

    Route::post('/manual-deploy', [DeploymentController::class, 'manualDeploy'])
        ->middleware('throttle:5,1');

    // rollback endpoint
    Route::post('/rollback', [DeploymentController::class, 'rollback'])
        ->middleware('throttle:2,10');
});

// Fallback for undefined routes
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'Endpoint not found',
        'timestamp' => now()->toISOString()
    ], 404);
});
