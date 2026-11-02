<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use App\Models\DeviceRetrievalLog;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ganti Perangkat Tambahan (AP/STB) pasca-pemasangan lewat Laporan Maintenance:
 * AP lama dicabut (RETURNED, menunggu Terima Retur Gudang), pengganti dari
 * custody dipasang, Detail Pelanggan diperbarui — satu transaksi.
 */
class MaintenanceGantiPerangkatTambahanTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Customer $customer;

    private User $technician;

    private Task $task;

    private InventorySerial $oldAp;

    private InventorySerial $newAp;

    private InventorySerial $ont;

    private CustomerAdditionalDevice $oldRow;

    private ItemCategory $apCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'MTN-GAP', 'pop_code' => 'MGP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Ganti AP', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-MTN-GAP-001',
            'full_name' => 'Pelanggan Ganti AP',
            'primary_phone' => '0812340010',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $this->task = Task::create([
            'task_number' => 'TASK-MTN-GAP-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Ganti AP',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $this->task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        FopTask::create([
            'task_number' => 'TFOP-MTN-GAP-001',
            'task_date' => now(),
            'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Uji ganti AP',
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'task_id' => $this->task->id,
            'issue' => 'AP rusak',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        $ontCategory = ItemCategory::create(['code' => 'modem_ont', 'name' => 'Modem/ONT Pelanggan', 'default_unit' => 'pcs']);
        $ontItem = Item::create(['code' => 'ONT-GAP', 'name' => 'ONT Pelanggan', 'unit' => 'pcs', 'item_category_id' => $ontCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        $this->apCategory = ItemCategory::create(['code' => 'ap_gap', 'name' => 'Access Point', 'default_unit' => 'pcs', 'is_customer_extra_device' => true]);
        $apItem = Item::create(['code' => 'AP-GAP', 'name' => 'Access Point', 'unit' => 'pcs', 'item_category_id' => $this->apCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        $this->ont = InventorySerial::create([
            'item_id' => $ontItem->id, 'serial_number' => 'ONT-GAP-001', 'status' => SerialStatus::INSTALLED->value,
            'customer_id' => $this->customer->id, 'issued_from_pop_id' => $this->pop->id,
        ]);
        $this->oldAp = InventorySerial::create([
            'item_id' => $apItem->id, 'serial_number' => 'AP-LAMA-001', 'status' => SerialStatus::INSTALLED->value,
            'customer_id' => $this->customer->id, 'issued_from_pop_id' => $this->pop->id,
        ]);
        $this->newAp = InventorySerial::create([
            'item_id' => $apItem->id, 'serial_number' => 'AP-BARU-001', 'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->pop->id,
        ]);
        $this->oldRow = CustomerAdditionalDevice::create([
            'customer_id' => $this->customer->id, 'inventory_serial_id' => $this->oldAp->id,
            'device_type' => 'Access Point', 'serial_number' => 'AP-LAMA-001', 'status' => 'terpasang',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'kendala_teknis' => 'AP rusak, diganti unit baru.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
        ], $extra);
    }

    #[Test]
    public function form_maintenance_menawarkan_ap_terpasang_dan_sn_pengganti_dari_custody(): void
    {
        $this->withoutVite();

        $this->actingAs($this->technician)
            ->get(route('tasks.maintenance.report', $this->task))
            ->assertOk()
            ->assertSee('Ganti Perangkat Tambahan')
            ->assertViewHas('replaceableExtraDevices', fn ($rows) => $rows->pluck('id')->all() === [$this->oldRow->id])
            ->assertViewHas('eligibleExtraSerials', fn ($rows) => $rows->pluck('id')->all() === [$this->newAp->id]);
    }

    #[Test]
    public function ganti_ap_rusak_memasang_pengganti_mencabut_lama_dan_memperbarui_detail_pelanggan(): void
    {
        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->payload([
            'extra_replacements' => [[
                'old_device_id' => $this->oldRow->id,
                'new_serial_id' => $this->newAp->id,
                'old_condition' => 'rusak',
                'note' => 'Port LAN mati',
            ]],
        ]))->assertSessionHasNoErrors();

        // Pengganti terpasang.
        $this->assertSame(SerialStatus::INSTALLED, $this->newAp->refresh()->status);
        $this->assertSame($this->customer->id, $this->newAp->customer_id);

        // Lama dicabut: transit di tangan teknisi, kondisi belum diperiksa Gudang.
        $this->oldAp->refresh();
        $this->assertSame(SerialStatus::RETURNED, $this->oldAp->status);
        $this->assertNull($this->oldAp->customer_id);
        $this->assertSame($this->technician->id, $this->oldAp->current_technician_id);
        $this->assertSame(ItemCondition::USED_DAMAGED, $this->oldAp->condition);
        $this->assertNull($this->oldAp->condition_checked_at);

        $this->assertTrue(InventoryTransaction::where('serial_id', $this->oldAp->id)->where('type', InventoryTransactionType::RETURN)->exists());
        $this->assertTrue(DeviceRetrievalLog::where('serial_id', $this->oldAp->id)->where('customer_id', $this->customer->id)->exists());

        // Detail Pelanggan: lama 'ditarik', baru 'terpasang', jatah paket tetap 1.
        $this->oldRow->refresh();
        $this->assertSame('ditarik', $this->oldRow->status);
        $this->assertNotNull($this->oldRow->retrieved_at);
        $this->assertStringContainsString('AP-BARU-001', $this->oldRow->technical_note);
        $this->assertStringContainsString('Port LAN mati', $this->oldRow->technical_note);
        $this->assertDatabaseHas('customer_additional_devices', ['customer_id' => $this->customer->id, 'serial_number' => 'AP-BARU-001', 'status' => 'terpasang', 'inventory_serial_id' => $this->newAp->id]);
        $this->assertSame(1, $this->customer->additionalDevices()->active()->count());

        // Perangkat utama tidak tersentuh.
        $this->assertSame(SerialStatus::INSTALLED, $this->ont->refresh()->status);
        $this->assertSame($this->customer->id, $this->ont->customer_id);
        $this->assertNotSame('AP-BARU-001', $this->customer->refresh()->ont_sn);

        $this->assertSame(TaskStatus::SELESAI, $this->task->refresh()->status);
    }

    #[Test]
    public function pengganti_beda_kategori_ditolak_dan_seluruh_laporan_dibatalkan(): void
    {
        $stbCategory = ItemCategory::create(['code' => 'stb_gap', 'name' => 'Set Top Box', 'default_unit' => 'pcs', 'is_customer_extra_device' => true]);
        $stbItem = Item::create(['code' => 'STB-GAP', 'name' => 'Set Top Box', 'unit' => 'pcs', 'item_category_id' => $stbCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        $stb = InventorySerial::create([
            'item_id' => $stbItem->id, 'serial_number' => 'STB-001', 'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->pop->id,
        ]);

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->payload([
            'extra_replacements' => [['old_device_id' => $this->oldRow->id, 'new_serial_id' => $stb->id, 'old_condition' => 'baik']],
        ]))->assertSessionHas('error');

        $this->assertSame(SerialStatus::INSTALLED, $this->oldAp->refresh()->status);
        $this->assertSame(SerialStatus::ISSUED, $stb->refresh()->status);
        $this->assertSame('terpasang', $this->oldRow->refresh()->status);
        $this->assertNotSame(TaskStatus::SELESAI, $this->task->refresh()->status);
    }

    #[Test]
    public function sn_pengganti_di_luar_custody_tim_ditolak(): void
    {
        $this->newAp->update(['current_technician_id' => User::factory()->create()->id]);

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->payload([
            'extra_replacements' => [['old_device_id' => $this->oldRow->id, 'new_serial_id' => $this->newAp->id, 'old_condition' => 'rusak']],
        ]))->assertSessionHasErrors('extra_replacements.0.new_serial_id');

        $this->assertSame('terpasang', $this->oldRow->refresh()->status);
    }

    #[Test]
    public function perangkat_milik_pelanggan_lain_tidak_bisa_diganti(): void
    {
        $other = Customer::create([
            'customer_code' => 'TEST-MTN-GAP-002', 'full_name' => 'Pelanggan Lain', 'primary_phone' => '0812340011',
            'status' => 'active', 'pop_id' => $this->pop->id, 'registration_date' => now(),
        ]);
        $otherRow = CustomerAdditionalDevice::create([
            'customer_id' => $other->id, 'inventory_serial_id' => $this->ont->id,
            'device_type' => 'Access Point', 'serial_number' => 'AP-LAIN', 'status' => 'terpasang',
        ]);

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->payload([
            'extra_replacements' => [['old_device_id' => $otherRow->id, 'new_serial_id' => $this->newAp->id, 'old_condition' => 'rusak']],
        ]))->assertSessionHasErrors('extra_replacements.0.old_device_id');

        $this->assertSame(SerialStatus::ISSUED, $this->newAp->refresh()->status);
    }

    #[Test]
    public function perangkat_data_lama_tanpa_stok_bisa_diganti_tanpa_retur_gudang(): void
    {
        // Pelanggan legacy: AP dicatat teks bebas, tidak pernah ada di Gudang.
        $legacy = CustomerAdditionalDevice::create([
            'customer_id' => $this->customer->id, 'device_type' => 'AP', 'serial_number' => 'AP-LEGACY-X', 'status' => 'terpasang',
        ]);
        $this->oldRow->update(['status' => 'ditarik']);

        $this->withoutVite();
        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $this->task))
            ->assertOk()
            ->assertViewHas('replaceableExtraDevices', fn ($rows) => $rows->pluck('id')->all() === [$legacy->id]);

        $returnsBefore = InventoryTransaction::where('type', InventoryTransactionType::RETURN)->count();

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->payload([
            'extra_replacements' => [['old_device_id' => $legacy->id, 'new_serial_id' => $this->newAp->id, 'old_condition' => 'baik']],
        ]))->assertSessionHasNoErrors();

        $legacy->refresh();
        $this->assertSame('ditarik', $legacy->status);
        $this->assertStringContainsString('tidak tercatat di Gudang', $legacy->technical_note);
        $this->assertSame(SerialStatus::INSTALLED, $this->newAp->refresh()->status);
        $this->assertDatabaseHas('customer_additional_devices', ['customer_id' => $this->customer->id, 'serial_number' => 'AP-BARU-001', 'status' => 'terpasang']);

        // Tidak ada unit Gudang yang dicabut: tanpa RETURN baru, tanpa jejak tarik.
        $this->assertSame($returnsBefore, InventoryTransaction::where('type', InventoryTransactionType::RETURN)->count());
        $this->assertSame(0, DeviceRetrievalLog::count());
        $this->assertSame(1, $this->customer->additionalDevices()->active()->count());
    }

    #[Test]
    public function laporan_tanpa_penggantian_tidak_menyentuh_perangkat_tambahan(): void
    {
        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('terpasang', $this->oldRow->refresh()->status);
        $this->assertSame(SerialStatus::INSTALLED, $this->oldAp->refresh()->status);
        $this->assertSame(SerialStatus::ISSUED, $this->newAp->refresh()->status);
    }
}
