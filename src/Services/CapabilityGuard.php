<?php

namespace Uticms\Platform\Services;

use Illuminate\Support\Carbon;
use Uticms\Platform\Exceptions\PlatformException;

final class CapabilityGuard
{
    public function __construct(
        private readonly TrustStore $trustStore,
    ) {}

    public function assertCanInstall(string $moduleName): void
    {
        if (! $this->canUse($this->productCode($moduleName))) {
            throw new PlatformException(
                "Module [{$moduleName}] is not available for this installation.",
                'entitlement_missing',
            );
        }
    }

    public function canUse(string $productCode): bool
    {
        if ($this->trustStore->isBanned()) {
            return false;
        }

        $claims = $this->trustStore->getCertificateForUsage();

        if ($claims === null) {
            return false;
        }

        return $claims->hasEntitlement($productCode);
    }

    public function canUpdate(string $productCode): bool
    {
        if ($this->trustStore->isBanned()) {
            return false;
        }

        if (! $this->trustStore->certificateWithinGrace()) {
            return false;
        }

        $claims = $this->trustStore->getCertificateForUsage();

        if ($claims === null) {
            return false;
        }

        if (($claims->flags['updates_allowed'] ?? true) === false) {
            return false;
        }

        $entitlement = $claims->findEntitlement($productCode);

        if ($entitlement === null) {
            return false;
        }

        $updatesUntil = $entitlement['updates_until'] ?? null;

        if (! is_string($updatesUntil) || $updatesUntil === '') {
            return false;
        }

        return now()->lt(Carbon::parse($updatesUntil));
    }

    public function isBanned(): bool
    {
        return $this->trustStore->isBanned();
    }

    private function productCode(string $moduleName): string
    {
        return str_starts_with($moduleName, 'module:') ? $moduleName : 'module:'.$moduleName;
    }
}
