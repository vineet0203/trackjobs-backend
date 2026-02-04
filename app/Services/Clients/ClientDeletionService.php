<?php

namespace App\Services\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientDeletionService
{
    /**
     * Soft delete a client
     */
    public function softDelete(Client $client, int $deletedBy): bool
    {
        DB::beginTransaction();

        try {
            $client->delete();

            Log::info('Client soft deleted', [
                'client_id' => $client->id,
                'vendor_id' => $client->vendor_id,
                'business_name' => $client->business_name,
                'deleted_by' => $deletedBy,
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to soft delete client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'deleted_by' => $deletedBy,
            ]);
            throw $e;
        }
    }

    /**
     * Force delete a client (permanent)
     */
    public function forceDelete(Client $client, int $deletedBy): bool
    {
        DB::beginTransaction();

        try {
            // Delete associated logo file if exists
            $this->deleteClientLogo($client);
            
            $client->forceDelete();

            Log::warning('Client permanently deleted', [
                'client_id' => $client->id,
                'vendor_id' => $client->vendor_id,
                'business_name' => $client->business_name,
                'deleted_by' => $deletedBy,
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to permanently delete client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'deleted_by' => $deletedBy,
            ]);
            throw $e;
        }
    }

    /**
     * Restore a soft deleted client
     */
    public function restore(int $vendorId, int $clientId, int $restoredBy): bool
    {
        DB::beginTransaction();

        try {
            $client = Client::withTrashed()
                ->where('vendor_id', $vendorId)
                ->where('id', $clientId)
                ->first();

            if (!$client) {
                throw new \Exception('Client not found or not soft deleted');
            }

            $client->restore();
            $client->update(['updated_by' => $restoredBy]);

            Log::info('Client restored', [
                'client_id' => $client->id,
                'vendor_id' => $vendorId,
                'restored_by' => $restoredBy,
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore client', [
                'vendor_id' => $vendorId,
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'restored_by' => $restoredBy,
            ]);
            throw $e;
        }
    }

    /**
     * Delete all clients for a vendor (soft delete)
     */
    public function deleteAllForVendor(int $vendorId, int $deletedBy): int
    {
        DB::beginTransaction();

        try {
            $count = Client::where('vendor_id', $vendorId)->delete();

            Log::warning('All clients soft deleted for vendor', [
                'vendor_id' => $vendorId,
                'client_count' => $count,
                'deleted_by' => $deletedBy,
            ]);

            DB::commit();
            return $count;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete all clients for vendor', [
                'vendor_id' => $vendorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'deleted_by' => $deletedBy,
            ]);
            throw $e;
        }
    }

    /**
     * Check if client can be deleted
     */
    public function canDelete(Client $client): array
    {
        $canDelete = true;
        $dependencies = [];
        $message = 'Client can be deleted';

        // Check for invoices
        if (method_exists($client, 'invoices') && $client->invoices()->count() > 0) {
            $canDelete = false;
            $dependencies[] = 'invoices';
        }

        // Check for projects
        if (method_exists($client, 'projects') && $client->projects()->count() > 0) {
            $canDelete = false;
            $dependencies[] = 'projects';
        }

        // Check for payments
        if (method_exists($client, 'payments') && $client->payments()->count() > 0) {
            $canDelete = false;
            $dependencies[] = 'payments';
        }

        if (!$canDelete) {
            $message = 'Client has dependencies: ' . implode(', ', $dependencies);
        }

        return [
            'can_delete' => $canDelete,
            'dependencies' => $dependencies,
            'message' => $message,
        ];
    }

    /**
     * Delete client logo file
     */
    private function deleteClientLogo(Client $client): void
    {
        if ($client->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($client->logo_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($client->logo_path);
        }
    }
}