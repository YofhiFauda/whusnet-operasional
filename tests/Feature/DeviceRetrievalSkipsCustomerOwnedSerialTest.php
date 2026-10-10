<?php

namespace Tests\Feature;

use App\Enums\DeviceAcquisitionSource;
use App\Enums\DeviceOwnership;
use App\Enums\DeviceRetrievalOutcome;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskTeam;
use App\Models\User;
use App\Services\InventoryReceiveService;
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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Task 7 "DEAC skip SN milik pelanggan" — modem yang sudah dijual ke
 * pelanggan (`ownership=customer`, Beli dari Gudang/Beli Sendiri) bukan aset
 * perusahaan lagi, jadi tidak boleh ditagih balik lewat form Ambil Modem
 * (DEAC). SN `ownership=company` tetap wajib ditarik seperti biasa.
 */
class DeviceRetrievalSkipsCustomerOwnedSerialTest extends TestCase
{
    use RefreshDatabase;

    private User $teknisi;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

    private Item $modem;

    private Customer $customer;

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

        $this->pusat = Pop::create(['code' => 'DO-PUSAT', 'pop_code' => 'DOP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Ownership Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'DO-CABANG', 'pop_code' => 'DOC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Ownership Test', 'type' => 'cabang', 'status' => 'active']);

        $cat = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $this->modem = Item::create(['code' => 'DO-MODEM', 'name' => 'Modem Ownership Test', 'item_category_id' => $cat->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        $this->customer = Customer::factory()->create(['pop_id' => $this->cabang->id, 'status' => 'terminated']);
    }

    private function makeTask(): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-2026-'.random_int(100000, 999999),
            'task_type' => TaskType::AMBIL_MODEM->value,
            'title' => 'Ambil Modem '.$this->customer->id,
            'pop_id' => $this->cabang->id,
            'customer_id' => $this->customer->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ]);

        TaskTeam::create(['task_id' => $task->id, 'user_id' => $this->teknisi->id, 'role_in_task' => 'lead']);

        return $task;
    }

    private function makeInstalledSerial(string $sn, DeviceOwnership $ownership): InventorySerial
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, [$sn], 250000, $this->owner);

        $serial->update([
            'status' => SerialStatus::INSTALLED,
            'issued_from_pop_id' => $this->cabang->id,
            'current_pop_id' => null,
            'customer_id' => $this->customer->id,
            'ownership' => $ownership,
        ]);

        return $serial;
    }

    #[Test]
    public function sn_ownership_customer_tidak_muncul_di_installed_serials(): void
    {
        $customerOwnedSerial = $this->makeInstalledSerial('OWN-CUST-001', DeviceOwnership::CUSTOMER);
        $companyOwnedSerial = $this->makeInstalledSerial('OWN-COMP-001', DeviceOwnership::COMPANY);
        $task = $this->makeTask();

        $response = $this->actingAs($this->teknisi)->get(route('tasks.device-retrieval.report', $task));

        $response->assertOk();
        $response->assertViewHas('installedSerials', function ($serials) use ($customerOwnedSerial, $companyOwnedSerial) {
            return $serials->pluck('id')->doesntContain($customerOwnedSerial->id)
                && $serials->pluck('id')->contains($companyOwnedSerial->id);
        });
        $response->assertViewHas('showOwnershipSkipBanner', false);
    }

    #[Test]
    public function semua_sn_customer_owned_tampilkan_banner_dan_submit_otomatis_diambil_tanpa_serial(): void
    {
        $this->makeInstalledSerial('OWN-CUST-ALL', DeviceOwnership::CUSTOMER);
        $task = $this->makeTask();

        $reportResponse = $this->actingAs($this->teknisi)->get(route('tasks.device-retrieval.report', $task));

        $reportResponse->assertOk();
        $reportResponse->assertViewHas('installedSerials', fn ($serials) => $serials->isEmpty());
        $reportResponse->assertViewHas('showOwnershipSkipBanner', true);
        $reportResponse->assertSee('tidak perlu ditarik');

        $storeResponse = $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $task), [
            'outcome' => DeviceRetrievalOutcome::DIAMBIL->value,
            'serials' => [],
            'condition_photo' => UploadedFile::fake()->image('kondisi.jpg'),
        ]);

        $storeResponse->assertSessionHasNoErrors();
        $storeResponse->assertRedirect(route('tasks.show', $task));

        $this->assertEquals(TaskStatus::SELESAI, $task->refresh()->status);
        $this->assertEquals(DeviceRetrievalOutcome::DIAMBIL, $task->deviceRetrieval->outcome);
        $this->assertEquals('Modem milik pelanggan, tidak perlu ditarik', $task->deviceRetrieval->notes);
    }

    /**
     * Filter daftar hint saja tidak cukup — SN milik pelanggan yang DIKETIK
     * manual tetap harus ditolak di jalur tulis (pickupSerialFromCustomer()).
     */
    #[Test]
    public function sn_milik_pelanggan_diketik_manual_ditolak_dan_tidak_berubah(): void
    {
        $customerOwnedSerial = $this->makeInstalledSerial('OWN-CUST-TYPED', DeviceOwnership::CUSTOMER);
        $task = $this->makeTask();

        $response = $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $task), [
            'outcome' => DeviceRetrievalOutcome::DIAMBIL->value,
            'serials' => [['serial_number' => 'OWN-CUST-TYPED']],
            'condition_photo' => UploadedFile::fake()->image('kondisi.jpg'),
        ]);

        $response->assertSessionHasErrors('serials');
        $this->assertStringContainsString('milik pelanggan', session('errors')->first('serials'));

        $customerOwnedSerial->refresh();
        $this->assertEquals(SerialStatus::INSTALLED, $customerOwnedSerial->status);
        $this->assertEquals($this->customer->id, $customerOwnedSerial->customer_id);
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->refresh()->status);
    }

    /**
     * Pelanggan "Beli Sendiri" yang TIDAK PERNAH punya baris inventory_serials
     * sama sekali (rancangan §6) — tidak ada yang bisa ditarik, jadi jalurnya
     * konfirmasi, bukan "gagal diambil + alasan".
     */
    #[Test]
    public function pelanggan_beli_sendiri_tanpa_sn_sama_sekali_tampilkan_banner_dan_boleh_submit_tanpa_serial(): void
    {
        $this->customer->customerDevice()->create([
            'device_type' => 'modem',
            'brand' => 'TP-Link',
            'model' => 'Archer C6',
            'serial_number' => 'MANUAL-BS-001',
            'acquisition_source' => DeviceAcquisitionSource::BELI_SENDIRI,
        ]);
        $task = $this->makeTask();

        $this->assertSame(0, InventorySerial::where('customer_id', $this->customer->id)->count());

        $this->actingAs($this->teknisi)->get(route('tasks.device-retrieval.report', $task))
            ->assertOk()
            ->assertViewHas('showOwnershipSkipBanner', true);

        $storeResponse = $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $task), [
            'outcome' => DeviceRetrievalOutcome::DIAMBIL->value,
            'serials' => [],
            'condition_photo' => UploadedFile::fake()->image('kondisi.jpg'),
        ]);

        $storeResponse->assertSessionHasNoErrors();
        $this->assertEquals(TaskStatus::SELESAI, $task->refresh()->status);
        $this->assertEquals('Modem milik pelanggan, tidak perlu ditarik', $task->deviceRetrieval->notes);
    }

    /**
     * Pelanggan tanpa SN dan tanpa catatan Beli Sendiri (mis. Sewa legacy yang
     * SN-nya belum tercatat) TIDAK dilonggarkan — tetap wajib isi SN.
     */
    #[Test]
    public function pelanggan_sewa_tanpa_sn_tercatat_tetap_tanpa_banner(): void
    {
        $this->customer->customerDevice()->create([
            'device_type' => 'modem',
            'brand' => 'ZTE',
            'model' => 'F609',
            'serial_number' => 'LEGACY-SEWA-001',
            'acquisition_source' => DeviceAcquisitionSource::SEWA,
        ]);
        $task = $this->makeTask();

        $this->actingAs($this->teknisi)->get(route('tasks.device-retrieval.report', $task))
            ->assertOk()
            ->assertViewHas('showOwnershipSkipBanner', false);

        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $task), [
            'outcome' => DeviceRetrievalOutcome::DIAMBIL->value,
            'serials' => [],
            'condition_photo' => UploadedFile::fake()->image('kondisi.jpg'),
        ])->assertSessionHasErrors('serials');
    }

    #[Test]
    public function campuran_ownership_tetap_wajib_tarik_sn_company_owned(): void
    {
        $this->makeInstalledSerial('OWN-CUST-MIX', DeviceOwnership::CUSTOMER);
        $companyOwnedSerial = $this->makeInstalledSerial('OWN-COMP-MIX', DeviceOwnership::COMPANY);
        $task = $this->makeTask();

        $reportResponse = $this->actingAs($this->teknisi)->get(route('tasks.device-retrieval.report', $task));

        $reportResponse->assertViewHas('showOwnershipSkipBanner', false);
        $reportResponse->assertViewHas('installedSerials', fn ($serials) => $serials->count() === 1 && $serials->first()->id === $companyOwnedSerial->id);

        // Submit tanpa SN sama sekali harus TETAP gagal validasi — masih ada
        // modem milik perusahaan yang wajib ditarik.
        $storeResponse = $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $task), [
            'outcome' => DeviceRetrievalOutcome::DIAMBIL->value,
            'serials' => [],
            'condition_photo' => UploadedFile::fake()->image('kondisi.jpg'),
        ]);

        $storeResponse->assertSessionHasErrors('serials');
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->refresh()->status);
    }
}
