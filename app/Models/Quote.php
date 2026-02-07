<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quote_number',
        'title',
        'client_name',
        'client_email',
        'subtotal',
        'discount',
        'total_amount',
        'deposit_type',
        'deposit_amount',
        'status',
        'client_signature',
        'approved_at',
        'sent_at',
        'follow_up_at',
        'reminder_type',
        'follow_up_status',
        'expires_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'follow_up_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Relationship with quote items
     */
    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }

    /**
     * Relationship with creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with updater
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for active quotes
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'sent', 'pending']);
    }

    /**
     * Scope for pending approval
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved quotes
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Check if quote can be edited
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    /**
     * Check if quote can be sent
     */
    public function canBeSent(): bool
    {
        return $this->status === 'draft' && $this->items()->count() > 0;
    }

    /**
     * Calculate totals from items
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->items()->sum('total');
        
        $this->update([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal - $this->discount,
        ]);
    }

    /**
     * Generate quote number
     */
    public static function generateQuoteNumber(): string
    {
        $prefix = 'QT-';
        $lastQuote = self::withTrashed()
            ->where('quote_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastQuote) {
            return $prefix . str_pad('1', 5, '0', STR_PAD_LEFT);
        }

        $lastNumber = (int) str_replace($prefix, '', $lastQuote->quote_number);
        $nextNumber = $lastNumber + 1;

        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}