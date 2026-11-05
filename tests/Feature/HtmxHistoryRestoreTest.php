<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HtmxHistoryRestoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function browser_history_restores_the_app_shell_from_the_server(): void
    {
        $this->loginAsAdmin();

        $response = $this->get('/');

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<body\b[^>]*\bhx-boost="true"[^>]*\bhx-history="false"/',
            $response->getContent(),
        );
    }
}
