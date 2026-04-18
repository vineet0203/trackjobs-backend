<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Api\V1\Customers\CustomerQuoteDecisionRequest;
use App\Http\Resources\Api\V1\Quote\QuoteCollection;
use App\Http\Resources\Api\V1\Quote\QuoteResource;
use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;

class CustomerQuoteController extends BaseController
{
    public function index(): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer();

        $quotes = Quote::query()
            ->with(['items'])
            ->where(function ($query) use ($customer) {
                $query->where('customer_id', $customer->id)
                    ->orWhere(function ($subQuery) use ($customer) {
                        $subQuery->whereNull('customer_id')
                            ->where('client_email', $customer->email);
                    });
            })
            ->latest('id')
            ->paginate(15);

        // Backfill relation progressively for legacy quotes mapped by email.
        Quote::query()
            ->whereNull('customer_id')
            ->where('client_email', $customer->email)
            ->update(['customer_id' => $customer->id]);

        return $this->successResponse(
            new QuoteCollection($quotes),
            'Customer quotes retrieved successfully.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer();

        $quote = Quote::query()
            ->with(['items'])
            ->where('id', $id)
            ->where(function ($query) use ($customer) {
                $query->where('customer_id', $customer->id)
                    ->orWhere(function ($subQuery) use ($customer) {
                        $subQuery->whereNull('customer_id')
                            ->where('client_email', $customer->email);
                    });
            })
            ->first();

        if (!$quote) {
            return $this->notFoundResponse('Quote not found.');
        }

        if (!$quote->customer_id) {
            $quote->customer_id = $customer->id;
            $quote->save();
        }

        return $this->successResponse(new QuoteResource($quote), 'Customer quote retrieved successfully.');
    }

    public function decide(int $id, CustomerQuoteDecisionRequest $request): JsonResponse
    {
        return $this->handleDecision($id, $request->validated());
    }

    public function submit(int $id, CustomerQuoteDecisionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['action'] = 'submit';

        return $this->handleDecision($id, $validated);
    }

    private function handleDecision(int $id, array $validated): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer();
        $quote = $this->findCustomerQuote($id, $customer);

        if (!$quote) {
            return $this->notFoundResponse('Quote not found.');
        }

        $action = $validated['action'] ?? null;
        if (!$action) {
            return $this->validationErrorResponse([
                'action' => ['The action field is required.'],
            ]);
        }

        if (!$quote->customer_id) {
            $quote->customer_id = $customer->id;
        }

        if (isset($validated['approved_price'])) {
            $quote->customer_approved_price = $validated['approved_price'];
        }

        if (!empty($validated['signature'])) {
            $quote->customer_signature = $validated['signature'];
            $quote->client_signature = $validated['signature'];
        }

        if (!empty($validated['notes'])) {
            $quote->notes = trim(($quote->notes ? $quote->notes . PHP_EOL : '') . $validated['notes']);
        }

        if ($action === 'approve' || $action === 'submit') {
            $quote->status = 'approved';
            $quote->approval_status = 'accepted';
            $quote->approval_action_date = now();
            $quote->approval_date = now();
        }

        if ($action === 'reject') {
            $quote->status = 'rejected';
            $quote->approval_status = 'rejected';
            $quote->approval_action_date = now();
            $quote->approval_date = now();
        }

        $quote->save();

        if ($quote->job_id) {
            \App\Models\Job::where('id', $quote->job_id)
                ->whereNull('customer_id')
                ->update(['customer_id' => $customer->id]);
        }

        return $this->successResponse(
            new QuoteResource($quote->fresh(['items'])),
            $action === 'reject' ? 'Quote rejected successfully.' : 'Quote updated successfully.'
        );
    }

    private function getAuthenticatedCustomer(): Customer
    {
        $customerData = request()->attributes->get('customer');
        return Customer::findOrFail((int) $customerData['id']);
    }

    private function findCustomerQuote(int $quoteId, Customer $customer): ?Quote
    {
        return Quote::query()
            ->where('id', $quoteId)
            ->where(function ($query) use ($customer) {
                $query->where('customer_id', $customer->id)
                    ->orWhere(function ($subQuery) use ($customer) {
                        $subQuery->whereNull('customer_id')
                            ->where('client_email', $customer->email);
                    });
            })
            ->first();
    }
}
