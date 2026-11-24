<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class SupabaseSubmissionClient
{
    public function isConfigured(): bool
    {
        return is_string(config('services.supabase.url'))
            && config('services.supabase.url') !== ''
            && is_string(config('services.supabase.storage_key'))
            && config('services.supabase.storage_key') !== ''
            && is_string(config('services.supabase.submissions_bucket'))
            && config('services.supabase.submissions_bucket') !== '';
    }

    public function upload(UploadedFile $file, string $path): Response
    {
        return $this->request()
            ->withHeaders([
                'x-upsert' => 'false',
                'cache-control' => '3600',
            ])
            ->withBody($file->getContent(), (string) $file->getMimeType())
            ->post($this->objectEndpoint($path));
    }

    public function createSignedUrl(string $path): Response
    {
        return $this->request()
            ->post($this->storageEndpoint('object/sign/'.$this->encodedBucketPath($path)), [
                'expiresIn' => 3600,
            ]);
    }

    public function deleteObject(string $path): Response
    {
        return $this->request()->delete(
            $this->storageEndpoint('object/'.rawurlencode($this->bucket())),
            ['prefixes' => [$path]],
        );
    }

    public function signedUrl(string $signedPath): string
    {
        if (str_starts_with($signedPath, 'https://') || str_starts_with($signedPath, 'http://')) {
            return $signedPath;
        }

        return rtrim((string) config('services.supabase.url'), '/').'/storage/v1/'.ltrim($signedPath, '/');
    }

    private function request(): PendingRequest
    {
        $key = (string) config('services.supabase.storage_key');

        return Http::acceptJson()
            ->withHeaders([
                'apikey' => $key,
                'Authorization' => 'Bearer '.$key,
            ])
            ->connectTimeout(5)
            ->timeout(60);
    }

    private function objectEndpoint(string $path): string
    {
        return $this->storageEndpoint('object/'.$this->encodedBucketPath($path));
    }

    private function encodedBucketPath(string $path): string
    {
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

        return rawurlencode($this->bucket()).'/'.$encodedPath;
    }

    private function bucket(): string
    {
        return (string) config('services.supabase.submissions_bucket');
    }

    private function storageEndpoint(string $path): string
    {
        return rtrim((string) config('services.supabase.url'), '/').'/storage/v1/'.$path;
    }
}
