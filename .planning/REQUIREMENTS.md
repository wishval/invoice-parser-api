# Requirements: Invoice Parser API

**Defined:** 2026-02-20
**Core Value:** Users can upload a PDF invoice and get back structured, queryable data — vendor, customer, line items, totals — extracted automatically by AI

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Authentication

- [ ] **AUTH-01**: User can log in with email/password and receive a Sanctum API token
- [ ] **AUTH-02**: User can log out (revoke current API token)
- [ ] **AUTH-03**: Test user created via database seeder for development/demo

### PDF Upload

- [ ] **UPLD-01**: User can upload a PDF file via multipart form POST
- [ ] **UPLD-02**: System validates file is PDF format and rejects non-PDF files with clear error
- [ ] **UPLD-03**: System validates file size does not exceed 10MB and rejects oversized files
- [ ] **UPLD-04**: System stores original PDF for later download
- [ ] **UPLD-05**: System returns 202 Accepted with invoice ID and status "pending"

### Invoice Parsing

- [ ] **PARS-01**: System converts all PDF pages to images using Imagick/Ghostscript
- [ ] **PARS-02**: System sends page images to OpenAI GPT-4o Vision API for data extraction
- [ ] **PARS-03**: System extracts vendor info (name, address, tax ID)
- [ ] **PARS-04**: System extracts customer info (name, address, tax ID)
- [ ] **PARS-05**: System extracts invoice metadata (invoice number, date, due date, currency)
- [ ] **PARS-06**: System extracts totals (subtotal, tax amount, total)
- [ ] **PARS-07**: System extracts line items with description, quantity, unit price, amount, and tax
- [ ] **PARS-08**: System validates OpenAI response structure before storing in database
- [ ] **PARS-09**: System provides confidence scores (0-100%) for each extracted field
- [ ] **PARS-10**: System validates that line item amounts sum to invoice totals

### Queue Processing

- [ ] **QUEU-01**: Invoice parsing dispatched as async background job (not blocking HTTP request)
- [ ] **QUEU-02**: Jobs chained sequentially: PDF → Images → AI Parse → Save → Cleanup temp files
- [ ] **QUEU-03**: Jobs are idempotent — reprocessing same invoice does not create duplicate records
- [ ] **QUEU-04**: Failed jobs are recorded in failed_jobs table with error details
- [ ] **QUEU-05**: Invoice status updates through lifecycle: pending → processing → completed/failed

### Invoice CRUD

- [ ] **CRUD-01**: User can list their invoices with pagination (default 15 per page)
- [ ] **CRUD-02**: User can view a single invoice with all extracted data and line items
- [ ] **CRUD-03**: User can delete an invoice (removes record and stored PDF)
- [ ] **CRUD-04**: User can download the original uploaded PDF
- [ ] **CRUD-05**: User can filter invoices by status (pending, processing, completed, failed)
- [ ] **CRUD-06**: User can only access their own invoices (ownership enforced)

### API Infrastructure

- [ ] **INFR-01**: Parse endpoint rate limited to 10 requests per minute per user
- [ ] **INFR-02**: All responses use API Resources for consistent JSON structure
- [ ] **INFR-03**: All errors return JSON with message and appropriate HTTP status code (never HTML)
- [ ] **INFR-04**: API versioned under /api/v1/ prefix
- [ ] **INFR-05**: OpenAPI 3.x specification auto-generated via dedoc/scramble

### Testing

- [ ] **TEST-01**: Feature tests cover all API endpoints (auth, upload, CRUD)
- [ ] **TEST-02**: Unit tests cover service layer (parser, PDF converter, OpenAI service)
- [ ] **TEST-03**: OpenAI API calls mocked in all tests (no real API calls in test suite)
- [ ] **TEST-04**: Test coverage reaches 80%+ across application code

### DevOps & Documentation

- [ ] **DEVP-01**: Docker Compose runs entire stack (PHP-FPM, Nginx, Redis) with single command
- [ ] **DEVP-02**: Docker setup includes Imagick and Ghostscript for PDF processing
- [ ] **DEVP-03**: README includes project badges, architecture overview, and curl examples
- [ ] **DEVP-04**: README includes setup instructions and environment variable documentation

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Advanced Parsing

- **PARS-V2-01**: System detects duplicate invoices by matching invoice numbers
- **PARS-V2-02**: Webhook notifications sent when parsing completes or fails

### Monitoring

- **MNTR-01**: Laravel Horizon dashboard for queue monitoring
- **MNTR-02**: Audit logging for invoice operations

## Out of Scope

| Feature | Reason |
|---------|--------|
| Frontend/UI | API-only microservice, no server-side rendering |
| WebSocket notifications | Overkill for infrequent events; status polling sufficient |
| Multi-tenant/organization support | Single user owns their invoices; simplicity |
| Invoice editing after parsing | Read-only extracted data; re-parse to correct |
| OAuth/social login | Email/password with Sanctum tokens sufficient for portfolio |
| GraphQL API | REST sufficient for document processing use case |
| Custom ML model training | Infrastructure complexity outweighs portfolio value |
| Non-PDF document formats | Keep scope focused; PDF-only |
| Payment processing | Out of domain |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| AUTH-01 | Phase 2 | Pending |
| AUTH-02 | Phase 2 | Pending |
| AUTH-03 | Phase 2 | Pending |
| UPLD-01 | Phase 3 | Pending |
| UPLD-02 | Phase 3 | Pending |
| UPLD-03 | Phase 3 | Pending |
| UPLD-04 | Phase 3 | Pending |
| UPLD-05 | Phase 3 | Pending |
| PARS-01 | Phase 4 | Pending |
| PARS-02 | Phase 4 | Pending |
| PARS-03 | Phase 4 | Pending |
| PARS-04 | Phase 4 | Pending |
| PARS-05 | Phase 4 | Pending |
| PARS-06 | Phase 4 | Pending |
| PARS-07 | Phase 4 | Pending |
| PARS-08 | Phase 4 | Pending |
| PARS-09 | Phase 4 | Pending |
| PARS-10 | Phase 4 | Pending |
| QUEU-01 | Phase 3 | Pending |
| QUEU-02 | Phase 3 | Pending |
| QUEU-03 | Phase 3 | Pending |
| QUEU-04 | Phase 3 | Pending |
| QUEU-05 | Phase 3 | Pending |
| CRUD-01 | Phase 5 | Pending |
| CRUD-02 | Phase 5 | Pending |
| CRUD-03 | Phase 5 | Pending |
| CRUD-04 | Phase 5 | Pending |
| CRUD-05 | Phase 5 | Pending |
| CRUD-06 | Phase 5 | Pending |
| INFR-01 | Phase 2 | Pending |
| INFR-02 | Phase 2 | Pending |
| INFR-03 | Phase 2 | Pending |
| INFR-04 | Phase 1 | Pending |
| INFR-05 | Phase 6 | Pending |
| TEST-01 | Phase 6 | Pending |
| TEST-02 | Phase 6 | Pending |
| TEST-03 | Phase 6 | Pending |
| TEST-04 | Phase 6 | Pending |
| DEVP-01 | Phase 1 | Pending |
| DEVP-02 | Phase 1 | Pending |
| DEVP-03 | Phase 6 | Pending |
| DEVP-04 | Phase 6 | Pending |

**Coverage:**
- v1 requirements: 42 total
- Mapped to phases: 42
- Unmapped: 0

---
*Requirements defined: 2026-02-20*
*Last updated: 2026-02-20 after roadmap creation*
