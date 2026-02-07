<?php

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Models\QuoteItem;
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
            // Update quote fields
            $updateData = array_intersect_key($data, array_flip([
                'title', 'client_name', 'client_email', 'discount',
                'deposit_type', 'follow_up_at', 'reminder_type',
                'follow_up_status', 'notes', 'status', 'client_signature'
            ]));
            
            // Handle deposit calculation
            if (isset($data['deposit_type'])) {
                $updateData['deposit_amount'] = $this->calculateDepositAmount(
                    $quote, 
                    $data['deposit_type'], 
                    $data['deposit_amount'] ?? null, 
                    $data['deposit_percentage'] ?? null
                );
            }
            
            // Handle status updates
            if (isset($data['status'])) {
                $updateData = array_merge($updateData, $this->handleStatusUpdate($quote, $data['status']));
            }
            
            // Add updated_by
            $updateData['updated_by'] = $updatedBy;
            
            $quote->update($updateData);
            
            // Update items if provided
            if (isset($data['items'])) {
                $this->updateQuoteItems($quote, $data['items']);
                $quote->calculateTotals();
            }
            
            DB::commit();
            
            Log::info('Quote updated successfully', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'updated_by' => $updatedBy,
            ]);
            
            return $quote->fresh(['items', 'updater']);
            
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
     * Calculate deposit amount
     */
    private function calculateDepositAmount(
        Quote $quote, 
        string $depositType, 
        ?float $depositAmount, 
        ?float $depositPercentage
    ): ?float {
        return match ($depositType) {
            'fixed' => $depositAmount,
            'percentage' => $quote->subtotal * ($depositPercentage / 100),
            default => null,
        };
    }
    
    /**
     * Handle status update logic
     */
    private function handleStatusUpdate(Quote $quote, string $status): array
    {
        $updateData = [];
        
        switch ($status) {
            case 'approved':
                $updateData['approved_at'] = now();
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
            if (isset($item['id']) && $item['id']) {
                // Update existing item
                $quoteItem = QuoteItem::where('quote_id', $quote->id)
                    ->where('id', $item['id'])
                    ->first();
                
                if ($quoteItem && !($item['_delete'] ?? false)) {
                    $quoteItem->update([
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'] ?? 0,
                        'sort_order' => $sortOrder++,
                        'package_id' => $item['package_id'] ?? null,
                    ]);
                    $quoteItem->calculateTotal();
                    $quoteItem->save();
                    $existingItemIds[] = $quoteItem->id;
                } elseif ($quoteItem && ($item['_delete'] ?? false)) {
                    $quoteItem->delete();
                }
            } else {
                // Create new item
                $quoteItem = new QuoteItem([
                    'quote_id' => $quote->id,
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'sort_order' => $sortOrder++,
                    'package_id' => $item['package_id'] ?? null,
                ]);
                $quoteItem->calculateTotal();
                $quoteItem->save();
                $existingItemIds[] = $quoteItem->id;
            }
        }
        
        // Delete items not in the updated list
        QuoteItem::where('quote_id', $quote->id)
            ->whereNotIn('id', $existingItemIds)
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