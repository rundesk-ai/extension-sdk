<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Events;

final class KnownEvents
{
    public const CONFIG_UPDATED = 'config.updated';

    public const RESOURCE_UPDATED = 'resource.updated';

    public const SYSTEM_STATUS_UPDATED = 'system.status_updated';

    public const CHAT_MESSAGE_SENT = 'chat.message_sent';

    public const EXTENSION_EXECUTED = 'extension.executed';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CONFIG_UPDATED,
            self::RESOURCE_UPDATED,
            self::SYSTEM_STATUS_UPDATED,
            self::CHAT_MESSAGE_SENT,
            self::EXTENSION_EXECUTED,
        ];
    }
}
