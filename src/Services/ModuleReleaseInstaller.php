<?php

namespace Uticms\Platform\Services;

/**
 * Phase 2: full/patch install pipeline — см. docs/license-service/13-module-releases.md
 */
final class ModuleReleaseInstaller
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function apply(string $moduleName, array $manifest): void
    {
        throw new \RuntimeException('ModuleReleaseInstaller is not implemented yet.');
    }
}
