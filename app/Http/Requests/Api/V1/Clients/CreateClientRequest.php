<?php

namespace App\Http\Requests\Api\V1\Clients;

use App\Services\File\FileValidationRules;
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
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        $rules = [
            /*
            |--------------------------------------------------------------------------
            | Core
            |--------------------------------------------------------------------------
            */
            'vendor_id' => 'required|exists:vendors,id',
            'client_type' => 'required|in:commercial,residential',

            /*
            |--------------------------------------------------------------------------
            | Contact Fields (common for both types - REQUIRED)
            |--------------------------------------------------------------------------
            */
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('clients')->where(
                    fn($q) => $q->where('vendor_id', $vendorId)
                ),
            ],
            'mobile_number' => 'required|string|max:20',
            'alternate_mobile_number' => 'nullable|string|max:20',

            /*
            |--------------------------------------------------------------------------
            | Address Object (common for both types - REQUIRED)
            |--------------------------------------------------------------------------
            */
            'address' => 'required|array',
            'address.address_line_1' => 'required|string|max:191',
            'address.address_line_2' => 'nullable|string|max:191',
            'address.city' => 'required|string|max:191',
            'address.state' => 'required|string|max:191',
            'address.country' => 'required|string|max:191',
            'address.zip_code' => 'required|string|max:20',

            /*
            |--------------------------------------------------------------------------
            | Residential Fields (only allowed if residential)
            |--------------------------------------------------------------------------
            */
            'first_name' => [
                'required_if:client_type,residential',
                'nullable',
                'string',
                'max:191',
                'exclude_unless:client_type,residential'
            ],
            'last_name' => [
                'nullable',
                'string',
                'max:191',
                'exclude_unless:client_type,residential'
            ],

            /*
            |--------------------------------------------------------------------------
            | Commercial Business Fields (only allowed if commercial)
            |--------------------------------------------------------------------------
            */
            'business_name' => [
                'required_if:client_type,commercial',
                'nullable',
                'string',
                'max:191',
                'exclude_unless:client_type,commercial',
                Rule::unique('clients')->where(
                    fn($q) => $q->where('vendor_id', $vendorId)
                ),
            ],

            'business_type' => [
                'required_if:client_type,commercial',
                'nullable',
                'exclude_unless:client_type,commercial',
                'in:sole_proprietorship,partnership,corporation,non_profit,government,other'
            ],

            'industry' => [
                'required_if:client_type,commercial',
                'nullable',
                'exclude_unless:client_type,commercial',
                'in:technology,retail,healthcare,finance,manufacturing,construction,education,hospitality,transportation,other'
            ],

            'business_registration_number' => 'nullable|string|max:191|exclude_unless:client_type,commercial',
            'contact_person_name' => [
                'required_if:client_type,commercial',
                'nullable',
                'string',
                'max:191',
                'exclude_unless:client_type,commercial'
            ],
            'designation' => [
                'required_if:client_type,commercial',
                'nullable',
                'exclude_unless:client_type,commercial',
                'in:owner,ceo,manager,director,accountant,admin,employee,other'
            ],

            /*
            |--------------------------------------------------------------------------
            | Payment Object (Commercial Only)
            |--------------------------------------------------------------------------
            */
            'payment' => 'nullable|array|exclude_unless:client_type,commercial',
            'payment.billing_name' => 'nullable|string|max:191',
            'payment.payment_term' => [
                'required_if:client_type,commercial',
                'nullable',
                'exclude_unless:client_type,commercial',
                'in:due_on_receipt,net_7,net_15,net_30,net_45,net_60'
            ],
            'payment.preferred_currency' => [
                'required_if:client_type,commercial',
                'nullable',
                'string',
                'size:3',
                'exclude_unless:client_type,commercial',
                'in:inr,usd,eur,gbp,aed,sgd,cad,aud'
            ],

            /*
            |--------------------------------------------------------------------------
            | Tax Object
            |--------------------------------------------------------------------------
            */
            'tax' => 'nullable|array',
            'tax.tax_percentage' => 'nullable|numeric|between:0,100',

            /*
            |--------------------------------------------------------------------------
            | Additional Details
            |--------------------------------------------------------------------------
            */
            'website_url' => 'nullable|url|max:191',
            'client_category' => 'nullable|in:premium,regular,vip,strategic,new,at_risk',
            'notes' => 'nullable|string',

            /*
            |--------------------------------------------------------------------------
            | Availability Schedule (REQUIRED for both types)
            |--------------------------------------------------------------------------
            */
            'availability_schedule' => 'required|array',
            'availability_schedule.available_days' => 'required|array|min:1',
            'availability_schedule.available_days.*' => 'required|string|in:' . implode(',', $days),
            'availability_schedule.preferred_start_time' => 'required|date_format:H:i',
            'availability_schedule.preferred_end_time' => [
                'required',
                'date_format:H:i',
                'after:availability_schedule.preferred_start_time'
            ],
            'availability_schedule.has_lunch_break' => 'required|boolean',
            'availability_schedule.lunch_start' => [
                'nullable',
                'date_format:H:i',
                'required_if:availability_schedule.has_lunch_break,true'
            ],
            'availability_schedule.lunch_end' => [
                'nullable',
                'date_format:H:i',
                'required_if:availability_schedule.has_lunch_break,true',
                'after:availability_schedule.lunch_start'
            ],
            'availability_schedule.notes' => 'nullable|string|max:1000',
        ];

        /*
        |--------------------------------------------------------------------------
        | Logo Temp Validation
        |--------------------------------------------------------------------------
        */
        $logoRules = FileValidationRules::tempId(
            fieldName: 'logo_temp_id',
            allowedTypes: FileValidationRules::getAllowedMimeTypes('images'),
            maxSizeKb: FileValidationRules::getSizeLimits('images')
        );

        return array_merge($rules, $logoRules);
    }

    protected function prepareForValidation(): void
    {
        $rawLogoId = $this->input('logo_temp_id') ?: $this->input('logoTempId');

        // Prepare base data
        $data = [
            'vendor_id' => $this->route('vendorId') ?? $this->vendor_id,
            'logo_temp_id' => $rawLogoId ?: null,
            'website_url' => $this->prepareWebsiteUrl($this->website_url),
            'client_category' => $this->client_category ?? 'regular',
        ];

        // Handle tax percentage
        if ($this->has('tax') && isset($this->tax['tax_percentage'])) {
            $data['tax_percentage'] = (float)$this->tax['tax_percentage'];
        }

        // Handle payment currency case
        if ($this->has('payment') && isset($this->payment['preferred_currency'])) {
            $data['preferred_currency'] = strtolower($this->payment['preferred_currency']);
        }

        // Handle availability schedule
        if ($this->has('availability_schedule')) {
            $schedule = is_array($this->availability_schedule)
                ? $this->availability_schedule
                : json_decode($this->availability_schedule, true);

            if (isset($schedule['has_lunch_break'])) {
                $schedule['has_lunch_break'] = filter_var($schedule['has_lunch_break'], FILTER_VALIDATE_BOOLEAN);
            }

            $data['availability_schedule'] = $schedule;
        }

        $this->merge($data);
    }

    private function prepareWebsiteUrl(?string $url): ?string
    {
        if (!$url) return null;

        if (str_starts_with($url, 'tmp_')) return null;

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return 'https://' . $url;
        }

        return $url;
    }
}