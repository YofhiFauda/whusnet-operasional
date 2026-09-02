<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Distribution;
use App\Models\Pop;
use App\Models\WebhookOutbox;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * ≥5 gagal 422 beruntun dalam 10 menit → alert `TelegramBotService`
 * (rencana-implementasi.md §"Keputusan resmi" #3).
 */
class NetworkAssignmentAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webhooks.network_assignment_write_token' => 'write-token-456']);
    }

    private function seedCustomer(): Customer
    {
        $cabang = Pop::create([
            'code' => 'JTS', 'pop_code' => 'PNR-JTS', 'name' => 'Jetis',
            'type' => 'cabang', 'status' => 'active', 'cid_prefix' => 'C',
        ]);

        return Customer::create([
            'customer_code' => 'RQ000631',
            'full_name' => 'Pelanggan Uji',
            'primary_phone' => '081234500001',
            'registration_date' => '2026-08-01',
            'status' => 'installation_in_progress',
            'pop_id' => $cabang->id,
            'address' => 'Jl. Uji No. 1',
        ]);
    }

    public function test_lima_gagal_422_beruntun_memicu_alert_telegram_sekali(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')->once();
        $this->app->instance(TelegramBotService::class, $mock);

        // Body kosong (tanpa mini_pop_code+distribution_code, tanpa
        // perangkat) → 422, dipicu jalur validasi controller, bukan service.
        for ($i = 0; $i < 6; $i++) {
            $this->withToken('write-token-456')
                ->postJson('/api/v1/installations/network-assignment', ['idempotency_key' => 'x-'.$i])
                ->assertStatus(422);
        }
    }

    public function test_sukses_memutus_rentetan_gagal_counter_reset(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')->never();
        $this->app->instance(TelegramBotService::class, $mock);

        $customer = $this->seedCustomer();
        $outbox = new WebhookOutbox([
            'destination' => 'website_b',
            'event' => 'installation.activated',
            'event_id' => (string) Str::uuid(),
            'idempotency_key' => 'installation:ok:activation:1',
            'customer_id' => $customer->id,
            'payload' => [],
            'status' => 'delivered',
        ]);
        $outbox->save();

        for ($i = 0; $i < 4; $i++) {
            $this->withToken('write-token-456')
                ->postJson('/api/v1/installations/network-assignment', ['idempotency_key' => 'x-'.$i])
                ->assertStatus(422);
        }

        // Sukses (perangkat-saja gak valid krn belum ada assignment — pakai
        // body kosong yang lolos: assignment beneran, sukses).
        $miniPop = Pop::create([
            'code' => 'JTS-C1', 'pop_code' => 'C1', 'name' => 'Mini POP C1',
            'type' => 'mini_pop', 'status' => 'active', 'parent_id' => $customer->pop_id,
        ]);
        Distribution::create(['pop_id' => $miniPop->id, 'code' => 'A', 'name' => 'Distribusi A', 'description' => '-']);

        $this->withToken('write-token-456')
            ->postJson('/api/v1/installations/network-assignment', [
                'idempotency_key' => 'installation:ok:activation:1',
                'mini_pop_code' => 'C1',
                'distribution_code' => 'A',
            ])
            ->assertOk();

        for ($i = 4; $i < 8; $i++) {
            $this->withToken('write-token-456')
                ->postJson('/api/v1/installations/network-assignment', ['idempotency_key' => 'y-'.$i])
                ->assertStatus(422);
        }
    }
}
