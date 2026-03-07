<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Testing;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\SQLiteConnection;
use Rundesk\Extension\Sdk\Abstract\BaseModel;
use Rundesk\Extension\Sdk\Contracts\ExtensionContext;
use Rundesk\Extension\Sdk\Contracts\ExtensionLogger;

/**
 * A real context for `dev:serve` — creates a real SQLite database,
 * runs migrations, and supports real credentials and config.
 */
class DevContext implements ExtensionContext
{
    private FakeLogger $logger;

    private ?Connection $dbConnection = null;

    /**
     * @param  array<string, string>  $credentials
     * @param  array<string, mixed>  $config
     * @param  array<string, list<array{id: int, label: string, is_default: bool, metadata: array<string, mixed>|null}>>  $accounts
     */
    public function __construct(
        private readonly string $extensionId,
        private readonly string $extensionPath,
        private readonly array $credentials = [],
        private readonly array $config = [],
        private readonly array $accounts = [],
        private readonly ?string $migrationsPath = null,
        private readonly bool $dbEnabled = false,
    ) {
        $this->logger = new FakeLogger;

        if ($this->dbEnabled) {
            $this->bootDatabase();
        }
    }

    public function log(): ExtensionLogger
    {
        return $this->logger;
    }

    public function credential(string $key, ?int $accountId = null): string
    {
        if (! isset($this->credentials[$key])) {
            throw new \RuntimeException(
                "Credential '{$key}' not provided. Pass via: --credential {$key}=value "
                .'or set CREDENTIAL_'.strtoupper($key).' in .env.rundesk'
            );
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
        if ($this->dbConnection === null) {
            throw new \LogicException(
                'Database is not enabled for this extension. Set database.enabled = true in rundesk.json.'
            );
        }

        return $this->dbConnection;
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
        $base = $this->extensionPath.'/storage';

        if (! is_dir($base)) {
            mkdir($base, 0755, true);
        }

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

    private function bootDatabase(): void
    {
        $dbPath = $this->storagePath().'/dev.db';

        $pdo = new \PDO('sqlite:'.$dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->dbConnection = new SQLiteConnection($pdo, $dbPath);

        // Register this connection as the resolver for all Eloquent models
        Model::setConnectionResolver(new DevConnectionResolver($this->dbConnection));
        BaseModel::setDefaultConnection('default');

        // Run migrations
        if ($this->migrationsPath !== null && is_dir($this->migrationsPath)) {
            $this->runMigrations($pdo);
        }
    }

    private function runMigrations(\PDO $pdo): void
    {
        if ($this->migrationsPath === null) {
            return;
        }

        $files = glob($this->migrationsPath.'/*.sql');

        if ($files === false) {
            return;
        }

        sort($files);

        foreach ($files as $file) {
            $sql = file_get_contents($file);

            if ($sql !== false) {
                $pdo->exec($sql);
            }
        }
    }
}

/**
 * @internal
 */
class DevConnectionResolver implements ConnectionResolverInterface
{
    public function __construct(private readonly Connection $connection) {}

    /** @param  string|null  $name */
    public function connection($name = null)
    {
        return $this->connection;
    }

    public function getDefaultConnection()
    {
        return 'default';
    }

    /** @param  string  $name */
    public function setDefaultConnection($name) {}
}
