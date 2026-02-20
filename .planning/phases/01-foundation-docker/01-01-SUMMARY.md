---
phase: 01-foundation-docker
plan: 01
subsystem: infra
tags: [docker, php-fpm, nginx, redis, laravel, imagick, ghostscript, sanctum]

requires:
  - phase: none
    provides: greenfield project
provides:
  - Docker Compose stack with PHP 8.3-FPM, Nginx, and Redis
  - Laravel 11 scaffolded with Sanctum API routes
  - Imagick and Ghostscript available for PDF processing
  - Redis configured as queue, cache, and session driver
affects: [02-models-api, 03-pdf-processing, 04-ai-integration]

tech-stack:
  added: [laravel/framework:11.48, laravel/sanctum:4.3, php:8.3-fpm, nginx:1.24, redis:7, imagick:3.8.1, ghostscript:10.05]
  patterns: [docker-compose multi-service, entrypoint bootstrap, volume-mount development]

key-files:
  created:
    - docker/php/Dockerfile
    - docker/php/entrypoint.sh
    - docker/nginx/default.conf
    - docker-compose.yml
    - .env.example
    - routes/api.php
  modified:
    - .gitignore
    - composer.json

key-decisions:
  - "Used php:8.3-fpm Debian base (not Alpine) for easier Imagick compilation"
  - "Set USER www-data in Dockerfile for security, override with user:root in docker-compose for dev volume mounts"
  - "Redis for queue, cache, and session from day one"

patterns-established:
  - "Docker entrypoint: idempotent bootstrap (composer install, key:generate, migrate)"
  - "Environment: .env.example as source of truth, .env created at container start"

requirements-completed: [DEVP-01, DEVP-02]

duration: 14min
completed: 2026-02-20
---

# Phase 1 Plan 1: Docker Infrastructure & Laravel Scaffold Summary

**Docker Compose stack with PHP 8.3-FPM (Imagick + Ghostscript), Nginx on port 8000, Redis 7, and Laravel 11 with Sanctum API**

## Performance

- **Duration:** 14 min
- **Started:** 2026-02-20T08:40:08Z
- **Completed:** 2026-02-20T08:53:56Z
- **Tasks:** 2
- **Files modified:** 65

## Accomplishments
- Docker Compose stack starts all three services (app, nginx, redis) with single command
- PHP 8.3-FPM container includes Imagick 3.8.1, Ghostscript 10.05, Redis extension, pdo_sqlite, gd, bcmath, pcntl, zip
- Laravel 11.48 scaffolded with Sanctum API routes installed
- .env.example configured with QUEUE_CONNECTION=redis, REDIS_HOST=redis, CACHE_STORE=redis, SESSION_DRIVER=redis
- ImageMagick policy.xml modified to allow PDF read/write operations

## Task Commits

Both tasks committed atomically:

1. **Task 1 & 2: Docker infrastructure + Laravel scaffold** - `df0897e` (feat)

**Plan metadata:** pending (docs: complete plan)

## Files Created/Modified
- `docker/php/Dockerfile` - PHP 8.3-FPM image with Imagick, Ghostscript, Redis, pdo_sqlite
- `docker/php/entrypoint.sh` - Bootstrap script: composer install, key:generate, sqlite creation, migrate
- `docker/nginx/default.conf` - Nginx vhost proxying to PHP-FPM on port 9000
- `docker-compose.yml` - Multi-service orchestration (app, nginx, redis)
- `.env.example` - Environment template with Redis queue/cache/session config
- `.gitignore` - Updated with database/database.sqlite
- `.dockerignore` - Excludes vendor, node_modules, .git from build context
- `routes/api.php` - Sanctum API routes scaffold
- `composer.json` / `composer.lock` - Laravel 11 + Sanctum dependencies

## Decisions Made
- Used php:8.3-fpm Debian base instead of Alpine for reliable Imagick PECL installation
- Set USER www-data in Dockerfile for production security; docker-compose overrides with user:root for development volume mount permissions
- Used env_file with required:false in docker-compose so stack can bootstrap without pre-existing .env
- Redis configured as queue, cache, and session driver from the start

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Added libsqlite3-dev to Dockerfile system dependencies**
- **Found during:** Task 2 (Docker build)
- **Issue:** pdo_sqlite extension failed to compile -- missing sqlite3 pkg-config
- **Fix:** Added libsqlite3-dev to apt-get install list
- **Files modified:** docker/php/Dockerfile
- **Verification:** Build succeeded, pdo_sqlite extension loads
- **Committed in:** df0897e

**2. [Rule 1 - Bug] Fixed Dockerfile COPY path for entrypoint.sh**
- **Found during:** Task 2 (Docker build)
- **Issue:** COPY entrypoint.sh failed because build context is project root, not docker/php/
- **Fix:** Changed to COPY docker/php/entrypoint.sh
- **Files modified:** docker/php/Dockerfile
- **Verification:** Build succeeded
- **Committed in:** df0897e

**3. [Rule 1 - Bug] Removed incompatible vendor/ and composer.lock from PHP 8.4 scaffolding**
- **Found during:** Task 2 (Container startup)
- **Issue:** composer:2 image uses PHP 8.4, generating lock file with PHP 8.4-only dependencies (Symfony 8.x). Container runs PHP 8.3
- **Fix:** Deleted vendor/ and composer.lock, let entrypoint's composer install resolve fresh for PHP 8.3
- **Files modified:** vendor/ (deleted), composer.lock (regenerated)
- **Verification:** All dependencies installed successfully on PHP 8.3
- **Committed in:** df0897e

**4. [Rule 2 - Missing Critical] Added USER www-data to Dockerfile for security**
- **Found during:** Task 1 (Semgrep security scan)
- **Issue:** Dockerfile had no USER directive, running as root
- **Fix:** Added USER www-data, configured docker-compose to override for dev
- **Files modified:** docker/php/Dockerfile, docker-compose.yml
- **Verification:** Semgrep scan passes, container operates correctly
- **Committed in:** df0897e

---

**Total deviations:** 4 auto-fixed (3 bugs, 1 missing critical security)
**Impact on plan:** All auto-fixes necessary for correctness and security. No scope creep.

## Issues Encountered
- Docker Desktop was not running at start; started automatically via `open -a Docker`
- PHP version mismatch between composer:2 image (PHP 8.4) and container (PHP 8.3) required regenerating lock file

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Docker stack fully operational, ready for model and API endpoint development
- Sanctum installed for API token authentication
- Redis available for queues (needed for async PDF processing)

---
*Phase: 01-foundation-docker*
*Completed: 2026-02-20*
