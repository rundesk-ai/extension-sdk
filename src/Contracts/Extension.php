<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Contracts;

use Rundesk\Extension\Sdk\Results\ExtensionResult;
use Rundesk\Extension\Sdk\Results\RegisterResult;

interface Extension
{
    /**
     * Called on activate, update, and re-enable.
     * Nothing goes live until this returns RegisterResult::ok().
     */
    public function register(ExtensionContext $context): RegisterResult;

    /**
     * Execute a named method. Called by agents and schedules.
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(string $method, array $input, ExtensionContext $context): ExtensionResult;

    /**
     * Return sample data without hitting real APIs.
     * Used by agent planning and approval previews.
     *
     * @param  array<string, mixed>  $input
     */
    public function dryRun(string $method, array $input): ExtensionResult;
}
