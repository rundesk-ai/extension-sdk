<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Manifest\ManifestReader;
use Rundesk\Extension\Sdk\Manifest\ManifestValidator;

test('file existence validation passes when files exist', function (): void {
    $dir = sys_get_temp_dir().'/rundesk-test-validator-files-'.uniqid();
    mkdir($dir.'/src', 0755, true);
    file_put_contents($dir.'/src/Extension.php', '<?php');
    file_put_contents($dir.'/SKILL.md', '# Skill');

    $manifest = new ManifestReader([
        'id' => 'test',
        'name' => 'Test',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => 'SKILL.md',
    ]);

    $validator = new ManifestValidator;
    $validator->validate($manifest, $dir);

    expect($validator->errors())->toBe([]);

    unlink($dir.'/src/Extension.php');
    rmdir($dir.'/src');
    unlink($dir.'/SKILL.md');
    rmdir($dir);
});

test('file existence validation flags missing entry file', function (): void {
    $dir = sys_get_temp_dir().'/rundesk-test-validator-missing-'.uniqid();
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/SKILL.md', '# Skill');

    $manifest = new ManifestReader([
        'id' => 'test',
        'name' => 'Test',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => 'SKILL.md',
    ]);

    $validator = new ManifestValidator;
    $validator->validate($manifest, $dir);

    expect($validator->errors())->toContain('Entry file not found: src/Extension.php');

    unlink($dir.'/SKILL.md');
    rmdir($dir);
});

test('file existence validation flags path traversal in entry', function (): void {
    $dir = sys_get_temp_dir().'/rundesk-test-validator-traversal-'.uniqid();
    $parentFile = sys_get_temp_dir().'/traversal-target-'.uniqid().'.php';

    mkdir($dir, 0755, true);
    file_put_contents($parentFile, '<?php');

    $relative = '../'.basename($parentFile);

    $manifest = new ManifestReader([
        'id' => 'test',
        'name' => 'Test',
        'version' => '1.0.0',
        'entry' => $relative,
        'skill_guide' => 'SKILL.md',
    ]);

    $validator = new ManifestValidator;
    $validator->validate($manifest, $dir);

    $errors = implode(', ', $validator->errors());
    expect($errors)->toContain('escapes extension directory');

    unlink($parentFile);
    rmdir($dir);
});

test('file existence validation flags missing migrations directory', function (): void {
    $dir = sys_get_temp_dir().'/rundesk-test-validator-migrations-'.uniqid();
    mkdir($dir.'/src', 0755, true);
    file_put_contents($dir.'/src/Extension.php', '<?php');
    file_put_contents($dir.'/SKILL.md', '# Skill');

    $manifest = new ManifestReader([
        'id' => 'test',
        'name' => 'Test',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => 'SKILL.md',
        'database' => ['enabled' => true, 'migrations' => 'migrations/'],
    ]);

    $validator = new ManifestValidator;
    $validator->validate($manifest, $dir);

    expect($validator->errors())->toContain('Migrations directory not found: migrations/');

    unlink($dir.'/src/Extension.php');
    rmdir($dir.'/src');
    unlink($dir.'/SKILL.md');
    rmdir($dir);
});

test('file existence validation flags nonexistent base path', function (): void {
    $manifest = new ManifestReader([
        'id' => 'test',
        'name' => 'Test',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => 'SKILL.md',
    ]);

    $validator = new ManifestValidator;
    $validator->validate($manifest, '/nonexistent/path/'.uniqid());

    expect($validator->errors())->not->toBe([]);
    $errors = implode(', ', $validator->errors());
    expect($errors)->toContain('Base path does not exist');
});

test('schedule missing method is flagged', function (): void {
    $manifest = new ManifestReader([
        'id' => 'test',
        'name' => 'Test',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => 'SKILL.md',
        'schedules' => [['method' => '', 'cron' => '0 * * * *']],
    ]);

    $validator = new ManifestValidator;
    $validator->validate($manifest);

    expect($validator->errors())->toContain("Schedule #0 missing required 'method' field");
});

test('hooks missing event is flagged', function (): void {
    $manifest = new ManifestReader([
        'id' => 'test',
        'name' => 'Test',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => 'SKILL.md',
        'hooks' => [['event' => '', 'method' => 'handle']],
    ]);

    $validator = new ManifestValidator;
    $validator->validate($manifest);

    expect($validator->errors())->toContain("Hook #0 missing required 'event' field");
});
