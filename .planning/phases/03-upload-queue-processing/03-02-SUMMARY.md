---
phase: 03-upload-queue-processing
plan: 02
subsystem: api
tags: [laravel-queue, redis, bus-chain, jobs, idempotency]

requires:
  - phase: 03-upload-queue-processing/01
    provides: Invoice model, upload endpoint, InvoiceResource
  - phase: 01-project-foundation
    provides: Docker stack with Redis, queue config
provides:
  - Async job chain pipeline (ProcessInvoice -> ConvertPdfToImages -> ParseInvoiceWithAI -> SaveParsedData -> CleanupTempFiles)
  - Status lifecycle (pending -> processing -> completed/failed)
  - Status check endpoint GET /api/v1/invoices/{id}
  - Idempotent job dispatch via ShouldBeUniqueUntilProcessing
affects: [04-ai-parsing, 05-data-retrieval]

tech-stack:
  added: []
  patterns: [Bus::chain for sequential job pipelines, ShouldBeUniqueUntilProcessing for idempotency, WithoutRelations for serialized models, chain catch for failure handling]

key-files:
  created:
    - app/Jobs/ProcessInvoice.php
    - app/Jobs/ConvertPdfToImages.php
    - app/Jobs/ParseInvoiceWithAI.php
    - app/Jobs/SaveParsedData.php
    - app/Jobs/CleanupTempFiles.php
  modified:
    - app/Http/Controllers/Api/InvoiceController.php
    - routes/api.php

key-decisions:
  - "Bus::chain with onQueue('parse') keeps invoice processing on dedicated queue"
  - "ShouldBeUniqueUntilProcessing with 1-hour lock prevents duplicate processing"
  - "Chain catch handler sets status to failed with truncated error message"
  - "Ownership enforcement in show() via user_id check rather than policy class"

patterns-established:
  - "Job chain pattern: orchestrator sets processing, final job sets completed"
  - "WithoutRelations attribute on all job Invoice parameters for clean serialization"
  - "All jobs: tries=3, timeout=300, failed() method for observability"

requirements-completed: [QUEU-01, QUEU-02, QUEU-03, QUEU-04, QUEU-05]

duration: 3min
completed: 2026-02-20
---

# Phase 3 Plan 2: Job Pipeline Summary

**Bus::chain job pipeline with 5 sequential jobs, ShouldBeUniqueUntilProcessing idempotency, and status lifecycle endpoint**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-20T09:37:50Z
- **Completed:** 2026-02-20T09:41:01Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments
- Created 5 job classes: ProcessInvoice (orchestrator), ConvertPdfToImages, ParseInvoiceWithAI, SaveParsedData, CleanupTempFiles
- Wired Bus::chain dispatch in upload controller with failure handling
- Added GET /api/v1/invoices/{invoice} status endpoint with ownership enforcement
- Verified full lifecycle: upload -> pending -> processing -> completed

## Task Commits

Each task was committed atomically:

1. **Task 1: Create job chain classes** - `3bb16d6` (feat)
2. **Task 2: Wire chain dispatch and status endpoint** - `b62e650` (feat)

## Files Created/Modified
- `app/Jobs/ProcessInvoice.php` - Orchestrator job, sets status to processing, ShouldBeUniqueUntilProcessing
- `app/Jobs/ConvertPdfToImages.php` - Placeholder for Phase 4 PDF conversion
- `app/Jobs/ParseInvoiceWithAI.php` - Placeholder for Phase 4 AI parsing
- `app/Jobs/SaveParsedData.php` - Placeholder for Phase 4 data persistence
- `app/Jobs/CleanupTempFiles.php` - Final job, sets status to completed
- `app/Http/Controllers/Api/InvoiceController.php` - Added Bus::chain dispatch and show() method
- `routes/api.php` - Added GET /invoices/{invoice} route

## Decisions Made
- Bus::chain with onQueue('parse') keeps invoice processing on a dedicated queue separate from default
- ShouldBeUniqueUntilProcessing with 1-hour lock prevents duplicate job dispatch for same invoice
- Chain catch handler sets status to failed with Str::limit($message, 500) to avoid oversized error storage
- Ownership enforcement done inline in show() rather than creating a Policy class (simple enough for now)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Job pipeline infrastructure complete, ready for Phase 4 to fill in real implementations
- ConvertPdfToImages, ParseInvoiceWithAI, SaveParsedData are stubs awaiting OpenAI/Imagick integration
- Status endpoint ready for frontend polling

## Self-Check: PASSED

All 7 files found. Both commit hashes verified.

---
*Phase: 03-upload-queue-processing*
*Completed: 2026-02-20*
