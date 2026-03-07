<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Console\NewCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

function newCommandTester(): CommandTester
{
    $app = new Application;
    $app->addCommand(new NewCommand);

    return new CommandTester($app->find('new'));
}

function cleanupDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

test('it scaffolds a new extension', function (): void {
    $outputDir = sys_get_temp_dir().'/rundesk-test-new-'.uniqid();
    mkdir($outputDir, 0755, true);

    $tester = newCommandTester();
    $tester->execute([
        'id' => 'my-test-ext',
        '--name' => 'My Test Extension',
        '--output' => $outputDir,
    ]);

    $targetDir = $outputDir.'/my-test-ext';

    expect($tester->getStatusCode())->toBe(0);
    expect(is_dir($targetDir))->toBeTrue();
    expect(file_exists($targetDir.'/rundesk.json'))->toBeTrue();
    expect(file_exists($targetDir.'/src/Extension.php'))->toBeTrue();
    expect(file_exists($targetDir.'/SKILL.md'))->toBeTrue();

    $manifest = json_decode(file_get_contents($targetDir.'/rundesk.json'), true);
    expect($manifest['id'])->toBe('my-test-ext');
    expect($manifest['name'])->toBe('My Test Extension');
    expect($manifest['credentials'])->toBe([]);

    cleanupDir($outputDir);
});

test('it scaffolds with --oauth flag', function (): void {
    $outputDir = sys_get_temp_dir().'/rundesk-test-oauth-'.uniqid();
    mkdir($outputDir, 0755, true);

    $tester = newCommandTester();
    $tester->execute([
        'id' => 'oauth-ext',
        '--name' => 'OAuth Extension',
        '--oauth' => true,
        '--output' => $outputDir,
    ]);

    $targetDir = $outputDir.'/oauth-ext';
    $manifest = json_decode(file_get_contents($targetDir.'/rundesk.json'), true);

    expect($manifest['credentials'])->toHaveKey('oauth_token');
    expect($manifest['credentials']['oauth_token']['type'])->toBe('oauth');
    expect($manifest['credentials']['oauth_token']['label'])->toBe('OAuth Extension');
    expect($manifest['credentials']['oauth_token']['required'])->toBeTrue();

    cleanupDir($outputDir);
});

test('it scaffolds with --database flag', function (): void {
    $outputDir = sys_get_temp_dir().'/rundesk-test-db-'.uniqid();
    mkdir($outputDir, 0755, true);

    $tester = newCommandTester();
    $tester->execute([
        'id' => 'db-ext',
        '--database' => true,
        '--output' => $outputDir,
    ]);

    $targetDir = $outputDir.'/db-ext';
    $manifest = json_decode(file_get_contents($targetDir.'/rundesk.json'), true);

    expect($manifest['database']['enabled'])->toBeTrue();
    expect(is_dir($targetDir.'/migrations'))->toBeTrue();

    cleanupDir($outputDir);
});

test('it scaffolds minimal extension', function (): void {
    $outputDir = sys_get_temp_dir().'/rundesk-test-min-'.uniqid();
    mkdir($outputDir, 0755, true);

    $tester = newCommandTester();
    $tester->execute([
        'id' => 'min-ext',
        '--minimal' => true,
        '--output' => $outputDir,
    ]);

    $targetDir = $outputDir.'/min-ext';

    expect($tester->getStatusCode())->toBe(0);
    expect(file_exists($targetDir.'/rundesk.json'))->toBeTrue();

    cleanupDir($outputDir);
});

test('it fails when directory already exists', function (): void {
    $outputDir = sys_get_temp_dir().'/rundesk-test-exists-'.uniqid();
    mkdir($outputDir.'/existing-ext', 0755, true);

    $tester = newCommandTester();
    $tester->execute([
        'id' => 'existing-ext',
        '--output' => $outputDir,
    ]);

    expect($tester->getStatusCode())->toBe(1);
    expect($tester->getDisplay())->toContain('Directory already exists');

    cleanupDir($outputDir);
});

test('it fails when stub directory is missing', function (): void {
    $outputDir = sys_get_temp_dir().'/rundesk-test-nostubs-'.uniqid();
    mkdir($outputDir, 0755, true);

    // The stubs path is derived from NewCommand's __DIR__: dirname(__DIR__, 2)/stubs/{type}
    // Temporarily rename the stubs/extension directory to simulate it missing
    $stubBase = dirname(__DIR__, 3).'/stubs/extension';
    $renamed = $stubBase.'_backup_'.uniqid();

    if (! is_dir($stubBase)) {
        cleanupDir($outputDir);
        $this->markTestSkipped('Stubs directory not found — cannot test missing stubs');
    }

    rename($stubBase, $renamed);

    try {
        $tester = newCommandTester();
        $tester->execute([
            'id' => 'stub-test',
            '--output' => $outputDir,
        ]);

        expect($tester->getStatusCode())->toBe(1);
        expect($tester->getDisplay())->toContain('Stub directory not found');
    } finally {
        rename($renamed, $stubBase);
        cleanupDir($outputDir);
    }
});

test('it derives name from id when not provided', function (): void {
    $outputDir = sys_get_temp_dir().'/rundesk-test-derive-'.uniqid();
    mkdir($outputDir, 0755, true);

    $tester = newCommandTester();
    $tester->execute([
        'id' => 'google-calendar',
        '--output' => $outputDir,
    ]);

    $manifest = json_decode(file_get_contents($outputDir.'/google-calendar/rundesk.json'), true);
    expect($manifest['name'])->toBe('Google Calendar');

    cleanupDir($outputDir);
});
