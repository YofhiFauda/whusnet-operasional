<?php

namespace Tests\Feature;

use App\Enums\CReqCategory;
use App\Enums\InventoryTransactionType;
use App\Enums\MaterialKind;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskCreqDetail;
use App\Models\TaskMaterial;
use App\Models\User;
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

class TaskUsageBadgeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pop;

    private Item $kabelItem;

    private Item $modemItem;

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

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);

        $this->pop = Pop::create([
            'code' => 'POP-TST',
            'pop_code' => 'TST',
            'name' => 'Cabang Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->kabelItem = Item::create([
            'code' => 'DC-1C',
            'name' => 'Dropcore 1 Core',
            'unit' => 'meter',
            'item_category_id' => 1,
            'tracking_type' => 'roll',
            'is_active' => true,
        ]);

        $this->modemItem = Item::create([
            'code' => 'ONT-HW',
            'name' => 'Modem Huawei',
            'unit' => 'unit',
            'item_category_id' => 1,
            'tracking_type' => 'serialized',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function fop_task_resolve_usage_badge_menangani_berbagai_tipe_dan_detail(): void
    {
        // 1. PSB
        $psbTask = new FopTask(['category' => TaskType::PEMASANGAN]);
        $this->assertEquals([
            'type' => 'PSB',
            'detail' => null,
            'label' => 'PSB',
        ], $psbTask->resolveUsageBadge());

        // 2. MTN Biasa
        $mtnTask = new FopTask(['category' => TaskType::MAINTENANCE, 'tugas' => 'Perbaikan Redaman']);
        $this->assertEquals([
            'type' => 'MTN',
            'detail' => null,
            'label' => 'MTN',
        ], $mtnTask->resolveUsageBadge());

        // 3. MTN Ganti Modem (via flag isModemInstall)
        $this->assertEquals([
            'type' => 'MTN',
            'detail' => 'Ganti Modem',
            'label' => 'MTN — Ganti Modem',
        ], $mtnTask->resolveUsageBadge(isModemInstall: true));

        // 4. MTN Ganti Modem (via keyword judul tugas)
        $mtnGantiModem = new FopTask(['category' => TaskType::MAINTENANCE, 'tugas' => 'Maintenance ganti modem pelanggan']);
        $this->assertEquals([
            'type' => 'MTN',
            'detail' => 'Ganti Modem',
            'label' => 'MTN — Ganti Modem',
        ], $mtnGantiModem->resolveUsageBadge());

        // 5. C-REQ via keyword judul tugas (tanpa creqDetail)
        $creqPindah = new FopTask(['category' => TaskType::CREQ, 'tugas' => 'Permintaan pindah lokasi pelanggan']);
        $this->assertEquals([
            'type' => 'C-REQ',
            'detail' => 'Pindah Lokasi',
            'label' => 'C-REQ — Pindah Lokasi',
        ], $creqPindah->resolveUsageBadge());

        $creqMigrasi = new FopTask(['category' => TaskType::CREQ, 'tugas' => 'Migrasi paket & ont']);
        $this->assertEquals([
            'type' => 'C-REQ',
            'detail' => 'Migrasi',
            'label' => 'C-REQ — Migrasi',
        ], $creqMigrasi->resolveUsageBadge());

        // 6. Null task fallback
        $this->assertEquals([
            'type' => 'PSB',
            'detail' => null,
            'label' => 'PSB',
        ], FopTask::resolveUsageBadgeForTask(null));
    }

    #[Test]
    public function fop_task_resolve_usage_badge_membaca_relasi_creq_detail(): void
    {
        $task = Task::create([
            'task_number' => 'TSK-MIGRASI-01',
            'task_type' => TaskType::CREQ->value,
            'title' => 'C-REQ Migrasi Test',
            'pop_id' => $this->pop->id,
            'created_by' => $this->owner->id,
            'status' => 'selesai',
        ]);

        TaskCreqDetail::create([
            'task_id' => $task->id,
            'category' => CReqCategory::MIGRASI->value,
            'verification_status' => 'pending',
            'is_billable' => false,
        ]);

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-CREQ-01',
            'task_id' => $task->id,
            'category' => TaskType::CREQ->value,
            'tugas' => 'Customer Request',
            'pop_id' => $this->pop->id,
            'status' => 'selesai',
        ]);

        $fopTask->load('task.creqDetail');

        $badge = $fopTask->resolveUsageBadge();

        $this->assertEquals('C-REQ', $badge['type']);
        $this->assertEquals('Migrasi', $badge['detail']);
        $this->assertEquals('C-REQ — Migrasi', $badge['label']);
    }

    #[Test]
    public function halaman_laporan_pemakaian_merender_badge_tipe_dan_detail(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->pop->id, 'full_name' => 'Budi Sukses']);

        $techTask = Task::create([
            'task_number' => 'TSK-PINDAH-01',
            'task_type' => TaskType::CREQ->value,
            'title' => 'C-REQ Pindah Test',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'created_by' => $this->owner->id,
            'status' => 'selesai',
        ]);

        TaskCreqDetail::create([
            'task_id' => $techTask->id,
            'category' => CReqCategory::PINDAH_LOKASI->value,
            'verification_status' => 'pending',
            'is_billable' => false,
        ]);

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-001',
            'task_id' => $techTask->id,
            'category' => TaskType::CREQ->value,
            'tugas' => 'Customer Request Pindah',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'status' => 'selesai',
        ]);

        TaskMaterial::create([
            'fop_task_id' => $fopTask->id,
            'customer_id' => $customer->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_id' => $this->kabelItem->id,
            'item_type' => 'kabel',
            'item_name' => $this->kabelItem->name,
            'qty' => 50,
            'unit' => 'meter',
            'recorded_by' => $this->owner->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('warehouse.usage.index', ['preset' => 'today']))
            ->assertOk();

        // Harus melihat badge C-REQ dan Pindah Lokasi
        $response->assertSee('C-REQ');
        $response->assertSee('Pindah Lokasi');
    }

    #[Test]
    public function instalasi_modem_maintenance_merender_badge_ganti_modem(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->pop->id, 'full_name' => 'Siti Aminah']);

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-MTN-002',
            'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Maintenance Gangguan',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'status' => 'selesai',
        ]);

        $serial = InventorySerial::create([
            'item_id' => $this->modemItem->id,
            'serial_number' => 'SN-HW-9988',
            'status' => 'installed',
            'customer_id' => $customer->id,
        ]);

        InventoryTransaction::create([
            'item_id' => $this->modemItem->id,
            'serial_id' => $serial->id,
            'fop_task_id' => $fopTask->id,
            'from_technician_id' => $this->owner->id,
            'type' => InventoryTransactionType::INSTALL->value,
            'qty' => 1,
            'created_by' => $this->owner->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('warehouse.usage.index', ['preset' => 'today']))
            ->assertOk();

        // Harus melihat badge MTN dan Ganti Modem
        $response->assertSee('MTN');
        $response->assertSee('Ganti Modem');
    }

    #[Test]
    public function widget_pemakaian_hari_ini_dashboard_fop_merender_badge_tipe(): void
    {
        $response = $this->actingAs($this->owner)->get(route('fop.dashboard'));
        $response->assertOk();

        $customer = Customer::factory()->create(['pop_id' => $this->pop->id]);
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-WIDGET-01',
            'task_date' => now(),
            'category' => TaskType::PEMASANGAN->value,
            'tugas' => 'Pasang Baru',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'status' => 'selesai',
        ]);

        TaskMaterial::create([
            'fop_task_id' => $fopTask->id,
            'customer_id' => $customer->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_type' => 'kabel',
            'item_name' => 'Drop Core',
            'qty' => 15,
            'unit' => 'm',
            'created_at' => now(),
        ]);

        $responseAfter = $this->actingAs($this->owner)->get(route('fop.dashboard'));

        $responseAfter->assertOk();
        $responseAfter->assertSee('PSB');
    }
}
