<?php

namespace App\Services\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClientUpdateService
{
    /**
     * Update an existing client
     */
    public function update(Client $client, array $data, int $updatedBy): Client
    {
        DB::beginTransaction();

        try {
            // Add updated_by
            $data['updated_by'] = $updatedBy;

            // Handle billing address logic
            if (isset($data['same_as_business_address']) && $data['same_as_business_address']) {
                $data = $this->copyBusinessAddressToBilling($data, $client);
            }

            // Handle logo upload if present
            if (isset($data['logo']) && $data['logo'] instanceof \Illuminate\Http\UploadedFile) {
                // Delete old logo if exists
                $this->deleteOldLogo($client->logo_path);
                
                $data['logo_path'] = $this->uploadLogo($data['logo']);
                unset($data['logo']);
            }

            // Handle logo removal
            if (isset($data['remove_logo']) && $data['remove_logo']) {
                $this->deleteOldLogo($client->logo_path);
                $data['logo_path'] = null;
                unset($data['remove_logo']);
            }

            $client->update($data);
            $client->refresh();

            DB::commit();

            Log::info('Client updated successfully', [
                'client_id' => $client->id,
                'vendor_id' => $client->vendor_id,
                'updated_fields' => array_keys($data),
                'updated_by' => $updatedBy,
            ]);

            return $client;

        } catch (\Exception $e) {
            DB::rollBack();
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
            $data['updated_by'] = $updatedBy;
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
            $data['updated_by'] = $updatedBy;
            
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
            $data['updated_by'] = $updatedBy;
            return $this->update($client, $data, $updatedBy);
        }

        return $client;
    }

    /**
     * Copy business address to billing address fields
     */
    private function copyBusinessAddressToBilling(array $data, Client $client): array
    {
        $data['billing_address_line_1'] = $data['address_line_1'] ?? $client->address_line_1;
        $data['billing_address_line_2'] = $data['address_line_2'] ?? $client->address_line_2;
        $data['billing_city'] = $data['city'] ?? $client->city;
        $data['billing_state'] = $data['state'] ?? $client->state;
        $data['billing_country'] = $data['country'] ?? $client->country;
        $data['billing_zip_code'] = $data['zip_code'] ?? $client->zip_code;

        return $data;
    }

    /**
     * Upload client logo
     */
    private function uploadLogo(\Illuminate\Http\UploadedFile $logo): string
    {
        $path = $logo->store('clients/logos', 'public');
        return $path;
    }

    /**
     * Delete old logo
     */
    private function deleteOldLogo(?string $logoPath): void
    {
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            Storage::disk('public')->delete($logoPath);
        }
    }
}