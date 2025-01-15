<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Ajz\Anthropic\Exceptions\InvalidConfigurationException;
use Ajz\Anthropic\Exceptions\RateLimitExceededException;

class HandleAnthropicErrors
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
        try {
            return $next($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Handle the caught exception.
     *
     * @param  \Throwable  $e
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleException(Throwable $e, Request $request)
    {
        // Log the error if logging is enabled
        if (config('anthropic.logging.enabled', true)) {
            $this->logException($e, $request);
        }

        // Handle known exceptions
        if ($e instanceof InvalidConfigurationException) {
            return $this->handleConfigurationException($e);
        }

        if ($e instanceof RateLimitExceededException) {
            return $e->render();
        }

        // Handle Anthropic API errors
        if ($this->isAnthropicApiError($e)) {
            return $this->handleAnthropicApiError($e);
        }

        // Handle all other exceptions
        return $this->handleUnknownException($e);
    }

    /**
     * Log the exception.
     *
     * @param  \Throwable  $e
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function logException(Throwable $e, Request $request): void
    {
        $context = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'input' => $this->sanitizeInput($request->input()),
                'headers' => $this->sanitizeHeaders($request->headers->all()),
            ],
        ];

        $channel = config('anthropic.logging.channel', 'anthropic');
        Log::channel($channel)->error('Anthropic Error', $context);
    }

    /**
     * Handle configuration exceptions.
     *
     * @param  \Ajz\Anthropic\Exceptions\InvalidConfigurationException  $e
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleConfigurationException(InvalidConfigurationException $e)
    {
        return response()->json([
            'message' => 'Invalid configuration',
            'error' => $e->getMessage(),
            'details' => $e->getValidationErrors(),
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Handle Anthropic API errors.
     *
     * @param  \Throwable  $e
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleAnthropicApiError(Throwable $e)
    {
        $status = $this->getHttpStatusFromException($e);

        return response()->json([
            'message' => 'Anthropic API error',
            'error' => $e->getMessage(),
            'type' => 'api_error',
            'code' => $e->getCode(),
        ], $status);
    }

    /**
     * Handle unknown exceptions.
     *
     * @param  \Throwable  $e
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleUnknownException(Throwable $e)
    {
        $debug = config('app.debug', false);

        $response = [
            'message' => 'Internal server error',
            'error' => $debug ? $e->getMessage() : 'An unexpected error occurred',
            'type' => 'internal_error',
        ];

        if ($debug) {
            $response['trace'] = explode("\n", $e->getTraceAsString());
        }

        return response()->json($response, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Check if the exception is from the Anthropic API.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function isAnthropicApiError(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'anthropic') ||
            str_contains(get_class($e), 'Anthropic') ||
            property_exists($e, 'anthropicCode');
    }

    /**
     * Get HTTP status code from exception.
     *
     * @param  \Throwable  $e
     * @return int
     */
    protected function getHttpStatusFromException(Throwable $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        return match ($e->getCode()) {
            400 => Response::HTTP_BAD_REQUEST,
            401 => Response::HTTP_UNAUTHORIZED,
            403 => Response::HTTP_FORBIDDEN,
            404 => Response::HTTP_NOT_FOUND,
            429 => Response::HTTP_TOO_MANY_REQUESTS,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    /**
     * Sanitize request input for logging.
     *
     * @param  array  $input
     * @return array
     */
    protected function sanitizeInput(array $input): array
    {
        $sensitiveFields = [
            'password',
            'token',
            'api_key',
            'secret',
            'key',
        ];

        return collect($input)->map(function ($value, $key) use ($sensitiveFields) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                return '[REDACTED]';
            }

            if (is_array($value)) {
                return $this->sanitizeInput($value);
            }

            return $value;
        })->all();
    }

    /**
     * Sanitize request headers for logging.
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
                ? ['[REDACTED]']
                : $value;
        })->all();
    }
}
