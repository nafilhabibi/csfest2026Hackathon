<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupabaseAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->session()->get('app_user');

        if (! is_array($user) || ! is_string($user['email'] ?? null)) {
            $request->session()->forget('app_user');

            return redirect()->route('login');
        }

        return $next($request);
    }
}
