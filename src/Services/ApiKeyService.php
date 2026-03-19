<?php

namespace Licorice19\ApiKey\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Licorice19\ApiKey\Models\ApiKey;

class ApiKeyService
{
    /**
     * Model for working with API keys.
     */
    protected string $model;

    /**
     * Cache lifetime in seconds
     */
    protected int $cacheTtl;

    /**
     * Header name for the API key.
     */
    protected string $headerName;

    /**
     * Prefix for the Authorization header.
     */
    protected string $authPrefix;

    public function __construct()
    {
        $this->model = config('api-key.model', ApiKey::class);
        $this->cacheTtl = config('api-key.cache_ttl', 300);
        $this->headerName = config('api-key.header_name', 'X-API-Key');
        $this->authPrefix = config('api-key.auth_prefix', 'Bearer');
    }

    /**
     * Create a new API key.
     *
     * @param string $name key name
     * @param \DateTime|null $expiresAt expiry date
     * @param int|null $rateLimit request rate limit
     * @param int|null $rateLimitPeriod period in seconds
     * @param string $tag Access control tag
     * @return array{key: string, model: mixed} Generated key and model
     */
    public function createKey(string $name, ?\DateTime $expiresAt = null, ?int $rateLimit = null, ?int $rateLimitPeriod = null, string $tag = 'default'): array
    {
        $plainKey = $this->generateKey();
        $keyHash = $this->hashKey($plainKey);

        $model = $this->model::create([
            'key_hash' => $keyHash,
            'name' => $name,
            'tag' => $tag,
            'is_active' => true,
            'expires_at' => $expiresAt,
            'rate_limit' => $rateLimit,
            'rate_limit_period' => $rateLimitPeriod,
        ]);

        return [
            'key' => $plainKey,
            'model' => $model,
        ];
    }

    /**
     * Check the validity of the API key.
     *
     * @param string $apiKey API key for verification
     * @return bool
     */
    public function validateKey(string $apiKey): bool
    {
        $keyHash = $this->hashKey($apiKey);

        $apiKeyRecord = $this->getKeyFromCache($keyHash);

        if (!$apiKeyRecord || !$apiKeyRecord->is_active) {
            return false;
        }

        if ($apiKeyRecord->expires_at && now()->gt($apiKeyRecord->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Get the API model key by key value.
     *
     * @param string $apiKey API key
     * @return mixed|null
     */
    public function getKeyModel(string $apiKey): mixed
    {
        $keyHash = $this->hashKey($apiKey);

        return $this->getKeyFromCache($keyHash);
    }

    /**
     * Revoke API key by ID.
     *
     * @param string $id ID key
     * @return bool
     */
    public function revokeById(string $id): bool
    {
        $apiKeyRecord = $this->model::find($id);

        if (!$apiKeyRecord) {
            return false;
        }

        $apiKeyRecord->update(['is_active' => false]);
        Cache::forget("api_key_{$apiKeyRecord->key_hash}");

        return true;
    }

    /**
     * Activate API key by ID
     *
     * @param string $id ID key
     * @return bool
     */
    public function activateById(string $id): bool
    {
        $apiKeyRecord = $this->model::find($id);

        if (!$apiKeyRecord) {
            return false;
        }

        $apiKeyRecord->update(['is_active' => true]);
        Cache::forget("api_key_{$apiKeyRecord->key_hash}");

        return true;
    }

    /**
     * Delete API key by id
     *
     * @param string $id ID key
     * @return bool
     */
    public function deleteById(string $id): bool
    {
        $apiKeyRecord = $this->model::find($id);

        if (!$apiKeyRecord) {
            return false;
        }

        $keyHash = $apiKeyRecord->key_hash;
        $apiKeyRecord->delete();
        Cache::forget("api_key_{$keyHash}");

        return true;
    }

    /**
     * Find API key by ID.
     *
     * @param string $id ID key
     * @return mixed|null
     */
    public function findById(string $id): mixed
    {
        return $this->model::find($id);
    }

    /**
     * Update the last used time of the API key.
     *
     * @param string $apiKey API key
     * @return void
     */
    public function touchLastUsed(string $apiKey): void
    {
        $keyHash = $this->hashKey($apiKey);
        $this->model::where('key_hash', $keyHash)->update(['last_used_at' => now()]);
    }

    /**
     * Get all active API keys.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveKeys(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->model::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();
    }

    /**
     * Get all API keys.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllKeys(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->model::all();
    }

    /**
     * Extract API key from request.
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    public function extractKeyFromRequest(\Illuminate\Http\Request $request): ?string
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

    /**
     * Generate a new API key.
     *
     * @return string
     */
    protected function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Hash an API key.
     *
     * @param string $key
     * @return string
     */
    protected function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Get API key from cache or database.
     *
     * @param string $keyHash Key hash
     * @return mixed|null
     */
    protected function getKeyFromCache(string $keyHash): mixed
    {
        return Cache::remember("api_key_{$keyHash}", $this->cacheTtl, function () use ($keyHash) {
            return $this->model::where('key_hash', $keyHash)->first();
        });
    }
}