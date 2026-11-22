<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminParticipantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.supabase.url' => 'https://csfest-test.supabase.co',
            'services.supabase.key' => 'test-supabase-key',
            'services.supabase.users_table' => 'users',
        ]);
    }

    public function test_participant_cannot_open_admin_participant_form(): void
    {
        $response = $this->withSession([
            'app_user' => [
                'id' => 2,
                'name' => 'Peserta',
                'email' => 'peserta@example.com',
                'role' => 'participant',
            ],
        ])->get(route('admin.participants.create'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_participant_account(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response([], 200);
                }

                return Http::response([[
                    'id' => 3,
                    'name' => 'Peserta Baru',
                    'email' => 'baru@example.com',
                    'role' => 'participant',
                ]], 201);
            },
        ]);

        $response = $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
            ],
        ])->post(route('admin.participants.store'), [
            'name' => 'Peserta Baru',
            'email' => 'baru@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.participants.create'));
        $response->assertSessionHas('status', 'Akun peserta berhasil dibuat untuk baru@example.com.');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['name'] === 'Peserta Baru'
            && $request['email'] === 'baru@example.com'
            && $request['role'] === 'participant'
            && is_string($request['password'])
            && str_starts_with($request['password'], '$2'));
    }

    public function test_admin_cannot_create_duplicate_participant_email(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users*' => Http::response([['id' => 2]], 200),
        ]);

        $response = $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
            ],
        ])->post(route('admin.participants.store'), [
            'name' => 'Peserta Lama',
            'email' => 'peserta@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'Email sudah terdaftar sebagai akun peserta.']);
    }

    public function test_participant_creation_validates_required_fields(): void
    {
        $response = $this->withSession([
            'app_user' => [
                'id' => 1,
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
            ],
        ])->post(route('admin.participants.store'), [
            'name' => '',
            'email' => 'bukan-email',
            'password' => '123',
            'password_confirmation' => 'beda',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }
}
