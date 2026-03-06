<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Abstract;

use Rundesk\Extension\Sdk\Contracts\Extension;
use Rundesk\Extension\Sdk\Contracts\ExtensionContext;
use Rundesk\Extension\Sdk\Results\ExtensionResult;
use Rundesk\Extension\Sdk\Results\RegisterResult;

abstract class BaseExtension implements Extension
{
    public function register(ExtensionContext $context): RegisterResult
    {
        return RegisterResult::ok();
    }

    /**
     * Dispatches execute('list_events', ...) to handleListEvents(...)
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(string $method, array $input, ExtensionContext $context): ExtensionResult
    {
        $handler = 'handle'.str_replace('_', '', ucwords($method, '_'));

        if (! method_exists($this, $handler)) {
            return ExtensionResult::failed("Unknown method: {$method}");
        }

        return $this->{$handler}($input, $context);
    }

    /**
     * Dispatches dryRun('list_events', ...) to dryRunListEvents(...)
     * Falls back to empty sample if no dry-run handler defined.
     *
     * @param  array<string, mixed>  $input
     */
    public function dryRun(string $method, array $input): ExtensionResult
    {
        $handler = 'dryRun'.str_replace('_', '', ucwords($method, '_'));

        if (method_exists($this, $handler)) {
            return $this->{$handler}($input);
        }

        return ExtensionResult::sample([]);
    }
}
