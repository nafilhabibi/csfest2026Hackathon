<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminParticipantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.supabase.url' => 'https://csfest-test.supabase.co',
            'services.supabase.key' => 'test-supabase-key',
            'services.supabase.users_table' => 'users',
            'services.supabase.themes_table' => 'themes',
        ]);
    }

    public function test_participant_cannot_open_admin_participant_form(): void
    {
        $response = $this->withSession([
            'app_user' => [
                'id' => 2,
                'name' => 'Tim Peserta',
                'role' => 'participant',
            ],
        ])->get(route('admin.participants.create'));

        $response->assertForbidden();
    }

    public function test_admin_can_load_team_and_study_case_management(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([[
                'id' => 'team-123',
                'name' => 'Tim Merah',
                'theme_id' => 5,
                'theme' => ['id' => 5, 'title' => 'Kualitas Air'],
            ]]),
            'csfest-test.supabase.co/rest/v1/themes*' => Http::response([[
                'id' => 5,
                'title' => 'Kualitas Air',
                'description' => 'Akses air bersih di kampus.',
            ]]),
        ]);

        $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ])->get(route('admin.participants.create'))
            ->assertOk()
            ->assertSee('Tim Merah')
            ->assertSee('Kualitas Air')
            ->assertSee('Nama tim menjadi identitas login')
            ->assertSee('Pusat pengelolaan')
            ->assertSee('href="#team-account"', false)
            ->assertSee('href="#study-cases"', false)
            ->assertSee('href="#submission-window"', false)
            ->assertSee('Status pengiriman submission')
            ->assertSee('href="#team-assignments"', false)
            ->assertSee('Belum mendapat tema');
    }

    public function test_admin_can_close_and_reopen_submissions(): void
    {
        $adminSession = [
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ];

        $this->withSession($adminSession)
            ->patch(route('admin.submissions.settings.update'), ['closed' => '1'])
            ->assertRedirect(route('admin.participants.create'))
            ->assertSessionHas(
                'status',
                'Submission berhasil ditutup. Peserta tidak dapat mengirim atau memperbarui submission.',
            );

        $this->assertDatabaseHas('app_settings', [
            'setting_key' => 'submissions_closed',
            'setting_value' => '1',
        ]);

        $this->withSession($adminSession)
            ->patch(route('admin.submissions.settings.update'), ['closed' => '0'])
            ->assertRedirect(route('admin.participants.create'))
            ->assertSessionHas('status', 'Submission dibuka kembali.');

        $this->assertDatabaseHas('app_settings', [
            'setting_key' => 'submissions_closed',
            'setting_value' => '0',
        ]);
    }

    public function test_non_admin_cannot_change_submission_availability(): void
    {
        $this->withSession([
            'app_user' => [
                'id' => 'judge-1',
                'name' => 'Juri',
                'role' => 'judge',
            ],
        ])->patch(route('admin.submissions.settings.update'), ['closed' => '1'])
            ->assertForbidden();

        $this->assertDatabaseHas('app_settings', [
            'setting_key' => 'submissions_closed',
            'setting_value' => '0',
        ]);
    }

    public function test_admin_management_does_not_show_empty_counts_when_data_cannot_load(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([], 500),
            'csfest-test.supabase.co/rest/v1/themes*' => Http::response([], 500),
        ]);

        $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ])->get(route('admin.participants.create'))
            ->assertOk()
            ->assertSee('Data tidak tersedia')
            ->assertSee('Data studi kasus belum dapat ditampilkan.')
            ->assertSee('Data tim belum dapat ditampilkan.')
            ->assertSee('Belum mendapat tema');
    }

    public function test_admin_can_create_a_team_account_without_email(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response([], 200);
                }

                return Http::response([[
                    'id' => 'team-123',
                    'name' => 'Tim Baru',
                    'login_name' => 'tim baru',
                    'role' => 'participant',
                ]], 201);
            },
        ]);

        $response = $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ])->post(route('admin.participants.store'), [
            'name' => 'Tim Baru',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.participants.create'));
        $response->assertSessionHas('status', 'Akun tim Tim Baru berhasil dibuat.');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['name'] === 'Tim Baru'
            && $request['login_name'] === 'tim baru'
            && $request['role'] === 'participant'
            && $request['theme_id'] === null
            && ! array_key_exists('email', $request->data())
            && is_string($request['password'])
            && str_starts_with($request['password'], '$2'));
    }

    public function test_admin_cannot_create_duplicate_team_name(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([['id' => 'existing-team']], 200),
        ]);

        $response = $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ])->post(route('admin.participants.store'), [
            'name' => 'Tim Lama',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['name' => 'Nama tim sudah digunakan.']);
    }

    public function test_admin_can_assign_a_study_case_when_creating_a_team(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response([], 200);
                }

                return Http::response([[
                    'id' => 'team-456',
                    'name' => 'Tim Kampus',
                    'theme_id' => 7,
                ]], 201);
            },
            'csfest-test.supabase.co/rest/v1/themes*' => Http::response([['id' => 7]], 200),
        ]);

        $response = $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ])->post(route('admin.participants.store'), [
            'name' => 'Tim Kampus',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'theme_id' => 7,
        ]);

        $response->assertRedirect(route('admin.participants.create'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/rest/v1/users')
            && $request['name'] === 'Tim Kampus'
            && $request['theme_id'] === 7);
    }

    public function test_participant_creation_validates_required_fields(): void
    {
        $response = $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ])->post(route('admin.participants.store'), [
            'name' => '',
            'password' => '123',
            'password_confirmation' => 'beda',
        ]);

        $response->assertSessionHasErrors(['name', 'password']);
    }

    public function test_admin_can_add_a_study_case(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/themes*' => Http::response([[
                'id' => 7,
                'title' => 'Aksesibilitas Kampus',
                'description' => 'Meningkatkan akses layanan kampus.',
            ]], 201),
        ]);

        $response = $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ])->post(route('admin.themes.store'), [
            'title' => 'Aksesibilitas Kampus',
            'description' => 'Meningkatkan akses layanan kampus.',
        ]);

        $response->assertRedirect(route('admin.participants.create'));
        $response->assertSessionHas('status', 'Studi kasus Aksesibilitas Kampus berhasil ditambahkan.');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/rest/v1/themes')
            && $request['title'] === 'Aksesibilitas Kampus'
            && $request['description'] === 'Meningkatkan akses layanan kampus.');
    }

    public function test_admin_can_edit_a_study_case(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/themes*' => Http::response([[
                'id' => 7,
                'title' => 'Kampus Inklusif',
                'description' => 'Memperluas akses layanan kampus.',
            ]], 200),
        ]);

        $response = $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ])->patch(route('admin.themes.update', 7), [
            'title' => 'Kampus Inklusif',
            'description' => 'Memperluas akses layanan kampus.',
        ]);

        $response->assertRedirect(route('admin.participants.create'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_contains($request->url(), '/rest/v1/themes?id=eq.7')
            && $request['title'] === 'Kampus Inklusif'
            && $request['description'] === 'Memperluas akses layanan kampus.');
    }

    public function test_admin_can_assign_a_study_case_to_a_team(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/themes*' => Http::response([['id' => 7]], 200),
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([[
                'id' => 'team-123',
                'name' => 'Tim Akses',
                'theme_id' => 7,
            ]], 200),
        ]);

        $response = $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'role' => 'admin',
            ],
        ])->patch(route('admin.participants.theme.update', 'team-123'), [
            'theme_id' => 7,
        ]);

        $response->assertRedirect(route('admin.participants.create'));
        $response->assertSessionHas('status', 'Studi kasus berhasil diperbarui untuk tim Tim Akses.');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_contains($request->url(), '/rest/v1/users?')
            && str_contains($request->url(), 'id=eq.team-123')
            && str_contains($request->url(), 'role=eq.participant')
            && $request['theme_id'] === 7);
    }

    public function test_participant_cannot_add_a_study_case(): void
    {
        Http::preventStrayRequests();

        $this->withSession([
            'app_user' => [
                'id' => 'team-123',
                'name' => 'Tim Peserta',
                'role' => 'participant',
            ],
        ])->post(route('admin.themes.store'), [
            'title' => 'Studi Kasus Terlarang',
        ])->assertForbidden();
    }
}
