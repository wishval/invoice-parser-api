<?php

namespace Tests\Unit\Services;

use App\Services\InvoiceParser;
use Illuminate\Support\Facades\File;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use RuntimeException;
use Tests\TestCase;

class InvoiceParserTest extends TestCase
{
    private InvoiceParser $parser;

    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new InvoiceParser();
    }

    protected function tearDown(): void
    {
        File::delete($this->tempFiles);

        parent::tearDown();
    }

    private function createTempImage(): string
    {
        $img = imagecreatetruecolor(1, 1);
        $path = tempnam(sys_get_temp_dir(), 'test_img_') . '.jpg';
        imagejpeg($img, $path);
        imagedestroy($img);

        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_parse_returns_decoded_json_from_openai(): void
    {
        $expectedData = [
            'vendor' => ['name' => 'Test Vendor', 'address' => null, 'tax_id' => null],
            'customer' => ['name' => 'Test Customer', 'address' => null, 'tax_id' => null],
            'metadata' => ['invoice_number' => 'INV-001', 'invoice_date' => null, 'due_date' => null, 'currency' => 'USD'],
            'totals' => ['subtotal' => 100, 'tax_amount' => 0, 'total' => 100],
            'line_items' => [
                ['description' => 'Item 1', 'quantity' => 1, 'unit_price' => 100, 'amount' => 100, 'tax' => null],
            ],
            'confidence' => ['vendor' => 80, 'customer' => 75, 'metadata' => 90, 'totals' => 85, 'line_items' => 70],
        ];

        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($expectedData),
                        ],
                    ],
                ],
            ]),
        ]);

        $imagePath = $this->createTempImage();

        $result = $this->parser->parse([$imagePath]);

        $this->assertEquals($expectedData, $result);
    }

    public function test_parse_throws_on_empty_image_paths(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No image paths');

        $this->parser->parse([]);
    }

    public function test_parse_throws_on_missing_image_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Image file not found');

        $this->parser->parse(['/nonexistent/file.jpg']);
    }

    public function test_parse_throws_on_invalid_json_response(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'This is not valid JSON at all',
                        ],
                    ],
                ],
            ]),
        ]);

        $imagePath = $this->createTempImage();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode');

        $this->parser->parse([$imagePath]);
    }
}
