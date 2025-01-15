<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Ajz\Anthropic\Exceptions\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ValidateRequest
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     * @throws ValidationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Get validation rules based on route
            $rules = $this->getValidationRules($request);

            // Validate request
            $validator = Validator::make(
                $this->getRequestData($request),
                $rules,
                $this->getValidationMessages()
            );

            if ($validator->fails()) {
                throw new ValidationException(
                    'Request validation failed',
                    $validator->errors()->toArray()
                );
            }

            // Log validated request if enabled
            if (config('anthropic.logging.requests.enabled', false)) {
                $this->logRequest($request);
            }

            // Add validation timestamp to request
            $request->attributes->set('validated_at', now());

            return $next($request);
        } catch (ValidationException $e) {
            // Log validation failure if enabled
            if (config('anthropic.logging.validation.enabled', false)) {
                $this->logValidationFailure($request, $e);
            }

            throw $e;
        }
    }

    /**
     * Get validation rules based on the request route.
     *
     * @param Request $request
     * @return array
     */
    protected function getValidationRules(Request $request): array
    {
        $route = $request->route();
        $action = $route ? $route->getAction() : [];
        $controller = $action['controller'] ?? null;

        // Get base rules for all requests
        $rules = [
            'api_key' => ['required', 'string'],
            'timestamp' => ['required', 'integer'],
            'version' => ['required', 'string'],
        ];

        // Add content validation rules if request has content
        if ($request->hasHeader('Content-Type')) {
            $rules['content'] = ['required'];

            // Add specific rules based on content type
            switch ($request->header('Content-Type')) {
                case 'application/json':
                    $rules['content.*'] = ['array'];
                    break;
                case 'text/plain':
                    $rules['content'] = ['string'];
                    break;
                case 'multipart/form-data':
                    $rules['content.files.*'] = ['file'];
                    break;
            }
        }

        // Add controller-specific validation rules if available
        if ($controller && method_exists($controller, 'validationRules')) {
            $rules = array_merge($rules, $controller::validationRules());
        }

        return $rules;
    }

    /**
     * Get the data to validate from the request.
     *
     * @param Request $request
     * @return array
     */
    protected function getRequestData(Request $request): array
    {
        $data = array_merge(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all()
        );

        // Add headers that need validation
        $data['api_key'] = $request->header('X-API-Key');
        $data['timestamp'] = $request->header('X-Timestamp');
        $data['version'] = $request->header('X-API-Version');

        return $data;
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    protected function getValidationMessages(): array
    {
        return [
            'api_key.required' => 'API key is required',
            'api_key.string' => 'API key must be a string',
            'timestamp.required' => 'Request timestamp is required',
            'timestamp.integer' => 'Request timestamp must be an integer',
            'version.required' => 'API version is required',
            'version.string' => 'API version must be a string',
            'content.required' => 'Request content is required',
            'content.*.array' => 'Each content item must be an array',
            'content.files.*.file' => 'Each file must be a valid upload',
        ];
    }

    /**
     * Log the validated request.
     *
     * @param Request $request
     * @return void
     */
    protected function logRequest(Request $request): void
    {
        $logData = [
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $request->query->all(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Add request body if not a file upload
        if (!$request->isMethod('POST') || !str_contains($request->header('Content-Type'), 'multipart/form-data')) {
            $logData['body'] = $request->except(['password', 'token', 'key']);
        }

        Log::channel(config('anthropic.logging.requests.channel', 'daily'))
            ->info('Validated request', $logData);
    }

    /**
     * Log validation failure.
     *
     * @param Request $request
     * @param ValidationException $exception
     * @return void
     */
    protected function logValidationFailure(Request $request, ValidationException $exception): void
    {
        Log::channel(config('anthropic.logging.validation.channel', 'daily'))
            ->warning('Request validation failed', [
                'method' => $request->method(),
                'path' => $request->path(),
                'errors' => $exception->errors,
                'ip' => $request->ip(),
            ]);
    }

    /**
     * Sanitize headers for logging by removing sensitive information.
     *
     * @param array $headers
     * @return array
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'cookie',
            'x-api-key',
            'x-csrf-token',
        ];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = '[REDACTED]';
            }
        }

        return $headers;
    }
}
