# Roadmap: Invoice Parser API

## Overview

This roadmap delivers a Laravel 11 microservice that accepts PDF invoices, parses them with OpenAI GPT-4o Vision, and returns structured JSON data. The build order follows infrastructure-first principles: Docker and database foundation, then authentication and API conventions, then the upload-to-queue pipeline, then AI-powered parsing, then CRUD for accessing results, and finally comprehensive testing and documentation for portfolio presentation.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Foundation & Docker** - Database schema, Docker Compose stack with PHP-FPM/Nginx/Redis, and API versioning structure
- [ ] **Phase 2: Authentication & API Infrastructure** - Sanctum auth, rate limiting, error handling, and consistent JSON responses
- [ ] **Phase 3: Invoice Upload & Queue Processing** - PDF upload endpoint with validation, async job dispatch, job chaining, and status lifecycle
- [ ] **Phase 4: PDF Parsing & AI Integration** - PDF-to-image conversion, OpenAI GPT-4o Vision integration, structured data extraction, and response validation
- [ ] **Phase 5: Invoice CRUD** - List, show, delete, download, filter, and ownership enforcement for parsed invoices
- [ ] **Phase 6: Testing, Documentation & Polish** - Comprehensive test suite, OpenAPI spec, and professional README with badges and examples

## Phase Details

### Phase 1: Foundation & Docker
**Goal**: The application runs in Docker with database migrations applied, Redis configured for queues, and API routes responding under /api/v1/
**Depends on**: Nothing (first phase)
**Requirements**: DEVP-01, DEVP-02, INFR-04
**Success Criteria** (what must be TRUE):
  1. Running `docker-compose up -d` starts the full stack (PHP-FPM, Nginx, Redis) with no manual setup beyond .env configuration
  2. Docker containers include Imagick and Ghostscript system dependencies for PDF processing
  3. API routes are accessible under the /api/v1/ prefix and return JSON responses
  4. Database migrations run successfully and create invoice/invoice_items tables
**Plans**: 2 plans

Plans:
- [ ] 01-01-PLAN.md — Docker infrastructure + Laravel 11 scaffolding
- [ ] 01-02-PLAN.md — Database migrations, models, and API v1 routing

### Phase 2: Authentication & API Infrastructure
**Goal**: Users can authenticate with Sanctum tokens and interact with a consistent JSON API that enforces rate limits and returns proper error responses
**Depends on**: Phase 1
**Requirements**: AUTH-01, AUTH-02, AUTH-03, INFR-01, INFR-02, INFR-03
**Success Criteria** (what must be TRUE):
  1. User can log in with email/password and receive a bearer token that authenticates subsequent requests
  2. User can log out, revoking the current token so it no longer works
  3. A test user exists via database seeder for development and demo purposes
  4. Parse endpoint returns 429 Too Many Requests after 10 requests per minute from the same user
  5. All error responses return structured JSON with message and HTTP status code, never HTML
**Plans**: 2 plans

Plans:
- [ ] 02-01-PLAN.md — Sanctum auth (login/logout), test user seeder, UserResource
- [ ] 02-02-PLAN.md — JSON error handling and rate limiter configuration

### Phase 3: Invoice Upload & Queue Processing
**Goal**: Users can upload a PDF invoice and receive an immediate 202 response while the system processes it asynchronously through a reliable job pipeline with status tracking
**Depends on**: Phase 2
**Requirements**: UPLD-01, UPLD-02, UPLD-03, UPLD-04, UPLD-05, QUEU-01, QUEU-02, QUEU-03, QUEU-04, QUEU-05
**Success Criteria** (what must be TRUE):
  1. User can upload a PDF via multipart form POST and receives 202 Accepted with invoice ID and status "pending"
  2. Non-PDF files and files exceeding 10MB are rejected with clear error messages
  3. The original PDF is stored and retrievable for later download
  4. Invoice parsing runs as a background job (not blocking the HTTP request)
  5. Invoice status progresses through pending, processing, completed/failed and the user can observe current status
**Plans**: 2 plans

Plans:
- [ ] 03-01-PLAN.md — PDF upload endpoint with validation, file storage, and 202 response
- [ ] 03-02-PLAN.md — Job chain infrastructure with idempotency, failure handling, and status lifecycle

### Phase 4: PDF Parsing & AI Integration
**Goal**: The system converts uploaded PDFs to images, sends them to OpenAI GPT-4o Vision, and stores validated structured data including vendor info, customer info, line items, and totals
**Depends on**: Phase 3
**Requirements**: PARS-01, PARS-02, PARS-03, PARS-04, PARS-05, PARS-06, PARS-07, PARS-08, PARS-09, PARS-10
**Success Criteria** (what must be TRUE):
  1. All pages of a multi-page PDF are converted to images and sent to OpenAI GPT-4o Vision API
  2. Extracted data includes vendor info (name, address, tax ID), customer info, invoice metadata (number, date, due date, currency), and totals (subtotal, tax, total)
  3. Line items are extracted with description, quantity, unit price, amount, and tax, and stored in a separate table
  4. Each extracted field has a confidence score (0-100%) and line item amounts are validated against invoice totals
  5. Malformed or incomplete OpenAI responses are caught by validation before database storage
**Plans**: TBD

Plans:
- [ ] 04-01: TBD
- [ ] 04-02: TBD
- [ ] 04-03: TBD

### Phase 5: Invoice CRUD
**Goal**: Users can browse, inspect, download, and delete their parsed invoices through complete REST endpoints with pagination, filtering, and ownership enforcement
**Depends on**: Phase 4
**Requirements**: CRUD-01, CRUD-02, CRUD-03, CRUD-04, CRUD-05, CRUD-06
**Success Criteria** (what must be TRUE):
  1. User can list their invoices with pagination (default 15 per page) and filter by status
  2. User can view a single invoice with all extracted data and line items included
  3. User can download the original uploaded PDF file
  4. User can delete an invoice, which removes both the database record and stored PDF
  5. A user cannot access, view, or delete invoices belonging to another user
**Plans**: TBD

Plans:
- [ ] 05-01: TBD
- [ ] 05-02: TBD

### Phase 6: Testing, Documentation & Polish
**Goal**: The project demonstrates professional engineering quality with comprehensive tests, auto-generated API documentation, and a polished README suitable for portfolio presentation
**Depends on**: Phase 5
**Requirements**: TEST-01, TEST-02, TEST-03, TEST-04, INFR-05, DEVP-03, DEVP-04
**Success Criteria** (what must be TRUE):
  1. Feature tests cover all API endpoints (auth, upload, CRUD) and unit tests cover service layer classes
  2. All tests run without real OpenAI API calls (mocked) and test coverage reaches 80%+
  3. OpenAPI 3.x specification is auto-generated and accessible at a documentation endpoint
  4. README includes project badges, architecture overview, curl examples, setup instructions, and environment variable documentation
**Plans**: TBD

Plans:
- [ ] 06-01: TBD
- [ ] 06-02: TBD
- [ ] 06-03: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation & Docker | 0/2 | Not started | - |
| 2. Authentication & API Infrastructure | 0/2 | Not started | - |
| 3. Invoice Upload & Queue Processing | 0/2 | Not started | - |
| 4. PDF Parsing & AI Integration | 0/3 | Not started | - |
| 5. Invoice CRUD | 0/2 | Not started | - |
| 6. Testing, Documentation & Polish | 0/3 | Not started | - |
