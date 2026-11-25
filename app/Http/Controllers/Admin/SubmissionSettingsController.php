<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SubmissionAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubmissionSettingsController extends Controller
{
    public function __construct(private SubmissionAvailability $availability) {}

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'closed' => ['required', 'boolean'],
        ]);

        $closed = filter_var($data['closed'], FILTER_VALIDATE_BOOLEAN);
        $this->availability->setClosed($closed);

        return redirect()
            ->route('admin.participants.create')
            ->with('status', $closed
                ? 'Submission berhasil ditutup. Peserta tidak dapat mengirim atau memperbarui submission.'
                : 'Submission dibuka kembali.');
    }
}
