<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Console\ValidateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

function validateCommandTester(): CommandTester
{
    $app = new Application;
    $app->addCommand(new ValidateCommand);

    return new CommandTester($app->find('validate:manifest'));
}

test('it validates a valid manifest', function (): void {
    $dir = sys_get_temp_dir().'/rundesk-test-validate-'.uniqid();
    mkdir($dir, 0755, true);

    file_put_contents($dir.'/rundesk.json', json_encode([
        'id' => 'test-ext',
        'name' => 'Test Extension',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => 'SKILL.md',
    ]));

    // Create required files
    mkdir($dir.'/src', 0755, true);
    file_put_contents($dir.'/src/Extension.php', '<?php return new class {};');
    file_put_contents($dir.'/SKILL.md', '# Skill');

    $tester = validateCommandTester();
    $tester->execute(['path' => $dir]);

    expect($tester->getStatusCode())->toBe(0);
    expect($tester->getDisplay())->toContain('Manifest is valid');

    // Cleanup
    unlink($dir.'/src/Extension.php');
    rmdir($dir.'/src');
    unlink($dir.'/SKILL.md');
    unlink($dir.'/rundesk.json');
    rmdir($dir);
});

test('it fails when rundesk.json is missing', function (): void {
    $tester = validateCommandTester();
    $tester->execute(['path' => '/tmp/nonexistent-'.uniqid()]);

    expect($tester->getStatusCode())->toBe(1);
    expect($tester->getDisplay())->toContain('No rundesk.json');
});

test('it reports validation errors', function (): void {
    $dir = sys_get_temp_dir().'/rundesk-test-invalid-'.uniqid();
    mkdir($dir, 0755, true);

    file_put_contents($dir.'/rundesk.json', json_encode([
        'id' => '',
        'name' => '',
        'version' => '1.0.0',
        'entry' => 'src/Extension.php',
        'skill_guide' => 'SKILL.md',
    ]));

    $tester = validateCommandTester();
    $tester->execute(['path' => $dir]);

    expect($tester->getStatusCode())->toBe(1);
    expect($tester->getDisplay())->toContain('Missing required field: id');
    expect($tester->getDisplay())->toContain('Missing required field: name');

    unlink($dir.'/rundesk.json');
    rmdir($dir);
});
