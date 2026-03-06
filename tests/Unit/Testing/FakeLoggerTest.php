<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Testing\FakeLogger;

test('it stores log entries', function (): void {
    $logger = new FakeLogger;

    $logger->info('Test message');
    $logger->error('Error message');
    $logger->debug('Debug message');
    $logger->warning('Warning message');
    $logger->critical('Critical message');

    expect($logger->entries)->toHaveCount(5);
    expect($logger->entries[0])->toBe(['level' => 'INFO', 'message' => 'Test message']);
    expect($logger->entries[1])->toBe(['level' => 'ERROR', 'message' => 'Error message']);
});

test('assertLogged passes for matching entry', function (): void {
    $logger = new FakeLogger;
    $logger->info('User signed in successfully');

    $logger->assertLogged('INFO', 'signed in');

    expect(true)->toBeTrue();
});

test('assertLogged fails for missing entry', function (): void {
    $logger = new FakeLogger;
    $logger->info('Something else');

    expect(fn () => $logger->assertLogged('ERROR', 'not here'))
        ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
});
