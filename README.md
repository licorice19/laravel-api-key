# API Key Authentication for Laravel

Simple and reliable API key authentication for internal tools and B2B integrations — no user binding, no overhead.

## Installation
```bash
composer require licorice19/api-key

php artisan vendor:publish --tag=api-key-config
php artisan vendor:publish --tag=api-key-migrations
php artisan migrate
```

## Quick Start

Create your first API key:
```bash
# Save the key — the plain key won't be shown again
php artisan api-key:create "My App"
```

Protect your routes:
```php
Route::middleware('api-key')->group(function () {
    Route::get('/data', DataController::class);
});
```

Clients can pass the key in either format:
```
X-API-Key: your-api-key
Authorization: Bearer your-api-key
```

## Usage Examples

### Creating an API Key

**Via Artisan CLI:**
```bash
# Without expiration
php artisan api-key:create "Partner Integration"

# With expiration date
php artisan api-key:create "Temporary Access" --expires="2026-12-31 23:59:59"

# With rate limit (100 requests per 60 seconds)
php artisan api-key:create "Limited Partner" --rate-limit=100 --rate-period=60
```

**Programmatically:**
```php
use Licorice19\ApiKey\Models\ApiKey;
use Licorice19\ApiKey\Services\ApiKeyService;

// Via model
$result = ApiKey::createKey('Partner Integration', new \DateTime('2026-12-31'));
$key = $result['key']; // Store this — it won't be shown again!

// Via service with rate limit
$service = app(ApiKeyService::class);
$result = $service->createKey('Limited Partner', null, 100, 60); // 100 req / 60 sec
```

### Protecting Routes
```php
// Protect a group of routes
Route::middleware('api-key')->group(function () {
    Route::get('/data', [DataController::class, 'index']);
    Route::post('/webhook', [WebhookController::class, 'handle']);
});

// Protect a single route
Route::get('/internal/stats', [StatsController::class, 'index'])
    ->middleware('api-key');
```

### Accessing the API Key in a Controller
```php
public function index(Request $request)
{
    $apiKey = $request->attributes->get('api_key');

    return response()->json([
        'key_name' => $apiKey->name,
        'last_used' => $apiKey->last_used_at,
    ]);
}
```

### Testing with cURL
```bash
# Using X-API-Key header
curl -H "X-API-Key: your-api-key-here" \
     https://your-app.com/api/data

# Using Authorization Bearer
curl -H "Authorization: Bearer your-api-key-here" \
     https://your-app.com/api/data

# POST request
curl -X POST \
     -H "X-API-Key: your-api-key-here" \
     -H "Content-Type: application/json" \
     -d '{"event": "test"}' \
     https://your-app.com/api/webhook

# Missing key → 401
# {"error": "API key is required"}

# Invalid key → 401
# {"error": "Invalid API key"}
```

## Key Management
```bash
php artisan api-key:create        # Create a new key
php artisan api-key:list          # List all keys
php artisan api-key:revoke {id}   # Deactivate a key
php artisan api-key:activate {id} # Reactivate a key
php artisan api-key:delete {id}   # Delete a key
```

Or manage keys programmatically:
```php
$service = app(ApiKeyService::class);

$result = $service->createKey('Partner Integration', new \DateTime('2026-12-31'));
$keyId = $result['model']->id;

$service->revokeById($keyId);
$service->activateById($keyId);
$service->deleteById($keyId);
```

## Rate Limiting

Each API key can have its own rate limit. When exceeded, the API returns `429 Too Many Requests`.

### Configure Rate Limit per Key

```bash
# 100 requests per 60 seconds
php artisan api-key:create "Limited Partner" --rate-limit=100 --rate-period=60

# 1000 requests per hour
php artisan api-key:create "Hourly Limited" --rate-limit=1000 --rate-period=3600
```

### Response Headers

When rate limiting is enabled, responses include:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
```

### Rate Limit Exceeded Response

```json
{
    "error": "Rate limit exceeded"
}
```

Rate limit information is available via response headers only:
- `X-RateLimit-Limit` — maximum requests
- `X-RateLimit-Remaining` — remaining requests
- `X-RateLimit-Reset` — reset time in seconds

### Disable Rate Limit for a Key

Simply omit `--rate-limit` option or set `rate_limit` to `null`:
```php
$result = $service->createKey('Unlimited Key'); // No rate limit
```

## Tags

Tags allow you to categorize API keys and restrict access to specific routes based on key category.

### Create Key with Tag

**Via Artisan CLI:**
```bash
# Create key with custom tag
php artisan api-key:create "Admin Service" --tag=admin

# Create key with tag and rate limit
php artisan api-key:create "API Partner" --tag=api --rate-limit=1000
```

**Programmatically:**
```php
use Licorice19\ApiKey\Models\ApiKey;

// Create key with tag
$result = ApiKey::createKey('Admin Service', null, null, null, 'admin');
$key = $result['key'];

// Via service
$service = app(ApiKeyService::class);
$result = $service->createKey('API Partner', null, 100, 60, 'api');
```

### Protect Routes by Tag

Use the `api-key.tag` middleware to restrict access to specific key categories:

```php
// Only keys with "admin" tag can access these routes
Route::middleware(['api-key', 'api-key.tag:admin'])->group(function () {
    Route::get('/admin/stats', [AdminController::class, 'stats']);
    Route::post('/admin/config', [AdminController::class, 'config']);
});

// Keys with "api" OR "internal" tag can access
Route::middleware(['api-key', 'api-key.tag:api,internal'])->group(function () {
    Route::get('/data', [DataController::class, 'index']);
});

// Multiple tags as separate arguments (same as comma-separated)
Route::middleware(['api-key', 'api-key.tag:admin,api'])->group(function () {
    // Accessible by admin OR api keys
});
```

### Tag Middleware Responses

| Scenario | Status | Response |
|----------|--------|----------|
| Key tag matches | 200 | Normal response |
| Key tag doesn't match | 403 | `{"error": "Access denied"}` |
| No API key in request | 401 | `{"error": "Unauthorized"}` |

### Filter Keys by Tag

```bash
# List only keys with specific tag
php artisan api-key:list --tag=admin

# List keys with default tag
php artisan api-key:list --tag=default
```

### Check Key Tag Programmatically

```php
$apiKey = $request->attributes->get('api_key');

// Check single tag
if ($apiKey->hasTag('admin')) {
    // Key has admin tag
}

// Check multiple tags (returns true if ANY matches)
if ($apiKey->hasTag(['admin', 'api'])) {
    // Key has admin OR api tag
}
```

### Default Tag

When creating a key without specifying a tag, the default tag is used:

```bash
# Creates key with "default" tag
php artisan api-key:create "My App"
```

Configure the default tag in `config/api-key.php`:
```php
return [
    'default_tag' => 'default',  // Default tag for new keys
];
```

## Configuration

`config/api-key.php`:
```php
return [
    'header_name' => 'X-API-Key',       // Request header name
    'cache_ttl'   => 300,               // Cache duration in seconds
    'rate_limit'  => null,              // Default rate limit (null = unlimited)
    'rate_limit_period' => 60,          // Default rate limit period in seconds
    'default_tag' => 'default',         // Default tag for new keys
    'last_used_probability' => 100,     // Probability of updating last_used_at (0-100)
];
```

## Programmatic API Reference

### ApiKeyService Methods

```php
use Licorice19\ApiKey\Services\ApiKeyService;

$service = app(ApiKeyService::class);
```

#### Key Creation

```php
// Create key without expiration
$result = $service->createKey('Partner Integration');
$key = $result['key'];       // Plain key (save it — won't be shown again!)
$model = $result['model'];    // ApiKey model instance

// Create key with expiration
$result = $service->createKey('Temporary Access', new \DateTime('2026-12-31'));

// Create key with rate limit
$result = $service->createKey('Limited', null, 100, 60); // 100 req / 60 sec
```

#### Key Validation & Retrieval

```php
// Validate a key
$isValid = $service->validateKey($plainKey);  // Returns bool

// Get key model by plain key
$model = $service->getKeyModel($plainKey);     // Returns ApiKey|null

// Find key by ID
$model = $service->findById($id);              // Returns ApiKey|null

// Get all active keys
$activeKeys = $service->getActiveKeys();       // Returns Collection<ApiKey>

// Get all keys
$allKeys = $service->getAllKeys();             // Returns Collection<ApiKey>
```

#### Key Management by ID

```php
// Revoke (deactivate) by ID
$success = $service->revokeById($id);          // Returns bool

// Activate by ID
$success = $service->activateById($id);        // Returns bool

// Delete by ID
$success = $service->deleteById($id);          // Returns bool
```

#### Request Handling

```php
// Extract key from request
$plainKey = $service->extractKeyFromRequest($request);  // Returns string|null

// Update last used timestamp
$service->touchLastUsed($plainKey);            // Returns void
```

### ApiKey Model Methods

```php
use Licorice19\ApiKey\Models\ApiKey;

// Create key
$result = ApiKey::createKey('Partner Integration', new \DateTime('2026-12-31'));

// Create key with rate limit
$result = ApiKey::createKey('Limited', null, 100, 60);

// Find by hash with caching (internal use — hash is SHA-256 of plain key)
$keyHash = hash('sha256', $plainKey);
$apiKey = ApiKey::findByHashCached($keyHash, $cacheTtl = 300);

// Get active keys
$activeKeys = ApiKey::getActive();

// Instance methods
$apiKey->isValid();           // Check if key is valid (active + not expired)
$apiKey->activate();          // Activate key
$apiKey->revoke();            // Revoke (deactivate) key
$apiKey->touchLastUsed();     // Update last_used_at

// Rate limiting
$apiKey->hasRateLimit();      // Check if rate limit is enabled
$apiKey->getRateLimit();      // Get rate limit value
$apiKey->getRateLimitPeriod();// Get rate limit period
$apiKey->checkRateLimit();    // Check and increment counter
$apiKey->getRateLimitUsage(); // Get current usage
$apiKey->resetRateLimit();    // Reset counter

// Tags
$apiKey->hasTag('admin');     // Check if key has specific tag
$apiKey->hasTag(['admin', 'api']); // Check if key has any of the tags
$apiKey->tag;                 // Get key's tag value
```

---

## Frequently Asked Questions

### Does cache invalidate immediately on key revocation?
Yes. Cache is cleared immediately via `Cache::forget()` when a key is revoked, activated, or deleted. No stale cache entries.

### Which Laravel versions are supported?
Laravel 10.x, 11.x, 12.x, and 13.x are fully supported. PHP 8.1+ is required.

### Is this compatible with Laravel Sanctum?
Yes. These packages serve different purposes:
- **Sanctum**: User authentication (personal access tokens)
- **api-key**: Service-to-service authentication (no user binding)

You can use both in the same application for different use cases.

### What happens when a key expires?
Expired keys return `401 Unauthorized` with `{"error": "Unauthorized"}`. The key remains in the database but is considered invalid.

### How should I rotate keys without downtime?
1. Create a new key: `$service->createKey('New Key')`
2. Distribute the new key to your client
3. Revoke the old key: `$service->revokeById($oldKeyId)`
4. No requests will fail — clients switch seamlessly

### Does updating `last_used_at` create database load?
By default, `touchLastUsed()` writes to the database on every authenticated request. For high-traffic APIs, use probabilistic updates:

```php
// config/api-key.php
'last_used_probability' => 5,  // Update on ~5% of requests
```

Configuration options:
- `100` — Update on every request (default, exact tracking)
- `5` — Update on ~5% of requests (recommended for high-traffic APIs)
- `0` — Disable automatic updates entirely

With `last_used_probability => 5`, you get approximate tracking with 20x less database writes. The `last_used_at` timestamp will be within a few minutes of actual usage — sufficient for auditing and debugging.

### Can I restrict keys by IP or domain?
Not built-in.

### Is rate limiting included?
**Yes!** Each key can have its own rate limit. Use `--rate-limit` option when creating a key.

You can also combine with Laravel's throttle middleware for additional protection:
```php
Route::middleware(['api-key', 'throttle:60,1'])->group(function () {
    // Your routes
});
```

### Does it work with Octane?
Yes. The middleware is stateless and doesn't rely on persistent state between requests.

### Where should clients store API keys?
- **Never** commit keys to version control (`.gitignore` your `.env` files)
- **Development**: Use `.env` files (`API_KEY=your-key-here`)
- **Production**: Use a secrets manager (AWS Secrets Manager, HashiCorp Vault, Azure Key Vault)
- **CI/CD**: Use built-in secrets (GitHub Actions secrets, GitLab CI variables)

---

## Security Considerations

- **Hashing**: Keys are hashed with SHA-256 before storage
- **Plain keys**: Never stored, only shown once at creation
- **No encryption**: Use HTTPS for key transmission
- **No IP/domain restrictions**: Implement in your middleware if needed
- **Rate limiting**: Per-key rate limiting is built-in

---

## Performance

| Operation | Cache Behavior |
|------------|----------------|
| Validate key | Cached for `cache_ttl` seconds |
| Create key | No cache (key returned once — caching pointless) |
| Revoke/Delete | Cache cleared immediately |
| Rate limit | Cached for `rate_limit_period` seconds |

For high-traffic APIs:
- Set `cache_ttl` to 300-3600 seconds
- Consider batching `last_used_at` updates

---

## Features

- Keys are stored as hashes — never in plain text
- Built-in caching for high-throughput APIs
- Optional expiration via `expires_at`
- Per-key rate limiting — each key can have its own limits
- Supports both `X-API-Key` and `Bearer` headers
- Full Artisan CLI for key lifecycle management
- No user binding — designed for service-to-service and B2B use cases

## Use Cases

Perfect for:
- **B2B integrations** — issue keys to partners and external services
  ```bash
  php artisan api-key:create "Acme Corp" --rate-limit=1000
  ```
- **Internal APIs** — authenticate microservices and background workers
  ```php
  Route::middleware('api-key')->post('/internal/jobs', JobController::class);
  ```
- **Webhooks & automation** — secure inbound endpoints without user sessions
  ```php
  Route::middleware('api-key')->post('/webhooks/stripe', WebhookController::class);
  ```

## License

MIT