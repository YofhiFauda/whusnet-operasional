<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HtmxBoostNavigationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function body_tag_has_htmx_boost_enabled(): void
    {
        $this->loginAsAdmin();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('hx-boost="true"', false);
    }
}
