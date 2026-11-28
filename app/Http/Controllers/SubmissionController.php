<?php

namespace App\Http\Controllers;

use App\Services\SubmissionAvailability;
use App\Services\SupabaseSubmissionClient;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    public function __construct(
        private SupabaseSubmissionClient $supabase,
        private SubmissionAvailability $submissionAvailability,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->session()->get('app_user');
        $submission = null;
        $prototypeUrl = null;
        $pageError = null;
        $submissionClosed = $this->submissionAvailability->isClosed();

        if (! $this->supabase->isConfigured()) {
            Log::error('Cannot load a submission because Supabase storage configuration is missing.');
            $pageError = 'Layanan submission belum dikonfigurasi. Silakan hubungi panitia.';

            return view('submission.show', compact('user', 'submission', 'prototypeUrl', 'pageError', 'submissionClosed'));
        }

        $submissionRecord = DB::table('submissions')
            ->where('participant_id', (string) $user['id'])
            ->first();
        $submission = $submissionRecord !== null ? (array) $submissionRecord : null;

        if (is_string($submission['prototype_path'] ?? null)) {
            try {
                $signedUrlResponse = $this->supabase->createSignedUrl($submission['prototype_path']);
            } catch (ConnectionException $exception) {
                Log::warning('Unable to create a signed URL for a participant prototype.', [
                    'exception' => $exception::class,
                ]);
                $pageError = 'Tautan prototype belum dapat dibuat. Coba muat ulang halaman.';

                return view('submission.show', compact('user', 'submission', 'prototypeUrl', 'pageError', 'submissionClosed'));
            }

            $signedUrl = $signedUrlResponse->json('signedURL');
            if ($signedUrlResponse->successful() && is_string($signedUrl)) {
                $prototypeUrl = $this->supabase->signedUrl($signedUrl);
            } else {
                Log::warning('Supabase returned an unsuccessful response while creating a prototype signed URL.', [
                    'status' => $signedUrlResponse->status(),
                ]);
                $pageError = 'Tautan prototype belum dapat dibuat. Coba muat ulang halaman.';
            }
        }

        return view('submission.show', compact('user', 'submission', 'prototypeUrl', 'pageError', 'submissionClosed'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->session()->get('app_user');

        if ($this->submissionAvailability->isClosed()) {
            return back()->withErrors([
                'submission' => 'Submission sudah ditutup. Pengiriman dan pembaruan tidak dapat dilakukan.',
            ]);
        }

        if (! $this->supabase->isConfigured()) {
            Log::error('Cannot save a submission because Supabase storage configuration is missing.');

            return back()->withErrors([
                'submission' => 'Layanan submission belum dikonfigurasi. Silakan hubungi panitia.',
            ]);
        }

        $existingRecord = DB::table('submissions')
            ->where('participant_id', (string) $user['id'])
            ->first();
        $existingSubmission = $existingRecord !== null ? (array) $existingRecord : null;
        $prototypeFile = $request->file('prototype');
        $maximumFileSize = $prototypeFile?->isValid()
            && str_starts_with((string) $prototypeFile->getMimeType(), 'image/')
                ? 5120
                : 30720;

        $data = $request->validate([
            'submitter_name' => ['required', 'string', 'max:255'],
            'github_link' => [
                'required',
                'url',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $host = parse_url($value, PHP_URL_HOST);

                    if (! is_string($host) || ! in_array(mb_strtolower($host), ['github.com', 'www.github.com'], true)) {
                        $fail('Masukkan tautan repository dari github.com.');
                    }
                },
            ],
            'prototype' => [
                Rule::requiredIf($existingSubmission === null),
                'nullable',
                File::types([
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/gif',
                    'video/mp4',
                    'video/quicktime',
                    'video/webm',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/pdf',
                ])->max($maximumFileSize),
            ],
            'another' => ['nullable', 'string', 'max:5000'],
        ], [
            'prototype.required' => 'Upload gambar, video, atau presentasi prototype.',
            'prototype.max' => 'Ukuran prototype melebihi batas untuk jenis file tersebut.',
            'prototype.file' => 'Prototype harus berupa file gambar, video, atau presentasi yang valid.',
            'prototype.uploaded' => 'Batas upload server menolak file ini sebelum diproses. Hubungi panitia jika ukuran file masih di bawah batas yang ditampilkan.',
        ]);

        $prototypePath = $existingSubmission['prototype_path'] ?? null;
        $prototypeMimeType = $existingSubmission['prototype_mime_type'] ?? null;
        $uploadedPrototypePath = null;

        if ($prototypeFile instanceof UploadedFile) {
            $extension = $this->extensionForMimeType((string) $prototypeFile->getMimeType());
            if ($extension === null) {
                return back()->withErrors([
                    'prototype' => 'Format prototype tidak didukung.',
                ])->withInput($request->except('prototype'));
            }

            $prototypePath = 'participants/'.(string) $user['id'].'/'.Str::uuid().'.'.$extension;
            $prototypeMimeType = (string) $prototypeFile->getMimeType();

            try {
                $uploadResponse = $this->supabase->upload($prototypeFile, $prototypePath);
            } catch (ConnectionException $exception) {
                Log::warning('Unable to upload a participant prototype to Supabase Storage.', [
                    'exception' => $exception::class,
                ]);

                return back()->withErrors([
                    'prototype' => 'Prototype belum berhasil diunggah. Silakan coba lagi.',
                ])->withInput($request->except('prototype'));
            }

            if (! $uploadResponse->successful()) {
                Log::warning('Supabase rejected a participant prototype upload.', [
                    'status' => $uploadResponse->status(),
                ]);

                return back()->withErrors([
                    'prototype' => 'Prototype belum berhasil diunggah. Silakan coba lagi.',
                ])->withInput($request->except('prototype'));
            }

            $uploadedPrototypePath = $prototypePath;
        }

        $submission = [
            'participant_id' => (string) $user['id'],
            'submitter_name' => trim($data['submitter_name']),
            'github_link' => trim($data['github_link']),
            'prototype_path' => $prototypePath,
            'prototype_mime_type' => $prototypeMimeType,
            'additional_text' => isset($data['another']) ? trim($data['another']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            DB::table('submissions')->upsert(
                [$submission],
                ['participant_id'],
                [
                    'submitter_name',
                    'github_link',
                    'prototype_path',
                    'prototype_mime_type',
                    'additional_text',
                    'updated_at',
                ],
            );
        } catch (QueryException $exception) {
            Log::error('Unable to save a participant submission to the application database.', [
                'exception' => $exception::class,
            ]);
            $this->deleteObject($uploadedPrototypePath, 'new prototype after a database save failure');

            return back()->withErrors([
                'submission' => 'Submission belum berhasil disimpan. Silakan coba lagi.',
            ])->withInput($request->except('prototype'));
        }

        if (is_string($existingSubmission['prototype_path'] ?? null)
            && $existingSubmission['prototype_path'] !== $prototypePath) {
            $this->deleteObject($existingSubmission['prototype_path'], 'replaced participant prototype');
        }

        return redirect()->route('submission.show')->with('status', 'Submission berhasil disimpan.');
    }

    private function extensionForMimeType(string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/pdf' => 'pdf',
            default => null,
        };
    }

    private function deleteObject(?string $path, string $context): void
    {
        if ($path === null) {
            return;
        }

        try {
            $response = $this->supabase->deleteObject($path);
        } catch (ConnectionException $exception) {
            Log::warning('Unable to clean up a Supabase submission object.', [
                'context' => $context,
                'exception' => $exception::class,
            ]);

            return;
        }

        if (! $response->successful()) {
            Log::warning('Supabase rejected cleanup of a submission object.', [
                'context' => $context,
                'status' => $response->status(),
            ]);
        }
    }
}
