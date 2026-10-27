<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Exceptions\InsufficientCustodyException;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Barang (SN/roll/custody QUANTITY) yang diissue dari Cabang A gak boleh
 * dipakai (install/consume) untuk Task di Cabang B — laporan user: "modem
 * Jetis dapat digunakan Sandya, seharusnya itu tidak bisa". Sebelum ini
 * `installSerial()`/`consumeFromCustody()`/`consumeFromRoll()` cuma mengecek
 * custody (siapa pegang), gak pernah mengecek cabang asal vs cabang task.
 */
class InventoryServiceCrossBranchGuardTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabangJetis;

    private Pop $cabangSandya;

    private Pop $miniPopJetis;

    private User $technician;

    private User $actor;

    private Item $modem;

    private Item $kabel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabangJetis = Pop::create(['code' => 'JETIS', 'pop_code' => 'JTS', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Jetis', 'type' => 'cabang', 'status' => 'active']);
        $this->cabangSandya = Pop::create(['code' => 'SANDYA', 'pop_code' => 'SDY', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Sandya', 'type' => 'cabang', 'status' => 'active']);
        $this->miniPopJetis = Pop::create(['code' => 'JETIS-MINI', 'pop_code' => 'JTM', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Mini POP Jetis', 'type' => 'mini_pop', 'parent_id' => $this->cabangJetis->id, 'status' => 'active']);

        $this->technician = User::factory()->create();
        $this->actor = User::factory()->create();

        $category = ItemCategory::where('code', 'media_converter')->first()
            ?? ItemCategory::create(['code' => 'media_converter', 'name' => 'Media Converter', 'default_unit' => 'pcs']);
        $this->modem = Item::create(['code' => 'XBRANCH-MODEM', 'name' => 'Modem Lintas Cabang Test', 'unit' => 'pcs', 'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        $this->kabel = Item::create(['code' => 'XBRANCH-KABEL', 'name' => 'Kabel Lintas Cabang Test', 'unit' => 'meter', 'item_category_id' => $category->id, 'tracking_type' => 'quantity']);
    }

    private function customerAt(Pop $pop): Customer
    {
        return Customer::create([
            'customer_code' => 'TEST-XBR-'.$pop->id,
            'full_name' => 'Pelanggan Lintas Cabang Test',
            'primary_phone' => '0812340005',
            'status' => 'active',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);
    }

    private function fopTaskAt(?Pop $pop): FopTask
    {
        return FopTask::create([
            'task_number' => 'TFOP-XBR-'.($pop?->id ?? 'null'),
            'task_date' => now(),
            'category' => 'PSB',
            'tugas' => 'Uji Lintas Cabang',
            'pop_id' => $pop?->id,
            'issue' => 'Uji',
            'status' => 'draft',
            'priority' => 'Medium',
        ]);
    }

    #[Test]
    public function install_serial_menolak_sn_dari_cabang_lain(): void
    {
        $serial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-XBR-001',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangJetis->id,
        ]);

        $fopTaskSandya = $this->fopTaskAt($this->cabangSandya);
        $customerSandya = $this->customerAt($this->cabangSandya);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryService::class)->installSerial($serial, $customerSandya, $fopTaskSandya, [$this->technician], $this->actor);
    }

    #[Test]
    public function install_serial_lolos_kalau_cabang_sama(): void
    {
        $serial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-XBR-002',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangJetis->id,
        ]);

        $fopTaskJetis = $this->fopTaskAt($this->cabangJetis);
        $customerJetis = $this->customerAt($this->cabangJetis);

        app(InventoryService::class)->installSerial($serial, $customerJetis, $fopTaskJetis, [$this->technician], $this->actor);

        $this->assertSame(SerialStatus::INSTALLED, $serial->refresh()->status);
    }

    #[Test]
    public function install_serial_lolos_kalau_task_di_mini_pop_bawah_cabang_asal(): void
    {
        $serial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-XBR-003',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangJetis->id,
        ]);

        $fopTaskMiniJetis = $this->fopTaskAt($this->miniPopJetis);
        $customerMiniJetis = $this->customerAt($this->miniPopJetis);

        app(InventoryService::class)->installSerial($serial, $customerMiniJetis, $fopTaskMiniJetis, [$this->technician], $this->actor);

        $this->assertSame(SerialStatus::INSTALLED, $serial->refresh()->status);
    }

    #[Test]
    public function install_serial_lolos_kalau_fop_task_belum_punya_pop_id(): void
    {
        $serial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-XBR-004',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangJetis->id,
        ]);

        $fopTaskTanpaPop = $this->fopTaskAt(null);
        $customerJetis = $this->customerAt($this->cabangJetis);

        app(InventoryService::class)->installSerial($serial, $customerJetis, $fopTaskTanpaPop, [$this->technician], $this->actor);

        $this->assertSame(SerialStatus::INSTALLED, $serial->refresh()->status);
    }

    #[Test]
    public function consume_from_custody_skip_lot_dari_cabang_lain(): void
    {
        TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangSandya->id,
            'item_id' => $this->kabel->id,
            'qty_remaining' => 50,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $fopTaskJetis = $this->fopTaskAt($this->cabangJetis);

        $this->expectException(InsufficientCustodyException::class);

        app(InventoryService::class)->consumeFromCustody([$this->technician], $this->kabel, 10, $fopTaskJetis, null, $this->actor);
    }

    #[Test]
    public function consume_from_custody_pakai_lot_cabang_sama_walau_ada_lot_cabang_lain(): void
    {
        TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangSandya->id,
            'item_id' => $this->kabel->id,
            'qty_remaining' => 50,
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangJetis->id,
            'item_id' => $this->kabel->id,
            'qty_remaining' => 30,
            'status' => 'issued',
            'issued_at' => now()->addMinute(),
        ]);

        $fopTaskJetis = $this->fopTaskAt($this->cabangJetis);

        $consumed = app(InventoryService::class)->consumeFromCustody([$this->technician], $this->kabel, 10, $fopTaskJetis, null, $this->actor);

        $this->assertCount(1, $consumed);
        $this->assertEquals(10, $consumed[0]['qty']);
    }

    #[Test]
    public function consume_from_roll_menolak_roll_dari_cabang_lain(): void
    {
        $roll = InventoryRoll::create([
            'item_id' => $this->kabel->id,
            'roll_code' => 'ROLL-XBR-001',
            'status' => 'issued',
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangSandya->id,
            'length_total' => 100,
            'length_remaining' => 100,
        ]);

        $fopTaskJetis = $this->fopTaskAt($this->cabangJetis);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryService::class)->consumeFromRoll($roll, 10, [$this->technician], $fopTaskJetis, null, $this->actor);
    }
}
