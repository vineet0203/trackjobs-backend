<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Customer;
use App\Models\Job;
use Illuminate\Http\JsonResponse;

class CustomerJobController extends BaseController
{
    public function index(): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer();

        $jobs = Job::query()
            ->with(['quote:id,job_id,customer_id,client_email'])
            ->where(function ($query) use ($customer) {
                $query->where('customer_id', $customer->id)
                    ->orWhereHas('quote', function ($quoteQuery) use ($customer) {
                        $quoteQuery->where('customer_id', $customer->id)
                            ->orWhere('client_email', $customer->email);
                    });
            })
            ->latest('id')
            ->paginate(15);

        return $this->successResponse([
            'data' => $jobs->map(fn (Job $job) => $this->transformJob($job))->values(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
        ], 'Customer jobs retrieved successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer();

        $job = Job::query()
            ->with([
                'quote:id,job_id,customer_id,client_email',
                'schedules:id,job_id,schedule_date,start_time,end_time,address,status',
            ])
            ->where('id', $id)
            ->where(function ($query) use ($customer) {
                $query->where('customer_id', $customer->id)
                    ->orWhereHas('quote', function ($quoteQuery) use ($customer) {
                        $quoteQuery->where('customer_id', $customer->id)
                            ->orWhere('client_email', $customer->email);
                    });
            })
            ->first();

        if (!$job) {
            return $this->notFoundResponse('Job not found.');
        }

        return $this->successResponse($this->transformJob($job, true), 'Customer job retrieved successfully.');
    }

    private function getAuthenticatedCustomer(): Customer
    {
        $customerData = request()->attributes->get('customer');
        return Customer::findOrFail((int) $customerData['id']);
    }

    private function transformJob(Job $job, bool $withSchedules = false): array
    {
        $payload = [
            'id' => $job->id,
            'job_number' => $job->job_number,
            'title' => $job->title,
            'description' => $job->description,
            'status' => $job->status,
            'priority' => $job->priority,
            'issue_date' => optional($job->issue_date)->format('Y-m-d'),
            'start_date' => optional($job->start_date)->format('Y-m-d'),
            'end_date' => optional($job->end_date)->format('Y-m-d'),
            'location' => [
                'type' => $job->location_type,
                'address_line_1' => $job->address_line_1,
                'address_line_2' => $job->address_line_2,
                'city' => $job->city,
                'state' => $job->state,
                'country' => $job->country,
                'zip_code' => $job->zip_code,
                'full_address' => implode(', ', array_filter([
                    $job->address_line_1,
                    $job->address_line_2,
                    $job->city,
                    $job->state,
                    $job->country,
                    $job->zip_code,
                ])),
            ],
            'job_rate' => [
                'currency' => $job->currency,
                'amount' => (float) ($job->estimated_amount ?: $job->total_amount),
                'formatted' => $job->currency . ' ' . number_format((float) ($job->estimated_amount ?: $job->total_amount), 2),
            ],
            'total_amount' => (float) $job->total_amount,
            'paid_amount' => (float) $job->paid_amount,
            'balance_due' => (float) $job->balance_due,
            'can_edit' => false,
            'can_delete' => false,
        ];

        if ($withSchedules) {
            $payload['schedules'] = $job->schedules->map(fn ($schedule) => [
                'id' => $schedule->id,
                'schedule_date' => optional($schedule->schedule_date)->format('Y-m-d'),
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'location' => $schedule->address,
                'status' => $schedule->status,
            ])->values();
        }

        return $payload;
    }
}
