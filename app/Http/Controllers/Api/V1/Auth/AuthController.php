<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Api\V1\Auth\ForceChangePasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\UpdatePasswordRequest;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\PasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    public function __construct(
        private AuthService $authService,
        private PasswordService $passwordService
    ) {}

    /**
     * Login user and return JWT token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $token = $this->authService->login($request->validated());

            return $this->successResponse($token, 'Login successful');
        } catch (\Exception $e) {
            Log::warning('Login failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($e->getMessage(), 401);
        }
    }

    /**
     * Register a new user and company
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // Force JSON response
        $request->headers->set('Accept', 'application/json');

        try {
            // Throw ValidationException if validation fails
            $validatedData = $request->validated();
            $user = $this->authService->register($validatedData);
            // Use the ApiResponse trait's createdResponse method
            return $this->createdResponse(
                new UserResource($user),
                'Registration successful!'
            );
        } catch (ValidationException $e) {
            Log::error('Validation Exception:', ['errors' => $e->errors()]);

            // Use the ApiResponse trait's validationErrorResponse method
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            Log::error('Registration Exception:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Registration failed: ' . $e->getMessage(), 400);
        } finally {
            Log::info('=== REGISTRATION END ===');
        }
    }

    /** 
     * Update user's password
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $validated = $request->validated();

            // Use AuthService which internally uses PasswordService
            $success = $this->authService->updatePassword($user, $validated);

            if ($success) {
                return $this->successResponse(null, 'Password updated successfully');
            }

            return $this->errorResponse('Failed to update password', 500);
        } catch (\Exception $e) {
            Log::error('Failed to update password', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);

            return $this->errorResponse($e->getMessage(), 400);
        }
    }


    /**
     * Force password change (for expired or first-time login)
     */
    public function forceChangePassword(ForceChangePasswordRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();

            // Only allow if password change is forced or expired
            if (!$user->shouldForcePasswordChange()) {
                return $this->errorResponse('Password change is not required at this time', 400);
            }

            $validated = $request->validated();

            // Use PasswordService for force password change
            $success = $this->passwordService->forceChangePassword($user, $validated['new_password']);

            if ($success) {
                return $this->successResponse(null, 'Password changed successfully. You may now log in with your new password.');
            }

            return $this->errorResponse('Failed to change password', 500);
        } catch (\Exception $e) {
            Log::error('Force password change failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);

            return $this->errorResponse($e->getMessage(), 400);
        }
    }


    /**
     * Get password security information
     */
    public function getPasswordSecurityInfo(): JsonResponse
    {
        try {
            $user = auth()->user();

            // Use PasswordService to get security information
            $securityInfo = $this->passwordService->getUserSecurityInfo($user);

            return $this->successResponse(
                $securityInfo,
                'Password security information retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to get password security info', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to retrieve security information', 500);
        }
    }

    /**
     * Get user security logs
     */
    public function getSecurityLogs(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $limit = $request->get('limit', 50);
            $page = $request->get('page', 1);
            $eventType = $request->get('event_type');

            $query = $user->securityLogs()
                ->orderBy('event_time', 'desc');

            if ($eventType) {
                $query->where('event_type', $eventType);
            }

            $logs = $query->paginate($limit, ['*'], 'page', $page);

            return $this->successResponse(
                $logs,
                'Security logs retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to get security logs', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to retrieve security logs', 500);
        }
    }

    /**
     * Log the user out
     */
    public function logout(): JsonResponse
    {
        try {
            $this->authService->logout();

            return $this->successResponse(null, 'Successfully logged out');
        } catch (\Exception $e) {
            Log::error('Logout failed', ['error' => $e->getMessage()]);
            return $this->errorResponse('Logout failed', 500);
        }
    }


    /**
     * Refresh JWT token
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = $this->authService->refresh();

            return $this->successResponse($token, 'Token refreshed successfully');
        } catch (\Exception $e) {
            Log::error('Token refresh failed', ['error' => $e->getMessage()]);
            return $this->errorResponse('Token refresh failed', 401);
        }
    }

    /**
     * Get current authenticated user
     */
    // GET /api/user/me?include_roles=true&include_permissions=true
    public function me(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            // Force load necessary relationships
            $user->load([
                'company' => function ($query) {
                    $query->select(['id', 'name', 'website', 'status', 'is_active']);
                },
                'employee' => function ($query) {
                    $query->select([
                        'id',
                        'user_id',
                        'employee_code',
                        'first_name',
                        'last_name',
                        'manager_id',
                        'status'
                    ]);
                }
            ]);

            // Add query parameters for optional includes
            $includeRoles = $request->boolean('include_roles', false);
            $includePermissions = $request->boolean('include_permissions', false);
            $includeTokenInfo = $request->boolean('include_token_info', false);
            $includeSecurityInfo = $request->boolean('include_security_info', false);

            // Add security information if requested - USE PasswordService
            $securityInfo = [];
            if ($includeSecurityInfo) {
                // Get security info from PasswordService
                $securityData = $this->passwordService->getUserSecurityInfo($user);

                $securityInfo = [
                    'password_expiry_days' => $securityData['password_info']['days_until_expiry'] ?? 0,
                    'force_password_change' => $securityData['password_info']['force_change_required'] ?? false,
                    'account_locked' => $securityData['account_security']['is_locked'] ?? false,
                    'failed_login_attempts' => $securityData['account_security']['failed_login_attempts'] ?? 0,
                    'last_password_change' => $securityData['password_info']['last_changed_at'] ?? null,
                ];
            }

            // Create resource with context
            $resource = new UserResource($user);
            $resource->additional([
                'meta' => [
                    'requested_includes' => [
                        'roles' => $includeRoles,
                        'permissions' => $includePermissions,
                        'token_info' => $includeTokenInfo,
                        'security_info' => $includeSecurityInfo,
                    ],
                    'security' => $securityInfo,
                    'timestamp' => now()->toISOString(),
                ]
            ]);

            return $this->successResponse(
                $resource,
                'User profile retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Get user profile failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to retrieve user profile', 500);
        }
    }


    /**
     * Logout other sessions
     */
    // public function logoutOtherSessions(Request $request): JsonResponse
    // {
    //     try {
    //         // This would require JWT blacklist implementation
    //         // For now, we'll just return success

    //         return $this->successResponse(
    //             null,
    //             'All other sessions will be logged out. You may need to log in again on other devices.'
    //         );
    //     } catch (\Exception $e) {
    //         Log::error('Failed to logout other sessions', [
    //             'user_id' => auth()->id(),
    //             'error' => $e->getMessage()
    //         ]);
    //         return $this->errorResponse('Failed to logout other sessions', 500);
    //     }
    // }


    /**
 * Unlock user account (admin only)
 */
    // public function unlockAccount(Request $request, $userId): JsonResponse
    // {
    //     try {
    //         $currentUser = auth()->user();

    //         // Only platform admins or company owners can unlock accounts
    //         if (
    //             !$currentUser->isPlatformSuperAdmin() &&
    //             !$currentUser->isCompanyOwner()
    //         ) {
    //             return $this->errorResponse('Unauthorized to perform this action', 403);
    //         }

    //         $user = User::findOrFail($userId);

    //         // Check if user belongs to same company (for company owners)
    //         if ($currentUser->isCompanyOwner() && $user->company_id !== $currentUser->company_id) {
    //             return $this->errorResponse('Cannot unlock account from another company', 403);
    //         }

    //         if (!$user->isAccountLocked()) {
    //             return $this->errorResponse('Account is not locked', 400);
    //         }

    //         // Use PasswordService to unlock account
    //         $success = $this->passwordService->unlockAccount($user);

    //         if ($success) {
    //             Log::info('Account unlocked by admin', [
    //                 'admin_id' => $currentUser->id,
    //                 'user_id' => $user->id,
    //                 'ip' => $request->ip()
    //             ]);

    //             return $this->successResponse(null, 'Account unlocked successfully');
    //         }

    //         return $this->errorResponse('Failed to unlock account', 500);
    //     } catch (\Exception $e) {
    //         Log::error('Account unlock failed', [
    //             'admin_id' => auth()->id(),
    //             'user_id' => $userId,
    //             'error' => $e->getMessage()
    //         ]);

    //         return $this->errorResponse($e->getMessage(), 400);
    //     }
    // }

}
