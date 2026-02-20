# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-20)

**Core value:** Users can upload a PDF invoice and get back structured, queryable data extracted automatically by AI
**Current focus:** Phase 5: Invoice CRUD

## Current Position

Phase: 5 of 6 (Invoice CRUD)
Plan: 1 of 2 in current phase -- COMPLETE
Status: Executing Phase 5
Last activity: 2026-02-20 -- Completed 05-01-PLAN.md

Progress: [########░░] 82%

## Performance Metrics

**Velocity:**
- Total plans completed: 8
- Average duration: 4min
- Total execution time: 0.57 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01 Plan 01 | 2 tasks | 14min | 7min |
| 01 Plan 02 | 2 tasks | 2min | 1min |
| 02 Plan 01 | 2 tasks | 3min | 1.5min |
| 02 Plan 02 | 1 task | 3min | 3min |
| 03 Plan 01 | 2 tasks | 5min | 2.5min |
| 04 Plan 01 | 2 tasks | 2min | 1min |
| 04 Plan 02 | 2 tasks | 3min | 1.5min |
| 04 Plan 03 | 2 tasks | 2min | 1min |
| 05 Plan 01 | 2 tasks | 3min | 1.5min |

**Recent Trend:**
- Last 5 plans: -
- Trend: -

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- PHP 8.3-FPM Debian base (not Alpine) for Imagick compatibility
- Redis for queue/cache/session from day one
- USER www-data in Dockerfile, override in docker-compose for dev
- API versioning via apiPrefix in withRouting() for /api/v1/ prefix
- Decimal(12,2) for monetary fields, decimal(10,3) for quantity
- Revoke only current token on logout (multi-device support)
- API controllers in App\Http\Controllers\Api namespace
- All user responses through UserResource (no sensitive fields)
- redirectGuestsTo returns null for API routes to prevent redirect-to-login 500 errors
- Rate limiters key by user ID when authenticated, falling back to IP
- Storage::disk('local')->putFile for private PDF storage with auto-generated filenames
- Form Request authorize() returns true when Sanctum middleware handles auth upstream
- Page-by-page PDF conversion loop for named output control over saveAllPages()
- Manifest JSON file in temp dir for transient inter-job data (not database column)
- [Phase 04]: GPT-4o-2024-08-06 model for Structured Outputs support
- [Phase 04]: Parsed data written to JSON file for independent job retryability
- [Phase 04]: Validate structure and totals before any DB write to reject malformed AI responses early
- [Phase 04]: SaveParsedData sets status to completed inside transaction (not CleanupTempFiles)
- [Phase 05]: whenNotNull for all nullable extracted fields to keep pending invoice JSON clean
- [Phase 05]: User->invoices() relationship added to User model (was missing)

### Pending Todos

None yet.

### Blockers/Concerns

None yet.

## Session Continuity

Last session: 2026-02-20
Stopped at: Completed 05-01-PLAN.md (invoice list with pagination, filtering, and line items)
Resume file: None
