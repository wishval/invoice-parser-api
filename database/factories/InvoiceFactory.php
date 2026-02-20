<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 10000);
        $taxAmount = round($subtotal * 0.2, 2);
        $total = round($subtotal + $taxAmount, 2);

        return [
            'user_id' => User::factory(),
            'original_filename' => fake()->word() . '.pdf',
            'stored_path' => 'invoices/' . fake()->uuid() . '.pdf',
            'status' => 'completed',
            'invoice_number' => 'INV-' . fake()->unique()->numerify('######'),
            'invoice_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'due_date' => fake()->dateTimeBetween('now', '+3 months'),
            'currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
            'vendor_name' => fake()->company(),
            'vendor_address' => fake()->address(),
            'vendor_tax_id' => fake()->numerify('##-#######'),
            'customer_name' => fake()->company(),
            'customer_address' => fake()->address(),
            'customer_tax_id' => fake()->numerify('##-#######'),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'confidence_scores' => [
                'vendor' => 90,
                'customer' => 85,
                'metadata' => 95,
                'totals' => 88,
                'line_items' => 92,
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'invoice_number' => null,
            'invoice_date' => null,
            'due_date' => null,
            'currency' => null,
            'vendor_name' => null,
            'vendor_address' => null,
            'vendor_tax_id' => null,
            'customer_name' => null,
            'customer_address' => null,
            'customer_tax_id' => null,
            'subtotal' => null,
            'tax_amount' => null,
            'total' => null,
            'confidence_scores' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => 'AI parsing failed: timeout',
        ]);
    }
}
