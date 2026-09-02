<?php

namespace Tests\Feature\Api;

use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * ≥5 gagal 422 beruntun dalam 10 menit → alert `TelegramBotService`, khusus
 * endpoint network-device — namespace cache counter-nya TERPISAH dari
 * network-assignment (keputusan.md §19), jadi rentetan gagal di satu
 * endpoint gak ikut memicu/reset counter endpoint yang lain.
 */
class NetworkDeviceAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webhooks.network_assignment_write_token' => 'write-token-456']);
    }

    public function test_lima_gagal_422_beruntun_memicu_alert_telegram_sekali(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')->once();
        $this->app->instance(TelegramBotService::class, $mock);

        // perangkat tidak dikirim → 422, dipicu jalur validasi controller.
        for ($i = 0; $i < 6; $i++) {
            $this->withToken('write-token-456')
                ->postJson('/api/v1/installations/network-device', ['idempotency_key' => 'x-'.$i])
                ->assertStatus(422);
        }
    }

    public function test_counter_network_device_terpisah_dari_network_assignment(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')->never();
        $this->app->instance(TelegramBotService::class, $mock);

        // 4 gagal di network-assignment + 4 gagal di network-device — kalau
        // counter-nya gak terpisah, totalnya 8 bakal ke-hitung nembus ambang
        // 5 di salah satu. Karena terpisah, masing-masing cuma 4 — gak
        // pernah memicu alert.
        for ($i = 0; $i < 4; $i++) {
            $this->withToken('write-token-456')
                ->postJson('/api/v1/installations/network-assignment', ['idempotency_key' => 'a-'.$i])
                ->assertStatus(422);
        }

        for ($i = 0; $i < 4; $i++) {
            $this->withToken('write-token-456')
                ->postJson('/api/v1/installations/network-device', ['idempotency_key' => 'd-'.$i])
                ->assertStatus(422);
        }
    }
}
