<?php

namespace App\Services\Quotes;

use App\Models\Quote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class QuoteQueryService
{
    /**
     * Get quotes with filters and pagination
     */
    public function getQuotes(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Quote::query();
        
        // Apply filters
        $query = $this->applyFilters($query, $filters);
        
        // Apply search
        if (!empty($filters['search'])) {
            $query = $this->applySearch($query, $filters['search']);
        }
        
        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query = $this->applySorting($query, $sortBy, $sortOrder);
        
        // Eager load relationships
        $query->with(['items', 'creator']);
        
        Log::debug('Executing quote query', [
            'filters' => $filters,
            'per_page' => $perPage,
        ]);
        
        return $query->paginate($perPage);
    }
    
    /**
     * Get a specific quote
     */
    public function getQuote(int $quoteId): ?Quote
    {
        return Quote::with(['items', 'creator', 'updater'])
            ->find($quoteId);
    }
    
    /**
     * Get quote by quote number
     */
    public function getQuoteByNumber(string $quoteNumber): ?Quote
    {
        return Quote::where('quote_number', $quoteNumber)
            ->with(['items', 'creator'])
            ->first();
    }
    
    /**
     * Get quotes by client email
     */
    public function getQuotesByClient(string $clientEmail): Collection
    {
        return Quote::where('client_email', $clientEmail)
            ->with(['items'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Get quotes by status
     */
    public function getQuotesByStatus(string $status): Collection
    {
        return Quote::where('status', $status)
            ->with(['items'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Get quotes needing follow-up
     */
    public function getQuotesNeedingFollowUp(): Collection
    {
        return Quote::where('follow_up_status', 'scheduled')
            ->where('follow_up_at', '<=', now())
            ->with(['items'])
            ->get();
    }
    
    /**
     * Get quote statistics
     */
    public function getQuoteStatistics(): array
    {
        return [
            'total' => Quote::count(),
            'draft' => Quote::where('status', 'draft')->count(),
            'sent' => Quote::where('status', 'sent')->count(),
            'pending' => Quote::where('status', 'pending')->count(),
            'approved' => Quote::where('status', 'approved')->count(),
            'rejected' => Quote::where('status', 'rejected')->count(),
            'expired' => Quote::where('status', 'expired')->count(),
            'total_amount' => Quote::where('status', 'approved')->sum('total_amount'),
            'by_month' => Quote::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(6)
                ->pluck('count', 'month')
                ->toArray(),
        ];
    }
    
    /**
     * Apply filters to the query
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        // Filter by status
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        
        // Filter by client email
        if (!empty($filters['client_email'])) {
            $query->where('client_email', $filters['client_email']);
        }
        
        // Filter by client name
        if (!empty($filters['client_name'])) {
            $query->where('client_name', 'like', '%' . $filters['client_name'] . '%');
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        
        // Filter by follow-up status
        if (!empty($filters['follow_up_status'])) {
            $query->where('follow_up_status', $filters['follow_up_status']);
        }
        
        return $query;
    }
    
    /**
     * Apply search to the query
     */
    private function applySearch(Builder $query, string $searchTerm): Builder
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('quote_number', 'like', '%' . $searchTerm . '%')
              ->orWhere('title', 'like', '%' . $searchTerm . '%')
              ->orWhere('client_name', 'like', '%' . $searchTerm . '%')
              ->orWhere('client_email', 'like', '%' . $searchTerm . '%');
        });
    }
    
    /**
     * Apply sorting to the query
     */
    private function applySorting(Builder $query, string $sortBy, string $sortOrder): Builder
    {
        $allowedSortFields = [
            'id', 'quote_number', 'title', 'client_name', 
            'total_amount', 'created_at', 'updated_at', 'expires_at'
        ];
        
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }
        
        return $query;
    }
    
    /**
     * Get applied filters for meta data
     */
    public function getAppliedFilters(array $filters): array
    {
        $appliedFilters = [];
        $filterableFields = [
            'status', 'client_email', 'client_name', 
            'date_from', 'date_to', 'follow_up_status'
        ];
        
        foreach ($filterableFields as $field) {
            if (!empty($filters[$field])) {
                $appliedFilters[$field] = $filters[$field];
            }
        }
        
        if (!empty($filters['search'])) {
            $appliedFilters['search'] = $filters['search'];
        }
        
        return $appliedFilters;
    }
}