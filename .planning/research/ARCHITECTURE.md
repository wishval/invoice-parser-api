# Architecture Research

**Domain:** Laravel 11 REST API Microservice with Async Processing and AI Integration
**Researched:** 2026-02-20
**Confidence:** HIGH

## Standard Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     HTTP Layer (Nginx)                          │
│  - Static asset serving                                         │
│  - Request routing to PHP-FPM                                   │
│  - SSL termination                                              │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────┴──────────────────────────────────────────┐
│                   Application Layer (Laravel)                   │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐            │
│  │  Auth   │  │  API    │  │  Queue  │  │ Event   │            │
│  │  Layer  │  │ Routes  │  │ Dispatch│  │Listener │            │
│  └────┬────┘  └────┬────┘  └────┬────┘  └────┬────┘            │
│       │            │            │            │                  │
│  ┌────┴────────────┴────────────┴────────────┴────┐             │
│  │              Controllers                         │            │
│  │  - Request validation                           │            │
│  │  - Authorization                                │            │
│  │  - Delegate to services                         │            │
│  │  - Return API resources                         │            │
│  └────┬───────────────────────────────────────┬───┘             │
│       │                                       │                 │
│  ┌────┴────────────┐                     ┌───┴──────────────┐   │
│  │   Service Layer │                     │   Queue Jobs     │   │
│  │  - Business logic│                     │  - Async tasks   │   │
│  │  - Orchestration│                     │  - Long-running  │   │
│  │  - External APIs│                     │  - Background    │   │
│  └────┬────────────┘                     └───┬──────────────┘   │
│       │                                      │                  │
│  ┌────┴──────────────────────────────────────┴────┐             │
│  │              Repository Layer                   │            │
│  │  - Data access abstraction                     │            │
│  │  - Database queries                            │            │
│  │  - Eloquent operations                         │            │
│  └────┬───────────────────────────────────────────┘             │
│       │                                                         │
├───────┴─────────────────────────────────────────────────────────┤
│                    Resource Layer                               │
│  ┌────────────────────────────────────────────────────────┐     │
│  │  API Resources (Response Transformers)                 │     │
│  │  - Conditional attributes                              │     │
│  │  - Relationship loading                                │     │
│  │  - Consistent JSON formatting                          │     │
│  └────────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────────┘
                       │
┌──────────────────────┴──────────────────────────────────────────┐
│                   Data Layer (SQLite)                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │   invoices   │  │invoice_items │  │    users     │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘

                    ┌──────────────────┐
                    │  External APIs   │
                    │  - OpenAI Vision │
                    │  - PDF Libraries │
                    └──────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| **Nginx** | HTTP server, static files, reverse proxy to PHP-FPM | Docker container with custom nginx.conf |
| **PHP-FPM** | PHP runtime, handles application code execution | Docker container (PHP 8.2+) with extensions |
| **Routes (API)** | HTTP request routing, middleware application | `routes/api.php` with versioned endpoints |
| **Middleware** | Auth verification, rate limiting, CORS | Sanctum auth, Laravel throttle, built-in CORS |
| **Controllers** | Request handling, validation, delegation | Thin controllers - validation + service calls |
| **Service Layer** | Business logic, orchestration, external API calls | Custom service classes in `app/Services/` |
| **Repository Layer** | Data access abstraction, query logic | Repository pattern classes in `app/Repositories/` |
| **Models** | Eloquent ORM, relationships, accessors | Standard Laravel models in `app/Models/` |
| **Queue Jobs** | Async processing, long-running tasks | Jobs in `app/Jobs/` implementing ShouldQueue |
| **Queue Workers** | Job processing daemons | `queue:work` command with Supervisor |
| **API Resources** | JSON response transformation | Resource classes in `app/Http/Resources/` |
| **External APIs** | OpenAI integration, PDF processing | HTTP clients, SDK packages |
| **Database** | Data persistence | SQLite for simplicity, easily upgradeable to PostgreSQL |

## Recommended Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/                     # Versioned API controllers
│   │           ├── InvoiceController.php
│   │           └── AuthController.php
│   ├── Middleware/
│   │   └── EnsureTokenIsValid.php      # Custom auth middleware
│   ├── Requests/                       # Form request validation
│   │   ├── CreateInvoiceRequest.php
│   │   └── UpdateInvoiceRequest.php
│   └── Resources/                      # API response transformers
│       ├── InvoiceResource.php
│       └── InvoiceCollection.php
├── Services/                           # Business logic layer
│   ├── InvoiceParserService.php        # Orchestrates PDF → AI → DB
│   ├── OpenAIService.php               # OpenAI API integration
│   └── PdfConverterService.php         # PDF to image conversion
├── Repositories/                       # Data access layer
│   ├── InvoiceRepository.php
│   └── InvoiceItemRepository.php
├── Jobs/                               # Async queue jobs
│   ├── ProcessInvoiceJob.php           # Main async invoice processing
│   ├── ConvertPdfToImageJob.php        # PDF conversion step
│   └── ParseInvoiceWithAIJob.php       # AI parsing step
├── Models/                             # Eloquent models
│   ├── Invoice.php
│   ├── InvoiceItem.php
│   └── User.php
├── Events/                             # Domain events
│   └── InvoiceProcessed.php
├── Listeners/                          # Event handlers
│   └── SendInvoiceNotification.php
└── Exceptions/                         # Custom exceptions
    ├── PdfConversionException.php
    └── OpenAIException.php

config/
├── queue.php                           # Queue driver configuration
├── services.php                        # External API credentials (OpenAI)
└── sanctum.php                         # API authentication config

database/
├── migrations/
│   ├── create_invoices_table.php
│   └── create_invoice_items_table.php
└── seeders/

routes/
├── api.php                             # API endpoints (versioned)
└── web.php                             # Health checks, docs

storage/
├── app/
│   ├── invoices/                       # Uploaded PDF files
│   └── temp/                           # Converted images (cleanup job)
└── logs/                               # Application logs

tests/
├── Feature/
│   ├── InvoiceApiTest.php              # E2E API tests
│   └── InvoiceProcessingTest.php       # Queue job tests
└── Unit/
    ├── InvoiceParserServiceTest.php
    └── OpenAIServiceTest.php

docker/
├── nginx/
│   └── default.conf                    # Nginx virtual host config
├── php/
│   └── Dockerfile                      # PHP-FPM container
└── docker-compose.yml                  # Multi-container orchestration
```

### Structure Rationale

- **`Http/Controllers/Api/V1/`**: API versioning from day one prevents breaking changes
- **`Services/`**: Centralized business logic keeps controllers thin and testable
- **`Repositories/`**: Database abstraction allows switching ORMs or adding caching without touching services
- **`Jobs/`**: Async processing isolation - each step can retry independently
- **`Http/Resources/`**: Response transformation decoupled from models, conditional field exposure
- **`docker/`**: Infrastructure-as-code, reproducible environments across dev/staging/production

## Architectural Patterns

### Pattern 1: Service Layer Pattern

**What:** Business logic extracted from controllers into dedicated service classes

**When to use:** Always for Laravel microservices - keeps controllers thin and logic reusable

**Trade-offs:**
- **Pros**: Testable, reusable, clear separation of concerns
- **Cons**: Additional abstraction layer, more files to navigate

**Example:**
```php
// app/Services/InvoiceParserService.php
class InvoiceParserService
{
    public function __construct(
        private OpenAIService $openAI,
        private PdfConverterService $pdfConverter,
        private InvoiceRepository $invoiceRepo
    ) {}

    public function parseInvoice(UploadedFile $pdf, User $user): Invoice
    {
        // Orchestrate the entire workflow
        $imagePath = $this->pdfConverter->convertToImage($pdf);
        $parsedData = $this->openAI->extractInvoiceData($imagePath);

        return $this->invoiceRepo->createWithItems($parsedData, $user);
    }
}

// app/Http/Controllers/Api/V1/InvoiceController.php
class InvoiceController extends Controller
{
    public function store(CreateInvoiceRequest $request, InvoiceParserService $parser)
    {
        // Dispatch async processing
        ProcessInvoiceJob::dispatch($request->file('invoice'), $request->user());

        return response()->json(['message' => 'Invoice queued for processing'], 202);
    }
}
```

### Pattern 2: Repository Pattern

**What:** Data access abstraction layer between services and Eloquent models

**When to use:** When you need to decouple data logic, add caching, or swap data sources

**Trade-offs:**
- **Pros**: Testable (mock repositories), cacheable, swappable data sources
- **Cons**: Adds boilerplate, can be overkill for simple CRUDs

**Example:**
```php
// app/Repositories/InvoiceRepository.php
class InvoiceRepository
{
    public function createWithItems(array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($data, $user) {
            $invoice = Invoice::create([
                'user_id' => $user->id,
                'vendor_name' => $data['vendor'],
                'total_amount' => $data['total'],
                'invoice_date' => $data['date'],
            ]);

            foreach ($data['items'] as $item) {
                $invoice->items()->create($item);
            }

            return $invoice->load('items');
        });
    }

    public function findByUserWithItems(User $user, string $id): ?Invoice
    {
        return Cache::remember("invoice:{$id}", 3600, fn() =>
            Invoice::with('items')
                ->where('user_id', $user->id)
                ->find($id)
        );
    }
}
```

### Pattern 3: Queue Job Chaining

**What:** Sequential async jobs where each step depends on the previous one

**When to use:** Multi-step workflows that must execute in order (PDF → Image → AI → DB)

**Trade-offs:**
- **Pros**: Failure isolation, individual step retries, clear dependencies
- **Cons**: Complexity in error handling, more jobs to manage

**Example:**
```php
use Illuminate\Support\Facades\Bus;

// In controller or service
Bus::chain([
    new ConvertPdfToImageJob($pdfPath, $invoiceId),
    new ParseInvoiceWithAIJob($imagePath, $invoiceId),
    new CleanupTempFilesJob($imagePath),
])->catch(function (Throwable $e) use ($invoiceId) {
    Invoice::find($invoiceId)->update(['status' => 'failed']);
    Log::error("Invoice processing failed", ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);
})->dispatch();
```

### Pattern 4: API Resource Transformation

**What:** Dedicated classes for transforming models into JSON responses

**When to use:** Always for API responses - provides consistent formatting and conditional fields

**Trade-offs:**
- **Pros**: Conditional attributes, relationship control, consistent responses
- **Cons**: Additional layer, must keep in sync with models

**Example:**
```php
// app/Http/Resources/InvoiceResource.php
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_name' => $this->vendor_name,
            'total_amount' => $this->total_amount,
            'invoice_date' => $this->invoice_date,
            'status' => $this->status,

            // Conditional relationship loading (prevents N+1)
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),

            // Admin-only field
            'raw_ai_response' => $this->when(
                $request->user()->isAdmin(),
                $this->raw_ai_response
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### Pattern 5: Middleware-Based Rate Limiting

**What:** Throttle API requests and queue job processing to protect resources

**When to use:** Always for external API calls (OpenAI), public endpoints

**Trade-offs:**
- **Pros**: Prevents API quota exhaustion, protects against abuse
- **Cons**: May delay legitimate requests, requires tuning

**Example:**
```php
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
});

// app/Jobs/ParseInvoiceWithAIJob.php
use Illuminate\Queue\Middleware\RateLimited;

class ParseInvoiceWithAIJob implements ShouldQueue
{
    public function middleware(): array
    {
        return [new RateLimited('openai')]; // Custom rate limiter
    }
}

// app/Providers/AppServiceProvider.php
RateLimiter::for('openai', function (object $job) {
    return Limit::perMinute(20); // OpenAI rate limit
});
```

## Data Flow

### Request Flow (Synchronous)

```
[POST /api/v1/invoices]
    ↓
[Nginx] → [PHP-FPM]
    ↓
[Route Middleware] → [auth:sanctum, throttle:60,1]
    ↓
[InvoiceController::store()]
    - Validates request (CreateInvoiceRequest)
    - Authorizes user
    - Dispatches ProcessInvoiceJob
    ↓
[Queue System] → Pushes job to database/redis queue
    ↓
[HTTP 202 Accepted] ← Returns "Invoice queued for processing"
```

### Queue Processing Flow (Asynchronous)

```
[Queue Worker] → Polls for jobs
    ↓
[ProcessInvoiceJob::handle()]
    ↓
    ├─→ [PdfConverterService::convertToImage()]
    │       - Uses Imagick/Ghostscript
    │       - Saves to storage/app/temp/
    │       - Returns image path
    ↓
    ├─→ [OpenAIService::extractInvoiceData()]
    │       - Encodes image as base64
    │       - Calls OpenAI Vision API
    │       - Parses structured JSON response
    │       - Returns invoice data array
    ↓
    ├─→ [InvoiceRepository::createWithItems()]
    │       - Starts DB transaction
    │       - Creates Invoice model
    │       - Creates InvoiceItem models
    │       - Commits transaction
    │       - Returns Invoice with items
    ↓
    └─→ [CleanupTempFilesJob::dispatch()]
            - Deletes temporary image file
```

### State Management

```
[Invoice Status Lifecycle]
    pending → processing → completed
                  ↓
              failed (with retries)
```

### Key Data Flows

1. **Invoice Upload**: Client → API → Queue → Background processing → Database
2. **Invoice Retrieval**: Client → API → Repository (with cache) → Database → API Resource → JSON
3. **Error Handling**: Job failure → Log → Update invoice status → Retry (up to 3 times)

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0-1k users | **Single server**: SQLite, database queue, 1 queue worker. Simple deployment. |
| 1k-10k users | **Separate queue workers**: Switch to Redis queue, add 2-3 dedicated worker containers, enable caching. |
| 10k-100k users | **Horizontal scaling**: Load balancer + multiple PHP-FPM containers, PostgreSQL (clustered), Redis cluster, 5-10 queue workers with priority queues. |
| 100k+ users | **Distributed architecture**: Separate microservices (auth, parsing, storage), message queue (RabbitMQ/SQS), CDN for static assets, managed database (RDS/Aurora). |

### Scaling Priorities

1. **First bottleneck: Queue processing (OpenAI API latency)**
   - **Fix**: Add more queue workers, implement job batching, cache AI responses for duplicate invoices

2. **Second bottleneck: Database write throughput**
   - **Fix**: Upgrade from SQLite to PostgreSQL, add read replicas, implement write-through caching

## Anti-Patterns

### Anti-Pattern 1: Fat Controllers

**What people do:** Put all business logic in controller methods
**Why it's wrong:** Untestable, duplicated logic, violates single responsibility
**Do this instead:** Extract to service layer, keep controllers thin (validate → delegate → respond)

### Anti-Pattern 2: Direct Eloquent in Controllers

**What people do:** `Invoice::where('user_id', $user->id)->with('items')->get()`
**Why it's wrong:** Couples controller to database structure, hard to cache, no abstraction
**Do this instead:** Use repository pattern for data access, allows caching and testing with mocks

### Anti-Pattern 3: Synchronous External API Calls

**What people do:** Call OpenAI directly in HTTP request lifecycle
**Why it's wrong:** Slow responses (30s+ timeout), user waits, can't retry on failure
**Do this instead:** Queue job for async processing, return 202 Accepted immediately, poll for status

### Anti-Pattern 4: Missing Queue Job Idempotency

**What people do:** Process job without checking if already completed
**Why it's wrong:** Duplicate invoices on retry, wasted API calls, data inconsistency
**Do this instead:** Implement `ShouldBeUnique` interface, check invoice status before processing

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessInvoiceJob implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return $this->invoiceId;
    }

    public function handle()
    {
        $invoice = Invoice::find($this->invoiceId);

        if ($invoice->status !== 'pending') {
            return; // Already processed or processing
        }

        // Process...
    }
}
```

### Anti-Pattern 5: No API Versioning

**What people do:** `/api/invoices` without version prefix
**Why it's wrong:** Breaking changes affect all clients simultaneously
**Do this instead:** Version from day one (`/api/v1/invoices`), maintain backward compatibility

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| **OpenAI Vision API** | HTTP client via `openai-php/laravel` package | Rate limit to 20 req/min, cache responses, handle 429 errors with exponential backoff |
| **Imagick/Ghostscript** | PHP extension for PDF → image conversion | Requires system packages in Docker, configure memory limits, cleanup temp files |
| **Storage (S3)** | Laravel Flysystem abstraction | Optional: Replace local storage with S3 for production, use signed URLs for downloads |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| **Controller ↔ Service** | Direct method calls (dependency injection) | Services injected via constructor, type-hinted for auto-resolution |
| **Service ↔ Repository** | Direct method calls (dependency injection) | Repositories return models/collections, services handle orchestration |
| **Service ↔ External API** | HTTP client (async via jobs) | Always wrap in try-catch, implement retry logic, log failures |
| **Queue Job ↔ Service** | Direct method calls | Jobs resolve services from container, handle exceptions, update status |
| **API ↔ Client** | JSON over HTTP/HTTPS | Use API Resources for responses, validate with Form Requests, return 202 for async |

## Build Order Implications

Based on component dependencies, the recommended build order is:

### Phase 1: Foundation (No dependencies)
- Database schema (migrations)
- Models with relationships
- Docker environment (PHP-FPM, Nginx, SQLite)

### Phase 2: Authentication & Basic API (Depends on Phase 1)
- Sanctum authentication
- User registration/login endpoints
- Middleware configuration

### Phase 3: Core Domain Logic (Depends on Phase 1-2)
- Repository layer
- Service layer (without external APIs)
- API Resources for responses

### Phase 4: Synchronous Invoice Upload (Depends on Phase 1-3)
- File upload endpoint
- Basic validation
- Invoice creation (manual entry)

### Phase 5: Async Queue Processing (Depends on Phase 1-4)
- Queue configuration
- Base job structure
- Queue worker setup

### Phase 6: PDF Processing (Depends on Phase 5)
- PdfConverterService
- Imagick/Ghostscript integration
- ConvertPdfToImageJob

### Phase 7: AI Integration (Depends on Phase 5-6)
- OpenAIService
- ParseInvoiceWithAIJob
- Rate limiting for API calls

### Phase 8: End-to-End Orchestration (Depends on Phase 6-7)
- ProcessInvoiceJob (main orchestrator)
- Job chaining
- Error handling & status updates

### Phase 9: Production Readiness (Depends on all)
- Comprehensive testing
- OpenAPI documentation
- Monitoring & logging
- Docker production optimization

**Key Dependency Notes:**
- Queue infrastructure must exist before any async jobs
- External API integrations (OpenAI) should be last to isolate complexity
- Testing can begin after Phase 3, incrementally expanding

## Sources

### Laravel Architecture & Patterns
- [Microservices with Laravel 11: Best Practices for Scaling Applications](https://medium.com/@techsolutionstuff/microservices-with-laravel-11-best-practices-for-scaling-applications-63f60d4fbf11)
- [Laravel Microservices in 2025: Architecture and Cost Guide](https://www.abbacustechnologies.com/laravel-microservices-in-2025-architecture-and-cost-guide/)
- [3 Essential Laravel Architecture Best Practices for 2025](https://medium.com/@s.h.siddiqui5830/3-essential-laravel-architecture-best-practices-for-2025-0fc12335590a)

### Service Layer & Repository Pattern
- [Mastering Repository Pattern and Service Layer in Laravel — A Complete Guide](https://medium.com/@rejwancse10/mastering-repository-pattern-and-service-layer-in-laravel-a-complete-guide-b755354cc231)
- [Laravel Service Repository Pattern: My Experience and Key Benefits](https://medium.com/@devajayantha/laravel-service-repository-pattern-my-experience-and-key-benefits-afa985cd8b18)

### Queue & Async Processing
- [Laravel Queues & Jobs 2025: Auto-Scaling, Job Visibility & Async Workflows](https://medium.com/@backendbyeli/laravel-queues-jobs-2025-smarter-async-workflows-f06f1bde728b)
- [Laravel 11.x Queues Documentation](https://laravel.com/docs/11.x/queues) (HIGH confidence - official docs)
- [Field-Proven Complete Guide: Laravel Queue Design and Async Processing](https://blog.greeden.me/en/2026/02/11/field-proven-complete-guide-laravel-queue-design-and-async-processing-jobs-queues-horizon-retries-idempotency-delays-priorities-failure-isolation-external-api-integrations/)

### OpenAI Integration
- [Laravel AI Integration Tutorial: Complete Guide 2025](https://jetthoughts.com/blog/laravel-ai-integration-tutorial-complete-guide/)
- [Laravel AI Integration with OpenAI & OpenRouter: Complete Guide 2025](https://pola5h.github.io/blog/laravel-openai-integration-guide/)
- [openai-php/laravel](https://github.com/openai-php/laravel) (Official OpenAI PHP package for Laravel)

### PDF Processing
- [spatie/pdf-to-image](https://github.com/spatie/pdf-to-image) (Industry-standard package)
- [Using image generators | laravel-medialibrary | Spatie](https://spatie.be/docs/laravel-medialibrary/v11/converting-other-file-types/using-image-generators)

### API Resources & REST Best Practices
- [Laravel 11.x Eloquent API Resources Documentation](https://laravel.com/docs/11.x/eloquent-resources) (HIGH confidence - official docs)
- [Laravel API Development: RESTful Best Practices for 2025](https://hafiz.dev/blog/laravel-api-development-restful-best-practices-for-2025)
- [8 Laravel RESTful APIs best practices for 2025](https://benjamincrozat.com/laravel-restful-api-best-practices)

### Authentication
- [Laravel 11.x Sanctum Documentation](https://laravel.com/docs/11.x/sanctum) (HIGH confidence - official docs)

### Docker & Deployment
- [Laravel Production Setup with Docker Compose | Docker Docs](https://docs.docker.com/guides/frameworks/laravel/production-setup/) (HIGH confidence - official docs)
- [Laravel Docker Environment: Production-Ready Setup Guide 2025](https://www.zestminds.com/blog/laravel-docker-production-environment/)
- [Containerizing Laravel with Docker for Scalable Deployments](https://www.innoraft.ai/blog/containerizing-laravel-with-docker-for-scalable-deployments)

---
*Architecture research for: Laravel 11 Invoice Parser API with Async Processing and AI Integration*
*Researched: 2026-02-20*
