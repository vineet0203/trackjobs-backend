<?php

namespace App\Http\Requests\Api\V1\Quotes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $quoteId = $this->route('quote');

        return [
            // Quote Details
            'title' => 'sometimes|string|max:255',
            'client_name' => 'sometimes|string|max:191',
            'client_email' => 'sometimes|email|max:191',
            
            // Pricing
            'discount' => 'nullable|numeric|min:0',
            'deposit_type' => 'sometimes|in:none,percentage,fixed,default',
            'deposit_amount' => 'nullable|required_if:deposit_type,fixed|numeric|min:0',
            'deposit_percentage' => 'nullable|required_if:deposit_type,percentage|numeric|between:1,100',
            
            // Status Updates
            'status' => 'sometimes|in:draft,sent,pending,approved,rejected,expired',
            'client_signature' => 'nullable|string',
            
            // Follow-ups
            'follow_up_at' => 'nullable|date',
            'reminder_type' => 'nullable|in:none,email,sms',
            'follow_up_status' => 'nullable|in:scheduled,completed,cancelled',
            'notes' => 'nullable|string',
            
            // Line Items (full replace or partial update)
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'nullable|exists:quote_items,id',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|between:0,100',
            'items.*.package_id' => 'nullable|exists:packages,id',
            'items.*._delete' => 'sometimes|boolean', // For deleting items
        ];
    }

    public function messages(): array
    {
        return [
            'client_email.email' => 'Please enter a valid email address.',
            'items.*.name.required' => 'Item name is required.',
            'items.*.quantity.required' => 'Item quantity is required.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.unit_price.required' => 'Unit price is required.',
            'items.*.unit_price.min' => 'Unit price cannot be negative.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discount' => $this->discount ? (float) $this->discount : 0,
            'deposit_amount' => $this->deposit_amount ? (float) $this->deposit_amount : null,
            'deposit_percentage' => $this->deposit_percentage ? (float) $this->deposit_percentage : null,
            'follow_up_at' => $this->follow_up_at ?: null,
            'reminder_type' => $this->reminder_type ?: 'none',
        ]);
    }
}