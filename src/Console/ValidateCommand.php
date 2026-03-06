<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Console;

use Rundesk\Extension\Sdk\Manifest\ManifestReader;
use Rundesk\Extension\Sdk\Manifest\ManifestValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('validate:manifest')
            ->setDescription('Validate rundesk.json manifest')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to extension directory', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $path */
        $path = $input->getArgument('path');
        $path = realpath($path) ?: $path;

        try {
            $manifest = ManifestReader::fromPath($path);
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");

            return Command::FAILURE;
        }

        $validator = new ManifestValidator;
        $valid = $validator->validate($manifest, $path);

        if ($valid) {
            $output->writeln('<info>Manifest is valid.</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>Manifest validation failed:</error>');

        foreach ($validator->errors() as $error) {
            $output->writeln("  - {$error}");
        }

        return Command::FAILURE;
    }
}
