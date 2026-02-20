<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\PdfConverter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ConvertPdfToImages implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        #[WithoutRelations]
        public Invoice $invoice,
    ) {}

    public function handle(PdfConverter $converter): void
    {
        $pdfPath = Storage::disk('local')->path($this->invoice->stored_path);

        Log::info("ConvertPdfToImages starting for invoice {$this->invoice->id}", [
            'pdf_path' => $pdfPath,
        ]);

        $imagePaths = $converter->convert($pdfPath, $this->invoice->id);

        $manifestPath = storage_path("app/temp/invoice_{$this->invoice->id}_manifest.json");
        file_put_contents($manifestPath, json_encode($imagePaths, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Log::info("ConvertPdfToImages completed for invoice {$this->invoice->id}", [
            'pages_converted' => count($imagePaths),
            'manifest' => $manifestPath,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ConvertPdfToImages failed for invoice {$this->invoice->id}: {$exception->getMessage()}");

        $this->invoice->update([
            'status' => 'failed',
            'error_message' => "PDF conversion failed: {$exception->getMessage()}",
        ]);
    }
}
