<?php

namespace Tests\Feature;

use Database\Seeders\SupabaseUserSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupabaseUserSeederTest extends TestCase
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

    public function test_seeder_sends_demo_account_to_supabase_users_table(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'csfest-test.supabase.co/rest/v1/users' => Http::response([
                'id' => 'user-123',
                'email' => 'peserta@example.com',
            ], 201),
        ]);

        $this->seed(SupabaseUserSeeder::class);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://csfest-test.supabase.co/rest/v1/users'
            && $request->method() === 'POST'
            && $request->hasHeader('apikey', 'test-supabase-key')
            && $request->hasHeader('Authorization', 'Bearer test-supabase-key')
            && $request->hasHeader('Prefer', 'resolution=merge-duplicates')
            && $request['email'] === 'peserta@example.com'
            && $request['name'] === 'Ayu Peserta'
            && is_string($request['password'])
            && str_starts_with($request['password'], '$2'));
    }
}
