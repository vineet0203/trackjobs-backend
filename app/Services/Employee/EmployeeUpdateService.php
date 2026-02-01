<?php

namespace App\Services\Employee;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmployeeUpdateService
{
    public function __construct() {}

    /**
     * Update an existing employee with all details
     */
    public function updateEmployee(Employee $employee, array $data, User $updatedBy): array
    {
        DB::beginTransaction();

        try {
            $changes = [];
            $auditChanges = [];

            // Step 1: Extract nested data for easier access
            $personalDetails = $data['personal_details'] ?? [];
            $jobDetails = $data['job_details'] ?? [];
            $compensationDetails = $data['compensation_details'] ?? [];
            $experienceDetails = $data['experience_details'] ?? [];

            // Step 2: AUDIT LOG - Track email change (User table)
            if (isset($data['email']) && $data['email'] !== $employee->user->email) {
                $auditChanges['email'] = [
                    'old' => $employee->user->email,
                    'new' => $data['email']
                ];

                $employee->user->update([
                    'email' => $data['email'],
                    'email_verified_at' => null,
                ]);
            }

            // Step 3: Prepare employee data for update
            $employeeData = [];
            $employeeData['updated_by'] = $updatedBy->id;

            // Direct field updates for specific audit fields
            $auditFields = ['status', 'manager_id'];
            $otherFields = [
                'first_name',
                'last_name',
                'middle_name',
                'preferred_name',
                'phone',
                'personal_email',
                'profile_image'
            ];

            // Check and update specific audit fields
            foreach ($auditFields as $field) {
                if (array_key_exists($field, $data) && $employee->$field != $data[$field]) {
                    $employeeData[$field] = $data[$field];
                    $changes[$field] = [
                        'old' => $employee->$field,
                        'new' => $data[$field]
                    ];
                    $auditChanges[$field] = [
                        'old' => $employee->$field,
                        'new' => $data[$field]
                    ];
                }
            }

            // Handle status separately if it has additional logic
            if (isset($data['status'])) {
                $this->handleStatusChange($employee, $data['status'], $updatedBy);
            }

            // Check and update other non-audit fields
            foreach ($otherFields as $field) {
                if (array_key_exists($field, $data) && $employee->$field != $data[$field]) {
                    $employeeData[$field] = $data[$field];
                    $changes[$field] = [
                        'old' => $employee->$field,
                        'new' => $data[$field]
                    ];
                }
            }

            // Update employee record if there are changes
            if (!empty($employeeData)) {
                $employee->update($employeeData);
            }

            // Step 4: Update nested details (unchanged)
            if (!empty($personalDetails)) {
                $this->updateEmployeePersonalDetails($employee, $personalDetails, $updatedBy);
            }

            if (!empty($jobDetails)) {
                $this->updateEmployeeJobDetails($employee, $jobDetails, $updatedBy);
            }

            if (!empty($compensationDetails)) {
                $this->updateEmployeeCompensationDetails($employee, $compensationDetails, $updatedBy);
            }

            if (!empty($experienceDetails)) {
                $this->updateEmployeeExperienceDetails($employee, $experienceDetails, $updatedBy);
            }

            if (isset($data['emergency_contacts'])) {
                $this->updateEmployeeEmergencyContacts($employee, $data, $updatedBy);
            }

            if (isset($data['legal_documents'])) {
                $this->updateEmployeeLegalDocuments($employee, $data, $updatedBy);
            }

            // Step 5: Handle manager reassignment
            if (isset($data['manager_id'])) {
                $this->validateManagerAssignment($employee, $data['manager_id']);
            }

            // Step 6: AUDIT LOG - Log the root-level changes
            if (!empty($auditChanges)) {
                AuditLogService::log(
                    event: 'updated',
                    entityType: 'employee',
                    entityId: $employee->id,
                    oldValues: array_map(fn($change) => $change['old'], $auditChanges),
                    newValues: array_map(fn($change) => $change['new'], $auditChanges),
                    meta: [
                        'updated_by' => $updatedBy->id,
                        'updated_by_email' => $updatedBy->email,
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->full_name,
                        'change_fields' => array_keys($auditChanges),
                        'context' => 'employee_update_root_level'
                    ],
                    companyId: $employee->company_id,
                    userId: $updatedBy->id,
                    context: 'employee_management'
                );

                Log::info('Audit logged for employee update', [
                    'employee_id' => $employee->id,
                    'audit_changes' => array_keys($auditChanges),
                    'updated_by' => $updatedBy->id
                ]);
            }

            // Step 7: Refresh the employee with all relationships
            $employee->refresh();
            $employee->load([
                'personalDetails',
                'jobDetails',
                'compensationDetails',
                'experienceDetails',
                'emergencyContacts',
                'legalDocuments',
                'user.roles'
            ]);

            DB::commit();

            Log::info('=== EMPLOYEE UPDATE COMPLETED ===', [
                'employee_id' => $employee->id,
                'company_id' => $employee->company_id,
                'all_changes_made' => $changes,
                'audit_changes' => $auditChanges
            ]);

            return [
                'success' => true,
                'employee' => $employee,
                'changes' => $changes,
                'audit_changes' => $auditChanges,
                'message' => 'Employee updated successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Update employee personal details
     */
    private function updateEmployeePersonalDetails(Employee $employee, array $data, User $updatedBy): void
    {
        $personalData = [];
        $personalFields = [
            'gender',
            'birthdate',
            'marital_status',
            'nationality',
            'country',
            'address',
            'social_media'
        ];

        foreach ($personalFields as $field) {
            if (array_key_exists($field, $data)) {
                $personalData[$field] = $data[$field];
            }
        }

        if (!empty($personalData)) {
            $personalData['updated_by'] = $updatedBy->id;

            if ($employee->personalDetails) {
                $employee->personalDetails->update($personalData);
            } else {
                $personalData['employee_id'] = $employee->id;
                $personalData['created_by'] = $updatedBy->id;
                $employee->personalDetails()->create($personalData);
            }

            Log::info('✅ Personal details updated', ['employee_id' => $employee->id]);
        }
    }

    /**
     * Update employee job details
     */
    private function updateEmployeeJobDetails(Employee $employee, array $data, User $updatedBy): void
    {
        $jobData = [];
        $jobFields = [
            'job_title',
            'department',
            'division',
            'employment_type',
            'workplace',
            'work_location',
            'start_date',
            'effective_date',
            'work_schedule'
        ];

        foreach ($jobFields as $field) {
            if (array_key_exists($field, $data)) {
                $jobData[$field] = $data[$field];
            }
        }

        if (!empty($jobData)) {
            $jobData['updated_by'] = $updatedBy->id;

            if ($employee->jobDetails) {
                $employee->jobDetails->update($jobData);
            } else {
                $jobData['employee_id'] = $employee->id;
                $jobData['created_by'] = $updatedBy->id;
                $employee->jobDetails()->create($jobData);
            }

            Log::info('✅ Job details updated', ['employee_id' => $employee->id]);
        }
    }

    /**
     * Update employee compensation details
     */
    private function updateEmployeeCompensationDetails(Employee $employee, array $data, User $updatedBy): void
    {
        $compensationData = [];
        $compensationFields = [
            'salary',
            'currency',
            'pay_frequency',
            'bank_name',
            'account_number',
            'iban',
            'tax_id'
        ];

        foreach ($compensationFields as $field) {
            if (array_key_exists($field, $data)) {
                $compensationData[$field] = $data[$field];
            }
        }

        if (!empty($compensationData)) {
            $compensationData['updated_by'] = $updatedBy->id;

            if ($employee->compensationDetails) {
                $employee->compensationDetails->update($compensationData);
            } else {
                $compensationData['employee_id'] = $employee->id;
                $compensationData['created_by'] = $updatedBy->id;
                $employee->compensationDetails()->create($compensationData);
            }

            Log::info('✅ Compensation details updated', ['employee_id' => $employee->id]);
        }
    }

    /**
     * Update employee experience details
     */
    private function updateEmployeeExperienceDetails(Employee $employee, array $data, User $updatedBy): void
    {
        $experienceData = [];
        $experienceFields = [
            'education',
            'work_experience',
            'skills',
            'languages',
            'certifications',
            'resume'
        ];

        foreach ($experienceFields as $field) {
            if (array_key_exists($field, $data)) {
                $experienceData[$field] = $data[$field];
            }
        }

        if (!empty($experienceData)) {
            $experienceData['updated_by'] = $updatedBy->id;

            if ($employee->experienceDetails) {
                $employee->experienceDetails->update($experienceData);
            } else {
                $experienceData['employee_id'] = $employee->id;
                $experienceData['created_by'] = $updatedBy->id;
                $employee->experienceDetails()->create($experienceData);
            }

            Log::info('✅ Experience details updated', ['employee_id' => $employee->id]);
        }
    }

    /**
     * Update employee emergency contacts
     */
    private function updateEmployeeEmergencyContacts(Employee $employee, array $data, User $updatedBy): void
    {
        if (!empty($data['emergency_contacts'])) {
            // Delete existing emergency contacts
            $employee->emergencyContacts()->delete();

            // Create new emergency contacts
            foreach ($data['emergency_contacts'] as $contact) {
                $employee->emergencyContacts()->create([
                    'employee_id' => $employee->id,
                    'contact_name' => $contact['contact_name'],
                    'contact_phone' => $contact['contact_phone'],
                    'relationship' => $contact['relationship'],
                    'address' => $contact['address'] ?? null,
                    'is_primary' => $contact['is_primary'] ?? false,
                    'created_by' => $updatedBy->id,
                    'updated_by' => $updatedBy->id
                ]);
            }

            Log::info('✅ Emergency contacts updated', [
                'employee_id' => $employee->id,
                'count' => count($data['emergency_contacts'])
            ]);
        }
    }

    /**
     * Update employee legal documents
     */
    private function updateEmployeeLegalDocuments(Employee $employee, array $data, User $updatedBy): void
    {
        if (!empty($data['legal_documents'])) {
            // For simplicity, we'll delete and recreate
            // In production, you might want more sophisticated document management
            $employee->legalDocuments()->delete();

            foreach ($data['legal_documents'] as $document) {
                $employee->legalDocuments()->create([
                    'employee_id' => $employee->id,
                    'document_type' => $document['document_type'],
                    'document_name' => $document['document_name'],
                    'document_path' => $document['document_path'],
                    'issue_date' => $document['issue_date'] ?? null,
                    'expiry_date' => $document['expiry_date'] ?? null,
                    'notes' => $document['notes'] ?? null,
                    'is_verified' => $document['is_verified'] ?? false,
                    'created_by' => $updatedBy->id,
                    'updated_by' => $updatedBy->id
                ]);
            }

            Log::info('✅ Legal documents updated', [
                'employee_id' => $employee->id,
                'count' => count($data['legal_documents'])
            ]);
        }
    }

    /**
     * Handle employee status change
     */
    private function handleStatusChange(Employee $employee, string $newStatus, User $updatedBy): void
    {
        $oldStatus = $employee->status;

        switch ($newStatus) {
            case 'inactive':
                // Deactivate user account
                $employee->user->update([
                    'is_active' => false,
                    'updated_by' => $updatedBy->id
                ]);
                Log::info('Employee deactivated', ['employee_id' => $employee->id]);
                break;

            case 'active':
                // Reactivate user account
                $employee->user->update([
                    'is_active' => true,
                    'updated_by' => $updatedBy->id
                ]);
                Log::info('Employee reactivated', ['employee_id' => $employee->id]);
                break;

            default:
                Log::warning('Unknown status change attempted', [
                    'employee_id' => $employee->id,
                    'attempted_status' => $newStatus
                ]);
                break;
        }
    }

    /**
     * Validate manager assignment
     */
    private function validateManagerAssignment(Employee $employee, ?int $managerId): void
    {
        if ($managerId === null) {
            return; // Removing manager is allowed
        }

        // Check if manager exists and belongs to same company
        $manager = Employee::where('id', $managerId)
            ->where('company_id', $employee->company_id)
            ->first();

        if (!$manager) {
            throw new \Exception('Manager not found or belongs to different company');
        }

        // Prevent circular reference (employee cannot be their own manager)
        if ($managerId === $employee->id) {
            throw new \Exception('Employee cannot be their own manager');
        }

        // Prevent manager chain loops
        if ($this->wouldCreateCircularReference($employee, $managerId)) {
            throw new \Exception(
                'an employee cannot be assigned as a manager if they already report (directly or indirectly) to the same person.'
            );
        }
    }

    /**
     * Check for circular reference in manager hierarchy
     */
    private function wouldCreateCircularReference(Employee $employee, int $managerId): bool
    {
        $currentManagerId = $managerId;

        // Traverse up the management chain
        while ($currentManagerId !== null) {
            if ($currentManagerId === $employee->id) {
                return true; // Circular reference found
            }

            $currentManager = Employee::find($currentManagerId);
            if (!$currentManager) {
                break;
            }

            $currentManagerId = $currentManager->manager_id;
        }

        return false;
    }
}
