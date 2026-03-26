<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\BreakEntry;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TimeTrackingController extends BaseController
{
    public function dashboard(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        $employeeId = (int) $employee['id'];
        $vendorId = (int) $employee['vendor_id'];
        $perPage = max(1, min(50, (int) $request->integer('per_page', 5)));

        $jobs = $this->assignedJobs($employeeId, $vendorId);

        $entriesPaginator = TimeEntry::with('job:id,title,job_number')
            ->where('employee_id', $employeeId)
            ->orderByDesc('check_in')
            ->paginate($perPage);

        $entries = collect($entriesPaginator->items())
            ->map(fn (TimeEntry $entry) => $this->formatEntry($entry));

        $activeEntry = TimeEntry::with(['job:id,title,job_number', 'breaks' => function ($q) {
            $q->orderByDesc('break_start');
        }])
            ->where('employee_id', $employeeId)
            ->whereNull('check_out')
            ->latest('check_in')
            ->first();

        return $this->successResponse([
            'jobs' => $jobs,
            'entries' => $entries,
            'entries_pagination' => [
                'current_page' => $entriesPaginator->currentPage(),
                'last_page' => $entriesPaginator->lastPage(),
                'per_page' => $entriesPaginator->perPage(),
                'total' => $entriesPaginator->total(),
            ],
            'active_entry' => $activeEntry ? $this->formatActiveEntry($activeEntry) : null,
        ], 'Dashboard data fetched successfully.');
    }

    public function checkIn(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        $employeeId = (int) $employee['id'];
        $vendorId = (int) $employee['vendor_id'];

        $validator = Validator::make($request->all(), [
            'job_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $jobId = (int) $request->job_id;

        $isAssigned = DB::table('job_assignments as ja')
            ->join('jobs as j', 'j.id', '=', 'ja.job_id')
            ->where('ja.employee_id', $employeeId)
            ->where('ja.job_id', $jobId)
            ->where('j.vendor_id', $vendorId)
            ->exists();

        if (!$isAssigned) {
            return $this->forbiddenResponse('Selected job is not assigned to this employee.');
        }

        $hasActive = TimeEntry::where('employee_id', $employeeId)
            ->whereNull('check_out')
            ->exists();

        if ($hasActive) {
            return $this->errorResponse('An active session already exists.', 409);
        }

        $entry = TimeEntry::create([
            'employee_id' => $employeeId,
            'job_id' => $jobId,
            'check_in' => now(),
            'total_time' => 0,
            'status' => 'pending',
        ]);

        $entry->load('job:id,title,job_number');

        return $this->createdResponse([
            'entry' => $this->formatActiveEntry($entry),
        ], 'Checked in successfully.');
    }

    public function checkOut(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        $employeeId = (int) $employee['id'];

        $validator = Validator::make($request->all(), [
            'time_entry_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $entry = TimeEntry::with('breaks')
            ->where('id', (int) $request->time_entry_id)
            ->where('employee_id', $employeeId)
            ->first();

        if (!$entry) {
            return $this->notFoundResponse('Time entry not found.');
        }

        if ($entry->status === 'approved') {
            return $this->errorResponse('Approved entries cannot be edited.', 422);
        }

        if ($entry->check_out) {
            return $this->errorResponse('This session has already been checked out.', 409);
        }

        $checkoutTime = now();

        $openBreak = $entry->breaks->first(fn (BreakEntry $break) => $break->break_end === null);
        if ($openBreak) {
            $openBreak->break_end = $checkoutTime;
            $openBreak->break_duration = max(0, $openBreak->break_start->diffInSeconds($checkoutTime));
            $openBreak->save();
            $entry->refresh();
            $entry->load('breaks');
        }

        $breakSeconds = (int) $entry->breaks->sum('break_duration');
        $workedSeconds = max(0, $entry->check_in->diffInSeconds($checkoutTime) - $breakSeconds);

        $entry->check_out = $checkoutTime;
        $entry->total_time = $workedSeconds;
        $entry->save();
        $entry->load('job:id,title,job_number');

        return $this->successResponse([
            'entry' => $this->formatEntry($entry),
        ], 'Checked out successfully.');
    }

    public function breakStart(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        $employeeId = (int) $employee['id'];

        $entry = TimeEntry::with('breaks')
            ->where('employee_id', $employeeId)
            ->whereNull('check_out')
            ->latest('check_in')
            ->first();

        if (!$entry) {
            return $this->errorResponse('No active working session found.', 409);
        }

        $hasOpenBreak = $entry->breaks->contains(fn (BreakEntry $break) => $break->break_end === null);
        if ($hasOpenBreak) {
            return $this->errorResponse('Break is already active.', 409);
        }

        $break = BreakEntry::create([
            'time_entry_id' => $entry->id,
            'break_start' => now(),
            'break_duration' => 0,
        ]);

        return $this->createdResponse([
            'break_entry' => [
                'id' => $break->id,
                'break_start' => $break->break_start?->toISOString(),
            ],
        ], 'Break started successfully.');
    }

    public function breakEnd(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        $employeeId = (int) $employee['id'];

        $entry = TimeEntry::with(['breaks' => function ($q) {
            $q->latest('break_start');
        }])
            ->where('employee_id', $employeeId)
            ->whereNull('check_out')
            ->latest('check_in')
            ->first();

        if (!$entry) {
            return $this->errorResponse('No active working session found.', 409);
        }

        $openBreak = $entry->breaks->first(fn (BreakEntry $break) => $break->break_end === null);
        if (!$openBreak) {
            return $this->errorResponse('No active break found.', 409);
        }

        $endTime = now();
        $openBreak->break_end = $endTime;
        $openBreak->break_duration = max(0, $openBreak->break_start->diffInSeconds($endTime));
        $openBreak->save();

        return $this->successResponse([
            'break_entry' => [
                'id' => $openBreak->id,
                'break_start' => $openBreak->break_start?->toISOString(),
                'break_end' => $openBreak->break_end?->toISOString(),
                'break_duration' => (int) $openBreak->break_duration,
            ],
        ], 'Break ended successfully.');
    }

    public function timeEntries(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        $employeeId = (int) $employee['id'];

        $perPage = (int) $request->integer('per_page', 5);
        $perPage = max(1, min(50, $perPage));

        $paginator = TimeEntry::with('job:id,title,job_number')
            ->where('employee_id', $employeeId)
            ->orderByDesc('check_in')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(fn (TimeEntry $entry) => $this->formatEntry($entry));

        return $this->successResponse([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Time entries fetched successfully.');
    }

    public function updateTimeEntry(Request $request, int $id): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        $employeeId = (int) $employee['id'];

        $validator = Validator::make($request->all(), [
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $entry = TimeEntry::with('breaks')
            ->where('id', $id)
            ->where('employee_id', $employeeId)
            ->first();

        if (!$entry) {
            return $this->notFoundResponse('Time entry not found.');
        }

        $startTime = Carbon::parse($request->start_time);
        $endTime = Carbon::parse($request->end_time);

        if ($startTime->gte($endTime)) {
            return $this->errorResponse('Start time must be before end time.', 422);
        }

        if ($startTime->diffInSeconds($endTime) > 86400) {
            return $this->errorResponse('Entry duration cannot exceed 24 hours.', 422);
        }

        $hasOverlap = TimeEntry::where('employee_id', $employeeId)
            ->where('id', '!=', $entry->id)
            ->whereNotNull('check_out')
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('check_in', '<', $endTime)
                  ->where('check_out', '>', $startTime);
            })
            ->exists();

        if ($hasOverlap) {
            return $this->errorResponse('Time range overlaps with another entry.', 422);
        }

        $breakSeconds = (int) $entry->breaks
            ->filter(fn (BreakEntry $break) => $break->break_end !== null)
            ->sum('break_duration');

        $workedSeconds = max(0, $startTime->diffInSeconds($endTime) - $breakSeconds);

        $entry->check_in = $startTime;
        $entry->check_out = $endTime;
        $entry->total_time = $workedSeconds;
        $entry->status = 'pending';
        $entry->save();
        $entry->load('job:id,title,job_number');

        return $this->successResponse([
            'entry' => $this->formatEntry($entry),
        ], 'Time entry updated successfully.');
    }

    private function assignedJobs(int $employeeId, int $vendorId)
    {
        return DB::table('job_assignments as ja')
            ->join('jobs as j', 'j.id', '=', 'ja.job_id')
            ->where('ja.employee_id', $employeeId)
            ->where('j.vendor_id', $vendorId)
            ->whereNull('j.deleted_at')
            ->orderByDesc('ja.assigned_at')
            ->select([
                'j.id as id',
                'j.title',
                'j.job_number',
                'ja.shift as task',
            ])
            ->get();
    }

    private function formatActiveEntry(TimeEntry $entry): array
    {
        $breakSeconds = (int) $entry->breaks
            ->filter(fn (BreakEntry $break) => $break->break_end !== null)
            ->sum('break_duration');

        $openBreak = $entry->breaks->first(fn (BreakEntry $break) => $break->break_end === null);

        return [
            'id' => $entry->id,
            'employee_id' => $entry->employee_id,
            'job_id' => $entry->job_id,
            'job_name' => $entry->job?->title,
            'task' => $this->taskForJob($entry->employee_id, $entry->job_id),
            'check_in' => $entry->check_in?->toISOString(),
            'check_out' => $entry->check_out?->toISOString(),
            'total_time' => (int) $entry->total_time,
            'status' => $entry->status,
            'break_seconds' => $breakSeconds,
            'is_on_break' => (bool) $openBreak,
            'active_break_id' => $openBreak?->id,
            'active_break_start' => $openBreak?->break_start?->toISOString(),
        ];
    }

    private function formatEntry(TimeEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'date' => $entry->check_in?->format('d M Y'),
            'employee_id' => $entry->employee_id,
            'job_id' => $entry->job_id,
            'job_name' => $entry->job?->title,
            'job_number' => $entry->job?->job_number,
            'task' => $this->taskForJob($entry->employee_id, $entry->job_id),
            'check_in' => $entry->check_in?->toISOString(),
            'check_out' => $entry->check_out?->toISOString(),
            'total_time' => (int) $entry->total_time,
            'status' => $entry->status,
        ];
    }

    private function taskForJob(int $employeeId, int $jobId): string
    {
        $task = DB::table('job_assignments')
            ->where('employee_id', $employeeId)
            ->where('job_id', $jobId)
            ->value('shift');

        return $task ?: '-';
    }
}
