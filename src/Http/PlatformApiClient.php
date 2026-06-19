<?php

namespace Uticms\Platform\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Uticms\Platform\Services\InstanceKeyStore;
use Uticms\Platform\Services\TrustStore;
use Uticms\Platform\Support\PlatformResult;
use Uticms\Platform\Support\ServerSignatureVerifier;

final class PlatformApiClient
{
    public function __construct(
        private readonly InstanceKeyStore $keys,
        private readonly TrustStore $trustStore,
        private readonly ServerSignatureVerifier $signatureVerifier,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public function activate(array $body): PlatformResult
    {
        return $this->postUnsigned('/api/v1/license/activate', $body);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function confirm(array $body): PlatformResult
    {
        return $this->postUnsigned('/api/v1/license/activate/confirm', $body);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function sync(array $body): PlatformResult
    {
        return $this->postSigned('/api/v1/license/heartbeat', $body);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function fetchUpdates(array $query): PlatformResult
    {
        $queryString = http_build_query($query);
        $path = '/api/v1/license/updates'.($queryString !== '' ? '?'.$queryString : '');

        return $this->getSigned($path);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postUnsigned(string $path, array $body): PlatformResult
    {
        $this->trustStore->recordNetworkAttempt();

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->withHeaders($this->baseHeaders())
                ->post($this->url($path), $body);
        } catch (ConnectionException $exception) {
            $this->trustStore->recordNetworkAttempt($exception->getMessage());

            return PlatformResult::networkError($exception->getMessage());
        }

        return $this->parseResponse($response->json(), $response->status());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postSigned(string $path, array $body): PlatformResult
    {
        return $this->sendSigned('POST', $path, json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function getSigned(string $path): PlatformResult
    {
        return $this->sendSigned('GET', $path, '');
    }

    private function sendSigned(string $method, string $path, string $body): PlatformResult
    {
        $installationId = $this->trustStore->installationId();

        if ($installationId === null) {
            return PlatformResult::apiError('not_registered', 'Installation is not registered.');
        }

        $timestamp = (string) time();
        $nonce = (string) Str::ulid();
        $signature = $this->keys->signMessage($timestamp."\n".$nonce."\n".hash('sha256', $body));

        $this->trustStore->recordNetworkAttempt();

        try {
            $pending = Http::timeout($this->timeout())
                ->acceptJson()
                ->withHeaders(array_merge($this->baseHeaders(), [
                    'X-Installation-Id' => $installationId,
                    'X-Timestamp' => $timestamp,
                    'X-Nonce' => $nonce,
                    'X-Signature' => $signature,
                    'Content-Type' => 'application/json',
                ]));

            $response = $method === 'GET'
                ? $pending->get($this->url($path))
                : $pending->withBody($body, 'application/json')->post($this->url($path));
        } catch (ConnectionException $exception) {
            $this->trustStore->recordNetworkAttempt($exception->getMessage());

            return PlatformResult::networkError($exception->getMessage());
        }

        return $this->parseResponse($response->json(), $response->status());
    }

    /**
     * @param  mixed  $json
     */
    private function parseResponse(mixed $json, int $status): PlatformResult
    {
        if (! is_array($json)) {
            return PlatformResult::networkError('Invalid response from platform server.');
        }

        if ($status >= 400) {
            $code = is_string($json['code'] ?? null) ? $json['code'] : 'api_error';
            $message = is_string($json['message'] ?? null) ? $json['message'] : 'Platform API error.';

            return PlatformResult::apiError($code, $message);
        }

        $signature = $json['server_signature'] ?? null;

        if (! is_string($signature) || $signature === '') {
            return PlatformResult::invalidSignature();
        }

        $payload = $json;
        unset($payload['server_signature']);

        if (! $this->signatureVerifier->verifyWithRotation($payload, $signature)) {
            return PlatformResult::invalidSignature();
        }

        return PlatformResult::ok($json);
    }

    /**
     * @return array<string, string>
     */
    private function baseHeaders(): array
    {
        return [
            'X-Client-Version' => (string) config('platform.client_version', 'uticms-platform/1.0.0'),
        ];
    }

    private function url(string $path): string
    {
        return rtrim((string) config('platform.server_url'), '/').$path;
    }

    private function timeout(): int
    {
        return max(5, (int) config('platform.api_timeout_seconds', 15));
    }
}
