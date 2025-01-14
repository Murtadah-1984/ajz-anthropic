# Laravel Anthropic

A Laravel package for integrating with the Anthropic AI API, providing access to Claude and organization management features.

## Installation

You can install the package via composer:

```bash
composer require ajz/anthropic
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="anthropic-config"
```

Add these environment variables to your `.env` file:

```env
ANTHROPIC_API_KEY=your-api-key
ANTHROPIC_ADMIN_API_KEY=your-admin-api-key
ANTHROPIC_API_VERSION=2023-06-01
```

## Usage

```php
// Using the facade
use Ajz\Anthropic\Facades\Anthropic;

// Send a message to Claude
$response = Anthropic::messages()->createMessage(
    'claude-3-5-sonnet-20241022',
    [
        ['role' => 'user', 'content' => 'Hello, Claude']
    ]
);

// Manage workspaces
$workspace = Anthropic::workspaces()->createWorkspace('My Workspace');

// Manage workspace members
$member = Anthropic::workspaceMembers()->addMember(
    $workspace->id,
    'user_id',
    WorkspaceMember::ROLE_DEVELOPER
);

// Manage organization invites
$invite = Anthropic::invites()->createInvite(
    'user@example.com',
    User::ROLE_DEVELOPER
);

// Manage API keys
$keys = Anthropic::apiKeys()->listApiKeys([
    'status' => ApiKey::STATUS_ACTIVE
]);
```

## Available Services

- `messages()` - Claude messaging API
- `workspaces()` - Workspace management
- `workspaceMembers()` - Workspace member management
- `organization()` - Organization management
- `invites()` - Organization invite management
- `apiKeys()` - API key management

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
