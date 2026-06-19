<?php

namespace Uticms\Platform\Support;

final class ServerSignatureVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload, string $signatureBase64, string $publicKeyBase64): bool
    {
        ksort($payload);

        $message = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return Ed25519::verify($message, $signatureBase64, $publicKeyBase64);
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
}
