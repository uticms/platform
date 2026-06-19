<?php

namespace Uticms\Platform\Services;

use Uticms\Platform\Exceptions\PlatformException;
use Uticms\Platform\Http\PlatformApiClient;
use Uticms\Platform\Support\PlatformResult;

final class UpdateChecker
{
    public function __construct(
        private readonly TrustStore $trustStore,
        private readonly PlatformApiClient $api,
        private readonly CapabilityGuard $capabilityGuard,
    ) {}

    /**
     * @param  array<string, string>  $modules
     */
    public function check(?string $coreVersion = null, array $modules = []): PlatformResult
    {
        if ($this->trustStore->installationId() === null) { 
            return PlatformResult::apiError('not_registered', 'Installation is not registered.');
        }

        $coreVersion ??= app(CoreVersionResolver::class)->resolve() ?? '0.0.0'; 

        $query = array_merge([
            'core_version' => $coreVersion,
            'channel' => (string) config('platform.channel', 'stable'),
        ], $this->formatModuleQuery($modules));

        return $this->api->fetchUpdates($query);
    }

    public function assertCanUpdate(string $productCode, string $targetVersion): void
    {
        if (! $this->capabilityGuard->canUpdate($productCode)) {
            throw new PlatformException(
                "Updates are not available for [{$productCode}] (target {$targetVersion}).",
                'updates_not_allowed',
            );
        }
    }

    /**
     * @param  array<string, string>  $modules
     * @return array<string, string>
     */
    private function formatModuleQuery(array $modules): array
    {
        $query = [];

        foreach ($modules as $code => $version) {
            $query['modules['.$code.']'] = $version;
        }

        return $query;
    }
}
