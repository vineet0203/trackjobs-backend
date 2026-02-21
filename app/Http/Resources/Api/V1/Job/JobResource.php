<?php
// app/Http/Resources/Api/V1/Job/JobResource.php

namespace App\Http\Resources\Api\V1\Job;

use App\Http\Resources\Api\V1\Client\ClientResource;
use App\Http\Resources\Api\V1\Quote\QuoteResource;
use App\Http\Resources\Api\V1\User\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'job_number' => $this->job_number,
            'title' => $this->title,
            'description' => $this->description,
            
            // Relationships - Check if loaded before using
            'client' => $this->whenLoaded('client', function() {
                return new ClientResource($this->client);
            }),
            'quote' => $this->whenLoaded('quote', function() {
                return new QuoteResource($this->quote);
            }),
            'assigned_to' => $this->whenLoaded('assignedTo', function() {
                return new UserResource($this->assignedTo);
            }),
            'created_by' => $this->whenLoaded('createdBy', function() {
                return new UserResource($this->createdBy);
            }),
            'updated_by' => $this->whenLoaded('updatedBy', function() {
                return new UserResource($this->updatedBy);
            }),
            
            // Work order details
            'work_type' => $this->work_type,
            'priority' => $this->priority,
            'status' => $this->status,
            
            // Dates
            'issue_date' => $this->issue_date?->format('M d, Y'),
            'start_date' => $this->start_date?->format('M d, Y'),
            'end_date' => $this->end_date?->format('M d, Y'),
            'estimated_completion_date' => $this->estimated_completion_date?->format('M d, Y'),
            'actual_completion_date' => $this->actual_completion_date?->format('M d, Y H:i'),
            
            // Financial
            'currency' => $this->currency,
            'estimated_amount' => (float) $this->estimated_amount,
            'total_amount' => (float) $this->total_amount,
            'deposit_amount' => (float) $this->deposit_amount,
            'paid_amount' => (float) $this->paid_amount,
            'balance_due' => (float) $this->balance_due,
            'formatted_total' => $this->currency . ' ' . number_format((float) $this->total_amount, 2),
            'formatted_balance' => $this->currency . ' ' . number_format((float) $this->balance_due, 2),
            
            // Location
            'location_type' => $this->location_type,
            'address' => [
                'address_line_1' => $this->address_line_1,
                'address_line_2' => $this->address_line_2,
                'city' => $this->city,
                'state' => $this->state,
                'country' => $this->country,
                'zip_code' => $this->zip_code,
                'full_address' => $this->getFullAddressAttribute(),
            ],
            
            // Additional info
            'instructions' => $this->instructions,
            'notes' => $this->notes,
            
            // Conversion tracking
            'is_converted_from_quote' => (bool) $this->is_converted_from_quote,
            'converted_at' => $this->converted_at?->format('M d, Y H:i'),
            
            // Collections - Use whenLoaded with fallback to empty collection
            'tasks' => $this->whenLoaded('tasks', function() {
                return JobTaskResource::collection($this->tasks);
            }, []),
            'attachments' => $this->whenLoaded('attachments', function() {
                return JobAttachmentResource::collection($this->attachments);
            }, []),
            'activities' => $this->whenLoaded('activities', function() {
                return JobActivityResource::collection($this->activities);
            }, []),
            'timeline' => $this->whenLoaded('timeline', function() {
                return JobTimelineResource::collection($this->timeline);
            }, []),
            
            // Stats - Calculate safely
            'stats' => [
                'total_tasks' => $this->whenLoaded('tasks', function() {
                    return $this->tasks->count();
                }, 0),
                'completed_tasks' => $this->whenLoaded('tasks', function() {
                    return $this->tasks->where('completed', true)->count();
                }, 0),
                'pending_tasks' => $this->whenLoaded('tasks', function() {
                    return $this->tasks->where('completed', false)->count();
                }, 0),
                'total_attachments' => $this->whenLoaded('attachments', function() {
                    return $this->attachments->count();
                }, 0),
            ],
            
            // Timestamps
            'created_at' => $this->created_at?->format('M d, Y H:i'),
            'updated_at' => $this->updated_at?->format('M d, Y H:i'),
            'deleted_at' => $this->deleted_at?->format('M d, Y H:i'),
        ];
    }

    protected function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->zip_code,
            $this->country,
        ]);

        return empty($parts) ? null : implode(', ', $parts);
    }
}