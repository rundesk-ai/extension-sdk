<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Abstract\BaseExtension;
use Rundesk\Extension\Sdk\Contracts\ExtensionContext;
use Rundesk\Extension\Sdk\Results\ExtensionResult;
use Rundesk\Extension\Sdk\Results\RegisterResult;
use Rundesk\Extension\Sdk\Testing\FakeContext;

function createTestExtension(): BaseExtension
{
    return new class extends BaseExtension
    {
        /** @param array<string, mixed> $input */
        public function handleListEvents(array $input, ExtensionContext $context): ExtensionResult
        {
            return ExtensionResult::ok(['events' => ['standup', 'retro']]);
        }

        /** @param array<string, mixed> $input */
        public function dryRunListEvents(array $input): ExtensionResult
        {
            return ExtensionResult::sample(['events' => ['sample_event']]);
        }
    };
}

test('register returns ok by default', function (): void {
    $ext = createTestExtension();
    $context = new FakeContext('test-ext');

    $result = $ext->register($context);

    expect($result)->toBeInstanceOf(RegisterResult::class);
    expect($result->passed)->toBeTrue();
});

test('execute dispatches to handler method', function (): void {
    $ext = createTestExtension();
    $context = new FakeContext('test-ext');

    $result = $ext->execute('list_events', [], $context);

    expect($result->success)->toBeTrue();
    expect($result->data)->toBe(['events' => ['standup', 'retro']]);
});

test('execute returns failed for unknown method', function (): void {
    $ext = createTestExtension();
    $context = new FakeContext('test-ext');

    $result = $ext->execute('unknown_method', [], $context);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('Unknown method');
});

test('dryRun dispatches to dryRun handler', function (): void {
    $ext = createTestExtension();

    $result = $ext->dryRun('list_events', []);

    expect($result->success)->toBeTrue();
    expect($result->isDryRun)->toBeTrue();
    expect($result->data)->toBe(['events' => ['sample_event']]);
});

test('dryRun falls back to empty sample', function (): void {
    $ext = createTestExtension();

    $result = $ext->dryRun('unknown_method', []);

    expect($result->success)->toBeTrue();
    expect($result->isDryRun)->toBeTrue();
    expect($result->data)->toBe([]);
});
