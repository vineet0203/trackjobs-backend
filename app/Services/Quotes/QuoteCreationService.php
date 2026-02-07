<?php

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuoteCreationService
{
    /**
     * Create a new quote with items
     */
    public function create(array $data, int $createdBy): Quote
    {
        DB::beginTransaction();
        
        try {
            // Generate quote number
            $quoteNumber = Quote::generateQuoteNumber();
            
            // Calculate deposit amount if percentage
            $depositAmount = $this->calculateDepositAmount($data);
            
            // Create quote
            $quote = Quote::create([
                'quote_number' => $quoteNumber,
                'title' => $data['title'],
                'client_name' => $data['client_name'],
                'client_email' => $data['client_email'],
                'discount' => $data['discount'] ?? 0,
                'deposit_type' => $data['deposit_type'],
                'deposit_amount' => $depositAmount,
                'follow_up_at' => $data['follow_up_at'] ?? null,
                'reminder_type' => $data['reminder_type'] ?? 'none',
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
                'status' => 'draft',
            ]);
            
            // Create quote items
            $this->createQuoteItems($quote, $data['items']);
            
            // Calculate totals
            $quote->calculateTotals();
            
            // Set expiry date (default 30 days)
            $quote->update(['expires_at' => now()->addDays(30)]);
            
            DB::commit();
            
            Log::info('Quote created successfully', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'client_email' => $quote->client_email,
                'created_by' => $createdBy,
            ]);
            
            return $quote->fresh(['items']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create quote', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => array_keys($data),
                'created_by' => $createdBy,
            ]);
            throw $e;
        }
    }
    
    /**
     * Calculate deposit amount based on type
     */
    private function calculateDepositAmount(array $data): ?float
    {
        return match ($data['deposit_type']) {
            'fixed' => $data['deposit_amount'],
            'percentage' => ($data['subtotal'] ?? 0) * ($data['deposit_percentage'] / 100),
            default => null,
        };
    }
    
    /**
     * Create quote items
     */
    private function createQuoteItems(Quote $quote, array $items): void
    {
        $sortOrder = 0;
        
        foreach ($items as $item) {
            $quoteItem = new QuoteItem([
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'] ?? 0,
                'sort_order' => $sortOrder++,
                'package_id' => $item['package_id'] ?? null,
            ]);
            
            $quoteItem->calculateTotal();
            $quote->items()->save($quoteItem);
        }
    }
    
    /**
     * Send quote to client
     */
    public function sendQuote(Quote $quote, int $sentBy): Quote
    {
        if (!$quote->canBeSent()) {
            throw new \Exception('Quote cannot be sent. Check if it has items and is in draft status.');
        }
        
        $quote->update([
            'status' => 'sent',
            'sent_at' => now(),
            'updated_by' => $sentBy,
        ]);
        
        Log::info('Quote sent to client', [
            'quote_id' => $quote->id,
            'quote_number' => $quote->quote_number,
            'client_email' => $quote->client_email,
            'sent_by' => $sentBy,
        ]);
        
        return $quote->fresh();
    }
    
    /**
     * Validate if client already has similar quote
     */
    public function validateDuplicateQuote(string $clientEmail, string $title): bool
    {
        return Quote::where('client_email', $clientEmail)
            ->where('title', 'like', '%' . $title . '%')
            ->whereIn('status', ['draft', 'sent', 'pending'])
            ->exists();
    }
}