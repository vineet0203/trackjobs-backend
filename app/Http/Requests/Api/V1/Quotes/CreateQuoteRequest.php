<?php

namespace App\Http\Requests\Api\V1\Quotes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Quote Details
            'title' => 'required|string|max:255',
            'client_name' => 'required|string|max:191',
            'client_email' => 'required|email|max:191',
            
            // Pricing
            'discount' => 'nullable|numeric|min:0',
            'deposit_type' => 'required|in:none,percentage,fixed,default',
            'deposit_amount' => 'nullable|required_if:deposit_type,fixed|numeric|min:0',
            'deposit_percentage' => 'nullable|required_if:deposit_type,percentage|numeric|between:1,100',
            
            // Follow-ups
            'follow_up_at' => 'nullable|date',
            'reminder_type' => 'nullable|in:none,email,sms',
            'notes' => 'nullable|string',
            
            // Line Items
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|between:0,100',
            'items.*.package_id' => 'nullable|exists:packages,id',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Quote title is required.',
            'client_name.required' => 'Client name is required.',
            'client_email.required' => 'Client email is required.',
            'client_email.email' => 'Please enter a valid email address.',
            'items.required' => 'At least one line item is required.',
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
            'deposit_amount' => $this->deposit_amount ? (float) $this->deposit_amount : null,
            'deposit_percentage' => $this->deposit_percentage ? (float) $this->deposit_percentage : null,
            'follow_up_at' => $this->follow_up_at ?: null,
            'reminder_type' => $this->reminder_type ?: 'none',
        ]);
    }
}