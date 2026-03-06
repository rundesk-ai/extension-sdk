<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Manifest;

use Rundesk\Extension\Sdk\Events\KnownEvents;

class ManifestValidator
{
    /**
     * @var list<string>
     */
    private array $errors = [];

    public function validate(ManifestReader $manifest, ?string $basePath = null): bool
    {
        $this->errors = [];

        $this->validateRequiredFields($manifest);
        $this->validateIdFormat($manifest);
        $this->validateHooks($manifest);
        $this->validateSchedules($manifest);

        if ($basePath !== null) {
            $this->validateFileExistence($manifest, $basePath);
        }

        return $this->errors === [];
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    private function validateRequiredFields(ManifestReader $manifest): void
    {
        foreach (['id', 'name', 'version', 'entry', 'skill_guide'] as $field) {
            $value = match ($field) {
                'id' => $manifest->id(),
                'name' => $manifest->name(),
                'version' => $manifest->version(),
                'entry' => $manifest->entry(),
                'skill_guide' => $manifest->skillGuide(),
            };

            if ($value === '') {
                $this->errors[] = "Missing required field: {$field}";
            }
        }
    }

    private function validateIdFormat(ManifestReader $manifest): void
    {
        $id = $manifest->id();

        if ($id !== '' && ! preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            $this->errors[] = "Invalid extension id format: {$id}";
        }
    }

    private function validateHooks(ManifestReader $manifest): void
    {
        $knownEvents = KnownEvents::all();

        foreach ($manifest->hooks() as $index => $hook) {
            /** @var string $event */
            $event = $hook['event'] ?? '';
            /** @var string $method */
            $method = $hook['method'] ?? '';

            if ($event === '') {
                $this->errors[] = "Hook #{$index} missing required 'event' field";
            } elseif (! in_array($event, $knownEvents, true)) {
                $this->errors[] = "Hook #{$index} has unknown event: {$event}";
            }

            if ($method === '') {
                $this->errors[] = "Hook #{$index} missing required 'method' field";
            }
        }
    }

    private function validateSchedules(ManifestReader $manifest): void
    {
        foreach ($manifest->schedules() as $index => $schedule) {
            $method = $schedule['method'] ?? '';
            $cron = $schedule['cron'] ?? '';

            if ($method === '') {
                $this->errors[] = "Schedule #{$index} missing required 'method' field";
            }

            if ($cron !== '' && ! $this->isValidCron($cron)) {
                $this->errors[] = "Schedule #{$index} has invalid cron expression: {$cron}";
            }
        }
    }

    private function validateFileExistence(ManifestReader $manifest, string $basePath): void
    {
        $resolvedBase = realpath($basePath);

        if ($resolvedBase === false) {
            $this->errors[] = "Base path does not exist: {$basePath}";

            return;
        }

        $entry = $manifest->entry();

        if ($entry !== '') {
            $entryPath = realpath($resolvedBase.'/'.$entry);

            if ($entryPath === false) {
                $this->errors[] = "Entry file not found: {$entry}";
            } elseif (! str_starts_with($entryPath, $resolvedBase.DIRECTORY_SEPARATOR)) {
                $this->errors[] = "Entry file escapes extension directory: {$entry}";
            }
        }

        $skillGuide = $manifest->skillGuide();

        if ($skillGuide !== '') {
            $guidePath = realpath($resolvedBase.'/'.$skillGuide);

            if ($guidePath === false) {
                $this->errors[] = "Skill guide not found: {$skillGuide}";
            } elseif (! str_starts_with($guidePath, $resolvedBase.DIRECTORY_SEPARATOR)) {
                $this->errors[] = "Skill guide escapes extension directory: {$skillGuide}";
            }
        }

        $migrations = $manifest->migrations();

        if ($migrations !== null) {
            $migrationsPath = realpath($resolvedBase.'/'.$migrations);

            if ($migrationsPath === false || ! is_dir($migrationsPath)) {
                $this->errors[] = "Migrations directory not found: {$migrations}";
            } elseif (! str_starts_with($migrationsPath, $resolvedBase.DIRECTORY_SEPARATOR)) {
                $this->errors[] = "Migrations directory escapes extension directory: {$migrations}";
            }
        }
    }

    private function isValidCron(string $expression): bool
    {
        $parts = preg_split('/\s+/', trim($expression));

        return is_array($parts) && count($parts) === 5;
    }
}
