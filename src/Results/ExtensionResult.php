<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Results;

final readonly class ExtensionResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public bool $success,
        public array $data = [],
        public ?string $error = null,
        public bool $isDryRun = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(array $data = []): self
    {
        return new self(success: true, data: $data);
    }

    public static function failed(string $error): self
    {
        return new self(success: false, error: $error);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function sample(array $data): self
    {
        return new self(success: true, data: $data, isDryRun: true);
    }

    /**
     * @return array{success: bool, data: array<string, mixed>, error: string|null, isDryRun: bool}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
            'isDryRun' => $this->isDryRun,
        ];
    }
}
