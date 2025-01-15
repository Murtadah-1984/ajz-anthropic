<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TransformAnthropicResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if (!$this->shouldTransform($response)) {
            return $response;
        }

        $transformed = $this->transformResponse($response, $duration);
        return $response instanceof JsonResponse
            ? $response->setData($transformed)
            : response()->json($transformed, $response->getStatusCode());
    }

    /**
     * Determine if the response should be transformed.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    protected function shouldTransform(Response $response): bool
    {
        if (!config('anthropic.response.transform_enabled', true)) {
            return false;
        }

        // Don't transform binary responses
        if ($this->isBinaryResponse($response)) {
            return false;
        }

        // Only transform JSON responses or responses that can be converted to JSON
        return $response instanceof JsonResponse ||
            str_contains($response->headers->get('Content-Type', ''), 'application/json');
    }

    /**
     * Transform the response data.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  float  $duration
     * @return array
     */
    protected function transformResponse(Response $response, float $duration): array
    {
        $data = $this->getResponseData($response);
        $isSuccess = $response->isSuccessful();
        $envelope = config('anthropic.response.envelope_enabled', true);

        $transformed = [
            'success' => $isSuccess,
            'status' => $response->getStatusCode(),
            'message' => $this->getResponseMessage($data, $isSuccess),
        ];

        // Add metadata if enabled
        if (config('anthropic.response.include_metadata', true)) {
            $transformed['metadata'] = [
                'timestamp' => now()->toIso8601String(),
                'duration_ms' => $duration,
                'version' => config('anthropic.api.version'),
                'request_id' => request()->header('X-Request-ID'),
            ];
        }

        // Add response data
        if ($envelope) {
            $transformed['data'] = $isSuccess ? $data : null;
            if (!$isSuccess) {
                $transformed['error'] = $this->getErrorDetails($data);
            }
        } else {
            return array_merge($data, [
                '_metadata' => $transformed['metadata'] ?? null,
            ]);
        }

        return $transformed;
    }

    /**
     * Get the response data.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return array
     */
    protected function getResponseData(Response $response): array
    {
        if ($response instanceof JsonResponse) {
            return $response->getData(true);
        }

        $content = $response->getContent();
        return json_decode($content, true) ?? ['content' => $content];
    }

    /**
     * Get the response message.
     *
     * @param  array  $data
     * @param  bool  $isSuccess
     * @return string|null
     */
    protected function getResponseMessage(array $data, bool $isSuccess): ?string
    {
        if (isset($data['message'])) {
            return $data['message'];
        }

        return $isSuccess ? 'Request processed successfully' : 'Request failed';
    }

    /**
     * Get error details from response data.
     *
     * @param  array  $data
     * @return array|null
     */
    protected function getErrorDetails(array $data): ?array
    {
        $error = [
            'type' => $data['type'] ?? 'error',
            'message' => $data['error'] ?? $data['message'] ?? 'Unknown error',
        ];

        if (isset($data['details'])) {
            $error['details'] = $data['details'];
        }

        if (isset($data['code'])) {
            $error['code'] = $data['code'];
        }

        return $error;
    }

    /**
     * Determine if the response is binary.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    protected function isBinaryResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'image/') ||
            str_contains($contentType, 'video/') ||
            str_contains($contentType, 'audio/') ||
            str_contains($contentType, 'application/octet-stream');
    }

    /**
     * Add pagination metadata if available.
     *
     * @param  array  $data
     * @return array|null
     */
    protected function getPaginationMetadata(array $data): ?array
    {
        if (!isset($data['meta']['current_page'])) {
            return null;
        }

        return [
            'current_page' => $data['meta']['current_page'],
            'per_page' => $data['meta']['per_page'],
            'total' => $data['meta']['total'],
            'total_pages' => $data['meta']['last_page'],
        ];
    }
}
