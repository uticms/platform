<?php

namespace Uticms\Platform\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Uticms\Platform\Services\SyncService;

final class SyncCommand extends Command
{
    protected $signature = 'platform:sync {--force : Run even if not due}';

    protected $description = 'Synchronize installation state with UTICMS platform';

    public function handle(SyncService $sync): int
    {
        $result = $sync->runOnce((bool) $this->option('force'));

        if ($result->networkError) {
            Log::warning('platform:sync failed (network)', ['message' => $result->errorMessage]);
            $this->warn($result->errorMessage ?? 'Network error.');

            return self::SUCCESS;
        }

        if (! $result->ok) {
            $this->warn($result->errorMessage ?? 'Sync failed.');

            return self::SUCCESS;
        }

        if (($result->data['skipped'] ?? false) === true) {
            $this->line('Sync skipped — not due yet.');

            return self::SUCCESS;
        }

        $this->info('Sync completed successfully.');

        return self::SUCCESS;
    }
}
