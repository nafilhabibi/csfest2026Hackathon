<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleSheetsException;
use App\Services\GoogleSheetsAssessmentClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ParticipantAssessmentController extends Controller
{
    public function __construct(private GoogleSheetsAssessmentClient $assessments) {}

    public function show(Request $request): View
    {
        $user = $request->session()->get('app_user', []);
        $assessments = [];
        $assessmentError = null;

        try {
            $assessments = $this->assessments->forTeam((string) $user['id']);
        } catch (GoogleSheetsException $exception) {
            Log::warning('Unable to load participant assessment results from Google Sheets.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $assessmentError = 'Penilaian belum dapat dimuat. Silakan coba lagi beberapa saat.';
        }

        return view('submission.assessment', compact('user', 'assessments', 'assessmentError'));
    }
}
