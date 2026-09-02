<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\InstallationActivated;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use App\Models\WebhookOutbox;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * API 1 — Webhook Pemasangan (docs/api/business-logic.md). Trigger SATU-
 * SATUNYA: tombol "Aktivasi Laporan Speedtest" (storePemasangan(), step 5
 * wizard) — bukan Mulai Pemasangan, bukan modal admin, bukan penyelesaian
 * Laporan Speedtest (step 6).
 */
class InstallationActivatedWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->seed(DatabaseSeeder::class);

        config([
            'webhooks.website_b.url' => 'https://website-b.test/webhooks/installation',
            'webhooks.website_b.secret' => 'test-secret-website-b',
            'webhooks.telegram_external.bot_token' => 'EXTERNAL-BOT-TOKEN',
            'webhooks.telegram_external.chat_id' => '-100999',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'website-b.test/*' => Http::response(['received' => true], 200),
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
    }

    public function test_payload_contains_all_fields_with_decimal_string_money(): void
    {
        [$customer, $technician] = $this->setupActivatableInstallation();

        $this->postPemasangan($customer, $technician, ['serial_number' => 'ZTEGC1234567']);

        $row = WebhookOutbox::where('destination', 'website_b')->firstOrFail();
        $data = $row->payload['data'];

        $this->assertSame('installation.activated', $row->payload['event']);
        $this->assertSame($customer->full_name, $data['customer']['nama']);
        $this->assertSame('POP GATE', $data['pop']['name']);
        $this->assertSame('Joresan', $data['desa']['name']);
        $this->assertSame('Mlarak', $data['desa']['kecamatan']);
        $this->assertSame('Kabupaten Ponorogo', $data['desa']['kota']);
        $this->assertSame('Home 20 Mbps', $data['paket']['name']);
        // decimal:2 cast → string desimal, BUKAN float JSON.
        $this->assertSame('150000.00', $data['paket']['harga_bulanan']);
        $this->assertIsString($data['paket']['harga_bulanan']);
        $this->assertSame('ZTEGC1234567', $data['perangkat']['sn']);
        $this->assertNotNull($data['task']['number']);
        $this->assertArrayNotHasKey('completed_at', $data['task']);
    }

    public function test_sn_odp_fallback_from_technical_detail_when_device_empty(): void
    {
        [$customer, $technician] = $this->setupActivatableInstallation();

        // customer_devices.odp TIDAK diisi controller (fakta yang diverifikasi
        // eksplorasi) — ODP cuma sampai di customer_technical_details.odp_number.
        $this->postPemasangan($customer, $technician, [
            'serial_number' => 'ZTEGC0000001',
            'odp_number' => 'ODP-JTS-04',
            'odp_port' => '3',
        ]);

        $row = WebhookOutbox::where('destination', 'website_b')->firstOrFail();
        $perangkat = $row->payload['data']['perangkat'];

        $this->assertSame('ODP-JTS-04', $perangkat['odp']);
        $this->assertSame('3', $perangkat['odp_port']);

        $device = CustomerDevice::where('customer_id', $customer->id)->first();
        $this->assertTrue(empty($device->odp), 'customer_devices.odp seharusnya tetap kosong — controller tidak pernah menulisnya.');
    }

    public function test_event_fires_only_on_store_pemasangan(): void
    {
        [$customer, $technician, $task] = $this->setupActivatableInstallation();

        // Mulai Pemasangan (start()) — SN/ODP belum ada, tidak boleh menembak.
        // Sudah dijalankan implisit oleh installations()->create() di fixture,
        // jadi cukup pastikan baseline masih nol sebelum step 5.
        $this->assertSame(0, WebhookOutbox::count());

        $this->postPemasangan($customer, $technician, ['serial_number' => 'SN-STEP5']);
        $this->assertSame(2, WebhookOutbox::count(), 'storePemasangan() harus memicu 1 event → 2 baris outbox.');

        Storage::fake('public');
        $this->actingAs($technician)->post(route('customers.installation.speedtest', $customer->id), [
            'test_download' => 20,
            'test_upload' => 10,
            'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
        ]);

        $this->assertSame(2, WebhookOutbox::count(), 'storeSpeedtest() TIDAK boleh menambah baris outbox — installation.activated cuma sekali per Aktivasi.');
    }

    public function test_fan_out_creates_two_outbox_rows_for_two_destinations(): void
    {
        [$customer, $technician] = $this->setupActivatableInstallation();

        $this->postPemasangan($customer, $technician, ['serial_number' => 'SN-FANOUT']);

        $rows = WebhookOutbox::orderBy('destination')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['telegram_external', 'website_b'], $rows->pluck('destination')->all());
        $this->assertSame($rows[0]->event_id, $rows[1]->event_id);
        $this->assertSame($rows[0]->idempotency_key, $rows[1]->idempotency_key);
        // Data inti sama, tapi baris ini dua record TERPISAH (retry independen).
        $this->assertSame($rows[0]->payload['data'], $rows[1]->payload['data']);
    }

    public function test_telegram_uses_external_config_not_internal_bot(): void
    {
        [$customer, $technician] = $this->setupActivatableInstallation();

        $this->postPemasangan($customer, $technician, ['serial_number' => 'SN-TELE']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org/botEXTERNAL-BOT-TOKEN/sendMessage');
        });
    }

    public function test_telegram_skips_when_data_unchanged_website_b_still_receives(): void
    {
        [$customer, $technician] = $this->setupActivatableInstallation();

        $payload = [
            'serial_number' => 'SN-SAMA',
            'odp_number' => 'ODP-SAMA',
        ];

        $this->postPemasangan($customer, $technician, $payload);
        $firstTelegram = WebhookOutbox::where('destination', 'telegram_external')->latest('id')->first();
        $this->assertSame('delivered', $firstTelegram->status);

        // Tekan Aktivasi lagi TANPA mengubah data apa pun.
        $this->postPemasangan($customer, $technician, $payload);

        $telegramRows = WebhookOutbox::where('destination', 'telegram_external')->orderBy('id')->get();
        $this->assertCount(2, $telegramRows);
        $this->assertSame('skipped', $telegramRows[1]->status);
        $this->assertStringContainsString('unchanged', $telegramRows[1]->last_error);

        $websiteBRows = WebhookOutbox::where('destination', 'website_b')->get();
        $this->assertCount(2, $websiteBRows, 'website_b wajib tetap menerima setiap penekanan Aktivasi, walau datanya sama.');
        $this->assertSame('delivered', $websiteBRows[1]->status);
    }

    public function test_repeated_activation_increments_idempotency_key(): void
    {
        [$customer, $technician] = $this->setupActivatableInstallation();

        $this->postPemasangan($customer, $technician, ['serial_number' => 'SN-SALAH']);
        $this->postPemasangan($customer, $technician, ['serial_number' => 'SN-BENAR']);

        $websiteBRows = WebhookOutbox::where('destination', 'website_b')->orderBy('id')->get();
        $this->assertCount(2, $websiteBRows);
        $this->assertSame("installation:{$customer->id}:activation:1", $websiteBRows[0]->idempotency_key);
        $this->assertSame("installation:{$customer->id}:activation:2", $websiteBRows[1]->idempotency_key);
        $this->assertNotSame($websiteBRows[0]->event_id, $websiteBRows[1]->event_id);
        $this->assertSame('SN-BENAR', $websiteBRows[1]->payload['data']['perangkat']['sn']);
    }

    public function test_no_outbox_rows_when_transaction_rolls_back(): void
    {
        $pop = Pop::create([
            'code' => 'RB', 'pop_code' => 'RB',
            'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Rollback', 'type' => 'cabang', 'status' => 'active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'TEST-RB-001',
            'full_name' => 'Rollback Customer',
            'primary_phone' => '0812340001',
            'status' => 'installation_in_progress',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        try {
            DB::transaction(function () use ($customer) {
                InstallationActivated::dispatch($customer);
                throw new \RuntimeException('paksa rollback');
            });
        } catch (\RuntimeException) {
            // sengaja ditelan — yang diuji cuma efek sampingnya
        }

        $this->assertSame(0, WebhookOutbox::count());
    }

    /**
     * @return array{0: Customer, 1: User, 2: Task}
     */
    private function setupActivatableInstallation(): array
    {
        $pop = Pop::create([
            'code' => 'GATE', 'pop_code' => 'GATE',
            'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP GATE', 'type' => 'cabang', 'status' => 'active',
        ]);

        $city = City::create(['name' => 'Kabupaten Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Mlarak']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Joresan']);

        $package = InternetPackage::create([
            'package_code' => 'PKT-20M',
            'name' => 'Home 20 Mbps',
            'category' => 'Paket Home Broadband',
            'package_group' => 'Home',
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => 150000,
            'is_active' => true,
        ]);

        $technician = User::factory()->create();
        $role = Role::where('name', 'Teknisi')->firstOrFail();
        $technician->role_id = $role->id;
        $technician->save();
        $technician->load('role');
        $technician->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP,
        ]);

        $customer = Customer::create([
            'customer_code' => 'TEST-WH-'.uniqid(),
            'full_name' => 'Masudah Yuni Fitri',
            'primary_phone' => '0812340000',
            'status' => 'installation_in_progress',
            'pop_id' => $pop->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $customer->installations()->create([
            'installation_status' => 'in_progress',
            'started_at' => now(),
            'start_time' => now()->toTimeString(),
            'scheduled_date' => now()->format('Y-m-d'),
            'scheduled_time' => '09:00',
            'technician_id' => $technician->id,
        ]);

        $task = Task::create([
            'task_number' => 'TASK-TEST-'.uniqid(),
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan '.$customer->full_name,
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        return [$customer, $technician, $task];
    }

    private function postPemasangan(Customer $customer, User $technician, array $overrides = []): void
    {
        Storage::fake('public');

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), array_merge([
            'device_type' => 'ont',
            'connection_mode' => 'pppoe',
            'wifi_ssid' => 'WHUSNET_TEST',
            'wifi_password' => 'password123',
            'odp_number' => 'ODP-01',
            'odp_port' => '1',
            'olt_number' => 'OLT-01',
            'olt_slot' => '1',
            'olt_port' => '1',
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'contract_photo' => UploadedFile::fake()->image('contract.jpg'),
            'signature_photo' => UploadedFile::fake()->image('signature.jpg'),
            'materials' => [
                [
                    'item_name' => 'Kabel Dropcore',
                    'item_type' => 'kabel_dropcore',
                    'qty' => 50,
                    'unit' => 'meter',
                ],
            ],
        ], $overrides));
    }
}
