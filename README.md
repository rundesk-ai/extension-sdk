# Rundesk Extension SDK

[![Automated Checks](https://github.com/rundesk-ai/extension-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/rundesk-ai/extension-sdk/actions/workflows/ci.yml)
[![Test Coverage](https://codecov.io/gh/rundesk-ai/extension-sdk/graph/badge.svg)](https://codecov.io/gh/rundesk-ai/extension-sdk)

The official SDK for building [Rundesk](https://rundesk.ai) extensions. Install it as your single Composer dependency to get contracts, base classes, testing infrastructure, and CLI tooling.

Both Rundesk and extensions depend on this package — it's the shared contract that keeps both sides in sync.

## Requirements

- PHP 8.4+
- Laravel 12+

## Installation

```bash
composer require rundesk-ai/extension-sdk
```

## Quick Start

Every extension needs two things: an entry file that returns an object implementing the SDK contract, and a `rundesk.json` manifest.

### 1. Create the entry file

```php
// src/Extension.php
<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Abstract\BaseExtension;
use Rundesk\Extension\Sdk\Contracts\ExtensionContext;
use Rundesk\Extension\Sdk\Results\ExtensionResult;

return new class extends BaseExtension
{
    protected function handleListEvents(array $input, ExtensionContext $context): ExtensionResult
    {
        $token = $context->credential('api_key');

        // Your logic here...

        return ExtensionResult::ok(['events' => []]);
    }
};
```

`BaseExtension` auto-dispatches `execute('list_events', ...)` to `handleListEvents(...)` — no match statements needed.

### 2. Create the manifest

```json
{
    "id": "my-extension",
    "name": "My Extension",
    "version": "1.0.0",
    "entry": "src/Extension.php",
    "skill_guide": "SKILL.md",
    "credentials": {},
    "config": {}
}
```

### 3. Scaffold with the CLI

Or skip the manual setup entirely:

```bash
./vendor/bin/rundesk new my-extension --name="My Extension" --database
```

## Key Concepts

### Contracts

| Interface | Purpose |
|-----------|---------|
| `Contracts\Extension` | The contract Rundesk calls — `register()`, `execute()`, `dryRun()` |
| `Contracts\ExtensionContext` | Runtime context — credentials, config, database, logging, storage |
| `Contracts\ExtensionLogger` | Structured logging interface |

### Base Classes

| Class | Purpose |
|-------|---------|
| `Abstract\BaseExtension` | Auto-dispatches methods, default `register()` |
| `Abstract\BaseModel` | Eloquent model wired to the extension's SQLite DB |
| `Abstract\BaseJob` | Queue job base — store primitives, rebuild context in `handle()` |
| `Abstract\BaseServiceProvider` | Service provider with migration and route helpers |

### Result Objects

```php
// Success
ExtensionResult::ok(['events' => $events]);

// Failure
ExtensionResult::failed('API returned 401');

// Dry run (sample data for agent planning)
ExtensionResult::sample(['events' => [['id' => 1, 'title' => 'Example']]]);

// Registration
RegisterResult::ok();
RegisterResult::failed('Missing API credentials');
```

### Testing

The SDK ships test infrastructure built on Orchestra Testbench:

```php
use Rundesk\Extension\Sdk\Testing\ExtensionTestCase;

class SyncTest extends ExtensionTestCase
{
    protected string $extensionId = 'my-extension';

    protected function extensionMigrationsPath(): string
    {
        return __DIR__ . '/../../migrations';
    }

    public function test_sync_returns_data(): void
    {
        $context = $this->fakeContext(['api_key' => 'test-token']);
        $extension = require __DIR__ . '/../../src/Extension.php';

        $result = $extension->execute('list_events', [], $context);

        $this->assertResultOk($result);
    }
}
```

`FakeContext` provides an in-memory implementation of `ExtensionContext` — no Rundesk installation needed.

### CLI

```bash
# Scaffold a new extension
./vendor/bin/rundesk new my-extension --name="My Extension"

# Validate rundesk.json
./vendor/bin/rundesk validate:manifest

# Execute a method with fake context
./vendor/bin/rundesk dev:serve list_events --input='{"from": "2026-01-01"}'
```

## Contributing

### Setup

```bash
git clone https://github.com/rundesk-ai/extension-sdk.git
cd extension-sdk
composer install
```

### Linting

```bash
# Check code style
./vendor/bin/pint --test

# Fix code style
./vendor/bin/pint

# Static analysis (level 5)
./vendor/bin/phpstan analyse src --level=5
```

### Testing

```bash
# Run all tests
./vendor/bin/pest

# Run a specific test file
./vendor/bin/pest tests/Unit/Abstract/BaseExtensionTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

All contributions must pass Pint, PHPStan level 5, and the full test suite before merging.

## License

MIT
