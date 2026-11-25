<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleSheetsException;
use App\Services\GoogleSheetsAssessmentClient;
use App\Services\SupabaseRestClient;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JudgeAssessmentController extends Controller
{
    public function __construct(private GoogleSheetsAssessmentClient $assessments) {}

    public function store(
        Request $request,
        string $participant,
        SupabaseRestClient $database,
    ): RedirectResponse {
        $criteria = array_keys(GoogleSheetsAssessmentClient::CRITERIA);
        $scoreRules = [];
        foreach ($criteria as $criterion) {
            $scoreRules['scores.'.$criterion] = ['required', 'integer', 'between:1,5'];
        }

        $data = $request->validate([
            'scores' => ['required', 'array:'.implode(',', $criteria)],
            ...$scoreRules,
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $submission = DB::table('submissions')
                ->where('participant_id', $participant)
                ->exists();
        } catch (QueryException $exception) {
            Log::error('Unable to verify the submission before saving a judge assessment.', [
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'assessment' => 'Data submission belum dapat diverifikasi. Silakan coba lagi.',
            ]);
        }

        abort_unless($submission, 404);

        if (! $database->isConfigured()) {
            Log::error('Cannot load a team name for a judge assessment because Supabase configuration is missing.');

            return back()->withErrors([
                'assessment' => 'Informasi tim belum dapat dimuat. Silakan hubungi panitia.',
            ])->withInput();
        }

        try {
            $teamResponse = $database->request()->get(
                $database->endpoint($database->usersTable()),
                [
                    'select' => 'id,name',
                    'id' => 'eq.'.$participant,
                    'role' => 'eq.participant',
                    'limit' => 1,
                ],
            );
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to Supabase while preparing a judge assessment.', [
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'assessment' => 'Informasi tim sedang tidak dapat dimuat. Coba lagi beberapa saat.',
            ])->withInput();
        }

        $teams = $teamResponse->json();
        if (! $teamResponse->successful()
            || ! is_array($teams)
            || ! is_array($teams[0] ?? null)
            || ! isset($teams[0]['name'])
            || ! is_string($teams[0]['name'])) {
            Log::warning('Supabase returned invalid team data while preparing a judge assessment.', [
                'status' => $teamResponse->status(),
            ]);

            return back()->withErrors([
                'assessment' => 'Informasi tim belum dapat diverifikasi. Silakan coba lagi.',
            ])->withInput();
        }

        $judge = $request->session()->get('app_user');

        try {
            $this->assessments->saveForJudge(
                (string) $judge['id'],
                (string) $judge['name'],
                $participant,
                $teams[0]['name'],
                $data['scores'],
                isset($data['comment']) ? trim($data['comment']) : null,
            );
        } catch (GoogleSheetsException $exception) {
            Log::warning('Unable to save a judge assessment to Google Sheets.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'assessment' => $this->assessments->isConfigured()
                    ? 'Penilaian belum berhasil disimpan ke Google Sheets. Periksa konfigurasi dan izin spreadsheet, lalu coba lagi.'
                    : 'Penilaian belum terhubung ke Google Sheets. Silakan hubungi panitia.',
            ])->withInput();
        }

        return redirect()
            ->route('admin.submissions.show', $participant)
            ->with('status', 'Penilaian berhasil disimpan.');
    }
}
