---
phase: 04-pdf-parsing-ai
plan: 01
subsystem: api
tags: [spatie, pdf-to-image, imagick, openai, pdf, image-conversion]

requires:
  - phase: 03-upload-queue-processing
    provides: "Invoice model with stored_path, ConvertPdfToImages job stub, queue chain"
provides:
  - "PdfConverter service for PDF-to-JPEG conversion at 150 DPI"
  - "Working ConvertPdfToImages job producing manifest JSON"
  - "openai-php/laravel package installed and configured"
affects: [04-02-PLAN, 04-03-PLAN]

tech-stack:
  added: [spatie/pdf-to-image ^3.2, openai-php/laravel ^0.18, openai-php/client ^0.18]
  patterns: [manifest-json-for-job-chain, service-class-for-external-tool-wrapping]

key-files:
  created:
    - app/Services/PdfConverter.php
    - config/openai.php
  modified:
    - app/Jobs/ConvertPdfToImages.php
    - composer.json
    - composer.lock
    - .env.example

key-decisions:
  - "Page-by-page conversion loop instead of saveAllPages for named output control"
  - "Manifest JSON file in temp dir instead of database column for transient image paths"

patterns-established:
  - "Service class wrapping external tool: PdfConverter encapsulates spatie/pdf-to-image"
  - "Manifest JSON pattern: jobs write JSON manifests for downstream job consumption"

requirements-completed: [PARS-01]

duration: 2min
completed: 2026-02-20
---

# Phase 4 Plan 1: PDF Parsing Dependencies & Conversion Summary

**PDF-to-JPEG conversion via spatie/pdf-to-image at 150 DPI with manifest JSON output for job chain consumption**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-20T09:54:22Z
- **Completed:** 2026-02-20T09:56:39Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- Installed spatie/pdf-to-image ^3.2 and openai-php/laravel ^0.18 with published config
- Created PdfConverter service converting all PDF pages to 150 DPI JPEG at 85% quality
- Replaced ConvertPdfToImages job stub with real implementation producing manifest JSON
- OpenAI configuration ready for Plan 02 (ParseInvoiceWithAI)

## Task Commits

Each task was committed atomically:

1. **Task 1: Install dependencies and configure OpenAI** - `fe0fcfd` (chore)
2. **Task 2: Create PdfConverter service and implement ConvertPdfToImages job** - `5dccfaa` (feat)

## Files Created/Modified
- `app/Services/PdfConverter.php` - Wraps spatie/pdf-to-image for page-by-page JPEG conversion
- `app/Jobs/ConvertPdfToImages.php` - Reads stored PDF, converts pages, writes manifest JSON
- `config/openai.php` - OpenAI API configuration (key, org, project, timeout)
- `composer.json` - Added spatie/pdf-to-image and openai-php/laravel dependencies
- `composer.lock` - Lock file updated with 5 new packages
- `.env.example` - Added OPENAI_API_KEY and OPENAI_ORGANIZATION vars

## Decisions Made
- Used page-by-page conversion loop with named output paths (invoice_{id}_page_{N}.jpg) instead of saveAllPages() for explicit control over file naming
- Manifest JSON file in storage/app/temp/ instead of a database column, since image paths are transient and cleaned up after processing

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required. OpenAI API key will be needed when Plan 02 (ParseInvoiceWithAI) executes, but the config is ready.

## Next Phase Readiness
- PdfConverter service ready, ConvertPdfToImages job produces manifest JSON
- OpenAI package installed and config published, ready for Plan 02
- Manifest JSON pattern established for ParseInvoiceWithAI to consume image paths

## Self-Check: PASSED

All files verified present. All commit hashes verified in git log.

---
*Phase: 04-pdf-parsing-ai*
*Completed: 2026-02-20*
