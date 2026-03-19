<?php

use Licorice19\ApiKey\Models\ApiKey;
use Licorice19\ApiKey\Services\ApiKeyService;
use Illuminate\Http\Request;

describe('ApiKeyService', function () {
    
    beforeEach(function () {
        $this->service = app(ApiKeyService::class);
    });

    test('can create an API key', function () {
        $result = $this->service->createKey('Test Key');

        expect($result)->toHaveKeys(['key', 'model'])
            ->and($result['model']->name)->toBe('Test Key')
            ->and($result['model']->is_active)->toBeTrue()
            ->and($result['key'])->toBeString()
            ->and(strlen($result['key']))->toBe(64);
    });

    test('can create an API key with an expiration date', function () {
        $expiresAt = new \DateTime('+30 days');
        $result = $this->service->createKey('Test Key', $expiresAt);

        expect($result['model']->expires_at)->not->toBeNull()
            ->and($result['model']->expires_at->format('Y-m-d'))->toBe($expiresAt->format('Y-m-d'));
    });

    test('validateKey returns true for a valid key.', function () {
        $result = $this->service->createKey('Test Key');
        $plainKey = $result['key'];

        expect($this->service->validateKey($plainKey))->toBeTrue();
    });

    test('validateKey returns false for an invalid key.', function () {
        expect($this->service->validateKey('invalid-key'))->toBeFalse();
    });

    test('validateKey returns false for a deactivated key', function () {
        $result = $this->service->createKey('Test Key');
        $plainKey = $result['key'];
        $result['model']->revoke();

        expect($this->service->validateKey($plainKey))->toBeFalse();
    });

    test('validateKey returns false for an expired key.', function () {
        $apiKey = ApiKey::create([
            'key_hash' => hash('sha256', 'test-key'),
            'name' => 'Test Key',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        expect($this->service->validateKey('test-key'))->toBeFalse();
    });

    test('getKeyModel returns the model for a valid key.', function () {
        $result = $this->service->createKey('Test Key');
        $plainKey = $result['key'];

        $model = $this->service->getKeyModel($plainKey);

        expect($model)->toBeInstanceOf(ApiKey::class)
            ->and($model->name)->toBe('Test Key');
    });

    test('getKeyModel returns null for an invalid key', function () {
        $model = $this->service->getKeyModel('invalid-key');

        expect($model)->toBeNull();
    });

    test('revokeById deactivates the key by ID', function () {
        $result = $this->service->createKey('Test Key');
        $keyId = $result['model']->id;

        $this->service->revokeById($keyId);

        expect($result['model']->fresh()->is_active)->toBeFalse();
    });

    test('revokeById returns false for non-existent ID', function () {
        expect($this->service->revokeById('non-existent-id'))->toBeFalse();
    });

    test('activateById activates the key by ID', function () {
        $result = $this->service->createKey('Test Key');
        $keyId = $result['model']->id;
        $result['model']->revoke();

        $this->service->activateById($keyId);

        expect($result['model']->fresh()->is_active)->toBeTrue();
    });

    test('activateById returns false for non-existent ID', function () {
        expect($this->service->activateById('non-existent-id'))->toBeFalse();
    });

    test('deleteById removes the key by ID', function () {
        $result = $this->service->createKey('Test Key');
        $keyId = $result['model']->id;

        $this->service->deleteById($keyId);

        expect(ApiKey::find($keyId))->toBeNull();
    });

    test('deleteById returns false for non-existent ID', function () {
        expect($this->service->deleteById('non-existent-id'))->toBeFalse();
    });

    test('findById returns the key model', function () {
        $result = $this->service->createKey('Test Key');
        $keyId = $result['model']->id;

        $found = $this->service->findById($keyId);

        expect($found)->toBeInstanceOf(ApiKey::class)
            ->and($found->id)->toBe($keyId)
            ->and($found->name)->toBe('Test Key');
    });

    test('findById returns null for non-existent ID', function () {
        expect($this->service->findById('non-existent-id'))->toBeNull();
    });

    test('touchLastUsed updates the time of use', function () {
        $result = $this->service->createKey('Test Key');
        $plainKey = $result['key'];

        $this->service->touchLastUsed($plainKey);

        expect($result['model']->fresh()->last_used_at)->not->toBeNull();
    });

    test('getActiveKeys returns only active keys', function () {
        $this->service->createKey('Active Key 1');
        $this->service->createKey('Active Key 2');
        
        $inactiveResult = $this->service->createKey('Inactive Key');
        $inactiveResult['model']->revoke();

        $activeKeys = $this->service->getActiveKeys();

        expect($activeKeys)->toHaveCount(2);
    });

    test('getAllKeys return all keys', function () {
        $this->service->createKey('Key 1');
        $this->service->createKey('Key 2');
        $result = $this->service->createKey('Key 3');
        $result['model']->revoke();

        $allKeys = $this->service->getAllKeys();

        expect($allKeys)->toHaveCount(3);
    });

    describe('extractKeyFromRequest', function () {
        
        test('extracts the key from the X-API-Key header', function () {
            $request = Request::create('/test', 'GET');
            $request->headers->set('X-API-Key', 'test-api-key');

            $extractedKey = $this->service->extractKeyFromRequest($request);

            expect($extractedKey)->toBe('test-api-key');
        });

        test('extracts the key from the Authorization header', function () {
            $request = Request::create('/test', 'GET');
            $request->headers->set('Authorization', 'Bearer test-api-key');

            $extractedKey = $this->service->extractKeyFromRequest($request);

            expect($extractedKey)->toBe('test-api-key');
        });

        test('returns null if the key is not passed', function () {
            $request = Request::create('/test', 'GET');

            $extractedKey = $this->service->extractKeyFromRequest($request);

            expect($extractedKey)->toBeNull();
        });

        test('X-API-Key priority over Authorization', function () {
            $request = Request::create('/test', 'GET');
            $request->headers->set('X-API-Key', 'header-key');
            $request->headers->set('Authorization', 'Bearer auth-key');

            $extractedKey = $this->service->extractKeyFromRequest($request);

            expect($extractedKey)->toBe('header-key');
        });
    });
});