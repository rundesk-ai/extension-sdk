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
     * Set the extension connection for this model class and all BaseModel subclasses
     * that don't have their own explicit connection set.
     */
    public static function setExtensionConnection(string $connectionName): void
    {
        self::$connectionMap[static::class] = $connectionName;

        // Also set on the base class so subclasses without explicit binding inherit it
        self::$connectionMap[self::class] = $connectionName;
    }

    public function getConnectionName(): ?string
    {
        return self::$connectionMap[static::class] ?? self::$connectionMap[self::class] ?? null;
    }
}
