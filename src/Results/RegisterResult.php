<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Results;

final readonly class RegisterResult
{
    private function __construct(
        public bool $passed,
        public ?string $reason = null,
    ) {}

    public static function ok(): self
    {
        return new self(passed: true);
    }

    public static function failed(string $reason): self
    {
        return new self(passed: false, reason: $reason);
    }
}
