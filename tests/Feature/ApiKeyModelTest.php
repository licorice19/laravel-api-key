<?php

use Licorice19\ApiKey\Models\ApiKey;

describe('ApiKey Model', function () {
    
    test('can create an API key', function () {
        $apiKey = ApiKey::create([
            'key_hash' => hash('sha256', 'test-key'),
            'name' => 'Test Key',
            'is_active' => true,
        ]);

        expect($apiKey)->toBeInstanceOf(ApiKey::class)
            ->and($apiKey->name)->toBe('Test Key')
            ->and($apiKey->is_active)->toBeTrue();
    });

    test('The createKey method creates a key with a hash', function () {
        $result = ApiKey::createKey('Test Key');

        expect($result)->toHaveKeys(['key', 'model'])
            ->and($result['model'])->toBeInstanceOf(ApiKey::class)
            ->and($result['model']->name)->toBe('Test Key')
            ->and($result['model']->is_active)->toBeTrue()
            ->and($result['model']->key_hash)->not->toBeEmpty()
            ->and($result['key'])->not->toBeEmpty();
    });

    test('isValid method returns true for the active key.', function () {
        $result = ApiKey::createKey('Test Key');
        $apiKey = $result['model'];

        expect($apiKey->isValid())->toBeTrue();
    });

    test('isValid method returns false for an inactive key.', function () {
        $apiKey = ApiKey::create([
            'key_hash' => hash('sha256', 'test-key'),
            'name' => 'Test Key',
            'is_active' => false,
        ]);

        expect($apiKey->isValid())->toBeFalse();
    });

    test('isValid method returns false for an expired key.', function () {
        $apiKey = ApiKey::create([
            'key_hash' => hash('sha256', 'test-key'),
            'name' => 'Test Key',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        expect($apiKey->isValid())->toBeFalse();
    });

    test('isValid method returns true for a key with a future expiration date.', function () {
        $apiKey = ApiKey::create([
            'key_hash' => hash('sha256', 'test-key'),
            'name' => 'Test Key',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        expect($apiKey->isValid())->toBeTrue();
    });

    test('revoke method deactivates the key', function () {
        $result = ApiKey::createKey('Test Key');
        $apiKey = $result['model'];
        
        expect($apiKey->is_active)->toBeTrue();

        $apiKey->revoke();

        expect($apiKey->fresh()->is_active)->toBeFalse();
    });

    test('activate method activates the key.', function () {
        $apiKey = ApiKey::create([
            'key_hash' => hash('sha256', 'test-key'),
            'name' => 'Test Key',
            'is_active' => false,
        ]);

        expect($apiKey->is_active)->toBeFalse();

        $apiKey->activate();

        expect($apiKey->fresh()->is_active)->toBeTrue();
    });

    test('expires_at attribute is correctly converted to Carbon', function () {
        $expiresAt = now()->addDays(30);
        $apiKey = ApiKey::create([
            'key_hash' => hash('sha256', 'test-key'),
            'name' => 'Test Key',
            'expires_at' => $expiresAt,
        ]);

        expect($apiKey->expires_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
            ->and($apiKey->expires_at->format('Y-m-d H:i:s'))->toBe($expiresAt->format('Y-m-d H:i:s'));
    });

    test('attributes to be filled', function () {
        $apiKey = new ApiKey();
        
        expect($apiKey->getFillable())->toContain('key_hash')
            ->and($apiKey->getFillable())->toContain('name')
            ->and($apiKey->getFillable())->toContain('tag')
            ->and($apiKey->getFillable())->toContain('is_active')
            ->and($apiKey->getFillable())->toContain('expires_at');
    });

    test('findByHashCached method finds a key by hash', function () {
        $result = ApiKey::createKey('Test Key');
        $keyHash = $result['model']->key_hash;

        $found = ApiKey::findByHashCached($keyHash);

        expect($found)->toBeInstanceOf(ApiKey::class)
            ->and($found->key_hash)->toBe($keyHash);
    });

    test('getActive method returns only active keys.', function () {
        ApiKey::create([
            'key_hash' => hash('sha256', 'active-key'),
            'name' => 'Active Key',
            'is_active' => true,
        ]);

        ApiKey::create([
            'key_hash' => hash('sha256', 'inactive-key'),
            'name' => 'Inactive Key',
            'is_active' => false,
        ]);

        $activeKeys = ApiKey::getActive();

        expect($activeKeys)->toHaveCount(1)
            ->and($activeKeys->first()->name)->toBe('Active Key');
    });

    test('createKey assigns default tag when not specified', function () {
        $result = ApiKey::createKey('Test Key');

        expect($result['model']->tag)->toBe('default');
    });

    test('createKey assigns custom tag', function () {
        $result = ApiKey::createKey('Admin Key', null, null, null, 'admin');

        expect($result['model']->tag)->toBe('admin');
    });

    test('hasTag returns true for matching tag', function () {
        $result = ApiKey::createKey('Admin Key', null, null, null, 'admin');

        expect($result['model']->hasTag('admin'))->toBeTrue();
    });

    test('hasTag returns false for non-matching tag', function () {
        $result = ApiKey::createKey('Default Key');

        expect($result['model']->hasTag('admin'))->toBeFalse();
    });

    test('hasTag accepts array of tags', function () {
        $result = ApiKey::createKey('API Key', null, null, null, 'api');

        expect($result['model']->hasTag(['admin', 'api', 'internal']))->toBeTrue()
            ->and($result['model']->hasTag(['admin', 'internal']))->toBeFalse();
    });
});
