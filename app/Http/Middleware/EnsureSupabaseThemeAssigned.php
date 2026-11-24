<?php

namespace App\Http\Middleware;

use App\Services\SupabaseRestClient;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupabaseThemeAssigned
{
    public function __construct(private SupabaseRestClient $supabase) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->supabase->isConfigured()) {
            Log::error('Cannot verify a participant theme before allowing submission access because Supabase configuration is missing.');

            return $this->redirectToDashboard('Informasi tema belum dapat diverifikasi. Coba muat ulang beberapa saat lagi.');
        }

        $user = $request->session()->get('app_user');

        try {
            $response = $this->supabase->request()->get(
                $this->supabase->endpoint($this->supabase->usersTable()),
                [
                    'select' => 'theme_id',
                    'id' => 'eq.'.$user['id'],
                    'limit' => 1,
                ],
            );
        } catch (ConnectionException $exception) {
            Log::warning('Unable to verify a participant theme before allowing submission access.', [
                'exception' => $exception::class,
            ]);

            return $this->redirectToDashboard('Informasi tema belum dapat diverifikasi. Coba muat ulang beberapa saat lagi.');
        }

        $participants = $response->json();
        if (! $response->successful() || ! is_array($participants) || ! isset($participants[0]) || ! is_array($participants[0])) {
            Log::warning('Unable to verify a participant theme for submission access from the Supabase response.', [
                'status' => $response->status(),
            ]);

            return $this->redirectToDashboard('Informasi tema belum dapat diverifikasi. Coba muat ulang beberapa saat lagi.');
        }

        $themeId = $participants[0]['theme_id'] ?? null;
        $hasAssignedTheme = is_int($themeId)
            ? $themeId > 0
            : is_string($themeId) && ctype_digit($themeId) && (int) $themeId > 0;

        if (! $hasAssignedTheme) {
            return $this->redirectToDashboard('Submission hanya dapat dibuka setelah panitia menetapkan tema tim.');
        }

        return $next($request);
    }

    private function redirectToDashboard(string $message): Response
    {
        return redirect()
            ->route('dashboard')
            ->with('submission_access_error', $message);
    }
}
