<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SupabaseRestClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ThemeController extends Controller
{
    public function __construct(private SupabaseRestClient $supabase) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        if (! $this->supabase->isConfigured()) {
            Log::error('Cannot create a study case because Supabase configuration is missing.');

            return back()->withErrors([
                'title' => 'Konfigurasi database belum lengkap.',
            ]);
        }

        try {
            $response = $this->supabase->request()
                ->withHeaders(['Prefer' => 'return=representation'])
                ->post($this->supabase->endpoint($this->supabase->themesTable()), [
                    'title' => trim($data['title']),
                    'description' => isset($data['description']) ? trim($data['description']) : null,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to Supabase while creating a study case.', [
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'title' => 'Layanan database sedang tidak dapat dijangkau.',
            ])->withInput();
        }

        if (! $response->successful()) {
            Log::warning('Supabase rejected study case creation.', [
                'status' => $response->status(),
            ]);

            return back()->withErrors([
                'title' => $response->status() === 409
                    ? 'Nama studi kasus sudah digunakan.'
                    : 'Studi kasus belum berhasil ditambahkan. Silakan coba lagi.',
            ])->withInput();
        }

        return redirect()->route('admin.participants.create')->with(
            'status',
            'Studi kasus '.$data['title'].' berhasil ditambahkan.'
        );
    }

    public function update(Request $request, string $theme): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        if (! $this->supabase->isConfigured()) {
            Log::error('Cannot update a study case because Supabase configuration is missing.');

            return back()->withErrors([
                'title' => 'Konfigurasi database belum lengkap.',
            ]);
        }

        try {
            $response = $this->supabase->request()
                ->withHeaders(['Prefer' => 'return=representation'])
                ->patch($this->supabase->endpoint($this->supabase->themesTable()).'?'.http_build_query([
                    'id' => 'eq.'.$theme,
                ]), [
                    'title' => trim($data['title']),
                    'description' => isset($data['description']) ? trim($data['description']) : null,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to Supabase while updating a study case.', [
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'title' => 'Layanan database sedang tidak dapat dijangkau.',
            ])->withInput();
        }

        if (! $response->successful()) {
            Log::warning('Supabase rejected study case update.', [
                'status' => $response->status(),
            ]);

            return back()->withErrors([
                'title' => $response->status() === 409
                    ? 'Nama studi kasus sudah digunakan.'
                    : 'Studi kasus belum berhasil diperbarui. Silakan coba lagi.',
            ])->withInput();
        }

        $updatedThemes = $response->json();
        if (! is_array($updatedThemes) || count($updatedThemes) === 0) {
            return back()->withErrors([
                'title' => 'Studi kasus tidak ditemukan.',
            ]);
        }

        return redirect()->route('admin.participants.create')->with(
            'status',
            'Studi kasus '.$data['title'].' berhasil diperbarui.'
        );
    }
}
