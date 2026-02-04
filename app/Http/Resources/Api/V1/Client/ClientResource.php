<?php

namespace App\Http\Resources\Api\V1\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            //'user_id' => $this->user_id,
            'business_name' => $this->business_name,
            'business_type' => $this->business_type,
            'industry' => $this->industry,
            'business_registration_number' => $this->business_registration_number,
            'contact_person_name' => $this->contact_person_name,
            'designation' => $this->designation,
            'email' => $this->email,
            'mobile_number' => $this->mobile_number,
            'alternate_mobile_number' => $this->alternate_mobile_number,
            'address' => [
                'address_line_1' => $this->address_line_1,
                'address_line_2' => $this->address_line_2,
                'city' => $this->city,
                'state' => $this->state,
                'country' => $this->country,
                'zip_code' => $this->zip_code,
            ],
            'billing' => [
                'billing_name' => $this->billing_name,
                'same_as_business_address' => (bool)$this->same_as_business_address,
                'address_line_1' => $this->billing_address_line_1,
                'address_line_2' => $this->billing_address_line_2,
                'city' => $this->billing_city,
                'state' => $this->billing_state,
                'country' => $this->billing_country,
                'zip_code' => $this->billing_zip_code,
            ],
            'payment' => [
                'payment_term' => $this->payment_term,
                //'custom_payment_term' => $this->custom_payment_term,
                'preferred_currency' => $this->preferred_currency,
            ],
            'tax' => [
                'tax_percentage' => $this->tax_percentage,
                'tax_id' => $this->tax_id,
            ],
            'website_url' => $this->website_url,
            'logo_path' => $this->logo_path,
            'client_category' => $this->client_category,
            'notes' => $this->notes,
            'status' => $this->status,
            //'is_verified' => (bool)$this->is_verified,
            //'verified_at' => $this->verified_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
