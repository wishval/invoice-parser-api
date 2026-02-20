<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\InvoiceParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ParseInvoiceWithAI implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300;
    public array $backoff = [30, 60, 120];
    public int $maxExceptions = 2;

    public function __construct(
        #[WithoutRelations]
        public Invoice $invoice,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(10, 5 * 60))->backoff(30),
        ];
    }

    public function handle(): void
    {
        $invoiceId = $this->invoice->id;

        $manifestPath = storage_path("app/temp/invoice_{$invoiceId}_manifest.json");

        if (! file_exists($manifestPath)) {
            throw new RuntimeException("Image manifest not found for invoice {$invoiceId}");
        }

        $imagePaths = json_decode(file_get_contents($manifestPath), true);

        if (! is_array($imagePaths) || empty($imagePaths)) {
            throw new RuntimeException("Invalid or empty image manifest for invoice {$invoiceId}");
        }

        foreach ($imagePaths as $imagePath) {
            if (! file_exists($imagePath)) {
                throw new RuntimeException("Image file missing: {$imagePath} for invoice {$invoiceId}");
            }
        }

        Log::info("ParseInvoiceWithAI starting for invoice {$invoiceId}", [
            'pages' => count($imagePaths),
        ]);

        $parser = new InvoiceParser();
        $parsedData = $parser->parse($imagePaths);

        $parsedPath = storage_path("app/temp/invoice_{$invoiceId}_parsed.json");
        file_put_contents($parsedPath, json_encode($parsedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $fieldCount = count(array_filter($parsedData, fn ($v) => $v !== null));

        Log::info("ParseInvoiceWithAI completed for invoice {$invoiceId}", [
            'parsed_path' => $parsedPath,
            'sections_extracted' => $fieldCount,
            'line_items_count' => isset($parsedData['line_items']) ? count($parsedData['line_items']) : 0,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ParseInvoiceWithAI failed for invoice {$this->invoice->id}: {$exception->getMessage()}");

        $this->invoice->update([
            'status' => 'failed',
            'error_message' => "AI parsing failed: {$exception->getMessage()}",
        ]);
    }
}
