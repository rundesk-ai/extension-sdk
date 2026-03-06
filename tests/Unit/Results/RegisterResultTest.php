<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Results\RegisterResult;

test('ok returns a passed result', function (): void {
    $result = RegisterResult::ok();

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBeNull();
});

test('failed returns a failed result with reason', function (): void {
    $result = RegisterResult::failed('API not reachable');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('API not reachable');
});
