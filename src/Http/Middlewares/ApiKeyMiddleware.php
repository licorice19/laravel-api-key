<?php

namespace Licorice19\ApiKey\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Licorice19\ApiKey\Services\ApiKeyService;

class ApiKeyMiddleware
{
    protected ApiKeyService $apiKeyService;

    protected string $headerName;
    protected string $authPrefix;
    protected int $cacheTtl;

    public function __construct(ApiKeyService $apiKeyService)
    {
        $this->apiKeyService = $apiKeyService;
        $this->headerName = config('api-key.header_name', 'X-API-Key');
        $this->authPrefix = config('api-key.auth_prefix', 'Bearer');
        $this->cacheTtl = config('api-key.cache_ttl', 300);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->extractApiKey($request);

        if (empty($apiKey)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$this->apiKeyService->validateKey($apiKey)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $keyModel = $this->apiKeyService->getKeyModel($apiKey);

        // Check rate limit
        if ($keyModel->hasRateLimit()) {
            $remaining = $keyModel->checkRateLimit();
            
            if ($remaining < 0) {
                return response()->json([
                    'error' => 'Rate limit exceeded',
                ], 429)->withHeaders([
                    'X-RateLimit-Limit' => $keyModel->getRateLimit(),
                    'X-RateLimit-Remaining' => 0,
                    'X-RateLimit-Reset' => $keyModel->getRateLimitPeriod(),
                ]);
            }
        }

        $this->apiKeyService->touchLastUsed($apiKey);

        $request->attributes->set('api_key', $keyModel);

        $response = $next($request);

        // Add rate limit headers to response
        if ($keyModel->hasRateLimit()) {
            $response->headers->set('X-RateLimit-Limit', $keyModel->getRateLimit());
            $response->headers->set('X-RateLimit-Remaining', max(0, $keyModel->getRateLimit() - $keyModel->getRateLimitUsage()));
        }

        return $response;
    }

    /**
     * Extract the API key from the request.
     *
     * @param Request $request
     * @return string|null
     */
    protected function extractApiKey(Request $request): ?string
    {
        $apiKey = $request->header($this->headerName);

        if (!$apiKey) {
            $authHeader = $request->header('Authorization');
            if ($authHeader && Str::startsWith($authHeader, $this->authPrefix . ' ')) {
                $apiKey = Str::after($authHeader, $this->authPrefix . ' ');
            }
        }

        return $apiKey ? trim($apiKey) : null;
    }
}