<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use DomainException;

class InvoiceValidator
{
    /**
     * Validate the parsed invoice data structure.
     *
     * @param array $data The parsed invoice data from OpenAI.
     * @return array The validated data.
     * @throws InvalidArgumentException If validation fails.
     */
    public function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'vendor' => 'required|array',
            'vendor.name' => 'nullable|string|max:255',
            'vendor.address' => 'nullable|string',
            'vendor.tax_id' => 'nullable|string|max:50',
            'customer' => 'required|array',
            'customer.name' => 'nullable|string|max:255',
            'customer.address' => 'nullable|string',
            'customer.tax_id' => 'nullable|string|max:50',
            'metadata' => 'required|array',
            'metadata.invoice_number' => 'nullable|string|max:100',
            'metadata.invoice_date' => 'nullable|string|max:20',
            'metadata.due_date' => 'nullable|string|max:20',
            'metadata.currency' => 'nullable|string|max:3',
            'totals' => 'required|array',
            'totals.subtotal' => 'nullable|numeric|min:0',
            'totals.tax_amount' => 'nullable|numeric|min:0',
            'totals.total' => 'nullable|numeric|min:0',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string|max:500',
            'line_items.*.quantity' => 'required|numeric|min:0',
            'line_items.*.unit_price' => 'required|numeric',
            'line_items.*.amount' => 'required|numeric',
            'line_items.*.tax' => 'nullable|numeric|min:0',
            'confidence' => 'required|array',
            'confidence.vendor' => 'required|integer|min:0|max:100',
            'confidence.customer' => 'required|integer|min:0|max:100',
            'confidence.metadata' => 'required|integer|min:0|max:100',
            'confidence.totals' => 'required|integer|min:0|max:100',
            'confidence.line_items' => 'required|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first());
        }

        return $validator->validated();
    }

    /**
     * Validate that line item amounts sum to the invoice total within tolerance.
     *
     * @param array $data The validated invoice data.
     * @throws DomainException If total mismatch exceeds tolerance.
     */
    public function validateTotals(array $data): void
    {
        $invoiceTotal = $data['totals']['total'] ?? null;

        if ($invoiceTotal === null) {
            return;
        }

        $calculatedSubtotal = 0;
        $calculatedTax = 0;

        foreach ($data['line_items'] as $item) {
            $calculatedSubtotal += (float) $item['amount'];
            $calculatedTax += (float) ($item['tax'] ?? 0);
        }

        $calculatedTotal = $calculatedSubtotal + $calculatedTax;
        $tolerance = 0.01;

        if (abs($calculatedTotal - (float) $invoiceTotal) > $tolerance) {
            throw new DomainException(
                "Invoice total mismatch: calculated {$calculatedTotal}, expected {$invoiceTotal}"
            );
        }

        $invoiceSubtotal = $data['totals']['subtotal'] ?? null;

        if ($invoiceSubtotal !== null && abs($calculatedSubtotal - (float) $invoiceSubtotal) > $tolerance) {
            Log::warning("Invoice subtotal mismatch: calculated {$calculatedSubtotal}, expected {$invoiceSubtotal} (rounding difference)");
        }
    }
}
