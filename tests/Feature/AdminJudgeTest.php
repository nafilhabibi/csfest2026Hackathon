<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminJudgeTest extends TestCase
{
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

    public function test_admin_sees_a_separate_judge_account_form(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([], 200),
            'csfest-test.supabase.co/rest/v1/themes*' => Http::response([], 200),
        ]);

        $this->withSession($this->adminSession())
            ->get(route('admin.participants.create'))
            ->assertOk()
            ->assertSee('Buat akun juri')
            ->assertSee('name="judge_name"', false)
            ->assertSee('action="'.route('admin.judges.store').'"', false)
            ->assertSee('tanpa akses ke halaman lain');
    }

    public function test_admin_can_create_a_judge_account_with_a_hashed_password(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response([], 200);
                }

                return Http::response([[
                    'id' => 'judge-123',
                    'name' => 'Juri Baru',
                    'login_name' => 'juri baru',
                    'role' => 'judge',
                ]], 201);
            },
        ]);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.judges.store'), [
                'judge_name' => ' Juri Baru ',
                'judge_password' => 'password123',
                'judge_password_confirmation' => 'password123',
                'role' => 'admin',
            ]);

        $response->assertRedirect(route('admin.participants.create'))
            ->assertSessionHas('status', 'Akun juri Juri Baru berhasil dibuat.');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/rest/v1/users')
            && $request['name'] === 'Juri Baru'
            && $request['login_name'] === 'juri baru'
            && $request['role'] === 'judge'
            && Hash::check('password123', $request['password'])
            && ! array_key_exists('email', $request->data())
            && ! array_key_exists('theme_id', $request->data()));
    }

    public function test_judge_account_creation_requires_matching_passwords_and_a_name(): void
    {
        Http::preventStrayRequests();

        $this->from(route('admin.participants.create'))
            ->withSession($this->adminSession())
            ->post(route('admin.judges.store'), [
                'judge_name' => '',
                'judge_password' => 'short',
                'judge_password_confirmation' => 'different',
            ])
            ->assertRedirect(route('admin.participants.create'))
            ->assertSessionHasErrors(['judge_name', 'judge_password']);
    }

    public function test_admin_cannot_create_a_judge_with_a_duplicate_login_name(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([['id' => 'existing-user']], 200),
        ]);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.judges.store'), [
                'judge_name' => 'Juri Lama',
                'judge_password' => 'password123',
                'judge_password_confirmation' => 'password123',
            ]);

        $response->assertSessionHasErrors([
            'judge_name' => 'Nama login sudah digunakan.',
        ]);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_admin_gets_a_migration_error_when_supabase_rejects_the_judge_role(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response([], 200);
                }

                return Http::response([
                    'code' => '23514',
                    'message' => 'new row violates check constraint users_role_check',
                ], 400);
            },
        ]);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.judges.store'), [
                'judge_name' => 'Juri Baru',
                'judge_password' => 'password123',
                'judge_password_confirmation' => 'password123',
            ]);

        $response->assertSessionHasErrors([
            'judge_name' => 'Database belum mengizinkan role juri. Terapkan migrasi Supabase terbaru, lalu coba lagi.',
        ]);
    }

    public function test_judge_cannot_access_dashboard_participant_submission_or_admin_management(): void
    {
        $judgeSession = [
            'app_user' => [
                'id' => 'judge-123',
                'name' => 'Juri',
                'role' => 'judge',
            ],
        ];

        $this->withSession($judgeSession)->get(route('dashboard'))->assertForbidden();
        $this->withSession($judgeSession)->get(route('submission.show'))->assertForbidden();
        $this->withSession($judgeSession)->post(route('submission.store'))->assertForbidden();
        $this->withSession($judgeSession)->get(route('admin.participants.create'))->assertForbidden();
        $this->withSession($judgeSession)->post(route('admin.judges.store'))->assertForbidden();
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
