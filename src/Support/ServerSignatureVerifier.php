<?php

namespace Uticms\Platform\Support;

final class ServerSignatureVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload, string $signatureBase64, string $publicKeyBase64): bool
    {
        $canonical = $this->canonicalize($payload);

        foreach ($this->payloadHashes($canonical) as $message) {
            if (Ed25519::verify($message, $signatureBase64, $publicKeyBase64)) {
                return true;
            } 
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyWithRotation(array $payload, string $signatureBase64): bool
    {
        $keys = array_filter([
            config('platform.server_public_key'),
            config('platform.server_public_key_previous'),
        ]);

        foreach ($keys as $key) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            if ($this->verify($payload, $signatureBase64, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string|int, mixed>  $payload
     * @return array<string|int, mixed>
     */
    private function canonicalize(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->canonicalize($value);
            }
        }

        if (! array_is_list($payload)) {
            ksort($payload);
        }

        return $payload;
    }

    /**
     * @param  array<string|int, mixed>  $canonical
     * @return list<string>
     */
    private function payloadHashes(array $canonical): array
    {
        $hashes = [
            hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR)),
            hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];

        return array_values(array_unique($hashes));
    }
}
