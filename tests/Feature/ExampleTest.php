<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_secure_forwarded_requests_generate_https_asset_and_navigation_urls(): void
    {
        $response = $this->get('/', [
            'X-Forwarded-Proto' => 'https',
        ]);

        $response->assertOk()
            ->assertSee('rel="stylesheet" href="https://', false)
            ->assertSee('type="module" src="https://', false)
            ->assertSee('href="https://', false)
            ->assertDontSee('http://', false);
    }
}
