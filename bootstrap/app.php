<?php

use App\Http\Middleware\EnsureSupabaseAdmin;
use App\Http\Middleware\EnsureSupabaseAuthenticated;
use App\Http\Middleware\EnsureSupabaseDashboardAccess;
use App\Http\Middleware\EnsureSupabaseJudge;
use App\Http\Middleware\EnsureSupabaseParticipant;
use App\Http\Middleware\EnsureSupabaseSubmissionReviewer;
use App\Http\Middleware\EnsureSupabaseThemeAssigned;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->alias([
            'supabase.auth' => EnsureSupabaseAuthenticated::class,
            'supabase.admin' => EnsureSupabaseAdmin::class,
            'supabase.participant' => EnsureSupabaseParticipant::class,
            'supabase.dashboard' => EnsureSupabaseDashboardAccess::class,
            'supabase.judge' => EnsureSupabaseJudge::class,
            'supabase.submission-reviewer' => EnsureSupabaseSubmissionReviewer::class,
            'supabase.theme-assigned' => EnsureSupabaseThemeAssigned::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
