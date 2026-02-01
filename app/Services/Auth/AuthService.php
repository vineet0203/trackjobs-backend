<?php

namespace App\Services\Auth;


use App\Http\Resources\Api\V1\User\UserResource;
use App\Models\User;
use App\Models\UserSecurityLog;
use App\Services\Role\RoleService;
use Illuminate\Support\Facades\Log;


class AuthService
{
    public function __construct(
        private PasswordService $passwordService,
        private RegistrationService $registrationService

    ) {}

    /**
     * Login user and return JWT token with account lockout handling
     */
    public function login(array $credentials): array
    {
        // Step 1: Find user by email
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            // Don't reveal if user exists for security
            throw new \Exception('Invalid credentials');
        }

        // Step 2: Block system users
        if ($user->is_system) {
            throw new \Exception('This account is a system account and cannot be used to sign in.');
        }

        // Step 3: Check if account is already locked
        if ($user->isAccountLocked()) {
            throw new \Exception('Account is temporarily locked. Please try again later or contact administrator.');
        }

        // Step 4: Check if user is active
        if (!$user->is_active) {
            throw new \Exception('Account is deactivated. Please contact administrator.');
        }

        // Step 5: Check if vendor is active (if applicable)
        if ($user->vendor_id && $user->vendor) {
            if ($user->vendor->status !== 'active') {
                throw new \Exception('Vendor account is not active');
            }
        }

        // Step 6: Attempt authentication
        $token = auth()->attempt($credentials);

        if (!$token) {
            // Log failed login attempt
            if (class_exists(UserSecurityLog::class)) {
                UserSecurityLog::logEvent($user, 'login_failed', [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'failed_attempts' => $user->failed_login_attempts + 1
                ]);
            }

            // Increment failed login attempts
            $user->incrementFailedLoginAttempts();

            // Check if account got locked after this attempt
            if ($user->isAccountLocked()) {
                // Get lockout duration from service
                $lockoutMinutes = $this->passwordService->getSecuritySettings($user)['lockout_duration_minutes'] ?? 15;
                throw new \Exception('Too many failed login attempts. Account has been locked for ' . $lockoutMinutes . ' minutes.');
            }

            // Check remaining attempts
            $maxAttempts = $this->passwordService->getSecuritySettings($user)['max_login_attempts'] ?? 5;
            $remainingAttempts = $maxAttempts - $user->failed_login_attempts;

            if ($remainingAttempts > 0) {
                throw new \Exception('Invalid credentials. ' . $remainingAttempts . ' attempt(s) remaining.');
            } else {
                throw new \Exception('Invalid credentials. Account locked.');
            }
        }

        // Step 7: Login successful - reset failed attempts
        $user->resetFailedLoginAttempts();

        // Step 8: Update last login info
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        // Step 9: Log successful login
        if (class_exists(UserSecurityLog::class)) {
            UserSecurityLog::logEvent($user, 'login_success', [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Register a new user and vendor account
     */
    public function register(array $data): User
    {
        return $this->registrationService->registerVendor($data);
    }

    /**
     * Update user password
     */
    public function updatePassword(User $user, array $data): bool
    {
        try {
            Log::info('Updating password for user', ['user_id' => $user->id]);

            // Check if user should force password change
            if ($user->shouldForcePasswordChange()) {
                Log::warning('User should use force password change endpoint', ['user_id' => $user->id]);
                throw new \Exception('Please use the force password change endpoint');
            }

            // Use PasswordService to update password
            $success = $this->passwordService->updatePassword($user, $data);

            if (!$success) {
                throw new \Exception('Failed to update password');
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update password', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        $user = auth()->user();

        // Log logout event
        if ($user && class_exists(UserSecurityLog::class)) {
            UserSecurityLog::logEvent($user, 'logout', [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
        }

        auth()->logout();
    }

    /**
     * Refresh JWT token
     */
    public function refresh(): array
    {
        $token = auth()->refresh();
        return $this->respondWithToken($token);
    }

    /**
     * Get token response
     */
    private function respondWithToken(string $token): array
    {
        $user = auth()->user()->load([
            'vendor',
            'roles.permissions',
        ]);

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => new UserResource($user),
        ];
    }


    /**
     * Resend verification email
     */
    public function resendVerification(string $email): bool
    {
        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                return true; // Don't reveal if user exists
            }

            // Implement resend verification logic

            Log::info('Verification email resend requested', [
                'user_id' => $user->id,
                'email' => $email
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to resend verification email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send password reset link
     */
    public function sendPasswordResetLink(string $email): bool
    {
        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                Log::warning('Password reset requested for non-existent email', ['email' => $email]);
                return true; // Return true for security (don't reveal if user exists)
            }

            // Generate reset token
            $token = app('auth.password.broker')->createToken($user);

            // You can implement email sending here
            Log::info('Password reset link generated', [
                'user_id' => $user->id,
                'email' => $email,
                'token' => $token
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send password reset link', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Reset password with token
     */
    public function resetPassword(array $data): bool
    {
        try {
            // Implement password reset logic
            // This would use Laravel's password reset functionality

            Log::info('Password reset requested', [
                'email' => $data['email'] ?? 'unknown',
                'ip' => request()->ip()
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to reset password', [
                'email' => $data['email'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verify email
     */
    public function verifyEmail(string $token): bool
    {
        try {
            // Implement email verification logic

            Log::info('Email verification requested', ['token' => $token]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to verify email', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
