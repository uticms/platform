<?php

namespace Uticms\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Uticms\Platform\Services\SyncService;

final class MaybeRunPlatformSync
{
    public function __construct(
        private readonly SyncService $syncService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->syncService->isDue() && Cache::add('platform:sync:lock', 1, 300)) {
            app()->terminating(fn () => $this->syncService->runOnce());
        }

        return $response;
    }
}
