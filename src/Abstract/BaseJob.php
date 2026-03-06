<?php

declare(strict_types=1);

namespace Rundesk\Extension\Sdk\Abstract;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Base job for extension queue work.
 *
 * Jobs cannot serialize ExtensionContext — store only primitives
 * (extensionId, accountId) and rebuild context in handle().
 */
abstract class BaseJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    abstract public function handle(): void;
}
