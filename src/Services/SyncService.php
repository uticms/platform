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
            Log::warning('platform:sync api error', [
                'code' => $result->errorCode,
                'message' => $result->errorMessage,
            ]);

            return $result;
        }

        /** @var array<string, mixed> $data */
        $data = $result->data ?? [];
        $flags = is_array($data['flags'] ?? null) ? $data['flags'] : [];

        if (($flags['banned'] ?? false) === true) {
            $this->trustStore->mergeState(['flags' => $flags]);

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
}
