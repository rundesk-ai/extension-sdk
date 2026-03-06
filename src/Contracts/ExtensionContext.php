<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Contracts;

use Illuminate\Database\Connection;

interface ExtensionContext
{
    public function log(): ExtensionLogger;

    public function credential(string $key, ?int $accountId = null): string;

    /**
     * @return list<array{id: int, label: string, is_default: bool, metadata: array<string, mixed>|null}>
     */
    public function accounts(string $credentialKey): array;

    public function db(): Connection;

    public function config(?string $key = null, mixed $default = null): mixed;

    public function storagePath(string $path = ''): string;

    public function extensionId(): string;
}
