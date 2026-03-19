<?php

namespace Licorice19\ApiKey\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'key_hash',
        'name',
        'tag',
        'is_active',
        'expires_at',
        'rate_limit',
        'rate_limit_period',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        static::updated(function ($apiKey) {
            Cache::forget("api_key_{$apiKey->key_hash}");
        });

        static::deleted(function ($apiKey) {
            Cache::forget("api_key_{$apiKey->key_hash}");
        });
    }


    /**
     * Generate a new API key.
     *
     * @return string
     */
    public static function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Hash the API key.
     *
     * @param string $key
     * @return string
     */
    public static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Create a new API key.
     *
     * @param string $name
     * @param \DateTime|null $expiresAt
     * @param int|null $rateLimit
     * @param int|null $rateLimitPeriod
     * @param string $tag
     * @return array{key: string, model: self}
     */
    public static function createKey(string $name, ?\DateTime $expiresAt = null, ?int $rateLimit = null, ?int $rateLimitPeriod = null, string $tag = 'default'): array
    {
        $plainKey = self::generateKey();
        $keyHash = self::hashKey($plainKey);

        $model = self::create([
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
     * Check if key has specified tag(s).
     *
     * @param string|array $tags
     * @return bool
     */
    public function hasTag(string|array $tags): bool
    {
        $tags = is_array($tags) ? $tags : [$tags];
        
        return in_array($this->tag, $tags, true);
    }

    /**
     * Check the validity of the key.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Activate the key.
     *
     * @return bool
     */
    public function activate(): bool
    {
        $this->is_active = true;
        $this->save();

        Cache::forget("api_key_{$this->key_hash}");

        return true;
    }

    /**
     * Revoke key.
     *
     * @return bool
     */
    public function revoke(): bool
    {
        $this->is_active = false;
        $this->save();

        Cache::forget("api_key_{$this->key_hash}");

        return true;
    }

    /**
     * Update last used time.
     *
     * Uses probabilistic update to reduce database load.
     * Configure probability via config('api-key.last_used_probability').
     *
     * @return void
     */
    public function touchLastUsed(): void
    {
        $probability = config('api-key.last_used_probability', 100);

        if ($probability <= 0) {
            return;
        }

        if ($probability >= 100 || random_int(1, 100) <= $probability) {
            $this->update(['last_used_at' => now()]);
        }
    }

    /**
     * Find a key by hash with caching.
     *
     * @param string $keyHash
     * @param int $cacheTtl
     * @return self|null
     */
    public static function findByHashCached(string $keyHash, int $cacheTtl = 300): ?self
    {
        return Cache::remember("api_key_{$keyHash}", $cacheTtl, function () use ($keyHash) {
            return self::where('key_hash', $keyHash)->first();
        });
    }

    /**
     * Get only active keys.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getActive(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();
    }

    /**
     * Get rate limit for this key (from key or config default).
     *
     * @return int|null
     */
    public function getRateLimit(): ?int
    {
        return $this->rate_limit ?? config('api-key.rate_limit');
    }

    /**
     * Get rate limit period for this key (from key or config default).
     *
     * @return int
     */
    public function getRateLimitPeriod(): int
    {
        return $this->rate_limit_period ?? config('api-key.rate_limit_period', 60);
    }

    /**
     * Check if rate limiting is enabled for this key.
     *
     * @return bool
     */
    public function hasRateLimit(): bool
    {
        return $this->getRateLimit() !== null;
    }

    /**
     * Check and increment rate limit counter.
     * Returns remaining requests or -1 if limit exceeded.
     *
     * @return int
     */
    public function checkRateLimit(): int
    {
        $limit = $this->getRateLimit();
        
        if ($limit === null) {
            return PHP_INT_MAX; // No limit
        }

        $key = "api_key_rate:{$this->key_hash}";
        $period = $this->getRateLimitPeriod();

        $current = Cache::get($key, 0);

        if ($current >= $limit) {
            return -1;
        }

        // Increment or initialize counter
        if ($current === 0) {
            Cache::put($key, 1, $period);
            return $limit - 1;
        }

        Cache::increment($key);
        return $limit - $current - 1;
    }

    /**
     * Get current rate limit usage.
     *
     * @return int
     */
    public function getRateLimitUsage(): int
    {
        return Cache::get("api_key_rate:{$this->key_hash}", 0);
    }

    /**
     * Reset rate limit counter.
     *
     * @return void
     */
    public function resetRateLimit(): void
    {
        Cache::forget("api_key_rate:{$this->key_hash}");
    }
}
