<?php

namespace App\Services\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientCreationService
{
    /**
     * Create a new client
     */
    public function create(array $data, int $createdBy): Client
    {
        DB::beginTransaction();
        
        try {
            // Add created_by and updated_by
            $data['created_by'] = $createdBy;
            $data['updated_by'] = $createdBy;

            // Handle billing address logic
            if ($data['same_as_business_address'] ?? false) {
                $data = $this->copyBusinessAddressToBilling($data);
            }

            // Handle logo upload if present
            if (isset($data['logo']) && $data['logo'] instanceof \Illuminate\Http\UploadedFile) {
                $data['logo_path'] = $this->uploadLogo($data['logo']);
                unset($data['logo']);
            }

            $client = Client::create($data);

            DB::commit();

            Log::info('Client created successfully', [
                'client_id' => $client->id,
                'vendor_id' => $client->vendor_id,
                'business_name' => $client->business_name,
                'created_by' => $createdBy,
            ]);

            return $client;

        } catch (\Exception $e) {
            DB::rollBack();
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
     * Upload client logo
     */
    private function uploadLogo(\Illuminate\Http\UploadedFile $logo): string
    {
        $path = $logo->store('clients/logos', 'public');
        return $path;
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