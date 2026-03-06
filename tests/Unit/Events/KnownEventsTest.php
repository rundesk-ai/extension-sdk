<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Events\KnownEvents;

test('all returns all known event names', function (): void {
    $events = KnownEvents::all();

    expect($events)->toContain('config.updated');
    expect($events)->toContain('resource.updated');
    expect($events)->toContain('system.status_updated');
    expect($events)->toContain('chat.message_sent');
    expect($events)->toContain('extension.executed');
    expect($events)->toHaveCount(5);
});

test('constants match the all() values', function (): void {
    expect(KnownEvents::CONFIG_UPDATED)->toBe('config.updated');
    expect(KnownEvents::RESOURCE_UPDATED)->toBe('resource.updated');
    expect(KnownEvents::SYSTEM_STATUS_UPDATED)->toBe('system.status_updated');
    expect(KnownEvents::CHAT_MESSAGE_SENT)->toBe('chat.message_sent');
    expect(KnownEvents::EXTENSION_EXECUTED)->toBe('extension.executed');
});
