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
        $projectDir = (string) getcwd();
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

        // Install production dependencies
        $output->writeln('Installing production dependencies...');

        $vendorDir = $projectDir.'/vendor';
        $hadVendor = is_dir($vendorDir);

        $installResult = $this->runProcess(
            'composer install --no-dev --optimize-autoloader --no-interaction',
            $projectDir,
        );

        if ($installResult !== 0) {
            $output->writeln('<error>composer install failed</error>');

            return Command::FAILURE;
        }

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

        // Restore dev dependencies
        if ($hadVendor) {
            $this->runProcess('composer install --no-interaction', $projectDir);
        }

        $size = round((int) filesize($zipPath) / 1024);
        $output->writeln("<info>Built: {$zipPath} ({$size} KB)</info>");

        return Command::SUCCESS;
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
            $relativePath = ltrim(str_replace($baseDir, '', $fullPath), '/');

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
            if (str_starts_with($path, $pattern)) {
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

    private function runProcess(string $command, string $cwd): int
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
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return proc_close($process);
    }
}
