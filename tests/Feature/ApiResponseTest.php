<?php

namespace Tests\Feature;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_health_endpoint_returns_success_envelope(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_unknown_api_route_returns_404_envelope(): void
    {
        $response = $this->getJson('/api/route-that-does-not-exist');

        $response->assertNotFound()->assertJson([
            'success' => false,
            'message' => 'Resource not found',
        ]);
    }

    public function test_unknown_web_route_renders_404_page(): void
    {
        $response = $this->get('/page-that-does-not-exist');

        $response->assertNotFound()->assertSee('Page not found');
    }
}