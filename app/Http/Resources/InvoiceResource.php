<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'original_filename' => $this->original_filename,

            // Vendor info
            'vendor_name' => $this->whenNotNull($this->vendor_name),
            'vendor_address' => $this->whenNotNull($this->vendor_address),
            'vendor_tax_id' => $this->whenNotNull($this->vendor_tax_id),

            // Customer info
            'customer_name' => $this->whenNotNull($this->customer_name),
            'customer_address' => $this->whenNotNull($this->customer_address),
            'customer_tax_id' => $this->whenNotNull($this->customer_tax_id),

            // Invoice metadata
            'invoice_number' => $this->whenNotNull($this->invoice_number),
            'invoice_date' => $this->whenNotNull($this->invoice_date?->toDateString()),
            'due_date' => $this->whenNotNull($this->due_date?->toDateString()),
            'currency' => $this->whenNotNull($this->currency),

            // Financials
            'subtotal' => $this->whenNotNull($this->subtotal),
            'tax_amount' => $this->whenNotNull($this->tax_amount),
            'total' => $this->whenNotNull($this->total),

            // AI confidence
            'confidence_scores' => $this->whenNotNull($this->confidence_scores),

            // Line items (only when eager-loaded)
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),

            // Error info
            'error_message' => $this->when($this->status === 'failed', $this->error_message),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
