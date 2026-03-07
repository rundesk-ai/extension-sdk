<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Testing;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Rundesk\Extension\Sdk\Contracts\ExtensionContext;
use Rundesk\Extension\Sdk\Contracts\ExtensionLogger;

class FakeContext implements ExtensionContext
{
    private FakeLogger $logger;

    /**
     * @param  array<string, string>  $credentials
     * @param  array<string, mixed>  $config
     * @param  array<string, list<array{id: int, label: string, is_default: bool, metadata: array<string, mixed>|null}>>  $accounts
     */
    public function __construct(
        private readonly string $extensionId,
        private readonly array $credentials = [],
        private readonly array $config = [],
        private readonly array $accounts = [],
    ) {
        $this->logger = new FakeLogger;
    }

    public function log(): ExtensionLogger
    {
        return $this->logger;
    }

    public function credential(string $key, ?int $accountId = null): string
    {
        if (! isset($this->credentials[$key])) {
            throw new \RuntimeException("Test credential not set: {$key}. Use fakeContext(['{$key}' => 'value'])");
        }

        return $this->credentials[$key];
    }

    /**
     * @return list<array{id: int, label: string, is_default: bool, metadata: array<string, mixed>|null}>
     */
    public function accounts(string $credentialKey): array
    {
        return $this->accounts[$credentialKey] ?? [];
    }

    public function db(): Connection
    {
        $connectionName = "ext_{$this->extensionId}";

        try {
            $config = config("database.connections.{$connectionName}");
        } catch (\Throwable) {
            $config = null;
        }

        if ($config === null) {
            throw new \LogicException(
                "Database connection '{$connectionName}' is not configured. "
                .'Use ExtensionTestCase (which sets up an in-memory SQLite connection) '
                .'or configure the connection manually before calling db().'
            );
        }

        return DB::connection($connectionName);
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key !== null) {
            return $this->config[$key] ?? $default;
        }

        return $this->config;
    }

    public function storagePath(string $path = ''): string
    {
        $base = sys_get_temp_dir().'/rundesk-test-'.$this->extensionId;

        return $path !== '' ? $base.'/'.$path : $base;
    }

    public function extensionId(): string
    {
        return $this->extensionId;
    }

    public function logger(): FakeLogger
    {
        return $this->logger;
    }
}
