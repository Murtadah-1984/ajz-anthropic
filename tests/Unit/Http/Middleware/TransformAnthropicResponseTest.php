<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Ajz\Anthropic\Http\Middleware\TransformAnthropicResponse;

class TransformAnthropicResponseTest extends TestCase
{
    protected TransformAnthropicResponse $middleware;
    protected Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new TransformAnthropicResponse();
        $this->request = Request::create('/api/test', 'GET');
        $this->request->headers->set('X-Request-ID', 'test-request-123');

        // Default config
        Config::set('anthropic.response.transform_enabled', true);
        Config::set('anthropic.response.envelope_enabled', true);
        Config::set('anthropic.response.include_metadata', true);
        Config::set('anthropic.api.version', '1.0.0');
    }

    public function test_transforms_successful_json_response()
    {
        $response = new JsonResponse(['key' => 'value']);

        $transformed = $this->middleware->handle($this->request, function () use ($response) {
            return $response;
        });

        $data = $transformed->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(200, $data['status']);
        $this->assertEquals('Request processed successfully', $data['message']);
        $this->assertEquals(['key' => 'value'], $data['data']);
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('timestamp', $data['metadata']);
        $this->assertArrayHasKey('duration_ms', $data['metadata']);
        $this->assertEquals('1.0.0', $data['metadata']['version']);
        $this->assertEquals('test-request-123', $data['metadata']['request_id']);
    }

    public function test_transforms_error_response()
    {
        $response = new JsonResponse(
            ['error' => 'Test error', 'type' => 'validation_error'],
            422
        );

        $transformed = $this->middleware->handle($this->request, function () use ($response) {
            return $response;
        });

        $data = $transformed->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals(422, $data['status']);
        $this->assertEquals('Request failed', $data['message']);
        $this->assertNull($data['data']);
        $this->assertEquals('validation_error', $data['error']['type']);
        $this->assertEquals('Test error', $data['error']['message']);
    }

    public function test_respects_disabled_transformation()
    {
        Config::set('anthropic.response.transform_enabled', false);

        $originalData = ['key' => 'value'];
        $response = new JsonResponse($originalData);

        $transformed = $this->middleware->handle($this->request, function () use ($response) {
            return $response;
        });

        $this->assertEquals($originalData, $transformed->getData(true));
    }

    public function test_respects_disabled_envelope()
    {
        Config::set('anthropic.response.envelope_enabled', false);

        $response = new JsonResponse(['key' => 'value']);

        $transformed = $this->middleware->handle($this->request, function () use ($response) {
            return $response;
        });

        $data = $transformed->getData(true);

        $this->assertEquals('value', $data['key']);
        $this->assertArrayHasKey('_metadata', $data);
    }

    public function test_handles_non_json_response()
    {
        $response = new Response('Plain text response', 200, [
            'Content-Type' => 'text/plain'
        ]);

        $transformed = $this->middleware->handle($this->request, function () use ($response) {
            return $response;
        });

        $this->assertEquals($response, $transformed);
    }

    public function test_handles_binary_response()
    {
        $response = new Response('Binary content', 200, [
            'Content-Type' => 'image/jpeg'
        ]);

        $transformed = $this->middleware->handle($this->request, function () use ($response) {
            return $response;
        });

        $this->assertEquals($response, $transformed);
    }

    public function test_preserves_custom_message()
    {
        $response = new JsonResponse([
            'message' => 'Custom success message',
            'data' => ['key' => 'value']
        ]);

        $transformed = $this->middleware->handle($this->request, function () use ($response) {
            return $response;
        });

        $data = $transformed->getData(true);
        $this->assertEquals('Custom success message', $data['message']);
    }

    public function test_handles_pagination_metadata()
    {
        $response = new JsonResponse([
            'data' => ['items' => []],
            'meta' => [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 45,
                'last_page' => 3
            ]
        ]);

        $transformed = $this->middleware->handle($this->request, function () use ($response) {
            return $response;
        });

        $data = $transformed->getData(true);
        $pagination = $this->getPaginationFromResponse($data);

        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(15, $pagination['per_page']);
        $this->assertEquals(45, $pagination['total']);
        $this->assertEquals(3, $pagination['total_pages']);
    }

    public function test_includes_error_details_when_available()
    {
        $response = new JsonResponse([
            'error' => 'Validation failed',
            'type' => 'validation_error',
            'details' => ['field' => 'required'],
            'code' => 'VAL001'
        ], 422);

        $transformed = $this->middleware->handle($this->request, function () use ($response) {
            return $response;
        });

        $data = $transformed->getData(true);
        $error = $data['error'];

        $this->assertEquals('validation_error', $error['type']);
        $this->assertEquals('Validation failed', $error['message']);
        $this->assertEquals(['field' => 'required'], $error['details']);
        $this->assertEquals('VAL001', $error['code']);
    }

    public function test_handles_empty_response()
    {
        $response = new JsonResponse(null, 204);

        $transformed = $this->middleware->handle($this->request, function () use ($response) {
            return $response;
        });

        $data = $transformed->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(204, $data['status']);
        $this->assertNull($data['data']);
    }

    /**
     * Extract pagination metadata from transformed response.
     *
     * @param array $data
     * @return array|null
     */
    protected function getPaginationFromResponse(array $data): ?array
    {
        return $data['metadata']['pagination'] ?? null;
    }
}
