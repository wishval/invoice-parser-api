<?php

namespace App\Data;

class InvoiceSchema
{
    /**
     * Return the full JSON schema for OpenAI Structured Outputs.
     *
     * Every object level uses "strict": true and "additionalProperties": false.
     * Nullable fields use the ["string", "null"] type pattern required by OpenAI.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['vendor', 'customer', 'metadata', 'totals', 'line_items', 'confidence'],
            'properties' => [
                'vendor' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['name', 'address', 'tax_id'],
                    'properties' => [
                        'name' => ['type' => ['string', 'null']],
                        'address' => ['type' => ['string', 'null']],
                        'tax_id' => ['type' => ['string', 'null']],
                    ],
                ],
                'customer' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['name', 'address', 'tax_id'],
                    'properties' => [
                        'name' => ['type' => ['string', 'null']],
                        'address' => ['type' => ['string', 'null']],
                        'tax_id' => ['type' => ['string', 'null']],
                    ],
                ],
                'metadata' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['invoice_number', 'invoice_date', 'due_date', 'currency'],
                    'properties' => [
                        'invoice_number' => ['type' => ['string', 'null']],
                        'invoice_date' => ['type' => ['string', 'null']],
                        'due_date' => ['type' => ['string', 'null']],
                        'currency' => ['type' => ['string', 'null']],
                    ],
                ],
                'totals' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['subtotal', 'tax_amount', 'total'],
                    'properties' => [
                        'subtotal' => ['type' => ['number', 'null']],
                        'tax_amount' => ['type' => ['number', 'null']],
                        'total' => ['type' => ['number', 'null']],
                    ],
                ],
                'line_items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['description', 'quantity', 'unit_price', 'amount', 'tax'],
                        'properties' => [
                            'description' => ['type' => 'string'],
                            'quantity' => ['type' => 'number'],
                            'unit_price' => ['type' => 'number'],
                            'amount' => ['type' => 'number'],
                            'tax' => ['type' => ['number', 'null']],
                        ],
                    ],
                ],
                'confidence' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['vendor', 'customer', 'metadata', 'totals', 'line_items'],
                    'properties' => [
                        'vendor' => ['type' => 'integer'],
                        'customer' => ['type' => 'integer'],
                        'metadata' => ['type' => 'integer'],
                        'totals' => ['type' => 'integer'],
                        'line_items' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];
    }
}
