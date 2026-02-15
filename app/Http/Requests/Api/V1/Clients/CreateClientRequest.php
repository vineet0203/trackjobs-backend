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
            | Residential Fields (only allowed if residential)
            |--------------------------------------------------------------------------
            */
            'first_name' => 'nullable|string|max:191|exclude_unless:client_type,residential',
            'last_name'  => 'nullable|string|max:191|exclude_unless:client_type,residential',
            'address'    => 'nullable|string|exclude_unless:client_type,residential',

            /*
            |--------------------------------------------------------------------------
            | Contact Fields (common)
            |--------------------------------------------------------------------------
            */
            'email' => [
                'nullable',
                'email',
                'max:191',
                Rule::unique('clients')->where(
                    fn($q) => $q->where('vendor_id', $vendorId)
                ),
            ],
            'mobile_number' => 'nullable|string|max:20',
            'alternate_mobile_number' => 'nullable|string|max:20',

            /*
            |--------------------------------------------------------------------------
            | Commercial Business Fields
            |--------------------------------------------------------------------------
            */
            'business_name' => [
                'nullable',
                'string',
                'max:191',
                'exclude_unless:client_type,commercial',
                Rule::unique('clients')->where(
                    fn($q) => $q->where('vendor_id', $vendorId)
                ),
            ],

            'business_type' => [
                'nullable',
                'exclude_unless:client_type,commercial',
                'in:individual,sole_proprietorship,partnership,llc,corporation,non_profit,government,other'
            ],

            'industry' => [
                'nullable',
                'exclude_unless:client_type,commercial',
                'in:technology,retail,healthcare,finance,manufacturing,construction,education,hospitality,transportation,other'
            ],

            'business_registration_number' => 'nullable|string|max:191|exclude_unless:client_type,commercial',

            'contact_person_name' => 'nullable|string|max:191|exclude_unless:client_type,commercial',
            'designation' => 'nullable|exclude_unless:client_type,commercial|in:owner,ceo,manager,director,accountant,admin,purchasing_manager,other',

            /*
            |--------------------------------------------------------------------------
            | Business Address (Commercial Only)
            |--------------------------------------------------------------------------
            */
            'address_line_1' => 'nullable|string|max:191|exclude_unless:client_type,commercial',
            'address_line_2' => 'nullable|string|max:191|exclude_unless:client_type,commercial',
            'city'           => 'nullable|string|max:191|exclude_unless:client_type,commercial',
            'state'          => 'nullable|string|max:191|exclude_unless:client_type,commercial',
            'country'        => 'nullable|string|max:191|exclude_unless:client_type,commercial',
            'zip_code'       => 'nullable|string|max:20|exclude_unless:client_type,commercial',

            /*
            |--------------------------------------------------------------------------
            | Billing & Financial (Commercial Only)
            |--------------------------------------------------------------------------
            */
            'billing_name' => 'nullable|string|max:191|exclude_unless:client_type,commercial',

            'payment_term' => [
                'nullable',
                'exclude_unless:client_type,commercial',
                'in:net_7,net_15,net_30,net_45,net_60,due_on_receipt'
            ],

            'preferred_currency' => 'nullable|string|size:3|exclude_unless:client_type,commercial',
            'tax_percentage' => 'nullable|numeric|between:0,100|exclude_unless:client_type,commercial',

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
            | Availability Schedule (Mostly Residential)
            |--------------------------------------------------------------------------
            */
            'availability_schedule' => 'sometimes|array',
            'availability_schedule.available_days' => 'sometimes|array|min:1',
            'availability_schedule.available_days.*' => 'string|in:' . implode(',', $days),
            'availability_schedule.preferred_start_time' => 'sometimes|date_format:H:i',
            'availability_schedule.preferred_end_time' => 'sometimes|date_format:H:i|after:availability_schedule.preferred_start_time',
            'availability_schedule.has_lunch_break' => 'sometimes|boolean',
            'availability_schedule.lunch_start' => 'nullable|date_format:H:i',
            'availability_schedule.lunch_end' => 'nullable|date_format:H:i',
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

        $this->merge([
            'vendor_id' => $this->route('vendorId') ?? $this->vendor_id,
            'tax_percentage' => $this->tax_percentage ? (float)$this->tax_percentage : null,
            'logo_temp_id' => $rawLogoId ?: null,
            'website_url' => $this->prepareWebsiteUrl($this->website_url),
            'client_category' => $this->client_category ?? 'regular',
        ]);

        if ($this->has('availability_schedule')) {
            $this->merge([
                'availability_schedule' => is_array($this->availability_schedule)
                    ? $this->availability_schedule
                    : json_decode($this->availability_schedule, true),
            ]);
        }
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
