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
            'created_at' => $this->created_at->toIso8601String(),
            'invoice_number' => $this->whenNotNull($this->invoice_number),
            'vendor_name' => $this->whenNotNull($this->vendor_name),
            'total' => $this->whenNotNull($this->total),
            'currency' => $this->whenNotNull($this->currency),
            'error_message' => $this->when($this->status === 'failed', $this->error_message),
        ];
    }
}
