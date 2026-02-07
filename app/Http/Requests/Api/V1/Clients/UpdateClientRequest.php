<?php

namespace App\Http\Requests\Api\V1\Clients;

use App\Services\File\FileValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendorId = $this->route('vendorId');
        $clientId = $this->route('clientId');

        // Check if client exists
        $clientExists = DB::table('clients')
            ->where('vendor_id', $vendorId)
            ->where('id', $clientId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$clientExists) {
            // Throw a 404 response
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Client not found.',
                    'timestamp' => now()->toIso8601String(),
                    'code' => 404,
                    'error_code' => 'CLIENT_NOT_FOUND'
                ], 404)
            );
        }

        return true;
    }


    public function rules(): array
    {
        $vendorId = $this->route('vendorId');
        $clientId = $this->route('clientId');

        return [
            // Basic Business Information
            'business_name' => [
                'sometimes',
                'required',
                'string',
                'max:191',
                Rule::unique('clients')->where(function ($query) use ($vendorId, $clientId) {
                    return $query->where('vendor_id', $vendorId)
                        ->where('id', '!=', $clientId) // Explicitly exclude current record
                        ->whereNull('deleted_at');
                })
            ],
            'business_type' => 'sometimes|required|in:individual,sole_proprietorship,partnership,llc,corporation,non_profit,government,other',
            'industry' => 'nullable|in:technology,retail,healthcare,finance,manufacturing,construction,education,hospitality,transportation,other',
            'business_registration_number' => 'nullable|string|max:191',

            // Primary Contact Information
            'contact_person_name' => 'sometimes|required|string|max:191',
            'designation' => 'nullable|in:owner,ceo,manager,director,accountant,admin,purchasing_manager,other',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:191',
                Rule::unique('clients')->where(function ($query) use ($vendorId, $clientId) {
                    return $query->where('vendor_id', $vendorId)
                        ->where('id', '!=', $clientId) // Explicitly exclude current record
                        ->whereNull('deleted_at');
                })
            ],
            'mobile_number' => 'sometimes|required|string|max:20',
            'alternate_mobile_number' => 'nullable|string|max:20',

            // Business Address
            'address_line_1' => 'sometimes|required|string|max:191',
            'address_line_2' => 'sometimes|required|string|max:191',
            'city' => 'sometimes|required|string|max:191',
            'state' => 'sometimes|required|string|max:191',
            'country' => 'sometimes|required|string|max:191',
            'zip_code' => 'sometimes|required|string|max:20',

            // Billing & Financial Details
            'billing_name' => 'nullable|string|max:191',
            'same_as_business_address' => 'sometimes|boolean',
            'billing_address_line_1' => 'nullable|string|max:191',
            'billing_address_line_2' => 'nullable|string|max:191',
            'billing_city' => 'nullable|string|max:191',
            'billing_state' => 'nullable|string|max:191',
            'billing_country' => 'nullable|string|max:191',
            'billing_zip_code' => 'nullable|string|max:191',
            'payment_term' => 'sometimes|required|in:net_7,net_15,net_30,net_45,net_60,due_on_receipt,custom',
            'preferred_currency' => 'sometimes|required|in:USD,EUR,GBP,INR,CAD,AUD,JPY,CNY,AED,SGD,MYR,THB,VND,PHP,IDR,PKR,BDT,LKR,NPR,MMK,KHR,LAK,BND,HKD,KRW,TWD,MXN,BRL,ARS,CLP,COP,PEN,UYU,ZAR,NGN,EGP,MAD,DZD,TND,QAR,SAR,OMR,KWD,BHD',
            'tax_percentage' => 'nullable|numeric|between:0,100',
            'tax_id' => 'nullable|string|max:191',

            // Additional Business Details
            'website_url' => [
                'nullable',
                'string',
                'max:191',
                function ($attribute, $value, $fail) {
                    if ($value && !$this->isValidUrl($value)) {
                        $fail('Please enter a valid website URL.');
                    }
                },
            ],

            // Logo validation using reusable rules
            ...FileValidationRules::tempId(
                fieldName: 'logo_temp_id',
                allowedTypes: FileValidationRules::getAllowedMimeTypes('images'),
                maxSizeKb: FileValidationRules::getSizeLimits('images')
            ),

            'remove_logo' => 'nullable|boolean',
            'client_category' => 'sometimes|required|in:premium,regular,vip,strategic,new,at_risk',
            'notes' => 'nullable|string',

            // Status & Actions
            'status' => 'sometimes|required|in:active,inactive,suspended,archived',
            'is_verified' => 'nullable|boolean',
        ];
    }

    /**
     * Custom URL validation
     */
    private function isValidUrl(string $url): bool
    {
        // If it starts with tmp_, it's likely a temp_id, not a URL
        if (str_starts_with($url, 'tmp_')) {
            return false;
        }

        // Add protocol if missing
        if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'https://' . $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    protected function prepareForValidation(): void
    {
        // Get the raw input value for logo_temp_id
        $rawLogoId = $this->input('logo_temp_id') ?: $this->input('logoTempId');

        $this->merge([
            'same_as_business_address' => isset($this->same_as_business_address) ?
                filter_var($this->same_as_business_address, FILTER_VALIDATE_BOOLEAN) :
                null,
            'is_verified' => isset($this->is_verified) ?
                filter_var($this->is_verified, FILTER_VALIDATE_BOOLEAN) :
                null,
            'remove_logo' => isset($this->remove_logo) ?
                filter_var($this->remove_logo, FILTER_VALIDATE_BOOLEAN) :
                null,

            // Use raw input value
            'logo_temp_id' => $rawLogoId ?: null,

            'tax_percentage' => $this->tax_percentage ? (float) $this->tax_percentage : null,

            // Prepare website URL
            'website_url' => $this->prepareWebsiteUrl($this->website_url),
        ]);
    }

    /**
     * Prepare website URL - add https:// if missing and not a temp_id
     */
    private function prepareWebsiteUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        // Check if it looks like a temp_id
        if (str_starts_with($url, 'tmp_')) {
            return null; // This is likely a temp_id, not a URL
        }

        // Add https:// if no protocol specified
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return 'https://' . $url;
        }

        return $url;
    }

    public function messages(): array
    {
        return [
            'business_name.unique' => 'A client with this business name already exists for this vendor.',
            'email.unique' => 'A client with this email already exists for this vendor.',
            'website_url.url' => 'Please enter a valid website URL (e.g., https://example.com).',
            'tax_percentage.between' => 'Tax percentage must be between 0 and 100.',

            'logo_temp_id.*' => 'Invalid or expired temporary upload ID for logo.',
        ];
    }
}
