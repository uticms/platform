<?php

namespace Uticms\Platform;

use Uticms\Platform\Services\CapabilityGuard;
use Uticms\Platform\Services\RegistrationService;
use Uticms\Platform\Services\SyncService;
use Uticms\Platform\Services\TrustStore;
use Uticms\Platform\Support\PlatformLocalState;

final class PlatformManager
{
    public function __construct(
        private readonly TrustStore $trustStore,
        private readonly SyncService $syncService,
        private readonly RegistrationService $registrationService,
        private readonly CapabilityGuard $capabilityGuard,
    ) {}

    public function localState(): PlatformLocalState
    {
        return $this->trustStore->resolveLocalState();
    }

    public function sync(bool $force = false): void
    {
        $this->syncService->runOnce($force);
    }

    public function register(?string $key = null, ?string $domain = null, bool $force = false): void 
    {
        $this->registrationService->register($key, $domain, $force);
    }

    public function guard(): CapabilityGuard
    {
        return $this->capabilityGuard;
    }
}
