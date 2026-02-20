<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

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

        return (new InvoiceResource($invoice))->response()->setStatusCode(202);
    }
}
