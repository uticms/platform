<?php

namespace Uticms\Platform\Console;

use Illuminate\Console\Command;
use Uticms\Platform\Services\TrustStore;

final class StatusCommand extends Command
{
    protected $signature = 'platform:status';

    protected $description = 'Show platform registration status';

    public function handle(TrustStore $trustStore): int
    {
        $state = $trustStore->resolveLocalState();

        $this->line('Status: '.$state->value);

        $stored = $trustStore->state();

        if ($stored !== []) {
            $this->line('Installation: '.($stored['installation_id'] ?? '—'));
            $this->line('Domain: '.($stored['domain'] ?? '—'));
            $this->line('Last sync: '.($stored['last_heartbeat_at'] ?? '—'));
        }

        if ($message = $state->bannerMessage()) {
            $this->warn($message);
        }

        return $state->statusExitCode();
    }
}
