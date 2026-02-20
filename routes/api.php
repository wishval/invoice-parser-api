<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'version' => 'v1',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn(Request $request) => new UserResource($request->user()));
    Route::post('/invoices', [InvoiceController::class, 'upload'])->middleware('throttle:parse');
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download']);
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);
});
