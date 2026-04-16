<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use NativeBlade\Facades\NativeBlade;
use Symfony\Component\HttpFoundation\Response;

class NativeBladeAuth
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = NativeBlade::getState('auth.user');

        if (! $user) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
