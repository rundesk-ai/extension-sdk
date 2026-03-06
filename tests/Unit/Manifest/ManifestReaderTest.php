<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Manifest\ManifestReader;

test('it reads all required fields', function (): void {
    $reader = new ManifestReader([
        'id' => 'my-ext',
        'name' => 'My Extension',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => 'SKILL.md',
    ]);

    expect($reader->id())->toBe('my-ext');
    expect($reader->name())->toBe('My Extension');
    expect($reader->version())->toBe('1.0.0');
    expect($reader->entry())->toBe('src/Extension.php');
    expect($reader->skillGuide())->toBe('SKILL.md');
});

test('missing fields return empty strings', function (): void {
    $reader = new ManifestReader([]);

    expect($reader->id())->toBe('');
    expect($reader->name())->toBe('');
    expect($reader->version())->toBe('');
    expect($reader->entry())->toBe('');
    expect($reader->skillGuide())->toBe('');
});

test('it reads optional description', function (): void {
    $with = new ManifestReader(['description' => 'A great extension']);
    $without = new ManifestReader([]);

    expect($with->description())->toBe('A great extension');
    expect($without->description())->toBeNull();
});

test('it reads author info', function (): void {
    $reader = new ManifestReader([
        'author' => ['name' => 'Tim', 'email' => 'tim@example.com'],
    ]);

    expect($reader->author())->toBe(['name' => 'Tim', 'email' => 'tim@example.com']);
});

test('author returns null when not an array', function (): void {
    $reader = new ManifestReader(['author' => 'just-a-string']);

    expect($reader->author())->toBeNull();
});

test('repository falls back to author url', function (): void {
    $reader = new ManifestReader([
        'author' => ['url' => 'https://github.com/test'],
    ]);

    expect($reader->repository())->toBe('https://github.com/test');
});

test('repository uses explicit repository field', function (): void {
    $reader = new ManifestReader([
        'repository' => 'https://github.com/explicit',
    ]);

    expect($reader->repository())->toBe('https://github.com/explicit');
});

test('repository returns null when not set', function (): void {
    expect((new ManifestReader([]))->repository())->toBeNull();
});

test('it reads database config', function (): void {
    $reader = new ManifestReader([
        'database' => ['enabled' => true, 'migrations' => 'migrations/'],
    ]);

    expect($reader->dbEnabled())->toBeTrue();
    expect($reader->migrations())->toBe('migrations/');
});

test('database defaults to disabled', function (): void {
    $reader = new ManifestReader([]);

    expect($reader->dbEnabled())->toBeFalse();
    expect($reader->migrations())->toBeNull();
});

test('it reads credentials', function (): void {
    $reader = new ManifestReader([
        'credentials' => ['api_key' => ['type' => 'api_key']],
    ]);

    expect($reader->credentials())->toBe(['api_key' => ['type' => 'api_key']]);
});

test('it reads allowed hosts', function (): void {
    $reader = new ManifestReader([
        'network' => ['allowed_hosts' => ['api.example.com']],
    ]);

    expect($reader->allowedHosts())->toBe(['api.example.com']);
});

test('it reads schedules', function (): void {
    $reader = new ManifestReader([
        'schedules' => [['method' => 'sync', 'cron' => '0 * * * *']],
    ]);

    expect($reader->schedules())->toHaveCount(1);
});

test('it reads hooks', function (): void {
    $reader = new ManifestReader([
        'hooks' => [['event' => 'config.updated', 'method' => 'onConfig']],
    ]);

    expect($reader->hooks())->toHaveCount(1);
});

test('it reads config', function (): void {
    $reader = new ManifestReader([
        'config' => ['sync_days' => 30],
    ]);

    expect($reader->config())->toBe(['sync_days' => 30]);
});

test('vendors defaults to vendor/autoload.php', function (): void {
    $reader = new ManifestReader([]);

    expect($reader->vendors())->toBe(['vendor/autoload.php']);
});

test('it reads custom vendors', function (): void {
    $reader = new ManifestReader([
        'vendors' => ['vendor/autoload.php', 'lib/autoload.php'],
    ]);

    expect($reader->vendors())->toBe(['vendor/autoload.php', 'lib/autoload.php']);
});

test('it reads provider', function (): void {
    $with = new ManifestReader(['provider' => 'App\\MyProvider']);
    $without = new ManifestReader([]);

    expect($with->provider())->toBe('App\\MyProvider');
    expect($without->provider())->toBeNull();
});

test('toArray returns raw data', function (): void {
    $data = ['id' => 'test', 'name' => 'Test'];
    $reader = new ManifestReader($data);

    expect($reader->toArray())->toBe($data);
});

test('fromPath reads rundesk.json', function (): void {
    $dir = sys_get_temp_dir().'/rundesk-test-manifest-'.uniqid();
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/rundesk.json', json_encode([
        'id' => 'from-file',
        'name' => 'From File',
    ]));

    $reader = ManifestReader::fromPath($dir);

    expect($reader->id())->toBe('from-file');
    expect($reader->name())->toBe('From File');

    unlink($dir.'/rundesk.json');
    rmdir($dir);
});

test('fromPath throws when file missing', function (): void {
    expect(fn () => ManifestReader::fromPath('/nonexistent/path'))
        ->toThrow(\RuntimeException::class, 'No rundesk.json');
});

test('fromPath throws for invalid JSON', function (): void {
    $dir = sys_get_temp_dir().'/rundesk-test-bad-json-'.uniqid();
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/rundesk.json', 'not json');

    expect(fn () => ManifestReader::fromPath($dir))
        ->toThrow(\RuntimeException::class, 'not valid JSON');

    unlink($dir.'/rundesk.json');
    rmdir($dir);
});
