<?php

namespace App\Http\Requests\Api\V1\Clients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
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
                Rule::unique('clients')->where(function ($query) use ($vendorId) {
                    return $query->where('vendor_id', $vendorId);
                })->ignore($clientId)
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
                Rule::unique('clients')->where(function ($query) use ($vendorId) {
                    return $query->where('vendor_id', $vendorId);
                })->ignore($clientId)
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
            //'custom_payment_term' => 'nullable|string|max:191',
            'preferred_currency' => 'sometimes|required|in:USD,EUR,GBP,INR,CAD,AUD,JPY,CNY,AED,SGD,MYR,THB,VND,PHP,IDR,PKR,BDT,LKR,NPR,MMK,KHR,LAK,BND,HKD,KRW,TWD,MXN,BRL,ARS,CLP,COP,PEN,UYU,ZAR,NGN,EGP,MAD,DZD,TND,QAR,SAR,OMR,KWD,BHD',
            'tax_percentage' => 'nullable|numeric|between:0,100',
            'tax_id' => 'nullable|string|max:191',

            // Additional Business Details
            'website_url' => 'nullable|url|max:191',
            // Temporary upload ID for logo
            'logo_temp_id' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value && !preg_match('/^tmp_[a-zA-Z0-9_]+$/', $value)) {
                        $fail('Invalid temporary upload ID format.');
                    }
                },
            ],
            'remove_logo' => 'nullable|boolean',
            'client_category' => 'sometimes|required|in:premium,regular,vip,strategic,new,at_risk',
            'notes' => 'nullable|string',

            // Status & Actions
            'status' => 'sometimes|required|in:active,inactive,suspended,archived',
            'is_verified' => 'nullable|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
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
            'logo_temp_id' => $this->logo_temp_id ?: ($this->logoTempId ?: null),
            'tax_percentage' => $this->tax_percentage ? (float) $this->tax_percentage : null,
        ]);
    }
}
