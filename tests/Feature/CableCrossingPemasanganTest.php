<?php

namespace Tests\Feature;

use App\Enums\CableCrossingStatus;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerCableCrossing;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-205 — rute kabel melintang jalan dicatat di form Laporan Pemasangan
 * (storePemasangan). Rute baru wajib foto dan minimal 2 titik koordinat; kalau
 * checkbox tidak dicentang, rute yang ikut terkirim diabaikan.
 */
class CableCrossingPemasanganTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);
    }

    /**
     * @return array{0: Customer, 1: User, 2: Task, 3: Pop, 4: Pop}
     */
    private function setupInProgressInstallation(): array
    {
        $pusat = Pop::create(['code' => 'PUSAT-CCP', 'pop_code' => 'PCP', 'registration_prefix' => 'C', 'cid_prefix' => 'F', 'name' => 'Pusat Kabel Melintang', 'type' => 'pusat', 'status' => 'active']);
        $pop = Pop::create(['code' => 'CABANG-CCP', 'pop_code' => 'CCP', 'registration_prefix' => 'C', 'cid_prefix' => 'G', 'name' => 'Cabang Kabel Melintang', 'type' => 'cabang', 'status' => 'active']);

        $technician = User::factory()->create();
        $role = Role::where('name', 'Teknisi')->firstOrFail();
        $technician->role_id = $role->id;
        $technician->save();
        $technician->load('role');
        $technician->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        $customer = Customer::create([
            'customer_code' => 'TEST-CCP-001',
            'full_name' => 'Kabel Melintang Test Customer',
            'primary_phone' => '0812340003',
            'status' => 'installation_in_progress',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $customer->installations()->create([
            'installation_status' => 'in_progress',
            'started_at' => now(),
            'start_time' => now()->toTimeString(),
            'scheduled_date' => now()->format('Y-m-d'),
            'scheduled_time' => '09:00',
            'technician_id' => $technician->id,
        ]);

        $task = Task::create([
            'task_number' => 'TASK-TEST-CCP-001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Kabel Melintang Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        return [$customer, $technician, $task, $pusat, $pop];
    }

    private function issueActiveSerialTo(User $technician, Pop $pusat, Pop $cabang): int
    {
        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $ont = Item::create([
            'code' => 'ONT-CCP-'.uniqid(), 'name' => 'ONT Kabel Melintang', 'item_category_id' => $catAktif->id,
            'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
        $sn = 'ZTECCP-'.uniqid();
        $admin = User::factory()->create();

        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $ont, [$sn], 250000, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $ont->id, 'serial_numbers' => [$sn]]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [$sn], [], $admin);
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $ont->id, 'serial_numbers' => [$sn]]], $admin);

        return $serial->refresh()->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(User $technician, Pop $pusat, Pop $cabang): array
    {
        return [
            'device_type' => 'ont',
            'connection_mode' => 'pppoe',
            'wifi_ssid' => 'WHUSNET_CCP_TEST',
            'wifi_password' => 'password123',
            'selected_inventory_serial_id' => $this->issueActiveSerialTo($technician, $pusat, $cabang),
            'odp_number' => 'ODP-01',
            'odp_port' => '1',
            'olt_number' => 'OLT-01',
            'olt_slot' => '1',
            'olt_port' => '1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ruteValid(array $overrides = []): array
    {
        return array_merge([
            'nama_jalan' => 'Jl. Raya Ponorogo',
            'jenis_jalan' => 'Jalan raya',
            'foto' => UploadedFile::fake()->image('bukti-rute.jpg'),
            'points' => [
                ['label' => 'Tiang TR-12', 'latitude' => '-7.8711000', 'longitude' => '111.4620000'],
                ['label' => 'Rumah pelanggan', 'latitude' => '-7.8730000', 'longitude' => '111.4640000'],
            ],
        ], $overrides);
    }

    #[Test]
    public function rute_kabel_melintang_dengan_dua_titik_tersimpan_saat_pemasangan(): void
    {
        [$customer, $technician, , $pusat, $pop] = $this->setupInProgressInstallation();

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload($technician, $pusat, $pop) + [
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'cable_crossing' => '1',
            'cable_crossings' => [0 => $this->ruteValid()],
        ])->assertSessionHasNoErrors();

        $crossing = CustomerCableCrossing::sole();
        $this->assertEquals(CableCrossingStatus::TERPASANG, $crossing->status);
        $this->assertEquals($customer->id, $crossing->customer_id);
        $this->assertEquals($customer->installations()->latest()->first()->id, $crossing->customer_installation_id);
        $this->assertEquals($technician->id, $crossing->dicatat_oleh);
        $this->assertCount(2, $crossing->points);
        Storage::disk('public')->assertExists($crossing->foto_bukti);
    }

    #[Test]
    public function rute_kabel_melintang_dengan_satu_titik_ditolak(): void
    {
        [$customer, $technician, , $pusat, $pop] = $this->setupInProgressInstallation();

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload($technician, $pusat, $pop) + [
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'cable_crossing' => '1',
            'cable_crossings' => [0 => $this->ruteValid(['points' => [
                ['label' => 'Tiang TR-12', 'latitude' => '-7.8711000', 'longitude' => '111.4620000'],
            ]])],
        ])->assertSessionHasErrors('cable_crossings.0.points');

        $this->assertEquals(0, CustomerCableCrossing::count());
    }

    #[Test]
    public function rute_kabel_melintang_baru_tanpa_foto_ditolak(): void
    {
        [$customer, $technician, , $pusat, $pop] = $this->setupInProgressInstallation();

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload($technician, $pusat, $pop) + [
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'cable_crossing' => '1',
            'cable_crossings' => [0 => $this->ruteValid(['foto' => null])],
        ])->assertSessionHasErrors('cable_crossings');

        $this->assertEquals(0, CustomerCableCrossing::count());
    }

    #[Test]
    public function koordinat_di_luar_rentang_ditolak(): void
    {
        [$customer, $technician, , $pusat, $pop] = $this->setupInProgressInstallation();

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload($technician, $pusat, $pop) + [
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'cable_crossing' => '1',
            'cable_crossings' => [0 => $this->ruteValid(['points' => [
                ['label' => 'Tiang', 'latitude' => '-95', 'longitude' => '111.4620000'],
                ['label' => 'Rumah', 'latitude' => '-7.8730000', 'longitude' => '111.4640000'],
            ]])],
        ])->assertSessionHasErrors('cable_crossings.0.points.0.latitude');

        $this->assertEquals(0, CustomerCableCrossing::count());
    }

    #[Test]
    public function rute_yang_ikut_terkirim_tanpa_centang_diabaikan(): void
    {
        [$customer, $technician, , $pusat, $pop] = $this->setupInProgressInstallation();

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload($technician, $pusat, $pop) + [
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'cable_crossings' => [0 => ['nama_jalan' => '', 'points' => [['latitude' => '', 'longitude' => '']]]],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(0, CustomerCableCrossing::count());
    }

    #[Test]
    public function halaman_laporan_pemasangan_menampilkan_rute_yang_sudah_tersimpan(): void
    {
        [$customer, $technician, , $pusat, $pop] = $this->setupInProgressInstallation();

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload($technician, $pusat, $pop) + [
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'cable_crossing' => '1',
            'cable_crossings' => [0 => $this->ruteValid(['nama_jalan' => 'Jl. Tersimpan Form'])],
        ])->assertSessionHasNoErrors();

        $crossing = CustomerCableCrossing::sole();

        $this->actingAs($technician)
            ->get(route('customers.installation.report', $customer->id))
            ->assertOk()
            ->assertSee('Jl. Tersimpan Form')
            // Nama input dibentuk Alpine di browser; yang dirender server adalah data rows-nya.
            // @js meng-escape kutip, jadi kunci `id` dicek lewat nilainya saja.
            ->assertSee('u0022id', false)
            ->assertSee('u0022:'.$crossing->id, false);
    }

    #[Test]
    public function menghapus_centang_di_form_melepas_rute_yang_sudah_tercatat(): void
    {
        [$customer, $technician, , $pusat, $pop] = $this->setupInProgressInstallation();

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload($technician, $pusat, $pop) + [
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'cable_crossing' => '1',
            'cable_crossings' => [0 => $this->ruteValid()],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(1, CustomerCableCrossing::count());

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload($technician, $pusat, $pop) + [
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
        ])->assertSessionHasNoErrors();

        $this->assertEquals(0, CustomerCableCrossing::count());
    }
}
