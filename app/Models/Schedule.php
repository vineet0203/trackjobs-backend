<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends BaseModel
{
    use SoftDeletes;

    protected $table = 'schedules';

    protected $fillable = [
        'vendor_id',
        'job_id',
        'crew_id',
        'start_datetime',
        'end_datetime',
        'priority',
        'status',
        'notes',
        'is_multi_day',
        'is_recurring',
        'notify_client',
        'notify_crew',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'is_multi_day' => 'boolean',
        'is_recurring' => 'boolean',
        'notify_client' => 'boolean',
        'notify_crew' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'crew_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scopes
     */
    public function scopeByVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->where('start_datetime', '>=', $startDate)
            ->where('end_datetime', '<=', $endDate);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_datetime', '>=', now())
            ->where('status', 'scheduled')
            ->orderBy('start_datetime');
    }
}
