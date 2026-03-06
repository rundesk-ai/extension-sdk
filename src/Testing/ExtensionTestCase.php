<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Testing;

use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;
use Rundesk\Extension\Sdk\Abstract\BaseModel;
use Rundesk\Extension\Sdk\Results\ExtensionResult;

abstract class ExtensionTestCase extends TestCase
{
    protected string $extensionId;

    protected function setUp(): void
    {
        parent::setUp();

        config(["database.connections.ext_{$this->extensionId}" => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);

        $this->runExtensionMigrations();

        BaseModel::setExtensionConnection("ext_{$this->extensionId}");
    }

    abstract protected function extensionMigrationsPath(): string;

    /**
     * @param  array<string, string>  $credentials
     * @param  array<string, mixed>  $config
     */
    protected function fakeContext(array $credentials = [], array $config = []): FakeContext
    {
        return new FakeContext(
            extensionId: $this->extensionId,
            credentials: $credentials,
            config: $config,
        );
    }

    protected function assertResultOk(ExtensionResult $result, string $message = ''): void
    {
        self::assertTrue($result->success, $message ?: "Expected ok result, got: {$result->error}");
    }

    protected function assertResultFailed(ExtensionResult $result, ?string $containing = null): void
    {
        self::assertFalse($result->success, 'Expected failed result');

        if ($containing !== null) {
            self::assertStringContainsString($containing, $result->error ?? '');
        }
    }

    private function runExtensionMigrations(): void
    {
        $path = $this->extensionMigrationsPath();

        if (! is_dir($path)) {
            return;
        }

        $pdo = DB::connection("ext_{$this->extensionId}")->getPdo();
        $files = glob($path.'/*.sql');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $sql = file_get_contents($file);

            if ($sql !== false) {
                $pdo->exec($sql);
            }
        }
    }
}
