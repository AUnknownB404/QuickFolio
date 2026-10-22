<?php

namespace Tests\Feature;

use Tests\TestCase;

class RateLimitTest extends TestCase
{
    public function test_web_routes_are_limited_to_sixty_requests_per_minute(): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->get('/')->assertOk();
        }

        $this->get('/')->assertTooManyRequests();
    }
}
