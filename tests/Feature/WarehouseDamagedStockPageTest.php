<?php

namespace Tests\Feature;

use App\Enums\ItemCondition;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryReceiveService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Halaman "Modem Rusak" (ADHOC-108) — listing SN DAMAGED/QUARANTINE/SCRAPPED
 * atau condition=used_damaged. Lihat docs/plan/warehouse/rancangan-retur-ke-pusat-dan-modem-rusak.md.
 */
class WarehouseDamagedStockPageTest extends TestCase
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

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    #[Test]
    public function menampilkan_sn_rusak_dan_menyembunyikan_sn_sehat(): void
    {
        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $owner = User::factory()->create(['role_id' => $ownerRole->id]);
        $owner->roleScopes()->create(['role_id' => $ownerRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $pusat = Pop::create(['code' => 'DMG-PUSAT', 'pop_code' => 'DMP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Damaged Test', 'type' => 'pusat', 'status' => 'active']);
        $cat = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $modem = Item::create(['code' => 'DMG-MODEM', 'name' => 'Modem Damaged Test', 'item_category_id' => $cat->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        [$sehat] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $modem, ['DMG-SN-SEHAT'], 250000, $owner);
        [$rusak] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $modem, ['DMG-SN-RUSAK'], 250000, $owner);
        $rusak->update(['status' => SerialStatus::DAMAGED, 'condition' => ItemCondition::USED_DAMAGED]);

        $this->actingAs($owner)
            ->get(route('warehouse.damaged.index'))
            ->assertOk()
            ->assertSee('DMG-SN-RUSAK')
            ->assertDontSee('DMG-SN-SEHAT');

        $this->assertEquals(SerialStatus::AVAILABLE, $sehat->refresh()->status);
    }

    #[Test]
    public function halaman_butuh_izin_gudang(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.damaged.index'))->assertForbidden();
    }
}
