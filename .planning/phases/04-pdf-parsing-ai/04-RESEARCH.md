# Phase 4: PDF Parsing & AI Integration - Research

**Researched:** 2026-02-20
**Domain:** PDF to image conversion, OpenAI Vision API, structured data extraction
**Confidence:** HIGH

## Summary

Phase 4 implements the core parsing functionality: converting uploaded PDF invoices to images, sending them to OpenAI GPT-4o Vision for AI extraction, and storing validated structured invoice data. The phase builds on existing job stubs created in Phase 3.

The standard stack combines **spatie/pdf-to-image** (PHP wrapper for Imagick/Ghostscript) with **openai-php/laravel** (community-maintained OpenAI client). OpenAI's GPT-4o Vision API with Structured Outputs provides reliable JSON extraction with 100% schema adherence when using `gpt-4o-2024-08-06` or later.

Key technical challenges include: multi-page PDF processing, base64 image encoding for API transmission, prompt engineering for accurate invoice field extraction, response validation before database storage, and mathematical validation (line items must sum to totals).

**Primary recommendation:** Use GPT-4o-2024-08-06 with Structured Outputs (json_schema strict mode) to guarantee valid JSON responses. Implement comprehensive validation in SaveParsedData job before persisting to database.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| PARS-01 | Convert all PDF pages to images using Imagick/Ghostscript | spatie/pdf-to-image provides `saveAllPages()` method for multi-page conversion at configurable DPI/quality |
| PARS-02 | Send page images to OpenAI GPT-4o Vision API | openai-php/client supports vision via chat completions with base64-encoded images in content array |
| PARS-03 | Extract vendor info (name, address, tax ID) | Structured Outputs with JSON schema enforces presence of vendor fields in response |
| PARS-04 | Extract customer info (name, address, tax ID) | Structured Outputs with JSON schema enforces presence of customer fields in response |
| PARS-05 | Extract invoice metadata (number, date, due date, currency) | Structured Outputs with JSON schema enforces metadata field structure |
| PARS-06 | Extract totals (subtotal, tax amount, total) | Structured Outputs with JSON schema enforces numeric total fields |
| PARS-07 | Extract line items with description, qty, unit price, amount, tax | Structured Outputs supports nested array schemas for line items |
| PARS-08 | Validate OpenAI response structure before storing | Laravel validation with nested array rules (*.field notation) validates extracted data |
| PARS-09 | Confidence scores (0-100%) for each extracted field | GPT-4o logprobs parameter provides token-level confidence; custom prompt can request field-level scores |
| PARS-10 | Validate line item amounts sum to invoice totals | Custom validation rule in SaveParsedData job calculates sum and compares to total within tolerance |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| spatie/pdf-to-image | ^3.0 | PDF to image conversion | Industry standard PHP wrapper for Imagick, actively maintained by Spatie, supports multi-page PDFs |
| openai-php/laravel | ^0.10 | OpenAI API Laravel integration | Community-maintained official PHP client wrapper, provides facade access and Laravel config integration |
| openai-php/client | ^0.10 | OpenAI API base client | Required dependency, provides actual HTTP client for OpenAI API calls |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| ext-imagick | Latest | PHP Imagick extension | Required for spatie/pdf-to-image, handles image manipulation |
| ghostscript | 10.x | PDF rasterization | Required system binary for PDF->image conversion via Imagick |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| spatie/pdf-to-image | Raw Imagick calls | Lose convenience methods, more verbose code |
| OpenAI Vision | Claude 3.5 Sonnet Vision | Comparable accuracy, different API/pricing, less PHP ecosystem support |
| GPT-4o Vision | Traditional OCR + GPT-4 text | Higher complexity (2-step process), potential OCR accuracy loss |

**Installation:**
```bash
composer require spatie/pdf-to-image:^3.0
composer require openai-php/laravel
php artisan openai:install
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Jobs/
│   ├── ConvertPdfToImages.php    # Phase 4: PDF->images with Imagick
│   ├── ParseInvoiceWithAI.php    # Phase 4: OpenAI Vision API call
│   └── SaveParsedData.php        # Phase 4: Validation and storage
├── Services/
│   ├── PdfConverter.php          # Encapsulates spatie/pdf-to-image logic
│   ├── InvoiceParser.php         # Encapsulates OpenAI API calls
│   └── InvoiceValidator.php      # Business rule validation (totals, etc.)
└── Data/
    └── InvoiceSchema.php         # JSON schema definition for OpenAI
```

### Pattern 1: Multi-Page PDF Conversion with Cleanup
**What:** Convert all PDF pages to temporary images, track paths for later cleanup
**When to use:** Multi-page invoice PDFs (common for itemized invoices)
**Example:**
```php
// Source: spatie/pdf-to-image official docs
use Spatie\PdfToImage\Pdf;

$pdf = new Pdf($storagePath);
$pdf->setResolution(150);  // 150 DPI balances quality/file size
$pdf->setOutputFormat('jpg');
$pdf->quality(85);  // 85% JPEG quality

$imageCount = $pdf->pageCount();
$imagePaths = [];

foreach (range(1, $imageCount) as $pageNumber) {
    $path = storage_path("temp/invoice_{$invoiceId}_page_{$pageNumber}.jpg");
    $pdf->selectPage($pageNumber)->save($path);
    $imagePaths[] = $path;
}

// Store paths on invoice for cleanup job
$invoice->update(['temp_image_paths' => json_encode($imagePaths)]);
```

### Pattern 2: OpenAI Vision with Base64 Images
**What:** Encode images as base64 and send to Vision API with structured schema
**When to use:** Private images not accessible via public URL
**Example:**
```php
// Source: openai-php/client examples, OpenAI docs
use OpenAI\Laravel\Facades\OpenAI;

$messages = [
    [
        'role' => 'system',
        'content' => 'You are an invoice data extraction assistant. Extract all fields accurately.'
    ]
];

foreach ($imagePaths as $index => $imagePath) {
    $base64Image = base64_encode(file_get_contents($imagePath));
    $messages[] = [
        'role' => 'user',
        'content' => [
            [
                'type' => 'text',
                'text' => "Extract invoice data from page " . ($index + 1)
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:image/jpeg;base64,{$base64Image}",
                    'detail' => 'high'  // high|low|auto - affects token cost
                ]
            ]
        ]
    ];
}

$response = OpenAI::chat()->create([
    'model' => 'gpt-4o-2024-08-06',
    'messages' => $messages,
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'invoice_extraction',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'vendor' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                            'tax_id' => ['type' => 'string']
                        ],
                        'required' => ['name', 'address', 'tax_id'],
                        'additionalProperties' => false
                    ],
                    'line_items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'description' => ['type' => 'string'],
                                'quantity' => ['type' => 'number'],
                                'unit_price' => ['type' => 'number'],
                                'amount' => ['type' => 'number'],
                                'tax' => ['type' => 'number']
                            ],
                            'required' => ['description', 'quantity', 'unit_price', 'amount', 'tax'],
                            'additionalProperties' => false
                        ]
                    ]
                ],
                'required' => ['vendor', 'customer', 'metadata', 'totals', 'line_items'],
                'additionalProperties' => false
            ]
        ]
    ]
]);

$extractedData = json_decode($response->choices[0]->message->content, true);
```

### Pattern 3: Nested Array Validation
**What:** Validate nested line items array structure before database storage
**When to use:** After OpenAI response, before SaveParsedData persists to DB
**Example:**
```php
// Source: Laravel 11 validation docs
use Illuminate\Support\Facades\Validator;

$validator = Validator::make($extractedData, [
    'vendor' => 'required|array',
    'vendor.name' => 'required|string|max:255',
    'vendor.address' => 'required|string',
    'vendor.tax_id' => 'nullable|string|max:50',

    'line_items' => 'required|array|min:1',
    'line_items.*.description' => 'required|string|max:500',
    'line_items.*.quantity' => 'required|numeric|min:0',
    'line_items.*.unit_price' => 'required|numeric|min:0',
    'line_items.*.amount' => 'required|numeric|min:0',
    'line_items.*.tax' => 'required|numeric|min:0',

    'totals' => 'required|array',
    'totals.subtotal' => 'required|numeric|min:0',
    'totals.tax_amount' => 'required|numeric|min:0',
    'totals.total' => 'required|numeric|min:0',
]);

if ($validator->fails()) {
    throw new \InvalidArgumentException(
        'OpenAI response validation failed: ' . $validator->errors()->first()
    );
}
```

### Pattern 4: Database Transaction with Totals Validation
**What:** Atomic save of invoice + line items with mathematical validation
**When to use:** SaveParsedData job final step
**Example:**
```php
// Source: Laravel docs, invoice parsing best practices
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($invoice, $validatedData) {
    // Calculate actual totals from line items
    $calculatedSubtotal = collect($validatedData['line_items'])
        ->sum('amount');

    $calculatedTax = collect($validatedData['line_items'])
        ->sum('tax');

    $calculatedTotal = $calculatedSubtotal + $calculatedTax;

    // Validate against extracted totals (allow 0.01 tolerance for rounding)
    if (abs($calculatedTotal - $validatedData['totals']['total']) > 0.01) {
        throw new \DomainException(
            "Line items total ({$calculatedTotal}) does not match invoice total ({$validatedData['totals']['total']})"
        );
    }

    // Update invoice with extracted data
    $invoice->update([
        'vendor_name' => $validatedData['vendor']['name'],
        'vendor_address' => $validatedData['vendor']['address'],
        'vendor_tax_id' => $validatedData['vendor']['tax_id'],
        'customer_name' => $validatedData['customer']['name'],
        'customer_address' => $validatedData['customer']['address'],
        'customer_tax_id' => $validatedData['customer']['tax_id'],
        'invoice_number' => $validatedData['metadata']['invoice_number'],
        'invoice_date' => $validatedData['metadata']['invoice_date'],
        'due_date' => $validatedData['metadata']['due_date'],
        'currency' => $validatedData['metadata']['currency'],
        'subtotal' => $validatedData['totals']['subtotal'],
        'tax_amount' => $validatedData['totals']['tax_amount'],
        'total' => $validatedData['totals']['total'],
        'status' => 'completed',
    ]);

    // Insert line items
    foreach ($validatedData['line_items'] as $item) {
        $invoice->items()->create([
            'description' => $item['description'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'amount' => $item['amount'],
            'tax' => $item['tax'],
        ]);
    }
});
```

### Anti-Patterns to Avoid
- **Setting Imagick resolution after loading PDF:** Resolution must be set BEFORE loading the PDF file or it has no effect
- **Not specifying strict mode in json_schema:** Without `strict: true`, GPT-4o may return schema-violating responses
- **Storing unvalidated OpenAI responses:** Always validate structure and business rules before persisting to database
- **Using low image resolution (<100 DPI):** Text becomes unreadable, reducing extraction accuracy
- **Not handling multi-page PDFs:** Invoices often span multiple pages, especially with many line items

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PDF rasterization | Custom Ghostscript shell commands | spatie/pdf-to-image | Handles path escaping, temp files, error handling, resolution/quality settings |
| OpenAI API client | Raw cURL/Guzzle HTTP calls | openai-php/laravel | Manages authentication, request formatting, error handling, retry logic |
| JSON schema validation | Manual array structure checks | Structured Outputs strict mode + Laravel validator | GPT-4o guarantees schema compliance (100% reliability), Laravel validates business rules |
| Image base64 encoding | Manual chunking/encoding | PHP base64_encode() | Built-in function handles memory efficiently |
| Invoice totals math | Manual calculation across jobs | Database transaction validator | Single source of truth, atomic failure if totals don't match |

**Key insight:** OpenAI's Structured Outputs (introduced 2024) eliminates the need for custom JSON parsing/fixing logic that was required with earlier GPT-4 Vision implementations. Combined with strict schemas, it guarantees valid responses.

## Common Pitfalls

### Pitfall 1: Imagick Memory Exhaustion on Large PDFs
**What goes wrong:** Large or high-resolution PDFs cause "Memory allocation failed" errors
**Why it happens:** Imagick operates outside PHP memory_limit, uses separate resource limits defined in ImageMagick policy.xml
**How to avoid:**
- Set conservative resolution (150 DPI for text documents, not 300+)
- Configure Imagick resource limits before processing:
  ```php
  $imagick = new \Imagick();
  $imagick->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 2048 * 1024 * 1024); // 2GB
  $imagick->setResourceLimit(\Imagick::RESOURCETYPE_MAP, 2048 * 1024 * 1024);
  ```
- Use job timeout (300s) to prevent infinite hangs
**Warning signs:** "cache resources exhausted" or "Memory allocation failed" in logs

### Pitfall 2: Base64 Encoding Hitting OpenAI Token Limits
**What goes wrong:** Very large images (>20MB) fail with "maximum context length exceeded"
**Why it happens:** Base64 increases file size by ~33%, high-res images consume massive token counts
**How to avoid:**
- Use `detail: 'auto'` for most cases, `detail: 'high'` only when needed
- Compress JPEG to 85% quality (vs 100%)
- Keep resolution at 150 DPI (sufficient for text OCR)
- Consider splitting very long invoices across multiple API calls
**Warning signs:** 400 error with "context_length_exceeded" in OpenAI response

### Pitfall 3: Hallucinated Data in Extraction
**What goes wrong:** GPT-4o invents plausible-looking but incorrect invoice numbers, dates, or amounts
**Why it happens:** Model fills in missing data based on patterns, especially when image quality is poor
**How to avoid:**
- Use high image quality (detail: 'high') for critical documents
- Implement confidence score checking (prompt model to indicate uncertainty)
- Validate against business rules (e.g., dates must be past or near-future, amounts > 0)
- Consider human review flag for low-confidence extractions
**Warning signs:** Extracted dates far in future, suspiciously round numbers, generic vendor names

### Pitfall 4: Multi-Page PDFs Processed as Single Page
**What goes wrong:** Only first page extracted, missing line items on subsequent pages
**Why it happens:** Default `save()` method only converts first page
**How to avoid:**
- Always use `pageCount()` to detect multi-page PDFs
- Use `saveAllPages()` or loop with `selectPage()` for each page
- Send ALL page images to OpenAI in single request (maintains context)
**Warning signs:** Extracted line items count lower than expected, totals don't match line items

### Pitfall 5: Schema Validation Failing Silently
**What goes wrong:** Invalid data stored in database despite validation rules
**Why it happens:** Missing exception handling in SaveParsedData job, transaction not rolled back
**How to avoid:**
- Wrap all validation + save in DB::transaction()
- Let validation exceptions bubble up to job's failed() method
- Update invoice status to 'failed' with error_message in failed() callback
**Warning signs:** Invoices stuck in 'processing' status, database contains null required fields

### Pitfall 6: OpenAI API Rate Limits in Bulk Processing
**What goes wrong:** Jobs fail with 429 "Rate limit exceeded" errors
**Why it happens:** Tier 1 accounts limited to 500 requests/minute
**How to avoid:**
- Implement job throttling middleware:
  ```php
  use Illuminate\Queue\Middleware\ThrottlesExceptions;

  public function middleware(): array
  {
      return [
          (new ThrottlesExceptions(10, 5 * 60))->backoff(30)
      ];
  }
  ```
- Use queue delays: `ParseInvoiceWithAI::dispatch($invoice)->delay(now()->addSeconds(5))`
- Monitor OpenAI usage dashboard
**Warning signs:** Repeated 429 errors in logs, jobs retrying rapidly

## Code Examples

Verified patterns from official sources:

### Setting PDF Resolution and Quality (spatie/pdf-to-image)
```php
// Source: https://github.com/spatie/pdf-to-image
use Spatie\PdfToImage\Pdf;

$pdf = new Pdf($pathToPdf);

// CRITICAL: Set resolution BEFORE loading/processing
$pdf->setResolution(150);  // 150 DPI - good balance for text
$pdf->quality(85);          // 85% JPEG quality
$pdf->setOutputFormat('jpg');

// Get total pages
$pageCount = $pdf->pageCount();

// Convert single page
$pdf->selectPage(1)->save($outputPath);

// Convert all pages to directory
$pdf->saveAllPages($directory);
```

### OpenAI Vision API with Structured Output (openai-php)
```php
// Source: https://github.com/openai-php/client
use OpenAI\Laravel\Facades\OpenAI;

$response = OpenAI::chat()->create([
    'model' => 'gpt-4o-2024-08-06',  // Required for Structured Outputs
    'messages' => [
        [
            'role' => 'system',
            'content' => 'Extract invoice data. Return null for missing fields.'
        ],
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Extract all invoice fields from this document.'
                ],
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:image/jpeg;base64,{$base64Image}",
                        'detail' => 'high'  // high detail for accurate OCR
                    ]
                ]
            ]
        ]
    ],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'invoice_data',
            'strict' => true,  // Enforces 100% schema compliance
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'vendor' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => ['string', 'null']],
                            'address' => ['type' => ['string', 'null']],
                            'tax_id' => ['type' => ['string', 'null']]
                        ],
                        'required' => ['name', 'address', 'tax_id'],
                        'additionalProperties' => false
                    ]
                ],
                'required' => ['vendor', 'customer', 'metadata', 'totals', 'line_items'],
                'additionalProperties' => false
            ]
        ]
    ]
]);

$data = json_decode($response->choices[0]->message->content, true);
```

### Laravel Nested Array Validation
```php
// Source: https://laravel.com/docs/11.x/validation
use Illuminate\Support\Facades\Validator;

$rules = [
    'line_items' => 'required|array|min:1',
    'line_items.*.description' => 'required|string|max:500',
    'line_items.*.quantity' => 'required|numeric|min:0|max:999999',
    'line_items.*.unit_price' => 'required|numeric|min:0',
    'line_items.*.amount' => 'required|numeric|min:0',
    'line_items.*.tax' => 'required|numeric|min:0',
];

$validator = Validator::make($extractedData, $rules);

if ($validator->fails()) {
    throw new \InvalidArgumentException($validator->errors()->first());
}

$validatedData = $validator->validated();
```

### Job Error Handling with Retry and Backoff
```php
// Source: https://laravel.com/docs/11.x/queues
class ParseInvoiceWithAI implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;           // Retry up to 3 times
    public int $timeout = 300;        // 5 minute timeout
    public int $maxExceptions = 2;    // Fail after 2 exceptions

    public array $backoff = [30, 60, 120];  // Exponential backoff in seconds

    public function middleware(): array
    {
        return [
            new ThrottlesExceptions(10, 5 * 60)->backoff(30)
        ];
    }

    public function handle(): void
    {
        // ... OpenAI API call
    }

    public function failed(Throwable $exception): void
    {
        $this->invoice->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage()
        ]);
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| OCR + text extraction + GPT-4 text | Direct Vision API processing | GPT-4V launch (Sept 2023) | Simpler pipeline, better table/layout understanding |
| Manual JSON parsing with regex | Structured Outputs strict mode | Aug 2024 (gpt-4o-2024-08-06) | 100% schema compliance, no malformed JSON |
| Public URL image hosting | Base64 inline images | Always supported | No temp public storage, better security |
| Function calling for structured data | response_format: json_schema | Aug 2024 | More reliable, purpose-built for extraction |
| High DPI (300+) for all PDFs | Auto/adaptive detail | GPT-4o detail parameter | Lower costs, same accuracy for text |

**Deprecated/outdated:**
- **gpt-4-vision-preview:** Replaced by gpt-4o models (faster, cheaper, better accuracy)
- **response_format: {type: "json_object"}:** Replaced by json_schema strict mode for guaranteed structure
- **Separate OCR step:** Vision API handles OCR internally, no need for Tesseract/Cloud Vision preprocessing

## Open Questions

1. **Confidence scores per field**
   - What we know: GPT-4o supports logprobs parameter for token-level confidence, but field-level confidence requires prompt engineering
   - What's unclear: Whether to use logprobs or ask model to include confidence in schema
   - Recommendation: Add optional `confidence` field (0-100 integer) to schema for each major section, prompt model to estimate

2. **Optimal image resolution/quality balance**
   - What we know: 150 DPI recommended for text, 85% JPEG quality sufficient
   - What's unclear: Impact on token costs for different resolution/quality combinations
   - Recommendation: Start with 150 DPI + 85% quality, test with sample invoices, monitor OpenAI costs

3. **Multi-page invoice strategy**
   - What we know: Can send multiple images in single request, GPT-4o maintains context
   - What's unclear: Token limits for very long invoices (10+ pages)
   - Recommendation: Send all pages in single request for <10 pages, implement pagination logic if needed

4. **Handling scanned vs digital PDFs**
   - What we know: Vision API handles both, scanned may have lower OCR accuracy
   - What's unclear: Whether to detect scan quality and adjust detail parameter
   - Recommendation: Use `detail: 'high'` for all invoices initially, optimize if costs become issue

## Sources

### Primary (HIGH confidence)
- [spatie/pdf-to-image GitHub](https://github.com/spatie/pdf-to-image) - API methods, configuration, multi-page handling
- [openai-php/laravel GitHub](https://github.com/openai-php/laravel) - Installation, configuration, facade usage
- [openai-php/client GitHub](https://github.com/openai-php/client) - Vision API examples, chat completions
- [Laravel 11 Queue Documentation](https://laravel.com/docs/11.x/queues) - Job patterns, error handling, retry logic
- [Laravel 11 Validation Documentation](https://laravel.com/docs/11.x/validation) - Nested array validation

### Secondary (MEDIUM confidence)
- [OpenAI Structured Outputs](https://platform.openai.com/docs/guides/structured-outputs) - Verified via WebSearch, confirmed json_schema support
- [GPT-4o Vision Guide](https://getstream.io/blog/gpt-4o-vision-guide/) - Best practices for vision API
- [Microsoft Azure OpenAI PDF Extraction Sample](https://learn.microsoft.com/en-us/samples/azure-samples/azure-openai-gpt-4-vision-pdf-extraction-sample/using-azure-openai-gpt-4o-to-extract-structured-json-data-from-pdf-documents/) - Structured JSON extraction patterns
- [LogRocket Laravel Array Validation](https://blog.logrocket.com/validating-arrays-nested-values-laravel/) - Nested validation techniques

### Tertiary (LOW confidence, requires verification)
- [Invoice Parsing Prompt Engineering (Invofox)](https://www.invofox.com/en/post/document-parsing-using-gpt-4o-api-vs-claude-sonnet-3-5-api-vs-invofox-api-with-code-samples) - Totals validation patterns
- [Confidence Scores Discussion (Medium)](https://djajafer.medium.com/build-an-intelligent-document-processing-with-confidence-scores-with-gpt-4o-ff93083e4ce5) - Field confidence implementation ideas

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Official packages verified via repos and Packagist
- Architecture: HIGH - Laravel patterns verified in official docs, OpenAI patterns verified via community examples
- Pitfalls: MEDIUM-HIGH - Common issues verified across multiple sources, some edge cases based on community reports
- Code examples: HIGH - Verified against official documentation and repositories

**Research date:** 2026-02-20
**Valid until:** 2026-03-22 (30 days for stable ecosystem, Laravel 11 and OpenAI API patterns unlikely to change significantly)
