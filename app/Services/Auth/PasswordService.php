<?php

namespace App\Services\Auth;

use App\Models\PasswordHistory;
use App\Models\PasswordSecuritySetting;
use App\Models\User;
use App\Models\UserSecurityLog;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;

class PasswordService
{
    /**
     * Generate a secure random password
     */
    public function generateRandomPassword(int $length = 12): string
    {
        // Define character sets
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $allChars = $uppercase . $lowercase . $numbers . $symbols;

        // Ensure at least one character from each required set
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        // Fill the rest with random characters from all sets
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password to randomize character positions
        $password = str_shuffle($password);

        return $password;
    }

    /**
     * Generate a simple random password (basic version, similar to Str::random)
     */
    public function generateSimpleRandomPassword(int $length = 12): string
    {
        return Str::random($length);
    }

    /**
     * Generate a password that meets security requirements
     */
    public function generateSecurePassword(User $user, int $length = 12): string
    {
        $settings = $this->getSecuritySettings($user);

        // Generate password and validate it
        $attempts = 0;
        $maxAttempts = 10;

        while ($attempts < $maxAttempts) {
            $password = $this->generateRandomPassword($length);

            // Check if it meets the security requirements
            $errors = $this->validatePassword($user, $password);

            if (empty($errors)) {
                return $password;
            }

            $attempts++;
        }

        // If we couldn't generate a valid password, fall back to simple random
        Log::warning('Could not generate secure password after multiple attempts, using fallback', [
            'user_id' => $user->id,
            'attempts' => $attempts
        ]);

        return $this->generateSimpleRandomPassword($length);
    }

    /**
     * Generate a temporary password for new users or password resets
     */
    public function generateTemporaryPassword(?User $user = null, int $length = 12): string
    {
        if ($user) {
            // Try to generate a password that meets user's security requirements
            try {
                return $this->generateSecurePassword($user, $length);
            } catch (\Exception $e) {
                Log::warning('Failed to generate secure password for user, using fallback', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Fallback: generate a secure random password
        return $this->generateRandomPassword($length);
    }

    /**
     * Generate and set a new password for user
     */
    public function generateAndSetNewPassword(User $user, bool $forceChange = true): array
    {
        try {
            Log::info('Generating and setting new password for user', ['user_id' => $user->id]);

            // Generate a new password
            $newPassword = $this->generateSecurePassword($user);

            // Update password with history tracking
            $success = $this->updatePasswordWithHistory($user, $newPassword);

            if (!$success) {
                throw new \Exception('Failed to update password');
            }

            // Set force password change if required
            if ($forceChange) {
                $user->force_password_change = true;
                $user->save();
            }

            Log::info('New password generated and set for user', [
                'user_id' => $user->id,
                'force_change' => $forceChange,
                'password_changed_at' => $user->password_changed_at
            ]);

            return [
                'success' => true,
                'password' => $newPassword,
                'user' => $user,
                'force_password_change' => $forceChange,
                'password_changed_at' => $user->password_changed_at
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate and set new password', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Generate a password reset token and optionally send email
     */
    public function generatePasswordResetToken(User $user, bool $sendEmail = true): array
    {
        try {
            // Generate a secure random password
            $temporaryPassword = $this->generateSecurePassword($user);

            // Create password reset record (you might have a PasswordReset model)
            $token = Str::random(60);
            $expiresAt = now()->addHours(24);

            // Here you would typically save to password_resets table
            // For now, we'll just generate the response

            $response = [
                'success' => true,
                'token' => $token,
                'expires_at' => $expiresAt,
                'temporary_password' => $sendEmail ? null : $temporaryPassword, // Don't expose if sending email
                'user_id' => $user->id,
                'email' => $user->email
            ];

            if ($sendEmail) {
                // Send password reset email with the token
                $this->sendPasswordResetEmail($user, $token, $temporaryPassword);

                Log::info('Password reset token generated and email sent', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            } else {
                Log::info('Password reset token generated', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to generate password reset token', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Send password reset email
     */
    private function sendPasswordResetEmail(User $user, string $token, string $temporaryPassword): void
    {
        // Implementation depends on your email setup
        // You would typically dispatch a job here

        Log::info('Password reset email should be sent', [
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => $token,
            'has_temp_password' => !empty($temporaryPassword)
        ]);

        // Example:
        // PasswordResetNotification::dispatch($user, $token, $temporaryPassword);
    }

    /**
     * Validate password against security settings
     */
    public function validatePassword(User $user, string $password): array
    {
        $errors = [];
        $settings = $this->getSecuritySettings($user);

        // 1. Check minimum length
        if (strlen($password) < $settings['min_length']) {
            $errors[] = "Password must be at least {$settings['min_length']} characters.";
        }

        // 2. Check uppercase
        if ($settings['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter (A-Z).";
        }

        // 3. Check lowercase
        if ($settings['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter (a-z).";
        }

        // 4. Check numbers
        if ($settings['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number (0-9).";
        }

        // 5. Check symbols
        if ($settings['require_symbols'] && !preg_match('/[\W_]/', $password)) {
            $errors[] = "Password must contain at least one special character (!@#$%^&* etc).";
        }

        return $errors;
    }

    /**
     * Validate password with all checks including history
     */
    public function validatePasswordWithHistory(User $user, string $password): array
    {
        $errors = $this->validatePassword($user, $password);

        if (empty($errors)) {
            $settings = $this->getSecuritySettings($user);

            // 6. Check against password history
            if ($settings['password_history_size'] > 0) {
                $previousPasswords = PasswordHistory::getUserHistory($user, $settings['password_history_size']);

                foreach ($previousPasswords as $history) {
                    if (Hash::check($password, $history->password_hash)) {
                        $errors[] = "You cannot reuse a previous password.";
                        break;
                    }
                }
            }

            // 7. Check if same as current password
            if (Hash::check($password, $user->password)) {
                $errors[] = "New password must be different from current password.";
            }
        }

        return $errors;
    }

    /**
     * Update password with history tracking
     */
    public function updatePasswordWithHistory(User $user, string $newPassword): bool
    {
        // Store current password in history before changing
        PasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => $user->password,
            'changed_by' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        // Update password
        $user->password = Hash::make($newPassword);
        $user->password_changed_at = now();
        $user->force_password_change = false;
        $user->failed_login_attempts = 0; // Reset failed attempts

        $success = $user->save();

        if ($success) {
            // Log password change
            UserSecurityLog::logEvent($user, 'password_changed', [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            // Send notification if enabled
            if ($this->getSecuritySettings($user)['notify_on_password_change']) {
                $this->sendPasswordChangeNotification($user);
            }
        }

        return $success;
    }

    /**
     * Get security settings for user
     */
    public function getSecuritySettings(User $user): array
    {
        try {
            // First check user's custom security settings
            if ($user->security_settings) {
                return $user->security_settings;
            }

            // Get global security settings
            $globalSettings = PasswordSecuritySetting::getGlobalSettings();

            return is_object($globalSettings) ? $globalSettings->toArray() : $globalSettings;
        } catch (\Exception $e) {
            Log::warning('Failed to load security settings, using defaults', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'min_length' => 8,
                'require_uppercase' => true,
                'require_lowercase' => true,
                'require_numbers' => true,
                'require_symbols' => true,
                'password_expiry_days' => 90,
                'password_history_size' => 5,
                'max_login_attempts' => 5,
                'lockout_duration_minutes' => 15,
                'force_password_change_on_first_login' => false,
                'notify_on_password_change' => true,
                'require_mfa' => false,
            ];
        }
    }

    /**
     * Update user password (regular password change)
     */
    public function updatePassword(User $user, array $data): bool
    {
        try {
            Log::info('Updating password for user', ['user_id' => $user->id]);

            // Check if account is locked
            if ($user->isAccountLocked()) {
                throw new \Exception('Account is temporarily locked. Please try again later.');
            }

            // Verify current password
            if (!Hash::check($data['current_password'], $user->password)) {
                $user->incrementFailedLoginAttempts();
                throw new \Exception('Current password is incorrect');
            }

            // Reset failed attempts on successful current password verification
            $user->resetFailedLoginAttempts();

            // Validate new password
            $errors = $this->validatePasswordWithHistory($user, $data['new_password']);

            if (!empty($errors)) {
                throw new \Exception(implode(' ', $errors));
            }

            // Update password with history tracking
            $success = $this->updatePasswordWithHistory($user, $data['new_password']);

            if (!$success) {
                throw new \Exception('Failed to update password');
            }

            Log::info('Password updated successfully', [
                'user_id' => $user->id,
                'password_changed_at' => $user->password_changed_at,
                'ip_address' => request()->ip()
            ]);

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
     * Force password change (for expired or first-time login)
     */
    public function forceChangePassword(User $user, string $newPassword): bool
    {
        try {
            Log::info('Force changing password for user', ['user_id' => $user->id]);

            // Only allow if password change is forced or expired
            if (!$user->shouldForcePasswordChange()) {
                throw new \Exception('Password change is not required at this time');
            }

            // Validate new password
            $errors = $this->validatePasswordWithHistory($user, $newPassword);

            if (!empty($errors)) {
                throw new \Exception(implode(' ', $errors));
            }

            // Update password with history tracking
            $success = $this->updatePasswordWithHistory($user, $newPassword);

            if (!$success) {
                throw new \Exception('Failed to change password');
            }

            Log::info('Force password change successful', [
                'user_id' => $user->id,
                'password_changed_at' => $user->password_changed_at,
                'ip_address' => request()->ip()
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Force password change failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Send password change notification
     */
    private function sendPasswordChangeNotification(User $user): void
    {
        try {
            Notification::send($user, new PasswordChangedNotification([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toISOString()
            ]));

            Log::info('Password change notification sent', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            // Don't fail the password update if notification fails
            Log::warning('Failed to send password change notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Unlock user account
     */
    public function unlockAccount(User $user): bool
    {
        try {
            Log::info('Unlocking account for user', ['user_id' => $user->id]);

            if (!$user->isAccountLocked()) {
                Log::warning('Account is not locked, nothing to unlock', ['user_id' => $user->id]);
                return false;
            }

            // Reset failed login attempts and unlock account
            $user->failed_login_attempts = 0;
            $user->account_locked_until = null;
            $success = $user->save();

            if ($success) {
                // Log the unlock event
                UserSecurityLog::logEvent($user, 'account_unlocked', [
                    'unlocked_by' => auth()->id() ?? 'system',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent()
                ]);

                Log::info('Account unlocked successfully', ['user_id' => $user->id]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Failed to unlock account', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get user security information
     */
    public function getUserSecurityInfo(User $user): array
    {
        try {
            $settings = $this->getSecuritySettings($user);

            // Calculate days until password expiry
            $passwordExpiryDays = 0;
            $isPasswordExpired = false;

            if ($user->password_changed_at && $settings['password_expiry_days'] > 0) {
                $expiryDate = $user->password_changed_at->addDays($settings['password_expiry_days']);
                $now = now();

                if ($expiryDate->isPast()) {
                    $isPasswordExpired = true;
                    $passwordExpiryDays = 0;
                } else {
                    $passwordExpiryDays = $now->diffInDays($expiryDate);
                }
            }

            // Check if account is locked
            $isAccountLocked = $user->isAccountLocked();

            // Calculate lockout remaining time if locked
            $lockoutRemainingMinutes = 0;
            if ($isAccountLocked && $user->account_locked_until) {
                $now = now();
                if ($user->account_locked_until->isFuture()) {
                    $lockoutRemainingMinutes = $now->diffInMinutes($user->account_locked_until);
                }
            }

            // Get recent password history count
            $passwordHistoryCount = PasswordHistory::where('user_id', $user->id)
                ->count();

            // Get last security events
            $lastSecurityEvents = UserSecurityLog::where('user_id', $user->id)
                ->orderBy('event_time', 'desc')
                ->take(5)
                ->get(['event_type', 'event_time', 'ip_address'])
                ->toArray();

            return [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->full_name ?? $user->email,
                ],
                'password_info' => [
                    'last_changed_at' => $user->password_changed_at?->toISOString(),
                    'days_until_expiry' => $passwordExpiryDays,
                    'is_expired' => $isPasswordExpired,
                    'force_change_required' => $user->shouldForcePasswordChange(),
                    'password_history_count' => $passwordHistoryCount,
                ],
                'account_security' => [
                    'is_locked' => $isAccountLocked,
                    'failed_login_attempts' => $user->failed_login_attempts,
                    'max_login_attempts' => $settings['max_login_attempts'],
                    'lockout_remaining_minutes' => $lockoutRemainingMinutes,
                    'last_login_at' => $user->last_login_at?->toISOString(),
                ],
                'security_settings' => [
                    'min_length' => $settings['min_length'],
                    'require_uppercase' => $settings['require_uppercase'],
                    'require_lowercase' => $settings['require_lowercase'],
                    'require_numbers' => $settings['require_numbers'],
                    'require_symbols' => $settings['require_symbols'],
                    'password_expiry_days' => $settings['password_expiry_days'],
                    'password_history_size' => $settings['password_history_size'],
                    'max_login_attempts' => $settings['max_login_attempts'],
                    'lockout_duration_minutes' => $settings['lockout_duration_minutes'],
                    'force_password_change_on_first_login' => $settings['force_password_change_on_first_login'],
                    'notify_on_password_change' => $settings['notify_on_password_change'],
                    'require_mfa' => $settings['require_mfa'],
                ],
                'recent_security_events' => $lastSecurityEvents,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get user security info', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            // Return basic info even if there's an error
            return [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
                'password_info' => [
                    'last_changed_at' => $user->password_changed_at?->toISOString(),
                    'force_change_required' => $user->shouldForcePasswordChange(),
                ],
                'account_security' => [
                    'is_locked' => $user->isAccountLocked(),
                    'failed_login_attempts' => $user->failed_login_attempts,
                ],
                'error' => 'Failed to load full security information',
            ];
        }
    }

    /**
     * Validate password strength (for registration/initial password)
     */
    public function validatePasswordStrength(string $password): void
    {
        $errors = [];

        // Get global/default settings
        try {
            $settings = PasswordSecuritySetting::getGlobalSettings();

            if (is_object($settings)) {
                $settings = $settings->toArray();
            } elseif (!is_array($settings)) {
                // Fallback to default settings
                $settings = [
                    'min_length' => 8,
                    'require_uppercase' => true,
                    'require_lowercase' => true,
                    'require_numbers' => true,
                    'require_symbols' => true,
                ];
            }
        } catch (\Exception $e) {
            // If there's any error getting settings, use defaults
            Log::warning('Failed to get password security settings, using defaults', [
                'error' => $e->getMessage()
            ]);

            $settings = [
                'min_length' => 8,
                'require_uppercase' => true,
                'require_lowercase' => true,
                'require_numbers' => true,
                'require_symbols' => true,
            ];
        }

        // 1. Check minimum length
        if (strlen($password) < $settings['min_length']) {
            $errors[] = "Password must be at least {$settings['min_length']} characters.";
        }

        // 2. Check uppercase
        if ($settings['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter (A-Z).";
        }

        // 3. Check lowercase
        if ($settings['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter (a-z).";
        }

        // 4. Check numbers
        if ($settings['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number (0-9).";
        }

        // 5. Check symbols
        if ($settings['require_symbols'] && !preg_match('/[\W_]/', $password)) {
            $errors[] = "Password must contain at least one special character (!@#$%^&* etc).";
        }

        // Throw exception if there are errors
        if (!empty($errors)) {
            throw new \Exception(implode(' ', $errors));
        }
    }


    /**
     * Reset failed login attempts
     */
    public function resetFailedAttempts(User $user): bool
    {
        try {
            $user->failed_login_attempts = 0;
            $user->account_locked_until = null;
            return $user->save();
        } catch (\Exception $e) {
            Log::error('Failed to reset login attempts', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if password matches any in history
     */
    public function isPasswordInHistory(User $user, string $password, int $historySize = null): bool
    {
        try {
            $settings = $this->getSecuritySettings($user);
            $historySize = $historySize ?? $settings['password_history_size'] ?? 5;

            if ($historySize <= 0) {
                return false;
            }

            $previousPasswords = PasswordHistory::getUserHistory($user, $historySize);

            foreach ($previousPasswords as $history) {
                if (Hash::check($password, $history->password_hash)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to check password history', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(User $user, string $eventType, array $metadata = []): void
    {
        try {
            UserSecurityLog::logEvent($user, $eventType, array_merge([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ], $metadata));
        } catch (\Exception $e) {
            Log::error('Failed to log security event', [
                'user_id' => $user->id,
                'event_type' => $eventType,
                'error' => $e->getMessage()
            ]);
        }
    }
}
