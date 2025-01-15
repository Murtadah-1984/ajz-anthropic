<?php

namespace Ajz\Anthropic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Ajz\Anthropic\Exceptions\SignatureVerificationException;
use Symfony\Component\HttpFoundation\Response;

class VerifyRequestSignature
{
    /**
     * Maximum age of request in seconds.
     *
     * @var int
     */
    protected const MAX_REQUEST_AGE = 300; // 5 minutes

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     * @throws SignatureVerificationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get required headers
        $timestamp = $request->header('X-Request-Timestamp');
        $signature = $request->header('X-Request-Signature');
        $signatureVersion = $request->header('X-Signature-Version', 'v1');

        // Verify required headers are present
        if (!$timestamp || !$signature) {
            throw new SignatureVerificationException('Missing required signature headers');
        }

        // Verify timestamp is not too old
        if (!$this->verifyTimestamp($timestamp)) {
            throw new SignatureVerificationException('Request timestamp is too old');
        }

        // Get signing secret based on API key or token
        $secret = $this->getSigningSecret($request);

        // Verify signature
        if (!$this->verifySignature($request, $signature, $secret, $signatureVersion)) {
            throw new SignatureVerificationException('Invalid request signature');
        }

        return $next($request);
    }

    /**
     * Verify request timestamp is not too old.
     *
     * @param string $timestamp
     * @return bool
     */
    protected function verifyTimestamp(string $timestamp): bool
    {
        $requestTime = (int) $timestamp;
        $currentTime = time();

        // Check if timestamp is too old
        if ($currentTime - $requestTime > self::MAX_REQUEST_AGE) {
            $this->logFailedVerification('Request timestamp too old', [
                'request_time' => $requestTime,
                'current_time' => $currentTime,
                'max_age' => self::MAX_REQUEST_AGE,
            ]);
            return false;
        }

        // Check if timestamp is in the future (with 30s grace period)
        if ($requestTime - $currentTime > 30) {
            $this->logFailedVerification('Request timestamp in future', [
                'request_time' => $requestTime,
                'current_time' => $currentTime,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Get signing secret for request.
     *
     * @param Request $request
     * @return string
     * @throws SignatureVerificationException
     */
    protected function getSigningSecret(Request $request): string
    {
        // Try to get from API key data
        $apiKeyData = $request->attributes->get('api_key_data');
        if ($apiKeyData && isset($apiKeyData['signing_secret'])) {
            return $apiKeyData['signing_secret'];
        }

        // Try to get from token data
        $tokenData = $request->attributes->get('token_data');
        if ($tokenData && isset($tokenData['signing_secret'])) {
            return $tokenData['signing_secret'];
        }

        throw new SignatureVerificationException('No signing secret available');
    }

    /**
     * Verify request signature.
     *
     * @param Request $request
     * @param string $signature
     * @param string $secret
     * @param string $version
     * @return bool
     */
    protected function verifySignature(Request $request, string $signature, string $secret, string $version): bool
    {
        $expectedSignature = $this->generateSignature($request, $secret, $version);

        $result = hash_equals($expectedSignature, $signature);

        if (!$result) {
            $this->logFailedVerification('Invalid signature', [
                'expected' => $expectedSignature,
                'received' => $signature,
                'version' => $version,
            ]);
        }

        return $result;
    }

    /**
     * Generate signature for request.
     *
     * @param Request $request
     * @param string $secret
     * @param string $version
     * @return string
     * @throws SignatureVerificationException
     */
    protected function generateSignature(Request $request, string $secret, string $version): string
    {
        $signatureData = match ($version) {
            'v1' => $this->getSignatureDataV1($request),
            'v2' => $this->getSignatureDataV2($request),
            default => throw new SignatureVerificationException("Unsupported signature version: {$version}"),
        };

        return hash_hmac('sha256', $signatureData, $secret);
    }

    /**
     * Get signature data for version 1.
     *
     * @param Request $request
     * @return string
     */
    protected function getSignatureDataV1(Request $request): string
    {
        $timestamp = $request->header('X-Request-Timestamp');
        $method = $request->method();
        $path = $request->path();
        $query = $request->query->all();
        ksort($query);

        $data = [
            $timestamp,
            $method,
            $path,
            http_build_query($query),
        ];

        // Add body hash if present
        if ($content = $request->getContent()) {
            $data[] = hash('sha256', $content);
        }

        return implode('|', $data);
    }

    /**
     * Get signature data for version 2.
     *
     * @param Request $request
     * @return string
     */
    protected function getSignatureDataV2(Request $request): string
    {
        $timestamp = $request->header('X-Request-Timestamp');
        $method = $request->method();
        $url = $request->fullUrl();
        $headers = $this->getSignedHeaders($request);
        $body = $request->getContent();

        $data = [
            $timestamp,
            $method,
            $url,
            $this->canonicalizeHeaders($headers),
        ];

        // Add body hash if present
        if ($body) {
            $data[] = hash('sha256', $body);
        }

        return implode("\n", $data);
    }

    /**
     * Get headers included in signature.
     *
     * @param Request $request
     * @return array
     */
    protected function getSignedHeaders(Request $request): array
    {
        $signedHeaders = explode(',', $request->header('X-Signed-Headers', ''));
        $headers = [];

        foreach ($signedHeaders as $header) {
            $header = trim(strtolower($header));
            if ($value = $request->header($header)) {
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Canonicalize headers for signature.
     *
     * @param array $headers
     * @return string
     */
    protected function canonicalizeHeaders(array $headers): string
    {
        ksort($headers);

        $canonical = [];
        foreach ($headers as $name => $value) {
            $canonical[] = $name . ':' . trim($value);
        }

        return implode("\n", $canonical);
    }

    /**
     * Log failed verification.
     *
     * @param string $reason
     * @param array $context
     * @return void
     */
    protected function logFailedVerification(string $reason, array $context = []): void
    {
        $context = array_merge($context, [
            'ip' => request()->ip(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);

        Log::warning("Signature verification failed: {$reason}", $context);
    }
}
