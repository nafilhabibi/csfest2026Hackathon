<?php

namespace Tests\Feature;

use App\Services\SubmissionAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    private string $participantId = '123e4567-e89b-12d3-a456-426614174000';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.supabase.url' => 'https://csfest-test.supabase.co',
            'services.supabase.key' => 'test-supabase-key',
            'services.supabase.storage_key' => 'test-service-role-key',
            'services.supabase.submissions_bucket' => 'Submission',
        ]);
    }

    public function test_participant_can_open_submission_form_without_an_existing_submission(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->assignedThemeFake());

        $this->withSession($this->participantSession())
            ->get(route('submission.show'))
            ->assertOk()
            ->assertSee('Kirim submission')
            ->assertSee('GitHub Link')
            ->assertSee('Media Upload')
            ->assertSee('Pilih satu file.')
            ->assertSee('Belum ada file dipilih.')
            ->assertSee('data-prototype-preview', false)
            ->assertSee('Another')
            ->assertSee('5 MB')
            ->assertSee('30 MB')
            ->assertSee('PPT, PPTX');
    }

    public function test_closed_submission_form_is_read_only_for_participant(): void
    {
        app(SubmissionAvailability::class)->setClosed(true);
        Http::preventStrayRequests();
        Http::fake($this->assignedThemeFake());

        $this->withSession($this->participantSession())
            ->get(route('submission.show'))
            ->assertOk()
            ->assertSee('Submission telah ditutup oleh panitia.')
            ->assertSee('<fieldset class="submission-form-fields" disabled>', false)
            ->assertSee('Satu submission per tim');
    }

    public function test_closed_submission_rejects_new_submissions(): void
    {
        app(SubmissionAvailability::class)->setClosed(true);
        Http::preventStrayRequests();
        Http::fake($this->assignedThemeFake());

        $this->withSession($this->participantSession())
            ->post(route('submission.store'), [
                'submitter_name' => 'Alya',
                'github_link' => 'https://github.com/csfest/team-app',
                'prototype' => UploadedFile::fake()->create('prototype.png', 450)->mimeType('image/png'),
            ])
            ->assertRedirect(route('submission.show'))
            ->assertSessionHasErrors([
                'submission' => 'Submission sudah ditutup. Pengiriman dan pembaruan tidak dapat dilakukan.',
            ]);

        $this->assertDatabaseMissing('submissions', [
            'participant_id' => $this->participantId,
        ]);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/storage/v1/object/'));
    }

    public function test_closed_submission_rejects_updates_to_existing_submissions(): void
    {
        DB::table('submissions')->insert([
            'participant_id' => $this->participantId,
            'submitter_name' => 'Alya',
            'github_link' => 'https://github.com/csfest/old-app',
            'prototype_path' => 'participants/'.$this->participantId.'/existing.png',
            'prototype_mime_type' => 'image/png',
            'additional_text' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(SubmissionAvailability::class)->setClosed(true);
        Http::preventStrayRequests();
        Http::fake($this->assignedThemeFake());

        $this->withSession($this->participantSession())
            ->post(route('submission.store'), [
                'submitter_name' => 'Alya Baru',
                'github_link' => 'https://github.com/csfest/new-app',
            ])
            ->assertRedirect(route('submission.show'))
            ->assertSessionHasErrors([
                'submission' => 'Submission sudah ditutup. Pengiriman dan pembaruan tidak dapat dilakukan.',
            ]);

        $this->assertDatabaseHas('submissions', [
            'participant_id' => $this->participantId,
            'submitter_name' => 'Alya',
            'github_link' => 'https://github.com/csfest/old-app',
        ]);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/storage/v1/object/'));
    }

    public function test_admin_cannot_open_or_create_a_participant_submission(): void
    {
        $session = [
            'app_user' => [
                'id' => $this->participantId,
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ];

        $this->withSession($session)->get(route('submission.show'))->assertForbidden();
        $this->withSession($session)->post(route('submission.store'))->assertForbidden();
    }

    public function test_judge_cannot_open_or_create_a_participant_submission(): void
    {
        $session = [
            'app_user' => [
                'id' => $this->participantId,
                'name' => 'Juri',
                'role' => 'judge',
            ],
        ];

        $this->withSession($session)->get(route('submission.show'))->assertForbidden();
        $this->withSession($session)->post(route('submission.store'))->assertForbidden();
    }

    public function test_participant_can_upload_a_prototype_and_save_submission_details(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->assignedThemeFake(),
            'csfest-test.supabase.co/storage/v1/object/Submission/participants/*' => Http::response([
                'Key' => 'Submission/participants/team/prototype.png',
            ]),
        ]);

        $response = $this->withSession($this->participantSession())
            ->post(route('submission.store'), [
                'submitter_name' => 'Alya',
                'github_link' => 'https://github.com/csfest/team-app',
                'prototype' => UploadedFile::fake()->create('prototype.png', 450)->mimeType('image/png'),
                'another' => 'Prototype untuk alur peminjaman ruang.',
            ]);

        $response->assertRedirect(route('submission.show'))
            ->assertSessionHas('status', 'Submission berhasil disimpan.');

        $this->assertDatabaseHas('submissions', [
            'participant_id' => $this->participantId,
            'submitter_name' => 'Alya',
            'github_link' => 'https://github.com/csfest/team-app',
            'prototype_mime_type' => 'image/png',
            'additional_text' => 'Prototype untuk alur peminjaman ruang.',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/storage/v1/object/Submission/participants/'.$this->participantId.'/')
            && $request->hasHeader('apikey', 'test-service-role-key')
            && $request->hasHeader('Content-Type', 'image/png'));
    }

    public function test_participant_can_upload_a_ppt_presentation(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->assignedThemeFake(),
            'csfest-test.supabase.co/storage/v1/object/Submission/participants/*' => Http::response([
                'Key' => 'Submission/participants/team/presentation.ppt',
            ]),
        ]);

        $response = $this->withSession($this->participantSession())
            ->post(route('submission.store'), [
                'submitter_name' => 'Alya',
                'github_link' => 'https://github.com/csfest/team-app',
                'prototype' => UploadedFile::fake()
                    ->create('presentation.ppt', 450)
                    ->mimeType('application/vnd.ms-powerpoint'),
            ]);

        $response->assertRedirect(route('submission.show'))
            ->assertSessionHas('status', 'Submission berhasil disimpan.');

        $submission = DB::table('submissions')->where('participant_id', $this->participantId)->first();
        $this->assertNotNull($submission);
        $this->assertSame('application/vnd.ms-powerpoint', $submission->prototype_mime_type);
        $this->assertStringEndsWith('.ppt', $submission->prototype_path);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '.ppt')
            && $request->hasHeader('Content-Type', 'application/vnd.ms-powerpoint'));
    }

    public function test_participant_can_upload_a_pptx_presentation(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->assignedThemeFake(),
            'csfest-test.supabase.co/storage/v1/object/Submission/participants/*' => Http::response([
                'Key' => 'Submission/participants/team/presentation.pptx',
            ]),
        ]);

        $response = $this->withSession($this->participantSession())
            ->post(route('submission.store'), [
                'submitter_name' => 'Alya',
                'github_link' => 'https://github.com/csfest/team-app',
                'prototype' => UploadedFile::fake()
                    ->create('presentation.pptx', 450)
                    ->mimeType('application/vnd.openxmlformats-officedocument.presentationml.presentation'),
            ]);

        $response->assertRedirect(route('submission.show'))
            ->assertSessionHas('status', 'Submission berhasil disimpan.');

        $submission = DB::table('submissions')->where('participant_id', $this->participantId)->first();
        $this->assertNotNull($submission);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            $submission->prototype_mime_type,
        );
        $this->assertStringEndsWith('.pptx', $submission->prototype_path);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '.pptx')
            && $request->hasHeader(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ));
    }

    public function test_participant_can_update_submission_details_without_replacing_the_prototype(): void
    {
        DB::table('submissions')->insert([
            'participant_id' => $this->participantId,
            'submitter_name' => 'Alya',
            'github_link' => 'https://github.com/csfest/old-app',
            'prototype_path' => 'participants/'.$this->participantId.'/existing.png',
            'prototype_mime_type' => 'image/png',
            'additional_text' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::preventStrayRequests();
        Http::fake([
            ...$this->assignedThemeFake(),
            'csfest-test.supabase.co/storage/v1/object/Submission' => Http::response([], 200),
        ]);

        $response = $this->withSession($this->participantSession())
            ->post(route('submission.store'), [
                'submitter_name' => 'Alya Putri',
                'github_link' => 'https://github.com/csfest/team-app',
            ]);

        $response->assertRedirect(route('submission.show'))
            ->assertSessionHas('status', 'Submission berhasil disimpan.');

        $this->assertDatabaseHas('submissions', [
            'participant_id' => $this->participantId,
            'submitter_name' => 'Alya Putri',
            'prototype_path' => 'participants/'.$this->participantId.'/existing.png',
        ]);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/storage/v1/object/Submission/participants/'));
    }

    public function test_submission_page_uses_a_temporary_signed_url_for_the_private_prototype(): void
    {
        $prototypePath = 'participants/'.$this->participantId.'/prototype.png';
        DB::table('submissions')->insert([
            'participant_id' => $this->participantId,
            'submitter_name' => 'Alya',
            'github_link' => 'https://github.com/csfest/team-app',
            'prototype_path' => $prototypePath,
            'prototype_mime_type' => 'image/png',
            'additional_text' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::preventStrayRequests();
        Http::fake([
            ...$this->assignedThemeFake(),
            'csfest-test.supabase.co/storage/v1/object/sign/Submission/*' => Http::response([
                'signedURL' => '/object/sign/Submission/'.$prototypePath.'?token=temporary-token',
            ]),
        ]);

        $this->withSession($this->participantSession())
            ->get(route('submission.show'))
            ->assertOk()
            ->assertSee('Perbarui submission')
            ->assertSee('Lihat prototype saat ini')
            ->assertSee('https://csfest-test.supabase.co/storage/v1/object/sign/Submission/'.$prototypePath.'?token=temporary-token', false);
    }

    public function test_participant_can_view_their_team_assessments_in_a_table(): void
    {
        $privateKeyResource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $this->assertNotFalse($privateKeyResource);
        $this->assertTrue(openssl_pkey_export($privateKeyResource, $privateKey));
        config([
            'services.google_sheets.spreadsheet_id' => 'test-spreadsheet',
            'services.google_sheets.worksheet' => 'Penilaian',
            'services.google_sheets.service_account_json_base64' => base64_encode(json_encode([
                'client_email' => 'judge-sheets@example.iam.gserviceaccount.com',
                'private_key' => $privateKey,
            ], JSON_THROW_ON_ERROR)),
        ]);
        Http::preventStrayRequests();
        Http::fake([
            ...$this->assignedThemeFake(),
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
            'sheets.googleapis.com/*' => function (Request $request) {
                if (str_contains($request->url(), '/values/')) {
                    return Http::response([
                        'values' => [
                            [
                                'Nama Juri',
                                'Nama Tim',
                                'Kesesuaian Solusi dengan Studi Kasus',
                                'Inovasi dan Kreativitas Ide',
                                'Kelayakan Implementasi',
                                'Kualitas Rancangan Solusi',
                                'Kualitas Prototype Awal',
                                'Total Nilai',
                                'Komentar',
                                'ID Juri Internal',
                                'ID Tim Internal',
                            ],
                            [
                                'Juri Satu', 'Tim Peserta', 5, 4, 3, 4, 5, 21, 'Solusi kuat.',
                                'judge-1', $this->participantId,
                            ],
                            [
                                'Juri Rahasia', 'Tim Lain', 1, 1, 1, 1, 1, 5, 'Komentar tim lain.',
                                'judge-2', 'other-team',
                            ],
                        ],
                    ]);
                }

                return Http::response([
                    'sheets' => [[
                        'properties' => ['sheetId' => 17, 'title' => 'Penilaian'],
                        'data' => [[
                            'startColumn' => 9,
                            'columnMetadata' => [
                                ['hiddenByUser' => true],
                                ['hiddenByUser' => true],
                            ],
                        ]],
                    ]],
                ]);
            },
        ]);

        $response = $this->withSession($this->participantSession())
            ->get(route('submission.assessment.show'));

        $response->assertOk()
            ->assertSee('<table class="participant-assessment-table">', false)
            ->assertSee('Juri Satu')
            ->assertSee('Solusi kuat.')
            ->assertSee('21 / 25')
            ->assertDontSee('Juri Rahasia')
            ->assertDontSee('Komentar tim lain.');
    }

    public function test_submission_requires_a_github_repository_and_a_prototype_on_first_submit(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->assignedThemeFake());

        $response = $this->withSession($this->participantSession())
            ->from(route('submission.show'))
            ->post(route('submission.store'), [
                'submitter_name' => 'Alya',
                'github_link' => 'https://example.com/team-app',
            ]);

        $response->assertRedirect(route('submission.show'))
            ->assertSessionHasErrors([
                'github_link' => 'Masukkan tautan repository dari github.com.',
                'prototype' => 'Upload gambar, video, atau presentasi prototype.',
            ]);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/storage/v1/object/'));
    }

    public function test_github_link_array_input_is_rejected_without_causing_an_error_response(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->assignedThemeFake());

        $response = $this->withSession($this->participantSession())
            ->from(route('submission.show'))
            ->post(route('submission.store'), [
                'submitter_name' => 'Alya',
                'github_link' => ['https://github.com/csfest/team-app'],
                'prototype' => UploadedFile::fake()->create('prototype.png', 100)->mimeType('image/png'),
            ]);

        $response->assertRedirect(route('submission.show'))
            ->assertSessionHasErrors('github_link');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/storage/v1/object/'));
    }

    public function test_image_prototype_cannot_exceed_five_megabytes(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->assignedThemeFake());

        $response = $this->withSession($this->participantSession())
            ->from(route('submission.show'))
            ->post(route('submission.store'), [
                'submitter_name' => 'Alya',
                'github_link' => 'https://github.com/csfest/team-app',
                'prototype' => UploadedFile::fake()->create('prototype.png', 5121, 'image/png'),
            ]);

        $response->assertRedirect(route('submission.show'))
            ->assertSessionHasErrors('prototype');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/storage/v1/object/'));
    }

    public function test_video_prototype_cannot_exceed_thirty_megabytes(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->assignedThemeFake());

        $response = $this->withSession($this->participantSession())
            ->from(route('submission.show'))
            ->post(route('submission.store'), [
                'submitter_name' => 'Alya',
                'github_link' => 'https://github.com/csfest/team-app',
                'prototype' => UploadedFile::fake()->create('prototype.mp4', 30721, 'video/mp4'),
            ]);

        $response->assertRedirect(route('submission.show'))
            ->assertSessionHasErrors('prototype');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/storage/v1/object/'));
    }

    public function test_participant_without_a_theme_cannot_open_or_submit_a_submission(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([
                ['theme_id' => null],
            ]),
        ]);

        $session = $this->participantSession();
        $this->withSession($session)
            ->get(route('submission.show'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('submission_access_error', 'Submission hanya dapat dibuka setelah panitia menetapkan tema tim.');

        $this->withSession($session)
            ->post(route('submission.store'), [
                'submitter_name' => 'Tim Peserta',
                'github_link' => 'https://github.com/csfest/team-app',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('submission_access_error', 'Submission hanya dapat dibuka setelah panitia menetapkan tema tim.');

        $this->withSession($session)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('submission.show'), false);

        $this->assertDatabaseMissing('submissions', [
            'participant_id' => $this->participantId,
        ]);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/storage/v1/object/'));
    }

    /**
     * @return array<string, mixed>
     */
    private function assignedThemeFake(): array
    {
        return [
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([
                ['theme_id' => 5],
            ]),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function participantSession(): array
    {
        return [
            'app_user' => [
                'id' => $this->participantId,
                'name' => 'Tim Peserta',
                'role' => 'participant',
            ],
        ];
    }
}
