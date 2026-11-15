<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Enums\ScopeType;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Models\WarehousePopPic;
use App\Services\InventoryIssueService;
use App\Services\InventoryReassignService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi keluhan lapangan 2026-11-14: PIC Gudang yang juga teknisi tidak bisa
 * mengambil barang untuk dirinya sendiri (harus menunggu POP Admin), padahal
 * dia juga dapat task FOP. Keputusan user "Opsi B": PIC boleh issue ke diri
 * sendiri dari gudang cabang penunjukannya, transaksinya ditandai & dipantau
 * Pusat di Laporan Gudang. Role lain dan Reassign custody tetap ditolak.
 */
class PicGudangKeluarkanBarangUntukDiriSendiriTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function cabang(string $code): Pop
    {
        return Pop::create(['code' => $code, 'pop_code' => $code, 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang '.$code, 'type' => 'cabang', 'status' => 'active']);
    }

    private function scopedUser(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    private function picOf(Pop $pop): User
    {
        $pic = $this->scopedUser('pic_gudang', $pop);
        WarehousePopPic::create(['pop_id' => $pop->id, 'user_id' => $pic->id]);

        return $pic;
    }

    private function kabelDenganStok(Pop $pop, string $code, float $qty = 100): Item
    {
        $item = Item::create([
            'code' => $code, 'name' => 'Kabel '.$code, 'unit' => 'meter', 'tracking_type' => 'quantity',
            'item_category_id' => ItemCategory::where('code', 'kabel_dropcore')->firstOrFail()->id,
        ]);
        InventoryBalance::create(['pop_id' => $pop->id, 'item_id' => $item->id, 'lot_no' => '', 'qty' => $qty]);

        return $item;
    }

    #[Test]
    public function pic_boleh_keluarkan_barang_untuk_diri_sendiri_dari_gudang_cabangnya(): void
    {
        $cabang = $this->cabang('SLF1');
        $pic = $this->picOf($cabang);
        $item = $this->kabelDenganStok($cabang, 'SLF-K1');

        app(InventoryIssueService::class)->issue($cabang, $pic, [['item_id' => $item->id, 'qty' => 30]], $pic);

        $this->assertTrue(TechnicianCustody::where('technician_id', $pic->id)->where('item_id', $item->id)->where('qty_remaining', 30)->exists());
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => InventoryTransactionType::ISSUE->value,
            'to_technician_id' => $pic->id,
            'created_by' => $pic->id,
        ]);
    }

    #[Test]
    public function pic_tetap_ditolak_di_cabang_yang_bukan_penunjukannya(): void
    {
        $cabangPic = $this->cabang('SLF2');
        $cabangLain = $this->cabang('SLF3');
        $pic = $this->picOf($cabangPic);
        $item = $this->kabelDenganStok($cabangLain, 'SLF-K2');

        $this->expectException(InvalidArgumentException::class);

        app(InventoryIssueService::class)->issue($cabangLain, $pic, [['item_id' => $item->id, 'qty' => 5]], $pic);
    }

    /**
     * Kejadian 2026-11-14: user ber-role pic_gudang tapi belum punya baris
     * warehouse_pop_pics dapat toast "tidak bisa untuk diri sendiri" —
     * pesannya harus menunjuk ke penyebab aslinya (belum ditunjuk).
     */
    #[Test]
    public function pic_yang_belum_ditunjuk_dapat_pesan_belum_ditunjuk_bukan_diri_sendiri(): void
    {
        $cabang = $this->cabang('SLF7');
        $picBelumDitunjuk = $this->scopedUser('pic_gudang', $cabang);
        $item = $this->kabelDenganStok($cabang, 'SLF-K6');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('belum ditunjuk sebagai PIC Gudang');

        app(InventoryIssueService::class)->issue($cabang, $picBelumDitunjuk, [['item_id' => $item->id, 'qty' => 5]], $picBelumDitunjuk);
    }

    #[Test]
    public function teknisi_biasa_tetap_tidak_boleh_keluarkan_untuk_diri_sendiri(): void
    {
        $cabang = $this->cabang('SLF4');
        $teknisi = $this->scopedUser('teknisi', $cabang);
        $item = $this->kabelDenganStok($cabang, 'SLF-K3');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('diri sendiri');

        app(InventoryIssueService::class)->issue($cabang, $teknisi, [['item_id' => $item->id, 'qty' => 5]], $teknisi);
    }

    #[Test]
    public function pic_tetap_tidak_boleh_reassign_custody_ke_diri_sendiri(): void
    {
        $cabang = $this->cabang('SLF5');
        $pic = $this->picOf($cabang);
        $teknisi = $this->scopedUser('teknisi', $cabang);
        $item = $this->kabelDenganStok($cabang, 'SLF-K4', 0);
        $custody = TechnicianCustody::create([
            'technician_id' => $teknisi->id, 'issued_from_pop_id' => $cabang->id, 'item_id' => $item->id,
            'lot_no' => '', 'qty_remaining' => 10, 'status' => 'issued', 'issued_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('diri sendiri');

        app(InventoryReassignService::class)->transferCustodyToTechnician($custody, $pic, 'ambil', $pic);
    }

    #[Test]
    public function laporan_gudang_menandai_issue_untuk_diri_sendiri_dan_bukan_issue_biasa(): void
    {
        $cabang = $this->cabang('SLF6');
        $pic = $this->picOf($cabang);
        $teknisi = $this->scopedUser('teknisi', $cabang);
        $item = $this->kabelDenganStok($cabang, 'SLF-K5');
        $service = app(InventoryIssueService::class);

        $service->issue($cabang, $pic, [['item_id' => $item->id, 'qty' => 10]], $pic);
        $service->issue($cabang, $teknisi, [['item_id' => $item->id, 'qty' => 10]], $pic);

        $selfRef = InventoryTransaction::where('to_technician_id', $pic->id)->value('reference_number');
        $normalRef = InventoryTransaction::where('to_technician_id', $teknisi->id)->value('reference_number');

        $this->actingAs($this->loginAsAdmin())
            ->get(route('warehouse.reports.index', ['period' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Dikeluarkan untuk Diri Sendiri (1)')
            ->assertSee($selfRef)
            ->assertDontSee($normalRef);

        $this->actingAs($pic)
            ->get(route('warehouse.issues.show', $selfRef))
            ->assertOk()
            ->assertSee('Dikeluarkan untuk diri sendiri');
    }
}
