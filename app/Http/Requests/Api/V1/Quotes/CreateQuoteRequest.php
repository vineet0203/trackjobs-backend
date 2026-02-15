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
            // Section 1: Quote Details
            'quote_number' => 'sometimes|string|unique:quotes,quote_number',
            'title' => 'required|string|max:255',
            'client_id' => 'required|exists:clients,id',
            'equity_status' => 'sometimes|in:pending,approved,rejected,not_applicable',
            'currency' => 'sometimes|string|size:3',
            
            // Section 2: Line Items - CHANGE THIS FROM 'items' TO 'line_items'
            'line_items' => 'required|array|min:1',
            'line_items.*.item_name' => 'required|string|max:255',
            'line_items.*.description' => 'nullable|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|between:0,100',
            'line_items.*.package_id' => 'nullable|exists:packages,id',
            
            // Section 3: Pricing Summary
            'discount' => 'nullable|numeric|min:0',
            'deposit_required' => 'sometimes|boolean',
            'deposit_type' => 'required_if:deposit_required,true|in:percentage,fixed',
            'deposit_amount' => 'required_if:deposit_required,true|numeric|min:0',
            
            // Section 4: Client Approval
            'approval_status' => 'sometimes|in:pending,accepted,rejected',
            'client_signature' => 'nullable|string',
            'approval_date' => 'nullable|date',
            'approval_action_date' => 'nullable|date',
            
            // Section 5: Follow Ups & Reminders
            'reminders' => 'sometimes|array',
            'reminders.*.follow_up_schedule' => 'required_with:reminders|date',
            'reminders.*.reminder_type' => 'required_with:reminders|in:email,sms,notification',
            'reminders.*.reminder_status' => 'sometimes|in:scheduled,sent,cancelled',
            
            // Section 6: Conversion to Job
            'can_convert_to_job' => 'sometimes|boolean', // CHANGE THIS FROM 'convert_to_job'
            
            // Meta
            'notes' => 'nullable|string',
            'expires_at' => 'nullable|date|after:today',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Quote title is required.',
            'client_id.required' => 'Client is required.',
            'client_id.exists' => 'Selected client does not exist.',
            'line_items.required' => 'At least one line item is required.', // UPDATED
            'line_items.*.item_name.required' => 'Item name is required for all items.', // UPDATED
            'line_items.*.quantity.required' => 'Item quantity is required.', // UPDATED
            'line_items.*.quantity.min' => 'Quantity must be at least 1.', // UPDATED
            'line_items.*.unit_price.required' => 'Unit price is required.', // UPDATED
            'line_items.*.unit_price.min' => 'Unit price cannot be negative.', // UPDATED
            'deposit_type.required_if' => 'Deposit type is required when deposit is required.',
            'deposit_amount.required_if' => 'Deposit amount is required when deposit is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'quote_number' => $this->quote_number ?? 'QT-' . now()->format('Ymd') . '-' . rand(1000, 9999),
            'equity_status' => $this->equity_status ?? 'not_applicable',
            'currency' => $this->currency ?? 'USD',
            'discount' => $this->discount ?? 0,
            'deposit_required' => !is_null($this->deposit_required) ? filter_var($this->deposit_required, FILTER_VALIDATE_BOOLEAN) : false,
            'can_convert_to_job' => !is_null($this->can_convert_to_job) ? filter_var($this->can_convert_to_job, FILTER_VALIDATE_BOOLEAN) : true,
            'approval_status' => $this->approval_status ?? 'pending',
        ];

        // Map line_items to items for the service (if your service expects 'items')
        if ($this->has('line_items')) {
            $data['items'] = $this->line_items;
        }

        $this->merge($data);
    }
}