<?php

namespace App\Http\Requests\Api\V1\Clients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vendorId = $this->route('vendorId') ?? $this->vendor_id;

        return [
            // Vendor relationship (should come from route/auth context, not form)
            'vendor_id' => 'required|exists:vendors,id',
            //'user_id' => 'nullable|exists:users,id',

            // 1. Basic Business Information
            'business_name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('clients')->where(function ($query) use ($vendorId) {
                    return $query->where('vendor_id', $vendorId);
                })
            ],
            'business_type' => 'required|in:individual,sole_proprietorship,partnership,llc,corporation,non_profit,government,other',
            'industry' => 'nullable|in:technology,retail,healthcare,finance,manufacturing,construction,education,hospitality,transportation,other',
            'business_registration_number' => 'nullable|string|max:191',

            // 2. Primary Contact Information
            'contact_person_name' => 'required|string|max:191',
            'designation' => 'nullable|in:owner,ceo,manager,director,accountant,admin,purchasing_manager,other',
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('clients')->where(function ($query) use ($vendorId) {
                    return $query->where('vendor_id', $vendorId);
                })
            ],
            'mobile_number' => 'required|string|max:20',
            'alternate_mobile_number' => 'nullable|string|max:20',

            // 3. Business Address
            'address_line_1' => 'required|string|max:191',
            'address_line_2' => 'required|string|max:191',
            'city' => 'required|string|max:191',
            'state' => 'required|string|max:191',
            'country' => 'required|string|max:191',
            'zip_code' => 'required|string|max:20',

            // 4. Billing & Financial Details
            'billing_name' => 'nullable|string|max:191',
            'same_as_business_address' => 'required|boolean',
            'billing_address_line_1' => 'nullable|required_if:same_as_business_address,false|string|max:191',
            'billing_address_line_2' => 'nullable|required_if:same_as_business_address,false|string|max:191',
            'billing_city' => 'nullable|required_if:same_as_business_address,false|string|max:191',
            'billing_state' => 'nullable|required_if:same_as_business_address,false|string|max:191',
            'billing_country' => 'nullable|required_if:same_as_business_address,false|string|max:191',
            'billing_zip_code' => 'nullable|required_if:same_as_business_address,false|string|max:191',
            'payment_term' => 'required|in:net_7,net_15,net_30,net_45,net_60,due_on_receipt,custom',
            'custom_payment_term' => 'nullable|string|max:191',
            'preferred_currency' => 'required|in:USD,EUR,GBP,INR,CAD,AUD,JPY,CNY,AED,SGD,MYR,THB,VND,PHP,IDR,PKR,BDT,LKR,NPR,MMK,KHR,LAK,BND,HKD,KRW,TWD,MXN,BRL,ARS,CLP,COP,PEN,UYU,ZAR,NGN,EGP,MAD,DZD,TND,QAR,SAR,OMR,KWD,BHD',
            'tax_percentage' => 'nullable|numeric|between:0,100',
            'tax_id' => 'nullable|string|max:191',

            // 5. Additional Business Details
            'website_url' => 'nullable|url|max:191',
            // Temporary upload ID for logo
            'logo_temp_id' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    // Add custom validation for temp_id
                    if ($value && !preg_match('/^tmp_[a-zA-Z0-9_]+$/', $value)) {
                        $fail('Invalid temporary upload ID format.');
                    }
                },
            ],
            'client_category' => 'required|in:premium,regular,vip,strategic,new,at_risk',
            'notes' => 'nullable|string',

            // 6. Status & Actions
            'status' => 'required|in:active,inactive,suspended,archived',
            'is_verified' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            // Basic Business Information
            'business_name.required' => 'Business name is required.',
            'business_name.unique' => 'A client with this business name already exists for this vendor.',
            'business_type.required' => 'Business type is required.',
            'business_type.in' => 'Please select a valid business type.',
            'industry.in' => 'Please select a valid industry.',

            // Primary Contact Information
            'contact_person_name.required' => 'Contact person name is required.',
            'designation.in' => 'Please select a valid designation.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'A client with this email already exists for this vendor.',
            'mobile_number.required' => 'Mobile number is required.',

            // Business Address
            'address_line_1.required' => 'Address line 1 is required.',
            'address_line_2.required' => 'Address line 2 is required.',
            'city.required' => 'City is required.',
            'state.required' => 'State is required.',
            'country.required' => 'Country is required.',
            'zip_code.required' => 'ZIP code is required.',

            // Billing & Financial Details
            'same_as_business_address.required' => 'Please specify if billing address is same as business address.',
            'billing_address_line_1.required_if' => 'Billing address line 1 is required when billing address is different.',
            'billing_address_line_2.required_if' => 'Billing address line 2 is required when billing address is different.',
            'billing_city.required_if' => 'Billing city is required when billing address is different.',
            'billing_state.required_if' => 'Billing state is required when billing address is different.',
            'billing_country.required_if' => 'Billing country is required when billing address is different.',
            'billing_zip_code.required_if' => 'Billing ZIP code is required when billing address is different.',
            'payment_term.required' => 'Payment term is required.',
            'payment_term.in' => 'Please select a valid payment term.',
            'preferred_currency.required' => 'Preferred currency is required.',
            'preferred_currency.in' => 'Selected currency is not supported.',
            'tax_percentage.between' => 'Tax percentage must be between 0 and 100.',
            'website_url.url' => 'Please enter a valid website URL.',

            'client_category.required' => 'Client category is required.',
            'client_category.in' => 'Please select a valid client category.',

            // Status & Actions
            'status.required' => 'Status is required.',
            'status.in' => 'Please select a valid status.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'vendor_id' => $this->route('vendorId') ?? $this->vendor_id,
            'same_as_business_address' => filter_var($this->same_as_business_address ?? true, FILTER_VALIDATE_BOOLEAN),
            'is_verified' => filter_var($this->is_verified ?? false, FILTER_VALIDATE_BOOLEAN),
            'tax_percentage' => $this->tax_percentage ? (float) $this->tax_percentage : null,
            'logo_temp_id' => $this->logo_temp_id ?: ($this->logoTempId ?: null),

            // Clean up website URL
            'website_url' => $this->website_url ?
                (strpos($this->website_url, 'http') !== 0 ? 'https://' . $this->website_url : $this->website_url)
                : null,
        ]);
    }
}
