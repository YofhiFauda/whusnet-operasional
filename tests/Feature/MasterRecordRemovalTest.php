<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Models\WorkTool;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hapus-atau-nonaktifkan data master. Yang dijaga: record tanpa data terkait
 * benar-benar hilang dari DB, record yang masih direferensikan cuma
 * dinonaktifkan (histori tetap utuh), dan tombol hapus tunduk pada
 * `{fitur}.delete`.
 */
class MasterRecordRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function asOwner(): static
    {
        $this->loginAsAdmin();

        return $this;
    }

    public function test_pop_tanpa_data_terkait_dihapus_permanen(): void
    {
        $pop = Pop::factory()->create();

        $this->asOwner()->delete(route('master.pop.destroy', $pop))->assertRedirect(route('master.pop.index'));

        $this->assertDatabaseMissing('pops', ['id' => $pop->id]);
    }

    public function test_pop_yang_punya_user_hanya_dinonaktifkan(): void
    {
        $pop = Pop::factory()->create(['status' => 'active']);
        $pop->users()->attach(User::factory()->create()->id);

        $this->asOwner()->delete(route('master.pop.destroy', $pop))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('pops', ['id' => $pop->id, 'status' => 'inactive']);
    }

    public function test_pop_yang_punya_child_pop_hanya_dinonaktifkan(): void
    {
        $induk = Pop::factory()->create(['status' => 'active']);
        Pop::factory()->create(['parent_id' => $induk->id]);

        $this->asOwner()->delete(route('master.pop.destroy', $induk));

        $this->assertDatabaseHas('pops', ['id' => $induk->id, 'status' => 'inactive']);
    }

    public function test_item_tanpa_data_terkait_dihapus_permanen(): void
    {
        $category = ItemCategory::create(['code' => 'TEST-CAT-DEL', 'name' => 'Kategori Hapus', 'default_unit' => 'pcs', 'is_active' => true]);
        $item = Item::create([
            'code' => 'TEST-ITEM-DEL',
            'name' => 'Item Tes Hapus',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'tracking_type' => 'quantity',
        ]);

        $this->asOwner()->delete(route('master.items.destroy', $item));

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_kategori_barang_yang_masih_dipakai_barang_hanya_dinonaktifkan(): void
    {
        $category = ItemCategory::create(['code' => 'TEST-CAT', 'name' => 'Kategori Tes', 'default_unit' => 'pcs', 'is_active' => true]);
        Item::create([
            'code' => 'TEST-ITEM-CAT',
            'name' => 'Item Penghuni Kategori',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'tracking_type' => 'quantity',
        ]);

        $this->asOwner()->delete(route('master.item-categories.destroy', $category));

        $this->assertDatabaseHas('item_categories', ['id' => $category->id, 'is_active' => false]);
    }

    public function test_kategori_lainnya_tidak_bisa_dihapus(): void
    {
        $lainnya = ItemCategory::firstOrCreate(['code' => ItemCategory::CODE_LAINNYA], ['name' => 'Lainnya', 'default_unit' => 'pcs', 'is_active' => true]);

        $this->asOwner()->delete(route('master.item-categories.destroy', $lainnya))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('item_categories', ['id' => $lainnya->id, 'is_active' => true]);
    }

    public function test_alat_kerja_tanpa_data_terkait_dihapus_permanen(): void
    {
        $tool = WorkTool::create(['code' => 'TEST-TOOL', 'name' => 'Tang Tes', 'is_active' => true, 'sort_order' => 1]);

        $this->asOwner()->delete(route('master.work-tools.destroy', $tool));

        $this->assertDatabaseMissing('work_tools', ['id' => $tool->id]);
    }

    public function test_kategori_issue_tanpa_data_terkait_dihapus_permanen(): void
    {
        $category = TicketIssueCategory::create([
            'name' => 'Kategori Tes Hapus',
            'default_priority' => 'High',
            'is_active' => true,
        ]);

        $this->asOwner()->delete(route('master.ticket-issue-categories.destroy', $category));

        $this->assertDatabaseMissing('ticket_issue_categories', ['id' => $category->id]);
    }

    public function test_user_tanpa_izin_hapus_ditolak(): void
    {
        $item = Item::create([
            'code' => 'TEST-ITEM-403',
            'name' => 'Item Tanpa Izin',
            'item_category_id' => ItemCategory::create(['code' => 'TEST-CAT-403', 'name' => 'Kategori 403', 'default_unit' => 'pcs', 'is_active' => true])->id,
            'unit' => 'pcs',
            'tracking_type' => 'quantity',
        ]);

        $teknisi = User::factory()->create([
            'role_id' => Role::where('code', 'teknisi')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->actingAs($teknisi)->delete(route('master.items.destroy', $item))->assertForbidden();

        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }
}
