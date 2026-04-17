<?php

namespace App\Http\Middleware;

use App\Services\AppSetupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleSetupGate
{
    public function __construct(
        private AppSetupService $setup
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('setup.gate_enabled', true)) {
            return $next($request);
        }

        if ($request->is('up')) {
            return $next($request);
        }

        $complete = $this->setup->isComplete();

        if ($request->routeIs('setup')) {
            if ($complete) {
                return redirect()->route('login');
            }

            return $next($request);
        }

        if (! $complete) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
