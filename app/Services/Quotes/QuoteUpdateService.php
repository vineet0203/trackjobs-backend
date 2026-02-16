<?php

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\QuoteReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuoteUpdateService
{
    /**
     * Update a quote
     */
    public function update(Quote $quote, array $data, int $updatedBy): Quote
    {
        if (!$quote->canBeEdited()) {
            throw new \Exception('Quote cannot be edited in its current status.');
        }

        DB::beginTransaction();

        try {
            // Fields that can be directly updated
            $fillableFields = [
                'title',
                'client_id',
                'equity_status',
                'currency',
                'discount',
                'deposit_required',
                'deposit_type',
                'deposit_amount',
                'approval_status',
                'client_signature',
                'approval_date',
                'approval_action_date',
                'can_convert_to_job',
                'notes',
                'status',
                'expires_at'
            ];

            $updateData = [];
            foreach ($fillableFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Handle status updates
            if (isset($data['status'])) {
                $updateData = array_merge($updateData, $this->handleStatusUpdate($quote, $data['status']));
            }

            // Add updated_by
            $updateData['updated_by'] = $updatedBy;

            // Only update if we have data
            if (!empty($updateData)) {
                $quote->update($updateData);
            }

            // Update items if provided - check both 'items' and 'line_items'
            if (isset($data['items'])) {
                $this->updateQuoteItems($quote, $data['items']);
                // Recalculate totals after items update
                $quote->calculateTotals();
            } elseif (isset($data['line_items'])) {
                $this->updateQuoteItems($quote, $data['line_items']);
                $quote->calculateTotals();
            }

            // Update reminders if provided
            if (isset($data['reminders'])) {
                $this->updateQuoteReminders($quote, $data['reminders'], $updatedBy);
            }

            DB::commit();

            Log::info('Quote updated successfully', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'updated_by' => $updatedBy,
            ]);

            return $quote->fresh(['items', 'reminders', 'updater']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update quote', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'updated_by' => $updatedBy,
            ]);
            throw $e;
        }
    }

    /**
     * Handle status update logic
     */
    private function handleStatusUpdate(Quote $quote, string $status): array
    {
        $updateData = [];

        switch ($status) {
            case 'approved':
                $updateData['approval_date'] = now();
                $updateData['approval_status'] = 'accepted';
                break;
            case 'rejected':
                $updateData['approval_date'] = now();
                $updateData['approval_status'] = 'rejected';
                break;
            case 'sent':
                $updateData['sent_at'] = now();
                break;
            case 'expired':
                $updateData['expires_at'] = now();
                break;
        }

        return $updateData;
    }


    /**
     * Update quote items
     */
    private function updateQuoteItems(Quote $quote, array $items): void
    {
        $existingItemIds = [];
        $sortOrder = 0;

        foreach ($items as $item) {
            // Check if this is an existing item (has real ID)
            if (isset($item['id']) && is_numeric($item['id']) && !str_starts_with((string)$item['id'], 'temp')) {
                // Check if item should be deleted
                if (isset($item['_delete']) && $item['_delete'] === true) {
                    QuoteItem::where('quote_id', $quote->id)
                        ->where('id', $item['id'])
                        ->delete();
                    continue;
                }

                // Update existing item
                $quoteItem = QuoteItem::where('quote_id', $quote->id)
                    ->where('id', $item['id'])
                    ->first();

                if ($quoteItem) {
                    $subtotal = $item['quantity'] * $item['unit_price'];
                    $taxAmount = $subtotal * (($item['tax_rate'] ?? 0) / 100);

                    $quoteItem->update([
                        'item_name' => $item['item_name'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'] ?? 0,
                        'tax_amount' => $taxAmount,
                        'item_total' => $subtotal + $taxAmount,
                        'sort_order' => $sortOrder++,
                        'package_id' => $item['package_id'] ?? null,
                    ]);

                    $existingItemIds[] = $quoteItem->id;
                }
            }
            // New item (no ID or temp ID)
            else if (!isset($item['_delete']) || $item['_delete'] !== true) {
                // Calculate totals for new item
                $subtotal = $item['quantity'] * $item['unit_price'];
                $taxAmount = $subtotal * (($item['tax_rate'] ?? 0) / 100);

                // Create new item
                $quoteItem = new QuoteItem([
                    'quote_id' => $quote->id,
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'tax_amount' => $taxAmount,
                    'item_total' => $subtotal + $taxAmount,
                    'sort_order' => $sortOrder++,
                    'package_id' => $item['package_id'] ?? null,
                ]);

                $quoteItem->save();
                $existingItemIds[] = $quoteItem->id;
            }
        }

        // Delete items that weren't in the update (soft delete)
        QuoteItem::where('quote_id', $quote->id)
            ->whereNotIn('id', $existingItemIds)
            ->delete();
    }

    /**
     * Update quote reminders
     */
    private function updateQuoteReminders(Quote $quote, array $reminders, int $updatedBy): void
    {
        $existingReminderIds = [];

        foreach ($reminders as $reminder) {
            if (isset($reminder['id']) && $reminder['id'] && !str_starts_with($reminder['id'], 'temp')) {
                // Update existing reminder
                $quoteReminder = QuoteReminder::where('quote_id', $quote->id)
                    ->where('id', $reminder['id'])
                    ->first();

                if ($quoteReminder && !($reminder['_delete'] ?? false)) {
                    $quoteReminder->update([
                        'scheduled_at' => $reminder['follow_up_schedule'],
                        'reminder_type' => $reminder['reminder_type'],
                        'status' => $reminder['reminder_status'] ?? 'scheduled',
                        'updated_by' => $updatedBy,
                    ]);

                    $existingReminderIds[] = $quoteReminder->id;
                } elseif ($quoteReminder && ($reminder['_delete'] ?? false)) {
                    $quoteReminder->delete();
                }
            } elseif (!($reminder['_delete'] ?? false)) {
                // Create new reminder
                $quoteReminder = new QuoteReminder([
                    'quote_id' => $quote->id,
                    'scheduled_at' => $reminder['follow_up_schedule'],
                    'reminder_type' => $reminder['reminder_type'],
                    'status' => $reminder['reminder_status'] ?? 'scheduled',
                    'created_by' => $updatedBy,
                    'updated_by' => $updatedBy,
                ]);

                $quoteReminder->save();
                $existingReminderIds[] = $quoteReminder->id;
            }
        }

        // Delete reminders not in the updated list
        QuoteReminder::where('quote_id', $quote->id)
            ->whereNotIn('id', $existingReminderIds)
            ->delete();
    }

    /**
     * Update follow-up status
     */
    public function updateFollowUpStatus(Quote $quote, string $status, int $updatedBy): Quote
    {
        $quote->update([
            'follow_up_status' => $status,
            'updated_by' => $updatedBy,
        ]);

        Log::info('Quote follow-up status updated', [
            'quote_id' => $quote->id,
            'quote_number' => $quote->quote_number,
            'status' => $status,
            'updated_by' => $updatedBy,
        ]);

        return $quote->fresh();
    }
}
