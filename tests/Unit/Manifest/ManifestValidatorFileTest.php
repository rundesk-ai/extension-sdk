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

test('file existence validation flags path traversal in skill guide', function (): void {
    $dir = sys_get_temp_dir().'/rundesk-test-validator-skill-traversal-'.uniqid();
    $parentFile = sys_get_temp_dir().'/traversal-skill-'.uniqid().'.md';

    mkdir($dir.'/src', 0755, true);
    file_put_contents($dir.'/src/Extension.php', '<?php');
    file_put_contents($parentFile, '# Escaped');

    $manifest = new ManifestReader([
        'id' => 'test',
        'name' => 'Test',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => '../'.basename($parentFile),
    ]);

    $validator = new ManifestValidator;
    $validator->validate($manifest, $dir);

    expect($validator->errors())->toContain('Skill guide escapes extension directory: ../'.basename($parentFile));

    unlink($parentFile);
    unlink($dir.'/src/Extension.php');
    rmdir($dir.'/src');
    rmdir($dir);
});

test('file existence validation flags path traversal in migrations directory', function (): void {
    $dir = sys_get_temp_dir().'/rundesk-test-validator-mig-traversal-'.uniqid();
    $parentDir = sys_get_temp_dir().'/traversal-migrations-'.uniqid();

    mkdir($dir.'/src', 0755, true);
    mkdir($parentDir, 0755, true);
    file_put_contents($dir.'/src/Extension.php', '<?php');
    file_put_contents($dir.'/SKILL.md', '# Skill');

    $manifest = new ManifestReader([
        'id' => 'test',
        'name' => 'Test',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => 'SKILL.md',
        'database' => ['enabled' => true, 'migrations' => '../'.basename($parentDir)],
    ]);

    $validator = new ManifestValidator;
    $validator->validate($manifest, $dir);

    expect($validator->errors())->toContain('Migrations directory escapes extension directory: ../'.basename($parentDir));

    rmdir($parentDir);
    unlink($dir.'/src/Extension.php');
    rmdir($dir.'/src');
    unlink($dir.'/SKILL.md');
    rmdir($dir);
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
