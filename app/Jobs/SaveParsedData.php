<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\InvoiceValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SaveParsedData implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        #[WithoutRelations]
        public Invoice $invoice,
    ) {}

    public function handle(): void
    {
        $parsedPath = storage_path("app/temp/invoice_{$this->invoice->id}_parsed.json");

        if (!file_exists($parsedPath)) {
            throw new RuntimeException("Parsed data not found for invoice {$this->invoice->id}");
        }

        $data = json_decode(file_get_contents($parsedPath), true);

        if ($data === null) {
            throw new RuntimeException("Failed to decode parsed JSON for invoice {$this->invoice->id}");
        }

        $validator = new InvoiceValidator();
        $data = $validator->validate($data);
        $validator->validateTotals($data);

        DB::transaction(function () use ($data) {
            $this->invoice->update([
                'vendor_name' => $data['vendor']['name'] ?? null,
                'vendor_address' => $data['vendor']['address'] ?? null,
                'vendor_tax_id' => $data['vendor']['tax_id'] ?? null,
                'customer_name' => $data['customer']['name'] ?? null,
                'customer_address' => $data['customer']['address'] ?? null,
                'customer_tax_id' => $data['customer']['tax_id'] ?? null,
                'invoice_number' => $data['metadata']['invoice_number'] ?? null,
                'invoice_date' => $this->parseDate($data['metadata']['invoice_date'] ?? null),
                'due_date' => $this->parseDate($data['metadata']['due_date'] ?? null),
                'currency' => $data['metadata']['currency'] ?? null,
                'subtotal' => $data['totals']['subtotal'] ?? null,
                'tax_amount' => $data['totals']['tax_amount'] ?? null,
                'total' => $data['totals']['total'] ?? null,
                'confidence_scores' => $data['confidence'],
                'status' => 'completed',
            ]);

            $this->invoice->items()->delete();

            foreach ($data['line_items'] as $item) {
                $this->invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'amount' => $item['amount'],
                    'tax' => $item['tax'] ?? null,
                ]);
            }
        });

        $itemCount = count($data['line_items']);
        Log::info("SaveParsedData completed for invoice {$this->invoice->id}: {$itemCount} line items saved");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("SaveParsedData failed for invoice {$this->invoice->id}: {$exception->getMessage()}");

        $this->invoice->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }

    private function parseDate(?string $dateString): ?Carbon
    {
        if ($dateString === null) {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (Throwable) {
            return null;
        }
    }
}
