<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-190 task 3 — guard preventif FOP: Task Pemasangan gak boleh jadi
 * TERJADWAL kalau tim yang diassign gak pegang custody perangkat tambahan
 * yang cukup buat paket pelanggannya. Lihat AdditionalDeviceCustodyService.
 */
class TaskAdditionalDeviceAssignGuardTest extends TestCase
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
        $this->seed(TaskFeatureSeeder::class);
    }

    #[Test]
    public function scheduling_pemasangan_task_is_rejected_when_extra_device_custody_insufficient(): void
    {
        $pop = $this->createPop('ADG1');
        $fop = $this->createFopUser($pop);
        $teknisi = $this->createTeknisi();
        $customer = $this->createCustomer($pop, 'TEST-ADG-001');
        $this->createActiveCustomerService($customer, $this->createPackage('ADG-PKT-001', 1));

        // Teknisi SENGAJA tanpa custody AP sama sekali.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Barang perangkat tambahan belum di-issue cukup');

        app(TaskService::class)->create([
            'task_type' => TaskType::PEMASANGAN->value,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'title' => 'Pemasangan Pelanggan',
            'team_member_ids' => [$teknisi->id],
            'scheduled_at' => now()->addDay(),
        ], $fop);
    }

    #[Test]
    public function scheduling_pemasangan_task_succeeds_when_extra_device_custody_sufficient(): void
    {
        $pop = $this->createPop('ADG2');
        $fop = $this->createFopUser($pop);
        $teknisi = $this->createTeknisi();
        $customer = $this->createCustomer($pop, 'TEST-ADG-002');
        $this->createActiveCustomerService($customer, $this->createPackage('ADG-PKT-002', 1));

        $this->issueExtraDeviceSerial($teknisi, 'ADG-SN-002', $pop->id);

        $task = app(TaskService::class)->create([
            'task_type' => TaskType::PEMASANGAN->value,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'title' => 'Pemasangan Pelanggan',
            'team_member_ids' => [$teknisi->id],
            'scheduled_at' => now()->addDay(),
        ], $fop);

        $this->assertSame('terjadwal', $task->status->value);
    }

    #[Test]
    public function non_pemasangan_task_type_is_not_blocked_by_custody_guard(): void
    {
        $pop = $this->createPop('ADG3');
        $fop = $this->createFopUser($pop);
        $teknisi = $this->createTeknisi();
        $customer = $this->createCustomer($pop, 'TEST-ADG-003');
        // Paket butuh perangkat tambahan, tapi task-nya MTN — guard harus no-op.
        $this->createActiveCustomerService($customer, $this->createPackage('ADG-PKT-003', 1));

        $task = app(TaskService::class)->create([
            'task_type' => TaskType::MAINTENANCE->value,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'title' => 'Maintenance Pelanggan',
            'team_member_ids' => [$teknisi->id],
            'scheduled_at' => now()->addDay(),
        ], $fop);

        $this->assertSame('terjadwal', $task->status->value);
    }

    #[Test]
    public function pemasangan_task_with_zero_additional_device_limit_is_not_blocked(): void
    {
        $pop = $this->createPop('ADG4');
        $fop = $this->createFopUser($pop);
        $teknisi = $this->createTeknisi();
        $customer = $this->createCustomer($pop, 'TEST-ADG-004');
        // max_additional_devices default 0 — tidak diisi sengaja.
        $this->createActiveCustomerService($customer, $this->createPackage('ADG-PKT-004', 0));

        $task = app(TaskService::class)->create([
            'task_type' => TaskType::PEMASANGAN->value,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'title' => 'Pemasangan Pelanggan',
            'team_member_ids' => [$teknisi->id],
            'scheduled_at' => now()->addDay(),
        ], $fop);

        $this->assertSame('terjadwal', $task->status->value);
    }

    /**
     * Regresi update(): reassign tim Pemasangan yang SUDAH terjadwal juga
     * harus dicek ulang, bukan cuma create().
     */
    #[Test]
    public function reassigning_team_on_scheduled_pemasangan_task_is_rejected_when_custody_insufficient(): void
    {
        $pop = $this->createPop('ADG5');
        $fop = $this->createFopUser($pop);
        $teknisiLama = $this->createTeknisi();
        $teknisiBaru = $this->createTeknisi();
        $customer = $this->createCustomer($pop, 'TEST-ADG-005');
        $this->createActiveCustomerService($customer, $this->createPackage('ADG-PKT-005', 1));

        $this->issueExtraDeviceSerial($teknisiLama, 'ADG-SN-005', $pop->id);

        $task = app(TaskService::class)->create([
            'task_type' => TaskType::PEMASANGAN->value,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'title' => 'Pemasangan Pelanggan',
            'team_member_ids' => [$teknisiLama->id],
            'scheduled_at' => now()->addDay(),
        ], $fop);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Barang perangkat tambahan belum di-issue cukup');

        // Teknisi baru belum pegang custody AP sama sekali.
        app(TaskService::class)->update($task, [
            'team_member_ids' => [$teknisiBaru->id],
        ], $fop);
    }

    /**
     * Review follow-up ADHOC-190 task 3: `reassignTeam()` TIDAK lewat
     * `create()`/`update()` sama sekali — dia nulis langsung ke
     * `teamMembers()` lewat query builder-nya sendiri. Precondition method
     * ini (`status` harus TERJADWAL/IN_PROGRESS) justru bikin dia SATU-
     * SATUNYA jalur lain yang bisa nukar teknisi di Task Pemasangan yang
     * SUDAH lolos guard custody di create() — tanpa guard di sini, FOP bisa
     * swap teknisi lama (custody cukup) ke teknisi baru (custody nol) dan
     * lolos begitu saja.
     */
    #[Test]
    public function reassign_team_is_rejected_when_new_technician_lacks_custody(): void
    {
        $pop = $this->createPop('ADG6');
        $fop = $this->createFopUser($pop);
        $teknisiLama = $this->createTeknisi();
        $teknisiBaru = $this->createTeknisi();
        $customer = $this->createCustomer($pop, 'TEST-ADG-006');
        $this->createActiveCustomerService($customer, $this->createPackage('ADG-PKT-006', 1));

        $this->issueExtraDeviceSerial($teknisiLama, 'ADG-SN-006', $pop->id);

        $task = app(TaskService::class)->create([
            'task_type' => TaskType::PEMASANGAN->value,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'title' => 'Pemasangan Pelanggan',
            'team_member_ids' => [$teknisiLama->id],
            'scheduled_at' => now()->addDay(),
        ], $fop);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Barang perangkat tambahan belum di-issue cukup');

        // Teknisi baru belum pegang custody AP sama sekali.
        app(TaskService::class)->reassignTeam($task, $teknisiLama->id, $teknisiBaru->id, $fop->id);
    }

    /**
     * Padanan sukses dari test di atas: teknisi baru PUNYA custody yang
     * cukup → reassign tetap jalan normal, guard gak nge-block yang
     * seharusnya lolos.
     */
    #[Test]
    public function reassign_team_succeeds_when_new_technician_has_sufficient_custody(): void
    {
        $pop = $this->createPop('ADG7');
        $fop = $this->createFopUser($pop);
        $teknisiLama = $this->createTeknisi();
        $teknisiBaru = $this->createTeknisi();
        $customer = $this->createCustomer($pop, 'TEST-ADG-007');
        $this->createActiveCustomerService($customer, $this->createPackage('ADG-PKT-007', 1));

        $this->issueExtraDeviceSerial($teknisiLama, 'ADG-SN-007A', $pop->id);
        $this->issueExtraDeviceSerial($teknisiBaru, 'ADG-SN-007B', $pop->id);

        $task = app(TaskService::class)->create([
            'task_type' => TaskType::PEMASANGAN->value,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'title' => 'Pemasangan Pelanggan',
            'team_member_ids' => [$teknisiLama->id],
            'scheduled_at' => now()->addDay(),
        ], $fop);

        app(TaskService::class)->reassignTeam($task, $teknisiLama->id, $teknisiBaru->id, $fop->id);

        $this->assertTrue($task->teamMembers()->where('user_id', $teknisiBaru->id)->exists());
        $this->assertFalse($task->teamMembers()->where('user_id', $teknisiLama->id)->exists());
    }

    /**
     * Guard no-op buat task_type non-Pemasangan — reassign MAINTENANCE tetap
     * jalan normal walau teknisi barunya gak pegang custody apa pun.
     */
    #[Test]
    public function reassign_team_succeeds_for_non_pemasangan_task_regardless_of_custody(): void
    {
        $pop = $this->createPop('ADG8');
        $fop = $this->createFopUser($pop);
        $teknisiLama = $this->createTeknisi();
        $teknisiBaru = $this->createTeknisi();
        $customer = $this->createCustomer($pop, 'TEST-ADG-008');
        // Paket butuh perangkat tambahan, tapi task-nya MAINTENANCE — guard harus no-op.
        $this->createActiveCustomerService($customer, $this->createPackage('ADG-PKT-008', 1));

        $task = app(TaskService::class)->create([
            'task_type' => TaskType::MAINTENANCE->value,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'title' => 'Maintenance Pelanggan',
            'team_member_ids' => [$teknisiLama->id],
            'scheduled_at' => now()->addDay(),
        ], $fop);

        app(TaskService::class)->reassignTeam($task, $teknisiLama->id, $teknisiBaru->id, $fop->id);

        $this->assertTrue($task->teamMembers()->where('user_id', $teknisiBaru->id)->exists());
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createCustomer(Pop $pop, string $code): Customer
    {
        return Customer::create([
            'customer_code' => $code,
            'full_name' => 'Customer '.$code,
            'primary_phone' => '0812345678',
            'pop_id' => $pop->id,
            'status' => 'installed',
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function createPackage(string $code, int $maxAdditionalDevices): InternetPackage
    {
        return InternetPackage::create([
            'package_code' => $code,
            'name' => 'Paket '.$code,
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '10 Mbps',
            'download_speed_mbps' => 10,
            'upload_speed_mbps' => 10,
            'monthly_price' => 150000,
            'max_additional_devices' => $maxAdditionalDevices,
        ]);
    }

    private function createActiveCustomerService(Customer $customer, InternetPackage $package): CustomerService
    {
        return CustomerService::create([
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
    }

    private function createFopUser(Pop $pop): User
    {
        $fop = User::factory()->create();
        $fop->role_id = Role::where('code', 'fop')->first()?->id;
        $fop->save();
        $fop->pops()->attach($pop->id);

        return $fop;
    }

    private function createTeknisi(): User
    {
        $teknisi = User::factory()->create();
        $teknisi->role_id = Role::where('code', 'teknisi')->first()?->id;
        $teknisi->save();

        return $teknisi;
    }

    /**
     * Issue satu SN kategori perangkat tambahan (is_customer_extra_device)
     * ke custody teknisi — status ISSUED, `issued_from_pop_id` = cabang task
     * biar lolos filter cabang `AdditionalDeviceCustodyService::countAvailable()`.
     */
    private function issueExtraDeviceSerial(User $technician, string $serialNumber, int $popId): InventorySerial
    {
        $category = ItemCategory::firstOrCreate(
            ['code' => 'ap_guard_test'],
            [
                'name' => 'AP Guard Test',
                'default_unit' => 'pcs',
                'is_active' => true,
                'is_customer_extra_device' => true,
            ]
        );
        $item = Item::firstOrCreate(
            ['code' => 'AP-GUARD-TEST'],
            [
                'name' => 'AP Guard Test',
                'item_category_id' => $category->id,
                'unit' => 'pcs',
            ]
        );

        return InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => $serialNumber,
            'status' => SerialStatus::ISSUED,
            'current_technician_id' => $technician->id,
            'issued_from_pop_id' => $popId,
        ]);
    }
}
