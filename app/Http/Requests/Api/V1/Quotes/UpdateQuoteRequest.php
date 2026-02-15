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
            // Section 1: Quote Details
            'title' => 'sometimes|string|max:255',
            'client_id' => 'sometimes|exists:clients,id',
            'client_name' => 'sometimes|string|max:191',
            'client_email' => 'sometimes|email|max:191',
            'equity_status' => 'sometimes|in:pending,approved,rejected,not_applicable',
            'currency' => 'sometimes|string|size:3',

            'line_items' => 'sometimes|array',
            'line_items.*.id' => 'nullable|exists:quote_items,id',
            'line_items.*.item_name' => 'required_with:line_items|string|max:255',
            'line_items.*.description' => 'nullable|string',
            'line_items.*.quantity' => 'required_with:line_items|integer|min:1',
            'line_items.*.unit_price' => 'required_with:line_items|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|between:0,100',
            'line_items.*.package_id' => 'nullable|exists:packages,id',
            'line_items.*._delete' => 'sometimes|boolean',

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
            'reminders.*.id' => 'nullable|sometimes',
            'reminders.*.follow_up_schedule' => 'required_with:reminders|date',
            'reminders.*.reminder_type' => 'required_with:reminders|in:email,sms,notification',
            'reminders.*.reminder_status' => 'sometimes|in:scheduled,sent,cancelled',
            'reminders.*._delete' => 'sometimes|boolean',

            // Section 6: Conversion to Job
            'can_convert_to_job' => 'sometimes|boolean',
            'job_id' => 'nullable|exists:jobs,id',

            // Status Updates
            'status' => 'sometimes|in:draft,sent,viewed,expired',

            // Meta
            'notes' => 'nullable|string',
            'expires_at' => 'nullable|date|after:today',
        ];
    }

    public function messages(): array
    {
        return [
            'client_email.email' => 'Please enter a valid email address.',
            'items.*.item_name.required_with' => 'Item name is required for all items.',
            'items.*.quantity.required_with' => 'Item quantity is required.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.unit_price.required_with' => 'Unit price is required.',
            'items.*.unit_price.min' => 'Unit price cannot be negative.',
            'deposit_type.required_if' => 'Deposit type is required when deposit is required.',
            'deposit_amount.required_if' => 'Deposit amount is required when deposit is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'discount' => $this->discount ?? 0,
            'deposit_required' => !is_null($this->deposit_required) ? filter_var($this->deposit_required, FILTER_VALIDATE_BOOLEAN) : null,
            'can_convert_to_job' => !is_null($this->can_convert_to_job) ? filter_var($this->can_convert_to_job, FILTER_VALIDATE_BOOLEAN) : null,
        ];

        // Map line_items to items if present
        if ($this->has('line_items')) {
            $data['items'] = $this->line_items;
        }

        $this->merge($data);
    }
}
