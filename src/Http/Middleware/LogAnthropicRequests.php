<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogAnthropicRequests
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
        if (!config('anthropic.logging.enabled', true)) {
            return $next($request);
        }

        $startTime = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $startTime;

        $this->logRequest($request, $response, $duration);

        return $response;
    }

    /**
     * Log the request details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  float  $duration
     * @return void
     */
    protected function logRequest(Request $request, Response $response, float $duration): void
    {
        $channel = config('anthropic.logging.channel', 'anthropic');
        $level = config('anthropic.logging.level', 'info');

        $context = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => $response->getStatusCode(),
            'duration' => round($duration * 1000, 2), // Convert to milliseconds
            'user_id' => $request->user()?->getAuthIdentifier(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'response_headers' => $this->sanitizeHeaders($response->headers->all()),
        ];

        if (config('anthropic.logging.include_body', false)) {
            $context['request_body'] = $this->sanitizeRequestBody($request);
            $context['response_body'] = $this->sanitizeResponseBody($response);
        }

        Log::channel($channel)->log($level, 'Anthropic API Request', $context);
    }

    /**
     * Sanitize headers for logging.
     *
     * @param  array  $headers
     * @return array
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'cookie',
        ];

        return collect($headers)->map(function ($value, $key) use ($sensitiveHeaders) {
            return in_array(strtolower($key), $sensitiveHeaders)
                ? '[REDACTED]'
                : $value;
        })->all();
    }

    /**
     * Sanitize request body for logging.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function sanitizeRequestBody(Request $request): array
    {
        $body = $request->except([
            'password',
            'password_confirmation',
            'token',
            'api_key',
        ]);

        return $this->truncateArrayValues($body);
    }

    /**
     * Sanitize response body for logging.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return string|array|null
     */
    protected function sanitizeResponseBody(Response $response): string|array|null
    {
        $content = $response->getContent();

        if (empty($content)) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->truncateArrayValues($decoded);
        }

        return mb_substr($content, 0, 1000);
    }

    /**
     * Truncate array values to prevent huge log entries.
     *
     * @param  array  $array
     * @param  int  $maxLength
     * @return array
     */
    protected function truncateArrayValues(array $array, int $maxLength = 1000): array
    {
        return array_map(function ($value) use ($maxLength) {
            if (is_string($value) && strlen($value) > $maxLength) {
                return mb_substr($value, 0, $maxLength) . '...';
            }

            if (is_array($value)) {
                return $this->truncateArrayValues($value, $maxLength);
            }

            return $value;
        }, $array);
    }
}
