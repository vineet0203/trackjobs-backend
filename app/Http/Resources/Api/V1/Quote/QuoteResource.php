<?php

namespace App\Http\Resources\Api\V1\Quote;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote_number' => $this->quote_number,
            'title' => $this->title,
            'client_name' => $this->client_name,
            'client_email' => $this->client_email,
            
            // Pricing
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total_amount' => $this->total_amount,
            'deposit_type' => $this->deposit_type,
            'deposit_amount' => $this->deposit_amount,
            
            // Status
            'status' => $this->status,
            'client_signature' => $this->client_signature,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'sent_at' => $this->sent_at?->format('Y-m-d H:i:s'),
            
            // Follow-ups
            'follow_up_at' => $this->follow_up_at?->format('Y-m-d H:i:s'),
            'reminder_type' => $this->reminder_type,
            'follow_up_status' => $this->follow_up_status,
            
            // Dates
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'items' => QuoteItemResource::collection($this->whenLoaded('items')),
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'updater' => $this->whenLoaded('updater', fn() => [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
                'email' => $this->updater->email,
            ]),
            
            // Meta
            'notes' => $this->notes,
            'can_edit' => $this->canBeEdited(),
            'can_send' => $this->canBeSent(),
        ];
    }
}