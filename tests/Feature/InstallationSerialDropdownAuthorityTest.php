<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\MaterialKind;
use App\Enums\OwnershipMode;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMaterial;
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
 *
 * Koreksi lanjutan (permintaan eksplisit user): fallback teks manual DICABUT
 * total. Teknisi TANPA SN di custody sekarang TIDAK BISA mengisi SN sama
 * sekali — `serial_number` jadi `prohibited`, `selected_inventory_serial_id`
 * jadi `required` + dibatasi ke custody tim (`Rule::in`).
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
    public function sn_dropdown_yang_dipilih_menentukan_serial_number_tersimpan(): void
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
    public function tanpa_custody_eligible_ditolak_tidak_ada_fallback_manual(): void
    {
        Storage::fake('public');
        [$customer, $technician] = $this->setupInProgressInstallation();

        // Gak ada InventorySerial sama sekali di custody teknisi ini —
        // eligibleSerials kosong. Dulu field manual jadi fallback, sekarang
        // DITOLAK total (permintaan eksplisit user) — teknisi wajib ambil
        // barang dari Gudang dulu, gak ada jalan pintas ngetik SN sendiri.
        $response = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload() + [
            'serial_number' => 'ZTEMANUAL001',
        ]);

        $response->assertSessionHasErrors('serial_number'); // prohibited
        $this->assertDatabaseMissing('customer_technical_details', [
            'customer_id' => $customer->id,
            'router_or_ont_serial' => 'ZTEMANUAL001',
        ]);
    }

    #[Test]
    public function tanpa_sn_dropdown_ditolak_validasi(): void
    {
        Storage::fake('public');
        [$customer, $technician] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload());

        $response->assertSessionHasErrors('selected_inventory_serial_id');
    }

    /**
     * Combobox (bukan <select>): SN dirender sebagai nilai hidden + opsi
     * JSON yang bisa dicari, dan kategori barang pasif ikut combobox.
     * Regresi: komponen combobox harus tetap menaruh `id` di input hidden,
     * karena validasi wajib isi di JS membaca nilai dari id itu.
     */
    #[Test]
    public function laporan_pemasangan_render_combobox_sn_roll_dan_kategori_barang(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $ont = Item::create([
            'code' => 'ONT-SN-03', 'name' => 'ONT Combo Test', 'item_category_id' => $catAktif->id,
            'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value,
        ]);

        $admin = User::factory()->create();
        app(InventoryReceiveService::class)->receiveSerialized($pusat, $ont, ['ZTE-COMBO-001'], 250000, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $ont->id, 'serial_numbers' => ['ZTE-COMBO-001']]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, ['ZTE-COMBO-001'], [], $admin);
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $ont->id, 'serial_numbers' => ['ZTE-COMBO-001']]], $admin);

        $response = $this->actingAs($technician)->get(route('customers.installation.report', $customer->id));

        $response->assertOk();
        $response->assertSee('<input type="hidden" id="selected_inventory_serial_id" name="selected_inventory_serial_id"', false);
        $response->assertSee('ZTE-COMBO-001');
        $response->assertSee('Semua Kategori');
        $response->assertDontSee('updateSnStockHint', false);
    }

    /**
     * Estimasi survey dibaca per kategori: kategori AKTIF jadi patokan di seksi
     * SN, kabel satuan meter jadi patokan Roll, sisanya patokan Perangkat Pasif.
     * Estimasi tidak di-prefill jadi baris realisasi. Regresi: dulu semua
     * estimasi masuk daftar Perangkat Pasif dan teknisi harus membuang baris
     * yang salah kolom.
     */
    #[Test]
    public function estimasi_survey_dipetakan_ke_seksi_sn_roll_dan_pasif_sesuai_jenis_barang(): void
    {
        [$customer, $technician, , , $cabang] = $this->setupInProgressInstallation();

        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        $ont = Item::create(['code' => 'ONT-EST-01', 'name' => 'ONT Estimasi', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value]);
        $roll = Item::create(['code' => 'KABEL-ROLL-EST', 'name' => 'Kabel Roll Estimasi', 'item_category_id' => $catPasif->id, 'unit' => 'meter', 'tracking_type' => 'roll']);
        $qtyItem = Item::create(['code' => 'KLEM-EST', 'name' => 'Klem Estimasi', 'item_category_id' => $catPasif->id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);

        $surveyUser = User::factory()->create();
        $surveyFop = FopTask::create([
            'task_number' => 'TFOP-EST-SURVEY-01',
            'task_date' => now(),
            'category' => TaskType::SURVEY,
            'tugas' => 'Survey Estimasi Test',
            'pop_id' => $cabang->id,
            'issue' => 'Survey',
            'status' => TaskStatus::DRAFT,
            'priority' => FopTaskPriority::MEDIUM,
            'handling_sla_hours' => 1,
        ]);
        foreach ([[$ont, 1, 'unit'], [$roll, 50, 'meter'], [$qtyItem, 4, 'pcs']] as [$item, $qty, $unit]) {
            TaskMaterial::create([
                'fop_task_id' => $surveyFop->id,
                'customer_id' => $customer->id,
                'kind' => MaterialKind::ESTIMASI->value,
                'item_id' => $item->id,
                'item_type' => $item->category->code,
                'item_category_id' => $item->item_category_id,
                'item_name' => $item->name,
                'qty' => $qty,
                'unit' => $unit,
                'recorded_by' => $surveyUser->id,
            ]);
        }

        $response = $this->actingAs($technician)->get(route('customers.installation.report', $customer->id));

        $response->assertOk();
        $response->assertViewHas('estimasiPerangkatAktif', fn ($rows) => $rows->pluck('item_name')->all() === ['ONT Estimasi']);
        $response->assertViewHas('estimasiRoll', fn ($rows) => $rows->pluck('item_name')->all() === ['Kabel Roll Estimasi']);
        // Estimasi tidak lagi di-prefill jadi baris realisasi (harus dari custody).
        $response->assertViewHas('estimasiPasif', fn ($rows) => $rows->pluck('item_name')->all() === ['Klem Estimasi']);
        $response->assertViewHas('materialRows', fn ($rows) => collect($rows)->isEmpty());
    }

    #[Test]
    public function sn_dropdown_dari_custody_teknisi_lain_ditolak(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $ont = Item::create([
            'code' => 'ONT-SN-02', 'name' => 'ONT ZTE Test 2', 'item_category_id' => $catAktif->id,
            'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value,
        ]);

        $admin = User::factory()->create();
        $roleTeknisi = Role::where('code', 'teknisi')->firstOrFail();
        $otherTechnician = User::factory()->create(['role_id' => $roleTeknisi->id, 'status' => 'active']);

        // SN ini ada di custody TEKNISI LAIN, bukan tim task ini — dropdown
        // di form gak bakal nampilin ini, tapi cek server tetap wajib
        // menolak walau ID-nya dikirim manual lewat request (bypass UI).
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $ont, ['ZTE-LAIN-001'], 250000, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $ont->id, 'serial_numbers' => ['ZTE-LAIN-001']]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, ['ZTE-LAIN-001'], [], $admin);
        app(InventoryIssueService::class)->issue($cabang, $otherTechnician, [['item_id' => $ont->id, 'serial_numbers' => ['ZTE-LAIN-001']]], $admin);

        $response = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload() + [
            'selected_inventory_serial_id' => $serial->id,
        ]);

        $response->assertSessionHasErrors('selected_inventory_serial_id');
    }
}
