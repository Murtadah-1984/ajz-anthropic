<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class LogRequests
{
    /**
     * Fields that should be masked in logs.
     *
     * @var array
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
        'credit_card',
        'card_number',
        'cvv',
    ];

    /**
     * Headers that should be masked in logs.
     *
     * @var array
     */
    protected array $sensitiveHeaders = [
        'authorization',
        'cookie',
        'x-api-key',
        'x-csrf-token',
        'x-xsrf-token',
    ];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip logging if disabled
        if (!config('anthropic.logging.requests.enabled', false)) {
            return $next($request);
        }

        // Generate request ID
        $requestId = (string) Str::uuid();
        $request->headers->set('X-Request-ID', $requestId);

        // Log request
        $this->logRequest($request, $requestId);

        // Process request and capture response
        $response = $next($request);

        // Log response
        $this->logResponse($response, $requestId, $request);

        // Add request ID to response headers
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    /**
     * Log the request details.
     *
     * @param Request $request
     * @param string $requestId
     * @return void
     */
    protected function logRequest(Request $request, string $requestId): void
    {
        $config = config('anthropic.logging.requests');
        $logData = [
            'id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Add headers if configured
        if (!empty($config['include_headers'])) {
            $logData['headers'] = $this->sanitizeHeaders($request->headers->all());
        }

        // Add query parameters if configured
        if (!empty($config['include_query'])) {
            $logData['query'] = $this->sanitizeData($request->query());
        }

        // Add request body if configured
        if (!empty($config['include_body']) && $this->shouldLogBody($request)) {
            $logData['body'] = $this->sanitizeData($request->except($this->sensitiveFields));
        }

        // Add user information if configured and available
        if (!empty($config['include_user']) && $request->user()) {
            $logData['user'] = [
                'id' => $request->user()->id,
                'email' => $request->user()->email,
            ];
        }

        // Add timing information
        $logData['timestamp'] = now()->toIso8601String();

        Log::channel($config['channel'] ?? 'daily')
            ->info('Incoming request', $logData);
    }

    /**
     * Log the response details.
     *
     * @param Response $response
     * @param string $requestId
     * @param Request $request
     * @return void
     */
    protected function logResponse(Response $response, string $requestId, Request $request): void
    {
        $config = config('anthropic.logging.responses');
        $logData = [
            'id' => $requestId,
            'status' => $response->getStatusCode(),
            'duration' => defined('LARAVEL_START') ? round((microtime(true) - LARAVEL_START) * 1000, 2) : null,
        ];

        // Add response headers if configured
        if (!empty($config['include_headers'])) {
            $logData['headers'] = $this->sanitizeHeaders($response->headers->all());
        }

        // Add response content if configured and appropriate
        if (!empty($config['include_content']) && $this->shouldLogResponseContent($response)) {
            $content = $response->getContent();

            // Try to decode JSON content
            if (Str::startsWith($response->headers->get('Content-Type'), 'application/json')) {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $content = $this->sanitizeData($decoded);
                }
            }

            $logData['content'] = $content;
        }

        // Add memory usage if configured
        if (!empty($config['include_memory'])) {
            $logData['memory'] = memory_get_peak_usage(true);
        }

        Log::channel($config['channel'] ?? 'daily')
            ->info('Outgoing response', $logData);
    }

    /**
     * Determine if the request body should be logged.
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldLogBody(Request $request): bool
    {
        // Don't log multipart form data (file uploads)
        if (Str::contains($request->header('Content-Type'), 'multipart/form-data')) {
            return false;
        }

        // Don't log binary content
        if (Str::contains($request->header('Content-Type'), ['image/', 'video/', 'audio/'])) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the response content should be logged.
     *
     * @param Response $response
     * @return bool
     */
    protected function shouldLogResponseContent(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type');

        // Don't log binary content
        if (Str::contains($contentType, ['image/', 'video/', 'audio/'])) {
            return false;
        }

        // Don't log downloads
        if (Str::contains($contentType, ['application/octet-stream', 'application/download'])) {
            return false;
        }

        // Don't log large responses
        $maxSize = config('anthropic.logging.responses.max_content_size', 64 * 1024); // 64KB default
        if ($response->headers->has('Content-Length') && $response->headers->get('Content-Length') > $maxSize) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize headers for logging.
     *
     * @param array $headers
     * @return array
     */
    protected function sanitizeHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $this->sensitiveHeaders)) {
                $headers[$name] = '[REDACTED]';
            }
        }

        return $headers;
    }

    /**
     * Sanitize data for logging.
     *
     * @param array $data
     * @return array
     */
    protected function sanitizeData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->sensitiveFields)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            }
        }

        return $data;
    }
}
