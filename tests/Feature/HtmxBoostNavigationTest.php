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

    #[Test]
    public function layout_menyediakan_on_page_ready_di_head(): void
    {
        // Script <body> memakai window.onPageReady() sebagai pengganti
        // DOMContentLoaded yang gak nyala lagi setelah navigasi boost
        // (ADHOC-297). Wajib di <head>: <head> tidak ikut ditukar boost.
        $this->loginAsAdmin();

        $html = $this->get('/')->assertOk()->getContent();
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        $this->assertStringContainsString('window.onPageReady = function', $head);
        $this->assertStringContainsString('queueMicrotask(fn)', $head);
    }
}
