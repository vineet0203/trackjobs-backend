<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;

class RegistrationValidationService
{
    public function __construct(
        private PasswordService $passwordService
    ) {}

    public function validateRegistrationData(array $data): void
    {
        $this->validateRequiredFields($data);
        $this->validateBusinessData($data);
        $this->validatePersonalData($data);
        $this->validateUniqueness($data);
        $this->validatePasswordStrength($data['password']);
        $this->validateTermsAccepted($data);
    }

    private function validateRequiredFields(array $data): void
    {
        $required = [
            'business_name',
            'website_name',
            'full_name',
            'email',
            'mobile_number',
            'terms_accepted',
            'password',
        ];

        $missing = [];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new \Exception("Required fields are missing: " . implode(', ', $missing));
        }
    }

    private function validateBusinessData(array $data): void
    {
        // Validate business name format
        if (strlen($data['business_name']) < 2) {
            throw new \Exception("Business name must be at least 2 characters long");
        }

        // Validate website format
        if (!filter_var($data['website_name'], FILTER_VALIDATE_URL)) {
            throw new \Exception("Please enter a valid website URL");
        }

        // Validate business type if provided
        if (isset($data['business_type']) && !in_array($data['business_type'], ['plumbing', 'carpentry', 'electrical', 'cleaning', 'other'])) {
            throw new \Exception("Invalid business type. Must be plumbing, carpentry, electrical, cleaning, or other");
        }

        // Validate service description length if provided
        if (isset($data['service_description']) && strlen($data['service_description']) > 500) {
            throw new \Exception("Service description must not exceed 500 characters");
        }
    }

    private function validatePersonalData(array $data): void
    {
        // Validate full name
        if (strlen($data['full_name']) < 2) {
            throw new \Exception("Full name must be at least 2 characters long");
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Please enter a valid email address");
        }

        // Validate mobile number format (basic validation)
        if (!preg_match('/^[0-9\s\+\-\(\)]{10,20}$/', $data['mobile_number'])) {
            throw new \Exception("Please enter a valid mobile number (10-20 digits)");
        }

        // Split full name and validate parts
        $nameParts = explode(' ', $data['full_name'], 2);
        if (count($nameParts) < 2) {
            Log::warning('Full name may not have both first and last name', ['full_name' => $data['full_name']]);
        }

        if (empty($nameParts[0])) {
            throw new \Exception("First name is required");
        }
    }

    private function validateUniqueness(array $data): void
    {
        // Check if business name already exists
        if (Vendor::where('business_name', $data['business_name'])->exists()) {
            throw new \Exception('Business name "' . $data['business_name'] . '" already exists');
        }

        // Check if email already exists
        if (User::where('email', $data['email'])->exists()) {
            throw new \Exception('Email "' . $data['email'] . '" is already registered');
        }

        // Check if mobile number already exists in vendor table (optional)
        if (Vendor::where('mobile_number', $data['mobile_number'])->exists()) {
            throw new \Exception('Mobile number "' . $data['mobile_number'] . '" is already registered');
        }
    }

    private function validatePasswordStrength(string $password): void
    {
        $this->passwordService->validatePasswordStrength($password);
    }

    private function validateTermsAccepted(array $data): void
    {
        if (!isset($data['terms_accepted']) || !$data['terms_accepted']) {
            throw new \Exception('You must accept the terms and conditions to register');
        }
    }

    /**
     * Validate complete registration data (comprehensive check)
     */
    public function validateCompleteRegistration(array $data): void
    {
        try {
            // Step 1: Basic required fields
            $this->validateRequiredFields($data);

            // Step 2: Business information
            $this->validateBusinessData($data);

            // Step 3: Personal information
            $this->validatePersonalData($data);

            // Step 4: Terms acceptance
            $this->validateTermsAccepted($data);

            // Step 6: Password strength
            $this->validatePasswordStrength($data['password']);

            // Step 7: Uniqueness checks (last because it's DB-intensive)
            $this->validateUniqueness($data);

        } catch (\Exception $e) {
            Log::error('Registration validation failed', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data)
            ]);
            throw $e;
        }
    }
}