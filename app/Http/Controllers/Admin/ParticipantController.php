<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SubmissionAvailability;
use App\Services\SupabaseRestClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ParticipantController extends Controller
{
    public function __construct(
        private SupabaseRestClient $supabase,
        private SubmissionAvailability $submissionAvailability,
    ) {}

    public function create(): View
    {
        $participants = [];
        $themes = [];
        $dataError = null;
        $submissionClosed = $this->submissionAvailability->isClosed();

        if (! $this->supabase->isConfigured()) {
            Log::error('Cannot load participant management because Supabase configuration is missing.');
            $dataError = 'Konfigurasi database belum lengkap.';

            return view('admin.participants.create', compact('participants', 'themes', 'dataError', 'submissionClosed'));
        }

        try {
            $participantsResponse = $this->supabase->request()->get(
                $this->supabase->endpoint($this->supabase->usersTable()),
                [
                    'select' => 'id,name,theme_id,theme:themes(id,title)',
                    'role' => 'eq.participant',
                    'order' => 'name.asc',
                ]
            );
            $themesResponse = $this->supabase->request()->get(
                $this->supabase->endpoint($this->supabase->themesTable()),
                [
                    'select' => 'id,title,description',
                    'order' => 'title.asc',
                ]
            );
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to Supabase while loading participant management.', [
                'exception' => $exception::class,
            ]);
            $dataError = 'Layanan database sedang tidak dapat dijangkau.';

            return view('admin.participants.create', compact('participants', 'themes', 'dataError', 'submissionClosed'));
        }

        $participantRows = $participantsResponse->json();
        $themeRows = $themesResponse->json();

        if (! $participantsResponse->successful() || ! is_array($participantRows)
            || ! $themesResponse->successful() || ! is_array($themeRows)) {
            Log::warning('Supabase returned an unsuccessful response while loading participant management.', [
                'participants_status' => $participantsResponse->status(),
                'themes_status' => $themesResponse->status(),
            ]);
            $dataError = 'Data tim dan studi kasus belum dapat dimuat. Silakan coba lagi.';

            return view('admin.participants.create', compact('participants', 'themes', 'dataError', 'submissionClosed'));
        }

        $participants = $participantRows;
        $themes = $themeRows;

        return view('admin.participants.create', compact('participants', 'themes', 'dataError', 'submissionClosed'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'max:1024'],
            'theme_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! $this->supabase->isConfigured()) {
            Log::error('Cannot create a participant because Supabase configuration is missing.');

            return back()->withErrors([
                'name' => 'Konfigurasi database belum lengkap.',
            ]);
        }

        $teamName = trim($data['name']);
        $loginName = mb_strtolower($teamName);
        $usersEndpoint = $this->supabase->endpoint($this->supabase->usersTable());

        try {
            $existing = $this->supabase->request()->get($usersEndpoint, [
                'select' => 'id',
                'login_name' => 'eq.'.$loginName,
                'limit' => 1,
            ]);

            if (! $existing->successful()) {
                Log::warning('Supabase could not check for a duplicate participant team name.', [
                    'status' => $existing->status(),
                ]);

                return back()->withErrors([
                    'name' => 'Nama tim belum dapat diverifikasi. Silakan coba lagi.',
                ])->withInput($request->except(['password', 'password_confirmation']));
            }

            $existingUsers = $existing->json();
            if (! is_array($existingUsers)) {
                Log::warning('Supabase returned an invalid response while checking a participant team name.');

                return back()->withErrors([
                    'name' => 'Nama tim belum dapat diverifikasi. Silakan coba lagi.',
                ])->withInput($request->except(['password', 'password_confirmation']));
            }

            if (count($existingUsers) > 0) {
                return back()->withErrors([
                    'name' => 'Nama tim sudah digunakan.',
                ])->withInput($request->except(['password', 'password_confirmation']));
            }

            if (isset($data['theme_id'])) {
                $theme = $this->supabase->request()->get(
                    $this->supabase->endpoint($this->supabase->themesTable()),
                    [
                        'select' => 'id',
                        'id' => 'eq.'.$data['theme_id'],
                        'limit' => 1,
                    ]
                );

                $themeRows = $theme->json();
                if (! $theme->successful() || ! is_array($themeRows)) {
                    Log::warning('Supabase could not validate the study case for a new participant.', [
                        'status' => $theme->status(),
                    ]);

                    return back()->withErrors([
                        'theme_id' => 'Studi kasus belum dapat diverifikasi. Silakan coba lagi.',
                    ])->withInput($request->except(['password', 'password_confirmation']));
                }

                if (count($themeRows) === 0) {
                    return back()->withErrors([
                        'theme_id' => 'Studi kasus yang dipilih tidak tersedia.',
                    ])->withInput($request->except(['password', 'password_confirmation']));
                }
            }

            $created = $this->supabase->request()
                ->withHeaders(['Prefer' => 'return=representation'])
                ->post($usersEndpoint, [
                    'name' => $teamName,
                    'login_name' => $loginName,
                    'password' => Hash::make($data['password']),
                    'role' => 'participant',
                    'theme_id' => isset($data['theme_id']) ? (int) $data['theme_id'] : null,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to Supabase while creating a participant.', [
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'name' => 'Layanan database sedang tidak dapat dijangkau.',
            ])->withInput($request->except(['password', 'password_confirmation']));
        }

        if (! $created->successful()) {
            Log::warning('Supabase rejected participant creation.', [
                'status' => $created->status(),
            ]);

            return back()->withErrors([
                'name' => $created->status() === 409
                    ? 'Nama tim sudah digunakan.'
                    : 'Akun tim belum berhasil dibuat. Silakan coba lagi.',
            ])->withInput($request->except(['password', 'password_confirmation']));
        }

        return redirect()->route('admin.participants.create')->with(
            'status',
            'Akun tim '.$teamName.' berhasil dibuat.'
        );
    }

    public function updateTheme(Request $request, string $participant): RedirectResponse
    {
        $data = $request->validate([
            'theme_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! $this->supabase->isConfigured()) {
            Log::error('Cannot update a participant theme because Supabase configuration is missing.');

            return back()->withErrors([
                'theme_id' => 'Konfigurasi database belum lengkap.',
            ]);
        }

        try {
            if (isset($data['theme_id'])) {
                $theme = $this->supabase->request()->get(
                    $this->supabase->endpoint($this->supabase->themesTable()),
                    [
                        'select' => 'id',
                        'id' => 'eq.'.$data['theme_id'],
                        'limit' => 1,
                    ]
                );

                $themeRows = $theme->json();
                if (! $theme->successful() || ! is_array($themeRows)) {
                    Log::warning('Supabase could not validate a study case assignment.', [
                        'status' => $theme->status(),
                    ]);

                    return back()->withErrors([
                        'theme_id' => 'Studi kasus belum dapat diverifikasi. Silakan coba lagi.',
                    ]);
                }

                if (count($themeRows) === 0) {
                    return back()->withErrors([
                        'theme_id' => 'Studi kasus yang dipilih tidak tersedia.',
                    ]);
                }
            }

            $updated = $this->supabase->request()
                ->withHeaders(['Prefer' => 'return=representation'])
                ->patch($this->supabase->endpoint($this->supabase->usersTable()).'?'.http_build_query([
                    'id' => 'eq.'.$participant,
                    'role' => 'eq.participant',
                ]), [
                    'theme_id' => isset($data['theme_id']) ? (int) $data['theme_id'] : null,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to Supabase while assigning a participant theme.', [
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'theme_id' => 'Layanan database sedang tidak dapat dijangkau.',
            ]);
        }

        $updatedParticipants = $updated->json();
        if (! $updated->successful() || ! is_array($updatedParticipants)) {
            Log::warning('Supabase rejected a participant theme assignment.', [
                'status' => $updated->status(),
            ]);

            return back()->withErrors([
                'theme_id' => 'Studi kasus tim belum berhasil diperbarui. Silakan coba lagi.',
            ]);
        }

        if (count($updatedParticipants) === 0) {
            return back()->withErrors([
                'theme_id' => 'Tim tidak ditemukan.',
            ]);
        }

        return redirect()->route('admin.participants.create')->with(
            'status',
            'Studi kasus berhasil diperbarui untuk tim '.$updatedParticipants[0]['name'].'.'
        );
    }
}
