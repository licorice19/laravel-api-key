<?php

namespace Licorice19\ApiKey\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyTagMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$tags  Allowed tags (comma-separated or multiple arguments)
     */
    public function handle(Request $request, Closure $next, string ...$tags): Response
    {
        $apiKey = $request->attributes->get('api_key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'Unauthorized',
            ], 401);
        }

        // Flatten comma-separated tags
        $allowedTags = [];
        foreach ($tags as $tag) {
            $allowedTags = array_merge($allowedTags, array_map('trim', explode(',', $tag)));
        }

        if (empty($allowedTags)) {
            // Log configuration error but return generic message
            logger()->error('ApiKeyTagMiddleware: No tags specified for access control');
            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }

        if (!$apiKey->hasTag($allowedTags)) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        return $next($request);
    }
}