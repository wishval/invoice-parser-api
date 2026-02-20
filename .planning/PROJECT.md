# Invoice Parser API

## What This Is

A Laravel 11 microservice REST API that accepts PDF invoices, sends them to OpenAI GPT-4o for AI-powered parsing, extracts structured data (vendor/customer info, line items, totals, tax details), and stores results in a database. This is a portfolio project demonstrating Laravel API development, OpenAI integration, file handling, async queue processing, and comprehensive testing.

## Core Value

Users can upload a PDF invoice and get back structured, queryable data — vendor, customer, line items, totals — extracted automatically by AI. The parsing must work reliably and return clean, structured JSON.

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] User authentication via Laravel Sanctum (register, login, token-based)
- [ ] PDF upload with validation (PDF only, max 10MB)
- [ ] Async invoice parsing via Laravel Queues (returns 202, processes in background)
- [ ] PDF-to-image conversion (all pages) for OpenAI vision input
- [ ] OpenAI GPT-4o integration for structured data extraction
- [ ] Full CRUD on parsed invoices (list with pagination, show, delete, download original)
- [ ] Invoice items stored in separate table with line-item details
- [ ] API rate limiting on parse endpoint (10 req/min per user)
- [ ] All responses via API Resources (consistent JSON format)
- [ ] Proper error handling (JSON error responses, never HTML)
- [ ] OpenAPI specification for full API documentation
- [ ] Comprehensive test suite with mocked OpenAI calls
- [ ] Docker setup (PHP 8.2 FPM + Nginx) — run with single command
- [ ] Professional README with badges, curl examples, architecture overview

### Out of Scope

- Frontend/UI — API only
- Real-time WebSocket notifications for parsing status — polling or simple status check is enough
- Multi-tenant / organization support — single user owns their invoices
- Invoice editing after parsing — read-only extracted data
- OAuth / social login — email/password with Sanctum tokens only
- MySQL/PostgreSQL setup — SQLite for simplicity (migrations are DB-agnostic)

## Context

- Portfolio project for GitHub — code quality, documentation, and structure matter as much as functionality
- Uses PHP 8.2+ features: enums, readonly properties, match expressions, type hints
- Service layer architecture: controllers delegate to services (InvoiceParserService, OpenAIService)
- PDF pages converted to images via Imagick/Spatie before sending to OpenAI vision API
- All invoice parsing happens asynchronously via Laravel Queue jobs
- SQLite chosen for zero-config setup; migrations work with any DB driver

## Constraints

- **Tech stack**: PHP 8.2+, Laravel 11, SQLite, Laravel Sanctum, OpenAI GPT-4o
- **File size**: PDF uploads limited to 10MB
- **API model**: RESTful JSON API, no server-side rendering
- **Dependencies**: Requires OpenAI API key to function (parsing)
- **Docker**: Must run with `docker-compose up -d` — no manual setup steps beyond env config

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Async parsing via queues | Parsing takes 10-30s; synchronous would block. Returns 202 immediately. | — Pending |
| PDF → images → OpenAI vision | GPT-4o vision works better with images than raw PDF base64 | — Pending |
| SQLite default | Zero-config for portfolio reviewers; migrations are DB-agnostic | — Pending |
| All pages processed | Multi-page invoices are common; first-page-only would miss data | — Pending |
| Rate limiting on parse | Protects OpenAI integration costs; good security practice | — Pending |
| OpenAPI spec included | Professional API documentation for portfolio | — Pending |

---
*Last updated: 2026-02-20 after initialization*
