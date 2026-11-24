<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SupabaseLoginTest extends TestCase
{
    private string $usersEndpoint = 'https://csfest-test.supabase.co/rest/v1/users';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.supabase.url' => 'https://csfest-test.supabase.co',
            'services.supabase.key' => 'test-supabase-key',
            'services.supabase.users_table' => 'users',
        ]);
    }

    public function test_landing_page_and_login_form_are_available(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Build a better campus')
            ->assertSee('Innovating Integrated Digital Solutions for Better Campus Life');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk ke dashboard')
            ->assertSee('name="team_name"', false)
            ->assertDontSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_guest_is_redirected_to_login_before_accessing_the_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_sign_in_using_a_user_record_from_supabase_and_view_the_dashboard(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([[
                'id' => 'user-123',
                'name' => 'Tim Peserta',
                'login_name' => 'tim peserta',
                'password_hash' => Hash::make('secret-password'),
                'role' => 'admin',
            ]]),
        ]);

        $response = $this->post(route('login.store'), [
            'team_name' => 'Tim Peserta',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('app_user', [
            'id' => 'user-123',
            'name' => 'Tim Peserta',
            'role' => 'admin',
        ]);

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), $this->usersEndpoint)
            && $request->method() === 'GET'
            && str_contains($request->url(), 'login_name=eq.')
            && $request->hasHeader('apikey', 'test-supabase-key')
            && $request->hasHeader('Authorization', 'Bearer test-supabase-key'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tim Peserta')
            ->assertSee('Innovating Integrated Digital Solutions for Better Campus Life');
    }

    public function test_user_can_sign_in_by_team_name_when_supabase_has_not_been_migrated(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => function (Request $request) {
                if (str_contains($request->url(), 'login_name=')) {
                    return Http::response([
                        'code' => '42703',
                        'message' => 'column users.login_name does not exist',
                    ], 400);
                }

                return Http::response([[
                    'id' => 'team-123',
                    'name' => 'Tim Lama',
                    'password' => Hash::make('secret-password'),
                    'role' => 'participant',
                ]]);
            },
        ]);

        $response = $this->post(route('login.store'), [
            'team_name' => 'Tim Lama',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('app_user', [
                'id' => 'team-123',
                'name' => 'Tim Lama',
                'role' => 'participant',
            ]);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'name=eq.Tim%20Lama')
            && $request->method() === 'GET');
    }

    public function test_invalid_supabase_credentials_return_to_login_with_an_error(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([[
                'id' => 'user-123',
                'name' => 'Tim Peserta',
                'login_name' => 'tim peserta',
                'password_hash' => Hash::make('different-password'),
            ]]),
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'team_name' => 'Tim Peserta',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['team_name' => 'Nama tim atau kata sandi tidak sesuai.']);
        $response->assertSessionMissing('app_user');
    }

    public function test_logout_clears_the_supabase_session(): void
    {
        $response = $this->withSession([
            'app_user' => [
                'id' => 'user-123',
                'name' => 'Tim Peserta',
            ],
        ])->post(route('logout'));

        $response->assertRedirect(route('home'));
        $response->assertSessionMissing('app_user');
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_login_requires_a_team_name_and_password(): void
    {
        Http::preventStrayRequests();

        $response = $this->post(route('login.store'), [
            'team_name' => '',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['team_name', 'password']);
    }

    public function test_login_reports_missing_supabase_configuration(): void
    {
        config([
            'services.supabase.url' => null,
            'services.supabase.key' => null,
        ]);
        Log::spy();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'team_name' => 'Tim Peserta',
            'password' => 'secret-password',
        ]);

        $response->assertSessionHasErrors(['team_name' => 'Database login belum dikonfigurasi. Silakan hubungi panitia.']);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_supabase_login_errors_are_logged_without_exposing_them_to_the_user(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([
                'code' => 'PGRST204',
                'message' => 'Could not find the login_name column in the schema cache.',
            ], 400),
        ]);
        Log::spy();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'team_name' => 'Tim Peserta',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors(['team_name' => 'Login belum berhasil. Silakan coba lagi.']);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_participant_sees_the_latest_assigned_study_case_on_the_dashboard(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => function (Request $request) {
                if (str_contains($request->url(), 'theme')) {
                    return Http::response([[
                        'theme' => [
                            'id' => 7,
                            'title' => 'Aksesibilitas Kampus',
                            'description' => 'Studi kasus layanan kampus.',
                        ],
                    ]]);
                }

                return Http::response([[
                    'id' => 'team-123',
                    'name' => 'Tim Akses',
                    'login_name' => 'tim akses',
                    'password_hash' => Hash::make('secret-password'),
                    'role' => 'participant',
                ]]);
            },
        ]);

        $this->post(route('login.store'), [
            'team_name' => 'Tim Akses',
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aksesibilitas Kampus')
            ->assertSee('Studi kasus layanan kampus.')
            ->assertSee('Akun tim peserta')
            ->assertSee('Ruang kerja tim')
            ->assertSee('Tema ditetapkan')
            ->assertSee('Submission')
            ->assertSee('href="'.route('submission.show').'"', false);
    }
}
