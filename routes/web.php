<?php

use App\Http\Controllers\Admin\ParticipantController;
use App\Http\Controllers\Auth\SupabaseLoginController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/login', [SupabaseLoginController::class, 'showLogin'])->name('login');
Route::post('/login', [SupabaseLoginController::class, 'login'])->middleware('throttle:5,1')->name('login.store');

Route::middleware('supabase.auth')->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::middleware('supabase.admin')->group(function (): void {
        Route::get('/admin/participants/create', [ParticipantController::class, 'create'])->name('admin.participants.create');
        Route::post('/admin/participants', [ParticipantController::class, 'store'])->name('admin.participants.store');
    });
    Route::post('/logout', [SupabaseLoginController::class, 'logout'])->name('logout');
});
