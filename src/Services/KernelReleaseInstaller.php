<?php

namespace Uticms\Platform\Services;

/**
 * Applies product "core" release steps to CMS kernel (base_path).
 * See docs/license-service/15-core-updates.md
 */
final class KernelReleaseInstaller
{
    /**
     * @param  array<string, mixed>  $manifestCore
     */
    public function apply(array $manifestCore): void
    {
        throw new \RuntimeException('KernelReleaseInstaller is not implemented yet.');
    }
} 
