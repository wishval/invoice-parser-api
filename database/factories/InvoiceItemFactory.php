<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 100);
        $unitPrice = fake()->randomFloat(2, 1, 1000);
        $amount = round($quantity * $unitPrice, 2);

        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'tax' => fake()->optional(0.7)->randomFloat(2, 1, 500),
        ];
    }
}
