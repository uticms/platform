<?php

namespace Uticms\Platform\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Uticms\Platform\Http\PlatformApiClient;
use Uticms\Platform\Support\DomainNormalizer;
use Uticms\Platform\Support\PlatformResult;

final class SyncService
{
    public function __construct(
        private readonly TrustStore $trustStore,
        private readonly PlatformApiClient $api,
    ) {}

    public function isDue(): bool
    {
        if ($this->trustStore->installationId() === null) {
            return false;
        }

        $state = $this->trustStore->state();
        $intervalHours = max(1, (int) config('platform.heartbeat_interval_hours', 24)); 
        $lastHeartbeat = $state['last_heartbeat_at'] ?? null;

        if (! is_string($lastHeartbeat) || $lastHeartbeat === '') {
            return true;
        }

        $dueAt = Carbon::parse($lastHeartbeat)->addHours($intervalHours);

        if (now()->greaterThanOrEqualTo($dueAt)) {
            return true;
        }

        return $this->certificateRenewDue();
    }

    public function runOnce(bool $force = false): PlatformResult
    {
        if ($this->trustStore->installationId() === null) {
            return PlatformResult::apiError('not_registered', 'Installation is not registered.');
        }

        if (! $force && ! $this->isDue()) {
            return PlatformResult::ok(['skipped' => true]);
        }

        $state = $this->trustStore->state();
        $claims = null;

        try {
            $claims = $this->trustStore->parseCertificate();
        } catch (\RuntimeException) {
            // continue with stored fingerprint/domain
        }

        $payload = [
            'domain' => $state['domain'] ?? DomainNormalizer::fromAppUrl(config('app.url')),
            'fingerprint' => $state['fingerprint'] ?? '',
            'certificate_jti' => $claims?->jti,
            'core_version' => app(CoreVersionResolver::class)->resolve()
                ?? (string) config('platform.core_version', '1.0.0'),
            'modules' => [],
        ];

        $result = $this->api->sync($payload);

        if ($result->networkError) {
            Log::warning('platform:sync network error', ['message' => $result->errorMessage]);

            return $result;
        }

        if (! $result->ok) {
            $this->applyEnforcementFromApiError($result);

            Log::warning('platform:sync api error', [
                'code' => $result->errorCode,
                'message' => $result->errorMessage,
            ]);

            return $result;
        }

        /** @var array<string, mixed> $data */
        $data = $result->data ?? [];
        $installationStatus = is_string($data['installation_status'] ?? null) ? $data['installation_status'] : null;
        $flags = is_array($data['flags'] ?? null) ? $data['flags'] : [];

        if ($installationStatus === 'revoked') {
            $reason = is_string($data['revoke_reason'] ?? null) ? $data['revoke_reason'] : null;
            $this->trustStore->markInstallationRevoked($reason);

            return $result;
        }

        if ($installationStatus === 'banned' || ($flags['banned'] ?? false) === true) {
            $reason = is_string($flags['ban_reason'] ?? null) ? $flags['ban_reason'] : null;
            $this->trustStore->markInstallationBanned($flags, $reason);

            return $result;
        }

        $certificate = $data['certificate'] ?? null;

        if (is_string($certificate) && $certificate !== '') {
            try {
                $this->trustStore->saveCertificate($certificate);
            } catch (\RuntimeException $exception) {
                Log::warning('platform:sync certificate rejected', ['message' => $exception->getMessage()]);

                return PlatformResult::apiError('invalid_certificate', $exception->getMessage());
            }
        }

        $this->trustStore->mergeState(['flags' => $flags]);
        $this->trustStore->recordSuccessfulSync();

        return $result;
    }

    private function certificateRenewDue(): bool
    {
        try {
            $claims = $this->trustStore->parseCertificate();
        } catch (\RuntimeException) {
            return true;
        }

        $renewBeforeDays = max(1, (int) config('platform.certificate_renew_before_days', 7));
        $expiresAt = Carbon::createFromTimestampUTC($claims->exp);

        return now()->addDays($renewBeforeDays)->greaterThanOrEqualTo($expiresAt);
    }

    private function applyEnforcementFromApiError(PlatformResult $result): void
    { 
        $code = $result->errorCode ?? '';

        match ($code) {
            'installation_not_found', 'installation_revoked', 'license_revoked', 'license_not_found' => $this->trustStore->markInstallationRevoked(
                $result->errorMessage ?? 'Installation is no longer valid on the license server.',
            ),
            'license_banned', 'installation_banned' => $this->trustStore->markInstallationBanned(
                ['ban_reason' => $result->errorMessage],
                $result->errorMessage,
            ),
            'license_suspended' => $this->trustStore->mergeState([
                'installation_status' => 'suspended',
                'flags' => array_merge(
                    is_array($this->trustStore->state()['flags'] ?? null) ? $this->trustStore->state()['flags'] : [],
                    ['updates_allowed' => false],
                ),
            ]),
            default => null,
        };
    } 
}
