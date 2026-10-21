<?php

namespace Tests\Feature;

use App\Enums\InfrastructureLocationKind;
use App\Enums\SerialStatus;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Posisi Pemasangan" di Master Barang (§10.4 poin 3): kecocokan SKU ↔
 * posisi akhir. Aset perusahaan (Switch/SFP) baru boleh netap kalau posisinya
 * dinyatakan eksplisit di sini.
 */
class MasterItemInfrastructureLocationKindTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $code, string $categoryCode, string $ownership, array $overrides = []): array
    {
        return array_merge([
            'code' => $code, 'name' => "Barang {$code}", 'item_category_id' => ItemCategory::where('code', $categoryCode)->firstOrFail()->id,
            'unit' => 'pcs', 'is_active' => 1, 'tracking_type' => 'serialized', 'auto_generate_serial' => 0,
            'ownership_mode' => $ownership,
        ], $overrides);
    }

    #[Test]
    public function kartu_olt_disimpan_dengan_posisi_slot(): void
    {
        $this->actingAs($this->owner)->post(route('master.items.store'), $this->payload('CARD-A', 'olt_module', 'infrastructure_asset', ['infrastructure_location_kind' => 'slot']))
            ->assertSessionHasNoErrors();

        $this->assertSame(InfrastructureLocationKind::SLOT, Item::where('code', 'CARD-A')->firstOrFail()->infrastructure_location_kind);
    }

    #[Test]
    public function sfp_aset_perusahaan_boleh_menyatakan_posisi_port(): void
    {
        $this->actingAs($this->owner)->post(route('master.items.store'), $this->payload('SFP-A', 'sfp_transceiver', 'company_asset', ['infrastructure_location_kind' => 'port']))
            ->assertSessionHasNoErrors();

        $this->assertSame(InfrastructureLocationKind::PORT, Item::where('code', 'SFP-A')->firstOrFail()->infrastructure_location_kind);
    }

    #[Test]
    public function barang_pelanggan_tidak_boleh_punya_posisi_infrastruktur(): void
    {
        $this->actingAs($this->owner)->post(route('master.items.store'), $this->payload('MDM-A', 'modem_ont', 'installable', ['infrastructure_location_kind' => 'mini_pop']))
            ->assertSessionHasErrors('infrastructure_location_kind');
    }

    #[Test]
    public function posisi_tidak_bisa_diganti_selama_ada_sn_terpasang(): void
    {
        $item = Item::create(['code' => 'RACK-A', 'name' => 'Rack', 'unit' => 'pcs', 'item_category_id' => ItemCategory::where('code', 'olt_module')->value('id'),
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset', 'infrastructure_location_kind' => 'mini_pop']);
        InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'SN-RACK', 'status' => SerialStatus::DEPLOYED->value,
            'current_mini_pop_id' => Pop::factory()->create(['type' => 'mini_pop'])->id]);

        $this->actingAs($this->owner)->put(route('master.items.update', $item), [
            'code' => 'RACK-A', 'name' => 'Rack', 'item_category_id' => $item->item_category_id, 'unit' => 'pcs', 'is_active' => 1,
            'tracking_type' => 'serialized', 'auto_generate_serial' => 0, 'ownership_mode' => 'infrastructure_asset',
            'infrastructure_location_kind' => 'slot',
        ])->assertSessionHasErrors('infrastructure_location_kind');

        $this->assertSame(InfrastructureLocationKind::MINI_POP, $item->fresh()->infrastructure_location_kind);
    }

    #[Test]
    public function form_tambah_menampilkan_pilihan_posisi_pemasangan(): void
    {
        $this->actingAs($this->owner)->get(route('master.items.create'))
            ->assertOk()
            ->assertSee('name="infrastructure_location_kind"', false)
            ->assertSee('Langsung di Mini POP');
    }
}
