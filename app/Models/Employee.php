<?php

namespace App\Models;

use App\Models\Relations\EmployeeRelations;
use App\Models\Scopes\EmployeeScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Employee extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vendor_id',
        'company_id',
        'employee_code',
        'first_name',
        'manager_id',
        'last_name',
        'middle_name',
        'preferred_name',
        'phone',
        'personal_email',
        'profile_image',
        'status',
        'created_by',
        'updated_by'
    ];

}
