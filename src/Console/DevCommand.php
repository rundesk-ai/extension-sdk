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
        /** @var string $rawPath */
        $rawPath = $input->getOption('path');
        $path = realpath($rawPath);

        if ($path === false) {
            $output->writeln("<error>Cannot resolve extension path: {$rawPath}</error>");

            return Command::FAILURE;
        }

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

        $entryFile = realpath($path.'/'.$manifest->entry());

        if ($entryFile === false) {
            $output->writeln("<error>Entry file not found: {$manifest->entry()}</error>");

            return Command::FAILURE;
        }

        if (! str_starts_with($entryFile, $path.DIRECTORY_SEPARATOR)) {
            $output->writeln('<error>Entry file escapes extension directory</error>');

            return Command::FAILURE;
        }

        $parsedInput = json_decode($jsonInput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $output->writeln('<error>Malformed JSON input: '.json_last_error_msg().'</error>');

            return Command::FAILURE;
        }

        if (! is_array($parsedInput)) {
            $output->writeln('<error>JSON input must be an object, got: '.gettype($parsedInput).'</error>');

            return Command::FAILURE;
        }

        // Load vendor autoloaders with path traversal protection
        foreach ($manifest->vendors() as $vendorFile) {
            $realVendor = realpath($path.'/'.ltrim($vendorFile, '/'));

            if ($realVendor !== false && str_starts_with($realVendor, $path.DIRECTORY_SEPARATOR)) {
                require_once $realVendor;
            }
        }

        $context = new FakeContext(extensionId: $manifest->id());

        $entry = require $entryFile;

        // Support both patterns:
        // 1. Anonymous class: entry file returns an Extension instance directly
        // 2. Named class: entry file defines a class via PSR-4 autoloading, require returns 1
        if (! ($entry instanceof \Rundesk\Extension\Sdk\Contracts\Extension)) {
            $entry = $this->resolveNamedExtension($entryFile);
        }

        if (! ($entry instanceof \Rundesk\Extension\Sdk\Contracts\Extension)) {
            $output->writeln('<error>Entry class does not implement Rundesk\Extension\Sdk\Contracts\Extension</error>');

            return Command::FAILURE;
        }

        $registerResult = $entry->register($context);

        if (! $registerResult->passed) {
            $output->writeln("<error>register() failed: {$registerResult->reason}</error>");

            return Command::FAILURE;
        }

        $output->writeln("<info>Executing {$method} on {$manifest->name()}...</info>");

        $result = $entry->execute($method, $parsedInput, $context);

        $output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $result->success ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Resolve a named Extension class from a PSR-4 autoloaded entry file.
     * Parses the namespace and class name from the file, then instantiates it.
     */
    private function resolveNamedExtension(string $entryFile): ?object
    {
        $contents = file_get_contents($entryFile);

        if ($contents === false) {
            return null;
        }

        // Extract namespace and class name from the file
        $namespace = null;
        $className = null;

        if (preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $matches)) {
            $namespace = trim($matches[1]);
        }

        if (preg_match('/^(?!\s*abstract\s)\s*class\s+(\w+)/m', $contents, $matches)) {
            $className = trim($matches[1]);
        }

        if ($className === null) {
            return null;
        }

        $fqcn = $namespace ? $namespace.'\\'.$className : $className;

        if (! class_exists($fqcn) || ! is_a($fqcn, \Rundesk\Extension\Sdk\Contracts\Extension::class, true)) {
            return null;
        }

        $reflection = new \ReflectionClass($fqcn);
        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        return $reflection->newInstance();
    }
}
