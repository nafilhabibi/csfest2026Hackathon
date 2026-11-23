<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseUserSeeder extends Seeder
{
    public function run(): void
    {
        $url = config('services.supabase.url');
        $key = config('services.supabase.key');
        $usersTable = config('services.supabase.users_table', 'users');

        if (! is_string($url) || $url === '' || ! is_string($key) || $key === '' || ! is_string($usersTable) || $usersTable === '') {
            throw new RuntimeException('Konfigurasi Supabase belum lengkap.');
        }

        $response = Http::acceptJson()
            ->withHeaders([
                'apikey' => $key,
                'Authorization' => 'Bearer '.$key,
                'Prefer' => 'resolution=merge-duplicates',
            ])
            ->connectTimeout(3)
            ->timeout(10)
            ->post(rtrim($url, '/').'/rest/v1/'.rawurlencode($usersTable).'?on_conflict=login_name', [
                'name' => 'Panitia CSFEST2026',
                'login_name' => 'panitia csfest2026',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Gagal menambahkan akun demo ke Supabase: '.$response->status().' '.$response->body()
            );
        }

        $this->command?->info('Akun demo Supabase berhasil disiapkan.');
        $this->command?->line('Nama tim: Panitia CSFEST2026');
        $this->command?->line('Kata sandi: password123');
    }
}
