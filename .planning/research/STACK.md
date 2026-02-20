# Stack Research

**Domain:** Laravel 11 PDF Invoice Parser API with OpenAI Integration
**Researched:** 2026-02-20
**Confidence:** HIGH

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel | 11.x | Core framework | Industry-standard PHP framework with excellent queue, API, and microservice support. Latest version with modern routing, slimmer app structure, and better performance. |
| PHP | 8.3 | Runtime | Laravel 11 requires PHP 8.2+. PHP 8.3 offers similar performance to 8.2 (~430-445 req/s for Laravel apps) but with longer security support lifecycle. Both are production-ready for 2025. |
| Nginx | 1.24+ | Web server | Reverse proxy for PHP-FPM; handles HTTP requests and serves static files efficiently. Standard choice for Laravel production deployments. |
| PHP-FPM | 8.3 | Process manager | Processes PHP code behind Nginx. Multi-stage Docker builds with FPM create smaller, more secure production images. |
| SQLite | 3.x | Database | Default in Laravel 11. IMPORTANT: Not suitable for Laravel Cloud (ephemeral filesystems). For portfolio/local microservice OK; for production consider Turso (distributed SQLite) or PostgreSQL. |
| Redis | 7.x | Cache & queue driver | Required for Laravel Horizon. Industry standard for queue backends, caching, and session storage in Laravel microservices. |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| openai-php/client | Latest (check Packagist) | OpenAI API integration | Community-maintained PHP SDK for OpenAI (no official PHP SDK exists). Requires PHP 8.2+, uses PSR-18 HTTP client. Active maintenance, supports GPT-4o vision. |
| openai-php/laravel | Latest | Laravel-specific OpenAI wrapper | Provides Laravel service provider and facades for openai-php/client. Use for cleaner integration with Laravel DI container. |
| spatie/pdf-to-image | 3.2.0+ | PDF to image conversion | PHP 8.2+ required. Uses Imagick + Ghostscript. Industry standard for Laravel PDF processing. Note: Requires system-level Imagick extension and Ghostscript binary. |
| guzzlehttp/guzzle | 7.x | HTTP client | PSR-18 HTTP client for openai-php/client. Standard choice if no HTTP client exists in project. |
| Laravel Sanctum | Built-in (11.x) | API authentication | Token-based auth for microservices. Simpler than Passport, no OAuth2 complexity. Recommended for API-only applications in 2025. |
| Laravel Horizon | Latest | Queue monitoring | Official Laravel package for Redis queue monitoring. Provides elegant dashboard, real-time metrics, worker configuration. Essential for production queue visibility. |
| dedoc/scramble | Latest | API documentation | Modern Laravel OpenAPI generator. No PHPDoc annotations required (auto-generates from code). Preferred over L5-Swagger for 2025 projects due to zero-maintenance docs. |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| Pest PHP | Testing framework | Default in Laravel 11 (alongside PHPUnit). More expressive syntax than PHPUnit. Use for feature/unit tests. Run with `php artisan test`. |
| Laravel Pint | Code style | Laravel's opinionated code formatter. Built on PHP CS Fixer. Should be in composer.json dev dependencies. |
| Docker Compose | Local development | Separate compose.dev.yaml and compose.prod.yaml files. Multi-stage builds for optimized production images. |
| php-amqplib/rabbitmq-bundle | Message queue (optional) | Only if async service-to-service communication needed. Not required for single microservice with internal queues. |

## Installation

```bash
# Core Laravel 11 project
composer create-project laravel/laravel invoice-parser-api
cd invoice-parser-api

# OpenAI SDK
composer require openai-php/laravel

# PDF processing (requires system Imagick + Ghostscript)
composer require spatie/pdf-to-image

# Queue monitoring
composer require laravel/horizon

# API documentation
composer require dedoc/scramble

# Dev dependencies
composer require --dev laravel/pint
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel

# HTTP client (if not already installed)
composer require guzzlehttp/guzzle
```

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| openai-php/client | orhanerday/open-ai | Only if openai-php lacks a specific feature. openai-php has larger community and more active maintenance. |
| Scramble | L5-Swagger | When you need fine-grained control over API docs via annotations. L5-Swagger requires manual PHPDoc but offers more customization. |
| Laravel Sanctum | Laravel Passport | When OAuth2 flows required (third-party integrations, authorization codes). Sanctum lacks refresh tokens and OAuth2 complexity. |
| Pest PHP | PHPUnit | Both supported in Laravel 11. Use PHPUnit if team prefers traditional syntax or has existing PHPUnit test suites. |
| Redis | Database queue driver | Never for production. Database queues don't scale and lack monitoring. Redis is required for Horizon. |
| SQLite | PostgreSQL/MySQL | Use PostgreSQL for production microservices needing concurrent writes, ACID guarantees, or cloud deployments (Laravel Cloud/Vapor). |
| Spatie PDF-to-Image | API services (ConvertAPI, Zamzar) | When you cannot install Imagick/Ghostscript on server (shared hosting). API services cost per conversion but require no system dependencies. |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| SQLite in Laravel Cloud/Vapor | Ephemeral filesystems reset on deploy/reboot. Data loss guaranteed. | Turso (distributed SQLite), PostgreSQL, or Laravel Serverless Postgres |
| Database queue driver | No monitoring, poor performance, no Horizon support. Not production-ready. | Redis with Laravel Horizon |
| PHP 8.1 or lower | Laravel 11 requires PHP 8.2+. Security support ending for 8.1. | PHP 8.3 for longer support lifecycle |
| Imagick without Ghostscript | PDF conversion requires Ghostscript binary (`gs` command). Imagick alone will fail. | Install both Imagick PHP extension AND Ghostscript system package |
| Monolithic deployment | Project is microservice. Don't combine with other services in same container. | Separate Docker containers with single responsibility |
| Global rate limiting only | Protects server but not OpenAI API costs. Need per-user AND per-endpoint limits. | Laravel's per-second rate limiting (new in Laravel 11) + OpenAI-specific throttling |

## Stack Patterns by Variant

**If deploying to Laravel Cloud/Vapor:**
- Use PostgreSQL or Turso instead of SQLite (ephemeral filesystems)
- Use Laravel Octane (Swoole/RoadRunner) for persistent workers
- Configure queue workers to scale automatically with Queue Clusters

**If portfolio/self-hosted:**
- SQLite acceptable for demo purposes with low traffic
- Use Redis for queues even in dev (required for Horizon)
- Docker Compose with separate dev/prod configurations

**If high OpenAI API usage:**
- Implement request caching (same invoice = cached result)
- Add circuit breaker pattern for OpenAI failures
- Consider OpenAI's batch API for non-urgent parsing

**If multiple PDF formats:**
- Add validation step before OpenAI (file type, size, page count)
- Implement retry logic with exponential backoff
- Store original PDFs in separate storage service (S3, CloudFlare R2)

## Version Compatibility

| Package | Compatible With | Notes |
|---------|-----------------|-------|
| Laravel 11.x | PHP 8.2, 8.3, 8.4 | PHP 8.2+ required. Performance differences minimal between versions. |
| openai-php/client | PHP 8.2+ | Requires PSR-18 HTTP client (Guzzle recommended). Check Packagist for latest stable version. |
| spatie/pdf-to-image 3.2.0 | PHP 8.2+ | Version 2.x for PHP < 8.2. Requires system Imagick extension + Ghostscript binary. |
| Laravel Horizon | Laravel 9.0+, PHP 8.0+ | Requires Redis and predis/predis OR phpredis extension. |
| Laravel Sanctum | Built-in Laravel 11 | No version conflicts. Works with SQLite, PostgreSQL, MySQL. |

## Known Configuration Issues

### Spatie PDF-to-Image with PHP-FPM

**Issue:** `FailedToExecuteCommand 'gs'` errors when running in browser (PHP-FPM).

**Fix:** Add to php-fpm.conf:
```ini
env[PATH] = /usr/local/bin:/usr/bin:/bin
```

### ImageMagick PDF Security Policy

**Issue:** PDF operations blocked by ImageMagick security policy.

**Fix:** Edit `/etc/ImageMagick-[VERSION]/policy.xml`:
```xml
<policy domain="coder" rights="read|write" pattern="PDF" />
```

### Ultra-Wide PDFs

**Issue:** Large PDF dimensions cause loading failures.

**Fix:** Update ImageMagick policy.xml resource limits:
```xml
<policy domain="resource" name="width" value="4GiB"/>
<policy domain="resource" name="height" value="4GiB"/>
```

## Docker Configuration Notes

Use multi-stage builds:
1. **Builder stage:** Install Composer dependencies, build assets
2. **Production stage:** Copy only runtime files (vendor/, app/, public/)

Required PHP extensions:
- pdo_sqlite (or pdo_pgsql for PostgreSQL)
- redis
- imagick
- gd (optional, if using as Imagick fallback)

Required system packages:
- ghostscript
- imagemagick

## Sources

**HIGH Confidence:**
- [Laravel 11 Documentation](https://laravel.com/docs/11.x) — Official framework docs
- [OpenAI PHP Client (GitHub)](https://github.com/openai-php/client) — Community SDK repository
- [Spatie PDF-to-Image (GitHub)](https://github.com/spatie/pdf-to-image) — Version 3.2.0 requirements and limitations
- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum) — Official auth package docs
- [Laravel Horizon Documentation](https://laravel.com/docs/12.x/horizon) — Official queue monitoring
- [Scramble Documentation](https://scramble.dedoc.co/) — OpenAPI generator docs

**MEDIUM Confidence:**
- [Microservices with Laravel 11: Best Practices](https://medium.com/@techsolutionstuff/microservices-with-laravel-11-best-practices-for-scaling-applications-63f60d4fbf11) — Architecture patterns
- [Laravel Queues & Jobs 2025](https://medium.com/@backendbyeli/laravel-queues-jobs-2025-smarter-async-workflows-f06f1bde728b) — Queue features and auto-scaling
- [Laravel Docker Production Setup](https://docs.docker.com/guides/frameworks/laravel/production-setup/) — Official Docker guides
- [Laravel API Best Practices 2025](https://www.zestminds.com/guide/laravel-api-development-best-practices-2025) — API patterns
- [Laravel Sanctum API Authentication](https://oneuptime.com/blog/post/2025-07-02-laravel-sanctum-api-auth/view) — Implementation guide
- [PHP Benchmarks 8.2 vs 8.3 vs 8.4](https://sevalla.com/blog/laravel-benchmarks/) — Performance comparisons
- [Per-Second Rate Limiting in Laravel 11](https://medium.com/@newQuery/exploring-laravel-11s-per-second-rate-limiting-a-game-changer-for-api-throttling-4c870de12f62) — New feature overview
- [SQLite in Production with Laravel](https://freek.dev/2906-using-sqlite-in-production-with-laravel) — Production considerations
- [Laravel Cloud SQLite Support](https://cloud.laravel.com/docs/knowledge-base/sqlite) — Ephemeral filesystem limitations

---
*Stack research for: Laravel 11 PDF Invoice Parser API with OpenAI Integration*
*Researched: 2026-02-20*
