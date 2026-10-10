<?php

namespace Tests\Feature;

use App\Enums\DeviceAcquisitionSource;
use App\Enums\DeviceOwnership;
use App\Enums\FopTaskPriority;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Radio ownership modem (Tidak Ganti/Sewa Gudang/Beli Sendiri/Beli dari
 * Gudang) di Laporan Maintenance & C-REQ — rancangan ownership modem
 * sewa/beli, 2026-10-09 (task 3). `TaskMaintenanceModemInstallTest` &
 * `TaskCreqBillingReportTest` menutupi regresi "sewa_gudang" (perilaku
 * lama); file ini fokus ke field `modem_ownership_choice` baru dan
 * cabang "beli_sendiri"/"beli_gudang".
 */
class TaskMaintenanceOwnershipRadioTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Customer $customer;

    protected User $technician;

    protected Task $task;

    protected FopTask $fopTask;

    protected Item $modem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'MTN-OWN', 'pop_code' => 'OWN', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Ownership Test', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-MTN-OWN-001',
            'full_name' => 'Ownership Maintenance Customer',
            'primary_phone' => '0812340004',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $this->task = Task::create([
            'task_number' => 'TASK-MTN-OWN-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Ownership Modem',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $this->task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-MTN-OWN-001',
            'task_date' => now(),
            'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Uji Ownership Modem',
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'task_id' => $this->task->id,
            'issue' => 'Uji ownership modem',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        $category = ItemCategory::create(['code' => 'modem_ont', 'name' => 'Modem/ONT Pelanggan', 'default_unit' => 'pcs']);
        $this->modem = Item::create([
            'code' => 'ONT-OWN-TEST', 'name' => 'Modem ONT Ownership', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
    }

    private function basePayload(): array
    {
        return [
            'kendala_teknis' => 'Cek ownership modem.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
        ];
    }

    #[Test]
    public function pilih_sn_tanpa_modem_ownership_choice_ditolak(): void
    {
        $serial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-OWN-001',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->basePayload() + [
            'selected_inventory_serial_id' => $serial->id,
        ]);

        $response->assertSessionHasErrors('modem_ownership_choice');

        $this->task->refresh();
        $this->assertNotEquals(TaskStatus::SELESAI, $this->task->status);
    }

    #[Test]
    public function beli_sendiri_wajib_isi_brand_model_serial_manual(): void
    {
        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->basePayload() + [
            'modem_ownership_choice' => 'beli_sendiri',
        ]);

        $response->assertSessionHasErrors(['manual_device_brand', 'manual_device_model', 'manual_device_serial_number']);

        $this->task->refresh();
        $this->assertNotEquals(TaskStatus::SELESAI, $this->task->status);
    }

    #[Test]
    public function beli_sendiri_dengan_data_lengkap_tercatat_sebagai_customer_device_tanpa_installserial(): void
    {
        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->basePayload() + [
            'modem_ownership_choice' => 'beli_sendiri',
            'manual_device_brand' => 'TP-Link',
            'manual_device_model' => 'Archer C6',
            'manual_device_serial_number' => 'MANUAL-SN-001',
        ]);

        $response->assertSessionHasNoErrors();

        $this->task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $this->task->status);

        $device = $this->customer->customerDevice()->first();
        $this->assertNotNull($device);
        $this->assertSame('TP-Link', $device->brand);
        $this->assertSame('Archer C6', $device->model);
        $this->assertSame('MANUAL-SN-001', $device->serial_number);
        $this->assertSame(DeviceAcquisitionSource::BELI_SENDIRI, $device->acquisition_source);

        // Beli Sendiri TIDAK PERNAH lewat gudang — tidak ada InventorySerial
        // yang disentuh sama sekali (§DeviceOwnership).
        $this->assertSame(0, InventorySerial::where('status', SerialStatus::INSTALLED->value)->count());
    }

    #[Test]
    public function beli_dari_gudang_installserial_dengan_ownership_customer_dan_sale_tercatat(): void
    {
        $serial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-OWN-BELI-001',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->basePayload() + [
            'selected_inventory_serial_id' => $serial->id,
            'modem_ownership_choice' => 'beli_gudang',
            'modem_sale_price' => 150000,
        ]);

        $response->assertSessionHasNoErrors();

        $this->task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $this->task->status);

        $serial->refresh();
        $this->assertEquals(SerialStatus::INSTALLED, $serial->status);
        $this->assertEquals(DeviceOwnership::CUSTOMER, $serial->ownership);
        $this->assertEquals($this->customer->id, $serial->customer_id);

        $sale = InventorySerialSale::where('inventory_serial_id', $serial->id)->firstOrFail();
        $this->assertEquals($this->customer->id, $sale->customer_id);
        $this->assertEquals(150000.00, (float) $sale->price);

        $device = $this->customer->customerDevice()->first();
        $this->assertEquals(DeviceAcquisitionSource::BELI_GUDANG, $device->acquisition_source);
    }

    #[Test]
    public function beli_dari_gudang_tanpa_harga_ditolak(): void
    {
        $serial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-OWN-BELI-002',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->basePayload() + [
            'selected_inventory_serial_id' => $serial->id,
            'modem_ownership_choice' => 'beli_gudang',
        ]);

        $response->assertSessionHasErrors('modem_sale_price');

        $serial->refresh();
        $this->assertEquals(SerialStatus::ISSUED, $serial->status);
    }

    #[Test]
    public function sewa_gudang_flow_sama_seperti_sekarang(): void
    {
        $serial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-OWN-SEWA-001',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->basePayload() + [
            'selected_inventory_serial_id' => $serial->id,
            'modem_ownership_choice' => 'sewa_gudang',
        ]);

        $response->assertSessionHasNoErrors();

        $this->task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $this->task->status);

        $serial->refresh();
        $this->assertEquals(SerialStatus::INSTALLED, $serial->status);
        $this->assertEquals(DeviceOwnership::COMPANY, $serial->ownership);
        $this->assertEquals($this->customer->id, $serial->customer_id);

        $this->assertSame(0, InventorySerialSale::count());

        $device = $this->customer->customerDevice()->first();
        $this->assertEquals(DeviceAcquisitionSource::SEWA, $device->acquisition_source);
    }

    private function issuedSerial(string $serialNumber): InventorySerial
    {
        return InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => $serialNumber,
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);
    }

    /**
     * Combobox SN cuma disembunyikan x-show — SN sisa pilihan sebelumnya tetap
     * ter-POST walau radio sudah pindah ke "Tidak Ganti Modem".
     */
    #[Test]
    public function tidak_ganti_dengan_sn_sisa_ditolak_dan_sn_tidak_terinstall(): void
    {
        $serial = $this->issuedSerial('SN-OWN-STALE-TG');

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->basePayload() + [
            'modem_ownership_choice' => 'tidak_ganti',
            'selected_inventory_serial_id' => $serial->id,
        ]);

        $response->assertSessionHasErrors('selected_inventory_serial_id');
        $this->assertEquals(SerialStatus::ISSUED, $serial->refresh()->status);
        $this->assertNotEquals(TaskStatus::SELESAI, $this->task->refresh()->status);
    }

    #[Test]
    public function beli_sendiri_dengan_sn_sisa_ditolak(): void
    {
        $serial = $this->issuedSerial('SN-OWN-STALE-BS');

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->basePayload() + [
            'modem_ownership_choice' => 'beli_sendiri',
            'manual_device_brand' => 'TP-Link',
            'manual_device_model' => 'Archer C6',
            'manual_device_serial_number' => 'MANUAL-STALE-BS',
            'selected_inventory_serial_id' => $serial->id,
        ]);

        $response->assertSessionHasErrors('selected_inventory_serial_id');
        $this->assertEquals(SerialStatus::ISSUED, $serial->refresh()->status);
        $this->assertNull($this->customer->customerDevice()->first());
    }

    #[Test]
    public function sewa_gudang_atau_beli_gudang_tanpa_sn_ditolak(): void
    {
        $this->issuedSerial('SN-OWN-NOSN');

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->basePayload() + [
            'modem_ownership_choice' => 'sewa_gudang',
        ])->assertSessionHasErrors('selected_inventory_serial_id');

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->basePayload() + [
            'modem_ownership_choice' => 'beli_gudang',
            'modem_sale_price' => 150000,
        ])->assertSessionHasErrors('selected_inventory_serial_id');

        $this->assertNotEquals(TaskStatus::SELESAI, $this->task->refresh()->status);
    }

    /**
     * Modem milik pelanggan (Beli dari Gudang di kunjungan sebelumnya) tidak
     * boleh ikut diretur oleh tukar modem Sewa Gudang berikutnya — SN-nya masih
     * tercatat terjual di `inventory_serial_sales`. SN sewa (COMPANY) tetap
     * diretur seperti biasa.
     */
    #[Test]
    public function tukar_modem_sewa_gudang_tidak_meretur_modem_milik_pelanggan(): void
    {
        $customerOwned = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-OWN-MILIK-PLG',
            'status' => SerialStatus::INSTALLED->value,
            'customer_id' => $this->customer->id,
            'ownership' => DeviceOwnership::CUSTOMER,
            'issued_from_pop_id' => $this->pop->id,
        ]);
        $companyOwned = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-OWN-SEWA-LAMA',
            'status' => SerialStatus::INSTALLED->value,
            'customer_id' => $this->customer->id,
            'ownership' => DeviceOwnership::COMPANY,
            'issued_from_pop_id' => $this->pop->id,
        ]);
        $replacement = $this->issuedSerial('SN-OWN-PENGGANTI');

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->basePayload() + [
            'modem_ownership_choice' => 'sewa_gudang',
            'selected_inventory_serial_id' => $replacement->id,
        ])->assertSessionHasNoErrors();

        $customerOwned->refresh();
        $this->assertEquals(SerialStatus::INSTALLED, $customerOwned->status, 'Modem milik pelanggan tidak boleh diretur oleh tukar modem.');
        $this->assertEquals($this->customer->id, $customerOwned->customer_id);
        $this->assertEquals(DeviceOwnership::CUSTOMER, $customerOwned->ownership);

        $companyOwned->refresh();
        $this->assertEquals(SerialStatus::RETURNED, $companyOwned->status, 'Modem sewa lama tetap diretur seperti biasa.');

        $this->assertEquals(SerialStatus::INSTALLED, $replacement->refresh()->status);
    }

    /**
     * CReqCategory::TAMBAH_MODEM (returnExistingSerial=false) tetap mencatat
     * ownership di SN KEDUA walau `customer_devices` cuma hasOne (tetap
     * reflect modem pertama) — limitasi struktural existing, bukan bug baru
     * yang diperkenalkan task ini (lihat docblock `installSerial()`).
     */
    #[Test]
    public function tambah_modem_beli_dari_gudang_tetap_catat_ownership_di_sn_walau_customer_devices_tidak_disentuh(): void
    {
        $creqTask = Task::create([
            'task_number' => 'TASK-CREQ-OWN-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::CREQ->value,
            'title' => 'C-REQ Tambah Modem Ownership',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $creqTask->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $firstSerial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-OWN-FIRST',
            'status' => SerialStatus::INSTALLED->value,
            'customer_id' => $this->customer->id,
            'ownership' => DeviceOwnership::COMPANY,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $this->customer->customerDevice()->create([
            'device_type' => 'ONT',
            'brand' => 'ZTE',
            'model' => 'F660',
            'serial_number' => $firstSerial->serial_number,
            'acquisition_source' => DeviceAcquisitionSource::SEWA,
        ]);

        $secondSerial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-OWN-SECOND',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $creqTask), $this->basePayload() + [
            'creq_category' => 'tambah_modem',
            'selected_inventory_serial_id' => $secondSerial->id,
            'modem_ownership_choice' => 'beli_gudang',
            'modem_sale_price' => 200000,
        ]);

        $response->assertSessionHasNoErrors();

        $firstSerial->refresh();
        $this->assertEquals(SerialStatus::INSTALLED, $firstSerial->status, 'Tambah Modem tidak boleh meretur modem lama.');
        $this->assertEquals(DeviceOwnership::COMPANY, $firstSerial->ownership);

        $secondSerial->refresh();
        $this->assertEquals(SerialStatus::INSTALLED, $secondSerial->status);
        $this->assertEquals(DeviceOwnership::CUSTOMER, $secondSerial->ownership);
        $this->assertEquals($this->customer->id, $secondSerial->customer_id);

        $sale = InventorySerialSale::where('inventory_serial_id', $secondSerial->id)->firstOrFail();
        $this->assertEquals(200000.00, (float) $sale->price);

        // customer_devices (hasOne) TIDAK disentuh — tetap reflect SN pertama.
        $device = $this->customer->customerDevice()->first();
        $this->assertSame($firstSerial->serial_number, $device->serial_number);
        $this->assertEquals(DeviceAcquisitionSource::SEWA, $device->acquisition_source);
    }
}
