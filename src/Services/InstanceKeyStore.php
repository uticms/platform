<?php

namespace Uticms\Platform\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Uticms\Platform\Support\Ed25519;

final class InstanceKeyStore
{
    public function ensureKeyPair(): void
    {
        if ($this->hasEnvKeys() || $this->hasStoredKeys()) {
            return;
        }

        $this->generateAndStore();
    }

    public function publicKeyBase64(): string
    {
        if ($env = env('PLATFORM_INSTANCE_PUBLIC_KEY')) {
            return trim((string) $env);
        }

        $path = $this->storagePath().'/instance.pub';
        $contents = File::get($path);

        return trim($contents);
    }

    public function secretKeyBinary(): string
    {
        if ($env = env('PLATFORM_INSTANCE_PRIVATE_KEY')) {
            $decoded = Ed25519::decodeBase64(trim((string) $env));

            if ($decoded === false) {
                throw new RuntimeException('Invalid PLATFORM_INSTANCE_PRIVATE_KEY.');
            }

            return $decoded;
        }

        $path = $this->storagePath().'/instance.key';
        $decoded = Ed25519::decodeBase64(trim(File::get($path)));

        if ($decoded === false) {
            throw new RuntimeException('Invalid instance private key in storage.');
        }

        return $decoded;
    }

    public function signMessage(string $message): string
    {
        return Ed25519::encodeBase64(
            Ed25519::sign($message, Ed25519::encodeBase64($this->secretKeyBinary())),
        );
    }

    private function hasEnvKeys(): bool
    {
        return filled(env('PLATFORM_INSTANCE_PRIVATE_KEY')) && filled(env('PLATFORM_INSTANCE_PUBLIC_KEY'));
    }

    private function hasStoredKeys(): bool
    {
        $path = $this->storagePath();

        return File::exists($path.'/instance.key') && File::exists($path.'/instance.pub');
    }

    private function generateAndStore(): void
    {
        $path = $this->storagePath();
        File::ensureDirectoryExists($path);

        $pair = Ed25519::generateKeyPair();

        File::put($path.'/instance.key', Ed25519::encodeBase64($pair['secret_key']));
        File::put($path.'/instance.pub', Ed25519::encodeBase64($pair['public_key']));
        @chmod($path.'/instance.key', 0600);
    }

    private function storagePath(): string
    {
        $path = config('platform.storage_path');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('platform.storage_path is not configured.');
        }

        return $path;
    }
}
