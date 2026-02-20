# Phase 3: Invoice Upload & Queue Processing - Research

**Researched:** 2026-02-20
**Domain:** Laravel file upload, queue processing, and async job orchestration
**Confidence:** HIGH

## Summary

Phase 3 implements asynchronous PDF invoice upload with a 202 Accepted response pattern and Redis-backed job queue processing. The architecture follows Laravel's recommended approach: immediate HTTP response with job ID, background processing via chained jobs, and status lifecycle management through database updates.

**Core technical stack**: Laravel 11 file upload validation with Form Requests, Storage facade for private file persistence, Redis queue with job chaining, and API Resources for consistent JSON responses. The pattern separates HTTP layer (fast response) from processing layer (queued work) using Laravel's ShouldQueue interface and Bus::chain() for sequential job execution.

**Primary recommendation:** Use Form Request for multipart validation (PDF mime type, 10MB limit), store to private disk (not public), dispatch job chain immediately, return 202 with invoice ID/status. Make jobs idempotent with ShouldBeUnique interface and use WithoutRelations attribute when serializing models to prevent bloated queue payloads.

## Phase Requirements

<phase_requirements>
| ID | Description | Research Support |
|----|-------------|-----------------|
| UPLD-01 | User can upload PDF via multipart form POST | Laravel Request->file() handles multipart automatically; Form Request validation recommended |
| UPLD-02 | Validate PDF format, reject non-PDF with clear error | Use `mimes:pdf` rule (checks MIME type, not just extension); custom error messages via Form Request->messages() |
| UPLD-03 | Validate file size ≤ 10MB, reject oversized files | Use `max:10240` rule (kilobytes); validation automatic before controller method execution |
| UPLD-04 | Store original PDF for later download | Use Storage::putFile() to private disk; stores at `storage/app/private/invoices/{hash}` |
| UPLD-05 | Return 202 Accepted with invoice ID and status "pending" | Use response()->json(['id' => $id, 'status' => 'pending'], 202); wrap in API Resource for consistency |
| QUEU-01 | Parsing dispatched as async background job | Implement ShouldQueue interface; dispatch job after invoice record created; HTTP returns immediately |
| QUEU-02 | Jobs chained sequentially | Use Bus::chain([Job1::class, Job2::class])->dispatch(); jobs run in order, stop on failure |
| QUEU-03 | Jobs are idempotent | Implement ShouldBeUnique or ShouldBeUniqueUntilProcessing; uniqueId() returns invoice ID |
| QUEU-04 | Failed jobs recorded in failed_jobs table | Configured via config/queue.php 'failed' driver; migrations already exist; define failed() method in jobs |
| QUEU-05 | Status updates: pending → processing → completed/failed | Update Invoice->status in job handle() method; on success: 'completed', on exception: 'failed' with error_message |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | 11.x | HTTP handling, validation, queues | Official framework with proven file upload and queue system |
| Laravel Sanctum | 4.x | API authentication | Already implemented in Phase 2 for token-based auth |
| predis/predis | ~2.0 | Redis PHP client | Laravel-recommended Redis driver for queues |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel Eloquent Resources | Built-in | JSON API transformation | Always use for consistent API responses (INFR-02) |
| Laravel Form Requests | Built-in | File upload validation | Always use for file upload validation logic separation |
| Redis | 7.x | Queue backend | Already configured in Docker stack (Phase 1) |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Redis queue | Database queue | Database queue simpler but Redis faster and doesn't bloat DB with job records |
| Form Request | Inline validation | Form Request cleaner but inline validation acceptable for simple cases |
| API Resources | Manual array building | Resources provide consistency and conditional fields; manual faster but error-prone |

**Installation:**
```bash
# All dependencies already present in Laravel 11 and Phase 1 Docker setup
# No additional packages required for Phase 3
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Http/
│   ├── Controllers/
│   │   └── InvoiceController.php      # upload() method dispatches job, returns 202
│   ├── Requests/
│   │   └── StoreInvoiceRequest.php    # Validates PDF upload
│   └── Resources/
│       └── InvoiceResource.php        # Consistent JSON response structure
├── Jobs/
│   ├── ProcessInvoice.php             # Orchestrator job (status → processing)
│   ├── ConvertPdfToImages.php         # PDF → images (Phase 4)
│   ├── ParseInvoiceWithAI.php         # AI extraction (Phase 4)
│   └── CleanupTempFiles.php           # Remove temp files after processing
└── Models/
    └── Invoice.php                    # Status lifecycle: pending/processing/completed/failed
```

### Pattern 1: 202 Accepted Async Response
**What:** Controller creates Invoice record, dispatches job, returns 202 immediately
**When to use:** Any long-running operation that shouldn't block HTTP response
**Example:**
```php
// Source: https://laravel.com/docs/11.x/responses
// Pattern: https://laravel-json-api.readthedocs.io/en/latest/features/async/

public function upload(StoreInvoiceRequest $request): JsonResponse
{
    $path = Storage::disk('local')->putFile('invoices', $request->file('pdf'));

    $invoice = Invoice::create([
        'user_id' => $request->user()->id,
        'original_filename' => $request->file('pdf')->getClientOriginalName(),
        'stored_path' => $path,
        'status' => 'pending',
    ]);

    ProcessInvoice::dispatch($invoice);

    return (new InvoiceResource($invoice))
        ->response()
        ->setStatusCode(202);
}
```

### Pattern 2: Job Chaining for Sequential Processing
**What:** Chain jobs that must execute in order; stop chain if any job fails
**When to use:** Multi-step processing where later steps depend on earlier ones
**Example:**
```php
// Source: https://laravel.com/docs/11.x/queues

use Illuminate\Support\Facades\Bus;

Bus::chain([
    new ProcessInvoice($invoice),
    new ConvertPdfToImages($invoice),
    new ParseInvoiceWithAI($invoice),
    new CleanupTempFiles($invoice),
])->catch(function (Throwable $e) use ($invoice) {
    $invoice->update([
        'status' => 'failed',
        'error_message' => $e->getMessage(),
    ]);
})->dispatch();
```

### Pattern 3: Idempotent Jobs with ShouldBeUnique
**What:** Prevent duplicate job execution for same invoice (reprocessing safety)
**When to use:** Jobs that could be dispatched multiple times but should only run once
**Example:**
```php
// Source: https://laravel.com/docs/11.x/queues

use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

class ProcessInvoice implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    public int $uniqueFor = 3600; // Lock for 1 hour

    public function __construct(
        #[WithoutRelations]
        public Invoice $invoice,
    ) {}

    public function uniqueId(): string
    {
        return "invoice-{$this->invoice->id}";
    }

    public function handle(): void
    {
        $this->invoice->update(['status' => 'processing']);
        // Processing logic...
    }
}
```

### Pattern 4: Status Lifecycle Management
**What:** Update Invoice status as job progresses through lifecycle
**When to use:** Always track processing state for user visibility
**Example:**
```php
// Job handle method
public function handle(): void
{
    $this->invoice->update(['status' => 'processing']);

    try {
        // Process invoice...
        $this->invoice->update(['status' => 'completed']);
    } catch (Exception $e) {
        $this->invoice->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);
        $this->fail($e);
    }
}
```

### Pattern 5: Form Request for File Validation
**What:** Dedicated class for multipart file upload validation
**When to use:** Always separate validation logic from controller
**Example:**
```php
// Source: https://laravel.com/docs/11.x/validation

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by Sanctum middleware
    }

    public function rules(): array
    {
        return [
            'pdf' => 'required|file|mimes:pdf|max:10240', // 10MB
        ];
    }

    public function messages(): array
    {
        return [
            'pdf.required' => 'Please provide a PDF file to upload',
            'pdf.mimes' => 'File must be a PDF document',
            'pdf.max' => 'PDF size must not exceed 10MB',
        ];
    }
}
```

### Anti-Patterns to Avoid
- **Storing PDFs to public disk:** Security risk; use private disk, serve via authenticated routes
- **Blocking HTTP response while processing:** Defeats async purpose; dispatch job, return 202 immediately
- **Serializing models with relations in jobs:** Bloats queue payload; use #[WithoutRelations] attribute
- **Missing failed() method in jobs:** Errors disappear silently; always define failed() for logging/cleanup
- **Setting tries = 0:** Creates infinite retry loop; use tries > 0 or retryUntil()

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| File MIME type detection | Custom binary header reading | Laravel `mimes` validation rule | Reads file contents, guesses MIME type, handles edge cases |
| Job retry/failure logic | Custom retry counters, manual error tables | Laravel queue `tries`, `failed_jobs` table | Automatic retry with exponential backoff, centralized failure tracking |
| API response formatting | Manual JSON array building per endpoint | Laravel API Resources | Consistent structure, conditional fields, metadata support |
| File storage paths | Manual path generation, hash functions | Storage::putFile() with hashName() | Automatic unique filenames, collision prevention |
| Queue job serialization | Custom serialization logic | SerializesModels trait + WithoutRelations | Handles model serialization, prevents relation bloat |

**Key insight:** Laravel's queue system handles retry logic, timeout management, and failure tracking automatically. Custom retry logic introduces bugs around edge cases (database transactions, race conditions, memory leaks). Use framework primitives.

## Common Pitfalls

### Pitfall 1: Public Storage Exposure
**What goes wrong:** Uploaded PDFs stored to `storage/app/public` become web-accessible, exposing sensitive invoice data
**Why it happens:** Default `public` disk seems intuitive for file storage; developers confuse "users need to download" with "files should be public"
**How to avoid:** Use `local` disk (configured to `storage/app/private`), serve files via authenticated controller route using Storage::download()
**Warning signs:** Symlink from `public/storage` exists; file URLs don't require authentication

### Pitfall 2: Missing UUID Column in failed_jobs
**What goes wrong:** Jobs fail silently without appearing in failed_jobs table; errors invisible
**Why it happens:** Laravel 11 uses `uuid` column by default but migration might be missing or nullable constraint incorrect
**How to avoid:** Verify migration includes `$table->string('uuid')->unique();` and run `php artisan migrate` to ensure table exists
**Warning signs:** Jobs disappear after exception; `php artisan queue:failed` shows empty list despite visible errors

### Pitfall 3: Retry After < Timeout
**What goes wrong:** Long-running jobs get retried while still executing, creating duplicate processing
**Why it happens:** Queue `retry_after` (default 90s) is shorter than job timeout, so worker assumes job failed and retries
**How to avoid:** Set `retry_after` higher than longest job timeout; use `retry_after: 600` if jobs can take 5+ minutes
**Warning signs:** Jobs running multiple times simultaneously; duplicate records in database

### Pitfall 4: Serializing Loaded Relations
**What goes wrong:** Job payload bloats to megabytes when Invoice model has loaded relations (user, items), slowing queue
**Why it happens:** `SerializesModels` trait serializes entire model including eager-loaded relations
**How to avoid:** Use `#[WithoutRelations]` attribute on constructor parameter: `public function __construct(#[WithoutRelations] public Invoice $invoice)`
**Warning signs:** Redis memory usage spikes; queue:work slow to process jobs

### Pitfall 5: File Size Limits (PHP/Nginx)
**What goes wrong:** 10MB PDFs rejected before reaching Laravel validation
**Why it happens:** PHP `upload_max_filesize` (default 2MB) or Nginx `client_max_body_size` (default 1MB) lower than Laravel validation
**How to avoid:** Configure Docker php.ini: `upload_max_filesize=20M`, `post_max_size=20M`; Nginx: `client_max_body_size 20M` (buffer above validation limit)
**Warning signs:** 413 Payload Too Large errors; files fail upload without hitting Laravel validation

### Pitfall 6: Database Transaction + Queue Dispatch
**What goes wrong:** Job dispatched, runs before transaction commits, finds no Invoice record in database
**Why it happens:** Default `after_commit: false` means job dispatches immediately, transaction might not be committed yet
**How to avoid:** Set `after_commit: true` in queue config OR use `->afterCommit()` method when dispatching
**Warning signs:** Jobs fail with "Invoice not found" errors; race condition during high traffic

### Pitfall 7: MIME Type Spoofing
**What goes wrong:** Attacker renames `.exe` to `.pdf`, bypasses validation
**Why it happens:** Using `extensions` rule instead of `mimes` rule validates filename extension only, not file content
**How to avoid:** Always use `mimes:pdf` (reads file content) NOT `extensions:pdf` (checks filename only)
**Warning signs:** Non-PDF files passing validation; security audit findings

## Code Examples

Verified patterns from official sources:

### File Upload Controller
```php
// Source: https://laravel.com/docs/11.x/filesystem
// Source: https://laravel.com/docs/11.x/responses

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function upload(StoreInvoiceRequest $request): JsonResponse
    {
        // Store to private disk with auto-generated unique filename
        $path = Storage::disk('local')->putFile('invoices', $request->file('pdf'));

        // Create invoice record with pending status
        $invoice = Invoice::create([
            'user_id' => $request->user()->id,
            'original_filename' => $request->file('pdf')->getClientOriginalName(),
            'stored_path' => $path,
            'status' => 'pending',
        ]);

        // Dispatch async job (non-blocking)
        ProcessInvoice::dispatch($invoice);

        // Return 202 Accepted with invoice resource
        return (new InvoiceResource($invoice))
            ->response()
            ->setStatusCode(202);
    }
}
```

### Form Request Validation
```php
// Source: https://laravel.com/docs/11.x/validation

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by Sanctum middleware
    }

    public function rules(): array
    {
        return [
            'pdf' => [
                'required',
                'file',
                'mimes:pdf',        // Validates MIME type (security)
                'max:10240',        // 10MB in kilobytes
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'pdf.required' => 'Please provide a PDF file to upload',
            'pdf.file' => 'The uploaded file is invalid',
            'pdf.mimes' => 'Only PDF files are accepted',
            'pdf.max' => 'PDF must not exceed 10MB in size',
        ];
    }
}
```

### API Resource for Consistent Responses
```php
// Source: https://laravel.com/docs/11.x/eloquent-resources

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'original_filename' => $this->original_filename,
            'created_at' => $this->created_at->toIso8601String(),

            // Conditional fields
            'invoice_number' => $this->whenNotNull($this->invoice_number),
            'total' => $this->whenNotNull($this->total),
            'error_message' => $this->when($this->status === 'failed', $this->error_message),
        ];
    }
}
```

### Idempotent Queue Job
```php
// Source: https://laravel.com/docs/11.x/queues

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;

class ProcessInvoice implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes
    public int $uniqueFor = 3600; // 1 hour lock

    public function __construct(
        #[WithoutRelations]
        public Invoice $invoice,
    ) {}

    public function uniqueId(): string
    {
        return "process-invoice-{$this->invoice->id}";
    }

    public function handle(): void
    {
        $this->invoice->update(['status' => 'processing']);

        try {
            // Processing logic here (Phase 4)

            $this->invoice->update(['status' => 'completed']);
        } catch (\Exception $e) {
            $this->invoice->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger failed() and failed_jobs
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Log failure, send notification, etc.
        \Log::error("Invoice {$this->invoice->id} processing failed", [
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

### Job Chaining (for Phase 4 multi-step processing)
```php
// Source: https://laravel.com/docs/11.x/queues

use Illuminate\Support\Facades\Bus;
use App\Jobs\{ProcessInvoice, ConvertPdfToImages, ParseInvoiceWithAI, CleanupTempFiles};

Bus::chain([
    new ProcessInvoice($invoice),
    new ConvertPdfToImages($invoice),
    new ParseInvoiceWithAI($invoice),
    new CleanupTempFiles($invoice),
])
->onQueue('parse')
->catch(function (Throwable $e) use ($invoice) {
    $invoice->update([
        'status' => 'failed',
        'error_message' => $e->getMessage(),
    ]);
})
->dispatch();
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Database queues | Redis queues | Laravel 5.x+ | Faster job processing, doesn't bloat database with job records |
| Manual model serialization | SerializesModels + WithoutRelations | Laravel 10.x (#[WithoutRelations] attribute) | Prevents bloated queue payloads, faster job deserialization |
| Inline validation | Form Requests | Laravel 5.0+ | Cleaner controllers, reusable validation logic |
| Manual JSON responses | API Resources | Laravel 5.5+ | Consistent response structure, conditional fields, metadata support |
| extensions validation | mimes validation | Always preferred | Security: validates file content, not just filename |
| sync job processing | ShouldQueue + afterCommit | Laravel 8.x+ (after_commit) | Prevents race conditions with database transactions |

**Deprecated/outdated:**
- **Database queue as default:** Use Redis for better performance
- **extensions rule for file validation:** Use mimes/mimetypes for security
- **Dispatching jobs without afterCommit in transactions:** Can cause race conditions

## Open Questions

1. **Queue Worker Process Management in Docker**
   - What we know: Docker container needs separate worker process; Supervisor typically used in production
   - What's unclear: Best practice for development - run worker in same container or separate service?
   - Recommendation: For Phase 3, document `docker-compose exec app php artisan queue:work` for development; defer Supervisor setup to Phase 6 (DevOps)

2. **File Storage Disk Configuration**
   - What we know: `local` disk configured to `storage/app/private`; files not web-accessible
   - What's unclear: Should we create dedicated `invoices` disk or use `local` disk with subdirectories?
   - Recommendation: Use existing `local` disk with `invoices/` subdirectory; simpler, consistent with Laravel conventions

3. **Rate Limiting Interaction**
   - What we know: "parse" rate limiter registered (10/min) in Phase 2
   - What's unclear: Should rate limiting apply to upload endpoint or only to actual parsing job?
   - Recommendation: Apply rate limiting to upload endpoint (prevents spam uploads); parsing job inherits limit indirectly

## Sources

### Primary (HIGH confidence)
- [Laravel 11.x Queues Documentation](https://laravel.com/docs/11.x/queues) - Queue configuration, job chaining, idempotency, failure handling
- [Laravel 11.x File Storage Documentation](https://laravel.com/docs/11.x/filesystem) - Storage facade, disk configuration, file upload handling
- [Laravel 11.x Validation Documentation](https://laravel.com/docs/11.x/validation) - File validation rules, Form Requests, custom messages
- [Laravel 11.x HTTP Responses Documentation](https://laravel.com/docs/11.x/responses) - 202 responses, JSON responses, headers
- [Laravel 11.x Eloquent Resources Documentation](https://laravel.com/docs/11.x/eloquent-resources) - API Resource creation, response transformation
- [Laravel 11.x HTTP Requests Documentation](https://laravel.com/docs/11.x/requests) - File upload handling, Request methods

### Secondary (MEDIUM confidence)
- [Laravel Queue Design and Async Processing Guide](https://blog.greeden.me/en/2026/02/11/field-proven-complete-guide-laravel-queue-design-and-async-processing-jobs-queues-horizon-retries-idempotency-delays-priorities-failure-isolation-external-api-integrations/) - Recent comprehensive guide (Feb 2026) on queue best practices
- [Laravel File Upload & Storage Best Practices](https://www.lexo.ch/blog/2025/08/file-upload-and-storage-in-laravel-best-practices/) - Security best practices for file handling
- [Securing File Uploads: Mastering File Type Validation in Laravel](https://medium.com/@harrisrafto/securing-file-uploads-mastering-file-type-validation-in-laravel-c87de28055fd) - MIME type validation security
- [Storing Public and Private Files/Images in Laravel](https://medium.com/@laravelprotips/storing-public-and-private-files-images-in-laravel-a-comprehensive-guide-6620789fad3b) - Public vs private storage patterns
- [How to Handle Failed Jobs in Laravel Queue](https://salehmegawer.com/en/blog/laravel-queue-failed-jobs-handling-4) - Failed jobs best practices
- [Handling Large File Uploads in Laravel](https://medium.com/byte-of-knowledge/handling-large-file-uploads-in-laravel-with-chunking-and-streaming-40ed82d21436) - Memory management for large files
- [Understanding Laravel's SerializesModels](https://ryanc.co/posts/understanding-laravels-serializesmodels) - WithoutRelations attribute usage
- [Dockerizing Laravel Queues](https://sevalla.com/blog/dockerizing-laravel-queues/) - Docker worker setup patterns

### Tertiary (LOW confidence)
- None - all research verified against official Laravel documentation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All packages part of Laravel core or already configured in Phase 1
- Architecture: HIGH - Patterns verified in Laravel 11 official documentation
- Pitfalls: HIGH - Cross-verified with recent community sources and official docs
- Code examples: HIGH - All examples from Laravel 11 official documentation

**Research date:** 2026-02-20
**Valid until:** ~30 days (Laravel 11 stable; queue patterns unlikely to change)
**Framework version:** Laravel 11.x (current stable)
