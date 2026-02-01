<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'business_name',
        'business_type',
        'industry',
        'business_registration_number',
        'contact_person_name',
        'designation',
        'email',
        'mobile_number',
        'alternate_mobile_number',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'zip_code',
        'billing_name',
        'same_as_business_address',
        'billing_address_line_1',
        'billing_address_line_2',
        'billing_city',
        'billing_state',
        'billing_country',
        'billing_zip_code',
        'payment_term',
        'custom_payment_term',
        'preferred_currency',
        'tax_percentage',
        'tax_id',
        'website_url',
        'logo_path',
        'client_category',
        'notes',
        'status',
        'is_verified',
        'verified_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'same_as_business_address' => 'boolean',
        'is_verified' => 'boolean',
        'tax_percentage' => 'decimal:2',
        'verified_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $dates = ['deleted_at'];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}