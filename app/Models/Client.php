<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Client extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'business_name',
        'first_name',
        'last_name',
        'business_type',
        'industry',
        'business_registration_number',
        'contact_person_name',
        'designation',
        'email',
        'client_type', 
        'mobile_number',
        'alternate_mobile_number',
        'address_line_1',
        'address_line_2',
        'residential_address',
        'city',
        'state',
        'country',
        'zip_code',
        'billing_name',
        'payment_term',
        'preferred_currency',
        'tax_percentage',
        'tax_id',
        'website_url',
        'logo_path',
        'client_category',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tax_percentage' => 'decimal:2',
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

    // <================== New relationship for Availability Schedules ==================>

    /**
     * Relationships
     */
    public function availabilitySchedules()
    {
        return $this->hasMany(ClientAvailabilitySchedule::class);
    }

    public function activeAvailabilitySchedule()
    {
        return $this->hasOne(ClientAvailabilitySchedule::class)
            ->where('is_active', true)
            ->latest('created_at'); // Simply get the most recently created active schedule
    }

    /**
     * Accessors & Mutators
     */
    public function getAvailableDaysAttribute(): ?array
    {
        $schedule = $this->activeAvailabilitySchedule;
        return $schedule ? $schedule->available_days : null;
    }

    public function getPreferredStartTimeAttribute(): ?string
    {
        $schedule = $this->activeAvailabilitySchedule;
        return $schedule ? $schedule->preferred_start_time : null;
    }

    public function getPreferredEndTimeAttribute(): ?string
    {
        $schedule = $this->activeAvailabilitySchedule;
        return $schedule ? $schedule->preferred_end_time : null;
    }

    public function getHasLunchBreakAttribute(): ?bool
    {
        $schedule = $this->activeAvailabilitySchedule;
        return $schedule ? $schedule->has_lunch_break : null;
    }

    public function getLunchStartAttribute(): ?string
    {
        $schedule = $this->activeAvailabilitySchedule;
        return $schedule ? $schedule->lunch_start : null;
    }

    public function getLunchEndAttribute(): ?string
    {
        $schedule = $this->activeAvailabilitySchedule;
        return $schedule ? $schedule->lunch_end : null;
    }

    /**
     * Business Logic Methods
     */
    public function isAvailableOnDate(string $date): bool
    {
        $schedule = $this->activeAvailabilitySchedule;

        if (!$schedule) {
            return false;
        }

        // Since we don't have date ranges, just check if the day of week is available
        $dayOfWeek = strtolower(Carbon::parse($date)->englishDayOfWeek);
        return $schedule->isAvailableOnDay($dayOfWeek);
    }

    public function isAvailableOnDay(string $day): bool
    {
        $schedule = $this->activeAvailabilitySchedule;

        if (!$schedule) {
            return false;
        }

        return $schedule->isAvailableOnDay($day);
    }

    public function checkAvailability(string $date, string $startTime, string $endTime): bool
    {
        $schedule = $this->activeAvailabilitySchedule;

        if (!$schedule) {
            return false;
        }

        // Check day of week
        $dayOfWeek = strtolower(Carbon::parse($date)->englishDayOfWeek);
        if (!$schedule->isAvailableOnDay($dayOfWeek)) {
            return false;
        }

        // Check time slot
        return $schedule->isTimeSlotAvailable($startTime, $endTime);
    }

    public function getNextAvailableDates(int $count = 5): array
    {
        $schedule = $this->activeAvailabilitySchedule;

        if (!$schedule) {
            return [];
        }

        return $schedule->getNextAvailableDates($count);
    }

    public function getAvailableTimeSlots(string $date): array
    {
        $schedule = $this->activeAvailabilitySchedule;

        if (!$schedule) {
            return [];
        }

        // Check day of week
        $dayOfWeek = strtolower(Carbon::parse($date)->englishDayOfWeek);
        if (!$schedule->isAvailableOnDay($dayOfWeek)) {
            return [];
        }

        // Generate time slots
        $slots = [];
        $start = Carbon::parse($schedule->preferred_start_time);
        $end = Carbon::parse($schedule->preferred_end_time);

        if ($schedule->has_lunch_break && $schedule->lunch_start && $schedule->lunch_end) {
            $lunchStart = Carbon::parse($schedule->lunch_start);
            $lunchEnd = Carbon::parse($schedule->lunch_end);
        }

        while ($start < $end) {
            $slotEnd = $start->copy()->addHour();

            // Skip lunch break
            if (isset($lunchStart) && isset($lunchEnd)) {
                if ($start->lt($lunchEnd) && $slotEnd->gt($lunchStart)) {
                    $start = $lunchEnd->copy();
                    continue;
                }
            }

            if ($slotEnd <= $end) {
                $slots[] = [
                    'start_time' => $start->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'display' => $start->format('g:i A') . ' - ' . $slotEnd->format('g:i A')
                ];
            }

            $start->addHour();
        }

        return $slots;
    }

    public function hasActiveSchedule(): bool
    {
        return $this->activeAvailabilitySchedule !== null;
    }

    /**
     * Scopes
     */
    public function scopeWithActiveSchedule($query)
    {
        return $query->whereHas('availabilitySchedules', function ($q) {
            $q->where('is_active', true);
        });
    }

    public function scopeAvailableOnDay($query, string $day)
    {
        $day = strtolower($day);

        return $query->whereHas('availabilitySchedules', function ($q) use ($day) {
            $q->where('is_active', true)
                ->whereJsonContains('available_days', $day);
        });
    }

    // Removed scopeAvailableForServiceType since service_type doesn't exist

    // <================== Availability Schedules methods Ends Here ==================>
}