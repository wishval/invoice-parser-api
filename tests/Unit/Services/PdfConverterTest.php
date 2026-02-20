<?php

namespace Tests\Unit\Services;

use App\Services\PdfConverter;
use RuntimeException;
use Tests\TestCase;

class PdfConverterTest extends TestCase
{
    private PdfConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = new PdfConverter();
    }

    public function test_convert_throws_on_missing_pdf(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PDF file not found');

        $this->converter->convert('/nonexistent/file.pdf', 1);
    }
}
