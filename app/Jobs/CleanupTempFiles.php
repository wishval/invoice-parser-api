<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupTempFiles implements ShouldQueue
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
        $invoiceId = $this->invoice->id;
        $manifestPath = storage_path("app/temp/invoice_{$invoiceId}_manifest.json");
        $parsedPath = storage_path("app/temp/invoice_{$invoiceId}_parsed.json");
        $deletedCount = 0;

        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);

            if (is_array($manifest) && isset($manifest['images'])) {
                foreach ($manifest['images'] as $imagePath) {
                    if (file_exists($imagePath)) {
                        File::delete($imagePath);
                        $deletedCount++;
                    }
                }
            }

            File::delete($manifestPath);
            $deletedCount++;
        }

        if (file_exists($parsedPath)) {
            File::delete($parsedPath);
            $deletedCount++;
        }

        Log::info("CleanupTempFiles completed for invoice {$invoiceId}: {$deletedCount} files deleted");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("CleanupTempFiles failed for invoice {$this->invoice->id}: {$exception->getMessage()}");
    }
}
