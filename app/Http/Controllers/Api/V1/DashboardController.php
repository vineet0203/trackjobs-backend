<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Quote;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendorId = $user?->vendor_id ?? $user?->id;

        if (!$vendorId) {
            return response()->json(['message' => 'Authenticated user is not associated with a vendor.'], 403);
        }

        [$from, $to] = $this->resolveDateRange($request);

        return response()->json([
            'data' => [
                'stats'          => $this->getStats($vendorId, $from, $to),
                'todaySchedule'  => $this->getScheduleRange($vendorId, $from, $to),
                'teamStatus'     => $this->getTeamStatus($vendorId, $from, $to),
                'totalEarning'   => $this->getTotalEarning($vendorId, $from, $to),
                'earningChart'   => $this->getEarningChartData($vendorId, $from, $to),
                'recentQuotes'   => $this->getRecentQuotes($vendorId, $from, $to),
                'recentInvoices' => $this->getRecentInvoices($vendorId, $from, $to),
            ],
        ]);
    }

    // ── Stats ────────────────────────────────────────────────────────────────

    private function getStats(int $vendorId, Carbon $from, Carbon $to): array
    {
        [$prevFrom, $prevTo] = $this->getPreviousRange($from, $to);

        $activeJobs    = Job::where('vendor_id', $vendorId)->where('status', 'active')->whereBetween('updated_at', [$from, $to])->count();
        $prevActiveJobs = Job::where('vendor_id', $vendorId)->where('status', 'active')->whereBetween('updated_at', [$prevFrom, $prevTo])->count();

        $totalQuotes    = Quote::where('vendor_id', $vendorId)->whereBetween('created_at', [$from, $to])->count();
        $prevQuotes     = Quote::where('vendor_id', $vendorId)->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        $totalJobs      = Job::where('vendor_id', $vendorId)->whereBetween('created_at', [$from, $to])->count();
        $prevJobs       = Job::where('vendor_id', $vendorId)->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        $employeeIds    = Employee::where('vendor_id', $vendorId)->pluck('id')->toArray();
        $totalInvoices  = empty($employeeIds) ? 0 : Invoice::whereIn('employee_id', $employeeIds)->whereBetween('created_at', [$from, $to])->count();
        $prevInvoices   = empty($employeeIds) ? 0 : Invoice::whereIn('employee_id', $employeeIds)->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        $totalBookings  = Booking::where('vendor_id', $vendorId)->whereBetween('created_at', [$from, $to])->count();
        $prevBookings   = Booking::where('vendor_id', $vendorId)->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        return [
            ['label' => 'Active Job',     'value' => $activeJobs,   'change' => $this->calcChange($activeJobs, $prevActiveJobs),  'changeLabel' => 'vs previous period', 'color' => 'green',  'icon' => 'briefcase'],
            ['label' => 'Total Quote',    'value' => $totalQuotes,  'change' => $this->calcChange($totalQuotes, $prevQuotes),      'changeLabel' => 'vs previous period', 'color' => 'blue',   'icon' => 'file'],
            ['label' => 'Total Job',      'value' => $totalJobs,    'change' => $this->calcChange($totalJobs, $prevJobs),          'changeLabel' => 'vs previous period', 'color' => 'purple', 'icon' => 'chart'],
            ['label' => 'Total Invoice',  'value' => $totalInvoices,'change' => $this->calcChange($totalInvoices, $prevInvoices),  'changeLabel' => 'vs previous period', 'color' => 'orange', 'icon' => 'invoice'],
            ['label' => 'Total Booking',  'value' => $totalBookings,'change' => $this->calcChange($totalBookings, $prevBookings),  'changeLabel' => 'vs previous period', 'color' => 'cyan',   'icon' => 'calendar'],
        ];
    }

    // ── Schedule ─────────────────────────────────────────────────────────────

    private function getScheduleRange(int $vendorId, Carbon $from, Carbon $to): array
    {
        return Schedule::where('vendor_id', $vendorId)
            ->whereBetween('start_datetime', [$from, $to])
            ->with(['job.client', 'job.customer'])
            ->orderBy('start_datetime')
            ->limit(10)
            ->get()
            ->map(function ($schedule) {
                $job        = $schedule->job;
                $clientName = $job?->customer?->name
                    ?? $job?->client?->name
                    ?? $job?->client?->full_name
                    ?? $schedule->client_name
                    ?? 'N/A';

                return [
                    'id'      => $schedule->id,
                    'time'    => Carbon::parse($schedule->start_datetime)->format('h:i A'),
                    'endTime' => $schedule->end_datetime ? Carbon::parse($schedule->end_datetime)->format('h:i A') : null,
                    'client'  => $clientName,
                    'service' => $job?->title ?? $schedule->title ?? 'N/A',
                    'address' => $job?->customer?->address ?? $job?->client?->address ?? '',
                    'status'  => $schedule->status ?? 'scheduled',
                    'statusColor' => $this->jobStatusColor($schedule->status ?? 'scheduled'),
                ];
            })->toArray();
    }

    // ── Team Status ──────────────────────────────────────────────────────────

    private function getTeamStatus(int $vendorId, Carbon $from, Carbon $to): array
    {
        $employees   = Employee::where('vendor_id', $vendorId)->get(['id', 'name', 'first_name', 'last_name', 'role', 'is_active', 'profile_photo_path']);
        $employeeIds = $employees->pluck('id')->toArray();

        $activeSchedules = empty($employeeIds) ? collect() : Schedule::whereBetween('start_datetime', [$from, $to])
            ->whereHas('jobAssignments', fn($q) => $q->whereIn('employee_id', $employeeIds))
            ->with(['job', 'jobAssignments'])
            ->get();

        $busyEmployeeIds = $activeSchedules->flatMap(fn($s) => $s->jobAssignments->pluck('employee_id'))->unique()->toArray();

        $grouped = ['ideal' => 0, 'busy' => 0, 'offline' => 0];
        $list    = [];

        foreach ($employees as $emp) {
            $status = in_array($emp->id, $busyEmployeeIds) ? 'busy' : 'ideal';
            if (!$emp->is_active) $status = 'offline';
            $grouped[$status]++;

            $currentSchedule = $activeSchedules->first(fn($s) => $s->jobAssignments->contains('employee_id', $emp->id));

            $list[] = [
                'id'          => $emp->id,
                'name'        => trim(($emp->name ?? '') ?: (($emp->first_name ?? '') . ' ' . ($emp->last_name ?? ''))),
                'role'        => $emp->role ?? 'employee',
                'avatar'      => $emp->profile_photo_path,
                'status'      => $status,
                'currentTask' => $currentSchedule?->job?->title,
                'jobTime'     => $currentSchedule
                    ? Carbon::parse($currentSchedule->start_datetime)->format('h:i A') . ' - ' . ($currentSchedule->end_datetime ? Carbon::parse($currentSchedule->end_datetime)->format('h:i A') : '')
                    : null,
            ];
        }

        $total = max($employees->count(), 1);

        return [
            'summary' => [
                ['label' => 'Ideal Sitting', 'key' => 'ideal',   'count' => $grouped['ideal'],   'percent' => round($grouped['ideal']   / $total * 100) . '%'],
                ['label' => 'Busy',          'key' => 'busy',    'count' => $grouped['busy'],    'percent' => round($grouped['busy']    / $total * 100) . '%'],
                ['label' => 'Offline',       'key' => 'offline', 'count' => $grouped['offline'], 'percent' => round($grouped['offline'] / $total * 100) . '%'],
            ],
            'members' => $list,
        ];
    }

    // ── Total Earning ────────────────────────────────────────────────────────

    private function getTotalEarning(int $vendorId, Carbon $from, Carbon $to): array
    {
        [$prevFrom, $prevTo] = $this->getPreviousRange($from, $to);

        $employeeIds = Employee::where('vendor_id', $vendorId)->pluck('id')->toArray();

        $current  = empty($employeeIds) ? 0 : Invoice::whereIn('employee_id', $employeeIds)->whereBetween('created_at', [$from, $to])->sum(DB::raw('COALESCE(mileage,0) + COALESCE(other_expense,0)'));
        $previous = empty($employeeIds) ? 0 : Invoice::whereIn('employee_id', $employeeIds)->whereBetween('created_at', [$prevFrom, $prevTo])->sum(DB::raw('COALESCE(mileage,0) + COALESCE(other_expense,0)'));

        return [
            'amount'      => number_format((float) $current, 2),
            'change'      => $this->calcChange($current, $previous),
            'changeLabel' => 'vs previous period',
        ];
    }

    // ── Earning Chart ────────────────────────────────────────────────────────

    private function getEarningChartData(int $vendorId, Carbon $from, Carbon $to): array
    {
        $employeeIds = Employee::where('vendor_id', $vendorId)->pluck('id')->toArray();
        if (empty($employeeIds)) return [];

        $rows = Invoice::whereIn('employee_id', $employeeIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, SUM(COALESCE(mileage,0) + COALESCE(other_expense,0)) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $data   = [];
        $cursor = $from->copy();
        $limit  = min($to->diffInDays($from) + 1, 60);

        for ($i = 0; $i < $limit; $i++) {
            $key    = $cursor->toDateString();
            $data[] = ['date' => $cursor->format('M j'), 'value' => isset($rows[$key]) ? (float) $rows[$key]->total : 0];
            $cursor->addDay();
        }

        return $data;
    }

    // ── Recent Quotes ────────────────────────────────────────────────────────

    private function getRecentQuotes(int $vendorId, Carbon $from, Carbon $to): array
    {
        return Quote::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['customer', 'client'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($quote) {
                $clientName = $quote->customer?->name
                    ?? $quote->client?->name
                    ?? $quote->client?->full_name
                    ?? $quote->client_name
                    ?? 'N/A';
                return [
                    'id'     => $quote->id,
                    'client' => $clientName,
                    'amount' => '$' . number_format((float) $quote->total_amount, 2),
                    'status' => $quote->status,
                    'date'   => $quote->created_at?->format('M d, Y') ?? '',
                ];
            })->toArray();
    }

    // ── Recent Invoices ──────────────────────────────────────────────────────

    private function getRecentInvoices(int $vendorId, Carbon $from, Carbon $to): array
    {
        $employeeIds = Employee::where('vendor_id', $vendorId)->pluck('id')->toArray();
        if (empty($employeeIds)) return [];

        return Invoice::whereIn('employee_id', $employeeIds)
            ->whereBetween('created_at', [$from, $to])
            ->with('customer')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($inv) => [
                'id'     => $inv->id,
                'number' => 'Invoice #' . $inv->invoice_number,
                'client' => $inv->customer?->name ?? 'N/A',
                'amount' => '$' . number_format(($inv->mileage ?? 0) + ($inv->other_expense ?? 0), 2),
                'status' => $inv->status,
            ])->toArray();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function calcChange(float $current, float $previous): string
    {
        if ($previous == 0) return $current > 0 ? '+100%' : '0%';
        $pct = round((($current - $previous) / $previous) * 100);
        return ($pct >= 0 ? '+' : '') . $pct . '%';
    }

    private function jobStatusColor(string $status): string
    {
        return match ($status) {
            'in_progress' => 'blue',
            'completed'   => 'green',
            'cancelled'   => 'red',
            default       => 'gray',
        };
    }

    private function resolveDateRange(Request $request): array
    {
        $fromParam = $request->query('from');
        $toParam   = $request->query('to');

        if (!$fromParam && !$toParam) {
            return [Carbon::parse('2000-01-01')->startOfDay(), Carbon::parse('2099-12-31')->endOfDay()];
        }

        try { $from = $fromParam ? Carbon::parse($fromParam)->startOfDay() : Carbon::today()->startOfDay(); }
        catch (\Exception $e) { $from = Carbon::today()->startOfDay(); }

        try { $to = $toParam ? Carbon::parse($toParam)->endOfDay() : $from->copy()->endOfDay(); }
        catch (\Exception $e) { $to = $from->copy()->endOfDay(); }

        if ($to->lt($from)) { [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()]; }

        return [$from, $to];
    }

    private function getPreviousRange(Carbon $from, Carbon $to): array
    {
        $days    = $from->diffInDays($to) + 1;
        $prevTo  = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();
        return [$prevFrom, $prevTo];
    }
}