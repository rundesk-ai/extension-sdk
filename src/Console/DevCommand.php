<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Console;

use Rundesk\Extension\Sdk\Manifest\ManifestReader;
use Rundesk\Extension\Sdk\Manifest\ManifestValidator;
use Rundesk\Extension\Sdk\Testing\DevContext;
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
            ->setDescription('Run an extension method with a dev context (real DB, optional credentials)')
            ->addArgument('method', InputArgument::REQUIRED, 'Method to execute (e.g. get_events)')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Extension directory', '.')
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'JSON input', '{}')
            ->addOption('credential', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Credentials as key=value pairs')
            ->addOption('config', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Config as key=value pairs')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Execute the dry-run handler instead of the real one')
            ->addOption('env-file', null, InputOption::VALUE_REQUIRED, 'Path to .env file for credentials/config', '.env.rundesk');
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

        // Build context with real DB, credentials from CLI/env file, and config
        $credentials = $this->resolveCredentials($input, $path);
        $config = $this->resolveConfig($input, $path, $manifest);

        $migrationsPath = null;

        if ($manifest->dbEnabled() && $manifest->migrations() !== null) {
            $migrationsPath = $path.'/'.$manifest->migrations();
        }

        try {
            $context = new DevContext(
                extensionId: $manifest->id(),
                extensionPath: $path,
                credentials: $credentials,
                config: $config,
                migrationsPath: $migrationsPath,
                dbEnabled: $manifest->dbEnabled(),
            );
        } catch (\Throwable $e) {
            $output->writeln("<error>Failed to create dev context: {$e->getMessage()}</error>");

            return Command::FAILURE;
        }

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

        $isDryRun = (bool) $input->getOption('dry-run');
        $modeLabel = $isDryRun ? 'dry-run' : 'execute';

        $output->writeln("<info>[{$modeLabel}] {$method} on {$manifest->name()}...</info>");

        try {
            $result = $isDryRun
                ? $entry->dryRun($method, $parsedInput)
                : $entry->execute($method, $parsedInput, $context);
        } catch (\Throwable $e) {
            $output->writeln("<error>Exception during execution: {$e->getMessage()}</error>");
            $output->writeln("<comment>{$e->getFile()}:{$e->getLine()}</comment>");

            return Command::FAILURE;
        }

        $output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $result->success ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Resolve credentials from CLI flags and .env.rundesk file.
     * CLI flags take precedence over env file values.
     *
     * @return array<string, string>
     */
    private function resolveCredentials(InputInterface $input, string $path): array
    {
        $credentials = [];

        // Load from env file first (lower priority)
        /** @var string $envFile */
        $envFile = $input->getOption('env-file');
        $envPath = str_starts_with($envFile, '/') ? $envFile : $path.'/'.$envFile;

        if (file_exists($envPath)) {
            $envVars = $this->parseEnvFile($envPath);

            foreach ($envVars as $key => $value) {
                if (str_starts_with($key, 'CREDENTIAL_')) {
                    $credKey = strtolower(substr($key, 11)); // CREDENTIAL_GOOGLE -> google
                    $credentials[$credKey] = $value;
                }
            }
        }

        // CLI flags override env file (higher priority)
        /** @var list<string> $cliCredentials */
        $cliCredentials = $input->getOption('credential');

        foreach ($cliCredentials as $pair) {
            $pos = strpos($pair, '=');

            if ($pos !== false) {
                $key = substr($pair, 0, $pos);
                $value = substr($pair, $pos + 1);
                $credentials[$key] = $value;
            }
        }

        return $credentials;
    }

    /**
     * Resolve config from CLI flags, .env.rundesk file, and manifest defaults.
     *
     * @return array<string, mixed>
     */
    private function resolveConfig(InputInterface $input, string $path, ManifestReader $manifest): array
    {
        // Start with manifest-defined config defaults
        $config = $manifest->config();

        // Load from env file
        /** @var string $envFile */
        $envFile = $input->getOption('env-file');
        $envPath = str_starts_with($envFile, '/') ? $envFile : $path.'/'.$envFile;

        if (file_exists($envPath)) {
            $envVars = $this->parseEnvFile($envPath);

            foreach ($envVars as $key => $value) {
                if (str_starts_with($key, 'CONFIG_')) {
                    $configKey = strtolower(substr($key, 7)); // CONFIG_SYNC_DAYS -> sync_days
                    $config[$configKey] = $this->castConfigValue($value);
                }
            }
        }

        // CLI flags override
        /** @var list<string> $cliConfig */
        $cliConfig = $input->getOption('config');

        foreach ($cliConfig as $pair) {
            $pos = strpos($pair, '=');

            if ($pos !== false) {
                $key = substr($pair, 0, $pos);
                $value = substr($pair, $pos + 1);
                $config[$key] = $this->castConfigValue($value);
            }
        }

        return $config;
    }

    /**
     * Parse a simple .env file (KEY=VALUE lines, # comments, empty lines ignored).
     *
     * @return array<string, string>
     */
    private function parseEnvFile(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $vars = [];

        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');

            if ($pos !== false) {
                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));

                // Strip surrounding quotes
                if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                $vars[$key] = $value;
            }
        }

        return $vars;
    }

    private function castConfigValue(string $value): mixed
    {
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if ($value === 'null') {
            return null;
        }

        if (is_numeric($value) && ! str_contains($value, '.')) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
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
