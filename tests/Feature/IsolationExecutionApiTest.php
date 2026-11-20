<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Endpoint eksekusi isolir dari Web A
 * (docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md).
 */
class IsolationExecutionApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['webhooks.web_a_write_token' => 'token-benar']);
    }

    #[Test]
    public function token_salah_ditolak_401(): void
    {
        $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => 'ISO-TEST'], [
            'Authorization' => 'Bearer token-salah',
        ])->assertStatus(401);
    }

    #[Test]
    public function tanpa_token_ditolak_401(): void
    {
        $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => 'ISO-TEST'])
            ->assertStatus(401);
    }
}
