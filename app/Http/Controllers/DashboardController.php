<?php

namespace App\Http\Controllers;

use App\Services\SupabaseRestClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private SupabaseRestClient $supabase) {}

    public function index(Request $request): View
    {
        $user = $request->session()->get('app_user', []);
        $theme = null;
        $themeError = null;

        if (($user['role'] ?? null) === 'participant') {
            if (! $this->supabase->isConfigured()) {
                Log::error('Cannot load a participant theme because Supabase configuration is missing.');
                $themeError = 'Informasi studi kasus belum dapat dimuat. Silakan hubungi panitia.';
            } else {
                try {
                    $response = $this->supabase->request()->get(
                        $this->supabase->endpoint($this->supabase->usersTable()),
                        [
                            'select' => 'theme:themes(id,title,description)',
                            'id' => 'eq.'.$user['id'],
                            'limit' => 1,
                        ]
                    );
                } catch (ConnectionException $exception) {
                    Log::warning('Unable to connect to Supabase while loading a participant theme.', [
                        'exception' => $exception::class,
                    ]);
                    $themeError = 'Informasi studi kasus sedang tidak dapat dimuat. Coba lagi beberapa saat.';

                    return view('dashboard', compact('user', 'theme', 'themeError'));
                }

                $rows = $response->json();
                if (! $response->successful() || ! is_array($rows) || ! isset($rows[0]) || ! is_array($rows[0])) {
                    Log::warning('Supabase returned an unsuccessful response while loading a participant theme.', [
                        'status' => $response->status(),
                    ]);
                    $themeError = 'Informasi studi kasus belum dapat dimuat. Silakan coba lagi.';
                } else {
                    $theme = is_array($rows[0]['theme'] ?? null) ? $rows[0]['theme'] : null;
                }
            }
        }

        return view('dashboard', compact('user', 'theme', 'themeError'));
    }
}
