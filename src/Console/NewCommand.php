<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NewCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('new')
            ->setDescription('Scaffold a new Rundesk extension')
            ->addArgument('id', InputArgument::REQUIRED, 'Extension ID (e.g. google-calendar)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Display name')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Description')
            ->addOption('database', null, InputOption::VALUE_NONE, 'Include database support')
            ->addOption('oauth', null, InputOption::VALUE_NONE, 'Include OAuth credential')
            ->addOption('minimal', null, InputOption::VALUE_NONE, 'Use minimal scaffold (no models, no provider)')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output directory', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $id */
        $id = $input->getArgument('id');

        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            $output->writeln('<error>Invalid extension id — only alphanumeric, hyphens, and underscores allowed</error>');

            return Command::FAILURE;
        }

        $name = $input->getOption('name') ?? ucwords(str_replace('-', ' ', $id));
        $description = $input->getOption('description') ?? '';
        $database = (bool) $input->getOption('database');
        $oauth = (bool) $input->getOption('oauth');
        $minimal = (bool) $input->getOption('minimal');

        /** @var string $outputDir */
        $outputDir = $input->getOption('output');
        $targetDir = rtrim($outputDir, '/').'/'.$id;

        if (is_dir($targetDir)) {
            $output->writeln("<error>Directory already exists: {$targetDir}</error>");

            return Command::FAILURE;
        }

        $stubDir = $minimal ? 'minimal' : 'extension';
        $stubBase = dirname(__DIR__, 2).'/stubs/'.$stubDir;

        if (! is_dir($stubBase)) {
            $output->writeln("<error>Stub directory not found: {$stubBase}</error>");

            return Command::FAILURE;
        }

        $credentials = $oauth
            ? json_encode([
                'oauth_token' => [
                    'type' => 'oauth',
                    'label' => (string) $name,
                    'required' => true,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : '{}';

        // Indent the credentials block to match the manifest's indentation
        $credentials = str_replace("\n", "\n    ", $credentials);

        $this->copyStubs($stubBase, $targetDir, [
            '{{ID}}' => $id,
            '{{NAME}}' => (string) $name,
            '{{DESCRIPTION}}' => (string) $description,
            '{{DATABASE_ENABLED}}' => $database ? 'true' : 'false',
            '{{CREDENTIALS}}' => $credentials,
            '{{NAMESPACE}}' => str_replace('-', '', ucwords($id, '-')),
        ]);

        if ($database && ! $minimal) {
            mkdir($targetDir.'/migrations', 0755, true);
        }

        $output->writeln("<info>Extension scaffolded at: {$targetDir}</info>");

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function copyStubs(string $source, string $target, array $replacements): void
    {
        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $items = scandir($source);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source.'/'.$item;
            $targetItem = str_replace(array_keys($replacements), array_values($replacements), $item);
            $targetPath = $target.'/'.$targetItem;

            if (is_dir($sourcePath)) {
                $this->copyStubs($sourcePath, $targetPath, $replacements);
            } else {
                $content = file_get_contents($sourcePath);

                if ($content !== false) {
                    $content = str_replace(array_keys($replacements), array_values($replacements), $content);
                    file_put_contents($targetPath, $content);
                }
            }
        }
    }
}
