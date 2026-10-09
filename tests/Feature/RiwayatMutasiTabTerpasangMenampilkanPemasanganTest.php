<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\InventoryTransactionType;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi 2026-09-28: tab "Terpasang di Pelanggan" di Riwayat Mutasi selalu
 * kosong walau data pemasangan ada. Baris INSTALL (SN keluar dari tangan
 * teknisi ke pelanggan) tidak punya from_pop_id/to_pop_id, sementara scope
 * halaman cuma meloloskan baris yang punya POP gudang. Sekarang scope baris
 * INSTALL ikut POP pekerjaannya (fop_tasks.pop_id) — tetap per cabang.
 */
class RiwayatMutasiTabTerpasangMenampilkanPemasanganTest extends TestCase
{
    use RefreshDatabase;

    private Pop $jetis;

    private Pop $miniJetis;

    private Pop $sandya;

    private Item $modem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);

        $this->jetis = Pop::create(['code' => 'RMT-JETIS', 'pop_code' => 'RMTJ', 'registration_prefix' => 'C', 'cid_prefix' => 'C', 'name' => 'Cabang Jetis', 'type' => 'cabang', 'status' => 'active']);
        $this->miniJetis = Pop::create(['code' => 'RMT-MINI', 'pop_code' => 'RMTM', 'registration_prefix' => 'C', 'cid_prefix' => 'C', 'name' => 'Mini Jetis', 'type' => 'mini_pop', 'status' => 'active', 'parent_id' => $this->jetis->id]);
        $this->sandya = Pop::create(['code' => 'RMT-SANDYA', 'pop_code' => 'RMTS', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Sandya', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $this->modem = Item::create(['code' => 'RMT-MODEM', 'name' => 'Modem Riwayat', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
    }

    private function installAt(Pop $taskPop, string $reference): InventoryTransaction
    {
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-'.$reference,
            'task_date' => now(),
            'category' => TaskType::PEMASANGAN->value,
            'tugas' => 'Pemasangan '.$reference,
            'pop_id' => $taskPop->id,
            'issue' => 'Pemasangan baru',
            'status' => TaskStatus::SELESAI->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        return InventoryTransaction::create([
            'type' => InventoryTransactionType::INSTALL,
            'reference_number' => $reference,
            // Nama barang unik per pemasangan — halaman menampilkan nama
            // barang/SN, bukan nomor referensi.
            'item_id' => Item::create(['code' => $reference, 'name' => $reference, 'item_category_id' => $this->modem->item_category_id, 'unit' => 'unit', 'tracking_type' => 'serialized'])->id,
            'qty' => 1,
            'fop_task_id' => $fopTask->id,
            'notes' => 'Pemasangan uji',
        ]);
    }

    private function user(string $roleCode, ?Pop $selectedPop = null): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        if ($selectedPop) {
            $scope = $user->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP->value]);
            $scope->targets()->create(['pop_id' => $selectedPop->id]);
            app(EffectiveAccessService::class)->clearCache($user);
        }

        return $user;
    }

    #[Test]
    public function tab_terpasang_menampilkan_pemasangan_yang_sudah_ada(): void
    {
        $this->installAt($this->jetis, 'INS-RMT-0001');

        $this->actingAs($this->user('owner'))
            ->get(route('warehouse.history.index', ['type' => InventoryTransactionType::INSTALL->value]))
            ->assertOk()
            ->assertSee('INS-RMT-0001');
    }

    #[Test]
    public function filter_gudang_cabang_ikut_menampilkan_pemasangan_di_mini_pop_bawahannya(): void
    {
        $this->installAt($this->miniJetis, 'INS-RMT-MINI');
        $this->installAt($this->sandya, 'INS-RMT-SANDYA');

        $this->actingAs($this->user('owner'))
            ->get(route('warehouse.history.index', ['type' => InventoryTransactionType::INSTALL->value, 'pop_id' => $this->jetis->id]))
            ->assertOk()
            ->assertSee('INS-RMT-MINI')
            ->assertDontSee('INS-RMT-SANDYA');
    }

    #[Test]
    public function admin_cabang_cuma_melihat_pemasangan_di_cabangnya(): void
    {
        $this->installAt($this->jetis, 'INS-RMT-JETIS');
        $this->installAt($this->sandya, 'INS-RMT-SANDYA');

        $this->actingAs($this->user('pop_admin', $this->jetis))
            ->get(route('warehouse.history.index', ['type' => InventoryTransactionType::INSTALL->value]))
            ->assertOk()
            ->assertSee('INS-RMT-JETIS')
            ->assertDontSee('INS-RMT-SANDYA');
    }
}
