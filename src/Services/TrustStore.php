<?php

namespace Uticms\Platform\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Uticms\Platform\Support\CertificateClaims;
use Uticms\Platform\Support\JwtEd25519;
use Uticms\Platform\Support\PlatformLocalState;

final class TrustStore
{
    public function __construct(
        private readonly JwtEd25519 $jwt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $path = $this->statePath();

        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function saveState(array $state): void
    {
        $this->ensureStorageDirectory();
        File::put($this->statePath(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function mergeState(array $patch): void
    {
        $this->saveState(array_merge($this->state(), $patch));
    }

    public function certificateJwt(): ?string
    {
        $path = $this->certificatePath();

        if (! File::exists($path)) {
            return null;
        }

        return trim(File::get($path));
    }

    public function saveCertificate(string $jwt): void
    {
        $claims = $this->decodeCertificate($jwt);
        $this->assertCertificateBinding($claims);
        $this->assertNotCertificateReplay($claims);

        $this->ensureStorageDirectory();
        File::put($this->certificatePath(), $jwt);

        $this->mergeState([
            'certificate_jti' => $claims->jti,
            'certificate_expires_at' => Carbon::createFromTimestampUTC($claims->exp)->toIso8601String(),
            'flags' => $claims->flags,
        ]);
    }

    public function parseCertificate(?string $jwt = null): CertificateClaims
    {
        $jwt ??= $this->certificateJwt();

        if ($jwt === null || $jwt === '') {
            throw new RuntimeException('Certificate is not available.');
        }

        $claims = $this->decodeCertificate($jwt);
        $this->assertCertificateBinding($claims);

        return $claims;
    }

    private function decodeCertificate(string $jwt): CertificateClaims
    {
        $publicKey = $this->resolveVerificationKey();

        try {
            $decoded = $this->jwt->decode($jwt, $publicKey);
        } catch (RuntimeException) {
            $previous = config('platform.server_public_key_previous');

            if (! is_string($previous) || trim($previous) === '') {
                throw new RuntimeException('Certificate verification failed.');
            }

            $decoded = $this->jwt->decode($jwt, $previous);
        }

        return CertificateClaims::fromPayload($decoded['payload']);
    }

    private function assertCertificateBinding(CertificateClaims $claims): void
    {
        $expectedInstallationId = $this->installationId();

        if ($expectedInstallationId === null) {
            throw new RuntimeException('Certificate cannot be validated without installation_id in state.');
        }

        if ($claims->installationId === '' || $claims->installationId !== $expectedInstallationId) {
            throw new RuntimeException('Certificate installation_id mismatch.');
        }

        $expectedIssuer = rtrim((string) config('platform.server_url'), '/');
        $issuer = rtrim($claims->iss, '/');

        if ($issuer === '' || $issuer !== $expectedIssuer) {
            throw new RuntimeException('Certificate issuer mismatch.');
        }
    }

    private function assertNotCertificateReplay(CertificateClaims $incoming): void
    {
        $path = $this->certificatePath();

        if (! File::exists($path)) {
            return;
        }

        try {
            $current = $this->decodeCertificate(trim(File::get($path)));
        } catch (RuntimeException) {
            return;
        }

        if ($incoming->exp > $current->exp) {
            return;
        }

        if ($incoming->exp === $current->exp && $incoming->jti === $current->jti) {
            return;
        }

        throw new RuntimeException('Certificate replay rejected.');
    }

    public function getCertificateForUsage(): ?CertificateClaims
    {
        try {
            $claims = $this->parseCertificate();
        } catch (RuntimeException) {
            return null;
        }

        if (($claims->flags['banned'] ?? false) === true) {
            return null;
        }

        return $claims;
    }

    public function certificateWithinGrace(): bool
    {
        try {
            $claims = $this->parseCertificate();
        } catch (RuntimeException) {
            return false;
        }

        $state = $this->resolveLocalState($claims);

        return in_array($state, [PlatformLocalState::Active, PlatformLocalState::Grace], true);
    }

    /** @deprecated use getCertificateForUsage() or certificateWithinGrace() */
    public function getValidCertificate(): ?CertificateClaims
    {
        if (! $this->certificateWithinGrace()) {
            return null;
        }

        return $this->getCertificateForUsage();
    }

    public function resolveLocalState(?CertificateClaims $claims = null): PlatformLocalState
    {
        $state = $this->state();

        if (($state['installation_status'] ?? null) === 'revoked') {
            return PlatformLocalState::Revoked;
        }

        if (($state['flags']['banned'] ?? false) === true) {
            return PlatformLocalState::Banned;
        }

        try {
            $claims ??= $this->parseCertificate();
        } catch (RuntimeException) {
            return PlatformLocalState::Unregistered;
        }

        if ($claims->flags['banned'] ?? false) {
            return PlatformLocalState::Banned;
        }

        $now = now()->utc();
        $expiresAt = Carbon::createFromTimestampUTC($claims->exp);
        $graceEndsAt = $expiresAt->copy()->addDays($claims->offlineGraceDays);

        if ($now->lte($expiresAt)) {
            return PlatformLocalState::Active;
        }

        if ($now->lte($graceEndsAt)) {
            return PlatformLocalState::Grace;
        }

        return PlatformLocalState::Restricted;
    }

    public function isBanned(): bool
    {
        $state = $this->state();

        if (($state['installation_status'] ?? null) === 'revoked') {
            return false;
        }

        if (($state['flags']['banned'] ?? false) === true) {
            return true;
        }

        try {
            return ($this->parseCertificate()->flags['banned'] ?? false) === true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function markInstallationRevoked(?string $reason = null): void
    {
        $this->clearCertificate();

        $this->mergeState([
            'installation_status' => 'revoked',
            'revoke_reason' => $reason,
            'flags' => array_merge(
                is_array($this->state()['flags'] ?? null) ? $this->state()['flags'] : [],
                ['updates_allowed' => false, 'support_allowed' => false],
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    public function markInstallationBanned(array $flags, ?string $reason = null): void
    {
        $flags['banned'] = true;

        $this->mergeState([
            'installation_status' => 'banned',
            'ban_reason' => $reason ?? (is_string($flags['ban_reason'] ?? null) ? $flags['ban_reason'] : null),
            'flags' => $flags,
        ]);
    }

    private function clearCertificate(): void
    {
        $path = $this->certificatePath();

        if (File::exists($path)) {
            File::delete($path);
        }

        $this->mergeState([
            'certificate_jti' => null,
            'certificate_expires_at' => null,
        ]);
    }

    public function installationId(): ?string
    {
        $id = $this->state()['installation_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function recordNetworkAttempt(?string $error = null): void
    {
        $this->mergeState([
            'last_network_attempt_at' => now()->utc()->toIso8601String(),
            'last_network_error' => $error,
        ]);
    }

    public function recordSuccessfulSync(): void
    {
        $this->mergeState([
            'last_heartbeat_at' => now()->utc()->toIso8601String(),
            'last_network_error' => null,
        ]);
    }

    private function resolveVerificationKey(): string
    {
        $key = config('platform.server_public_key');

        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('platform.server_public_key is not configured.');
        }

        return $key;
    }

    private function ensureStorageDirectory(): void
    {
        File::ensureDirectoryExists($this->storageDirectory());
    }

    private function storageDirectory(): string
    {
        $path = config('platform.storage_path');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('platform.storage_path is not configured.');
        }

        return $path;
    }

    private function statePath(): string
    {
        return $this->storageDirectory().'/state.json';
    }

    private function certificatePath(): string
    {
        return $this->storageDirectory().'/certificate.jwt';
    }
}
