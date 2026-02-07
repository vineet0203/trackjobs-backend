<?php
// app/Services/Clients/ClientCreationService.php

namespace App\Services\Clients;

use App\Models\Client;
use App\Services\File\FileValidationRules;
use App\Services\File\FileAttachmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientCreationService
{
    public function __construct(
        private FileAttachmentService $fileAttachmentService
    ) {}

    /**
     * Create a new client
     */
    public function create(array $data, int $createdBy): Client
    {
        DB::beginTransaction();

        try {
            Log::info('=== CLIENT CREATION START ===', [
                'data_keys' => array_keys($data),
                'has_logo_temp_id' => isset($data['logo_temp_id']),
                'logo_temp_id' => $data['logo_temp_id'] ?? null,
                'created_by' => $createdBy
            ]);

            // Handle billing address logic
            if ($data['same_as_business_address'] ?? false) {
                $data = $this->copyBusinessAddressToBilling($data);
            }

            // Handle logo upload if present
            if (isset($data['logo_temp_id'])) {
                $errors = $this->fileAttachmentService->attachFile(
                    data: $data,
                    tempIdField: 'logo_temp_id',
                    pathField: 'logo_path',
                    destinationPath: 'clients/logos', // 'private/clients/logos'
                    allowedMimeTypes: FileValidationRules::getAllowedMimeTypes('images'),
                    maxSizeKb: FileValidationRules::getSizeLimits('images'),
                    customFilename: $this->generateLogoFilename($data['business_name']),
                    keepOriginalName: false
                );

                if (!empty($errors)) {
                    throw new \Exception(implode(', ', $errors['logo_temp_id'] ?? []));
                }
            }
            
            Log::info('Creating client with data', [
                'data_keys' => array_keys($data),
                'has_logo_path' => isset($data['logo_path']),
                'logo_path' => $data['logo_path'] ?? null
            ]);

            $client = Client::create($data);

            DB::commit();

            Log::info('Client created successfully', [
                'client_id' => $client->id,
                'vendor_id' => $client->vendor_id,
                'business_name' => $client->business_name,
                'has_logo' => !empty($client->logo_path),
                'logo_path' => $client->logo_path,
                'created_by' => $createdBy,
            ]);

            return $client;
        } catch (\Exception $e) {
            DB::rollBack();

            // Cleanup any temporary uploads if creation failed
            if (isset($data['logo_temp_id'])) {
                Log::info('Cleaning up temporary upload due to failure', [
                    'temp_id' => $data['logo_temp_id']
                ]);
                $this->fileAttachmentService->cleanupUnusedTemporaryUpload($data['logo_temp_id']);
            }

            Log::error('Failed to create client', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => array_keys($data),
                'created_by' => $createdBy,
            ]);
            throw $e;
        }
    }

    /**
     * Generate logo filename
     */
    private function generateLogoFilename(string $businessName): string
    {
        $slug = \Illuminate\Support\Str::slug($businessName);
        $timestamp = time();
        $random = \Illuminate\Support\Str::random(6);
        return "logo_{$slug}_{$random}_{$timestamp}.png";
    }

    /**
     * Copy business address to billing address fields
     */
    private function copyBusinessAddressToBilling(array $data): array
    {
        $data['billing_address_line_1'] = $data['address_line_1'];
        $data['billing_address_line_2'] = $data['address_line_2'];
        $data['billing_city'] = $data['city'];
        $data['billing_state'] = $data['state'];
        $data['billing_country'] = $data['country'];
        $data['billing_zip_code'] = $data['zip_code'];

        return $data;
    }

    /**
     * Validate if client with same business name exists for vendor
     */
    public function validateBusinessNameExists(int $vendorId, string $businessName): bool
    {
        return Client::where('vendor_id', $vendorId)
            ->where('business_name', $businessName)
            ->exists();
    }

    /**
     * Validate if client with same email exists for vendor
     */
    public function validateEmailExists(int $vendorId, string $email): bool
    {
        return Client::where('vendor_id', $vendorId)
            ->where('email', $email)
            ->exists();
    }
}
