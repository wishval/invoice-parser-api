<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Jobs\CleanupTempFiles;
use App\Jobs\ConvertPdfToImages;
use App\Jobs\ParseInvoiceWithAI;
use App\Jobs\ProcessInvoice;
use App\Jobs\SaveParsedData;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class InvoiceController extends Controller
{
    public function upload(StoreInvoiceRequest $request): JsonResponse
    {
        $path = Storage::disk('local')->putFile('invoices', $request->file('pdf'));

        $invoice = Invoice::create([
            'user_id' => $request->user()->id,
            'original_filename' => $request->file('pdf')->getClientOriginalName(),
            'stored_path' => $path,
            'status' => 'pending',
        ]);

        Bus::chain([
            new ProcessInvoice($invoice),
            new ConvertPdfToImages($invoice),
            new ParseInvoiceWithAI($invoice),
            new SaveParsedData($invoice),
            new CleanupTempFiles($invoice),
        ])
        ->onQueue('parse')
        ->catch(function (Throwable $e) use ($invoice) {
            $invoice->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);
        })
        ->dispatch();

        return (new InvoiceResource($invoice))->response()->setStatusCode(202);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        if ($invoice->user_id !== auth()->id()) {
            abort(403, 'This invoice does not belong to you.');
        }

        return (new InvoiceResource($invoice))->response();
    }
}
