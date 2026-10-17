<?php

namespace Tests\Feature;

use App\Enums\DeviceAcquisitionSource;
use App\Enums\DeviceOwnership;
use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC ownership modem sewa/beli — Task 2: `InventoryService` jadi satu-satunya
 * penulis `inventory_serials.ownership`, `customer_devices.acquisition_source`,
 * dan baris `inventory_serial_sales` (lihat rancangan di
 * .superpowers/sdd/2026-10-09-ownership-modem-sewa-beli/).
 */
class InventoryServiceOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Customer $customer;

    private User $technician;

    private User $actor;

    private Item $modem;

    private FopTask $fopTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pop = Pop::create(['code' => 'OWN-TEST', 'pop_code' => 'OWT', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Ownership Test', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-OWN-001',
            'full_name' => 'Pelanggan Ownership Test',
            'primary_phone' => '0812340004',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $this->technician = User::factory()->create();
        $this->actor = User::factory()->create();

        $category = ItemCategory::create(['code' => 'modem_ont_own', 'name' => 'Modem/ONT Ownership Test', 'default_unit' => 'pcs']);
        $this->modem = Item::create([
            'code' => 'ONT-OWN-TEST', 'name' => 'Modem ONT Ownership Test', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);

        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-OWN-TEST-001',
            'task_date' => now(),
            'category' => 'PSB',
            'tugas' => 'Uji Ownership Modem',
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'issue' => 'Uji ownership',
            'status' => 'draft',
            'priority' => 'Medium',
        ]);
    }

    private function createIssuedSerial(string $serialNumber): InventorySerial
    {
        return InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => $serialNumber,
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);
    }

    #[Test]
    public function install_serial_dengan_ownership_customer_menulis_flag_di_sn_dan_customer_device(): void
    {
        $serial = $this->createIssuedSerial('SN-OWN-CUST-001');

        $service = app(InventoryService::class);
        $service->installSerial($serial, $this->customer, $this->fopTask, [$this->technician], $this->actor, true, DeviceOwnership::CUSTOMER);

        $this->assertSame(DeviceOwnership::CUSTOMER, $serial->refresh()->ownership);
        // `refresh()` di `Customer`, bukan di relasi `customerDevice` cache
        // lama — `installSerial()` sendiri (lihat `$customer->customerDevice?->brand`
        // sebelum baris `updateOrCreate`) sempat mengakses relasi ini duluan
        // sewaktu device belum ada, jadi ter-cache `null` di instance yang
        // sama persis dipakai test. `Customer::refresh()` membuang cache
        // relasi lama sebelum query ulang (bukan cuma refresh atribut).
        $this->assertSame(DeviceAcquisitionSource::BELI_GUDANG, $this->customer->refresh()->customerDevice->acquisition_source);
    }

    #[Test]
    public function install_serial_default_ownership_company_menulis_acquisition_source_sewa(): void
    {
        $serial = $this->createIssuedSerial('SN-OWN-COMP-001');

        $service = app(InventoryService::class);
        $service->installSerial($serial, $this->customer, $this->fopTask, [$this->technician], $this->actor);

        $this->assertSame(DeviceOwnership::COMPANY, $serial->refresh()->ownership);
        $this->assertSame(DeviceAcquisitionSource::SEWA, $this->customer->refresh()->customerDevice->acquisition_source);
    }

    #[Test]
    public function record_customer_owned_device_simpan_tanpa_sentuh_inventory_serials(): void
    {
        $device = app(InventoryService::class)->recordCustomerOwnedDevice($this->customer, [
            'brand' => 'TP-Link', 'model' => 'Archer C6', 'serial_number' => 'MANUAL-001',
        ], $this->actor);

        $this->assertSame(DeviceAcquisitionSource::BELI_SENDIRI, $device->acquisition_source);
        $this->assertSame('ONT', $device->device_type);
        $this->assertNull($device->device_retrieved_at);
        $this->assertDatabaseCount('inventory_serials', 0);
    }

    #[Test]
    public function record_serial_sale_sukses_simpan_baris_sale(): void
    {
        $serial = $this->createIssuedSerial('SN-OWN-SALE-001');

        $sale = app(InventoryService::class)->recordSerialSale($serial, $this->customer, $this->fopTask, 350000.0, $this->actor);

        $this->assertInstanceOf(InventorySerialSale::class, $sale);
        $this->assertSame($serial->id, $sale->inventory_serial_id);
        $this->assertSame($this->customer->id, $sale->customer_id);
        $this->assertSame($this->fopTask->id, $sale->fop_task_id);
        $this->assertEquals(350000.0, $sale->price);
        $this->assertSame($this->actor->id, $sale->created_by);
        $this->assertDatabaseCount('inventory_serial_sales', 1);
    }

    #[Test]
    public function record_serial_sale_tolak_harga_nol(): void
    {
        $serial = $this->createIssuedSerial('SN-OWN-SALE-002');

        $this->expectException(InvalidArgumentException::class);
        app(InventoryService::class)->recordSerialSale($serial, $this->customer, null, 0.0, $this->actor);
    }

    #[Test]
    public function record_serial_sale_tolak_harga_negatif(): void
    {
        $serial = $this->createIssuedSerial('SN-OWN-SALE-003');

        $this->expectException(InvalidArgumentException::class);
        app(InventoryService::class)->recordSerialSale($serial, $this->customer, null, -100.0, $this->actor);
    }

    /**
     * ADHOC-190 task 4 — Laporan Pemasangan memasang AP di step 5, ONT utama
     * baru di-install di step 6 (storeSpeedtest(), returnExistingSerial=true).
     * Retire SN lama wajib cuma menyentuh perangkat UTAMA: AP/STB
     * (`is_customer_extra_device`) yang sudah INSTALLED tidak boleh ikut
     * di-RETURN, sedangkan ONT lama tetap di-RETURN seperti biasa.
     */
    #[Test]
    public function install_serial_ganti_utama_tidak_meretur_perangkat_tambahan(): void
    {
        $apCategory = ItemCategory::create(['code' => 'ap_own_test', 'name' => 'AP Ownership Test', 'default_unit' => 'pcs', 'is_customer_extra_device' => true]);
        $apItem = Item::create([
            'code' => 'AP-OWN-TEST', 'name' => 'AP Ownership Test', 'unit' => 'pcs',
            'item_category_id' => $apCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);

        $service = app(InventoryService::class);

        $oldOnt = $this->createIssuedSerial('SN-OWN-ONT-OLD');
        $service->installSerial($oldOnt, $this->customer, $this->fopTask, [$this->technician], $this->actor);

        $ap = InventorySerial::create([
            'item_id' => $apItem->id,
            'serial_number' => 'SN-OWN-AP-001',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);
        $service->installSerial($ap, $this->customer, $this->fopTask, [$this->technician], $this->actor, false);

        $newOnt = $this->createIssuedSerial('SN-OWN-ONT-NEW');
        $service->installSerial($newOnt, $this->customer, $this->fopTask, [$this->technician], $this->actor, true);

        $this->assertSame(SerialStatus::INSTALLED, $ap->refresh()->status, 'AP tidak boleh di-retur saat ONT utama diganti');
        $this->assertSame($this->customer->id, $ap->customer_id);
        $this->assertSame(SerialStatus::RETURNED, $oldOnt->refresh()->status, 'ONT lama tetap di-retur seperti sebelumnya');
        $this->assertSame(SerialStatus::INSTALLED, $newOnt->refresh()->status);
    }
}
