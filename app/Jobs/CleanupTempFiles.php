<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
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
        $this->invoice->update(['status' => 'completed']);

        Log::info("Invoice {$this->invoice->id} processing completed");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("CleanupTempFiles failed for invoice {$this->invoice->id}: {$exception->getMessage()}");
    }
}
