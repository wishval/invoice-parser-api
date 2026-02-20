# Phase 1: Foundation & Docker - Research

**Researched:** 2026-02-20
**Domain:** Docker Compose orchestration, Laravel 11 project scaffolding, database migrations, API route versioning
**Confidence:** HIGH

## Summary

Phase 1 establishes the entire development environment from an empty repository. The project directory currently contains only `.git`, `.gitignore`, and `.planning/`. Everything must be created: the Laravel 11 project, Docker infrastructure (PHP-FPM 8.3, Nginx, Redis), database migrations for invoices/invoice_items tables, and versioned API routing under `/api/v1/`.

The key technical challenges are: (1) installing the Imagick PHP extension for PHP 8.3 in Docker, which historically required building from source but is now available via PECL 3.8.1; (2) configuring the ImageMagick security policy to allow PDF operations; (3) setting up Laravel 11's new routing system where `api.php` is not included by default and must be installed via `php artisan install:api`.

**Primary recommendation:** Use a Debian-based PHP 8.3-FPM Docker image (not Alpine) for easier Imagick/Ghostscript installation. Scaffold Laravel inside Docker to ensure consistent PHP version. Configure API versioning via `apiPrefix: 'api/v1'` in `bootstrap/app.php`.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DEVP-01 | Docker Compose runs entire stack (PHP-FPM, Nginx, Redis) with single command | Docker Compose multi-service setup with health checks; Nginx reverse proxy to PHP-FPM on port 9000; Redis service for queue driver |
| DEVP-02 | Docker setup includes Imagick and Ghostscript for PDF processing | Imagick PECL 3.8.1 now supports PHP 8.3; Ghostscript installed via apt-get; ImageMagick policy.xml must allow PDF operations |
| INFR-04 | API versioned under /api/v1/ prefix | Laravel 11 `bootstrap/app.php` `withRouting(apiPrefix: 'api/v1')` configuration; routes defined in `routes/api.php` created via `php artisan install:api` |
</phase_requirements>

## Standard Stack

### Core (Phase 1 specific)

| Library/Tool | Version | Purpose | Why Standard |
|-------------|---------|---------|--------------|
| Laravel | 11.x | Application framework | Latest LTS-track version; slim app structure; PHP 8.2+ required |
| PHP | 8.3-FPM | Runtime + process manager | Runs behind Nginx; longer security support than 8.2; Laravel 11 compatible |
| Nginx | 1.24+ (Alpine) | Reverse proxy / web server | Serves static files, proxies PHP requests to FPM on port 9000 |
| Redis | 7.x (Alpine) | Queue driver + cache | Required for Laravel Horizon in later phases; configure as queue driver from day one |
| SQLite | 3.x | Database | Default in Laravel 11; zero-config for portfolio; created automatically |
| Imagick (PECL) | 3.8.1 | PHP extension for PDF-to-image | Required by spatie/pdf-to-image; PECL 3.8.1 supports PHP 8.3 |
| Ghostscript | System package | PDF rendering engine | Required alongside Imagick for PDF operations; installed via `apt-get install ghostscript` |
| Docker Compose | v2 | Container orchestration | Single `docker-compose up -d` starts entire stack |

### Supporting

| Library/Tool | Version | Purpose | When to Use |
|-------------|---------|---------|-------------|
| Composer | 2.x | PHP dependency management | Installed in Docker builder stage; runs `composer install` |
| Laravel Sanctum | Built-in | API auth (installed now, configured in Phase 2) | `php artisan install:api` installs it alongside api.php routes |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Debian-based PHP image | Alpine-based PHP image | Alpine is smaller (~50MB vs ~150MB) but Imagick compilation is harder; Debian has better apt package support for Ghostscript and ImageMagick |
| SQLite | PostgreSQL in Docker | PostgreSQL adds complexity; SQLite is fine for portfolio/dev; migrations are DB-agnostic |
| Single Dockerfile | Separate dev/prod Dockerfiles | Single multi-stage Dockerfile with targets is simpler to maintain |

**Installation (inside Docker):**
```bash
# Laravel project creation (run once, on host or in temporary container)
composer create-project laravel/laravel:^11.0 .

# Install API routing + Sanctum
php artisan install:api

# Later phases will add:
# composer require openai-php/laravel spatie/pdf-to-image laravel/horizon dedoc/scramble
```

## Architecture Patterns

### Recommended Project Structure (Phase 1 additions)

```
/                              # Project root
├── docker/
│   ├── php/
│   │   └── Dockerfile         # PHP 8.3-FPM with Imagick, Ghostscript
│   └── nginx/
│       └── default.conf       # Nginx vhost config for Laravel
├── docker-compose.yml         # Full stack orchestration
├── .env.example               # Template with all Docker/Laravel vars
├── bootstrap/
│   └── app.php                # Modified: apiPrefix: 'api/v1'
├── routes/
│   └── api.php                # Created by install:api; v1 routes
├── database/
│   ├── database.sqlite        # Auto-created by Laravel
│   └── migrations/
│       ├── xxxx_create_invoices_table.php
│       └── xxxx_create_invoice_items_table.php
├── app/
│   └── Models/
│       ├── Invoice.php
│       └── InvoiceItem.php
└── ...                        # Standard Laravel 11 structure
```

### Pattern 1: Multi-Service Docker Compose

**What:** Separate containers for each concern (PHP-FPM, Nginx, Redis), connected via Docker network.

**When to use:** Always for Laravel applications requiring a web server and background services.

**Example docker-compose.yml structure:**
```yaml
services:
  app:                         # PHP-FPM container
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    volumes:
      - .:/var/www/html
    depends_on:
      redis:
        condition: service_healthy
    environment:
      - DB_CONNECTION=sqlite
      - QUEUE_CONNECTION=redis
      - REDIS_HOST=redis

  nginx:                       # Web server
    image: nginx:1.24-alpine
    ports:
      - "8000:80"
    volumes:
      - .:/var/www/html:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app

  redis:                       # Queue + cache
    image: redis:7-alpine
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 3
```

### Pattern 2: Laravel 11 API Route Versioning

**What:** Configure the API prefix in `bootstrap/app.php` using the `apiPrefix` parameter.

**When to use:** Always in Laravel 11 for API-only applications.

**Example:**
```php
// bootstrap/app.php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

**Key detail:** In Laravel 11, `routes/api.php` does NOT exist by default. You must run `php artisan install:api` which creates the file AND installs Sanctum.

### Pattern 3: PHP-FPM Dockerfile with Imagick + Ghostscript

**What:** Multi-stage Dockerfile that installs system dependencies for PDF processing.

**Example:**
```dockerfile
FROM php:8.3-fpm AS base

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libmagickwand-dev \
    ghostscript \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        zip \
        bcmath \
        gd \
        pcntl

# Imagick via PECL (3.8.1 supports PHP 8.3)
RUN pecl install imagick-3.8.1 \
    && docker-php-ext-enable imagick

# Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# ImageMagick policy: allow PDF operations
RUN if [ -f /etc/ImageMagick-6/policy.xml ]; then \
        sed -i 's/<policy domain="coder" rights="none" pattern="PDF"/<policy domain="coder" rights="read|write" pattern="PDF"/' /etc/ImageMagick-6/policy.xml; \
    fi

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP-FPM config: expose PATH for Ghostscript
RUN echo 'env[PATH] = /usr/local/bin:/usr/bin:/bin' >> /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
```

### Pattern 4: Nginx Configuration for Laravel

**What:** Nginx vhost that proxies PHP requests to FPM and serves static files directly.

**Example:**
```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Anti-Patterns to Avoid

- **Running composer on host:** Always run `composer install` inside the Docker container to match PHP version and extensions. Running on host with different PHP version causes dependency conflicts.
- **Using `QUEUE_CONNECTION=sync`:** Set `QUEUE_CONNECTION=redis` in `.env.example` from day one, even before queue jobs exist. Prevents the default sync behavior from silently running later phases' jobs synchronously.
- **Committing `database.sqlite`:** Add to `.gitignore`. Each developer should have their own local database.
- **Skipping `php artisan install:api`:** In Laravel 11, `routes/api.php` does not exist by default. Without this command, no API routes are available.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Docker PHP image with extensions | Manual PHP compilation | `php:8.3-fpm` official image + `docker-php-ext-install` | Official images handle extension compilation correctly |
| ImageMagick PDF policy | Manual policy.xml creation | `sed` to modify existing policy.xml | Policy file structure varies by ImageMagick version; modifying existing is safer |
| API route registration | Manual route file loading | `php artisan install:api` + `apiPrefix` in app.php | Laravel 11 handles middleware, rate limiting, Sanctum setup automatically |
| Nginx config from scratch | Writing full nginx.conf | Minimal server block with Laravel-specific `try_files` | Only need the vhost config; base nginx.conf from official image is fine |
| Redis connection management | Custom Redis client | Laravel's built-in Redis facade + `predis/predis` or `phpredis` | Laravel handles connection pooling, serialization, prefix namespacing |

**Key insight:** Phase 1 is infrastructure scaffolding. Every component has well-established Docker patterns. The value is in correct configuration, not custom code.

## Common Pitfalls

### Pitfall 1: ImageMagick PDF Security Policy Blocks PDF Operations
**What goes wrong:** Imagick throws "not authorized" errors when trying to read/convert PDFs.
**Why it happens:** Default ImageMagick `policy.xml` blocks PDF operations for security (prevents Ghostscript exploits).
**How to avoid:** Modify policy.xml in the Dockerfile to allow PDF read/write operations. The exact file path depends on ImageMagick version (6 vs 7): `/etc/ImageMagick-6/policy.xml` or `/etc/ImageMagick-7/policy.xml`.
**Warning signs:** "attempt to perform an operation not allowed by the security policy 'PDF'" error.

### Pitfall 2: Ghostscript Not Found by PHP-FPM
**What goes wrong:** PDF conversion works in CLI but fails in web requests via PHP-FPM.
**Why it happens:** PHP-FPM runs with a restricted `PATH` environment variable that does not include `/usr/bin` where `gs` (Ghostscript) is installed.
**How to avoid:** Add `env[PATH] = /usr/local/bin:/usr/bin:/bin` to PHP-FPM pool configuration (`www.conf`).
**Warning signs:** "FailedToExecuteCommand 'gs'" errors only in web context, not in `php artisan tinker`.

### Pitfall 3: Laravel 11 Missing api.php Routes File
**What goes wrong:** API routes return 404 because `routes/api.php` was never created.
**Why it happens:** Laravel 11 removed `api.php` from the default installation. It must be explicitly installed.
**How to avoid:** Run `php artisan install:api` during project setup. This creates `routes/api.php` and installs Sanctum.
**Warning signs:** `Route::getRoutes()` shows no API routes; `/api/v1/...` returns 404.

### Pitfall 4: SQLite Database File Permissions in Docker
**What goes wrong:** Laravel cannot create or write to `database/database.sqlite` inside the container.
**Why it happens:** The container user (`www-data`) does not have write permissions to the `database/` directory.
**How to avoid:** Ensure `database/` directory and `database.sqlite` file are writable by `www-data` (UID 33). Use `chown` or volume mount permissions in Dockerfile/entrypoint.
**Warning signs:** "unable to open database file" or "SQLSTATE[HY000]: General error: 8 attempt to write a readonly database".

### Pitfall 5: QUEUE_CONNECTION Defaults to sync
**What goes wrong:** Queue jobs run synchronously in the HTTP request, causing timeouts in later phases.
**Why it happens:** Laravel defaults `QUEUE_CONNECTION=sync` in fresh installations. If `.env` is copied from `.env.example` without changing this, all queue dispatch calls block.
**How to avoid:** Set `QUEUE_CONNECTION=redis` in `.env.example` from Phase 1. Even though queue jobs do not exist yet, this prevents a silent misconfiguration trap.
**Warning signs:** No jobs appearing in Redis; API requests taking unexpectedly long once queue jobs are added in Phase 3.

### Pitfall 6: Docker Volume Overwriting vendor/ Directory
**What goes wrong:** Bind-mounting the entire project directory (`.:/var/www/html`) overwrites `vendor/` built during Docker image build.
**Why it happens:** The bind mount replaces the container filesystem with host filesystem. If host has no `vendor/`, the container's installed dependencies disappear.
**How to avoid:** For development, run `composer install` as part of an entrypoint script, OR use a named volume for vendor/. For this portfolio project, running composer install inside the container after startup is simplest.
**Warning signs:** "Class not found" errors; `vendor/autoload.php` missing.

## Code Examples

### Migration: invoices table
```php
// database/migrations/xxxx_create_invoices_table.php
// Source: Laravel 11 migration conventions + project REQUIREMENTS.md
public function up(): void
{
    Schema::create('invoices', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('original_filename');
        $table->string('stored_path');
        $table->string('status')->default('pending'); // pending, processing, completed, failed
        $table->string('invoice_number')->nullable();
        $table->date('invoice_date')->nullable();
        $table->date('due_date')->nullable();
        $table->string('currency', 3)->nullable();

        // Vendor info
        $table->string('vendor_name')->nullable();
        $table->text('vendor_address')->nullable();
        $table->string('vendor_tax_id')->nullable();

        // Customer info
        $table->string('customer_name')->nullable();
        $table->text('customer_address')->nullable();
        $table->string('customer_tax_id')->nullable();

        // Totals
        $table->decimal('subtotal', 12, 2)->nullable();
        $table->decimal('tax_amount', 12, 2)->nullable();
        $table->decimal('total', 12, 2)->nullable();

        $table->text('error_message')->nullable();
        $table->timestamps();

        $table->index('status');
        $table->index('user_id');
    });
}
```

### Migration: invoice_items table
```php
// database/migrations/xxxx_create_invoice_items_table.php
public function up(): void
{
    Schema::create('invoice_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
        $table->string('description');
        $table->decimal('quantity', 10, 3)->default(1);
        $table->decimal('unit_price', 12, 2);
        $table->decimal('amount', 12, 2);
        $table->decimal('tax', 12, 2)->nullable();
        $table->timestamps();
    });
}
```

### Minimal API Route (Phase 1 smoke test)
```php
// routes/api.php
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'version' => 'v1',
    ]);
});
```

This route will be accessible at `GET /api/v1/health` due to the `apiPrefix: 'api/v1'` configuration.

### .env.example key settings
```env
APP_NAME="Invoice Parser API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# DB_DATABASE is auto-resolved to database/database.sqlite

QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `RouteServiceProvider` for API prefix | `apiPrefix` in `bootstrap/app.php` | Laravel 11 (March 2024) | Simpler configuration; no service provider needed |
| `routes/api.php` included by default | Must run `php artisan install:api` | Laravel 11 (March 2024) | Clean slate; only install what you need |
| PECL imagick broken on PHP 8.3 | PECL imagick 3.8.1 supports PHP 8.3 | November 2025 | No longer need to build from source |
| Separate `compose.dev.yaml` / `compose.prod.yaml` | Single `docker-compose.yml` with profiles or overrides | Docker Compose v2 (2023+) | Simpler for portfolio projects |

**Deprecated/outdated:**
- Building Imagick from GitHub source for PHP 8.3: No longer necessary as of PECL imagick 3.8.1 (November 2025)
- `RouteServiceProvider.php`: Removed in Laravel 11; routing configured in `bootstrap/app.php`
- `app/Http/Kernel.php`: Removed in Laravel 11; middleware configured in `bootstrap/app.php`

## Open Questions

1. **Imagick 3.8.1 PECL install reliability on php:8.3-fpm Debian image**
   - What we know: PECL 3.8.1 released Nov 2025 with PHP 8.3 support confirmed. cPanel installs succeed.
   - What's unclear: Whether `pecl install imagick-3.8.1` works cleanly on the official `php:8.3-fpm` Docker image without additional workarounds.
   - Recommendation: Try PECL install first. If it fails, fall back to building from GitHub source (well-documented pattern). Build step in plan should include fallback.

2. **Entrypoint script vs manual setup for migrations**
   - What we know: `docker-compose up -d` should require no manual setup beyond .env.
   - What's unclear: Whether migrations should auto-run on container start (via entrypoint) or be a one-time setup step.
   - Recommendation: Use an entrypoint script that runs `php artisan migrate --force` on startup. This satisfies the "no manual setup" requirement while being idempotent (migrations only run if not already applied).

## Sources

### Primary (HIGH confidence)
- [Docker Official Laravel Production Setup Guide](https://docs.docker.com/guides/frameworks/laravel/production-setup/) - Multi-stage build, compose structure, Nginx config
- [Laravel 11.x Official Installation Docs](https://laravel.com/docs/11.x/installation) - Project creation, default configuration, SQLite default
- [Laravel 11.x Official Routing Docs](https://laravel.com/docs/11.x/routing) - `withRouting`, `apiPrefix` configuration
- [PECL Imagick Package Page](https://pecl.php.net/package/imagick) - Version 3.8.1 released 2025-11-26
- [Laravel 11.x Sanctum Docs](https://laravel.com/docs/11.x/sanctum) - `php artisan install:api` behavior

### Secondary (MEDIUM confidence)
- [Orkhan's Blog: Imagick with PHP 8.3 on Docker](https://orkhan.dev/2024/02/07/using-imagick-with-php-83-on-docker/) - Fallback build-from-source approach
- [Laravel Daily: API Versioning](https://laraveldaily.com/tip/api-versioning) - apiPrefix pattern
- [Artisan Page: install:api command reference](https://artisan.page/11.x/installapi) - Command options and behavior

### Tertiary (LOW confidence)
- [XenForo Forum: Imagick PHP 8.3 compatibility discussion](https://xenforo.com/community/threads/imagick-still-not-installable-on-php-8-3-does-xenforo-recommend-a-fully-compatible-alternative-s.221222/) - Community reports on 3.8.1 compatibility

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Official Docker images, official Laravel docs, well-established patterns
- Architecture: HIGH - Standard Docker Compose + Laravel patterns used in production widely
- Pitfalls: HIGH - Well-documented issues with ImageMagick policy, PHP-FPM PATH, Laravel 11 routing changes
- Imagick PECL 3.8.1 on PHP 8.3: MEDIUM - Confirmed working by PECL release + community reports, but not personally verified in exact Docker image

**Research date:** 2026-02-20
**Valid until:** 2026-03-20 (stable infrastructure patterns; check Imagick PECL for updates)
