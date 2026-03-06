<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Abstract\BaseExtension;
use Rundesk\Extension\Sdk\Contracts\ExtensionContext;
use Rundesk\Extension\Sdk\Results\ExtensionResult;

return new class extends BaseExtension
{
    /**
     * @param  array<string, mixed>  $input
     */
    protected function handleExample(array $input, ExtensionContext $context): ExtensionResult
    {
        $context->log()->info('Example method executed');

        return ExtensionResult::ok(['message' => 'Hello from {{NAME}}!']);
    }
};
