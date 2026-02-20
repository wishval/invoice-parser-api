# Project Research Summary

**Project:** Laravel 11 Invoice Parser API
**Domain:** REST API Microservice with AI Integration and Async Processing
**Researched:** 2026-02-20
**Confidence:** HIGH

## Executive Summary

This is a Laravel 11 REST API microservice that accepts PDF invoices, converts them to images, sends them to OpenAI GPT-4o Vision for parsing, and returns structured JSON data. Industry experts build this type of system using queue-based async processing (critical for 10-30s OpenAI response times), multi-stage Docker deployments, and comprehensive error handling for external API failures.

The recommended approach is service layer architecture with repository pattern for data access, Laravel Horizon for queue monitoring, and strict validation of OpenAI responses (even with structured outputs). Start with core infrastructure (Docker, queue config, auth) before any AI integration—this prevents the most common pitfall of synchronous processing that causes timeout errors. Use Laravel Sanctum for token auth, Redis for queues (never database driver in production), and implement exponential backoff for OpenAI rate limits from day one.

Key risks include memory exhaustion from PDF processing (requires page-by-page conversion), duplicate invoice creation from non-idempotent jobs, and silent failures when queue workers crash. Mitigate with memory limits in Docker, idempotency checks using `withoutOverlapping()` middleware, and comprehensive monitoring of the `failed_jobs` table. This is a portfolio project, so prioritize code quality, comprehensive testing with mocked OpenAI calls, and professional documentation over feature breadth.

## Key Findings

### Recommended Stack

Laravel 11 on PHP 8.3 provides the modern framework foundation with excellent queue, API, and microservice support. The stack prioritizes production-ready patterns over cutting-edge tech: Nginx + PHP-FPM for HTTP handling, Redis for queues (required for Laravel Horizon monitoring), SQLite for local development (upgrade to PostgreSQL for production/cloud deployments), and standardized packages for external integrations.

**Core technologies:**
- **Laravel 11.x + PHP 8.3**: Framework core with longer security support lifecycle (8.3 vs 8.2) and modern routing/structure
- **openai-php/laravel**: Community-maintained OpenAI SDK (no official PHP SDK exists) with Laravel service provider for clean DI integration
- **spatie/pdf-to-image**: Industry standard for Laravel PDF processing using Imagick + Ghostscript (requires system-level dependencies)
- **Laravel Sanctum**: Built-in token-based API auth, simpler than Passport, recommended for API-only microservices
- **Laravel Horizon**: Official Redis queue monitoring with real-time metrics, worker configuration, and elegant dashboard
- **dedoc/scramble**: Modern OpenAPI 3.x generator that auto-generates docs from code (no PHPDoc annotations required)
- **Redis 7.x**: Required for Horizon, industry standard for Laravel queue backends and caching
- **Docker Compose**: Multi-stage builds for optimized production images, separate dev/prod configurations

**Critical version notes:**
- PHP 8.2+ required for Laravel 11 and spatie/pdf-to-image 3.2.0
- SQLite NOT suitable for Laravel Cloud/Vapor (ephemeral filesystems) — use PostgreSQL or Turso for production cloud deployments
- Database queue driver NOT acceptable for production — Redis required for monitoring and scale

**Known configuration issues to address early:**
- ImageMagick PDF security policy blocks PDF operations by default
- PHP-FPM needs `PATH` environment variable for Ghostscript (`gs` command)
- Resource limits in ImageMagick policy.xml for large/wide PDFs

### Expected Features

Invoice parsing APIs compete on accuracy (95-99.5%), speed, and compliance. As a portfolio project, this competes on **code quality, architecture, testing, and documentation** to demonstrate engineering skills rather than production scale.

**Must have (table stakes):**
- **PDF upload with validation** — Max 10MB file size, format validation, error handling
- **Multi-page PDF support** — Real invoices span multiple pages; first-page-only fails real use cases
- **Header + line item extraction** — Vendor info, invoice number, dates, totals PLUS multi-line items (description, quantity, unit price, tax)
- **Async queue processing** — 10-30s parsing times make synchronous responses unviable; return 202 Accepted
- **Authentication** — Token-based (Sanctum), enables per-user rate limiting
- **Rate limiting** — 10 req/min to protect OpenAI API costs and infrastructure
- **JSON API with proper error handling** — Structured responses, proper HTTP codes, never return HTML errors
- **CRUD operations** — List, show, delete parsed invoices

**Should have (competitive advantage in portfolio context):**
- **OpenAPI 3.x specification** — Professional API documentation standard (high portfolio value)
- **Comprehensive test suite** — Unit + feature tests with mocked OpenAI calls, 80%+ coverage (demonstrates testing discipline)
- **Docker deployment** — `docker-compose up` runs entire stack (shows DevOps skills)
- **Confidence scores per field** — 0-100% confidence enables auto-approve vs human review (shows ML understanding)
- **Webhook notifications** — Real-time callbacks when parsing completes (event-driven architecture pattern)
- **Data validation rules** — Verify totals match line items, dates valid, required fields present (business logic implementation)
- **Duplicate detection** — Flag invoices with matching invoice numbers (database queries, hashing algorithms)

**Defer (v2+ or never):**
- **Multi-tenant organizations** — Scope creep, not core to parsing demonstration
- **Custom ML model training** — Infrastructure complexity outweighs portfolio value
- **Fraud detection** — High complexity, requires PDF metadata analysis
- **GraphQL API** — REST sufficient for document processing use case
- **Real-time WebSocket notifications** — Overkill for infrequent events, webhooks/polling sufficient

**Anti-features to avoid:**
- Invoice editing UI (scope creep into frontend)
- Support for every document format (keep PDF-only)
- Built-in payment processing (out of scope)

### Architecture Approach

Standard Laravel microservice architecture uses thin controllers that delegate to a service layer for business logic, with repository pattern for data access abstraction. Queue-based async processing is mandatory for external API calls—synchronous OpenAI requests cause 30+ second timeouts and poor UX. API Resources transform models to consistent JSON responses with conditional fields.

**Major components:**
1. **HTTP Layer (Nginx)** — Reverse proxy to PHP-FPM, static asset serving, SSL termination
2. **Application Layer (Laravel)** — Controllers validate + authorize + delegate, Services orchestrate business logic (PDF → AI → DB), Repositories abstract data access
3. **Queue Layer** — Background processing with job chaining (ConvertPdfToImage → ParseWithAI → Cleanup), Laravel Horizon for monitoring
4. **Data Layer (SQLite/PostgreSQL)** — Invoice + InvoiceItem models with relationships, migrations for schema
5. **External APIs** — OpenAI Vision for parsing, Imagick/Ghostscript for PDF conversion

**Key patterns:**
- **Service Layer Pattern**: Business logic extracted from controllers into dedicated service classes (`InvoiceParserService`, `OpenAIService`, `PdfConverterService`)
- **Repository Pattern**: Data access abstraction layer between services and Eloquent models, enables caching and testing with mocks
- **Queue Job Chaining**: Sequential async jobs where each step depends on previous (`Bus::chain([ConvertJob, ParseJob, CleanupJob])`)
- **API Resource Transformation**: Dedicated classes for consistent JSON responses with conditional attributes
- **Middleware-Based Rate Limiting**: Protect both API endpoints (user abuse) AND OpenAI integration (cost/quota protection)

**Build order implications from dependencies:**
1. Foundation (database, models, Docker)
2. Authentication & basic API
3. Core domain logic (services, repositories, resources)
4. Synchronous invoice upload
5. Async queue infrastructure
6. PDF processing integration
7. OpenAI integration with rate limiting
8. End-to-end orchestration
9. Production readiness (testing, docs, monitoring)

**Anti-patterns to avoid:**
- Fat controllers with business logic (untestable, duplicated)
- Direct Eloquent in controllers (couples to DB structure)
- Synchronous external API calls (slow, can't retry)
- Missing job idempotency checks (duplicate records)
- No API versioning from day one (`/api/v1/invoices` required)

### Critical Pitfalls

Based on production experience reports and technical documentation review, these are the highest-impact pitfalls specific to this domain:

1. **Memory exhaustion from PDF-to-image conversion** — Processing large multi-page PDFs (50+ pages) causes OOM errors. **Prevention:** Convert page-by-page using range syntax `mydoc.pdf[10-19]`, allocate 2-4GB RAM per worker, implement file size limits (20MB/50 pages), use adaptive DPI (72-96 for web, 150 preview, 300+ OCR only).

2. **Synchronous queue processing (QUEUE_CONNECTION=sync)** — Jobs execute inline during HTTP requests, causing 30+ second timeouts. This is Laravel's default. **Prevention:** Set `QUEUE_CONNECTION=database` or `redis` from day one, validate in `AppServiceProvider` to throw exception if sync in production, run `queue:work` as supervised process.

3. **Missing rate limit handling and exponential backoff** — OpenAI returns 429 errors during high volume, jobs fail permanently. **Prevention:** Implement exponential backoff with jitter in `backoff()` method, monitor rate limit headers, reduce `max_completion_tokens` to actual size, use circuit breaker pattern after consecutive 429s, limit queue workers (5-10 for external APIs).

4. **Trusting OpenAI JSON responses without validation** — Even with `strict: true` structured outputs, occasional malformed JSON or missing fields crash application. **Prevention:** Define strict JSON schemas with all required fields, wrap parsing in try-catch with Laravel validation, log raw responses, implement fallback parsing strategies, test with 100+ real invoices.

5. **Non-idempotent job processing** — Multiple queue workers process same invoice twice, creating duplicate database records. **Prevention:** Use `withoutOverlapping()` middleware with unique lock key, check if invoice already processed, use "processing" status flag, implement pessimistic locking on status updates.

6. **Exposing sensitive data in production error responses** — `APP_DEBUG=true` left in production leaks stack traces, DB queries, file paths, env variables. **Prevention:** Set `APP_DEBUG=false` in production, configure custom exception handler for generic messages, log details server-side only, never include secrets in error messages.

7. **Ignoring failed jobs table and silent failures** — Jobs fail silently, invoices don't get processed, no notifications. **Prevention:** Run `php artisan queue:failed-table` migration, monitor `failed_jobs` count, use Horizon dashboard, implement job failure notifications, add `failed()` method to jobs for cleanup.

8. **Dispatching jobs inside database transactions** — Job dispatched then transaction rolls back, job processes with stale/non-existent data. **Prevention:** Use `dispatch()->afterResponse()` or `DB::afterCommit()`, dispatch AFTER transaction blocks complete, structure code: write → commit → dispatch.

## Implications for Roadmap

Based on research, the critical path is: infrastructure setup → authentication → service layer → async processing → external integrations. This order prevents the most common pitfalls (sync processing, memory exhaustion, rate limits) and allows incremental testing of each component.

### Phase 1: Foundation & Infrastructure
**Rationale:** Database schema, Docker environment, and queue configuration must exist before any feature development. Prevents pitfall #1 (memory limits) and #2 (sync processing) by establishing production-ready infrastructure from day one.

**Delivers:**
- Database migrations for Invoice + InvoiceItem models
- Docker Compose with Nginx, PHP-FPM, Redis containers
- Memory limits configured (2-4GB per worker)
- Queue configuration (Redis driver, NOT sync)
- Environment variable management (.env, never commit secrets)

**Addresses pitfalls:**
- Memory exhaustion (Docker memory limits set early)
- Sync queue processing (Redis configured before any jobs exist)
- Exposing sensitive data (APP_DEBUG=false enforced, .env in .gitignore)

**Research flags:** Standard Laravel patterns, minimal research needed

---

### Phase 2: Authentication & API Foundation
**Rationale:** Token-based auth enables per-user rate limiting (critical cost control) and is a table stakes feature. API versioning and middleware configuration set standards before any domain logic.

**Delivers:**
- Laravel Sanctum token authentication
- User registration/login endpoints
- API versioning structure (`/api/v1/`)
- Rate limiting middleware (10 req/min per user)
- API error handling with consistent JSON format

**Addresses features:**
- Authentication (table stakes)
- Rate limiting (table stakes)
- Error handling (table stakes)

**Addresses pitfalls:**
- Missing authentication (security)
- Generic error messages without logging (configure early)

**Research flags:** Well-documented patterns, no phase research needed

---

### Phase 3: Service Layer & Repository Pattern
**Rationale:** Business logic layer must exist before any complex features (PDF processing, AI integration). Establishes clean architecture that prevents fat controllers and enables testability.

**Delivers:**
- `InvoiceParserService` for workflow orchestration
- `PdfConverterService` for image conversion (not yet implemented, interface only)
- `OpenAIService` for API calls (not yet implemented, interface only)
- `InvoiceRepository` + `InvoiceItemRepository` for data access
- API Resources for JSON transformation
- Unit tests with service layer mocking

**Addresses architecture:**
- Service layer pattern (core)
- Repository pattern (core)
- API resource transformation

**Addresses pitfalls:**
- Fat controllers (prevented by architecture)
- Direct Eloquent in controllers (prevented by repository layer)

**Research flags:** Standard Laravel patterns, minimal research needed

---

### Phase 4: Async Queue Processing
**Rationale:** Queue infrastructure must be production-ready BEFORE integrating external APIs. Allows testing of job chaining, idempotency, and failure handling with stub jobs.

**Delivers:**
- Queue job structure (`ProcessInvoiceJob`)
- Job chaining setup (`Bus::chain()`)
- Idempotency checks (`withoutOverlapping()`, status flags)
- Failed jobs monitoring and alerting
- Laravel Horizon dashboard setup
- Queue worker health checks

**Addresses features:**
- Async processing (table stakes)

**Addresses pitfalls:**
- Synchronous queue processing (verification tests)
- Non-idempotent jobs (lock mechanism implemented)
- Ignoring failed jobs table (monitoring configured)
- Jobs in transactions (afterResponse pattern enforced)

**Research flags:** NEEDS RESEARCH — Laravel Horizon configuration, job chaining patterns, idempotency strategies

---

### Phase 5: PDF Processing Integration
**Rationale:** PDF conversion is a dependency for OpenAI integration (GPT-4o Vision needs images). Isolating this step allows testing memory limits, page-by-page conversion, and temp file cleanup independently.

**Delivers:**
- Imagick + Ghostscript Docker installation
- `PdfConverterService` implementation (page-by-page conversion)
- `ConvertPdfToImageJob` with memory-safe processing
- ImageMagick policy.xml configuration (PDF permissions, resource limits)
- File upload validation (size, format, page count)
- Temp file cleanup job

**Addresses features:**
- PDF upload with validation (table stakes)
- Multi-page PDF support (table stakes)

**Addresses pitfalls:**
- Memory exhaustion from PDF conversion (page-by-page, limits enforced)

**Research flags:** NEEDS RESEARCH — ImageMagick configuration, Ghostscript integration, adaptive DPI strategies

---

### Phase 6: OpenAI Integration
**Rationale:** External API integration is highest-risk component (rate limits, response validation, costs). Implement last to ensure all infrastructure (queues, error handling, monitoring) is production-ready.

**Delivers:**
- `openai-php/laravel` package integration
- `OpenAIService` with structured output requests
- `ParseInvoiceWithAIJob` with rate limiting
- Exponential backoff retry logic
- Response validation with JSON schema
- Circuit breaker pattern for rate limit errors
- Raw response logging for debugging

**Addresses features:**
- Header + line item extraction (table stakes)
- JSON output format (table stakes)

**Addresses pitfalls:**
- Missing rate limit handling (exponential backoff implemented)
- Trusting OpenAI responses (strict validation layer)

**Research flags:** NEEDS RESEARCH — GPT-4o Vision API specifics, structured output schema design, rate limit monitoring

---

### Phase 7: End-to-End Orchestration
**Rationale:** Full workflow integration after all components tested independently. Connects upload → queue → PDF conversion → AI parsing → database storage with comprehensive error handling.

**Delivers:**
- `ProcessInvoiceJob` main orchestrator
- Job chain: Upload → Convert → Parse → Save → Cleanup
- Status tracking (pending → processing → completed/failed)
- Error recovery strategies
- Integration tests with mocked OpenAI

**Addresses features:**
- CRUD operations (table stakes)
- Complete invoice parsing workflow

**Research flags:** Standard integration patterns, minimal research needed

---

### Phase 8: Production Readiness & Portfolio Features
**Rationale:** Polish for portfolio presentation after core functionality proven. Demonstrates professional engineering practices (testing, documentation, deployment automation).

**Delivers:**
- Comprehensive test suite (unit + feature, 80%+ coverage)
- OpenAPI 3.x specification with Scramble
- Docker production optimization (multi-stage builds)
- Professional README (badges, architecture diagram, curl examples)
- Deployment documentation

**Addresses features:**
- OpenAPI specification (portfolio value)
- Comprehensive tests (portfolio value)
- Docker deployment (portfolio value)

**Research flags:** Standard Laravel testing patterns, Scramble documentation minimal

---

### Phase 9: Competitive Differentiators (v1.x)
**Rationale:** Add after core validation and initial feedback. These features demonstrate advanced skills but aren't required for MVP.

**Delivers:**
- Confidence scores per field
- Webhook notifications for job completion
- Data validation rules (totals match line items)
- Duplicate detection by invoice number
- Audit logging

**Addresses features:**
- All "should have" differentiators from feature research

**Research flags:** NEEDS RESEARCH — OpenAI confidence score extraction, webhook delivery patterns

---

### Phase Ordering Rationale

- **Infrastructure first (Phases 1-2):** Prevents most common pitfalls by establishing production-ready foundation (queues, Docker, auth) before any complex logic
- **Architecture before features (Phase 3):** Service layer pattern prevents technical debt from fat controllers, enables comprehensive testing
- **Async processing before external APIs (Phase 4):** Queue infrastructure must be rock-solid before adding external API complexity (rate limits, network failures)
- **PDF before AI (Phase 5 → 6):** PDF conversion is a dependency for OpenAI Vision; test memory management independently
- **Core before polish (Phases 1-7 → 8):** Get working end-to-end before adding tests/docs (but don't skip them)
- **MVP before differentiators (Phase 8 → 9):** Validate core concept before adding advanced features

**Dependency notes:**
- Phase 5 (PDF) depends on Phase 4 (queues) — conversion is async
- Phase 6 (OpenAI) depends on Phase 5 (PDF) — Vision API needs images
- Phase 7 (orchestration) depends on Phases 4-6 — integrates all components
- Phase 8 (tests/docs) should start incrementally in Phases 3-7, finalize after Phase 7

### Research Flags

**Phases needing `/gsd:research-phase` during planning:**
- **Phase 4 (Async Queue Processing):** Laravel Horizon setup, job chaining patterns, idempotency strategies for multi-worker environments
- **Phase 5 (PDF Processing):** ImageMagick/Ghostscript configuration, memory-safe page-by-page conversion, adaptive DPI strategies
- **Phase 6 (OpenAI Integration):** GPT-4o Vision API specifics, structured output schema design, rate limit headers and monitoring, exponential backoff tuning
- **Phase 9 (Differentiators):** Webhook delivery patterns, confidence score extraction from OpenAI responses

**Phases with standard patterns (skip research-phase):**
- **Phase 1 (Foundation):** Standard Laravel migrations, Docker Compose, well-documented
- **Phase 2 (Authentication):** Laravel Sanctum is official, heavily documented
- **Phase 3 (Service Layer):** Standard Laravel architecture patterns
- **Phase 7 (Orchestration):** Integration of researched components
- **Phase 8 (Production Readiness):** Standard Laravel testing, Scramble documentation clear

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All packages verified with official docs, active maintenance confirmed, version compatibility checked. Known configuration issues documented with solutions. |
| Features | HIGH | Industry leader comparison (Mindee, Veryfi, AWS Textract, Google Cloud), API standards research, portfolio context well-defined. Clear table stakes vs differentiators. |
| Architecture | HIGH | Official Laravel docs for patterns (service layer, queues, resources), multiple production experience reports, microservice best practices from 2025+ sources. |
| Pitfalls | HIGH | Production pitfall documentation from Laravel experts, OpenAI rate limit official docs, Docker security best practices, queue failure patterns verified across sources. |

**Overall confidence:** HIGH

Research based on official documentation (Laravel 11.x, OpenAI API, Spatie packages) and verified production experience reports from 2025-2026. All critical paths have documented solutions. Stack decisions are industry-standard, not experimental.

### Gaps to Address

**Gaps identified during research:**

1. **SQLite vs PostgreSQL decision:** Research shows SQLite unacceptable for Laravel Cloud/Vapor (ephemeral filesystems) but acceptable for local/self-hosted portfolios. **Action:** Start with SQLite for simplicity, document PostgreSQL migration path, test migration before production deployment if targeting cloud.

2. **OpenAI structured output validation edge cases:** Research confirms "strict: true" reduces but doesn't eliminate malformed responses. **Action:** During Phase 6 planning, test with 100+ real invoices to identify failure patterns, implement comprehensive validation layer.

3. **Optimal queue worker count for OpenAI rate limits:** Research shows max 5-10 workers recommended but depends on API tier. **Action:** During Phase 6 execution, start with 3 workers, monitor rate limit headers, scale based on actual limits.

4. **ImageMagick resource limit tuning:** Research identifies potential issues with large/wide PDFs but optimal limits are deployment-specific. **Action:** During Phase 5, test with edge case PDFs (100+ pages, ultra-wide), tune memory/width/height limits based on available RAM.

5. **Webhook delivery reliability patterns:** Research identifies webhooks as best practice but doesn't detail retry/failure handling. **Action:** If implementing in Phase 9, research during phase planning — likely needs separate queue with exponential backoff for delivery failures.

**No blocking gaps identified.** All critical paths have documented solutions. Gaps are tuning/optimization concerns to address during relevant phases.

## Sources

### Primary (HIGH confidence)

**Official Documentation:**
- [Laravel 11.x Documentation](https://laravel.com/docs/11.x) — Framework core, queues, Eloquent, resources
- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum) — API authentication
- [Laravel Horizon Documentation](https://laravel.com/docs/12.x/horizon) — Queue monitoring
- [OpenAI API Documentation](https://platform.openai.com/docs) — Rate limits, structured outputs, Vision API
- [Spatie PDF-to-Image](https://github.com/spatie/pdf-to-image) — Version 3.2.0 requirements, limitations
- [Docker Production Setup for Laravel](https://docs.docker.com/guides/frameworks/laravel/production-setup/) — Multi-stage builds
- [Scramble Documentation](https://scramble.dedoc.co/) — OpenAPI generation

**Verified Package Sources:**
- [openai-php/client](https://github.com/openai-php/client) — Community SDK repository, requirements
- [openai-php/laravel](https://github.com/openai-php/laravel) — Laravel service provider integration

### Secondary (MEDIUM confidence)

**Industry Best Practices:**
- [Top 5 Invoice Parsing Solutions 2026 - Klippa](https://www.klippa.com/en/blog/information/invoice-parsing/) — Competitor feature analysis
- [Best Invoice OCR API 2026 - Figment Global](https://figmentglobal.com/best-invoice-ocr-api-2026/) — Industry standards
- [Best Invoice Parser APIs 2025 - Eden AI](https://www.edenai.co/post/best-invoice-parser-apis) — Feature comparison
- [Microservices with Laravel 11 - Medium](https://medium.com/@techsolutionstuff/microservices-with-laravel-11-best-practices-for-scaling-applications-63f60d4fbf11) — Architecture patterns
- [Laravel Queues & Jobs 2025 - Medium](https://medium.com/@backendbyeli/laravel-queues-jobs-2025-smarter-async-workflows-f06f1bde728b) — Queue patterns
- [3 Essential Laravel Architecture Best Practices 2025 - Medium](https://medium.com/@s.h.siddiqui5830/3-essential-laravel-architecture-best-practices-for-2025-0fc12335590a) — Service layer, repository pattern

**Production Experience Reports:**
- [30+ Laravel Queue Mistakes to Avoid - Medium](https://medium.com/@mdzahid.pro/30-laravel-queue-mistakes-you-must-avoid-in-production-ff259d6e067a) — Pitfall catalog
- [Field-Proven Laravel Queue Design Guide - GreenD.me](https://blog.greeden.me/en/2026/02/11/field-proven-complete-guide-laravel-queue-design-and-async-processing-jobs-queues-horizon-retries-idempotency-delays-priorities-failure-isolation-external-api-integrations/) — Idempotency, retries
- [19 Laravel Security Best Practices 2025 - Benjamin Crozat](https://benjamincrozat.com/laravel-security-best-practices) — Production security
- [Deploy Laravel to Production Checklist 2025 - PHP Dev Zone](https://www.php-dev-zone.com/laravel-production-deployment-checklist-and-common-mistakes-to-avoid) — Common mistakes

**OpenAI Integration:**
- [Laravel AI Integration Tutorial 2025 - JetThoughts](https://jetthoughts.com/blog/laravel-ai-integration-tutorial-complete-guide/) — Integration patterns
- [OpenAI Rate Limits Guide](https://platform.openai.com/docs/guides/rate-limits) — Rate limit dimensions
- [How to Handle Rate Limits - OpenAI Cookbook](https://cookbook.openai.com/examples/how_to_handle_rate_limits) — Exponential backoff

### Tertiary (LOW confidence — needs validation)

**Performance Benchmarks:**
- [PHP Benchmarks 8.2 vs 8.3 vs 8.4 - Sevalla](https://sevalla.com/blog/laravel-benchmarks/) — Performance comparisons (cited for PHP version choice, not critical)
- [SQLite in Production with Laravel - Freek Van der Herten](https://freek.dev/2906-using-sqlite-in-production-with-laravel) — Production considerations (portfolio context, not cloud)

---
*Research completed: 2026-02-20*
*Ready for roadmap: yes*
