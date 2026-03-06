<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Testing;

use Rundesk\Extension\Sdk\Contracts\ExtensionLogger;

class FakeLogger implements ExtensionLogger
{
    /**
     * @var list<array{level: string, message: string}>
     */
    public array $entries = [];

    public function debug(string $message): void
    {
        $this->entries[] = ['level' => 'DEBUG', 'message' => $message];
    }

    public function info(string $message): void
    {
        $this->entries[] = ['level' => 'INFO', 'message' => $message];
    }

    public function warning(string $message): void
    {
        $this->entries[] = ['level' => 'WARNING', 'message' => $message];
    }

    public function error(string $message): void
    {
        $this->entries[] = ['level' => 'ERROR', 'message' => $message];
    }

    public function critical(string $message): void
    {
        $this->entries[] = ['level' => 'CRITICAL', 'message' => $message];
    }

    public function assertLogged(string $level, string $containing): void
    {
        $level = strtoupper($level);

        foreach ($this->entries as $entry) {
            if ($entry['level'] === $level && str_contains($entry['message'], $containing)) {
                return;
            }
        }

        throw new \PHPUnit\Framework\AssertionFailedError(
            "Expected log entry [{$level}] containing \"{$containing}\" not found. Logged: ".json_encode($this->entries)
        );
    }
}
