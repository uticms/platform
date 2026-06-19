<?php

namespace Uticms\Platform\Support;

final class CertificateClaims
{
    /**
     * @param  list<array<string, mixed>>  $entitlements
     * @param  array<string, mixed>  $flags
     */
    public function __construct(
        public readonly array $payload,
        public readonly array $entitlements,
        public readonly array $flags,
        public readonly int $exp,
        public readonly string $jti,
        public readonly string $iss,
        public readonly string $installationId,
        public readonly int $offlineGraceDays,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            payload: $payload,
            entitlements: is_array($payload['entitlements'] ?? null) ? $payload['entitlements'] : [],
            flags: is_array($payload['flags'] ?? null) ? $payload['flags'] : [],
            exp: (int) ($payload['exp'] ?? 0),
            jti: (string) ($payload['jti'] ?? ''),
            iss: (string) ($payload['iss'] ?? ''),
            installationId: (string) ($payload['installation_id'] ?? ''),
            offlineGraceDays: (int) ($payload['offline_grace_days'] ?? config('platform.offline_grace_days', 14)),
        );
    }

    public function findEntitlement(string $productCode): ?array
    {
        foreach ($this->entitlements as $entitlement) {
            if (($entitlement['product_code'] ?? null) === $productCode) {
                return $entitlement;
            }
        }

        return null;
    }

    public function hasEntitlement(string $productCode): bool
    {
        return $this->findEntitlement($productCode) !== null;
    }
}
