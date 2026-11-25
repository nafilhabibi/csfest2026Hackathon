<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\GoogleSheetsException;
use App\Http\Controllers\Controller;
use App\Services\GoogleSheetsAssessmentClient;
use App\Services\SupabaseRestClient;
use App\Services\SupabaseSubmissionClient;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    public function __construct(
        private SupabaseRestClient $database,
        private SupabaseSubmissionClient $storage,
        private GoogleSheetsAssessmentClient $assessments,
    ) {}

    public function index(Request $request): View
    {
        $participants = [];
        $dataError = null;
        $assessmentError = null;
        $judgeAssessments = [];
        $user = $request->session()->get('app_user', []);
        $isJudge = ($user['role'] ?? null) === 'judge';

        if (! $this->database->isConfigured()) {
            Log::error('Cannot load admin submissions because Supabase configuration is missing.');
            $dataError = 'Konfigurasi database belum lengkap.';

            return view('admin.submissions.index', compact('participants', 'dataError', 'assessmentError', 'isJudge'));
        }

        try {
            $response = $this->database->request()->get(
                $this->database->endpoint($this->database->usersTable()),
                [
                    'select' => 'id,name',
                    'role' => 'eq.participant',
                    'order' => 'name.asc',
                ],
            );
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to Supabase while loading admin submissions.', [
                'exception' => $exception::class,
            ]);
            $dataError = 'Layanan database sedang tidak dapat dijangkau.';

            return view('admin.submissions.index', compact('participants', 'dataError', 'assessmentError', 'isJudge'));
        }

        $participantRows = $response->json();
        if (! $response->successful() || ! is_array($participantRows) || ! $this->hasValidParticipants($participantRows)) {
            Log::warning('Supabase returned invalid team data while loading admin submissions.', [
                'status' => $response->status(),
            ]);
            $dataError = 'Data tim belum dapat dimuat. Silakan coba lagi.';

            return view('admin.submissions.index', compact('participants', 'dataError', 'assessmentError', 'isJudge'));
        }

        try {
            $submittedParticipantIds = DB::table('submissions')->pluck('participant_id')->all();
        } catch (QueryException $exception) {
            Log::error('Unable to load submission statuses from the application database.', [
                'exception' => $exception::class,
            ]);
            $dataError = 'Data submission belum dapat dimuat. Silakan periksa koneksi database.';

            return view('admin.submissions.index', compact('participants', 'dataError', 'assessmentError', 'isJudge'));
        }

        $submittedParticipantIds = array_fill_keys(
            array_map('strval', $submittedParticipantIds),
            true,
        );

        if ($isJudge) {
            try {
                $judgeAssessments = $this->assessments->forJudge((string) $user['id']);
            } catch (GoogleSheetsException $exception) {
                Log::warning('Unable to load judge assessment statuses from Google Sheets.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
                $assessmentError = 'Status penilaian belum dapat dimuat. Anda tetap dapat membuka submission dan mencoba lagi.';
            }
        }

        $participants = array_map(
            fn (array $participant): array => [
                ...$participant,
                'has_submission' => isset($submittedParticipantIds[(string) $participant['id']]),
                'has_assessment' => isset($judgeAssessments[(string) $participant['id']]),
            ],
            $participantRows,
        );

        return view('admin.submissions.index', compact(
            'participants',
            'dataError',
            'assessmentError',
            'isJudge',
        ));
    }

    public function show(Request $request, string $participant): View
    {
        try {
            $submission = DB::table('submissions')
                ->where('participant_id', $participant)
                ->first();
        } catch (QueryException $exception) {
            Log::error('Unable to load a participant submission from the application database.', [
                'exception' => $exception::class,
            ]);

            abort(503, 'Data submission sedang tidak dapat diakses.');
        }

        abort_if($submission === null, 404);

        $team = null;
        $teamError = null;
        if (! $this->database->isConfigured()) {
            Log::error('Cannot load the team details for an admin submission because Supabase configuration is missing.');
            $teamError = 'Informasi tim belum dapat dimuat karena konfigurasi database belum lengkap.';
        } else {
            try {
                $teamResponse = $this->database->request()->get(
                    $this->database->endpoint($this->database->usersTable()),
                    [
                        'select' => 'id,name,theme:themes(title)',
                        'id' => 'eq.'.$participant,
                        'role' => 'eq.participant',
                        'limit' => 1,
                    ],
                );
            } catch (ConnectionException $exception) {
                Log::warning('Unable to connect to Supabase while loading an admin submission team.', [
                    'exception' => $exception::class,
                ]);
                $teamError = 'Informasi tim sedang tidak dapat dimuat. Coba muat ulang halaman.';

                return $this->submissionView($submission, $team, $teamError, $request);
            }

            $teamRows = $teamResponse->json();
            if ($teamResponse->successful() && is_array($teamRows) && $teamRows === []) {
                abort(404);
            }

            if (! $teamResponse->successful()
                || ! is_array($teamRows)
                || ! isset($teamRows[0])
                || ! $this->hasValidParticipant($teamRows[0])) {
                Log::warning('Supabase returned invalid team data while loading an admin submission.', [
                    'status' => $teamResponse->status(),
                ]);
                $teamError = 'Informasi tim belum dapat dimuat. Coba muat ulang halaman.';
            } else {
                $team = $teamRows[0];
            }
        }

        return $this->submissionView($submission, $team, $teamError, $request);
    }

    /**
     * @param  array<mixed>  $participants
     */
    private function hasValidParticipants(array $participants): bool
    {
        foreach ($participants as $participant) {
            if (! $this->hasValidParticipant($participant)) {
                return false;
            }
        }

        return true;
    }

    private function hasValidParticipant(mixed $participant): bool
    {
        return is_array($participant)
            && (is_string($participant['id'] ?? null) || is_int($participant['id'] ?? null))
            && is_string($participant['name'] ?? null)
            && trim($participant['name']) !== '';
    }

    /**
     * @param  array<string, mixed>|null  $team
     */
    private function submissionView(
        object $submission,
        ?array $team,
        ?string $teamError,
        Request $request,
    ): View {
        $prototypeUrl = null;
        $mediaError = null;
        $assessment = null;
        $assessmentError = null;

        if (! $this->storage->isConfigured()) {
            Log::error('Cannot create an admin submission media URL because Supabase Storage configuration is missing.');
            $mediaError = 'File prototype belum dapat ditampilkan karena konfigurasi Storage belum lengkap.';
        } else {
            $signedUrlResponse = null;

            try {
                $signedUrlResponse = $this->storage->createSignedUrl($submission->prototype_path);
            } catch (ConnectionException $exception) {
                Log::warning('Unable to create a signed URL for an admin submission prototype.', [
                    'exception' => $exception::class,
                ]);
                $mediaError = 'File prototype sedang tidak dapat dibuka. Coba muat ulang halaman.';
            }

            if ($signedUrlResponse !== null) {
                $signedUrl = $signedUrlResponse->json('signedURL');
                if ($signedUrlResponse->successful() && is_string($signedUrl)) {
                    $prototypeUrl = $this->storage->signedUrl($signedUrl);
                } else {
                    Log::warning('Supabase returned an unsuccessful response while signing an admin submission prototype.', [
                        'status' => $signedUrlResponse->status(),
                    ]);
                    $mediaError = 'File prototype belum dapat dibuka. Coba muat ulang halaman.';
                }
            }
        }

        $user = $request->session()->get('app_user', []);
        if (($user['role'] ?? null) === 'judge') {
            try {
                $assessment = $this->assessments->findForJudge((string) $user['id'], (string) $submission->participant_id);
            } catch (GoogleSheetsException $exception) {
                Log::warning('Unable to load a judge assessment from Google Sheets.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
                $assessmentError = 'Data penilaian belum dapat dimuat. Silakan coba lagi.';
            }
        }

        return view('admin.submissions.show', compact(
            'submission',
            'team',
            'teamError',
            'prototypeUrl',
            'mediaError',
            'assessment',
            'assessmentError',
        ));
    }
}
