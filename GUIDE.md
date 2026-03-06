# Rundesk Extension SDK Guide

Build extensions that integrate external services into Rundesk. Extensions can fetch data, sync accounts, respond to events, and expose methods for the AI agent to call.

## Prerequisites

- PHP 8.4+
- Composer 2.x

## Scaffold a New Extension

The SDK ships a CLI to generate extension boilerplate:

```bash
mkdir my-extension && cd my-extension
composer init --name="your-org/extension-my-service" --type="rundesk-extension" --require="rundesk-ai/extension-sdk:^1.0" -n
composer install
./vendor/bin/rundesk new my-service --name="My Service" --description="Integrate with My Service" --database --oauth
```

Or scaffold manually — see the project structure below.

### CLI Options

| Flag | Description |
|------|-------------|
| `--name` | Display name |
| `--description` | Short description |
| `--database` | Include database support (migrations, model) |
| `--oauth` | Include OAuth credential scaffold |
| `--minimal` | Bare minimum files only |
| `-o` | Output directory (default: current dir) |

## Project Structure

```
my-extension/
├── composer.json          # Package config, SDK dependency
├── rundesk.json           # Extension manifest (required)
├── SKILL.md               # Method docs for agent discovery (required)
├── src/
│   ├── Extension.php      # Entry file extending BaseExtension
│   └── Models/            # Eloquent models extending BaseModel
├── migrations/            # Raw SQL migration files
├── tests/
│   ├── Pest.php           # Pest configuration
│   └── Unit/
│       └── ExtensionTest.php
└── phpunit.xml
```

## The Manifest (rundesk.json)

The manifest declares everything about your extension. Rundesk reads this to know what your extension needs and can do.

```json
{
    "id": "my-service",
    "name": "My Service",
    "version": "1.0.0",
    "description": "Short description of what this extension does",
    "author": {
        "name": "Your Name",
        "url": "https://github.com/your-org/extension-my-service"
    },
    "entry": "src/Extension.php",
    "skill_guide": "SKILL.md",
    "credentials": {},
    "database": {
        "enabled": true,
        "migrations": "migrations"
    },
    "config": {},
    "schedules": [],
    "hooks": [],
    "network": {
        "allowed_hosts": []
    }
}
```

### Required Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Unique identifier. Alphanumeric, hyphens, underscores only. |
| `name` | string | Human-readable display name. |
| `version` | string | Semver version string. |
| `entry` | string | Path to the PHP entry file (relative to extension root). |
| `skill_guide` | string | Path to the SKILL.md file. |

### Credentials

Extensions can declare credentials they need. Two types are supported:

**OAuth:**
```json
{
    "credentials": {
        "google": {
            "type": "oauth",
            "label": "Google Account",
            "account_display": ["email"],
            "oauth": {
                "auth_url": "https://accounts.google.com/o/oauth2/v2/auth",
                "token_url": "https://oauth2.googleapis.com/token",
                "scopes": ["openid", "email", "profile"],
                "pkce": true,
                "extra_params": {
                    "access_type": "offline",
                    "prompt": "consent"
                }
            }
        }
    }
}
```

**API Key:**
```json
{
    "credentials": {
        "api": {
            "type": "api_key",
            "label": "API Key"
        }
    }
}
```

Retrieve credentials at runtime via the context:
```php
$token = $context->credential('google', $accountId);
```

### Config Schema

Define user-configurable settings. Rundesk renders a settings form from this schema.

```json
{
    "config": {
        "client_id": {
            "default": "",
            "label": "Client ID",
            "type": "string",
            "role": "oauth_credential"
        },
        "client_secret": {
            "default": "",
            "label": "Client Secret",
            "type": "secret",
            "role": "oauth_credential"
        },
        "max_results": {
            "default": 50,
            "label": "Max Results",
            "type": "number",
            "min": 10,
            "max": 500
        }
    }
}
```

Supported types: `string`, `secret`, `number`, `boolean`. Access at runtime:
```php
$maxResults = $context->config('max_results', 50);
```

### Schedules

Register background tasks that run on a cron schedule:

```json
{
    "schedules": [
        {
            "name": "Sync Data",
            "method": "syncData",
            "cron": "0 */6 * * *"
        }
    ]
}
```

The `method` maps to a handler in your Extension class: `syncData` dispatches to `handleSyncData()`.

### Hooks

React to Rundesk system events:

```json
{
    "hooks": [
        {
            "event": "config.updated",
            "method": "onConfigUpdated"
        }
    ]
}
```

Available events: `config.updated`, `resource.updated`, `system.status_updated`, `chat.message_sent`, `extension.executed`.

### Network

Declare which external hosts your extension communicates with. Rundesk enforces this allowlist.

```json
{
    "network": {
        "allowed_hosts": [
            "api.example.com",
            "oauth.example.com"
        ]
    }
}
```

### Database

Enable a per-extension SQLite database with raw SQL migrations:

```json
{
    "database": {
        "enabled": true,
        "migrations": "migrations"
    }
}
```

## Writing the Entry File

Your entry file extends `BaseExtension`. Each method your extension exposes maps to a `handle*` method.

```php
<?php

declare(strict_types=1);

namespace YourOrg\Extension\MyService;

use Illuminate\Support\Facades\Http;
use Rundesk\Extension\Sdk\Abstract\BaseExtension;
use Rundesk\Extension\Sdk\Contracts\ExtensionContext;
use Rundesk\Extension\Sdk\Results\ExtensionResult;

class Extension extends BaseExtension
{
    /**
     * Fetch items from the API.
     */
    public function handleGetItems(array $input, ExtensionContext $context): ExtensionResult
    {
        $token = $context->credential('api');

        $response = Http::withToken($token)
            ->get('https://api.example.com/items', [
                'limit' => $context->config('max_results', 50),
            ]);

        if ($response->failed()) {
            return ExtensionResult::failed('Failed to fetch items: ' . $response->status());
        }

        return ExtensionResult::ok(['items' => $response->json('data')]);
    }

    /**
     * Dry run returns sample data without hitting the real API.
     */
    public function dryRunGetItems(array $input): ExtensionResult
    {
        return ExtensionResult::sample([
            'items' => [
                ['id' => 1, 'name' => 'Sample Item', 'status' => 'active'],
            ],
        ]);
    }
}
```

### Method Dispatch Convention

When Rundesk calls `execute('get_items', ...)`, BaseExtension converts `get_items` to `handleGetItems` and invokes it. The naming convention is:

| Manifest/Agent Call | Handler Method | Dry Run Method |
|---------------------|----------------|----------------|
| `get_items` | `handleGetItems()` | `dryRunGetItems()` |
| `sync_data` | `handleSyncData()` | `dryRunSyncData()` |
| `validateAccount` | `handleValidateAccount()` | `dryRunValidateAccount()` |

Handler signature: `(array $input, ExtensionContext $context): ExtensionResult`
Dry run signature: `(array $input): ExtensionResult`

### Using ExtensionContext

The context object is your interface to the Rundesk runtime:

```php
// Logging
$context->log()->info('Syncing accounts...');
$context->log()->error('API call failed');

// Credentials
$token = $context->credential('google', $accountId);
$accounts = $context->accounts('google');

// Database
$rows = $context->db()->table('my_table')->get();

// Config
$value = $context->config('max_results', 50);

// Storage
$path = $context->storagePath('cache/data.json');

// Extension ID
$id = $context->extensionId();
```

### The register() Method

Override `register()` to run setup logic when the extension is activated, updated, or re-enabled. Return `RegisterResult::ok()` on success or `RegisterResult::failed('reason')` on failure.

```php
use Rundesk\Extension\Sdk\Results\RegisterResult;

public function register(ExtensionContext $context): RegisterResult
{
    // Verify connectivity, seed data, etc.
    return RegisterResult::ok();
}
```

The default implementation returns `RegisterResult::ok()`.

## Using Models

Extend `BaseModel` to work with your extension's database tables via Eloquent:

```php
<?php

declare(strict_types=1);

namespace YourOrg\Extension\MyService\Models;

use Rundesk\Extension\Sdk\Abstract\BaseModel;

class Account extends BaseModel
{
    protected $table = 'accounts';

    protected $fillable = [
        'account_id',
        'email',
        'name',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
```

At runtime, Rundesk sets the connection on your models automatically. In handlers, you can use the model directly:

```php
$accounts = Account::all();
Account::updateOrCreate(
    ['account_id' => $id],
    ['email' => $email, 'synced_at' => now()],
);
```

### Migrations

Write raw SQL files in your `migrations/` directory. They execute in alphabetical order.

```sql
-- migrations/001_create_accounts_table.sql
CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    email TEXT NOT NULL,
    name TEXT,
    synced_at TEXT NOT NULL
);
```

## Adding OAuth Credentials

1. **Declare in manifest** — Add the credential under `credentials` with `type: "oauth"` and OAuth config (auth_url, token_url, scopes, pkce).

2. **Add client config** — Add `client_id` and `client_secret` to the `config` section with `"role": "oauth_credential"`.

3. **Implement validateAccount** — This hook is called after OAuth to enrich the account with display info:

```php
public function handleValidateAccount(array $input, ExtensionContext $context): ExtensionResult
{
    $accountId = !empty($input['account_id']) ? (int) $input['account_id'] : null;
    $token = $context->credential('my_provider', $accountId);

    $response = Http::withToken($token)
        ->get('https://api.example.com/me');

    if ($response->failed()) {
        return ExtensionResult::failed('Could not validate account');
    }

    $user = $response->json();

    return ExtensionResult::ok([
        'label' => $user['email'] ?? 'Account',
        'metadata' => [
            'email' => $user['email'] ?? null,
            'name' => $user['name'] ?? null,
        ],
    ]);
}
```

4. **Use credentials in handlers** — Call `$context->credential('my_provider', $accountId)` to get the access token. Rundesk handles token refresh automatically.

## Adding Schedules

1. **Declare in manifest:**
```json
{
    "schedules": [
        {
            "name": "Sync Account Info",
            "method": "syncAccountInfo",
            "cron": "0 */6 * * *"
        }
    ]
}
```

2. **Implement the handler:**
```php
public function handleSyncAccountInfo(array $input, ExtensionContext $context): ExtensionResult
{
    $accounts = $context->accounts('my_provider');
    $synced = 0;

    foreach ($accounts as $account) {
        // sync logic...
        $synced++;
    }

    return ExtensionResult::ok(['synced' => $synced]);
}
```

## Writing the SKILL.md

The SKILL.md file tells the AI agent what your extension can do. Write it as clear method documentation:

```markdown
# My Service

Short description of the extension's capabilities.

## Methods

### getItems

Fetches items from the My Service API.

**Input:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| category | string | No | Filter by category. |
| limit | integer | No | Max items to return. Defaults to config value. |

**Output:**
\```json
{
  "items": [
    {
      "id": 1,
      "name": "Example Item",
      "status": "active"
    }
  ]
}
\```
```

Include every method the agent can call. Be precise about input parameters and output shapes.

## Testing

The SDK provides `ExtensionTestCase` with an in-memory SQLite database and `FakeContext` for isolated testing.

### Setup

**phpunit.xml:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

**tests/Pest.php:**
```php
<?php

declare(strict_types=1);

use YourOrg\Extension\MyService\Tests\TestCase;

uses(TestCase::class)->in('Unit');
```

**tests/TestCase.php:**
```php
<?php

declare(strict_types=1);

namespace YourOrg\Extension\MyService\Tests;

use Rundesk\Extension\Sdk\Testing\ExtensionTestCase;

class TestCase extends ExtensionTestCase
{
    protected string $extensionId = 'my-service';

    protected function extensionMigrationsPath(): string
    {
        return __DIR__ . '/../migrations';
    }
}
```

### Writing Tests

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use YourOrg\Extension\MyService\Extension;
use YourOrg\Extension\MyService\Models\Account;

beforeEach(function () {
    $this->extension = new Extension();
});

it('fetches items from API', function () {
    Http::fake([
        'api.example.com/items*' => Http::response([
            'data' => [
                ['id' => 1, 'name' => 'Item 1'],
            ],
        ]),
    ]);

    $context = $this->fakeContext(
        credentials: ['api' => 'test-token'],
        config: ['max_results' => 10],
    );

    $result = $this->extension->execute('get_items', [], $context);

    $this->assertResultOk($result);
    expect($result->toArray()['data']['items'])->toHaveCount(1);
});

it('returns sample data for dry run', function () {
    $result = $this->extension->dryRun('get_items', []);

    $this->assertResultOk($result);
    expect($result->toArray()['isDryRun'])->toBeTrue();
    expect($result->toArray()['data']['items'])->toBeArray();
});

it('handles API failures', function () {
    Http::fake([
        'api.example.com/*' => Http::response([], 500),
    ]);

    $context = $this->fakeContext(credentials: ['api' => 'test-token']);
    $result = $this->extension->execute('get_items', [], $context);

    $this->assertResultFailed($result, 'Failed to fetch items');
});

it('syncs accounts to database', function () {
    Http::fake([
        'api.example.com/me' => Http::response([
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]),
    ]);

    $context = $this->fakeContext(credentials: ['api' => 'test-token']);
    Account::setExtensionConnection($context->db()->getName());

    $this->extension->execute('sync_data', [], $context);

    expect(Account::count())->toBe(1);
    expect(Account::first()->email)->toBe('test@example.com');
});
```

### Assertions

| Method | Description |
|--------|-------------|
| `$this->assertResultOk($result)` | Assert the result succeeded |
| `$this->assertResultFailed($result)` | Assert the result failed |
| `$this->assertResultFailed($result, 'message')` | Assert failed with specific error substring |

### FakeContext

Create a fake context with test credentials and config:

```php
$context = $this->fakeContext(
    credentials: [
        'google' => 'fake-access-token',
    ],
    config: [
        'max_results' => 25,
    ],
);

// Access the test database
$context->db()->table('accounts')->insert([...]);

// Check logs
$context->logger()->assertLogged('info', 'Syncing');
```

## Validation & Quality

### Validate Manifest

```bash
./vendor/bin/rundesk validate:manifest
```

Checks all required fields, file paths, schedule cron formats, and hook event names.

### Code Quality

```bash
./vendor/bin/pint          # Laravel Pint (code style)
./vendor/bin/phpstan analyse --level=5 src  # Static analysis
./vendor/bin/pest          # Run tests
```

## Dev Execution

Test methods locally without a running Rundesk instance:

```bash
# Execute a method with fake context (dry run mode)
./vendor/bin/rundesk dev:serve get_events

# Pass input as JSON
./vendor/bin/rundesk dev:serve get_events --input='{"start_date":"2026-01-01"}'

# Specify extension path
./vendor/bin/rundesk dev:serve get_events --path=/path/to/extension
```

## Publishing

### As a Composer Package

1. Push your extension to a Git repository
2. Users install via Composer or add it as a repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/your-org/extension-my-service"
        }
    ],
    "require": {
        "your-org/extension-my-service": "^1.0"
    }
}
```

### As a ZIP Upload

Package your extension as a ZIP file. Include all source files, `rundesk.json`, `SKILL.md`, and migrations. Exclude `vendor/`, `tests/`, and dev files.

## Full Example

See the [Google Calendar extension](https://github.com/rundesk-ai/extension-google-calendar) for a complete, production-ready example covering OAuth, database models, scheduled syncs, and full test coverage.
