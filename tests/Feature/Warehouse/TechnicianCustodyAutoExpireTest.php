<?php

namespace Tests\Feature\Warehouse;

use App\Enums\CustodyStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InventoryIssueService;
use App\Services\TechnicianCustodyExpiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Auto-expire barang di tangan teknisi (laporan user 2026-11-05, gap #1 dari
 * 3 feature report) — custody qty, SN & Roll yang sudah GANTI HARI sejak
 * diambil AUTO-DIKREDIT ke stok Gudang Cabang, TANPA konfirmasi admin/teknisi
 * (keputusan user 2026-11-06 — gak masuk akal minta PIC/teknisi tap tombol
 * tengah malam). SN/Roll di sini TIDAK dipaksa "belum dicek fisik" — beda
 * dari barang balik DEACT/ganti modem yang genuinely sudah dipakai di
 * lapangan, kandidat di sini belum pernah naik dari ISSUED/IN_USE (belum
 * pernah dipakai buat kerjaan). Lihat docblock `TechnicianCustodyExpiryService`.
 */
class TechnicianCustodyAutoExpireTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private User $technician;

    private Item $itemQty;

    private Item $itemSerial;

    private Item $itemRoll;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->technician = User::factory()->create();

        $category = ItemCategory::create([
            'code' => 'auto_expire_cat', 'name' => 'Kategori Auto Expire', 'default_unit' => 'pcs',
            'equipment_class' => 'pasif', 'is_system' => false, 'is_active' => true,
        ]);
        $this->itemQty = Item::create([
            'code' => 'AUTO-EXPIRE-QTY', 'name' => 'Barang Qty Auto Expire', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'quantity',
        ]);
        $this->itemSerial = Item::create([
            'code' => 'AUTO-EXPIRE-SN', 'name' => 'Barang SN Auto Expire', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'serialized',
        ]);
        $this->itemRoll = Item::create([
            'code' => 'AUTO-EXPIRE-ROLL', 'name' => 'Kabel Roll Auto Expire', 'unit' => 'meter',
            'item_category_id' => $category->id, 'tracking_type' => 'roll',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function custodyIssuedAt(Carbon $when, float $qty = 10): TechnicianCustody
    {
        return TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id,
            'item_id' => $this->itemQty->id,
            'qty_remaining' => $qty,
            'status' => CustodyStatus::ISSUED,
            'issued_at' => $when,
        ]);
    }

    private function serialIssuedAt(Carbon $when, string $serialNumber): InventorySerial
    {
        $serial = InventorySerial::create([
            'item_id' => $this->itemSerial->id,
            'serial_number' => $serialNumber,
            'status' => SerialStatus::ISSUED,
            'issued_from_pop_id' => $this->cabang->id,
            'current_technician_id' => $this->technician->id,
        ]);

        Carbon::setTestNow($when);
        InventoryTransaction::create([
            'type' => InventoryTransactionType::ISSUE,
            'item_id' => $this->itemSerial->id,
            'serial_id' => $serial->id,
            'qty' => 1,
            'from_pop_id' => $this->cabang->id,
            'to_technician_id' => $this->technician->id,
        ]);
        Carbon::setTestNow();

        return $serial;
    }

    private function rollIssuedAt(Carbon $when, string $rollCode, float $lengthRemaining = 50): InventoryRoll
    {
        $roll = InventoryRoll::create([
            'item_id' => $this->itemRoll->id,
            'roll_code' => $rollCode,
            'length_total' => 100,
            'length_remaining' => $lengthRemaining,
            'status' => RollStatus::ISSUED,
            'issued_from_pop_id' => $this->cabang->id,
            'current_technician_id' => $this->technician->id,
        ]);

        Carbon::setTestNow($when);
        InventoryTransaction::create([
            'type' => InventoryTransactionType::ISSUE,
            'item_id' => $this->itemRoll->id,
            'roll_id' => $roll->id,
            'qty' => $lengthRemaining,
            'from_pop_id' => $this->cabang->id,
            'to_technician_id' => $this->technician->id,
        ]);
        Carbon::setTestNow();

        return $roll;
    }

    // --- POSITIF: ganti hari -> auto-return ke stok Cabang --------------

    #[Test]
    public function custody_qty_diambil_kemarin_otomatis_balik_ke_stok_cabang(): void
    {
        $custody = $this->custodyIssuedAt(now()->subDay(), qty: 7);

        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $custody->refresh();
        $this->assertNotNull($custody->expired_at);
        $this->assertSame(0.0, (float) $custody->qty_remaining);
        $this->assertSame(CustodyStatus::RETURNED, $custody->status);

        $balance = InventoryBalance::query()
            ->where('pop_id', $this->cabang->id)->where('item_id', $this->itemQty->id)->where('lot_no', '')
            ->first();
        $this->assertNotNull($balance);
        $this->assertSame(7.0, (float) $balance->qty);

        $ledger = InventoryTransaction::query()->where('item_id', $this->itemQty->id)
            ->where('type', InventoryTransactionType::RETURN->value)->latest('id')->first();
        $this->assertSame('auto_expired_presumptive', $ledger->reason);
    }

    #[Test]
    public function sn_diambil_kemarin_otomatis_balik_ke_gudang_cabang_dan_langsung_bisa_diissue_ulang(): void
    {
        $serial = $this->serialIssuedAt(now()->subDay(), 'SN-AUTO-RETURN-001');

        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $serial->refresh();
        $this->assertNotNull($serial->expired_at);
        $this->assertSame(SerialStatus::AVAILABLE, $serial->status);
        $this->assertSame($this->cabang->id, $serial->current_pop_id);
        $this->assertNull($serial->current_technician_id);

        // Belum pernah dipakai di lapangan (gak kayak balik DEACT/ganti
        // modem) — gak butuh gate "Sudah Dicek", langsung reusable.
        $this->assertTrue($serial->isClearedForIssue());

        $teknisiBaru = User::factory()->create();
        $admin = User::factory()->create();
        app(InventoryIssueService::class)->issue($this->cabang, $teknisiBaru, [
            ['item_id' => $this->itemSerial->id, 'serial_numbers' => [$serial->serial_number]],
        ], $admin);

        $serial->refresh();
        $this->assertSame(SerialStatus::ISSUED, $serial->status);
        $this->assertSame($teknisiBaru->id, $serial->current_technician_id);
        $this->assertNull($serial->expired_at);
    }

    #[Test]
    public function roll_diambil_kemarin_otomatis_balik_ke_stok_cabang(): void
    {
        $roll = $this->rollIssuedAt(now()->subDay(), 'ROLL-AUTO-RETURN-001', lengthRemaining: 40);

        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $roll->refresh();
        $this->assertNotNull($roll->expired_at);
        $this->assertSame(RollStatus::AVAILABLE, $roll->status);
        $this->assertSame($this->cabang->id, $roll->current_pop_id);
        $this->assertNull($roll->current_technician_id);
        $this->assertSame(40.0, (float) $roll->length_remaining);
    }

    /** "24 jam atau ganti hari" — pemicunya ganti hari, bukan genap 24 jam. */
    #[Test]
    public function custody_diambil_menjelang_tengah_malam_tetap_auto_return_begitu_ganti_hari(): void
    {
        $custody = $this->custodyIssuedAt(now()->subDay()->setTime(23, 50));

        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $this->assertSame(0.0, (float) $custody->refresh()->qty_remaining);
    }

    // --- NEGATIF: masih hari yang sama -> TIDAK disentuh ----------------

    #[Test]
    public function custody_qty_diambil_hari_ini_tidak_disentuh(): void
    {
        $custody = $this->custodyIssuedAt(now());

        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $custody->refresh();
        $this->assertNull($custody->expired_at);
        $this->assertSame(10.0, (float) $custody->qty_remaining);
        $this->assertSame(CustodyStatus::ISSUED, $custody->status);
    }

    #[Test]
    public function sn_diambil_hari_ini_tidak_disentuh(): void
    {
        $serial = $this->serialIssuedAt(now(), 'SN-AUTO-RETURN-TODAY');

        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $serial->refresh();
        $this->assertNull($serial->expired_at);
        $this->assertSame(SerialStatus::ISSUED, $serial->status);
    }

    #[Test]
    public function roll_diambil_hari_ini_tidak_disentuh(): void
    {
        $roll = $this->rollIssuedAt(now(), 'ROLL-AUTO-RETURN-TODAY');

        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $roll->refresh();
        $this->assertNull($roll->expired_at);
        $this->assertSame(RollStatus::ISSUED, $roll->status);
    }

    #[Test]
    public function custody_yang_sudah_habis_dipakai_tidak_ikut_diproses(): void
    {
        $custody = $this->custodyIssuedAt(now()->subDay());
        $custody->update(['qty_remaining' => 0, 'status' => CustodyStatus::CONSUMED]);

        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $this->assertNull($custody->refresh()->expired_at);
    }

    #[Test]
    public function sn_yang_sudah_terpasang_ke_pelanggan_tidak_ikut_diproses(): void
    {
        $serial = $this->serialIssuedAt(now()->subDay(), 'SN-AUTO-RETURN-INSTALLED');
        $serial->update(['status' => SerialStatus::INSTALLED]);

        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $this->assertNull($serial->refresh()->expired_at);
    }

    #[Test]
    public function roll_yang_sudah_habis_dipotong_tidak_ikut_diproses(): void
    {
        $roll = $this->rollIssuedAt(now()->subDay(), 'ROLL-AUTO-RETURN-DEPLETED', lengthRemaining: 0);
        $roll->update(['status' => RollStatus::DEPLETED]);

        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $this->assertNull($roll->refresh()->expired_at);
    }

    // --- IDEMPOTEN: jalan dua kali aman, gak dobel kredit ---------------

    #[Test]
    public function menjalankan_expire_dua_kali_tidak_mengkredit_dobel(): void
    {
        $custody = $this->custodyIssuedAt(now()->subDay(), qty: 5);

        app(TechnicianCustodyExpiryService::class)->expireOverdue();
        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $balance = InventoryBalance::query()
            ->where('pop_id', $this->cabang->id)->where('item_id', $this->itemQty->id)->where('lot_no', '')
            ->first();
        $this->assertSame(5.0, (float) $balance->qty);
    }

    #[Test]
    public function sn_yang_sudah_balik_tidak_ikut_diproses_lagi_di_run_kedua(): void
    {
        $serial = $this->serialIssuedAt(now()->subDay(), 'SN-AUTO-RETURN-IDEMPOTEN');

        app(TechnicianCustodyExpiryService::class)->expireOverdue();
        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $returnCount = InventoryTransaction::query()
            ->where('serial_id', $serial->id)
            ->where('type', InventoryTransactionType::RETURN->value)
            ->count();
        $this->assertSame(1, $returnCount);
    }

    #[Test]
    public function roll_yang_sudah_balik_tidak_ikut_diproses_lagi_di_run_kedua(): void
    {
        $roll = $this->rollIssuedAt(now()->subDay(), 'ROLL-AUTO-RETURN-IDEMPOTEN');

        app(TechnicianCustodyExpiryService::class)->expireOverdue();
        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        $returnCount = InventoryTransaction::query()
            ->where('roll_id', $roll->id)
            ->where('type', InventoryTransactionType::RETURN->value)
            ->count();
        $this->assertSame(1, $returnCount);
    }

    // --- ISOLASI: satu baris gagal gak nggagalin baris lain di batch ----

    #[Test]
    public function custody_yang_gagal_direturn_tidak_menggagalkan_custody_lain_di_batch_yang_sama(): void
    {
        $miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id]);

        $gagal = TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $miniPop->id,
            'item_id' => $this->itemQty->id,
            'qty_remaining' => 3,
            'status' => CustodyStatus::ISSUED,
            'issued_at' => now()->subDay(),
        ]);
        $berhasil = $this->custodyIssuedAt(now()->subDay(), qty: 9);

        app(TechnicianCustodyExpiryService::class)->expireOverdue();

        // Gagal (cabang bukan warehouse) — transaksi di-rollback utuh,
        // expired_at IKUT balik null, bukan nyangkut separuh jalan.
        $gagal->refresh();
        $this->assertNull($gagal->expired_at);
        $this->assertSame(3.0, (float) $gagal->qty_remaining);

        // Baris lain di batch yang sama tetap sukses diproses.
        $berhasil->refresh();
        $this->assertNotNull($berhasil->expired_at);
        $this->assertSame(0.0, (float) $berhasil->qty_remaining);
    }
}
