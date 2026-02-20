<?php

namespace App\Services;

use App\Data\InvoiceSchema;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class InvoiceParser
{
    /**
     * Send invoice page images to OpenAI GPT-4o Vision and return structured data.
     *
     * @param  array<string>  $imagePaths  Absolute paths to JPEG page images.
     * @return array<string, mixed>  Parsed invoice data matching InvoiceSchema.
     *
     * @throws RuntimeException If the API call fails or response cannot be decoded.
     */
    public function parse(array $imagePaths): array
    {
        if (empty($imagePaths)) {
            throw new RuntimeException('No image paths provided for invoice parsing');
        }

        $imageContent = [];

        foreach ($imagePaths as $imagePath) {
            if (! file_exists($imagePath)) {
                throw new RuntimeException("Image file not found: {$imagePath}");
            }

            $base64 = base64_encode(file_get_contents($imagePath));

            $imageContent[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:image/jpeg;base64,{$base64}",
                    'detail' => 'high',
                ],
            ];
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an invoice data extraction assistant. Extract all fields from the invoice images. For fields you cannot find or read, return null. For confidence scores, rate 0-100 how confident you are in each section\'s extraction accuracy. Return 0 confidence if the section data is mostly null.',
            ],
            [
                'role' => 'user',
                'content' => array_merge(
                    [
                        [
                            'type' => 'text',
                            'text' => 'Extract all invoice data from the following document pages.',
                        ],
                    ],
                    $imageContent,
                ),
            ],
        ];

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-2024-08-06',
                'messages' => $messages,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'invoice_extraction',
                        'strict' => true,
                        'schema' => InvoiceSchema::schema(),
                    ],
                ],
                'max_tokens' => 4096,
            ]);
        } catch (\Throwable $e) {
            Log::error('OpenAI API call failed during invoice parsing', [
                'error' => $e->getMessage(),
                'image_count' => count($imagePaths),
            ]);

            throw new RuntimeException(
                "OpenAI API call failed: {$e->getMessage()}",
                0,
                $e
            );
        }

        $content = $response->choices[0]->message->content;
        $decoded = json_decode($content, true);

        if ($decoded === null) {
            Log::error('Failed to decode OpenAI response', [
                'raw_content' => mb_substr($content, 0, 500),
            ]);

            throw new RuntimeException('Failed to decode OpenAI response');
        }

        return $decoded;
    }
}
