<?php

use Licorice19\ApiKey\Models\ApiKey;
use Licorice19\ApiKey\Http\Middlewares\ApiKeyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

describe('ApiKeyMiddleware', function () {
    
    beforeEach(function () {
        $this->middleware = app(ApiKeyMiddleware::class);
    });

    test('Allows requests with valid API key in header', function () {
        $result = ApiKey::createKey('Test Key');
        $plainKey = $result['key'];

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', $plainKey);

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('OK');
    });

    test('passes a request with a valid API key in Authorization', function () {
        $result = ApiKey::createKey('Test Key');
        $plainKey = $result['key'];

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $plainKey);

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        expect($response->getStatusCode())->toBe(200);
    });

    test('returns 401 without API key', function () {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        expect($response->getStatusCode())->toBe(401);
    });

    test('returns 401 with invalid API key', function () {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', 'invalid-key');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        expect($response->getStatusCode())->toBe(401);
    });

    test('returns 401 with deactivated key', function () {
        $result = ApiKey::createKey('Test Key');
        $plainKey = $result['key'];
        $result['model']->revoke();

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', $plainKey);

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        expect($response->getStatusCode())->toBe(401);
    });

    test('returns 401 with expired key', function () {
        $apiKey = ApiKey::create([
            'key_hash' => hash('sha256', 'expired-key'),
            'name' => 'Expired Key',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', 'expired-key');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        expect($response->getStatusCode())->toBe(401);
    });

    test('updates last_used_at upon successful login', function () {
        $result = ApiKey::createKey('Test Key');
        $plainKey = $result['key'];

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', $plainKey);

        $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        expect($result['model']->fresh()->last_used_at)->not->toBeNull();
    });

    test('adds api_key to request attributes', function () {
        $result = ApiKey::createKey('Test Key');
        $plainKey = $result['key'];

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', $plainKey);

        $capturedRequest = null;
        $this->middleware->handle($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return new Response('OK');
        });

        expect($capturedRequest->attributes->get('api_key'))->toBeInstanceOf(ApiKey::class);
    });

    test('returns a JSON response for API requests', function () {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        expect($response->getStatusCode())->toBe(401)
            ->and($response->headers->get('Content-Type'))->toContain('application/json');
    });

    test('key without rate limit passes requests', function () {
        $result = ApiKey::createKey('No Rate Limit Key');
        $plainKey = $result['key'];

        // Make multiple requests
        for ($i = 0; $i < 10; $i++) {
            $request = Request::create('/test', 'GET');
            $request->headers->set('X-API-Key', $plainKey);

            $response = $this->middleware->handle($request, function ($req) {
                return new Response('OK');
            });

            expect($response->getStatusCode())->toBe(200);
        }
    });

    test('returns 429 when rate limit exceeded', function () {
        $result = ApiKey::createKey('Rate Limited Key', null, 2, 60);
        $plainKey = $result['key'];

        // First request - OK
        $request1 = Request::create('/test', 'GET');
        $request1->headers->set('X-API-Key', $plainKey);
        $response1 = $this->middleware->handle($request1, fn() => new Response('OK'));
        expect($response1->getStatusCode())->toBe(200);

        // Second request - OK
        $request2 = Request::create('/test', 'GET');
        $request2->headers->set('X-API-Key', $plainKey);
        $response2 = $this->middleware->handle($request2, fn() => new Response('OK'));
        expect($response2->getStatusCode())->toBe(200);

        // Third request - 429
        $request3 = Request::create('/test', 'GET');
        $request3->headers->set('X-API-Key', $plainKey);
        $response3 = $this->middleware->handle($request3, fn() => new Response('OK'));
        expect($response3->getStatusCode())->toBe(429)
            ->and($response3->getContent())->toContain('Rate limit exceeded');
    });

    test('adds rate limit headers to response', function () {
        $result = ApiKey::createKey('Rate Limited Key', null, 100, 60);
        $plainKey = $result['key'];

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', $plainKey);

        $response = $this->middleware->handle($request, fn() => new Response('OK'));

        expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue()
            ->and($response->headers->has('X-RateLimit-Remaining'))->toBeTrue()
            ->and($response->headers->get('X-RateLimit-Limit'))->toBe('100');
    });

    test('does not add rate limit headers when no limit set', function () {
        $result = ApiKey::createKey('No Limit Key');
        $plainKey = $result['key'];

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', $plainKey);

        $response = $this->middleware->handle($request, fn() => new Response('OK'));

        expect($response->headers->has('X-RateLimit-Limit'))->toBeFalse();
    });

    test('creates key with default tag', function () {
        $result = ApiKey::createKey('Test Key');
        
        expect($result['model']->tag)->toBe('default');
    });

    test('creates key with custom tag', function () {
        $result = ApiKey::createKey('Admin Key', null, null, null, 'admin');
        
        expect($result['model']->tag)->toBe('admin');
    });
});

describe('ApiKeyTagMiddleware', function () {
    
    beforeEach(function () {
        $this->tagMiddleware = app(\Licorice19\ApiKey\Http\Middlewares\ApiKeyTagMiddleware::class);
        $this->apiKeyMiddleware = app(ApiKeyMiddleware::class);
    });

    test('allows request with matching tag', function () {
        $result = ApiKey::createKey('Admin Key', null, null, null, 'admin');
        $plainKey = $result['key'];

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', $plainKey);

        // First pass through api-key middleware
        $response = $this->apiKeyMiddleware->handle($request, function ($req) {
            // Then pass through tag middleware
            return $this->tagMiddleware->handle($req, fn() => new Response('OK'), 'admin');
        });

        expect($response->getStatusCode())->toBe(200);
    });

    test('allows request with one of multiple allowed tags', function () {
        $result = ApiKey::createKey('API Key', null, null, null, 'api');
        $plainKey = $result['key'];

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', $plainKey);

        $response = $this->apiKeyMiddleware->handle($request, function ($req) {
            return $this->tagMiddleware->handle($req, fn() => new Response('OK'), 'admin', 'api', 'internal');
        });

        expect($response->getStatusCode())->toBe(200);
    });

    test('returns 403 when tag does not match', function () {
        $result = ApiKey::createKey('Default Key', null, null, null, 'default');
        $plainKey = $result['key'];

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', $plainKey);

        $response = $this->apiKeyMiddleware->handle($request, function ($req) {
            return $this->tagMiddleware->handle($req, fn() => new Response('OK'), 'admin');
        });

        expect($response->getStatusCode())->toBe(403)
            ->and($response->getContent())->toContain('Access denied');
    });

    test('returns 401 when api_key not set in request', function () {
        $request = Request::create('/test', 'GET');

        $response = $this->tagMiddleware->handle($request, fn() => new Response('OK'), 'admin');

        expect($response->getStatusCode())->toBe(401)
            ->and($response->getContent())->toContain('Unauthorized');
    });

    test('supports comma-separated tags', function () {
        $result = ApiKey::createKey('Internal Key', null, null, null, 'internal');
        $plainKey = $result['key'];

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-API-Key', $plainKey);

        $response = $this->apiKeyMiddleware->handle($request, function ($req) {
            return $this->tagMiddleware->handle($req, fn() => new Response('OK'), 'admin,api,internal');
        });

        expect($response->getStatusCode())->toBe(200);
    });
});
