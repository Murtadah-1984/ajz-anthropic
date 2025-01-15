<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Ajz\Anthropic\Exceptions\ValidationException;
use Ajz\Anthropic\Exceptions\RateLimitExceededException;
use Throwable;

class HandleErrors
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);

            // Handle response errors (4xx and 5xx)
            if ($response->getStatusCode() >= 400) {
                return $this->handleErrorResponse($response, $request);
            }

            return $response;
        } catch (ValidationException $e) {
            return $this->handleValidationException($e, $request);
        } catch (RateLimitExceededException $e) {
            return $this->handleRateLimitException($e, $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Handle error responses.
     *
     * @param Response $response
     * @param Request $request
     * @return Response
     */
    protected function handleErrorResponse(Response $response, Request $request): Response
    {
        $statusCode = $response->getStatusCode();
        $content = $response->getContent();

        // Try to decode JSON content
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $data = ['message' => $content];
        }

        // Build error response
        $error = [
            'error' => [
                'type' => $this->getErrorType($statusCode),
                'code' => $statusCode,
                'message' => $data['message'] ?? $this->getDefaultMessage($statusCode),
            ],
        ];

        // Add additional error details if available
        if (isset($data['errors'])) {
            $error['error']['details'] = $data['errors'];
        }

        // Add request ID if available
        if ($requestId = $request->header('X-Request-ID')) {
            $error['error']['request_id'] = $requestId;
        }

        // Add debug information if enabled
        if ($this->shouldIncludeDebugInfo($request)) {
            $error['error']['debug'] = $this->getDebugInfo($request);
        }

        return response()->json($error, $statusCode);
    }

    /**
     * Handle validation exceptions.
     *
     * @param ValidationException $e
     * @param Request $request
     * @return Response
     */
    protected function handleValidationException(ValidationException $e, Request $request): Response
    {
        $error = [
            'error' => [
                'type' => 'validation_error',
                'code' => 422,
                'message' => $e->getMessage(),
                'errors' => $e->errors,
            ],
        ];

        if ($requestId = $request->header('X-Request-ID')) {
            $error['error']['request_id'] = $requestId;
        }

        return response()->json($error, 422);
    }

    /**
     * Handle rate limit exceptions.
     *
     * @param RateLimitExceededException $e
     * @param Request $request
     * @return Response
     */
    protected function handleRateLimitException(RateLimitExceededException $e, Request $request): Response
    {
        $error = [
            'error' => [
                'type' => 'rate_limit_exceeded',
                'code' => 429,
                'message' => $e->getMessage(),
                'details' => $e->context,
            ],
        ];

        if ($requestId = $request->header('X-Request-ID')) {
            $error['error']['request_id'] = $requestId;
        }

        return response()->json($error, 429)
            ->header('Retry-After', $e->context['retry_after'])
            ->header('X-RateLimit-Reset', time() + $e->context['retry_after']);
    }

    /**
     * Handle general exceptions.
     *
     * @param Throwable $e
     * @param Request $request
     * @return Response
     */
    protected function handleException(Throwable $e, Request $request): Response
    {
        // Log the error
        Log::error('Unhandled exception', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_id' => $request->header('X-Request-ID'),
        ]);

        $error = [
            'error' => [
                'type' => 'internal_error',
                'code' => 500,
                'message' => $this->shouldExposeErrorDetails($request)
                    ? $e->getMessage()
                    : 'An unexpected error occurred',
            ],
        ];

        if ($requestId = $request->header('X-Request-ID')) {
            $error['error']['request_id'] = $requestId;
        }

        // Add debug information if enabled
        if ($this->shouldIncludeDebugInfo($request)) {
            $error['error']['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ];
        }

        return response()->json($error, 500);
    }

    /**
     * Get the error type based on status code.
     *
     * @param int $statusCode
     * @return string
     */
    protected function getErrorType(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            408 => 'request_timeout',
            409 => 'conflict',
            413 => 'payload_too_large',
            422 => 'validation_error',
            429 => 'rate_limit_exceeded',
            500 => 'internal_error',
            502 => 'bad_gateway',
            503 => 'service_unavailable',
            504 => 'gateway_timeout',
            default => 'error',
        };
    }

    /**
     * Get default error message for status code.
     *
     * @param int $statusCode
     * @return string
     */
    protected function getDefaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            405 => 'Method not allowed',
            408 => 'Request timeout',
            409 => 'Conflict',
            413 => 'Payload too large',
            422 => 'Validation error',
            429 => 'Too many requests',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
            504 => 'Gateway timeout',
            default => 'An error occurred',
        };
    }

    /**
     * Check if debug information should be included.
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldIncludeDebugInfo(Request $request): bool
    {
        return config('app.debug') &&
            config('anthropic.errors.include_debug', false) &&
            $this->isDebugAllowed($request);
    }

    /**
     * Check if error details should be exposed.
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldExposeErrorDetails(Request $request): bool
    {
        return config('anthropic.errors.expose_details', false) &&
            $this->isInternalRequest($request);
    }

    /**
     * Check if debug is allowed for the request.
     *
     * @param Request $request
     * @return bool
     */
    protected function isDebugAllowed(Request $request): bool
    {
        return $this->isInternalRequest($request) ||
            $request->header('X-Debug') === config('anthropic.errors.debug_key');
    }

    /**
     * Check if request is from internal system.
     *
     * @param Request $request
     * @return bool
     */
    protected function isInternalRequest(Request $request): bool
    {
        $internalIps = config('anthropic.errors.internal_ips', []);
        $internalDomains = config('anthropic.errors.internal_domains', []);

        return in_array($request->ip(), $internalIps) ||
            $this->isInternalDomain($request->header('Host'), $internalDomains);
    }

    /**
     * Check if domain is internal.
     *
     * @param string|null $host
     * @param array $internalDomains
     * @return bool
     */
    protected function isInternalDomain(?string $host, array $internalDomains): bool
    {
        if (!$host) {
            return false;
        }

        foreach ($internalDomains as $domain) {
            if (str_ends_with($host, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get debug information for the request.
     *
     * @param Request $request
     * @return array
     */
    protected function getDebugInfo(Request $request): array
    {
        return [
            'request' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'query' => $request->query(),
                'body' => $request->except(['password', 'token']),
                'headers' => $this->sanitizeHeaders($request->headers->all()),
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ],
        ];
    }

    /**
     * Sanitize headers for debug output.
     *
     * @param array $headers
     * @return array
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitive = [
            'authorization',
            'cookie',
            'x-api-key',
            'x-csrf-token',
        ];

        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $sensitive)) {
                $headers[$name] = '[REDACTED]';
            }
        }

        return $headers;
    }
}
