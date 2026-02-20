---
phase: 02-auth-api-infrastructure
plan: 02
subsystem: api
tags: [rate-limiting, error-handling, json, middleware, laravel]

# Dependency graph
requires:
  - phase: 01-project-setup
    provides: Laravel Docker environment, bootstrap/app.php structure
provides:
  - JSON-only error responses for all API routes
  - 'parse' rate limiter (10 req/min per user)
  - 'api' rate limiter (60 req/min per user)
  - Proper 401 JSON response for unauthenticated API requests
affects: [03-invoice-parsing, 04-api-endpoints]

# Tech tracking
tech-stack:
  added: []
  patterns: [shouldRenderJsonWhen for API error rendering, RateLimiter::for definitions in AppServiceProvider]

key-files:
  created: []
  modified:
    - bootstrap/app.php
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "redirectGuestsTo returns null for API routes to prevent redirect-to-login 500 errors"
  - "Rate limiters key by user ID when authenticated, falling back to IP"

patterns-established:
  - "All API error responses via shouldRenderJsonWhen: ensures no HTML error pages for api/* routes"
  - "Rate limiter naming: 'parse' for invoice parsing, 'api' for general API"

requirements-completed: [INFR-01, INFR-03]

# Metrics
duration: 3min
completed: 2026-02-20
---

# Phase 2 Plan 02: JSON Error Handling & Rate Limiting Summary

**JSON-only API error rendering via shouldRenderJsonWhen with parse (10/min) and api (60/min) rate limiters**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-20T09:16:20Z
- **Completed:** 2026-02-20T09:19:20Z
- **Tasks:** 1
- **Files modified:** 2

## Accomplishments
- All /api/* routes now return JSON error responses (never HTML)
- Unauthenticated API requests return proper JSON 401 instead of 500
- 'parse' rate limiter registered at 10 requests/minute per user
- 'api' rate limiter registered at 60 requests/minute per user

## Task Commits

Each task was committed atomically:

1. **Task 1: JSON error handling and rate limiter configuration** - `d79de01` (feat)

## Files Created/Modified
- `bootstrap/app.php` - Added shouldRenderJsonWhen for API routes, redirectGuestsTo null for API routes
- `app/Providers/AppServiceProvider.php` - Added 'api' and 'parse' rate limiter definitions

## Decisions Made
- Used redirectGuestsTo returning null for API routes to prevent Laravel's default redirect-to-login behavior which caused 500 errors on unauthenticated API requests
- Rate limiters use user ID when authenticated with IP fallback for unauthenticated requests

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed unauthenticated API requests returning 500 instead of 401**
- **Found during:** Task 1 (verification step)
- **Issue:** Laravel's default Authenticate middleware tries to redirect to `route('login')` for unauthenticated requests, but no such route exists, causing RouteNotFoundException (500)
- **Fix:** Added `redirectGuestsTo` in withMiddleware that returns null for `api/*` requests, allowing the AuthenticationException to propagate and be rendered as JSON 401
- **Files modified:** bootstrap/app.php
- **Verification:** `curl -s -w "%{http_code}" http://localhost:8000/api/v1/user` returns `{"message":"Unauthenticated."}` with 401
- **Committed in:** d79de01 (part of task commit)

---

**Total deviations:** 1 auto-fixed (1 bug fix)
**Impact on plan:** Essential fix for correct 401 behavior on API routes. No scope creep.

## Issues Encountered
None beyond the auto-fixed deviation above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- JSON error handling active for all API routes
- Rate limiters defined and ready for route middleware application
- Parse endpoint rate limiter will be applied in Phase 3 via `throttle:parse` middleware

---
*Phase: 02-auth-api-infrastructure*
*Completed: 2026-02-20*
