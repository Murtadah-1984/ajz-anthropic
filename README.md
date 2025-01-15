# Laravel Anthropic

A Laravel package for integrating with the Anthropic AI API, providing access to Claude, AI Agents, automated sessions, and external system integrations.

## 🚀 Features

- 🤖 AI Agents with specialized roles (Developer, Architect, Security Expert)
- 🔄 Automated sessions for various workflows (API Design, Code Review, Architecture Review)
- 🔌 External system integrations (Google Docs, Airtable, Make.com, N8N)
- 🚀 High performance with caching and queue management
- 🔒 Enterprise-grade security with proper authentication and rate limiting
- 📚 Comprehensive documentation and examples

## 📋 Requirements

- PHP 8.2 or higher
- Laravel 10.x or higher
- Anthropic API key
- Redis (optional, for caching and queues)

## ⚡ Installation

1. Install via Composer:
```bash
composer require ajz/anthropic
```

2. Publish configuration:
```bash
php artisan vendor:publish --tag="anthropic-config"
```

3. Add environment variables to `.env`:
```env
ANTHROPIC_API_KEY=your-api-key
ANTHROPIC_API_VERSION=2023-06-01
```

## 🚦 Quick Start

```php
use Ajz\Anthropic\Facades\AI;

// Start an API design session
$session = AI::startSession('api_design', [
    'resource' => 'products',
    'features' => ['crud', 'bulk_operations']
]);

// Use a specialized agent
$developer = AI::agent('developer');
$response = $developer->handleRequest([
    'type' => 'code_review',
    'content' => $codeSnippet
]);

// Create a team
$team = AI::createTeam('development', [
    'agents' => ['developer', 'security_expert']
]);
```

## 📖 Documentation

- [Package Details](package-details.md) - Detailed package structure and components
- [Improvement Plan](improvement-plan.md) - Upcoming improvements and roadmap
- [Configuration Guide](#configuration) - Detailed configuration options
- [API Reference](#api-reference) - Complete API documentation
- [Testing Guide](#testing) - Testing setup and examples

## ⚙️ Configuration

The package can be configured through the `config/anthropic.php` file:

```php
return [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'base_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1'),
    
    'defaults' => [
        'model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-5-sonnet-20241022'),
        'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 1024),
    ],
    
    'cache' => [
        'enabled' => env('AI_ASSISTANT_CACHE_ENABLED', true),
        'ttl' => env('AI_ASSISTANT_CACHE_TTL', 3600),
    ],
];
```

## 🔧 Available Sessions

- `api_design` - API design and implementation
- `code_review` - Code review sessions
- `architecture_review` - Architecture review sessions
- `security_audit` - Security review sessions
- `performance_optimization` - Performance analysis
- `documentation` - Documentation sprints
- [View all sessions](package-details.md#session-management)

## 🤖 Available Agents

- `developer` - Code generation and review
- `architect` - System design and architecture
- `security_expert` - Security analysis
- `performance_expert` - Performance optimization
- [View all agents](package-details.md#specialized-agents)

## 🧪 Testing

Run the test suite:

```bash
composer test
```

Run with coverage report:

```bash
composer test-coverage
```

## 🤝 Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

1. Fork the repository
2. Create your feature branch
3. Run the tests
4. Create a pull request

## 🔒 Security

If you discover any security-related issues, please email security@example.com instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## ✨ Credits

- [Murtadah Haddad](https://github.com/username)
- [All Contributors](../../contributors)

## 🔍 Roadmap

See our [Improvement Plan](improvement-plan.md) for upcoming features and enhancements.

## 🎯 Performance Considerations

- Uses multi-level caching strategy
- Implements queue system for long-running tasks
- Optimizes API calls with rate limiting
- Manages resource allocation efficiently

## 🛡️ Security Implementations

- API key management
- Request validation and sanitization
- Rate limiting
- Proper error handling

## 📚 Additional Resources

- [API Documentation](docs/api.md)
- [Examples](docs/examples.md)
- [FAQ](docs/faq.md)
- [Troubleshooting](docs/troubleshooting.md)
