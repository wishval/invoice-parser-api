<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf;

class PdfConverter
{
    /**
     * Convert all pages of a PDF to JPEG images at 150 DPI, 85% quality.
     *
     * @param  string  $pdfPath  Absolute path to the PDF file.
     * @param  int  $invoiceId  Invoice ID used for naming output files.
     * @return array<string> Array of absolute paths to the generated JPEG images.
     *
     * @throws RuntimeException If the PDF file does not exist or conversion fails.
     */
    public function convert(string $pdfPath, int $invoiceId): array
    {
        if (! file_exists($pdfPath)) {
            throw new RuntimeException("PDF file not found: {$pdfPath}");
        }

        $outputDir = storage_path('app/temp');
        File::ensureDirectoryExists($outputDir);

        try {
            $pdf = new Pdf($pdfPath);

            $pdf->resolution(150)
                ->quality(85)
                ->format(OutputFormat::Jpg);

            $pageCount = $pdf->pageCount();

            if ($pageCount === 0) {
                throw new RuntimeException("PDF has no pages: {$pdfPath}");
            }

            $imagePaths = [];

            for ($page = 1; $page <= $pageCount; $page++) {
                $outputPath = "{$outputDir}/invoice_{$invoiceId}_page_{$page}.jpg";

                $pdf->selectPage($page)->save($outputPath);

                if (! file_exists($outputPath)) {
                    throw new RuntimeException("Failed to create image for page {$page} of invoice {$invoiceId}");
                }

                $imagePaths[] = $outputPath;
            }

            return $imagePaths;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "PDF conversion failed for invoice {$invoiceId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
