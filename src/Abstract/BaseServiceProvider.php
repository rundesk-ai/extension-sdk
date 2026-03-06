<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Abstract;

use Illuminate\Support\ServiceProvider;

abstract class BaseServiceProvider extends ServiceProvider
{
    /**
     * Load SQL migration files from a directory.
     * Use for dynamic migration paths — prefer declaring migrations in rundesk.json.
     */
    protected function loadExtensionMigrationsFrom(string $path): void
    {
        if ($this->app->bound('rundesk.extension.migrator')) {
            $this->app->make('rundesk.extension.migrator')->addPath($path);
        }
    }

    /**
     * Register extension webhook routes.
     */
    protected function loadWebhookRoutesFrom(string $path): void
    {
        if (file_exists($path)) {
            $this->loadRoutesFrom($path);
        }
    }
}
