<?php

namespace Uticms\Platform\Support;

final class PlatformResult
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    private function __construct(
        public readonly bool $ok,
        public readonly ?array $data = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $networkError = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(array $data): self
    {
        return new self(ok: true, data: $data);
    }

    public static function apiError(string $code, string $message): self
    {
        return new self(ok: false, errorCode: $code, errorMessage: $message);
    }

    public static function networkError(string $message): self
    {
        return new self(ok: false, errorMessage: $message, networkError: true);
    }

    public static function invalidSignature(): self
    {
        return new self(ok: false, errorCode: 'invalid_server_signature', errorMessage: 'Server signature verification failed.');
    }
}
