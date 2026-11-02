<?php

namespace Tests\Feature\Warehouse;

use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Pintu "Koreksi Stok" menggantikan 4 halaman koreksi yang membingungkan
 * (Stock Opname, Opname SN/Roll, Penyesuaian Stok, Tandai Status SN).
 * Link/bookmark lama wajib tetap sampai, dengan data terisi.
 */
class WarehouseAdjustmentHubTest extends StockBoardTestCase
{
    #[Test]
    public function halaman_pintu_menampilkan_dua_kartu_untuk_pemegang_permission(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.adjustments.index'))
            ->assertOk()
            ->assertSee('Cek &amp; cocokkan stok', false)
            ->assertSee('Ada barang bermasalah')
            ->assertSee(route('warehouse.adjustments.check.create'), false)
            ->assertSee(route('warehouse.adjustments.problem.create'), false);
    }

    #[Test]
    public function halaman_pintu_ditolak_tanpa_permission(): void
    {
        $sales = User::factory()->create(['role_id' => Role::where('code', 'sales')->firstOrFail()->id]);

        $this->actingAs($sales)->get(route('warehouse.adjustments.index'))->assertForbidden();
    }

    #[Test]
    public function link_lama_opname_create_redirect_ke_check_dengan_query(): void
    {
        $query = ['pop_id' => $this->pusat->id, 'item_id' => 7, 'lot_no' => 'L1'];

        $this->actingAs($this->owner)->get(route('warehouse.adjustments.opname.create', $query))
            ->assertRedirect(route('warehouse.adjustments.check.create', $query));
    }

    #[Test]
    public function link_lama_unit_opname_redirect_ke_check(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.adjustments.unit-opname.create'))
            ->assertRedirect(route('warehouse.adjustments.check.create'));
    }

    #[Test]
    public function link_lama_balance_create_redirect_ke_problem_dengan_query(): void
    {
        $query = ['pop_id' => $this->pusat->id, 'item_id' => 7];

        $this->actingAs($this->owner)->get(route('warehouse.adjustments.balance.create', $query))
            ->assertRedirect(route('warehouse.adjustments.problem.create', $query));
    }

    #[Test]
    public function header_gudang_hanya_punya_satu_link_koreksi_stok(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.stock.index'))
            ->assertOk()
            ->assertSee(route('warehouse.adjustments.index'), false)
            ->assertDontSee(route('warehouse.adjustments.balance.create'), false)
            ->assertDontSee(route('warehouse.adjustments.opname.create'), false);
    }

    #[Test]
    public function dashboard_gudang_mengarah_ke_form_cek_stok(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.index'))
            ->assertOk()
            ->assertSee(route('warehouse.adjustments.check.create'), false)
            ->assertDontSee(route('warehouse.adjustments.opname.create'), false);
    }
}
