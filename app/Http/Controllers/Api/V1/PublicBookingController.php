<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicBookingController extends BaseController
{
    /**
     * Fetch vendors matching the requested service.
     */
    public function getVendors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_category' => ['required', 'string', 'max:100'],
            'service_sub_category' => ['required', 'string', 'max:100'],
        ]);

        $vendors = \App\Models\Vendor::where('status', 'active')
            ->where('service_category', $validated['service_category'])
            ->where('service_sub_category', $validated['service_sub_category'])
            ->limit(5)
            ->get(['id', 'business_name', 'email', 'mobile_number', 'service_description']);

        return $this->successResponse($vendors, 'Matching vendors retrieved successfully.');
    }

    /**
     * Handle public booking submission from the landing page.
     * Matches the requested service to all suitable clients and generates a quote request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'location' => ['required', 'string', 'max:255'],
            'service_category' => ['required', 'string', 'max:100'],
            'service_sub_category' => ['required', 'string', 'max:100'],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'vendor_ids' => ['required', 'array', 'min:1', 'max:5'],
            'vendor_ids.*' => ['required', 'integer', 'exists:vendors,id'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'unit_price' => ['nullable', 'numeric'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            DB::beginTransaction();

            // 1. Get or create the Customer (End-user making the booking)
            $customer = Customer::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'status' => 'active',
                ]
            );

            // 2. Fetch the selected vendors
            $matchingVendors = \App\Models\Vendor::whereIn('id', $validated['vendor_ids'])
                ->where('status', 'active')
                ->get();

            if ($matchingVendors->isEmpty()) {
                DB::rollBack();
                return $this->errorResponse('Selected service providers are not available.', 404);
            }

            $quotesCreated = 0;

            // 3. Generate a Quote/Request for each selected vendor
            foreach ($matchingVendors as $vendor) {
                // Find or create Client under this vendor matching the customer
                $client = Client::firstOrCreate(
                    ['vendor_id' => $vendor->id, 'email' => $customer->email],
                    [
                        'first_name' => $customer->name,
                        'last_name' => '',
                        'business_name' => $customer->name,
                        'client_type' => 'residential',
                        'mobile_number' => $customer->phone,
                        'status' => 'active',
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]
                );

                // Create the quote
                $quote = Quote::create([
                    'quote_number' => Quote::generateQuoteNumber(),
                    'title' => 'New Lead: ' . str_replace('_', ' ', $validated['service_sub_category']),
                    'client_id' => $client->id,
                    'customer_id' => $customer->id,
                    'vendor_id' => $vendor->id,
                    'client_name' => $customer->name,
                    'client_email' => $customer->email,
                    'status' => 'pending', // Pending provider response
                    'notes' => "Location: {$validated['location']}\nDate: {$validated['date']}\nTime: {$validated['time']}\nNotes: {$validated['notes']}",
                    'subtotal' => 0,
                    'total_amount' => 0,
                ]);

                // Create quote item if details are provided
                if ($request->has('service_name') || $request->has('unit_price')) {
                    $itemName = $request->input('service_name') ?? str_replace('_', ' ', $validated['service_sub_category']);
                    $unitPrice = floatval($request->input('unit_price') ?? 0);
                    $qty = intval($request->input('quantity') ?? 1);

                    $quoteItem = new \App\Models\QuoteItem([
                        'item_name' => $itemName,
                        'description' => 'Requested service',
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'item_total' => $qty * $unitPrice,
                    ]);
                    $quote->items()->save($quoteItem);

                    // Recalculate totals
                    $quote->calculateTotals();
                }

                // Get the main user_id of the vendor to send the notification to
                $vendorUser = \App\Models\User::where('vendor_id', $vendor->id)->first();
                $vendorUserId = $vendorUser ? $vendorUser->id : null;

                if ($vendorUserId) {
                    // Create a notification for the Vendor/Client
                    Notification::create([
                        'user_id' => $vendorUserId,
                        'title' => 'New Service Request',
                        'message' => "A new booking request for {$validated['service_sub_category']} has been automatically routed to your business {$vendor->business_name}.",
                        'type' => 'booking_request',
                        'is_read' => false,
                        'data' => ['url' => "/quotes/{$quote->id}"],
                    ]);
                }

                $quotesCreated++;
            }

            DB::commit();

            return $this->successResponse([
                'matched_providers' => $quotesCreated,
                'customer_id' => $customer->id
            ], 'Booking submitted successfully. Matching service providers have been notified.', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Public Booking Error: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while processing your booking.', 500);
        }
    }
}
