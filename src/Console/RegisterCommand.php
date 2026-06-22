<?php

namespace Uticms\Platform\Console;

use Illuminate\Console\Command;
use Uticms\Platform\Exceptions\PlatformException;
use Uticms\Platform\Services\RegistrationService;

final class RegisterCommand extends Command
{
    protected $signature = 'platform:register
                            {--key= : Registration key (U-…)}
                            {--domain= : Reported domain}
                            {--force : Re-run registration flow}';

    protected $description = 'Register this installation with UTICMS platform (use --force to re-run after revoke or failed activation)';

    public function handle(RegistrationService $registration): int 
    {
        try {
            $registration->register(
                registrationKey: $this->option('key') ?: null,
                domain: $this->option('domain') ?: null,
                force: (bool) $this->option('force'),
            );
        } catch (PlatformException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Registration completed successfully.');

        return self::SUCCESS;
    }
}
