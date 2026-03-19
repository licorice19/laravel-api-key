<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Key Model
    |--------------------------------------------------------------------------
    |
    | Specify the model for working with API keys. By default, the
    | model from the Licorice19\ApiKey\Models\ApiKey package is used.
    | You can specify your own model for customization.
    |
    */
    'model' => \Licorice19\ApiKey\Models\ApiKey::class,

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Cache lifetime for API keys in seconds.
    |
    */
    'cache_ttl' => 300,

    /*
    |--------------------------------------------------------------------------
    | API Key Header
    |--------------------------------------------------------------------------
    |
    | The header name to pass the API key to.
    |
    */
    'header_name' => 'X-API-Key',

    /*
    |--------------------------------------------------------------------------
    | Authorization Header Prefix
    |--------------------------------------------------------------------------
    |
    | The header name to pass the API key to.
    |
    */
    'auth_prefix' => 'Bearer',

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Default rate limiting settings for API keys.
    | If rate_limit = null, rate limiting is disabled by default.
    | rate_limit_period is specified in seconds.
    |
    */
    'rate_limit' => null,
    'rate_limit_period' => 60,

    /*
    |--------------------------------------------------------------------------
    | Default Tag
    |--------------------------------------------------------------------------
    |
    | Default tag for new API keys.
    | Used to separate access to routes.
    |
    */
    'default_tag' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Last Used Update Probability
    |--------------------------------------------------------------------------
    |
    | The probability of updating last_used_at on each request (as a percentage).
    | Reduces database load during high traffic.
    |
    | Examples:
    | - 100: Update on each request (default behavior)
    | - 5: Update only ~5% of requests
    | - 0: Disable automatic updates completely
    |
    */
    'last_used_probability' => 100,
];
