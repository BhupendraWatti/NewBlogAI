<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_unknown_web_route_has_a_recovery_link(): void
    {
        $this->get('/definitely-not-a-route')
            ->assertNotFound()
            ->assertSee('This page could not be found')
            ->assertSee('Go to login')
            ->assertSee(route('login'));
    }
}
