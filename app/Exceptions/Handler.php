<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException; // ADD THIS

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        // Handle API requests
        if ($request->expectsJson() || $request->is('api/*') || strpos($request->path(), 'api/') === 0) {
            return $this->handleApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    protected function handleApiException($request, Throwable $exception)
    {
        // Authentication Exceptions (for non-JWT auth failures)
        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please log in.',
                'timestamp' => now()->toISOString(),
                'code' => 401,
            ], 401);
        }

        // Authorization Exceptions (for policy/ability failures) - ADD THIS
        if ($exception instanceof AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'timestamp' => now()->toISOString(),
                'code' => 403,
                'error_details' => config('app.debug') ? $exception->getMessage() : null,
            ], 403);
        }

        // Unauthorized HTTP Exceptions (for 403 errors)
        if ($exception instanceof UnauthorizedHttpException) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'Unauthorized access.',
                'timestamp' => now()->toISOString(),
                'code' => 403,
            ], 403);
        }

        // Validation Exceptions
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $exception->errors(),
                'timestamp' => now()->toISOString(),
                'code' => 422,
            ], 422);
        }

        // Not Found Exceptions
        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'The requested endpoint was not found.',
                'timestamp' => now()->toISOString(),
                'code' => 404,
            ], 404);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'The requested resource was not found.',
                'timestamp' => now()->toISOString(),
                'code' => 404,
            ], 404);
        }

        // Method Not Allowed
        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'The HTTP method is not allowed for this endpoint.',
                'timestamp' => now()->toISOString(),
                'code' => 405,
            ], 405);
        }

        // For other exceptions, return JSON with debug info in development
        $response = [
            'success' => false,
            'message' => 'An error occurred.',
            'timestamp' => now()->toISOString(),
            'code' => 500,
        ];

        if (config('app.debug')) {
            $response['debug'] = [
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return response()->json($response, 500);
    }
}