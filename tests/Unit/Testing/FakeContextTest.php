<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Testing\FakeContext;
use Rundesk\Extension\Sdk\Testing\FakeLogger;

test('it returns the extension id', function (): void {
    $context = new FakeContext('my-extension');

    expect($context->extensionId())->toBe('my-extension');
});

test('it returns credentials', function (): void {
    $context = new FakeContext('ext', credentials: ['api_key' => 'secret-123']);

    expect($context->credential('api_key'))->toBe('secret-123');
});

test('it throws for missing credentials', function (): void {
    $context = new FakeContext('ext');

    expect(fn () => $context->credential('missing'))
        ->toThrow(\RuntimeException::class);
});

test('it returns config values', function (): void {
    $context = new FakeContext('ext', config: ['sync_days' => 30]);

    expect($context->config('sync_days'))->toBe(30);
    expect($context->config('missing', 'default'))->toBe('default');
    expect($context->config())->toBe(['sync_days' => 30]);
});

test('it returns a FakeLogger', function (): void {
    $context = new FakeContext('ext');

    expect($context->log())->toBeInstanceOf(FakeLogger::class);
});

test('it returns a storage path', function (): void {
    $context = new FakeContext('ext');

    expect($context->storagePath())->toContain('rundesk-test-ext');
    expect($context->storagePath('data/file.json'))->toContain('rundesk-test-ext/data/file.json');
});

test('accounts returns empty array', function (): void {
    $context = new FakeContext('ext');

    expect($context->accounts('any_key'))->toBe([]);
});

test('db throws LogicException when connection not configured', function (): void {
    $context = new FakeContext('unconfigured-ext');

    expect(fn () => $context->db())
        ->toThrow(\LogicException::class, 'not configured');
});

test('logger returns same FakeLogger instance', function (): void {
    $context = new FakeContext('ext');

    $logger = $context->logger();

    expect($logger)->toBeInstanceOf(FakeLogger::class);
    expect($context->logger())->toBe($logger);
});
