<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Results\ExtensionResult;

test('ok returns a successful result with data', function (): void {
    $result = ExtensionResult::ok(['events' => [1, 2, 3]]);

    expect($result->success)->toBeTrue();
    expect($result->data)->toBe(['events' => [1, 2, 3]]);
    expect($result->error)->toBeNull();
    expect($result->isDryRun)->toBeFalse();
});

test('ok with empty data returns success', function (): void {
    $result = ExtensionResult::ok();

    expect($result->success)->toBeTrue();
    expect($result->data)->toBe([]);
});

test('failed returns an error result', function (): void {
    $result = ExtensionResult::failed('Connection timeout');

    expect($result->success)->toBeFalse();
    expect($result->error)->toBe('Connection timeout');
    expect($result->data)->toBe([]);
});

test('sample returns a dry run result', function (): void {
    $result = ExtensionResult::sample(['example' => true]);

    expect($result->success)->toBeTrue();
    expect($result->isDryRun)->toBeTrue();
    expect($result->data)->toBe(['example' => true]);
});

test('toArray serializes all fields', function (): void {
    $result = ExtensionResult::ok(['key' => 'value']);

    expect($result->toArray())->toBe([
        'success' => true,
        'data' => ['key' => 'value'],
        'error' => null,
        'isDryRun' => false,
    ]);
});
