<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class SupabaseRestClient
{
    public function isConfigured(): bool
    {
        $url = config('services.supabase.url');
        $key = config('services.supabase.key');
        $usersTable = config('services.supabase.users_table', 'users');

        return is_string($url)
            && $url !== ''
            && is_string($key)
            && $key !== ''
            && is_string($usersTable)
            && $usersTable !== '';
    }

    public function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders([
                'apikey' => (string) config('services.supabase.key'),
                'Authorization' => 'Bearer '.(string) config('services.supabase.key'),
            ])
            ->connectTimeout(3)
            ->timeout(10);
    }

    public function endpoint(string $table): string
    {
        return rtrim((string) config('services.supabase.url'), '/').'/rest/v1/'.rawurlencode($table);
    }

    public function usersTable(): string
    {
        return (string) config('services.supabase.users_table', 'users');
    }

    public function themesTable(): string
    {
        return 'themes';
    }
}
