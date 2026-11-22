<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SupabaseLoginController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (is_array($request->session()->get('app_user'))) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        $url = config('services.supabase.url');
        $key = config('services.supabase.key');
        $usersTable = config('services.supabase.users_table', 'users');

        if (! is_string($url) || $url === '' || ! is_string($key) || $key === '' || ! is_string($usersTable) || $usersTable === '') {
            Log::error('Database login is unavailable because its Supabase configuration is missing.');

            return back()->withErrors([
                'email' => 'Database login belum dikonfigurasi. Silakan hubungi panitia.',
            ])->onlyInput('email');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'apikey' => $key,
                    'Authorization' => 'Bearer '.$key,
                ])
                ->connectTimeout(3)
                ->timeout(10)
                ->get(rtrim($url, '/').'/rest/v1/'.rawurlencode($usersTable), [
                    'select' => '*',
                    'email' => 'eq.'.strtolower($credentials['email']),
                    'limit' => 1,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to the Supabase database.', [
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'email' => 'Layanan login sedang tidak dapat dijangkau. Coba lagi beberapa saat.',
            ])->onlyInput('email');
        }

        if (! $response->successful()) {
            Log::warning('Supabase database returned an unsuccessful response.', [
                'status' => $response->status(),
            ]);

            return back()->withErrors([
                'email' => 'Login belum berhasil. Silakan coba lagi.',
            ])->onlyInput('email');
        }

        $users = $response->json();
        $user = is_array($users) ? ($users[0] ?? null) : null;
        $passwordHash = is_array($user)
            ? ($user['password_hash'] ?? $user['password'] ?? null)
            : null;

        if (! is_array($user)
            || ! is_string($user['email'] ?? null)
            || ! is_string($passwordHash)
            || ! Hash::check($credentials['password'], $passwordHash)) {
            return back()->withErrors([
                'email' => 'Email atau kata sandi tidak sesuai.',
            ])->onlyInput('email');
        }

        $name = $user['name'] ?? $user['full_name'] ?? $user['email'];
        if (! is_string($name) || $name === '') {
            $name = $user['email'];
        }

        $request->session()->regenerate();
        $request->session()->put('app_user', [
            'id' => isset($user['id']) && (is_string($user['id']) || is_int($user['id'])) ? $user['id'] : null,
            'name' => $name,
            'email' => $user['email'],
            'role' => $this->resolveRole($user),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function resolveRole(array $user): string
    {
        $role = $user['role'] ?? null;
        if ($role === 'admin' || $role === 'participant') {
            return $role;
        }

        $adminEmails = collect(preg_split('/[,;]/', (string) config('services.supabase.admin_emails', ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter();

        return $adminEmails->contains(strtolower((string) ($user['email'] ?? '')))
            ? 'admin'
            : 'participant';
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('app_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
