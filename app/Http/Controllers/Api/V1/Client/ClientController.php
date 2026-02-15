<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Api\V1\Clients\CreateClientRequest;
use App\Http\Requests\Api\V1\Clients\UpdateClientRequest;
use App\Http\Requests\Api\V1\Clients\GetClientsRequest;
use App\Http\Resources\Api\V1\Client\ClientCollection;
use App\Http\Resources\Api\V1\Client\ClientResource;
use App\Services\Clients\ClientCreationService;
use App\Services\Clients\ClientQueryService;
use App\Services\Clients\ClientUpdateService;
use App\Services\Clients\ClientDeletionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ClientController extends BaseController
{
    use ApiResponse;

    private ClientCreationService $clientCreationService;
    private ClientQueryService $clientQueryService;
    private ClientUpdateService $clientUpdateService;
    private ClientDeletionService $clientDeletionService;

    public function __construct(
        ClientCreationService $clientCreationService,
        ClientQueryService $clientQueryService,
        ClientUpdateService $clientUpdateService,
        ClientDeletionService $clientDeletionService
    ) {
        $this->clientCreationService = $clientCreationService;
        $this->clientQueryService = $clientQueryService;
        $this->clientUpdateService = $clientUpdateService;
        $this->clientDeletionService = $clientDeletionService;
    }

    /**
     * Add a new client for a vendor
     */
    public function addClient(CreateClientRequest $request): JsonResponse
    {
        try {
            // Log the ENTIRE request data
            Log::info('=== RAW REQUEST DATA ===', [
                'all' => $request->all(),
                'validated' => $request->validated(),
                'has_billing_name' => $request->has('billing_name'),
                'billing_name' => $request->input('billing_name'),
                'has_tax_percentage' => $request->has('tax_percentage'),
                'tax_percentage' => $request->input('tax_percentage'),
                'has_availability' => $request->has('availability_schedule'),
                'availability' => $request->input('availability_schedule'),
            ]);

            // Request is automatically validated by CreateClientRequest
            $validatedData = $request->validated();
            Log::info('=== VALIDATED DATA ===', [
            'validated_keys' => array_keys($validatedData),
            'has_billing_name' => isset($validatedData['billing_name']),
            'billing_name' => $validatedData['billing_name'] ?? null,
            'has_tax_percentage' => isset($validatedData['tax_percentage']),
            'tax_percentage' => $validatedData['tax_percentage'] ?? null,
            'has_availability' => isset($validatedData['availability_schedule']),
        ]);

            $client = $this->clientCreationService->create($validatedData, auth()->id());

            Log::info('=== ADD CLIENT END ===', [
                'client_id' => $client->id,
                'has_schedule' => !empty($client->availabilitySchedules),
                'status' => 'success'
            ]);

            return $this->createdResponse(
                new ClientResource($client),
                'Client added successfully.'
            );
        } catch (\Exception $e) {
            Log::error('=== ADD CLIENT END ===', [
                'status' => 'error',
                'error' => $e->getMessage(),
                'vendor_id' => $request->vendor_id,
            ]);

            return $this->errorResponse(
                'Failed to add client. Please try again.',
                500
            );
        }
    }

    /**
     * Get all clients for a specific vendor with filtering and pagination
     */
    public function getVendorClients(GetClientsRequest $request, int $vendorId): JsonResponse
    {
        try {
            Log::info('=== GET VENDOR CLIENTS START ===', [
                'vendor_id' => $vendorId,
                'filters' => $request->all(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Request is automatically validated by GetClientsRequest
            $validated = $request->validated();
            $clients = $this->clientQueryService->getClients($vendorId, $validated, $validated['per_page'] ?? 15);

            $appliedFilters = $this->clientQueryService->getAppliedFilters($validated);

            Log::info('=== GET VENDOR CLIENTS END ===', [
                'vendor_id' => $vendorId,
                'total_clients' => $clients->total(),
                'status' => 'success'
            ]);

            return $this->successResponse(
                new ClientCollection($clients),
                'Clients retrieved successfully.',
                200,
                [
                    'vendor_id' => $vendorId,
                    'filters' => $appliedFilters,
                ]
            );
        } catch (\Exception $e) {
            Log::error('=== GET VENDOR CLIENTS END ===', [
                'vendor_id' => $vendorId,
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to retrieve clients. Please try again.',
                500
            );
        }
    }

    /**
     * Get a single client by ID for a specific vendor
     */
    public function getVendorClient(int $vendorId, int $clientId): JsonResponse
    {
        try {

            $client = $this->clientQueryService->getClient($vendorId, $clientId);

            if (!$client) {
                Log::warning('Client not found', [
                    'vendor_id' => $vendorId,
                    'client_id' => $clientId,
                ]);

                return $this->notFoundResponse('Client not found.');
            }

            Log::info('=== GET VENDOR CLIENT END ===', [
                'client_id' => $client->id,
                'status' => 'success'
            ]);

            return $this->successResponse(
                new ClientResource($client),
                'Client retrieved successfully.'
            );
        } catch (\Exception $e) {
            Log::error('=== GET VENDOR CLIENT END ===', [
                'vendor_id' => $vendorId,
                'client_id' => $clientId,
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to retrieve client. Please try again.',
                500
            );
        }
    }

    /**
     * Update a client for a vendor
     */
    public function modifyClient(UpdateClientRequest $request, int $vendorId, int $clientId): JsonResponse
    {
        try {
            Log::info('=== MODIFY CLIENT START ===', [
                'vendor_id' => $vendorId,
                'client_id' => $clientId,
                'has_availability' => $request->has('availability_schedule'),
                'updates' => array_keys($request->all()),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Check if client exists BEFORE validation
            $client = $this->clientQueryService->getClient($vendorId, $clientId);

            if (!$client) {
                return $this->notFoundResponse('Client not found.');
            }

            // Now run validation since we know client exists
            $validatedData = $request->validated();
            $client = $this->clientUpdateService->update($client, $validatedData, auth()->id());

            Log::info('=== MODIFY CLIENT END ===', [
                'client_id' => $client->id,
                'status' => 'success'
            ]);

            return $this->successResponse(
                new ClientResource($client),
                'Client updated successfully.'
            );
        } catch (\Exception $e) {
            Log::error('=== MODIFY CLIENT END ===', [
                'vendor_id' => $vendorId,
                'client_id' => $clientId,
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to update client. Please try again.',
                500
            );
        }
    }

    /**
     * Delete/soft delete a client
     */
    public function removeClient(int $vendorId, int $clientId): JsonResponse
    {
        try {
            Log::info('=== REMOVE CLIENT START ===', [
                'vendor_id' => $vendorId,
                'client_id' => $clientId,
                'deleted_by' => auth()->id(),
            ]);

            $client = $this->clientQueryService->getClient($vendorId, $clientId);

            if (!$client) {
                return $this->notFoundResponse('Client not found.');
            }

            // Check if client can be deleted
            $canDelete = $this->clientDeletionService->canDelete($client);

            if (!$canDelete['can_delete']) {
                return $this->errorResponse(
                    $canDelete['message'],
                    409 // Conflict status code
                );
            }

            $this->clientDeletionService->softDelete($client, auth()->id());

            Log::info('=== REMOVE CLIENT END ===', [
                'client_id' => $clientId,
                'status' => 'success'
            ]);

            return $this->successResponse(
                null,
                'Client deleted successfully.'
            );
        } catch (\Exception $e) {
            Log::error('=== REMOVE CLIENT END ===', [
                'vendor_id' => $vendorId,
                'client_id' => $clientId,
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to delete client. Please try again.',
                500
            );
        }
    }
}
