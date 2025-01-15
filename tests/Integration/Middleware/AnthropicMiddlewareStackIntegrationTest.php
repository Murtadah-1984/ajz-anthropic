<?php

namespace Tests\Integration\Middleware;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class AnthropicMiddlewareStackIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up real config (but with test API key)
        Config::set('anthropic.api.key', 'test-key-' . Str::random(32));
        Config::set('anthropic.api.base_url', 'https://api.anthropic.com/v1');
        Config::set('anthropic.api.version', '2024-01-01');

        // Enable all middleware features
        Config::set('anthropic.rate_limiting.enabled', true);
        Config::set('anthropic.logging.enabled', true);
        Config::set('anthropic.cache.enabled', true);
        Config::set('anthropic.response.transform_enabled', true);
    }

    public function test_middleware_stack_handles_real_api_call()
    {
        // Mock the Anthropic API response
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_' . Str::random(24),
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Test response']
                ],
                'model' => 'claude-3-opus-20240229',
                'stop_reason' => 'end_turn',
                'stop_sequence' => null,
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5
                ]
            ], 200)
        ]);

        $response = $this->postJson('/api/anthropic/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Test message']
            ],
            'model' => 'claude-3-opus-20240229'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'type' => 'message',
                    'role' => 'assistant'
                ]
            ])
            ->assertJsonStructure([
                'metadata' => [
                    'timestamp',
                    'duration_ms',
                    'version'
                ]
            ]);

        // Verify rate limit headers
        $response->assertHeader('X-RateLimit-Limit')
            ->assertHeader('X-RateLimit-Remaining');
    }

    public function test_middleware_stack_caches_identical_requests()
    {
        // Clear the cache first
        Cache::flush();

        // Mock the Anthropic API response
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_' . Str::random(24),
                'content' => [
                    ['type' => 'text', 'text' => 'Cached response']
                ]
            ], 200)
        ]);

        // Make first request
        $firstResponse = $this->postJson('/api/anthropic/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Cache test']
            ],
            'model' => 'claude-3-opus-20240229'
        ]);

        // Make second identical request
        $secondResponse = $this->postJson('/api/anthropic/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Cache test']
            ],
            'model' => 'claude-3-opus-20240229'
        ]);

        // Verify both responses are identical
        $this->assertEquals(
            $firstResponse->json('data.content'),
            $secondResponse->json('data.content')
        );

        // Verify only one actual API call was made
        Http::assertSentCount(1);
    }

    public function test_middleware_stack_handles_api_errors()
    {
        // Mock API error response
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'error' => [
                    'type' => 'invalid_request_error',
                    'message' => 'Invalid model specified'
                ]
            ], 400)
        ]);

        $response = $this->postJson('/api/anthropic/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Test message']
            ],
            'model' => 'invalid-model'
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'type' => 'invalid_request_error'
                ]
            ]);
    }

    public function test_middleware_stack_handles_rate_limiting_with_real_headers()
    {
        // Mock API response with rate limit headers
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response(
                ['id' => 'msg_' . Str::random(24)],
                200,
                [
                    'X-RateLimit-Limit' => '60',
                    'X-RateLimit-Remaining' => '59',
                    'X-RateLimit-Reset' => (string)(time() + 60)
                ]
            )
        ]);

        $response = $this->postJson('/api/anthropic/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Rate limit test']
            ],
            'model' => 'claude-3-opus-20240229'
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-RateLimit-Limit', '60')
            ->assertHeader('X-RateLimit-Remaining', '59');
    }

    public function test_middleware_stack_handles_streaming_responses()
    {
        // Mock streaming API response
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_' . Str::random(24),
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Streaming response']
                ],
                'model' => 'claude-3-opus-20240229',
                'stop_reason' => 'end_turn',
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5
                ]
            ], 200, ['Transfer-Encoding' => 'chunked'])
        ]);

        $response = $this->postJson('/api/anthropic/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Stream test']
            ],
            'model' => 'claude-3-opus-20240229',
            'stream' => true
        ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/event-stream');
    }

    public function test_middleware_stack_preserves_api_metadata()
    {
        // Mock API response with metadata
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_' . Str::random(24),
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Test response']
                ],
                'model' => 'claude-3-opus-20240229',
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5
                ],
                'metadata' => [
                    'api_version' => '2024-01-01',
                    'system_fingerprint' => 'fp_' . Str::random(24)
                ]
            ], 200)
        ]);

        $response = $this->postJson('/api/anthropic/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Metadata test']
            ],
            'model' => 'claude-3-opus-20240229'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'usage',
                    'metadata' => [
                        'api_version',
                        'system_fingerprint'
                    ]
                ]
            ]);
    }
}
