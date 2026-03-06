<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Abstract;

use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    /**
     * Per-class connection map — safe when multiple extensions loaded simultaneously.
     *
     * @var array<class-string, string>
     */
    private static array $connectionMap = [];

    /**
     * Global default connection for testing (single extension per process).
     * Production code should use setExtensionConnection on each concrete model.
     */
    private static ?string $defaultConnection = null;

    /**
     * Set the connection for a specific model class.
     * Call on concrete subclasses: MyModel::setExtensionConnection('ext_my-ext');
     */
    public static function setExtensionConnection(string $connectionName): void
    {
        self::$connectionMap[static::class] = $connectionName;
    }

    /**
     * Set a global default connection for all BaseModel subclasses.
     * Intended for test environments where only one extension is active.
     */
    public static function setDefaultConnection(string $connectionName): void
    {
        self::$defaultConnection = $connectionName;
    }

    /**
     * Clear the global default connection.
     */
    public static function clearDefaultConnection(): void
    {
        self::$defaultConnection = null;
    }

    public function getConnectionName(): ?string
    {
        return self::$connectionMap[static::class] ?? self::$defaultConnection;
    }
}
