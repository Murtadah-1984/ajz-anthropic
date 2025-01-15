<?php

namespace Ajz\Anthropic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Ajz\Anthropic\Services\Organization\ApiKeyService;

class GenerateApiKeyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anthropic:key:generate
                          {--name= : Name of the API key}
                          {--expires= : Number of days until the key expires}
                          {--scope=* : Scopes to assign to the key}
                          {--org= : Organization ID to generate the key for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new Anthropic API key';

    /**
     * The API key service instance.
     *
     * @var ApiKeyService
     */
    protected ApiKeyService $apiKeyService;

    /**
     * Create a new command instance.
     *
     * @param ApiKeyService $apiKeyService
     */
    public function __construct(ApiKeyService $apiKeyService)
    {
        parent::__construct();
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Enter a name for the API key');
        $expires = $this->option('expires') ?? $this->ask('Enter number of days until expiration (leave empty for no expiration)');
        $scopes = $this->option('scope') ?: $this->askForScopes();
        $orgId = $this->option('org') ?? $this->askForOrganization();

        try {
            $key = $this->generateKey($name, $expires, $scopes, $orgId);
            $this->displayKey($key);
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to generate API key: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Generate the API key.
     *
     * @param string $name
     * @param string|null $expires
     * @param array $scopes
     * @param string $orgId
     * @return array
     */
    protected function generateKey(string $name, ?string $expires, array $scopes, string $orgId): array
    {
        $expiresAt = $expires ? now()->addDays((int) $expires) : null;

        return $this->apiKeyService->createApiKey([
            'name' => $name,
            'key' => 'sk-' . Str::random(48), // Anthropic-style key
            'organization_id' => $orgId,
            'expires_at' => $expiresAt,
            'scopes' => $scopes,
            'created_at' => now(),
            'last_used_at' => null,
        ]);
    }

    /**
     * Ask for API key scopes.
     *
     * @return array
     */
    protected function askForScopes(): array
    {
        $availableScopes = [
            'messages:read',
            'messages:write',
            'agents:read',
            'agents:write',
            'teams:read',
            'teams:write',
        ];

        return $this->choice(
            'Select scopes (comma-separated numbers)',
            $availableScopes,
            null,
            null,
            true
        );
    }

    /**
     * Ask for organization ID.
     *
     * @return string
     */
    protected function askForOrganization(): string
    {
        $organizations = $this->apiKeyService->listOrganizations();

        if (empty($organizations)) {
            throw new \RuntimeException('No organizations found. Please create an organization first.');
        }

        $choices = collect($organizations)->mapWithKeys(function ($org) {
            return [$org['id'] => "{$org['name']} ({$org['id']})"];
        })->toArray();

        return $this->choice(
            'Select organization',
            $choices,
            null,
            null,
            false
        );
    }

    /**
     * Display the generated API key.
     *
     * @param array $key
     * @return void
     */
    protected function displayKey(array $key): void
    {
        $this->info('API key generated successfully!');
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['Key', $key['key']],
                ['Name', $key['name']],
                ['Organization ID', $key['organization_id']],
                ['Expires At', $key['expires_at'] ?? 'Never'],
                ['Scopes', implode(', ', $key['scopes'])],
            ]
        );
        $this->newLine();
        $this->warn('Make sure to copy your API key now. You won\'t be able to see it again!');
    }
}
