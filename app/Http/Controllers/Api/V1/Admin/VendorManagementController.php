<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Api\V1\Admin\UpdateVendorRequest;
use App\Models\Vendor;
use App\Models\User;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Job;
use App\Services\Auth\PasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VendorManagementController extends BaseController
{
    public function __construct(
        private PasswordService $passwordService
    ) {}

    /**
     * GET /api/v1/admin/vendors
     * paginated list, search by name/email/business, filter by status
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $perPage = $request->get('per_page', 10);

        $query = Vendor::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $vendors = $query->latest()->paginate($perPage);

        // Map count statistics
        $vendors->getCollection()->transform(function ($vendor) {
            $vendor->employee_count = Employee::where('vendor_id', $vendor->id)->count();
            
            $clientEmails = Client::where('vendor_id', $vendor->id)->pluck('email');
            $quoteEmails = Quote::where('vendor_id', $vendor->id)->whereNotNull('customer_id')->with('customer')->get()->pluck('customer.email');
            $jobEmails = Job::where('vendor_id', $vendor->id)->whereNotNull('customer_id')->with('customer')->get()->pluck('customer.email');
            $vendor->customer_count = $clientEmails->merge($quoteEmails)->merge($jobEmails)->filter()->unique()->count();

            return $vendor;
        });

        return $this->successResponse($vendors, 'Vendors retrieved successfully');
    }

    /**
     * GET /api/v1/admin/vendors/{id}
     * vendor detail with user info, employee count, customer count
     */
    public function show(int $id): JsonResponse
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return $this->notFoundResponse('Vendor not found');
        }

        // Get owner details
        $owner = User::find($vendor->user_id);
        $vendor->owner = $owner;

        // Get stats
        $vendor->employee_count = Employee::where('vendor_id', $vendor->id)->count();

        $clientEmails = Client::where('vendor_id', $vendor->id)->pluck('email');
        $quoteEmails = Quote::where('vendor_id', $vendor->id)->whereNotNull('customer_id')->with('customer')->get()->pluck('customer.email');
        $jobEmails = Job::where('vendor_id', $vendor->id)->whereNotNull('customer_id')->with('customer')->get()->pluck('customer.email');
        $vendor->customer_count = $clientEmails->merge($quoteEmails)->merge($jobEmails)->filter()->unique()->count();

        return $this->successResponse($vendor, 'Vendor details retrieved successfully');
    }

    /**
     * PUT /api/v1/admin/vendors/{id}
     * update vendor info
     */
    public function update(UpdateVendorRequest $request, int $id): JsonResponse
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return $this->notFoundResponse('Vendor not found');
        }

        $validated = $request->validated();
        $vendor->update($validated);

        return $this->successResponse($vendor, 'Vendor details updated successfully');
    }

    /**
     * DELETE /api/v1/admin/vendors/{id}
     * soft delete vendor + deactivate all linked users
     */
    public function destroy(int $id): JsonResponse
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return $this->notFoundResponse('Vendor not found');
        }

        DB::beginTransaction();
        try {
            // Soft delete vendor
            $vendor->delete();

            // Deactivate all linked users
            $userIds = User::where('vendor_id', $vendor->id)
                ->orWhere('id', $vendor->user_id)
                ->pluck('id');

            User::whereIn('id', $userIds)->update([
                'is_active' => false,
                'status' => 'inactive',
                'deactivated_at' => now(),
                'deactivation_reason' => 'Suspended by admin due to vendor deletion'
            ]);

            DB::commit();
            return $this->successResponse(null, 'Vendor and all associated users deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to soft delete vendor', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to delete vendor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /api/v1/admin/vendors/{id}/toggle-status
     * activate/deactivate (cascade to all linked users)
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return $this->notFoundResponse('Vendor not found');
        }

        $newStatus = $vendor->status === 'active' ? 'inactive' : 'active';

        DB::beginTransaction();
        try {
            $vendor->update(['status' => $newStatus]);

            $userIds = User::where('vendor_id', $vendor->id)
                ->orWhere('id', $vendor->user_id)
                ->pluck('id');

            if ($newStatus === 'inactive') {
                User::whereIn('id', $userIds)->update([
                    'is_active' => false,
                    'status' => 'inactive',
                    'deactivated_at' => now(),
                    'deactivation_reason' => 'Suspended by admin'
                ]);
            } else {
                User::whereIn('id', $userIds)->update([
                    'is_active' => true,
                    'status' => 'active',
                    'reactivated_at' => now()
                ]);
            }

            DB::commit();
            return $this->successResponse($vendor, 'Vendor status toggled successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to toggle vendor status', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to toggle status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /api/v1/admin/vendors/{id}/reset-password
     * force reset vendor's password, set force_password_change=true
     */
    public function resetPassword(int $id): JsonResponse
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return $this->notFoundResponse('Vendor not found');
        }

        $owner = User::find($vendor->user_id);

        if (!$owner) {
            return $this->errorResponse('Vendor owner user not found', 400);
        }

        try {
            // Generate temporary password
            $result = $this->passwordService->generateAndSetNewPassword($owner, true);

            if (!$result['success']) {
                return $this->errorResponse('Failed to generate new password', 500);
            }

            $newPassword = $result['password'];

            // Send email
            Mail::raw("Hello,\n\nAn administrator has reset your password for TrakJobs.\nYour new temporary password is: {$newPassword}\n\nYou will be required to change your password upon your next login.", function ($message) use ($owner) {
                $message->to($owner->email)->subject('TrakJobs - Admin Password Reset');
            });

            return $this->successResponse([
                'new_password' => $newPassword
            ], 'Password reset successfully and email sent');
        } catch (\Exception $e) {
            Log::error('Failed to reset vendor password', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to reset password: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/admin/vendors/{id}/employees
     * paginated list of vendor's employees
     */
    public function employees(Request $request, int $id): JsonResponse
    {
        $perPage = $request->get('per_page', 10);
        $employees = Employee::where('vendor_id', $id)->latest()->paginate($perPage);

        return $this->successResponse($employees, 'Employees retrieved successfully');
    }

    /**
     * GET /api/v1/admin/vendors/{id}/customers
     * paginated list of vendor's customers
     */
    public function customers(Request $request, int $id): JsonResponse
    {
        $perPage = $request->get('per_page', 10);

        // Find customer emails
        $clientEmails = Client::where('vendor_id', $id)->pluck('email');
        $quoteEmails = Quote::where('vendor_id', $id)->whereNotNull('customer_id')->with('customer')->get()->pluck('customer.email');
        $jobEmails = Job::where('vendor_id', $id)->whereNotNull('customer_id')->with('customer')->get()->pluck('customer.email');
        
        $emails = $clientEmails->merge($quoteEmails)->merge($jobEmails)->filter()->unique();

        $customers = Customer::whereIn('email', $emails)->latest()->paginate($perPage);

        return $this->successResponse($customers, 'Customers retrieved successfully');
    }

    /**
     * PATCH /api/v1/admin/vendors/{id}/employees/{uid}/toggle-status
     * activate/deactivate single employee
     */
    public function toggleEmployeeStatus(int $id, int $uid): JsonResponse
    {
        $employee = Employee::where('vendor_id', $id)->where('id', $uid)->first();

        if (!$employee) {
            return $this->notFoundResponse('Employee not found');
        }

        $employee->update([
            'is_active' => !$employee->is_active
        ]);

        return $this->successResponse($employee, 'Employee status updated successfully');
    }

    /**
     * PATCH /api/v1/admin/vendors/{id}/customers/{uid}/toggle-status
     * activate/deactivate single customer
     */
    public function toggleCustomerStatus(int $id, int $uid): JsonResponse
    {
        // Check if customer exists and is linked to the vendor
        $customer = Customer::find($uid);

        if (!$customer) {
            return $this->notFoundResponse('Customer not found');
        }

        // Verify relationship
        $clientEmails = Client::where('vendor_id', $id)->pluck('email');
        $quoteEmails = Quote::where('vendor_id', $id)->whereNotNull('customer_id')->with('customer')->get()->pluck('customer.email');
        $jobEmails = Job::where('vendor_id', $id)->whereNotNull('customer_id')->with('customer')->get()->pluck('customer.email');
        $emails = $clientEmails->merge($quoteEmails)->merge($jobEmails)->filter()->unique();

        if (!$emails->contains($customer->email)) {
            return $this->forbiddenResponse('Customer is not associated with this vendor');
        }

        $newStatus = $customer->status === 'active' ? 'inactive' : 'active';
        $customer->update(['status' => $newStatus]);

        return $this->successResponse($customer, 'Customer status updated successfully');
    }
}
