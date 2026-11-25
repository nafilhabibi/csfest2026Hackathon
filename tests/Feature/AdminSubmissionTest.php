<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.supabase.url' => 'https://csfest-test.supabase.co',
            'services.supabase.key' => 'test-supabase-key',
            'services.supabase.storage_key' => 'test-service-role-key',
            'services.supabase.users_table' => 'users',
            'services.supabase.submissions_bucket' => 'Submission',
        ]);
    }

    public function test_admin_sees_submission_status_for_every_team_and_only_submitted_teams_are_links(): void
    {
        $this->createSubmission('team-submitted');
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([
                ['id' => 'team-pending', 'name' => 'Tim Belum Kirim'],
                ['id' => 'team-submitted', 'name' => 'Tim Sudah Kirim'],
            ]),
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.submissions.index'));

        $response->assertOk()
            ->assertSee('Tim Belum Kirim')
            ->assertSee('Belum submission')
            ->assertSee('Tim Sudah Kirim')
            ->assertSee('Sudah submission')
            ->assertSee('href="'.route('admin.submissions.show', 'team-submitted').'"', false)
            ->assertDontSee('href="'.route('admin.submissions.show', 'team-pending').'"', false);
    }

    public function test_admin_can_view_submission_details_and_private_prototype(): void
    {
        $this->createSubmission('team-123', [
            'submitter_name' => 'Alya',
            'github_link' => 'https://github.com/csfest/team-app',
            'prototype_path' => 'participants/team-123/prototype.png',
            'prototype_mime_type' => 'image/png',
            'additional_text' => '<script>alert("prototype")</script>',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([[
                'id' => 'team-123',
                'name' => 'Tim Merah',
                'theme' => ['title' => 'Kampus Terhubung'],
            ]]),
            'csfest-test.supabase.co/storage/v1/object/sign/Submission/*' => Http::response([
                'signedURL' => 'https://csfest-test.supabase.co/storage/v1/object/sign/Submission/prototype.png?token=private',
            ]),
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.submissions.show', 'team-123'));

        $response->assertOk()
            ->assertSee('Tim Merah')
            ->assertSee('Kampus Terhubung')
            ->assertSee('Alya')
            ->assertSee('https://github.com/csfest/team-app')
            ->assertSee('&lt;script&gt;alert(&quot;prototype&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("prototype")</script>', false)
            ->assertSee('https://csfest-test.supabase.co/storage/v1/object/sign/Submission/prototype.png?token=private', false);
    }

    public function test_judge_can_review_submissions_without_admin_navigation(): void
    {
        $this->createSubmission('team-123');
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([[
                'id' => 'team-123',
                'name' => 'Tim Merah',
                'theme' => ['title' => 'Kampus Terhubung'],
            ]]),
            'csfest-test.supabase.co/storage/v1/object/sign/Submission/*' => Http::response([
                'signedURL' => 'https://csfest-test.supabase.co/storage/v1/object/sign/Submission/prototype.png?token=private',
            ]),
        ]);
        $judgeSession = [
            'app_user' => [
                'id' => 'judge-123',
                'name' => 'Juri Satu',
                'role' => 'judge',
            ],
        ];

        $this->withSession($judgeSession)
            ->get(route('admin.submissions.index'))
            ->assertOk()
            ->assertSee('Tim Merah')
            ->assertSee('Juri Satu')
            ->assertSee('href="'.route('admin.submissions.show', 'team-123').'"', false)
            ->assertDontSee('Dashboard')
            ->assertDontSee('Pusat pengelolaan');

        $this->withSession($judgeSession)
            ->get(route('admin.submissions.show', 'team-123'))
            ->assertOk()
            ->assertSee('Kampus Terhubung')
            ->assertSee('Juri Satu')
            ->assertDontSee('Dashboard')
            ->assertDontSee('Pusat pengelolaan');
    }

    public function test_judge_can_play_a_video_prototype_and_open_it_in_a_new_tab(): void
    {
        $this->createSubmission('team-video', [
            'prototype_path' => 'participants/team-video/prototype.mp4',
            'prototype_mime_type' => 'video/mp4',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([[
                'id' => 'team-video',
                'name' => 'Tim Video',
                'theme' => ['title' => 'Kampus Terhubung'],
            ]]),
            'csfest-test.supabase.co/storage/v1/object/sign/Submission/*' => Http::response([
                'signedURL' => 'https://csfest-test.supabase.co/storage/v1/object/sign/Submission/prototype.mp4?token=private',
            ]),
        ]);

        $response = $this->withSession([
            'app_user' => [
                'id' => 'judge-123',
                'name' => 'Juri Satu',
                'role' => 'judge',
            ],
        ])->get(route('admin.submissions.show', 'team-video'));

        $response->assertOk()
            ->assertSee(
                '<video class="admin-submission-video" controls playsinline preload="metadata" src="https://csfest-test.supabase.co/storage/v1/object/sign/Submission/prototype.mp4?token=private">',
                false,
            )
            ->assertSee('Buka video di tab baru')
            ->assertSee('https://csfest-test.supabase.co/storage/v1/object/sign/Submission/prototype.mp4?token=private', false);
    }

    public function test_participant_cannot_access_admin_submission_pages(): void
    {
        $response = $this->withSession([
            'app_user' => [
                'id' => 'team-123',
                'name' => 'Tim Peserta',
                'role' => 'participant',
            ],
        ])->get(route('admin.submissions.index'));

        $response->assertForbidden();
    }

    public function test_admin_cannot_open_a_team_submission_that_does_not_exist(): void
    {
        Http::preventStrayRequests();

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.submissions.show', 'team-without-submission'));

        $response->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSubmission(string $participantId, array $attributes = []): void
    {
        DB::table('submissions')->insert([
            'participant_id' => $participantId,
            'submitter_name' => 'Nama Pengirim',
            'github_link' => 'https://github.com/csfest/example',
            'prototype_path' => 'participants/'.$participantId.'/prototype.png',
            'prototype_mime_type' => 'image/png',
            'additional_text' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function adminSession(): array
    {
        return [
            'app_user' => [
                'id' => 'admin-123',
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ];
    }
}
