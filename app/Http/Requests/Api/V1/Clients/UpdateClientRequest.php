<?php

namespace App\Http\Requests\Api\V1\Clients;

use App\Services\File\FileValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendorId = $this->route('vendorId');
        $clientId = $this->route('clientId');

        $clientExists = DB::table('clients')
            ->where('vendor_id', $vendorId)
            ->where('id', $clientId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$clientExists) {
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
        $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

        return [

            /*
            |--------------------------------------------------------------------------
            | Client Type
            |--------------------------------------------------------------------------
            */
            'client_type' => 'sometimes|in:commercial,residential',

            /*
            |--------------------------------------------------------------------------
            | Residential Fields (Optional)
            |--------------------------------------------------------------------------
            */
            'first_name' => 'nullable|string|max:191|exclude_unless:client_type,residential',
            'last_name'  => 'nullable|string|max:191|exclude_unless:client_type,residential',
            'address'    => 'nullable|string|exclude_unless:client_type,residential',

            /*
            |--------------------------------------------------------------------------
            | Commercial Fields (Optional)
            |--------------------------------------------------------------------------
            */
            'business_name' => [
                'nullable',
                'string',
                'max:191',
                'exclude_unless:client_type,commercial',
                Rule::unique('clients')
                    ->where(fn($q) => $q->where('vendor_id',$vendorId)
                        ->where('id','!=',$clientId)
                        ->whereNull('deleted_at'))
            ],

            'business_type' => 'nullable|in:individual,sole_proprietorship,partnership,llc,corporation,non_profit,government,other|exclude_unless:client_type,commercial',
            'industry' => 'nullable|in:technology,retail,healthcare,finance,manufacturing,construction,education,hospitality,transportation,other',
            'business_registration_number' => 'nullable|string|max:191',

            /*
            |--------------------------------------------------------------------------
            | Contact Info (Common)
            |--------------------------------------------------------------------------
            */
            'contact_person_name' => 'nullable|string|max:191',
            'designation' => 'nullable|in:owner,ceo,manager,director,accountant,admin,purchasing_manager,other',

            'email' => [
                'nullable',
                'email',
                'max:191',
                Rule::unique('clients')
                    ->where(fn($q)=>$q->where('vendor_id',$vendorId)
                        ->where('id','!=',$clientId)
                        ->whereNull('deleted_at'))
            ],

            'mobile_number' => 'nullable|string|max:20',
            'alternate_mobile_number' => 'nullable|string|max:20',

            /*
            |--------------------------------------------------------------------------
            | Address (Commercial Only)
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
            | Billing / Finance
            |--------------------------------------------------------------------------
            */
            'billing_name' => 'nullable|string|max:191',
            'payment_term' => 'nullable|in:net_7,net_15,net_30,net_45,net_60,due_on_receipt',
            'preferred_currency' => 'nullable|string|max:5',
            'tax_percentage' => 'nullable|numeric|between:0,100',

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
            | Logo Upload
            |--------------------------------------------------------------------------
            */
            ...FileValidationRules::tempId(
                'logo_temp_id',
                FileValidationRules::getAllowedMimeTypes('images'),
                FileValidationRules::getSizeLimits('images')
            ),

            'remove_logo' => 'nullable|boolean',

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            'status' => 'nullable|in:active,inactive,suspended,archived',
            'is_verified' => 'nullable|boolean',

            /*
            |--------------------------------------------------------------------------
            | Availability Schedule
            |--------------------------------------------------------------------------
            */
            'availability_schedule' => 'sometimes|array',
            'availability_schedule.available_days' => 'sometimes|array|min:1',
            'availability_schedule.available_days.*' => 'string|in:' . implode(',', $days),
            'availability_schedule.preferred_start_time' => 'sometimes|date_format:H:i',
            'availability_schedule.preferred_end_time' => 'sometimes|date_format:H:i|after:availability_schedule.preferred_start_time',
            'availability_schedule.has_lunch_break' => 'sometimes|boolean',
            'availability_schedule.lunch_start' => 'nullable|date_format:H:i',
            'availability_schedule.lunch_end' => 'nullable|date_format:H:i|after:availability_schedule.lunch_start',
            'availability_schedule.notes' => 'nullable|string|max:1000',
        ];
    }

    protected function prepareForValidation(): void
    {
        $rawLogoId = $this->input('logo_temp_id') ?: $this->input('logoTempId');

        $this->merge([
            'logo_temp_id' => $rawLogoId ?: null,
            'tax_percentage' => $this->tax_percentage ? (float)$this->tax_percentage : null,
            'website_url' => $this->prepareWebsiteUrl($this->website_url),
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
        if (!$url || str_starts_with($url, 'tmp_')) {
            return null;
        }

        if (!str_starts_with($url,'http://') && !str_starts_with($url,'https://')) {
            return 'https://' . $url;
        }

        return $url;
    }

    public function messages(): array
    {
        return [
            'business_name.unique' => 'Business name already exists.',
            'email.unique' => 'Email already exists.',
            'tax_percentage.between' => 'Tax must be between 0-100.',
            'logo_temp_id.*' => 'Invalid logo upload ID.',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($this->has('logo_temp_id')) {
            $validated['logo_temp_id'] = $this->input('logo_temp_id');
        }

        return $validated;
    }
}
