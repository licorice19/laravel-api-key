<?php

use Licorice19\ApiKey\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Pest is built on top of PHPUnit, so you can use all the features provided by it.
|
*/

pest()->extend(TestCase::class);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| Here you can configure custom expectations for your tests.
|
*/

expect()->extend('toBeActive', function () {
    return $this->toBeTrue();
});

expect()->extend('toBeInactive', function () {
    return $this->toBeFalse();
});