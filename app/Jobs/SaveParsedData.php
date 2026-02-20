<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Support\Facades\Log;
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
        Log::info("SaveParsedData placeholder for invoice {$this->invoice->id}");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("SaveParsedData failed for invoice {$this->invoice->id}: {$exception->getMessage()}");
    }
}
