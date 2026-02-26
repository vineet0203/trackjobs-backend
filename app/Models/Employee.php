<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends BaseModel
{
    use SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'vendor_id',
        'employee_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'email',
        'mobile_number',
        'address',
        'designation',
        'department',
        'reporting_manager_id',
        'role',
        'is_active',
        'profile_photo_path',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the vendor that owns the employee
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the reporting manager
     */
    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    /**
     * Get the employees reporting to this employee
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    /**
     * Get the user who created this employee
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this employee
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Scope a query to only include active employees
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by department
     */
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope a query to filter by designation
     */
    public function scopeByDesignation($query, $designation)
    {
        return $query->where('designation', $designation);
    }
}