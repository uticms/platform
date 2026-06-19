<?php

namespace Uticms\Platform;

use Illuminate\Support\ServiceProvider;
use Uticms\Platform\Console\RegisterCommand;
use Uticms\Platform\Console\StatusCommand;
use Uticms\Platform\Console\SyncCommand;
use Uticms\Platform\Http\Middleware\MaybeRunPlatformSync;
use Uticms\Platform\Http\PlatformApiClient;
use Uticms\Platform\Services\CapabilityGuard;
use Uticms\Platform\Services\CoreVersionResolver;
use Uticms\Platform\Services\InstanceKeyStore;
use Uticms\Platform\Services\KernelReleaseInstaller;
use Uticms\Platform\Services\ModuleReleaseInstaller;
use Uticms\Platform\Services\RegistrationService;
use Uticms\Platform\Services\SyncService;
use Uticms\Platform\Services\TrustStore;
use Uticms\Platform\Services\UpdateChecker;
use Uticms\Platform\Support\FingerprintGenerator;
use Uticms\Platform\Support\JwtEd25519;
use Uticms\Platform\Support\ServerSignatureVerifier;

final class PlatformServiceProvider extends ServiceProvider 
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/platform.php', 'platform');

        $this->app->singleton(JwtEd25519::class);
        $this->app->singleton(ServerSignatureVerifier::class);
        $this->app->singleton(FingerprintGenerator::class);
        $this->app->singleton(InstanceKeyStore::class);
        $this->app->singleton(TrustStore::class);
        $this->app->singleton(PlatformApiClient::class);
        $this->app->singleton(RegistrationService::class);
        $this->app->singleton(SyncService::class);
        $this->app->singleton(CapabilityGuard::class);
        $this->app->singleton(UpdateChecker::class);
        $this->app->singleton(CoreVersionResolver::class); 
        $this->app->singleton(KernelReleaseInstaller::class); 
        $this->app->singleton(ModuleReleaseInstaller::class);
        $this->app->singleton(PlatformManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RegisterCommand::class,
                SyncCommand::class,
                StatusCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/platform.php' => config_path('platform.php'),
        ], 'platform-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'platform');

        if ($this->app->bound('router')) {
            $this->app['router']->aliasMiddleware('platform.sync', MaybeRunPlatformSync::class);
        }

        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule): void {
            $schedule->command('platform:sync')
                ->daily()
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
