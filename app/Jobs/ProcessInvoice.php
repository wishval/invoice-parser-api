<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessInvoice implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300;
    public int $uniqueFor = 3600;

    public function __construct(
        #[WithoutRelations]
        public Invoice $invoice,
    ) {}

    public function uniqueId(): string
    {
        return "process-invoice-{$this->invoice->id}";
    }

    public function handle(): void
    {
        $this->invoice->update(['status' => 'processing']);

        Log::info("Processing invoice {$this->invoice->id}");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ProcessInvoice failed for invoice {$this->invoice->id}: {$exception->getMessage()}");
    }
}
