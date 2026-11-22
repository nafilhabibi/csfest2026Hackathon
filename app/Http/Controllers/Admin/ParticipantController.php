<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ParticipantController extends Controller
{
    public function create(Request $request): View
    {
        return view('admin.participants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'max:1024'],
        ]);

        $url = config('services.supabase.url');
        $key = config('services.supabase.key');
        $usersTable = config('services.supabase.users_table', 'users');

        if (! is_string($url) || $url === '' || ! is_string($key) || $key === '' || ! is_string($usersTable) || $usersTable === '') {
            Log::error('Cannot create a participant because Supabase configuration is missing.');

            return back()->withErrors([
                'email' => 'Konfigurasi database belum lengkap.',
            ]);
        }

        $endpoint = rtrim($url, '/').'/rest/v1/'.rawurlencode($usersTable);
        $headers = [
            'apikey' => $key,
            'Authorization' => 'Bearer '.$key,
        ];

        try {
            $existing = Http::acceptJson()
                ->withHeaders($headers)
                ->connectTimeout(3)
                ->timeout(10)
                ->get($endpoint, [
                    'select' => 'id',
                    'email' => 'eq.'.strtolower($data['email']),
                    'limit' => 1,
                ]);

            if ($existing->successful() && count($existing->json()) > 0) {
                return back()->withErrors([
                    'email' => 'Email sudah terdaftar sebagai akun peserta.',
                ])->withInput();
            }

            $created = Http::acceptJson()
                ->withHeaders([
                    ...$headers,
                    'Prefer' => 'return=representation',
                ])
                ->connectTimeout(3)
                ->timeout(10)
                ->post($endpoint, [
                    'name' => trim($data['name']),
                    'email' => strtolower(trim($data['email'])),
                    'password' => Hash::make($data['password']),
                    'role' => 'participant',
                    'email_verified_at' => now()->utc()->toISOString(),
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to Supabase while creating a participant.', [
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'email' => 'Layanan database sedang tidak dapat dijangkau.',
            ])->withInput();
        }

        if (! $created->successful()) {
            Log::warning('Supabase rejected participant creation.', [
                'status' => $created->status(),
            ]);

            return back()->withErrors([
                'email' => 'Akun peserta belum berhasil dibuat. Silakan coba lagi.',
            ])->withInput();
        }

        return redirect()->route('admin.participants.create')->with(
            'status',
            'Akun peserta berhasil dibuat untuk '.$data['email'].'.'
        );
    }
}
