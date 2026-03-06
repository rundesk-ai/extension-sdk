<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Events\KnownEvents;
use Rundesk\Extension\Sdk\Manifest\ManifestReader;
use Rundesk\Extension\Sdk\Manifest\ManifestValidator;

function makeManifest(array $overrides = []): ManifestReader
{
    $data = array_merge([
        'id' => 'test-extension',
        'name' => 'Test Extension',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => 'SKILL.md',
    ], $overrides);

    return new ManifestReader($data);
}

test('valid manifest passes validation', function (): void {
    $validator = new ManifestValidator;
    $manifest = makeManifest();

    expect($validator->validate($manifest))->toBeTrue();
    expect($validator->errors())->toBe([]);
});

test('missing required fields are flagged', function (): void {
    $validator = new ManifestValidator;
    $manifest = makeManifest(['id' => '', 'name' => '']);

    $validator->validate($manifest);

    expect($validator->errors())->toContain('Missing required field: id');
    expect($validator->errors())->toContain('Missing required field: name');
});

test('invalid id format is flagged', function (): void {
    $validator = new ManifestValidator;
    $manifest = makeManifest(['id' => 'invalid id!']);

    $validator->validate($manifest);

    expect($validator->errors())->toContain('Invalid extension id format: invalid id!');
});

test('unknown hook events are flagged', function (): void {
    $validator = new ManifestValidator;
    $manifest = makeManifest([
        'hooks' => [
            ['event' => 'fake.event', 'method' => 'handleFake'],
        ],
    ]);

    $validator->validate($manifest);

    expect($validator->errors())->toContain('Hook #0 has unknown event: fake.event');
});

test('valid hook events pass', function (): void {
    $validator = new ManifestValidator;
    $manifest = makeManifest([
        'hooks' => [
            ['event' => KnownEvents::CONFIG_UPDATED, 'method' => 'handleConfig'],
        ],
    ]);

    expect($validator->validate($manifest))->toBeTrue();
});

test('hooks missing method are flagged', function (): void {
    $validator = new ManifestValidator;
    $manifest = makeManifest([
        'hooks' => [
            ['event' => KnownEvents::CONFIG_UPDATED, 'method' => ''],
        ],
    ]);

    $validator->validate($manifest);

    expect($validator->errors())->toContain("Hook #0 missing required 'method' field");
});

test('invalid cron expression is flagged', function (): void {
    $validator = new ManifestValidator;
    $manifest = makeManifest([
        'schedules' => [
            ['method' => 'sync', 'cron' => 'bad-cron'],
        ],
    ]);

    $validator->validate($manifest);

    expect($validator->errors())->toContain('Schedule #0 has invalid cron expression: bad-cron');
});

test('valid cron expression passes', function (): void {
    $validator = new ManifestValidator;
    $manifest = makeManifest([
        'schedules' => [
            ['method' => 'sync', 'cron' => '0 * * * *'],
        ],
    ]);

    expect($validator->validate($manifest))->toBeTrue();
});
