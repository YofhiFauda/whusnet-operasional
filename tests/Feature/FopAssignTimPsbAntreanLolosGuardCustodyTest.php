<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\FopTask;
use App\Models\InternetPackage;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use App\Services\AdditionalDeviceCustodyService;
use App\Services\CustomerWorkflowService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jalur FOP assign tim ke Task Pemasangan yang berasal dari antrean PSB.
 * Assign boleh sebelum Gudang issue, sementara laporan hanya menerima SN
 * yang kemudian benar-benar masuk custody tim.
 */
class FopAssignTimPsbAntreanLolosGuardCustodyTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private User $teknisi;

    private Pop $pop;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->fopUser = $this->userWithRole('fop');
        $this->teknisi = $this->userWithRole('teknisi');

        $city = City::create(['name' => 'Kota Uji Guard Antrean']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Kecamatan Uji']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Desa Uji', 'postal_code' => '63491']);

        $this->pop = Pop::create([
            'code' => 'GQA',
            'pop_code' => 'GQA',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Guard Antrean',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function assign_tim_ke_psb_antrean_berhasil_sebelum_perangkat_tambahan_di_issue(): void
    {
        [$customer, $fopTask, $queuedTask] = $this->bisnisCustomerInInstallationQueue();

        $this->assignTeknisi($fopTask)->assertOk();

        $this->assertSame($queuedTask->id, $fopTask->refresh()->task_id);
        $this->assertSame(TaskStatus::TERJADWAL, $queuedTask->refresh()->status);
        $this->assertTrue($queuedTask->teamMembers()->where('user_id', $this->teknisi->id)->exists());

        $this->assertCount(0, app(AdditionalDeviceCustodyService::class)->eligibleSerialsForTask($queuedTask));
        $this->issueExtraDeviceSerial($this->teknisi, 'GQA-AP-SETELAH-ASSIGN');
        $this->assertCount(1, app(AdditionalDeviceCustodyService::class)->eligibleSerialsForTask($queuedTask->fresh()));
    }

    #[Test]
    public function assign_tim_ke_psb_antrean_berhasil_kalau_custody_perangkat_tambahan_cukup(): void
    {
        [$customer, $fopTask, $queuedTask] = $this->bisnisCustomerInInstallationQueue();

        $this->issueExtraDeviceSerial($this->teknisi, 'GQA-AP-001');

        $this->assignTeknisi($fopTask)->assertOk();

        $fopTask->refresh();
        // Task antrean yang DIPAKAI (bukan kembaran baru) — dan sekarang terjadwal.
        $this->assertSame($queuedTask->id, $fopTask->task_id);
        $this->assertSame(TaskStatus::TERJADWAL, $queuedTask->refresh()->status);
        $this->assertTrue($queuedTask->teamMembers()->where('user_id', $this->teknisi->id)->exists());
    }

    /**
     * @return array{0: Customer, 1: FopTask, 2: Task}
     */
    private function bisnisCustomerInInstallationQueue(): array
    {
        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
            'status' => 'waiting_acc',
        ]);

        $package = InternetPackage::create([
            'package_code' => 'GQA-BISNIS',
            'name' => 'Paket Bisnis Guard',
            'category' => 'Bisnis',
            'package_group' => 'Basic',
            'bandwidth_label' => '50 Mbps',
            'download_speed_mbps' => 50,
            'upload_speed_mbps' => 50,
            'monthly_price' => 500000,
            'max_additional_devices' => 1,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => $package->bandwidth_label,
            'upload_speed_snapshot' => $package->bandwidth_label,
            'monthly_price' => $package->monthly_price,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $package->monthly_price,
            'activation_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'billing_cycle' => 'monthly',
            'service_status' => 'aktif',
            'billing_status' => 'active',
            'contract_type' => 'beli',
        ]);

        // Jalur nyata produksi: transisi workflow melahirkan Task antrean
        // `pending` + FopTask Draft (FopTaskProvisioningService).
        $this->actingAs($this->fopUser);
        app(CustomerWorkflowService::class)->transition($customer, WorkflowTransition::WAITING_INSTALLATION);

        $fopTask = FopTask::where('customer_id', $customer->id)
            ->where('category', TaskType::PEMASANGAN->value)
            ->firstOrFail();
        $queuedTask = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::PEMASANGAN->value)
            ->where('status', TaskStatus::PENDING->value)
            ->firstOrFail();

        $this->assertSame(TaskStatus::DRAFT, $fopTask->status);
        $this->assertNull($fopTask->task_id);

        return [$customer->refresh(), $fopTask, $queuedTask];
    }

    /** Payload modal Edit /fop-tasks — sama dengan FopTaskDraftAutoScheduleOnAssignTest. */
    private function assignTeknisi(FopTask $fopTask): TestResponse
    {
        return $this->actingAs($this->fopUser)->putJson(route('fop-tasks.update', $fopTask), [
            'category' => $fopTask->category->value,
            'task_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'tugas' => $fopTask->tugas,
            'village_id' => $fopTask->village_id,
            'pop_id' => $fopTask->pop_id,
            'issue' => $fopTask->issue,
            'priority' => $fopTask->priority->value,
            'status' => $fopTask->status->value,
            'technicians' => [$this->teknisi->id],
        ]);
    }

    private function userWithRole(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        return $user;
    }

    private function issueExtraDeviceSerial(User $technician, string $serialNumber): InventorySerial
    {
        $category = ItemCategory::firstOrCreate(
            ['code' => 'ap_guard_antrean'],
            ['name' => 'AP Guard Antrean', 'default_unit' => 'pcs', 'is_active' => true, 'is_customer_extra_device' => true]
        );
        $item = Item::firstOrCreate(
            ['code' => 'AP-GUARD-ANTREAN'],
            ['name' => 'AP Guard Antrean', 'item_category_id' => $category->id, 'unit' => 'pcs', 'ownership_mode' => 'installable']
        );

        return InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => $serialNumber,
            'status' => SerialStatus::ISSUED,
            'current_technician_id' => $technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);
    }
}
