<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Wizard Laporan Pemasangan (installations.report) dipecah jadi DUA submit
 * terpisah: Laporan Pemasangan & Perangkat (step 5, storePemasangan — TIDAK
 * menyelesaikan task/workflow) lalu Laporan Speedtest (step 6, storeSpeedtest
 * — SATU-SATUNYA titik penyelesaian). Step 6 wajib terkunci sampai step 5
 * lengkap & tombol "Aktivasi" ditekan.
 */
class InstallationSpeedtestActivationGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_speedtest_report_locked_until_pemasangan_saved(): void
    {
        [$customer, $technician] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)
            ->get(route('customers.installation.report', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('Aktivasi Laporan Speedtest');
        $response->assertDontSee('Simpan &amp; Selesaikan Pemasangan');
    }

    public function test_store_speedtest_rejected_when_pemasangan_not_complete(): void
    {
        [$customer, $technician] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)
            ->post(route('customers.installation.speedtest', $customer->id), [
                'test_download' => 20,
                'test_upload' => 10,
                'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
            ]);

        $response->assertStatus(422);

        $customer->refresh();
        $this->assertEquals('installation_in_progress', $customer->status);
    }

    /**
     * ADHOC: tombol Aktivasi cuma wajib Informasi Perangkat Aktif + Distribusi
     * Jaringan (ODP/OLT) — foto & material TIDAK menahan penyimpanan lagi.
     * Fase 6 tetap terkunci sampai foto+material menyusul di submit berikutnya.
     */
    public function test_store_pemasangan_succeeds_without_photos_or_material_but_fase6_stays_locked(): void
    {
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)
            ->post(route('customers.installation.pemasangan', $customer->id), [
                'device_type' => 'ont',
                'connection_mode' => 'pppoe',
                'wifi_ssid' => 'WHUSNET_PARTIAL',
                'wifi_password' => 'password123',
                'selected_inventory_serial_id' => $this->issueActiveSerialTo($technician, $pusat, $cabang, 'ZTEGC0009999')->id,
                'odp_number' => 'ODP-01',
                'odp_port' => '1',
                'olt_number' => 'OLT-01',
                'olt_slot' => '1',
                'olt_port' => '1',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertEquals('installation_in_progress', $customer->status);

        $installation = $customer->installations()->latest()->first();
        $this->assertEquals('in_progress', $installation->installation_status);
        $this->assertNull($installation->installation_photo);

        // Fase 6 masih terkunci — foto & material belum ada — tombol Aktivasi
        // masih tampil (belum jadi badge "Sudah Diaktivasi").
        $page = $this->actingAs($technician)
            ->get(route('customers.installation.report', $customer->id));
        $page->assertSee('Aktivasi Laporan Speedtest');
        $page->assertDontSee('Sudah Diaktivasi');

        // Server juga masih menolak Laporan Speedtest — bukan cuma UI klien.
        $speedtestResponse = $this->actingAs($technician)
            ->post(route('customers.installation.speedtest', $customer->id), [
                'test_download' => 20,
                'test_upload' => 10,
                'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
            ]);
        $speedtestResponse->assertStatus(422);
    }

    public function test_store_pemasangan_rejects_missing_device_or_network_fields(): void
    {
        [$customer, $technician] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)
            ->post(route('customers.installation.pemasangan', $customer->id), [
                'device_type' => 'ont',
                // connection_mode, wifi_ssid, wifi_password, odp sengaja kosong.
            ]);

        $response->assertSessionHasErrors([
            'connection_mode', 'wifi_ssid', 'wifi_password', 'odp_number', 'odp_port',
        ]);

        $customer->refresh();
        $this->assertEquals('installation_in_progress', $customer->status);
    }

    /**
     * Nomor/Slot/Port OLT opsional (ADHOC) — beda dari ODP yang wajib.
     */
    public function test_store_pemasangan_succeeds_without_olt_fields(): void
    {
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)
            ->post(route('customers.installation.pemasangan', $customer->id), [
                'device_type' => 'ont',
                'connection_mode' => 'pppoe',
                'wifi_ssid' => 'WHUSNET_NO_OLT',
                'wifi_password' => 'password123',
                'selected_inventory_serial_id' => $this->issueActiveSerialTo($technician, $pusat, $cabang, 'ZTEGC0001111')->id,
                'odp_number' => 'ODP-01',
                'odp_port' => '1',
                // olt_number, olt_slot, olt_port sengaja kosong.
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
    }

    public function test_store_pemasangan_unlocks_speedtest_without_completing_task(): void
    {
        Storage::fake('public');

        [$customer, $technician, $task, $pusat, $cabang] = $this->setupInProgressInstallation();
        [$kabel] = $this->issuePassiveQtyTo($technician, $pusat, $cabang, 50);

        $response = $this->actingAs($technician)
            ->post(route('customers.installation.pemasangan', $customer->id), [
                'device_type' => 'ont',
                'connection_mode' => 'pppoe',
                'wifi_ssid' => 'WHUSNET_GATE_TEST',
                'wifi_password' => 'password123',
                'selected_inventory_serial_id' => $this->issueActiveSerialTo($technician, $pusat, $cabang, 'ZTEGC1234567')->id,
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
                        'item_id' => $kabel->id,
                        'qty' => 50,
                        'unit' => 'meter',
                    ],
                ],
            ]);

        $response->assertSessionHas('success');
        $response->assertStatus(302);

        $customer->refresh();
        // Belum selesai — status pelanggan & task TIDAK ikut berubah.
        $this->assertEquals('installation_in_progress', $customer->status);
        $task->refresh();
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->status);

        $installation = $customer->installations()->latest()->first();
        $this->assertEquals('in_progress', $installation->installation_status);
        $this->assertNotNull($installation->installation_photo);

        // Step 6 sekarang terbuka, DAN data step 5 yang barusan diisi tidak hilang —
        // termasuk foto: dropzone wajib nunjukin thumbnail tersimpan, bukan
        // "Belum ada file" lagi (regresi yang dilaporkan user: keliatan hilang
        // padahal sudah kesimpen).
        $page = $this->actingAs($technician)
            ->get(route('customers.installation.report', $customer->id));
        $page->assertDontSee('Aktivasi Laporan Speedtest');
        $page->assertSee('WHUSNET_GATE_TEST');
        $page->assertSee('ZTEGC1234567');
        $page->assertSee('Sudah Tersimpan');
        $page->assertSee($installation->installation_photo);
    }

    public function test_store_speedtest_completes_task_and_transitions_workflow(): void
    {
        Storage::fake('public');

        [$customer, $technician, $task, $pusat, $cabang] = $this->setupInProgressInstallation();
        [$kabel] = $this->issuePassiveQtyTo($technician, $pusat, $cabang, 50);

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), [
            'device_type' => 'ont',
            'connection_mode' => 'pppoe',
            'wifi_ssid' => 'WHUSNET_GATE_TEST',
            'wifi_password' => 'password123',
            'selected_inventory_serial_id' => $this->issueActiveSerialTo($technician, $pusat, $cabang, 'ZTEGC1234567')->id,
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
                    'item_id' => $kabel->id,
                    'qty' => 50,
                    'unit' => 'meter',
                ],
            ],
        ]);

        $response = $this->actingAs($technician)
            ->post(route('customers.installation.speedtest', $customer->id), [
                'test_download' => 20,
                'test_upload' => 10,
                'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
            ]);

        $response->assertSessionHas('success');

        $customer->refresh();
        $this->assertEquals('verification_admin', $customer->status);

        $task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $task->status);

        $installation = $customer->installations()->latest()->first();
        $this->assertEquals('completed', $installation->installation_status);
    }

    /**
     * @return array{0: Customer, 1: User, 2: Task, 3: Pop, 4: Pop}
     */
    private function setupInProgressInstallation(): array
    {
        $pusat = Pop::create([
            'code' => 'GATE-PST',
            'pop_code' => 'GATEP',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP GATE Pusat',
            'type' => 'pusat',
            'status' => 'active',
        ]);

        $pop = Pop::create([
            'code' => 'GATE',
            'pop_code' => 'GATE',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP GATE',
            'type' => 'cabang',
            'status' => 'active',
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
            'customer_code' => 'TEST-GATE-001',
            'full_name' => 'Gate Speedtest Customer',
            'primary_phone' => '0812340000',
            'status' => 'installation_in_progress',
            'pop_id' => $pop->id,
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
            'task_number' => 'TASK-TEST-GATE-001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Gate Speedtest Customer',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        return [$customer, $technician, $task, $pusat, $pop];
    }

    /**
     * Terbitkan satu SN ONT ke custody teknisi (Receive Pusat → Transfer
     * Cabang → Issue Teknisi) supaya field SN — sekarang wajib dropdown
     * custody, fallback teks manual dicabut (koreksi lanjutan ADHOC-54) —
     * punya nilai valid buat submit. SN literal dipertahankan (bukan
     * di-random) supaya assertion `assertSee('ZTEGC...')` existing tetap valid.
     */
    private function issueActiveSerialTo(User $technician, Pop $pusat, Pop $cabang, string $serialNumber): InventorySerial
    {
        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $ont = Item::create([
            'code' => 'ONT-'.$serialNumber, 'name' => 'ONT Gate Test', 'item_category_id' => $catAktif->id,
            'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
        $admin = User::factory()->create();

        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $ont, [$serialNumber], 250000, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $ont->id, 'serial_numbers' => [$serialNumber]]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [$serialNumber], [], $admin);
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $ont->id, 'serial_numbers' => [$serialNumber]]], $admin);

        return $serial->refresh();
    }

    /**
     * Terbitkan qty barang PASIF (kabel dkk) ke custody teknisi — dipakai
     * fixture "Material Terpakai" di test lain di file ini. Opsi "Lainnya
     * (isi manual)" DICABUT dari dropdown Material Terpakai (koreksi lanjutan
     * ADHOC-54, 2026-09-12): baris material sekarang WAJIB `item_id` dari
     * custody tim, gak boleh lagi `item_name` karangan tanpa dasar sistem.
     *
     * @return array{0: Item, 1: int} [$item, $qtyIssued]
     */
    private function issuePassiveQtyTo(User $technician, Pop $pusat, Pop $cabang, float $qty): array
    {
        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create([
            'code' => 'KABEL-GATE-'.uniqid(), 'name' => 'Kabel Dropcore', 'item_category_id' => $catPasif->id,
            'unit' => 'meter', 'tracking_type' => 'quantity',
        ]);
        $admin = User::factory()->create();

        app(InventoryReceiveService::class)->receiveQuantity($pusat, $kabel, $qty, 5000, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $kabel->id, 'qty' => $qty]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$kabel->id => $qty], $admin);
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $kabel->id, 'qty' => $qty]], $admin);

        return [$kabel, $qty];
    }
}
