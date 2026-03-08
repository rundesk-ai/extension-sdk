<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Events;

final class EventHooks
{
    public const CONFIG_UPDATED = 'config.updated';

    public const RESOURCE_UPDATED = 'resource.updated';

    public const SYSTEM_STATUS_UPDATED = 'system.status_updated';

    public const CHAT_MESSAGE_SENT = 'chat.message_sent';

    public const EXTENSION_EXECUTED = 'extension.executed';

    public const EXTENSION_INSTALLED = 'extension.installed';

    public const EXTENSION_UNINSTALLED = 'extension.uninstalled';

    public const EXTENSION_ACTIVATED = 'extension.activated';

    public const EXTENSION_DEACTIVATED = 'extension.deactivated';

    public const EXTENSION_ACCOUNT_CONNECTED = 'extension.account_connected';

    public const EXTENSION_ACCOUNT_UPDATED = 'extension.account_updated';

    public const EXTENSION_ACCOUNT_DISCONNECTED = 'extension.account_disconnected';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_filter(
            (new \ReflectionClass(self::class))->getConstants(),
            fn (mixed $v): bool => is_string($v),
        ));
    }
}
