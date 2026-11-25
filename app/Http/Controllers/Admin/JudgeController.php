<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SupabaseRestClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class JudgeController extends Controller
{
    public function __construct(private SupabaseRestClient $supabase) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'judge_name' => ['required', 'string', 'max:255'],
            'judge_password' => ['required', 'string', 'min:8', 'confirmed', 'max:1024'],
        ]);

        if (! $this->supabase->isConfigured()) {
            Log::error('Cannot create a judge account because Supabase configuration is missing.');

            return back()->withErrors([
                'judge_name' => 'Konfigurasi database belum lengkap.',
            ]);
        }

        $judgeName = trim($data['judge_name']);
        $loginName = mb_strtolower($judgeName);
        $usersEndpoint = $this->supabase->endpoint($this->supabase->usersTable());

        try {
            $existing = $this->supabase->request()->get($usersEndpoint, [
                'select' => 'id',
                'login_name' => 'eq.'.$loginName,
                'limit' => 1,
            ]);

            if (! $existing->successful()) {
                Log::warning('Supabase could not check for a duplicate judge name.', [
                    'status' => $existing->status(),
                ]);

                return back()->withErrors([
                    'judge_name' => 'Nama juri belum dapat diverifikasi. Silakan coba lagi.',
                ])->withInput($request->except(['judge_password', 'judge_password_confirmation']));
            }

            $existingUsers = $existing->json();
            if (! is_array($existingUsers)) {
                Log::warning('Supabase returned an invalid response while checking a judge name.');

                return back()->withErrors([
                    'judge_name' => 'Nama juri belum dapat diverifikasi. Silakan coba lagi.',
                ])->withInput($request->except(['judge_password', 'judge_password_confirmation']));
            }

            if (count($existingUsers) > 0) {
                return back()->withErrors([
                    'judge_name' => 'Nama login sudah digunakan.',
                ])->withInput($request->except(['judge_password', 'judge_password_confirmation']));
            }

            $created = $this->supabase->request()
                ->withHeaders(['Prefer' => 'return=representation'])
                ->post($usersEndpoint, [
                    'name' => $judgeName,
                    'login_name' => $loginName,
                    'password' => Hash::make($data['judge_password']),
                    'role' => 'judge',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to Supabase while creating a judge account.', [
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'judge_name' => 'Layanan database sedang tidak dapat dijangkau.',
            ])->withInput($request->except(['judge_password', 'judge_password_confirmation']));
        }

        if (! $created->successful()) {
            $error = $created->json();
            $errorCode = is_array($error) && is_string($error['code'] ?? null)
                ? $error['code']
                : null;
            Log::warning('Supabase rejected judge account creation.', [
                'status' => $created->status(),
                'supabase_code' => $errorCode,
            ]);

            return back()->withErrors([
                'judge_name' => match (true) {
                    $created->status() === 409 => 'Nama login sudah digunakan.',
                    $errorCode === '23514' => 'Database belum mengizinkan role juri. Terapkan migrasi Supabase terbaru, lalu coba lagi.',
                    default => 'Akun juri belum berhasil dibuat. Silakan coba lagi.',
                },
            ])->withInput($request->except(['judge_password', 'judge_password_confirmation']));
        }

        return redirect()->route('admin.participants.create')->with(
            'status',
            'Akun juri '.$judgeName.' berhasil dibuat.'
        );
    }
}
