<?php

namespace Tests\Feature;

use App\Enums\CableCrossingStatus;
use App\Enums\DeviceRetrievalOutcome;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerCableCrossing;
use App\Models\CustomerCableCrossingPoint;
use App\Models\InventoryTransaction;
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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-205 — task DEAC tidak boleh ditutup selama masih ada rute kabel
 * melintang jalan yang `terpasang`. Hasil penarikan (ditarik / tidak bisa
 * ditarik) wajib diisi per rute, dan penarikan tidak mengubah stok.
 * Rancangan: docs/customer-lifecycle/rancangan-kabel-melintang.md.
 */
class CableCrossingDeactTest extends TestCase
{
    use RefreshDatabase;

    private User $teknisi;

    private Pop $pop;

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

        $owner = User::factory()->create(['role_id' => $ownerRole->id]);
        $owner->roleScopes()->create(['role_id' => $ownerRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->pop = Pop::create(['code' => 'CC-POP', 'pop_code' => 'CCP', 'registration_prefix' => 'C', 'cid_prefix' => 'C', 'name' => 'POP Kabel Melintang', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::factory()->create(['pop_id' => $this->pop->id, 'status' => 'terminated']);
        $this->task = $this->makeDeacTask($this->customer, $owner);
    }

    private function makeDeacTask(Customer $customer, User $creator): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-CC-'.random_int(1000, 9999),
            'task_type' => TaskType::AMBIL_MODEM->value,
            'title' => 'Ambil Modem '.$customer->id,
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        TaskTeam::create(['task_id' => $task->id, 'user_id' => $this->teknisi->id, 'role_in_task' => 'lead']);

        return $task;
    }

    private function makeTerpasangRute(string $namaJalan = 'Jl. Raya Test'): CustomerCableCrossing
    {
        $crossing = CustomerCableCrossing::factory()->create([
            'customer_id' => $this->customer->id,
            'nama_jalan' => $namaJalan,
        ]);

        CustomerCableCrossingPoint::factory()->create(['customer_cable_crossing_id' => $crossing->id, 'urutan' => 1]);
        CustomerCableCrossingPoint::factory()->create(['customer_cable_crossing_id' => $crossing->id, 'urutan' => 2, 'latitude' => -7.6, 'longitude' => 111.9]);

        return $crossing;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submitDeact(array $overrides = [])
    {
        $payload = array_merge([
            'outcome' => DeviceRetrievalOutcome::TIDAK_DITEMUKAN->value,
            'notes' => 'Pelanggan tidak di rumah saat penarikan.',
        ], $overrides);

        return $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), $payload);
    }

    #[Test]
    public function deact_tidak_bisa_ditutup_selama_rute_kabel_belum_diisi_hasilnya(): void
    {
        $crossing = $this->makeTerpasangRute();

        $this->submitDeact()
            ->assertSessionHasErrors("cable_crossings.{$crossing->id}.status");

        $this->assertEquals(TaskStatus::IN_PROGRESS, $this->task->refresh()->status);
        $this->assertEquals(CableCrossingStatus::TERPASANG, $crossing->refresh()->status);
    }

    #[Test]
    public function rute_yang_ditarik_dengan_foto_menutup_deact_tanpa_mengubah_stok(): void
    {
        $crossing = $this->makeTerpasangRute();
        $stokSebelum = InventoryTransaction::count();

        $this->submitDeact([
            'cable_crossings' => [
                $crossing->id => [
                    'status' => CableCrossingStatus::DITARIK->value,
                    'foto' => UploadedFile::fake()->image('tarik.jpg'),
                ],
            ],
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('tasks.show', $this->task));

        $crossing->refresh();
        $this->assertEquals(CableCrossingStatus::DITARIK, $crossing->status);
        $this->assertEquals($this->task->id, $crossing->deact_task_id);
        $this->assertNotNull($crossing->diselesaikan_at);
        $this->assertNull($crossing->alasan_tidak_ditarik);
        Storage::disk('public')->assertExists($crossing->foto_penarikan);

        $this->assertEquals(TaskStatus::SELESAI, $this->task->refresh()->status);

        // Kabel yang sudah keluar/dibeli tidak kembali ke gudang.
        $this->assertEquals($stokSebelum, InventoryTransaction::count());
    }

    #[Test]
    public function rute_ditarik_tanpa_foto_penarikan_ditolak(): void
    {
        $crossing = $this->makeTerpasangRute();

        $this->submitDeact([
            'cable_crossings' => [
                $crossing->id => ['status' => CableCrossingStatus::DITARIK->value],
            ],
        ])->assertSessionHasErrors("cable_crossings.{$crossing->id}.foto");

        $this->assertEquals(CableCrossingStatus::TERPASANG, $crossing->refresh()->status);
        $this->assertEquals(TaskStatus::IN_PROGRESS, $this->task->refresh()->status);
    }

    #[Test]
    public function rute_tidak_bisa_ditarik_tanpa_alasan_ditolak(): void
    {
        $crossing = $this->makeTerpasangRute();

        $this->submitDeact([
            'cable_crossings' => [
                $crossing->id => ['status' => CableCrossingStatus::TIDAK_BISA_DITARIK->value, 'alasan' => ''],
            ],
        ])->assertSessionHasErrors("cable_crossings.{$crossing->id}.alasan");

        $this->assertEquals(CableCrossingStatus::TERPASANG, $crossing->refresh()->status);
    }

    #[Test]
    public function rute_tidak_bisa_ditarik_dengan_alasan_menutup_deact_dan_tetap_terpantau(): void
    {
        $crossing = $this->makeTerpasangRute();

        $this->submitDeact([
            'cable_crossings' => [
                $crossing->id => [
                    'status' => CableCrossingStatus::TIDAK_BISA_DITARIK->value,
                    'alasan' => 'Kabel tertanam di aspal, perlu galian.',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $crossing->refresh();
        $this->assertEquals(CableCrossingStatus::TIDAK_BISA_DITARIK, $crossing->status);
        $this->assertEquals('Kabel tertanam di aspal, perlu galian.', $crossing->alasan_tidak_ditarik);
        $this->assertNull($crossing->foto_penarikan);
        $this->assertEquals(TaskStatus::SELESAI, $this->task->refresh()->status);

        // Tidak lagi dihitung terbuka sebagai penghalang, tapi masih ada di daftar pemantauan.
        $this->assertTrue($crossing->status->isOpen());
    }

    #[Test]
    public function deact_tanpa_rute_kabel_tetap_bisa_ditutup_seperti_biasa(): void
    {
        $this->submitDeact()
            ->assertSessionHasNoErrors();

        $this->assertEquals(TaskStatus::SELESAI, $this->task->refresh()->status);
    }

    #[Test]
    public function rute_kabel_pelanggan_lain_tidak_ikut_menghalangi_deact(): void
    {
        $otherCustomer = Customer::factory()->create(['pop_id' => $this->pop->id, 'status' => 'terminated']);
        CustomerCableCrossing::factory()->create(['customer_id' => $otherCustomer->id]);

        $this->submitDeact()
            ->assertSessionHasNoErrors();

        $this->assertEquals(TaskStatus::SELESAI, $this->task->refresh()->status);
    }

    #[Test]
    public function halaman_laporan_deact_menampilkan_rute_yang_belum_selesai(): void
    {
        $crossing = $this->makeTerpasangRute('Jl. Pahlawan Lapangan');

        $this->actingAs($this->teknisi)
            ->get(route('tasks.device-retrieval.report', $this->task))
            ->assertOk()
            ->assertSee('Kabel Melintang Jalan')
            ->assertSee('Jl. Pahlawan Lapangan')
            ->assertSee("cable_crossings[{$crossing->id}][status]", false);
    }
}
