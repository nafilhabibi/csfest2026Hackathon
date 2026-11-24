<?php

use App\Http\Controllers\Admin\ParticipantController;
use App\Http\Controllers\Admin\SubmissionController as AdminSubmissionController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Auth\SupabaseLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/login', [SupabaseLoginController::class, 'showLogin'])->name('login');
Route::post('/login', [SupabaseLoginController::class, 'login'])->middleware('throttle:5,1')->name('login.store');

Route::middleware('supabase.auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::middleware(['supabase.participant', 'supabase.theme-assigned'])->group(function (): void {
        Route::get('/submission', [SubmissionController::class, 'show'])->name('submission.show');
        Route::post('/submission', [SubmissionController::class, 'store'])->name('submission.store');
    });
    Route::middleware('supabase.admin')->group(function (): void {
        Route::get('/admin/submissions', [AdminSubmissionController::class, 'index'])->name('admin.submissions.index');
        Route::get('/admin/submissions/{participant}', [AdminSubmissionController::class, 'show'])
            ->where('participant', '[^/]+')
            ->name('admin.submissions.show');
        Route::get('/admin/participants/create', [ParticipantController::class, 'create'])->name('admin.participants.create');
        Route::post('/admin/participants', [ParticipantController::class, 'store'])->name('admin.participants.store');
        Route::patch('/admin/participants/{participant}/theme', [ParticipantController::class, 'updateTheme'])
            ->name('admin.participants.theme.update');
        Route::post('/admin/themes', [ThemeController::class, 'store'])->name('admin.themes.store');
        Route::patch('/admin/themes/{theme}', [ThemeController::class, 'update'])
            ->whereNumber('theme')
            ->name('admin.themes.update');
    });
    Route::post('/logout', [SupabaseLoginController::class, 'logout'])->name('logout');
});
