<?php

namespace Uticms\Platform\Services;

use Illuminate\Support\Facades\File;

final class CoreVersionResolver
{
    public function resolve(): ?string
    {
        $fromModule = $this->readModuleJsonVersion();

        if ($fromModule !== null) {
            return $fromModule;
        }

        $configured = config('platform.core_version');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    private function readModuleJsonVersion(): ?string
    {
        $path = base_path('modules/Core/module.json');

        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            return null;
        }

        $version = $decoded['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    } 
}
