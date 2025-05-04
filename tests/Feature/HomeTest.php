<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HomeTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_home_endpoint_returns_success(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
