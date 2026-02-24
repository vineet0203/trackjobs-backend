<?php

use App\Http\Controllers\Api\V1\Client\ClientController;
use App\Http\Controllers\Api\V1\Client\ClientAvailabilityController; // NEW: Add this line
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\DeploymentController;
use App\Http\Controllers\Api\V1\Quotes\QuoteController;
use App\Http\Controllers\Api\V1\SignedFileController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\Jobs\JobController;
use App\Services\RequestAnalyticsService;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// ============================================
// REQUEST ANALYTICS ROUTES (DEBUG/ANALYSIS)
// ============================================
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

// ============================================
// <<==================== ACTUAL API ENDPOINTS ===================>>
// ============================================

// ============================================
// PUBLIC AUTHENTICATION ROUTES
// ============================================
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');

    // Password reset flow
    Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::post('password/verify-token', [AuthController::class, 'verifyResetToken'])->name('auth.verify-email');
});

// ============================================
// PROTECTED ROUTES - REQUIRE AUTHENTICATION
// ============================================
Route::middleware(['jwt.verify'])->group(function () {

    // ============================================
    // AUTH & PROFILE ROUTES
    // ============================================
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');

        // Password management
        Route::put('password', [AuthController::class, 'updatePassword'])->name('auth.update-password');
        Route::put('password/force-change', [AuthController::class, 'forceChangePassword'])->name('auth.force-change-password');

        // Security information
        Route::get('security/info', [AuthController::class, 'getPasswordSecurityInfo'])->name('auth.security-info');
        Route::get('security/logs', [AuthController::class, 'getSecurityLogs'])->name('auth.security-logs');
    });

    // ============================================
    // VENDOR MANAGEMENT ROUTES
    // ============================================
    Route::prefix('vendors')->group(function () {
        // ============================================
        // CLIENT MANAGEMENT ROUTES
        // ============================================
        Route::prefix('clients')->group(function () {
            // Get all clients for the authenticated vendor with filters
            Route::get('/', [ClientController::class, 'getVendorClients']);
            // Get a specific client for the authenticated vendor
            Route::get('/{clientId}', [ClientController::class, 'getVendorClient']);
            // Add a new client for the authenticated vendor
            Route::post('/', [ClientController::class, 'addClient']);
            // Update a client for the authenticated vendor
            Route::put('/{clientId}', [ClientController::class, 'modifyClient']);
            // Delete a client for the authenticated vendor
            Route::delete('/{clientId}', [ClientController::class, 'removeClient']);

            // ============================================
            // CLIENT AVAILABILITY SCHEDULING ROUTES
            // ============================================
            Route::prefix('{clientId}/availability')->group(function () {
                // Get all availability schedules for client
                Route::get('/', [ClientAvailabilityController::class, 'index']);
                // Get active availability schedule
                Route::get('/active', [ClientAvailabilityController::class, 'getActive']);
                // Create new availability schedule
                Route::post('/', [ClientAvailabilityController::class, 'store']);
                // Check client availability for specific date/time
                Route::get('/check', [ClientAvailabilityController::class, 'checkAvailability']);
                // Get available time slots for specific date
                Route::get('/slots', [ClientAvailabilityController::class, 'getAvailableSlots']);
            });
        });

        // ============================================
        // QUOTE MANAGEMENT ROUTES
        // ============================================
        Route::prefix('quotes')->group(function () {
            Route::get('/', [QuoteController::class, 'index']);
            Route::get('/statistics', [QuoteController::class, 'statistics']);
            Route::get('/number/{quoteNumber}', [QuoteController::class, 'showByNumber']);
            Route::post('/', [QuoteController::class, 'store']);
            Route::get('/{id}', [QuoteController::class, 'show']);
            Route::put('/{id}', [QuoteController::class, 'update']);
            Route::delete('/{id}', [QuoteController::class, 'destroy']);
            Route::post('/{id}/send', [QuoteController::class, 'send']);
            Route::post('/{id}/follow-up-status', [QuoteController::class, 'updateFollowUpStatus']);
            Route::post('/{id}/convert-to-job', [QuoteController::class, 'convertToJob']);
        });

        // ============================================
        // WORK ORDER MANAGEMENT ROUTES (JOBS)
        // ============================================
        Route::prefix('jobs')->group(function () {
            // Statistics
            Route::get('/statistics', [JobController::class, 'statistics']);

            // Get by work order number
            Route::get('/number/{JobNumber}', [JobController::class, 'showByNumber']);

            // CRUD operations
            Route::get('/', [JobController::class, 'index']);
            Route::post('/', [JobController::class, 'store']);
            Route::get('/{id}', [JobController::class, 'show']);
            Route::put('/{id}', [JobController::class, 'update']);
            Route::delete('/{id}', [JobController::class, 'destroy']);

            // Status update
            Route::patch('/{id}/status', [JobController::class, 'updateStatus']);

            // Task management
            Route::post('/{id}/tasks', [JobController::class, 'addTask']);
            Route::patch('/{id}/tasks/{taskId}/toggle', [JobController::class, 'toggleTask']);
            Route::delete('/{id}/tasks/{taskId}', [JobController::class, 'deleteTask']);

            // Attachment management
            Route::post('/{id}/attachments', [JobController::class, 'addAttachment']);
            Route::delete('/{id}/attachments/{attachmentId}', [JobController::class, 'deleteAttachment']);
        });

        // ============================================
        // DIRECT AVAILABILITY SCHEDULE MANAGEMENT
        // (For updating/deleting specific schedules)
        // ============================================
        Route::prefix('availability-schedules')->group(function () {
            // Update specific availability schedule
            Route::put('/{scheduleId}', [ClientAvailabilityController::class, 'update']);
            // Delete specific availability schedule
            Route::delete('/{scheduleId}', [ClientAvailabilityController::class, 'destroy']);
        });
    });



    // ============================================
    // UPLOAD MANAGEMENT ROUTES
    // ============================================
    Route::prefix('uploads')->group(function () {
        Route::post('/temp', [UploadController::class, 'uploadTemporary']);
        Route::get('/limits', [UploadController::class, 'getUploadLimits']);
    });
});

// ============================================
// SIGNED URL ROUTES
// ============================================
Route::prefix('files')->group(function () {
    // Serve signed files (no auth required - URL itself is the auth)
    Route::get('/signed/{signature}', [SignedFileController::class, 'serveSigned'])
        ->name('api.v1.files.signed');
});

// ============================================
// DEPLOYMENT WEBHOOKS AND MANUAL DEPLOY ROUTES
// ============================================
Route::prefix('webhooks')->group(function () {
    Route::get('/github', [DeploymentController::class, 'verifyWebhook']);

    Route::post('/github', [DeploymentController::class, 'handleWebhook'])
        ->middleware(['throttle:10,1', 'github-webhook']);

    Route::post('/manual-deploy', [DeploymentController::class, 'manualDeploy'])
        ->middleware('throttle:5,1');

    // Rollback endpoint
    Route::post('/rollback', [DeploymentController::class, 'rollback'])
        ->middleware('throttle:2,10');
});

// ============================================
// FALLBACK FOR UNDEFINED ROUTES
// ============================================
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'Endpoint not found',
        'timestamp' => now()->toISOString()
    ], 404);
});
