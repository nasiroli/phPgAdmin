<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NativeBladeAuth
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Auth is enforced in the UI (layouts.app + page guards). Avoid HTTP
        // redirects here so the NativeBlade / Livewire shell always receives a
        // full response and wire:navigate can load normally.
        return $next($request);
    }
}
