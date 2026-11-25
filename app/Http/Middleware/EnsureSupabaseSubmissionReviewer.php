<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupabaseSubmissionReviewer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->session()->get('app_user');

        if (! is_array($user) || ! in_array($user['role'] ?? null, ['admin', 'judge'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
