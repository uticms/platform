<?php

namespace Uticms\Platform\Support;

use RuntimeException;

final class JwtEd25519
{
    /**
     * @return array{header: array<string, mixed>, payload: array<string, mixed>}
     */
    public function decode(string $jwt, string $publicKeyBase64): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid JWT structure.');
        }

        [$headerSegment, $payloadSegment, $signatureSegment] = $parts;
        $signingInput = $headerSegment.'.'.$payloadSegment;

        $headerJson = $this->base64UrlDecode($headerSegment);
        $payloadJson = $this->base64UrlDecode($payloadSegment);
        $signature = $this->base64UrlDecode($signatureSegment);

        if ($headerJson === false || $payloadJson === false || $signature === false) {
            throw new RuntimeException('Invalid JWT encoding.');
        }

        /** @var array<string, mixed> $header */
        $header = json_decode($headerJson, true, 512, JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

        if (($header['alg'] ?? null) !== 'EdDSA') {
            throw new RuntimeException('Unsupported JWT algorithm.');
        }

        if (! Ed25519::verify($signingInput, Ed25519::encodeBase64($signature), $publicKeyBase64)) {
            throw new RuntimeException('JWT signature verification failed.');
        }

        return [
            'header' => $header,
            'payload' => $payload,
        ];
    }

    private function base64UrlDecode(string $segment): string|false
    {
        $remainder = strlen($segment) % 4;

        if ($remainder > 0) {
            $segment .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($segment, '-_', '+/'), true);
    }
}
