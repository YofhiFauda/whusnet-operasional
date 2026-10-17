<?php

namespace Tests\Feature;

use App\Enums\DeviceOwnership;
use App\Enums\DeviceRetrievalOutcome;
use App\Enums\ItemCondition;
use App\Enums\OwnershipMode;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use App\Models\CustomerDevice;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskTeam;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-190 task 5 — DEAC (Ambil Modem/alat putus langganan) menarik SN
 * perangkat tambahan (AP/STB) lewat form "Ambil Alat" yang sama dengan modem
 * utama (form SN repeatable sudah generik, tidak ada perubahan struktural).
 * Yang hilang sebelum task ini: `customer_additional_devices` tidak ikut
 * disinkronkan, jadi Detail Pelanggan tetap menampilkan AP sebagai
 * "terpasang" walau unitnya sudah balik ke gudang.
 */
class DeviceRetrievalExtraDeviceTest extends TestCase
{
    use RefreshDatabase;

    private User $teknisi;

    private User $owner;

    private Pop $cabang;

    private Item $modem;

    private Item $apItem;

    private Customer $customer;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }

        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $ownerRole = Role::where('code', 'owner')->firstOrFail();

        $this->teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->teknisi->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);
        $this->owner->roleScopes()->create(['role_id' => $ownerRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->cabang = Pop::create(['code' => 'DRX-CABANG', 'pop_code' => 'DRX', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Retur Extra Test', 'type' => 'cabang', 'status' => 'active']);

        $modemCategory = ItemCategory::create(['code' => 'modem_extra_test', 'name' => 'Modem Extra Test', 'default_unit' => 'unit', 'is_active' => true]);
        $this->modem = Item::create(['code' => 'DRX-MODEM', 'name' => 'Modem Retur Extra Test', 'item_category_id' => $modemCategory->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value]);

        $apCategory = ItemCategory::create(['code' => 'ap_extra_test', 'name' => 'Access Point Extra Test', 'default_unit' => 'pcs', 'is_active' => true, 'is_customer_extra_device' => true]);
        $this->apItem = Item::create(['code' => 'DRX-AP', 'name' => 'AP Retur Extra Test', 'brand' => 'TP-Link', 'model' => 'EAP110', 'item_category_id' => $apCategory->id, 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value]);

        $this->customer = Customer::factory()->create(['pop_id' => $this->cabang->id, 'status' => 'terminated']);
        CustomerDevice::create(['customer_id' => $this->customer->id, 'device_type' => 'ONT', 'brand' => 'ZTE', 'model' => 'F609']);

        $this->task = Task::create([
            'task_number' => 'TASK-DRX-'.random_int(1000, 9999),
            'task_type' => TaskType::AMBIL_MODEM->value,
            'title' => 'Ambil Modem '.$this->customer->id,
            'pop_id' => $this->cabang->id,
            'customer_id' => $this->customer->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ]);

        TaskTeam::create(['task_id' => $this->task->id, 'user_id' => $this->teknisi->id, 'role_in_task' => 'lead']);
    }

    private function makeInstalledSerial(Item $item, string $sn): InventorySerial
    {
        $serial = InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => $sn,
            'status' => SerialStatus::INSTALLED->value,
            'ownership' => DeviceOwnership::COMPANY->value,
            'issued_from_pop_id' => $this->cabang->id,
            'current_pop_id' => null,
            'customer_id' => $this->customer->id,
            'condition' => ItemCondition::NEW->value,
        ]);

        return $serial;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submitReport(array $overrides = []): TestResponse
    {
        $payload = array_merge([
            'outcome' => DeviceRetrievalOutcome::DIAMBIL->value,
            'serials' => [['serial_number' => 'SN-DEFAULT', 'item_id' => $this->modem->id]],
            'condition_photo' => UploadedFile::fake()->image('kondisi.jpg'),
            'accessories' => [],
            'notes' => null,
        ], $overrides);

        return $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), $payload);
    }

    #[Test]
    public function retrieving_extra_device_serial_marks_additional_device_row_ditarik(): void
    {
        $ap = $this->makeInstalledSerial($this->apItem, 'DRX-AP-001');

        $device = CustomerAdditionalDevice::create([
            'customer_id' => $this->customer->id,
            'inventory_serial_id' => $ap->id,
            'device_type' => 'Access Point',
            'brand' => $ap->item?->brand,
            'model' => $ap->item?->model,
            'serial_number' => $ap->serial_number,
            'status' => 'terpasang',
        ]);

        $this->submitReport(['serials' => [['serial_number' => 'DRX-AP-001', 'item_id' => $this->apItem->id]]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tasks.show', $this->task));

        $ap->refresh();
        $this->assertEquals(SerialStatus::RETURNED, $ap->status);

        $device->refresh();
        $this->assertSame('ditarik', $device->status);
        $this->assertNotNull($device->retrieved_at);
    }

    /**
     * SN yang ditarik adalah modem utama, bukan perangkat tambahan — tidak
     * ada baris `customer_additional_devices` yang cocok. Sinkronisasi harus
     * diam saja, bukan melempar exception atau menyentuh baris manapun.
     */
    #[Test]
    public function retrieving_primary_device_serial_does_not_touch_additional_device_rows(): void
    {
        $ap = $this->makeInstalledSerial($this->apItem, 'DRX-AP-UNTOUCHED');
        $device = CustomerAdditionalDevice::create([
            'customer_id' => $this->customer->id,
            'inventory_serial_id' => $ap->id,
            'device_type' => 'Access Point',
            'serial_number' => $ap->serial_number,
            'status' => 'terpasang',
        ]);

        $modemSerial = $this->makeInstalledSerial($this->modem, 'DRX-MODEM-PRIMARY');

        $this->submitReport(['serials' => [['serial_number' => 'DRX-MODEM-PRIMARY', 'item_id' => $this->modem->id]]])
            ->assertSessionHasNoErrors();

        $modemSerial->refresh();
        $this->assertEquals(SerialStatus::RETURNED, $modemSerial->status);

        $device->refresh();
        $this->assertSame('terpasang', $device->status);
        $this->assertNull($device->retrieved_at);
    }
}
