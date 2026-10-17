<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Final review ADHOC-190 (Critical 2): sejak FOP rutin meng-issue AP ke
 * custody teknisi, AP bisa nongol di dropdown "ganti modem" Laporan
 * Maintenance/C-REQ. Kalau dipilih, `installSerial(returnExistingSerial: true)`
 * meretur ONT asli pelanggan dan AP-nya menyamar jadi ONT di
 * `customers.ont_sn`/`customer_devices`. Ditutup dua lapis: filter dropdown
 * (UX) dan guard di service (penegakan sebenarnya).
 */
class MaintenanceGantiModemPakaiApTidakMeretirOntTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Customer $customer;

    private User $technician;

    private Task $task;

    private FopTask $fopTask;

    private InventorySerial $ont;

    private InventorySerial $ap;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'MTN-AP', 'pop_code' => 'MAP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang AP Test', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-MTN-AP-001',
            'full_name' => 'Pelanggan AP Maintenance',
            'primary_phone' => '0812340009',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $this->task = Task::create([
            'task_number' => 'TASK-MTN-AP-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Ganti Modem',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $this->task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-MTN-AP-001',
            'task_date' => now(),
            'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Uji AP Maintenance',
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'task_id' => $this->task->id,
            'issue' => 'Uji ganti modem',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        $ontCategory = ItemCategory::create(['code' => 'modem_ont', 'name' => 'Modem/ONT Pelanggan', 'default_unit' => 'pcs']);
        $ontItem = Item::create([
            'code' => 'ONT-MTN-AP', 'name' => 'ONT Pelanggan', 'unit' => 'pcs',
            'item_category_id' => $ontCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
        $apCategory = ItemCategory::create(['code' => 'ap_mtn', 'name' => 'Access Point', 'default_unit' => 'pcs', 'is_customer_extra_device' => true]);
        $apItem = Item::create([
            'code' => 'AP-MTN', 'name' => 'Access Point', 'unit' => 'pcs',
            'item_category_id' => $apCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);

        // ONT asli pelanggan — yang TIDAK boleh ke-retur.
        $this->ont = InventorySerial::create([
            'item_id' => $ontItem->id,
            'serial_number' => 'ONT-ASLI-001',
            'status' => SerialStatus::INSTALLED->value,
            'customer_id' => $this->customer->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        // AP di custody teknisi (hasil issue guard ADHOC-190 task 3).
        $this->ap = InventorySerial::create([
            'item_id' => $apItem->id,
            'serial_number' => 'AP-CUSTODY-001',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);
    }

    #[Test]
    public function dropdown_ganti_modem_maintenance_tidak_menampilkan_sn_perangkat_tambahan(): void
    {
        $this->withoutVite();

        $this->actingAs($this->technician)
            ->get(route('tasks.maintenance.report', $this->task))
            ->assertOk()
            ->assertViewHas('eligibleSerials', fn ($serials) => ! $serials->pluck('id')->contains($this->ap->id));
    }

    #[Test]
    public function submit_laporan_maintenance_dengan_sn_ap_ditolak_dan_ont_tetap_terpasang(): void
    {
        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), [
            'kendala_teknis' => 'Ganti modem.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
            'selected_inventory_serial_id' => $this->ap->id,
            'modem_ownership_choice' => 'sewa_gudang',
        ])->assertSessionHasErrors('selected_inventory_serial_id');

        $this->assertSame(SerialStatus::INSTALLED, $this->ont->refresh()->status);
        $this->assertSame($this->customer->id, $this->ont->customer_id);
        $this->assertSame(SerialStatus::ISSUED, $this->ap->refresh()->status);
    }

    #[Test]
    public function install_serial_menolak_sn_perangkat_tambahan_sebagai_perangkat_utama(): void
    {
        try {
            app(InventoryService::class)->installSerial(
                $this->ap,
                $this->customer,
                $this->fopTask,
                [$this->technician],
                $this->technician,
                returnExistingSerial: true,
            );
            $this->fail('installSerial() seharusnya menolak SN perangkat tambahan dengan returnExistingSerial=true.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak bisa dipasang sebagai perangkat utama', $e->getMessage());
        }

        // Guard jalan SEBELUM retur apa pun — ONT asli utuh, AP tetap di custody.
        $this->assertSame(SerialStatus::INSTALLED, $this->ont->refresh()->status);
        $this->assertSame($this->customer->id, $this->ont->customer_id);
        $this->assertSame(SerialStatus::ISSUED, $this->ap->refresh()->status);
        $this->assertNotSame('AP-CUSTODY-001', $this->customer->refresh()->ont_sn);
    }

    /**
     * Jalur sah langkah Perangkat Tambahan (returnExistingSerial=false) tetap
     * boleh — guard cuma menolak AP yang dipasang SEBAGAI perangkat utama.
     */
    #[Test]
    public function install_serial_perangkat_tambahan_tanpa_retur_tetap_boleh(): void
    {
        app(InventoryService::class)->installSerial(
            $this->ap,
            $this->customer,
            $this->fopTask,
            [$this->technician],
            $this->technician,
            returnExistingSerial: false,
        );

        $this->assertSame(SerialStatus::INSTALLED, $this->ap->refresh()->status);
        $this->assertSame(SerialStatus::INSTALLED, $this->ont->refresh()->status);
    }
}
