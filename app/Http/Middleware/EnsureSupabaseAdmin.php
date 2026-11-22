<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupabaseAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->session()->get('app_user');

        if (! is_array($user) || ($user['role'] ?? null) !== 'admin') {
            abort(403);
        }

        return $next($request);
    }
}
