<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Manifest;

class ManifestReader
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly array $data) {}

    public static function fromPath(string $path): self
    {
        $file = rtrim($path, '/').'/rundesk.json';

        if (! file_exists($file)) {
            throw new \RuntimeException("No rundesk.json at {$path}");
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            throw new \RuntimeException("Cannot read rundesk.json at {$path}");
        }

        $data = json_decode($contents, true);

        if (! is_array($data)) {
            throw new \RuntimeException('rundesk.json is not valid JSON');
        }

        return new self($data);
    }

    public function id(): string
    {
        return (string) ($this->data['id'] ?? '');
    }

    public function name(): string
    {
        return (string) ($this->data['name'] ?? '');
    }

    public function version(): string
    {
        return (string) ($this->data['version'] ?? '');
    }

    public function entry(): string
    {
        return (string) ($this->data['entry'] ?? '');
    }

    public function skillGuide(): string
    {
        return (string) ($this->data['skill_guide'] ?? '');
    }

    public function description(): ?string
    {
        return $this->data['description'] ?? null;
    }

    /**
     * @return array{name?: string, email?: string, url?: string}|null
     */
    public function author(): ?array
    {
        $author = $this->data['author'] ?? null;

        return is_array($author) ? $author : null;
    }

    public function repository(): ?string
    {
        $explicit = $this->data['repository'] ?? null;

        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $authorUrl = $this->data['author']['url'] ?? null;

        return is_string($authorUrl) && $authorUrl !== '' ? $authorUrl : null;
    }

    public function dbEnabled(): bool
    {
        return $this->data['database']['enabled'] ?? false;
    }

    public function migrations(): ?string
    {
        return $this->data['database']['migrations'] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function credentials(): array
    {
        return $this->data['credentials'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function allowedHosts(): array
    {
        return $this->data['network']['allowed_hosts'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function schedules(): array
    {
        return $this->data['schedules'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function hooks(): array
    {
        /** @var list<array<string, mixed>> */
        return $this->data['hooks'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->data['config'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function vendors(): array
    {
        return $this->data['vendors'] ?? ['vendor/autoload.php'];
    }

    public function provider(): ?string
    {
        return $this->data['provider'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
