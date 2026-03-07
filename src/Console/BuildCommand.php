<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BuildCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('build')
            ->setDescription('Build a distributable zip with vendor dependencies included')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output directory for the zip', 'dist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectDir = getcwd();

        if ($projectDir === false) {
            $output->writeln('<error>Cannot determine current directory</error>');

            return Command::FAILURE;
        }

        $manifestPath = $projectDir.'/rundesk.json';

        if (! file_exists($manifestPath)) {
            $output->writeln('<error>No rundesk.json found in current directory</error>');

            return Command::FAILURE;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest) || empty($manifest['id']) || empty($manifest['version'])) {
            $output->writeln('<error>Invalid rundesk.json — missing id or version</error>');

            return Command::FAILURE;
        }

        $id = $manifest['id'];
        $version = $manifest['version'];

        if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            $output->writeln('<error>Invalid extension id — only alphanumeric, hyphens, and underscores allowed</error>');

            return Command::FAILURE;
        }

        if (! is_string($version) || ! preg_match('/^[a-zA-Z0-9._-]+$/', $version)) {
            $output->writeln('<error>Invalid version format</error>');

            return Command::FAILURE;
        }

        // Install production dependencies
        $output->writeln('Installing production dependencies...');

        $vendorDir = $projectDir.'/vendor';
        $hadVendor = is_dir($vendorDir);

        $installResult = $this->runProcess(
            'composer install --no-dev --optimize-autoloader --no-interaction',
            $projectDir,
            $output,
        );

        if ($installResult !== 0) {
            $output->writeln('<error>composer install failed</error>');

            return Command::FAILURE;
        }

        try {
            /** @var string $outputDir */
            $outputDir = $input->getOption('output');
            $outputPath = $projectDir.'/'.ltrim($outputDir, '/');

            if (! is_dir($outputPath)) {
                mkdir($outputPath, 0755, true);
            }

            $zipName = "{$id}-{$version}.zip";
            $zipPath = $outputPath.'/'.$zipName;

            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            $output->writeln("Building {$zipName}...");

            $zip = new \ZipArchive;

            if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
                $output->writeln('<error>Failed to create zip file</error>');

                return Command::FAILURE;
            }

            $this->addDirectoryToZip($zip, $projectDir, $projectDir, $this->excludePatterns());
            $zip->close();

            $size = round((int) filesize($zipPath) / 1024);
            $output->writeln("<info>Built: {$zipPath} ({$size} KB)</info>");

            return Command::SUCCESS;
        } finally {
            $this->restoreVendorState($hadVendor, $vendorDir, $projectDir, $output);
        }
    }

    private function addDirectoryToZip(\ZipArchive $zip, string $baseDir, string $currentDir, array $excludes): void
    {
        $items = scandir($currentDir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $currentDir.'/'.$item;
            $relativePath = ltrim(substr($fullPath, strlen($baseDir)), DIRECTORY_SEPARATOR);

            if ($this->isExcluded($relativePath, $excludes)) {
                continue;
            }

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($relativePath);
                $this->addDirectoryToZip($zip, $baseDir, $fullPath, $excludes);
            } else {
                $zip->addFile($fullPath, $relativePath);
            }
        }
    }

    /**
     * @param  list<string>  $excludes
     */
    private function isExcluded(string $path, array $excludes): bool
    {
        foreach ($excludes as $pattern) {
            if ($path === $pattern || str_starts_with($path, $pattern.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function excludePatterns(): array
    {
        return [
            '.git',
            '.idea',
            '.vscode',
            '.phpunit.cache',
            'dist',
            'tests',
            'node_modules',
            '.github',
            '.gitignore',
            '.editorconfig',
            'phpunit.xml',
            'phpstan.neon',
            'pint.json',
        ];
    }

    private function restoreVendorState(bool $hadVendor, string $vendorDir, string $projectDir, OutputInterface $output): void
    {
        if ($hadVendor) {
            $this->runProcess('composer install --no-interaction', $projectDir, $output);

            return;
        }

        // Only remove vendor dir if it exists and is verified to be inside the project directory
        $realVendor = realpath($vendorDir);
        $realProject = realpath($projectDir);

        if ($realVendor === false || $realProject === false) {
            return;
        }

        if (! str_starts_with($realVendor, $realProject.DIRECTORY_SEPARATOR)) {
            return;
        }

        // Final guard: must be exactly {projectDir}/vendor, not a nested or unrelated path
        if ($realVendor !== $realProject.DIRECTORY_SEPARATOR.'vendor') {
            return;
        }

        $this->runProcess('rm -rf '.escapeshellarg($realVendor), $projectDir, $output);
    }

    private function runProcess(string $command, string $cwd, ?OutputInterface $output = null): int
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $cwd);

        if (! is_resource($process)) {
            return 1;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0 && $output !== null && $stderr !== false && $stderr !== '') {
            $output->writeln("<error>{$stderr}</error>");
        }

        return $exitCode;
    }
}
