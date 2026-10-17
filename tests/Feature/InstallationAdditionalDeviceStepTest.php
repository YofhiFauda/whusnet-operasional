<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\OwnershipMode;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use App\Models\CustomerService;
use App\Models\FopTask;
use App\Models\InternetPackage;
use App\Models\InventorySerial;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-190 task 4 — langkah "Perangkat Tambahan" di Laporan Pemasangan:
 * teknisi memberi keputusan per SN perangkat tambahan di custody tim
 * (terpasang / kembali_gudang / rusak / hilang), dengan jalur kekurangan +
 * alasan wajib sebagai pengecualian (spec §4.2).
 */
class InstallationAdditionalDeviceStepTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private User $technician;

    private Customer $customer;

    private FopTask $fopTask;

    private Item $apItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'CABANG-ADS', 'pop_code' => 'ADS', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Additional Step', 'type' => 'cabang', 'status' => 'active']);

        $role = Role::where('code', 'teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $this->technician->load('role');
        $this->technician->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-ADS-001',
            'full_name' => 'Pelanggan Additional Step',
            'primary_phone' => '0812340090',
            'status' => 'installation_in_progress',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $this->customer->installations()->create([
            'installation_status' => 'in_progress',
            'started_at' => now(),
            'start_time' => now()->toTimeString(),
            'scheduled_date' => now()->format('Y-m-d'),
            'scheduled_time' => '09:00',
            'technician_id' => $this->technician->id,
        ]);

        $task = Task::create([
            'task_number' => 'TASK-TEST-ADS-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Additional Step',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-TEST-ADS-001',
            'task_date' => now(),
            'category' => TaskType::PEMASANGAN->value,
            'tugas' => 'Pemasangan Additional Step',
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'task_id' => $task->id,
            'issue' => 'Uji langkah perangkat tambahan',
            'status' => TaskStatus::IN_PROGRESS->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        $apCategory = ItemCategory::create(['code' => 'ap_step_test', 'name' => 'Access Point', 'default_unit' => 'pcs', 'is_active' => true, 'is_customer_extra_device' => true]);
        $this->apItem = Item::create([
            'code' => 'AP-STEP-TEST', 'name' => 'AP Step Test', 'brand' => 'TP-Link', 'model' => 'EAP110', 'unit' => 'pcs',
            'item_category_id' => $apCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value,
        ]);
    }

    #[Test]
    public function submit_rejected_when_decisions_incomplete_and_no_shortage_reason(): void
    {
        $this->givePackage(2);
        $ap1 = $this->issueAp('SN-ADS-AP-001');
        $this->issueAp('SN-ADS-AP-002');

        $response = $this->actingAs($this->technician)
            ->from(route('customers.installation.report', $this->customer))
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [
                    ['inventory_serial_id' => $ap1->id, 'outcome' => 'terpasang'],
                ],
            ]);

        $response->assertRedirect(route('customers.installation.report', $this->customer));
        $response->assertSessionHasErrors('decisions');
        $this->assertDatabaseCount('customer_additional_devices', 0);
        $this->assertSame(SerialStatus::ISSUED, $ap1->refresh()->status);
    }

    #[Test]
    public function submit_allowed_with_shortage_reason_when_fewer_devices_decided_than_required(): void
    {
        $this->givePackage(2);
        $ap1 = $this->issueAp('SN-ADS-AP-011');

        $response = $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [
                    ['inventory_serial_id' => $ap1->id, 'outcome' => 'terpasang'],
                ],
                'kekurangan_perangkat' => '1',
                'kekurangan_perangkat_alasan' => 'AP kedua pecah di jalan sebelum sampai lokasi.',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertSame(1, CustomerAdditionalDevice::where('customer_id', $this->customer->id)->count());

        $installation = $this->customer->installations()->latest()->first();
        $this->assertTrue($installation->extra_device_shortage);
        $this->assertSame('AP kedua pecah di jalan sebelum sampai lokasi.', $installation->extra_device_shortage_reason);
    }

    #[Test]
    public function submit_installs_selected_serial_without_retiring_primary_ont(): void
    {
        $this->givePackage(1);

        // ONT utama sudah INSTALLED ke pelanggan ini (ownership default COMPANY).
        $ontCategory = ItemCategory::create(['code' => 'ont_step_test', 'name' => 'ONT Step Test', 'default_unit' => 'pcs', 'is_active' => true]);
        $ontItem = Item::create([
            'code' => 'ONT-STEP-TEST', 'name' => 'ONT Step Test', 'unit' => 'pcs',
            'item_category_id' => $ontCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value,
        ]);
        $ont = InventorySerial::create([
            'item_id' => $ontItem->id,
            'serial_number' => 'SN-ADS-ONT-001',
            'status' => SerialStatus::INSTALLED->value,
            'customer_id' => $this->customer->id,
            'installed_at' => now(),
        ]);

        $ap = $this->issueAp('SN-ADS-AP-021');

        $response = $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [
                    ['inventory_serial_id' => $ap->id, 'outcome' => 'terpasang'],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $ap->refresh();
        $this->assertSame(SerialStatus::INSTALLED, $ap->status);
        $this->assertSame($this->customer->id, $ap->customer_id);

        $device = CustomerAdditionalDevice::where('customer_id', $this->customer->id)->sole();
        $this->assertSame($ap->id, $device->inventory_serial_id);
        $this->assertSame('terpasang', $device->status);
        $this->assertSame('SN-ADS-AP-021', $device->serial_number);
        $this->assertSame('Access Point', $device->device_type);

        // Asersi terpenting task ini: ONT utama TIDAK ikut di-retur.
        $ont->refresh();
        $this->assertSame(SerialStatus::INSTALLED, $ont->status, 'ONT utama tidak boleh di-RETURN saat AP dipasang');
        $this->assertSame($this->customer->id, $ont->customer_id);
    }

    #[Test]
    public function device_marked_not_installed_requires_final_status(): void
    {
        $this->givePackage(1);
        $ap = $this->issueAp('SN-ADS-AP-031');

        // Tanpa outcome → ditolak (status akhir wajib dipilih).
        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [
                    ['inventory_serial_id' => $ap->id],
                ],
            ])
            ->assertSessionHasErrors('decisions.0.outcome');

        $response = $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [
                    ['inventory_serial_id' => $ap->id, 'outcome' => 'kembali_gudang'],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseCount('customer_additional_devices', 0);
        $ap->refresh();
        $this->assertSame(SerialStatus::ISSUED, $ap->status);
        $this->assertSame($this->technician->id, $ap->current_technician_id);
        $this->assertStringContainsString('SN-ADS-AP-031', (string) $this->customer->installations()->latest()->first()->installation_note);
    }

    #[Test]
    public function serial_outside_team_custody_is_rejected(): void
    {
        $this->givePackage(1);
        $otherTechnician = User::factory()->create();
        $foreignAp = $this->issueAp('SN-ADS-AP-041', $otherTechnician);

        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [
                    ['inventory_serial_id' => $foreignAp->id, 'outcome' => 'terpasang'],
                ],
            ])
            ->assertSessionHasErrors('decisions.0.inventory_serial_id');

        $this->assertSame(SerialStatus::ISSUED, $foreignAp->refresh()->status);
    }

    #[Test]
    public function extra_device_serial_is_not_accepted_as_primary_device_in_step_five(): void
    {
        $this->givePackage(1);
        $ap = $this->issueAp('SN-ADS-AP-051');

        $this->actingAs($this->technician)
            ->post(route('customers.installation.pemasangan', $this->customer), [
                'device_type' => 'ont',
                'connection_mode' => 'pppoe',
                'wifi_ssid' => 'WHUSNET_ADS',
                'wifi_password' => 'password123',
                'odp_number' => 'ODP-01',
                'odp_port' => '1',
                'selected_inventory_serial_id' => $ap->id,
            ])
            ->assertSessionHasErrors('selected_inventory_serial_id');
    }

    #[Test]
    public function report_page_shows_step_only_when_package_requires_extra_devices(): void
    {
        $this->givePackage(1);
        $this->issueAp('SN-ADS-AP-061');

        $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $this->customer))
            ->assertOk()
            ->assertSee('form-additional-devices', false)
            ->assertSee('SN-ADS-AP-061');
    }

    #[Test]
    public function report_page_hides_step_when_package_limit_is_zero(): void
    {
        $this->givePackage(0);
        $this->issueAp('SN-ADS-AP-071');

        $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $this->customer))
            ->assertOk()
            ->assertDontSee('form-additional-devices', false);
    }

    private function givePackage(int $maxAdditionalDevices): void
    {
        $package = InternetPackage::create([
            'package_code' => 'ADS-PKT-'.$maxAdditionalDevices,
            'name' => 'Paket ADS '.$maxAdditionalDevices,
            'category' => 'Bisnis',
            'package_group' => 'Basic',
            'bandwidth_label' => '20 Mbps',
            'download_speed_mbps' => 20,
            'upload_speed_mbps' => 20,
            'monthly_price' => 300000,
            'max_additional_devices' => $maxAdditionalDevices,
        ]);

        CustomerService::create([
            'customer_id' => $this->customer->id,
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
        ]);
    }

    private function issueAp(string $serialNumber, ?User $holder = null): InventorySerial
    {
        return InventorySerial::create([
            'item_id' => $this->apItem->id,
            'serial_number' => $serialNumber,
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => ($holder ?? $this->technician)->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);
    }
}
