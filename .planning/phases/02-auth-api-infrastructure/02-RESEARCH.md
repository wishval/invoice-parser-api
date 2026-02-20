# Phase 2: Authentication & API Infrastructure - Research

**Researched:** 2026-02-20
**Domain:** Laravel 11 API authentication with Sanctum, API resource layer, rate limiting, and error handling
**Confidence:** HIGH

## Summary

Phase 2 implements authentication and API infrastructure for a Laravel 11 REST API using Sanctum for token-based authentication, API Resources for consistent JSON responses, per-user rate limiting, and guaranteed JSON error responses. The phase leverages Laravel 11's streamlined bootstrap/app.php configuration for centralized exception handling and Sanctum's simple token management for API-only authentication (no SPA state management needed).

**Key architectural insight:** Laravel 11 simplified configuration significantly - exception handling, middleware, and service providers now centralize in bootstrap/app.php rather than separate files. Sanctum 4.0 is already installed (via install:api in Phase 1), requiring only User model trait, database seeder for test user, and route protection via auth:sanctum middleware.

**Primary recommendation:** Implement authentication using Sanctum's mobile/SPA token flow (email/password exchange for token), enforce JSON-only error responses via shouldRenderJsonWhen in bootstrap/app.php, use dedicated API Resource classes for all responses (not raw models), and configure per-user rate limiting in AppServiceProvider with fallback to IP for unauthenticated requests.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| AUTH-01 | User can log in with email/password and receive a Sanctum API token | Sanctum's mobile token endpoint pattern (validate credentials, createToken, return plainTextToken) |
| AUTH-02 | User can log out (revoke current API token) | Sanctum's currentAccessToken()->delete() method on authenticated user |
| AUTH-03 | Test user created via database seeder for development/demo | Database seeder with Hash::make() for password hashing, no factory needed for single test user |
| INFR-01 | Parse endpoint rate limited to 10 requests per minute per user | RateLimiter::for() with Limit::perMinute(10)->by($request->user()->id) in AppServiceProvider |
| INFR-02 | All responses use API Resources for consistent JSON structure | Eloquent API Resources with JsonResource base class for all endpoints |
| INFR-03 | All errors return JSON with message and appropriate HTTP status code (never HTML) | shouldRenderJsonWhen() in bootstrap/app.php to force JSON for all api/* routes |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/sanctum | ^4.0 | API token authentication | Official Laravel package for SPA/mobile token auth, simpler than OAuth2 for first-party APIs |
| Laravel API Resources | Built-in (Laravel 11) | Consistent JSON transformation layer | Official abstraction for model-to-JSON conversion, conditional attributes, pagination support |
| RateLimiter | Built-in (Laravel 11) | Per-user API throttling | Native Laravel feature with Redis support for distributed rate limiting |
| Exception Handler | Built-in (Laravel 11) | Centralized error handling | Configured in bootstrap/app.php, replaces Laravel 10's Handler.php approach |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Hash facade | Built-in (Laravel 11) | Password hashing in seeders | Creating test users with bcrypt-hashed passwords |
| ValidationException | Built-in (Laravel 11) | Structured validation errors | Automatically returns 422 JSON with errors object for API requests |
| DB facade | Built-in (Laravel 11) | Direct database inserts in seeders | Simple test user creation without model events |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Sanctum | Laravel Passport | Passport adds OAuth2 complexity unnecessary for first-party API; Sanctum lighter and simpler |
| API Resources | Manual array transformations | Resources provide reusable, testable transformation logic; manual arrays scatter logic across controllers |
| Built-in RateLimiter | Custom middleware | Built-in solution handles Redis caching, header responses, and 429 automatically; custom requires reimplementing |

**Installation:**
```bash
# Sanctum already installed via Phase 1: php artisan install:api
# No additional packages required
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── AuthController.php      # login, logout endpoints
│   │       └── ...                     # future controllers
│   ├── Resources/
│   │   └── UserResource.php            # JSON transformation for User model
│   └── Middleware/                     # (auth:sanctum applied in routes)
├── Models/
│   └── User.php                        # HasApiTokens trait added
├── Providers/
│   └── AppServiceProvider.php          # Rate limiter definitions
└── ...
database/
├── seeders/
│   └── DatabaseSeeder.php              # Test user creation
bootstrap/
└── app.php                              # Exception handling config
routes/
└── api.php                              # Protected routes under /api/v1/
```

### Pattern 1: Sanctum Token Authentication Flow
**What:** Email/password credential validation, token creation, and revocation for API-only authentication
**When to use:** Every API requiring stateless token authentication without OAuth complexity
**Example:**
```php
// Source: https://laravel.com/docs/11.x/sanctum
// app/Http/Controllers/Api/AuthController.php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required', // or default to 'api-client'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return [
            'token' => $user->createToken($request->device_name)->plainTextToken,
        ];
    }

    public function logout(Request $request)
    {
        // Revoke only the current token used for authentication
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
```

### Pattern 2: API Resource Transformation
**What:** Consistent JSON structure using dedicated Resource classes instead of returning raw Eloquent models
**When to use:** Every API endpoint returning data (single resource or collection)
**Example:**
```php
// Source: https://laravel.com/docs/11.x/eloquent-resources
// app/Http/Resources/UserResource.php

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Never expose password or tokens
        ];
    }
}

// Usage in controller
use App\Http\Resources\UserResource;

Route::get('/user', function (Request $request) {
    return new UserResource($request->user());
})->middleware('auth:sanctum');
```

### Pattern 3: Per-User Rate Limiting Configuration
**What:** Define named rate limiters in AppServiceProvider with per-user segmentation
**When to use:** Throttling API endpoints by authenticated user identity, not just IP
**Example:**
```php
// Source: https://laravel.com/docs/11.x/rate-limiting
// app/Providers/AppServiceProvider.php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // Default API limiter: 60 req/min per user or IP if unauthenticated
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    // Parse endpoint specific limiter: 10 req/min per user
    RateLimiter::for('parse', function (Request $request) {
        return $request->user()
            ? Limit::perMinute(10)->by($request->user()->id)
            : Limit::perMinute(10)->by($request->ip());
    });
}

// Apply in routes/api.php
Route::post('/parse', [InvoiceController::class, 'parse'])
    ->middleware(['auth:sanctum', 'throttle:parse']);
```

### Pattern 4: JSON-Only Error Responses
**What:** Configure exception handler to force JSON responses for all API routes, never HTML
**When to use:** Any API that must never leak HTML error pages to clients
**Example:**
```php
// Source: https://laravel.com/docs/11.x/errors
// bootstrap/app.php

use Illuminate\Http\Request;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Force JSON for all /api/* routes
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*');
        });
    })->create();
```

### Pattern 5: Database Seeder for Test User
**What:** Create a single test user with known credentials for development/demo purposes
**When to use:** Development databases, demo environments, local testing (never production auto-seeding)
**Example:**
```php
// Source: https://laravel.com/docs/11.x/seeding
// database/seeders/DatabaseSeeder.php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

public function run(): void
{
    DB::table('users')->insert([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// Run with: php artisan db:seed
// Or refresh database: php artisan migrate:fresh --seed
```

### Anti-Patterns to Avoid

- **Returning raw models from controllers:** Always wrap in API Resources to prevent accidental exposure of sensitive fields and ensure consistent structure
- **Token abilities/scopes for simple APIs:** Unless you have admin vs user roles needing different permissions, skip token scopes - they add complexity with no value for single-role APIs
- **IP-only rate limiting for authenticated APIs:** Authenticated users should be rate-limited by user ID, not IP, to prevent shared IPs from blocking legitimate users
- **Forgetting device_name in token creation:** While optional, device_name helps identify tokens in database for revocation management (e.g., "mobile-app", "web-client")
- **Using global exception handler for route-specific logic:** Use shouldRenderJsonWhen for broad rules, not per-route conditionals in render() closures

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| API token generation | Custom JWT signing, token storage, expiration logic | Sanctum's createToken() | Handles token generation, hashing, storage, expiration, and secure comparison automatically |
| Rate limiting | Custom middleware with cache keys and counters | RateLimiter facade | Built-in Redis support, automatic 429 responses with retry-after headers, cache invalidation |
| JSON error responses | Try-catch in every controller, manual JSON formatting | shouldRenderJsonWhen() + ValidationException | Laravel auto-formats validation errors as 422 JSON, exception handler centralizes all error formatting |
| Password hashing | Custom bcrypt calls, salt management | Hash::make() / Hash::check() | Framework manages bcrypt rounds, salt generation, timing attack prevention |
| API response structure | Manual array building in controllers | API Resources | Reusable transformation logic, conditional attributes (whenLoaded, whenHas), automatic pagination wrapping |

**Key insight:** Laravel's API infrastructure is production-ready out of the box. Custom solutions for these problems introduce security vulnerabilities (password hashing, token generation), performance issues (rate limiting without Redis), and maintenance overhead (scattered response formatting). The framework has already solved these problems correctly.

## Common Pitfalls

### Pitfall 1: Exposing Sensitive Fields in API Responses
**What goes wrong:** Returning raw Eloquent models directly from controllers exposes all model attributes including password hashes, remember tokens, and API tokens
**Why it happens:** Developers skip API Resources for "simple" endpoints, unaware that Model::toArray() includes all fillable/visible attributes
**How to avoid:** Always use API Resources, explicitly list returned fields in toArray() method, never rely on $hidden property alone
**Warning signs:** Password or token fields appearing in JSON responses, client code accessing fields that should be internal

### Pitfall 2: Rate Limiting Before Authentication
**What goes wrong:** Applying throttle middleware before auth:sanctum causes rate limits to key by IP instead of user ID, making limits ineffective for authenticated users
**Why it happens:** Middleware order in route definitions is not obvious - throttle:api applied before auth:sanctum will not have access to $request->user()
**How to avoid:** Always apply middleware in order: ['auth:sanctum', 'throttle:limitername'], ensure rate limiter checks $request->user() availability
**Warning signs:** Rate limits triggering for one user when another user hits the endpoint from same network/IP

### Pitfall 3: Forgetting to Add HasApiTokens Trait
**What goes wrong:** Calling $user->createToken() fails with "Method does not exist" error
**Why it happens:** Sanctum token functionality requires the HasApiTokens trait on User model, but it's not added by install:api command
**How to avoid:** Add `use Laravel\Sanctum\HasApiTokens;` trait to User model immediately after installing Sanctum
**Warning signs:** Method not found errors when calling createToken(), tokens(), or currentAccessToken()

### Pitfall 4: HTML Error Pages Leaking to API Clients
**What goes wrong:** Clients receive HTML error pages (500, 404, 403) instead of JSON, breaking error handling
**Why it happens:** Default Laravel behavior renders HTML views for errors unless request explicitly has Accept: application/json header or wantsJson() returns true
**How to avoid:** Configure shouldRenderJsonWhen() in bootstrap/app.php to check $request->is('api/*'), ensuring route-based JSON enforcement regardless of headers
**Warning signs:** API testing tools showing HTML in response body, client apps crashing on error parsing

### Pitfall 5: Validation Errors Not Returning 422 Status
**What goes wrong:** Validation failures return 302 redirects or 200 status instead of 422 JSON for API requests
**Why it happens:** Using validate() on Request without ensuring API route detection, or throwing wrong exception type
**Why this matters:** API clients expect 422 Unprocessable Entity with errors object, not redirects
**How to avoid:** Use ValidationException::withMessages() for manual validation, rely on FormRequest auto-detection for XHR, verify shouldRenderJsonWhen() applies to validation routes
**Warning signs:** Postman/curl showing 302 redirects on validation failure, client apps not displaying field-specific errors

### Pitfall 6: Token Revocation Scope Confusion
**What goes wrong:** Calling $user->tokens()->delete() on logout revokes ALL user tokens across all devices/sessions instead of just the current one
**Why it happens:** Misunderstanding difference between tokens() (all tokens) and currentAccessToken() (the token used for current request)
**How to avoid:** Use $request->user()->currentAccessToken()->delete() for single-session logout, reserve $user->tokens()->delete() for "logout all devices" feature
**Warning signs:** Users complaining mobile app logs out when they log out on web, unexpected "unauthenticated" errors across devices

## Code Examples

Verified patterns from official sources:

### Login Endpoint with Credential Validation
```php
// Source: https://laravel.com/docs/11.x/sanctum
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

Route::post('/api/v1/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    $token = $user->createToken('api-client')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => new UserResource($user),
    ]);
});
```

### Logout Endpoint Revoking Current Token
```php
// Source: https://laravel.com/docs/11.x/sanctum
Route::post('/api/v1/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Logged out successfully']);
})->middleware('auth:sanctum');
```

### Protected Route with API Resource Response
```php
// Source: https://laravel.com/docs/11.x/eloquent-resources
use App\Http\Resources\UserResource;

Route::get('/api/v1/user', function (Request $request) {
    return new UserResource($request->user());
})->middleware('auth:sanctum');
```

### Test User Seeder
```php
// Source: https://laravel.com/docs/11.x/seeding
// database/seeders/DatabaseSeeder.php
use Illuminate\Support\Facades\Hash;
use App\Models\User;

public function run(): void
{
    User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);
}
```

### Per-User Rate Limiting with Fallback
```php
// Source: https://laravel.com/docs/11.x/rate-limiting
// app/Providers/AppServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('parse', function (Request $request) {
        return Limit::perMinute(10)->by(
            $request->user()?->id ?: $request->ip()
        );
    });
}
```

### JSON-Only Exception Handler Configuration
```php
// Source: https://laravel.com/docs/11.x/errors
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
        return $request->is('api/*');
    });
})
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| App\Exceptions\Handler.php class | bootstrap/app.php withExceptions() | Laravel 11 (Feb 2024) | Exception handling now centralized in bootstrap, no separate Handler class needed |
| Passport for API tokens | Sanctum for first-party APIs | Laravel 7+ (2020), standard by Laravel 8 | Sanctum preferred for SPAs/mobile, Passport only for OAuth2 providers |
| Manual $hidden property on models | API Resources for all responses | Best practice since Laravel 5.5 (2017) | Resources prevent accidental data leakage, provide reusable transformation logic |
| routes/api.php auto-loaded | Explicit routing config in bootstrap/app.php | Laravel 11 (Feb 2024) | Routes now explicitly registered, no "magic" auto-loading |

**Deprecated/outdated:**
- **App\Exceptions\Handler::render()** - Replaced by bootstrap/app.php withExceptions() closures in Laravel 11
- **config/auth.php guards array manual config** - Sanctum middleware handles guard automatically, no manual 'api' guard config needed
- **RouteServiceProvider for route prefixes** - Route prefixes/middleware now in bootstrap/app.php routing config

## Open Questions

1. **Should token expiration be configured for this project?**
   - What we know: Sanctum supports optional token expiration via config/sanctum.php 'expiration' value in minutes
   - What's unclear: Whether portfolio project needs automatic expiration or if manual revocation is sufficient
   - Recommendation: Leave expiration as null (infinite) for demo simplicity, document that production should set expiration (e.g., 1 week = 10080 minutes)

2. **Does rate limiter need Redis configuration verification?**
   - What we know: Redis already configured in Phase 1 for queue, rate limiting can use same connection
   - What's unclear: Whether CACHE_DRIVER needs to be 'redis' or if RateLimiter uses queue connection
   - Recommendation: Verify .env has CACHE_DRIVER=redis, test rate limiting actually stores in Redis not file cache

3. **Should validation error format be customized beyond default 422 structure?**
   - What we know: Laravel's default 422 validation response includes "message" and "errors" object with field keys
   - What's unclear: Whether this format satisfies frontend requirements or needs customization
   - Recommendation: Start with default Laravel format (industry standard), customize only if specific client requirements emerge

## Sources

### Primary (HIGH confidence)
- [Laravel 11 Sanctum Documentation](https://laravel.com/docs/11.x/sanctum) - API token authentication, middleware, token management
- [Laravel 11 API Resources Documentation](https://laravel.com/docs/11.x/eloquent-resources) - Resource classes, collections, pagination, conditional attributes
- [Laravel 11 Rate Limiting Documentation](https://laravel.com/docs/11.x/rate-limiting) - Per-user throttling, named limiters, Redis configuration
- [Laravel 11 Error Handling Documentation](https://laravel.com/docs/11.x/errors) - JSON error responses, shouldRenderJsonWhen, exception rendering
- [Laravel 11 Validation Documentation](https://laravel.com/docs/11.x/validation) - API validation error format, 422 responses, ValidationException
- [Laravel 11 Database Seeding Documentation](https://laravel.com/docs/11.x/seeding) - Seeder creation, Hash facade, running seeders

### Secondary (MEDIUM confidence)
- [OneUpTime: Laravel Sanctum Authentication Guide](https://oneuptime.com/blog/post/2026-01-26-laravel-sanctum-authentication/view) - Best practices and security considerations
- [Laravel Daily: API Resources Relations](https://laraveldaily.com/post/laravel-api-resources-relations-when-methods) - N+1 query prevention with whenLoaded
- [SaaSykit: JSON API Error Handling](https://saasykit.com/blog/how-to-return-json-errors-in-laravel-apis-instead-of-rendered-web-page-errors) - bootstrap/app.php configuration patterns
- [Cloudways: Laravel Rate Limiting Guide](https://www.cloudways.com/blog/laravel-and-api-rate-limiting/) - Per-user vs IP-based throttling

### Tertiary (LOW confidence)
- [Medium: Building Secure APIs with Sanctum](https://medium.com/@nethmiwelgamvila/building-secure-apis-in-laravel-11-with-sanctum-c48a2ef188e3) - Implementation walkthrough
- [Gyata: Nested Relationships in API Resources](https://www.gyata.ai/laravel/nesting-relationships) - Performance considerations

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All components are official Laravel 11 features, well-documented with stable APIs
- Architecture: HIGH - Patterns sourced from official Laravel docs, verified with current Laravel 11 structure
- Pitfalls: MEDIUM-HIGH - Common issues documented in community resources and official docs, some based on version migration experience

**Research date:** 2026-02-20
**Valid until:** 2026-03-22 (30 days - Laravel stable, unlikely breaking changes in minor versions)
