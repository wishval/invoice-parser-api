<?php

namespace Tests\Unit\Services;

use App\Services\InvoiceValidator;
use DomainException;
use InvalidArgumentException;
use Tests\TestCase;

class InvoiceValidatorTest extends TestCase
{
    private InvoiceValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new InvoiceValidator();
    }

    private function validInvoiceData(): array
    {
        return [
            'vendor' => [
                'name' => 'Acme Corp',
                'address' => '123 Main St, Springfield',
                'tax_id' => 'US123456789',
            ],
            'customer' => [
                'name' => 'Jane Doe',
                'address' => '456 Oak Ave, Shelbyville',
                'tax_id' => 'US987654321',
            ],
            'metadata' => [
                'invoice_number' => 'INV-2026-001',
                'invoice_date' => '2026-01-15',
                'due_date' => '2026-02-15',
                'currency' => 'USD',
            ],
            'totals' => [
                'subtotal' => 100.00,
                'tax_amount' => 20.00,
                'total' => 120.00,
            ],
            'line_items' => [
                [
                    'description' => 'Widget A',
                    'quantity' => 2,
                    'unit_price' => 25.00,
                    'amount' => 50.00,
                    'tax' => 10.00,
                ],
                [
                    'description' => 'Widget B',
                    'quantity' => 1,
                    'unit_price' => 50.00,
                    'amount' => 50.00,
                    'tax' => 10.00,
                ],
            ],
            'confidence' => [
                'vendor' => 95,
                'customer' => 90,
                'metadata' => 85,
                'totals' => 92,
                'line_items' => 88,
            ],
        ];
    }

    public function test_validates_complete_valid_data(): void
    {
        $data = $this->validInvoiceData();

        $result = $this->validator->validate($data);

        $this->assertIsArray($result);
        $this->assertEquals('Acme Corp', $result['vendor']['name']);
        $this->assertCount(2, $result['line_items']);
    }

    public function test_rejects_missing_vendor(): void
    {
        $data = $this->validInvoiceData();
        unset($data['vendor']);

        $this->expectException(InvalidArgumentException::class);

        $this->validator->validate($data);
    }

    public function test_rejects_missing_line_items(): void
    {
        $data = $this->validInvoiceData();
        unset($data['line_items']);

        $this->expectException(InvalidArgumentException::class);

        $this->validator->validate($data);
    }

    public function test_rejects_empty_line_items(): void
    {
        $data = $this->validInvoiceData();
        $data['line_items'] = [];

        $this->expectException(InvalidArgumentException::class);

        $this->validator->validate($data);
    }

    public function test_rejects_line_item_missing_description(): void
    {
        $data = $this->validInvoiceData();
        unset($data['line_items'][0]['description']);

        $this->expectException(InvalidArgumentException::class);

        $this->validator->validate($data);
    }

    public function test_validate_totals_passes_when_amounts_match(): void
    {
        $data = $this->validInvoiceData();
        // subtotal=100, tax=20, total=120 matches line items sum
        $data['totals']['subtotal'] = 100.00;
        $data['totals']['tax_amount'] = 20.00;
        $data['totals']['total'] = 120.00;

        // Should not throw
        $this->validator->validateTotals($data);

        $this->assertTrue(true);
    }

    public function test_validate_totals_throws_on_mismatch(): void
    {
        $data = $this->validInvoiceData();
        $data['totals']['total'] = 500.00;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/total mismatch/i');

        $this->validator->validateTotals($data);
    }

    public function test_validate_totals_skips_when_total_is_null(): void
    {
        $data = $this->validInvoiceData();
        $data['totals']['total'] = null;

        // Should not throw — early return when total is null
        $this->validator->validateTotals($data);

        $this->assertTrue(true);
    }

    public function test_validate_totals_logs_subtotal_mismatch_but_does_not_throw(): void
    {
        $data = $this->validInvoiceData();
        // Line items sum: amount=50+50=100, tax=10+10=20, total=120
        // Set subtotal to 99 (mismatch) but total matches
        $data['totals']['subtotal'] = 99.00;
        $data['totals']['total'] = 120.00;

        // Should not throw — subtotal mismatch only logs a warning
        $this->validator->validateTotals($data);

        $this->assertTrue(true);
    }
}
