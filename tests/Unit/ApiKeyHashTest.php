<?php

use Licorice19\ApiKey\Models\ApiKey;

describe('ApiKey Hash Unit Tests', function () {
    
    test('hashKey creates the same hash for the same key', function () {
        $key = 'my-secret-key';
        $hash1 = ApiKey::hashKey($key);
        $hash2 = ApiKey::hashKey($key);

        expect($hash1)->toBe($hash2);
    });

    test('hashKey creates different hashes for different keys', function () {
        $hash1 = ApiKey::hashKey('key-1');
        $hash2 = ApiKey::hashKey('key-2');

        expect($hash1)->not->toBe($hash2);
    });

    test('hashKey returns a 64-character string (SHA-256)', function () {
        $hash = ApiKey::hashKey('test-key');

        expect(strlen($hash))->toBe(64);
    });

    test('hashKey returns a hexadecimal string', function () {
        $hash = ApiKey::hashKey('test-key');

        expect($hash)->toMatch('/^[a-f0-9]{64}$/');
    });

    test('generateKey returns unique keys', function () {
        $key1 = ApiKey::generateKey();
        $key2 = ApiKey::generateKey();

        expect($key1)->not->toBe($key2);
    });

    test('generateKey returns a 64 character string', function () {
        $key = ApiKey::generateKey();

        expect(strlen($key))->toBe(64);
    });

    test('generateKey returns a hexadecimal string', function () {
        $key = ApiKey::generateKey();

        expect($key)->toMatch('/^[a-f0-9]{64}$/');
    });

    test('an empty key produces a valid hash', function () {
        $hash = ApiKey::hashKey('');

        expect(strlen($hash))->toBe(64)
            ->and($hash)->toMatch('/^[a-f0-9]{64}$/');
    });

    test('a long key creates a valid hash', function () {
        $longKey = str_repeat('a', 1000);
        $hash = ApiKey::hashKey($longKey);

        expect(strlen($hash))->toBe(64)
            ->and($hash)->toMatch('/^[a-f0-9]{64}$/');
    });

    test('a key with Unicode characters creates a valid hash', function () {
        $unicodeKey = 'key-key';
        $hash = ApiKey::hashKey($unicodeKey);

        expect(strlen($hash))->toBe(64)
            ->and($hash)->toMatch('/^[a-f0-9]{64}$/');
    });
});