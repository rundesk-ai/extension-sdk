<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Console;

use Rundesk\Extension\Sdk\Manifest\ManifestReader;
use Rundesk\Extension\Sdk\Manifest\ManifestValidator;
use Rundesk\Extension\Sdk\Testing\FakeContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DevCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('dev:serve')
            ->setDescription('Run an extension method with a fake context')
            ->addArgument('method', InputArgument::REQUIRED, 'Method to execute')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Extension directory', '.')
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'JSON input', '{}');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $path */
        $path = $input->getOption('path');
        $path = realpath($path) ?: $path;

        /** @var string $method */
        $method = $input->getArgument('method');

        /** @var string $jsonInput */
        $jsonInput = $input->getOption('input');

        try {
            $manifest = ManifestReader::fromPath($path);
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");

            return Command::FAILURE;
        }

        $validator = new ManifestValidator;

        if (! $validator->validate($manifest, $path)) {
            $output->writeln('<error>Manifest validation failed:</error>');

            foreach ($validator->errors() as $error) {
                $output->writeln("  - {$error}");
            }

            return Command::FAILURE;
        }

        $entryFile = $path.'/'.$manifest->entry();

        if (! file_exists($entryFile)) {
            $output->writeln("<error>Entry file not found: {$entryFile}</error>");

            return Command::FAILURE;
        }

        $parsedInput = json_decode($jsonInput, true);

        if (! is_array($parsedInput)) {
            $output->writeln('<error>Invalid JSON input</error>');

            return Command::FAILURE;
        }

        // Load vendor autoloaders if present
        foreach ($manifest->vendors() as $vendorFile) {
            $vendorPath = $path.'/'.$vendorFile;
            if (file_exists($vendorPath)) {
                require_once $vendorPath;
            }
        }

        $context = new FakeContext(extensionId: $manifest->id());

        $entry = require $entryFile;

        if (! ($entry instanceof \Rundesk\Extension\Sdk\Contracts\Extension)) {
            $output->writeln('<error>Entry class does not implement Rundesk\Extension\Sdk\Contracts\Extension</error>');

            return Command::FAILURE;
        }

        $output->writeln("<info>Executing {$method} on {$manifest->name()}...</info>");

        $result = $entry->execute($method, $parsedInput, $context);

        $output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $result->success ? Command::SUCCESS : Command::FAILURE;
    }
}
