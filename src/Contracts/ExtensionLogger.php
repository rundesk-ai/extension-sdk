<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Contracts;

interface ExtensionLogger
{
    public function debug(string $message): void;

    public function info(string $message): void;

    public function warning(string $message): void;

    public function error(string $message): void;

    public function critical(string $message): void;
}
