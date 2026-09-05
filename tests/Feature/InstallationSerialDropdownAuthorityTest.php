<?php

namespace Tests\Feature;

use App\Enums\OwnershipMode;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
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
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fix dual-SN Laporan Pemasangan (koreksi Warehouse — "barang aktif harus
 * punya SN buat dropdown"): sebelum fix ini, field teks `serial_number`
 * (manual) & dropdown `selected_inventory_serial_id` (custody Gudang) gak
 * saling validasi — teknisi bisa ngetik SN apa aja di teks sambil pilih SN
 * lain (atau gak pilih) di dropdown, dan yang tersimpan ke
 * customer_technical_details/customer_devices SELALU teks manual, beda dari
 * SN yang beneran di-install lewat installSerial() (storeSpeedtest()).
 *
 * `CustomerInstallationController::storePemasangan()` sekarang menimpa
 * `serial_number` dari `InventorySerial` begitu `selected_inventory_serial_id`
 * terisi — dropdown jadi satu-satunya sumber kebenaran kalau device ini
 * ke-track Inventory.
 */
class InstallationSerialDropdownAuthorityTest extends TestCase
{
    use RefreshDatabase;

    private function setupInProgressInstallation(): array
    {
        // Teknisi wajib punya permission `customers.detail.installation.update`
        // sungguhan (bukan cuma role) — beda dari test Warehouse lain yang
        // pakai user full-access, di sini abort_unless() cek hasPermission()
        // asli. Pola sama SurveyInstallationReportReturnToTest.
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $pusat = Pop::create(['code' => 'PUSAT-SN', 'pop_code' => 'PST', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat SN Test', 'type' => 'pusat', 'status' => 'active']);
        $pop = Pop::create(['code' => 'CABANG-SN', 'pop_code' => 'CBG', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang SN Test', 'type' => 'cabang', 'status' => 'active']);

        $role = Role::where('code', 'teknisi')->firstOrFail();
        $technician = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $technician->load('role');
        $technician->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        $customer = Customer::create([
            'customer_code' => 'TEST-SN-001',
            'full_name' => 'SN Dropdown Test Customer',
            'primary_phone' => '0812340002',
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
            'task_number' => 'TASK-TEST-SN-001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan SN Dropdown Test Customer',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        return [$customer, $technician, $task, $pusat, $pop];
    }

    private function basePayload(): array
    {
        return [
            'device_type' => 'ont',
            'connection_mode' => 'pppoe',
            'wifi_ssid' => 'WHUSNET_SN_TEST',
            'wifi_password' => 'password123',
            'odp_number' => 'ODP-01',
            'odp_port' => '1',
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'contract_photo' => UploadedFile::fake()->image('contract.jpg'),
            'signature_photo' => UploadedFile::fake()->image('signature.jpg'),
        ];
    }

    #[Test]
    public function pilih_sn_dari_dropdown_menimpa_teks_manual_yang_berbeda(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $ont = Item::create([
            'code' => 'ONT-SN-01', 'name' => 'ONT ZTE Test', 'item_category_id' => $catAktif->id,
            'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value,
        ]);

        $admin = User::factory()->create();
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $ont, ['ZTE-ASLI-001'], 250000, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $ont->id, 'serial_numbers' => ['ZTE-ASLI-001']]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, ['ZTE-ASLI-001'], [], $admin);
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $ont->id, 'serial_numbers' => ['ZTE-ASLI-001']]], $admin);

        $serial->refresh();

        $response = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload() + [
            // Teknisi (sengaja/khilaf) ngetik SN LAIN di kolom manual, tapi
            // milih SN yang bener di dropdown — dropdown yang harus menang.
            'serial_number' => 'SN-NGARANG-BEDA',
            'selected_inventory_serial_id' => $serial->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $customer->refresh();
        $installation = $customer->installations()->latest()->first();
        $this->assertEquals($serial->id, $installation->selected_inventory_serial_id);

        $detail = CustomerTechnicalDetail::where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals('ZTE-ASLI-001', $detail->router_or_ont_serial, 'SN tersimpan wajib dari InventorySerial, bukan teks manual yang beda');

        $device = $customer->customerDevice()->firstOrFail();
        $this->assertEquals('ZTE-ASLI-001', $device->serial_number, 'customer_devices juga wajib ikut SN dari dropdown, bukan teks manual');
    }

    #[Test]
    public function tanpa_custody_eligible_field_manual_tetap_jadi_fallback(): void
    {
        Storage::fake('public');
        [$customer, $technician] = $this->setupInProgressInstallation();

        // Gak ada InventorySerial sama sekali di custody teknisi ini —
        // eligibleSerials kosong, field manual harus tetap jalan seperti
        // perilaku lama (regresi negatif).
        $response = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload() + [
            'serial_number' => 'ZTEMANUAL001',
        ]);

        $response->assertSessionHasNoErrors();

        $detail = CustomerTechnicalDetail::where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals('ZTEMANUAL001', $detail->router_or_ont_serial);
    }

    #[Test]
    public function tanpa_sn_manual_maupun_dropdown_ditolak_validasi(): void
    {
        Storage::fake('public');
        [$customer, $technician] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload());

        $response->assertSessionHasErrors('serial_number');
    }
}
