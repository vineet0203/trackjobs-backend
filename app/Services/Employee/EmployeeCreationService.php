<?php

namespace App\Services\Employee;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Role\RoleService;
use App\Services\User\UserAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmployeeCreationService
{
    public function __construct(
        private RoleService $roleService,
        private UserAccountService $userAccountService
    ) {}

    /**
     * Create a new employee with all details
     */
    public function createEmployee(array $data, User $createdBy): array
    {
        DB::beginTransaction();

        try {
            // Step 1: Validate company context
            $company = $createdBy->company;
            if (!$company) {
                throw new \Exception('User does not belong to any company');
            }

            // Step 1a: Extract nested data for easier access
            $personalDetails = $data['personal_details'] ?? [];
            $jobDetails = $data['job_details'] ?? []; // This is REQUIRED for create
            $compensationDetails = $data['compensation_details'] ?? [];
            $experienceDetails = $data['experience_details'] ?? [];

            // Step 1b: Validate required job details
            if (empty($jobDetails)) {
                throw new \Exception('Job details are required for employee creation');
            }

            // Step 2: Create User account using UserAccountService
            Log::info('Creating user account using UserAccountService...');

            $userResult = $this->userAccountService->createOrUpdateUserAccount(
                [
                    'email' => $data['email'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                ],
                'employee', // Role type
                null, // No linked entity yet (we'll link after employee creation)
                null, // No linked type
                [
                    'company_id' => $company->id,
                    'created_by' => config('system.user_id') ?? $createdBy->id,
                    'send_welcome_email' => false,
                    'force_password_reset' => false,
                    'is_active' => ($data['status'] ?? 'active') !== 'inactive',
                ]
            );

            $user = $userResult['user'];

            // Step 3: Create Employee record
            $employeeCode = $this->generateEmployeeCode($company);

            $employeeData = [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'employee_code' => $employeeCode,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'manager_id' => $data['manager_id'] ?? null,
                'personal_email' => $data['personal_email'],
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'] ?? 'active',
                'created_by' => $createdBy->id
            ];

            // Add optional fields if provided
            if (isset($data['middle_name'])) $employeeData['middle_name'] = $data['middle_name'];
            if (isset($data['preferred_name'])) $employeeData['preferred_name'] = $data['preferred_name'];
            if (isset($data['profile_image'])) $employeeData['profile_image'] = $data['profile_image'];

            $employee = Employee::create($employeeData);
            Log::info('✅ Employee created', ['employee_id' => $employee->id]);

            // Step 4: Create Employee Details (with nested data extraction)
            Log::info('Creating employee details...');
            $this->createEmployeePersonalDetails($employee, $personalDetails, $createdBy);
            $this->createEmployeeJobDetails($employee, $jobDetails, $createdBy);
            $this->createEmployeeCompensationDetails($employee, $compensationDetails, $createdBy);
            $this->createEmployeeExperienceDetails($employee, $experienceDetails, $createdBy);
            $this->createEmployeeEmergencyContacts($employee, $data, $createdBy);  // emergency_contacts is at root level
            $this->createEmployeeLegalDocuments($employee, $data, $createdBy);  // legal_documents is at root level
            Log::info('✅ All employee details created');

            // Step 5: Assign Role
            Log::info('Assigning role...', ['role' => 'employee']);
            $this->assignEmployeeRole($user, 'employee', $createdBy);
            Log::info('✅ Role assigned');

            // Step 6: Create default leave balances
            $this->createDefaultLeaveBalances($employee);

            DB::commit();

            Log::info('=== EMPLOYEE CREATION COMPLETED ===', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'company_id' => $company->id
            ]);

            return [
                'success' => true,
                'user' => $user->load(['employee', 'roles']),
                'employee' => $employee->load([
                    'personalDetails',
                    'jobDetails',
                    'compensationDetails',
                    'experienceDetails',
                    'emergencyContacts',
                    'legalDocuments'
                ]),
                'temporary_password' => null
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('=== EMPLOYEE CREATION FAILED ===', [
                'error' => $e->getMessage(),
                'created_by' => $createdBy->id,
                'data' => $data
            ]);

            throw new \Exception('Employee creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate employee code
     */
    private function generateEmployeeCode(Company $company): string
    {
        $lockedCompany = Company::where('id', $company->id)
            ->lockForUpdate()
            ->first();

        $lockedCompany->increment('employee_sequence');

        $companyPrefix = strtoupper(
            substr(preg_replace('/[^A-Z]/i', '', $lockedCompany->name), 0, 3)
        ) ?: 'EMP';

        return $companyPrefix . str_pad(
            $lockedCompany->employee_sequence,
            6,
            '0',
            STR_PAD_LEFT
        );
    }


    /**
     * Create employee personal details
     */
    private function createEmployeePersonalDetails(Employee $employee, array $data, User $createdBy): void
    {
        $personalData = [
            'employee_id' => $employee->id,
            'gender' => $data['gender'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'marital_status' => $data['marital_status'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'country' => $data['country'] ?? null,
            'address' => $data['address'] ?? null,
            'social_media' => $data['social_media'] ?? null,
            'created_by' => $createdBy->id
        ];

        $employee->personalDetails()->create($personalData);
        Log::info('✅ Personal details created', ['employee_id' => $employee->id]);
    }

    /**
     * Create employee job details
     */
    private function createEmployeeJobDetails(Employee $employee, array $data, User $createdBy): void
    {
        $jobData = [
            'employee_id' => $employee->id,
            'job_title' => $data['job_title'],
            'department' => $data['department'],
            'division' => $data['division'] ?? $data['department'],
            'employment_type' => $data['employment_type'],
            'workplace' => $data['workplace'],
            'work_location' => $data['work_location'],
            'start_date' => $data['start_date'],
            'effective_date' => $data['effective_date'] ?? $data['start_date'],
            'hire_date' => $data['hire_date'] ?? $data['hire_date'],
            'work_schedule' => $data['work_schedule'] ?? 'Standard Business Hours',
            'created_by' => $createdBy->id
        ];

        $employee->jobDetails()->create($jobData);
        Log::info('✅ Job details created', ['employee_id' => $employee->id]);
    }

    /**
     * Create employee compensation details
     */
    private function createEmployeeCompensationDetails(Employee $employee, array $data, User $createdBy): void
    {
        if (isset($data['salary']) || isset($data['bank_name'])) {
            $compensationData = [
                'employee_id' => $employee->id,
                'salary' => $data['salary'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'pay_frequency' => $data['pay_frequency'] ?? 'monthly',
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'iban' => $data['iban'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'created_by' => $createdBy->id
            ];

            $employee->compensationDetails()->create($compensationData);
            Log::info('✅ Compensation details created', ['employee_id' => $employee->id]);
        }
    }

    /**
     * Create employee experience details
     */
    private function createEmployeeExperienceDetails(Employee $employee, array $data, User $createdBy): void
    {
        $experienceData = [
            'employee_id' => $employee->id,
            'education' => $data['education'] ?? null,
            'work_experience' => $data['work_experience'] ?? null,
            'skills' => $data['skills'] ?? null,
            'languages' => $data['languages'] ?? null,
            'certifications' => $data['certifications'] ?? null,
            'resume' => $data['resume'] ?? null,
            'created_by' => $createdBy->id
        ];

        $employee->experienceDetails()->create($experienceData);
        Log::info('✅ Experience details created', ['employee_id' => $employee->id]);
    }

    /**
     * Create employee emergency contacts
     */
    private function createEmployeeEmergencyContacts(Employee $employee, array $data, User $createdBy): void
    {
        if (!empty($data['emergency_contacts'])) {
            foreach ($data['emergency_contacts'] as $contact) {
                $employee->emergencyContacts()->create([
                    'employee_id' => $employee->id,
                    'contact_name' => $contact['contact_name'],
                    'contact_phone' => $contact['contact_phone'],
                    'relationship' => $contact['relationship'],
                    'address' => $contact['address'] ?? null,
                    'is_primary' => $contact['is_primary'] ?? false,
                    'created_by' => $createdBy->id
                ]);
            }
            Log::info('✅ Emergency contacts created', [
                'employee_id' => $employee->id,
                'count' => count($data['emergency_contacts'])
            ]);
        }
    }

    /**
     * Create employee legal documents
     */
    private function createEmployeeLegalDocuments(Employee $employee, array $data, User $createdBy): void
    {
        if (!empty($data['legal_documents'])) {
            foreach ($data['legal_documents'] as $document) {
                $legalData = [
                    'employee_id' => $employee->id,
                    'document_type' => $document['document_type'],
                    'document_name' => $document['document_name'],
                    'document_path' => $document['document_path'],
                    'issue_date' => $document['issue_date'] ?? null,
                    'expiry_date' => $document['expiry_date'] ?? null,
                    'notes' => $document['notes'] ?? null,
                    'is_verified' => $document['is_verified'] ?? false,
                    'created_by' => $createdBy->id
                ];

                $employee->legalDocuments()->create($legalData);
            }
            Log::info('✅ Legal documents created', [
                'employee_id' => $employee->id,
                'count' => count($data['legal_documents'])
            ]);
        }
    }

    /**
     * Assign role to employee
     */
    private function assignEmployeeRole(User $user, string $roleSlug, User $assignedBy): void
    {
        $success = $this->roleService->assignSystemRole($user, $roleSlug, $assignedBy);

        if (!$success) {
            throw new \Exception("Failed to assign role: {$roleSlug}");
        }
    }

    /**
     * Create default leave balances for employee
     */
    private function createDefaultLeaveBalances(Employee $employee): void
    {
        try {
            $timeOffTypes = \App\Models\TimeOffType::where('is_active', true)->get();

            foreach ($timeOffTypes as $type) {
                \App\Models\EmployeeLeaveBalance::create([
                    'employee_id' => $employee->id,
                    'time_off_type_id' => $type->id,
                    'year' => now()->year,
                    'allocated_days' => $type->max_days_per_year ?? 0,
                    'used_days' => 0,
                    'carried_forward' => 0,
                ]);
            }

            Log::info('Default leave balances created', [
                'employee_id' => $employee->id,
                'types_count' => $timeOffTypes->count()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create default leave balances', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send invitation email to employee
     */
    public function sendInvitationEmail(Employee $employee): bool
    {
        try {
            // TODO: Implement email sending logic
            Log::info('Invitation email would be sent to', [
                'employee_id' => $employee->id,
                'email' => $employee->personal_email,
                'user_email' => $employee->user->email
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
