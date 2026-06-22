<?php

namespace Uticms\Platform\Services;

use Illuminate\Support\Str;
use Uticms\Platform\Exceptions\PlatformException;
use Uticms\Platform\Http\PlatformApiClient;
use Uticms\Platform\Support\DomainNormalizer;
use Uticms\Platform\Support\FingerprintGenerator;
use Uticms\Platform\Support\PlatformResult;

final class RegistrationService
{
    public function __construct(
        private readonly InstanceKeyStore $keys,
        private readonly TrustStore $trustStore,
        private readonly FingerprintGenerator $fingerprintGenerator,
        private readonly PlatformApiClient $api,
    ) {}

    public function register(?string $registrationKey = null, ?string $domain = null): void
    {
        $registrationKey ??= (string) config('platform.registration_key');

        if (trim($registrationKey) === '') {
            throw new PlatformException('PLATFORM_KEY is not configured.', 'missing_registration_key');
        }

        $this->keys->ensureKeyPair();

        $publicKey = $this->keys->publicKeyBase64();
        $environmentType = (string) config('platform.environment_type', 'production');
        $fingerprint = $this->fingerprintGenerator->build($publicKey, $environmentType);
        $domain ??= DomainNormalizer::fromAppUrl(config('app.url'));

        $activateResult = $this->api->activate([
            'license_key' => $registrationKey,
            'instance_public_key' => $publicKey,
            'domain' => $domain,
            'fingerprint' => $fingerprint,
            'environment_type' => $environmentType,
            'environment' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'core_version' => (string) config('platform.core_version', '1.0.0'),
            ],
            'nonce_client' => (string) Str::ulid(),
        ]);

        $this->assertOk($activateResult);

        /** @var array<string, mixed> $data */
        $data = $activateResult->data ?? [];

        $installationId = (string) ($data['installation_id'] ?? '');
        $nonceServer = (string) ($data['nonce_server'] ?? '');

        if ($installationId === '' || $nonceServer === '') {
            throw new PlatformException('Invalid activate response.', 'invalid_activate_response');
        }

        $this->trustStore->mergeState([
            'installation_id' => $installationId,
            'domain' => $domain,
            'fingerprint' => $fingerprint,
            'installation_status' => 'pending',
            'revoke_reason' => null,  
            'ban_reason' => null,
        ]);


        $confirmSignature = $this->keys->signMessage($nonceServer.$installationId); 

        $confirmResult = $this->api->confirm([
            'installation_id' => $installationId,
            'nonce_server' => $nonceServer,
            'signature' => $confirmSignature,
        ]);

        $this->assertOk($confirmResult);

        /** @var array<string, mixed> $confirmData */
        $confirmData = $confirmResult->data ?? [];
        $certificate = $confirmData['certificate'] ?? null;

        if (! is_string($certificate) || $certificate === '') {
            throw new PlatformException('Confirm response did not include certificate.', 'invalid_confirm_response');
        }

        $this->saveCertificate($certificate);

        $this->trustStore->mergeState([ 
            'installation_status' => 'active',
            'revoke_reason' => null,
            'ban_reason' => null,
        ]);
    }

    private function saveCertificate(string $jwt): void
    {
        try {
            $this->trustStore->saveCertificate($jwt);
        } catch (\RuntimeException $exception) {
            throw new PlatformException($exception->getMessage(), 'invalid_certificate');
        }
    }

    private function assertOk(PlatformResult $result): void
    {
        if ($result->networkError) {
            throw new PlatformException($result->errorMessage ?? 'Network error.', 'network_error');
        }

        if (! $result->ok) {
            throw new PlatformException(
                $result->errorMessage ?? 'Platform API error.',
                $result->errorCode,
            );
        }
    }
}
