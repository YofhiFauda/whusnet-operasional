<?php

namespace Tests\Feature;

use App\Enums\CustodyStatus;
use App\Enums\DeviceAcquisitionSource;
use App\Enums\DeviceOwnership;
use App\Enums\FopTaskPriority;
use App\Enums\OwnershipMode;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\Village;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gate `contract_type` sewa/beli di Laporan Pemasangan (PSB) — rancangan
 * ownership modem sewa/beli, 2026-10-09, task 4. Pola setup gabungan
 * `InstallationSerialDropdownAuthorityTest` (custody SN/Task PEMASANGAN) +
 * `CustomerFinalVerificationTest` (alamat lengkap + CustomerService, dibutuhkan
 * `finalVerify()` buat test Invoice AWAL).
 */
class CustomerInstallationOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);
    }

    /**
     * @return array{0: Customer, 1: User, 2: Task, 3: Pop, 4: Pop}
     */
    private function setupInstallingCustomer(string $contractType): array
    {
        $pusat = Pop::create(['code' => 'PUSAT-OWN', 'pop_code' => 'PST', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Ownership Test', 'type' => 'pusat', 'status' => 'active']);
        $pop = Pop::create(['code' => 'CABANG-OWN', 'pop_code' => 'CBG', 'registration_prefix' => 'C', 'cid_prefix' => 'E', 'name' => 'Cabang Ownership Test', 'type' => 'cabang', 'status' => 'active']);

        $role = Role::where('code', 'teknisi')->firstOrFail();
        $technician = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $technician->load('role');
        $technician->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::query()->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'TEST-OWN-'.strtoupper($contractType).'-001',
            'full_name' => 'Ownership Installation Customer '.$contractType,
            'primary_phone' => '0812340005',
            'status' => 'installation_in_progress',
            'pop_id' => $pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
            'address' => 'Jl. Ownership Test No. 1',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Ownership Test No. 1',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'province' => 'Jawa Timur',
            'city' => 'Ponorogo',
            'district' => $district->name,
            'village' => $village->name,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '20 Mbps',
            'monthly_price' => $package->monthly_price,
            'discount' => 0.00,
            'ppn' => 11.00,
            'total_monthly_bill' => $package->monthly_price * 1.11,
            'contract_type' => $contractType,
            'service_status' => 'menunggu_pemasangan',
            'billing_status' => 'pending',
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
            'task_number' => 'TASK-OWN-'.strtoupper($contractType).'-001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Ownership Test '.$contractType,
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        return [$customer, $technician, $task, $pusat, $pop];
    }

    /**
     * Custody SN di tangan $technician, lewat chain Receive → Transfer → Issue
     * (pola sama InstallationSerialDropdownAuthorityTest).
     */
    private function issueSerialToTechnician(Pop $pusat, Pop $cabang, User $technician, string $serialNumber): InventorySerial
    {
        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $ont = Item::create([
            'code' => 'ONT-OWN-'.$serialNumber, 'name' => 'ONT Ownership Test', 'item_category_id' => $catAktif->id,
            'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value,
        ]);

        $admin = User::factory()->create();
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $ont, [$serialNumber], 250000, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $ont->id, 'serial_numbers' => [$serialNumber]]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [$serialNumber], [], $admin);
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $ont->id, 'serial_numbers' => [$serialNumber]]], $admin);

        return $serial->refresh();
    }

    /**
     * Item PASIF (tracking quantity, bukan SERIALIZED) buat baris Material
     * Terpakai — syarat buka Fase 6, lihat komentar di `basePemasanganPayload()`.
     */
    private function passiveMaterialItemId(User $technician, Pop $cabang): int
    {
        $category = ItemCategory::firstOrCreate(
            ['code' => 'kabel_dropcore'],
            ['name' => 'Kabel Dropcore', 'default_unit' => 'meter', 'equipment_class' => 'pasif']
        );

        $item = Item::firstOrCreate(
            ['code' => 'KABEL-OWN-TEST'],
            ['name' => 'Kabel Dropcore Ownership Test', 'unit' => 'meter', 'item_category_id' => $category->id, 'tracking_type' => 'quantity']
        );

        // Custody langsung lewat model, bukan chain Receive→Transfer→Issue —
        // test ini fokus ke ownership modem, bukan jalur Gudang barang pasif.
        // reconcileMaterialsAgainstCustody() (storeSpeedtest()) menegakkan
        // custody SUNGGUHAN, jadi baris ini wajib ada atau speedtest ditolak
        // "Sisa custody tim ... tidak cukup".
        TechnicianCustody::create([
            'technician_id' => $technician->id,
            'issued_from_pop_id' => $cabang->id,
            'item_id' => $item->id,
            'qty_remaining' => 10,
            'unit_price_snapshot' => 5000,
            'status' => CustodyStatus::ISSUED,
            'issued_at' => now(),
        ]);

        return $item->id;
    }

    private function basePemasanganPayload(User $technician, Pop $cabang): array
    {
        return [
            'device_type' => 'ont',
            'connection_mode' => 'pppoe',
            'wifi_ssid' => 'WHUSNET_OWN_TEST',
            'wifi_password' => 'password123',
            'odp_number' => 'ODP-01',
            'odp_port' => '1',
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'contract_photo' => UploadedFile::fake()->image('contract.jpg'),
            'signature_photo' => UploadedFile::fake()->image('signature.jpg'),
            // Material Terpakai (minimal 1 baris, jumlah > 0) — syarat buka
            // Fase 6 (lihat gerbang $pemasanganComplete di report() dan
            // abort_unless di storeSpeedtest()). Custody SUNGGUHAN disiapkan
            // di `passiveMaterialItemId()` — reconcileMaterialsAgainstCustody()
            // menolak submit kalau custody-nya gak cukup.
            'materials' => [
                ['item_id' => $this->passiveMaterialItemId($technician, $cabang), 'item_name' => 'Kabel Dropcore', 'item_type' => 'kabel_dropcore', 'qty' => 2, 'unit' => 'meter'],
            ],
        ];
    }

    private function baseSpeedtestPayload(): array
    {
        return [
            'test_upload' => 10,
            'test_download' => 20,
            'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
        ];
    }

    #[Test]
    public function contract_type_sewa_flow_installasi_tidak_berubah(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInstallingCustomer('sewa');
        $serial = $this->issueSerialToTechnician($pusat, $cabang, $technician, 'SN-OWN-SEWA-001');

        $pemasanganResponse = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload($technician, $cabang) + [
            'selected_inventory_serial_id' => $serial->id,
        ]);
        $pemasanganResponse->assertSessionHasNoErrors();

        $installation = $customer->installations()->latest()->first();
        $this->assertNull($installation->modem_acquisition_choice);

        $speedtestResponse = $this->actingAs($technician)->post(route('customers.installation.speedtest', $customer->id), $this->baseSpeedtestPayload());
        $speedtestResponse->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertEquals('verification_admin', $customer->status);

        $serial->refresh();
        $this->assertEquals(SerialStatus::INSTALLED, $serial->status);
        $this->assertEquals(DeviceOwnership::COMPANY, $serial->ownership, 'contract_type sewa wajib byte-identik: ownership tetap COMPANY.');

        $this->assertSame(0, InventorySerialSale::count(), 'contract_type sewa tidak pernah membuat InventorySerialSale.');

        $device = $customer->customerDevice()->first();
        $this->assertEquals(DeviceAcquisitionSource::SEWA, $device->acquisition_source);

        // Invoice AWAL tidak ada baris Jual Modem (amount 0 di-skip builder).
        $this->finalVerifyAsAdmin($customer);
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();
        $this->assertFalse(
            $invoice->items()->whereHas('subcategory', fn ($q) => $q->where('code', 'jual_modem'))->exists(),
            'Invoice AWAL contract_type sewa tidak boleh punya baris Jual Modem.'
        );
    }

    #[Test]
    public function contract_type_beli_wajib_pilih_modem_acquisition_choice(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInstallingCustomer('beli');
        $serial = $this->issueSerialToTechnician($pusat, $cabang, $technician, 'SN-OWN-BELI-WAJIB-001');

        $response = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload($technician, $cabang) + [
            'selected_inventory_serial_id' => $serial->id,
        ]);

        $response->assertSessionHasErrors('modem_acquisition_choice');

        $installation = $customer->installations()->latest()->first();
        $this->assertNotEquals('completed', $installation->installation_status);
    }

    #[Test]
    public function contract_type_beli_beli_sendiri_tidak_panggil_installserial(): void
    {
        Storage::fake('public');
        [$customer, $technician, , , $cabang] = $this->setupInstallingCustomer('beli');

        $pemasanganResponse = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload($technician, $cabang) + [
            'modem_acquisition_choice' => 'beli_sendiri',
            'manual_device_brand' => 'TP-Link',
            'manual_device_model' => 'Archer C6',
            'manual_device_serial_number' => 'MANUAL-OWN-SN-001',
        ]);
        $pemasanganResponse->assertSessionHasNoErrors();

        $installation = $customer->installations()->latest()->first();
        $this->assertNull($installation->selected_inventory_serial_id, 'beli_sendiri tidak wajib SN sama sekali.');

        $speedtestResponse = $this->actingAs($technician)->post(route('customers.installation.speedtest', $customer->id), $this->baseSpeedtestPayload());
        $speedtestResponse->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertEquals('verification_admin', $customer->status);

        $device = $customer->customerDevice()->first();
        $this->assertNotNull($device);
        $this->assertSame('TP-Link', $device->brand);
        $this->assertSame('Archer C6', $device->model);
        $this->assertSame('MANUAL-OWN-SN-001', $device->serial_number);
        $this->assertEquals(DeviceAcquisitionSource::BELI_SENDIRI, $device->acquisition_source);

        // Beli Sendiri TIDAK PERNAH lewat gudang — tidak ada InventorySerial
        // yang terinstall sama sekali.
        $this->assertSame(0, InventorySerial::where('status', SerialStatus::INSTALLED->value)->count());
        $this->assertSame(0, InventorySerialSale::count());
    }

    #[Test]
    public function contract_type_beli_beli_dari_gudang_installserial_ownership_customer_dan_invoice_dapat_baris_jual_modem(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInstallingCustomer('beli');
        $serial = $this->issueSerialToTechnician($pusat, $cabang, $technician, 'SN-OWN-BELI-GUDANG-001');

        $pemasanganResponse = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload($technician, $cabang) + [
            'selected_inventory_serial_id' => $serial->id,
            'modem_acquisition_choice' => 'beli_gudang',
            'modem_sale_price' => 150000,
        ]);
        $pemasanganResponse->assertSessionHasNoErrors();

        $speedtestResponse = $this->actingAs($technician)->post(route('customers.installation.speedtest', $customer->id), $this->baseSpeedtestPayload());
        $speedtestResponse->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertEquals('verification_admin', $customer->status);

        $serial->refresh();
        $this->assertEquals(SerialStatus::INSTALLED, $serial->status);
        $this->assertEquals(DeviceOwnership::CUSTOMER, $serial->ownership);
        $this->assertEquals($customer->id, $serial->customer_id);

        $sale = InventorySerialSale::where('inventory_serial_id', $serial->id)->firstOrFail();
        $this->assertEquals($customer->id, $sale->customer_id);
        $this->assertEquals(150000.00, (float) $sale->price);

        $device = $customer->customerDevice()->first();
        $this->assertEquals(DeviceAcquisitionSource::BELI_GUDANG, $device->acquisition_source);

        // finalVerify() (CS) menerbitkan Invoice AWAL — modem_sale_price
        // dibaca dari draft pointer CustomerInstallation, bukan dari request
        // finalVerify (form itu tidak pernah mengirim field ini).
        $this->finalVerifyAsAdmin($customer);

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();
        $jualModemItem = $invoice->items()->whereHas('subcategory', fn ($q) => $q->where('code', 'jual_modem'))->first();
        $this->assertNotNull($jualModemItem, 'Invoice AWAL wajib punya baris Jual Modem.');
        $this->assertEquals(150000.00, (float) $jualModemItem->amount);
    }

    private function finalVerifyAsAdmin(Customer $customer): void
    {
        $admin = $this->loginAsAdmin();

        $response = $this->actingAs($admin)->post(route('customers.verification.final', $customer->id), [
            'issue_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasNoErrors();
    }
}
