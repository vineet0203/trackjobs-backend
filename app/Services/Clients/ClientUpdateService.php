<?php

namespace App\Services\Clients;

use App\Models\Client;
use App\Services\File\FileValidationRules;
use App\Services\File\FileAttachmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientUpdateService
{
    public function __construct(
        private FileAttachmentService $fileAttachmentService
    ) {}

    /**
     * Update an existing client
     */
    public function update(Client $client, array $data, int $updatedBy): Client
    {
        DB::beginTransaction();

        try {
            Log::info('=== CLIENT UPDATE START ===', [
                'client_id' => $client->id,
                'data_keys' => array_keys($data),
                'has_logo_temp_id' => isset($data['logo_temp_id']),
                'logo_temp_id' => $data['logo_temp_id'] ?? null,
                'has_remove_logo' => isset($data['remove_logo']),
                'remove_logo' => $data['remove_logo'] ?? false,
                'updated_by' => $updatedBy
            ]);

            // Add updated_by
            $data['updated_by'] = $updatedBy;

            // Handle billing address logic
            if (isset($data['same_as_business_address']) && $data['same_as_business_address']) {
                $data = $this->copyBusinessAddressToBilling($data, $client);
            }

            // Handle logo updates using temporary upload ID
            if (isset($data['logo_temp_id']) || isset($data['remove_logo'])) {
                $errors = $this->fileAttachmentService->updateFile(
                    model: $client,
                    data: $data,
                    tempIdField: 'logo_temp_id',
                    pathField: 'logo_path',
                    destinationPath: 'clients/logos',
                    allowedMimeTypes: FileValidationRules::getAllowedMimeTypes('images'),
                    maxSizeKb: FileValidationRules::getSizeLimits('images'),
                    customFilename: $this->generateLogoFilename($client->business_name),
                    keepOriginalName: false,
                    removeField: 'remove_logo'
                );

                if (!empty($errors)) {
                    throw new \Exception(implode(', ', $errors['logo_temp_id'] ?? []));
                }
            }

            Log::info('Updating client with data', [
                'client_id' => $client->id,
                'data_keys' => array_keys($data),
                'has_logo_path' => isset($data['logo_path']),
                'logo_path' => $data['logo_path'] ?? null
            ]);

            $client->update($data);
            $client->refresh();

            DB::commit();

            Log::info('Client updated successfully', [
                'client_id' => $client->id,
                'vendor_id' => $client->vendor_id,
                'updated_fields' => array_keys($data),
                'has_logo' => !empty($client->logo_path),
                'logo_path' => $client->logo_path,
                'updated_by' => $updatedBy,
            ]);

            return $client;

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Cleanup any temporary uploads if update failed
            if (isset($data['logo_temp_id'])) {
                Log::info('Cleaning up temporary upload due to update failure', [
                    'temp_id' => $data['logo_temp_id']
                ]);
                $this->fileAttachmentService->cleanupUnusedTemporaryUpload($data['logo_temp_id']);
            }

            Log::error('Failed to update client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => array_keys($data),
                'updated_by' => $updatedBy,
            ]);
            throw $e;
        }
    }

    /**
     * Generate logo filename for updates
     */
    private function generateLogoFilename(string $businessName): string
    {
        $slug = \Illuminate\Support\Str::slug($businessName);
        $timestamp = time();
        $random = \Illuminate\Support\Str::random(6);
        return "logo_{$slug}_{$random}_{$timestamp}.png";
    }

    /**
     * Update client status
     */
    public function updateStatus(Client $client, string $status, int $updatedBy): Client
    {
        return $this->update($client, ['status' => $status], $updatedBy);
    }

    /**
     * Update client category
     */
    public function updateCategory(Client $client, string $category, int $updatedBy): Client
    {
        return $this->update($client, ['client_category' => $category], $updatedBy);
    }

    /**
     * Verify client
     */
    public function verifyClient(Client $client, int $verifiedBy): Client
    {
        DB::beginTransaction();

        try {
            $client->update([
                'is_verified' => true,
                'verified_at' => now(),
                'updated_by' => $verifiedBy,
            ]);

            DB::commit();

            Log::info('Client verified', [
                'client_id' => $client->id,
                'verified_by' => $verifiedBy,
            ]);

            return $client->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to verify client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update client contact information
     */
    public function updateContactInfo(Client $client, array $contactInfo, int $updatedBy): Client
    {
        $allowedFields = [
            'contact_person_name', 'designation', 'email', 
            'mobile_number', 'alternate_mobile_number'
        ];

        $data = array_intersect_key($contactInfo, array_flip($allowedFields));
        
        if (!empty($data)) {
            return $this->update($client, $data, $updatedBy);
        }

        return $client;
    }

    /**
     * Update client address
     */
    public function updateAddress(Client $client, array $addressData, int $updatedBy): Client
    {
        $allowedFields = [
            'address_line_1', 'address_line_2', 'city', 'state', 
            'country', 'zip_code', 'same_as_business_address',
            'billing_address_line_1', 'billing_address_line_2',
            'billing_city', 'billing_state', 'billing_country',
            'billing_zip_code'
        ];

        $data = array_intersect_key($addressData, array_flip($allowedFields));
        
        if (!empty($data)) {
            // Handle billing address logic
            if (isset($data['same_as_business_address']) && $data['same_as_business_address']) {
                $data = $this->copyBusinessAddressToBilling($data, $client);
            }

            return $this->update($client, $data, $updatedBy);
        }

        return $client;
    }

    /**
     * Update client payment terms
     */
    public function updatePaymentTerms(Client $client, array $paymentData, int $updatedBy): Client
    {
        $allowedFields = [
            'payment_term', 'custom_payment_term', 
            'preferred_currency', 'tax_percentage', 'tax_id'
        ];

        $data = array_intersect_key($paymentData, array_flip($allowedFields));
        
        if (!empty($data)) {
            return $this->update($client, $data, $updatedBy);
        }

        return $client;
    }

    /**
     * Update only client notes
     */
    public function updateNotes(Client $client, string $notes, int $updatedBy): Client
    {
        return $this->update($client, ['notes' => $notes], $updatedBy);
    }

    /**
     * Update website URL
     */
    public function updateWebsiteUrl(Client $client, string $websiteUrl, int $updatedBy): Client
    {
        // Add https:// if not present
        if (!empty($websiteUrl) && !str_starts_with($websiteUrl, 'http://') && !str_starts_with($websiteUrl, 'https://')) {
            $websiteUrl = 'https://' . $websiteUrl;
        }

        return $this->update($client, ['website_url' => $websiteUrl], $updatedBy);
    }

    /**
     * Update business registration number
     */
    public function updateBusinessRegistration(Client $client, string $registrationNumber, int $updatedBy): Client
    {
        return $this->update($client, ['business_registration_number' => $registrationNumber], $updatedBy);
    }

    /**
     * Copy business address to billing address fields
     */
    private function copyBusinessAddressToBilling(array $data, Client $client): array
    {
        if (isset($data['address_line_1'])) {
            $data['billing_address_line_1'] = $data['address_line_1'];
        } elseif ($client->address_line_1) {
            $data['billing_address_line_1'] = $client->address_line_1;
        }

        if (isset($data['address_line_2'])) {
            $data['billing_address_line_2'] = $data['address_line_2'];
        } elseif ($client->address_line_2) {
            $data['billing_address_line_2'] = $client->address_line_2;
        }

        if (isset($data['city'])) {
            $data['billing_city'] = $data['city'];
        } elseif ($client->city) {
            $data['billing_city'] = $client->city;
        }

        if (isset($data['state'])) {
            $data['billing_state'] = $data['state'];
        } elseif ($client->state) {
            $data['billing_state'] = $client->state;
        }

        if (isset($data['country'])) {
            $data['billing_country'] = $data['country'];
        } elseif ($client->country) {
            $data['billing_country'] = $client->country;
        }

        if (isset($data['zip_code'])) {
            $data['billing_zip_code'] = $data['zip_code'];
        } elseif ($client->zip_code) {
            $data['billing_zip_code'] = $client->zip_code;
        }

        return $data;
    }

    /**
     * Update client logo using temporary upload ID
     */
    public function updateLogo(Client $client, string $tempId, int $updatedBy): Client
    {
        return $this->update($client, ['logo_temp_id' => $tempId], $updatedBy);
    }

    /**
     * Remove client logo
     */
    public function removeLogo(Client $client, int $updatedBy): Client
    {
        return $this->update($client, ['remove_logo' => true], $updatedBy);
    }

    /**
     * Batch update multiple fields
     */
    public function batchUpdate(Client $client, array $updates, int $updatedBy): Client
    {
        $allowedFields = [
            'business_name', 'business_type', 'industry', 'business_registration_number',
            'contact_person_name', 'designation', 'email', 'mobile_number', 'alternate_mobile_number',
            'address_line_1', 'address_line_2', 'city', 'state', 'country', 'zip_code',
            'billing_name', 'same_as_business_address', 'billing_address_line_1', 'billing_address_line_2',
            'billing_city', 'billing_state', 'billing_country', 'billing_zip_code',
            'payment_term', 'custom_payment_term', 'preferred_currency', 'tax_percentage', 'tax_id',
            'website_url', 'client_category', 'notes', 'status', 'is_verified',
            'logo_temp_id', 'remove_logo'
        ];

        $filteredData = array_intersect_key($updates, array_flip($allowedFields));
        
        if (!empty($filteredData)) {
            return $this->update($client, $filteredData, $updatedBy);
        }

        return $client;
    }
}