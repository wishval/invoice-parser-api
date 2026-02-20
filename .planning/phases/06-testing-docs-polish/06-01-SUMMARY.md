---
phase: 06-testing-docs-polish
plan: 01
subsystem: testing
tags: [phpunit, feature-tests, sanctum, sqlite, factories]

requires:
  - phase: 02-auth-api-infrastructure
    provides: "Auth endpoints (login/logout) and Sanctum token auth"
  - phase: 03-upload-queue-processing
    provides: "Invoice upload endpoint with job chain dispatch"
  - phase: 05-invoice-crud
    provides: "Invoice CRUD endpoints (index, show, download, delete)"
provides:
  - "Feature tests covering all 8 API endpoints (20 tests, 51 assertions)"
  - "InvoiceFactory and InvoiceItemFactory model factories"
  - "SQLite in-memory test database configuration"
affects: [06-02, 06-03]

tech-stack:
  added: []
  patterns: ["SQLite in-memory for fast test execution", "Bus::fake() to prevent job dispatch in tests", "Storage::fake() for file operation tests", "actingAs() with sanctum guard for authenticated requests"]

key-files:
  created:
    - tests/Feature/AuthTest.php
    - tests/Feature/InvoiceUploadTest.php
    - tests/Feature/InvoiceCrudTest.php
    - database/factories/InvoiceFactory.php
    - database/factories/InvoiceItemFactory.php
  modified:
    - phpunit.xml

key-decisions:
  - "Verify logout by asserting token deleted from DB rather than making second HTTP request (avoids Sanctum guard cache and app key issues in test)"
  - "Use Bus::fake() in upload tests to prevent real job chain dispatch"
  - "Use Storage::fake('local') for download and delete tests"

patterns-established:
  - "Factory state methods: pending(), processing(), failed() for Invoice lifecycle"
  - "Ownership enforcement tests: create resource for userB, actingAs userA, assert 403"

requirements-completed: [TEST-01, TEST-03, TEST-04]

duration: 4min
completed: 2026-02-20
---

# Phase 06 Plan 01: Feature Tests Summary

**20 PHPUnit feature tests covering all 8 API endpoints with auth, validation, ownership enforcement, and mocked job dispatch**

## Performance

- **Duration:** 4 min
- **Started:** 2026-02-20T10:27:17Z
- **Completed:** 2026-02-20T10:31:00Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- Created InvoiceFactory and InvoiceItemFactory with realistic defaults and lifecycle state methods
- Configured phpunit.xml for SQLite in-memory testing (fast, no Docker DB dependency)
- 20 feature tests covering health, auth (login/logout), upload (valid/invalid/oversize), and CRUD (index/show/download/delete) with ownership enforcement
- Zero real OpenAI calls -- upload tests use Bus::fake(), CRUD tests use completed invoices from factory

## Task Commits

Each task was committed atomically:

1. **Task 1+2: Model factories, test config, and all feature tests** - `ad59866` (test)

## Files Created/Modified
- `database/factories/InvoiceFactory.php` - Invoice model factory with pending/processing/failed states
- `database/factories/InvoiceItemFactory.php` - InvoiceItem model factory with calculated amounts
- `phpunit.xml` - Uncommented SQLite in-memory DB config for tests
- `tests/Feature/AuthTest.php` - Health, login (valid/invalid/missing fields), logout, unauthenticated tests
- `tests/Feature/InvoiceUploadTest.php` - PDF upload, non-PDF rejection, oversize rejection, auth required tests
- `tests/Feature/InvoiceCrudTest.php` - Index pagination/filter/ownership, show with items, download, delete, 403 enforcement tests

## Decisions Made
- Verified logout by asserting token deletion from personal_access_tokens table rather than making a second HTTP request (avoids Sanctum guard cache and MissingAppKeyException after refreshApplication)
- Used Bus::fake() in upload tests to prevent real job chain dispatch without needing OpenAI mocking
- Used Storage::fake('local') in download and delete tests to avoid filesystem side effects

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed logout test approach**
- **Found during:** Task 2 (AuthTest)
- **Issue:** Original plan suggested verifying logout by making a second request with the revoked token. Sanctum caches the authenticated guard within a test lifecycle, and refreshApplication() causes MissingAppKeyException in SQLite in-memory test environment.
- **Fix:** Changed to assertDatabaseCount('personal_access_tokens', 0) to verify token was actually deleted.
- **Files modified:** tests/Feature/AuthTest.php
- **Verification:** Test passes, confirms token deletion behavior.
- **Committed in:** ad59866

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Necessary fix for test reliability. No scope creep.

## Issues Encountered
- Pre-existing ExampleTest fails with MissingAppKeyException (out of scope, not related to this plan's changes)

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Feature test suite established, ready for additional test coverage (unit tests, integration tests)
- Model factories available for use in future test plans
- SQLite in-memory testing configured for fast test execution

---
*Phase: 06-testing-docs-polish*
*Completed: 2026-02-20*
