<?php

namespace Uticms\Platform\Support;

final class FingerprintGenerator
{
    public function build(string $instancePublicKeyBase64, string $environmentType): string
    {
        $appKey = (string) config('app.key', '');
        $appKeyHash = substr(hash('sha256', $appKey), 0, 16);
        $raw = $appKeyHash.$instancePublicKeyBase64.$environmentType;

        return 'sha256:'.hash('sha256', $raw);
    }
}
