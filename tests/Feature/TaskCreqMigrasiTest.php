<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
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
use Tests\Concerns\BuildsPindahPopScenario;
use Tests\TestCase;

/**
 * Kategori C-REQ `MIGRASI` (ADHOC-108) — pindah lokasi LINTAS POP, beda dari
 * `PINDAH_LOKASI` yang POP-nya tetap sama. Laporan teknisi memilih POP
 * tujuan; task complete langsung memindah `pop_id` pelanggan lewat
 * `CustomerObserver`, jadi guard piutang (CLAUDE.md § Pindah Cabang) ikut
 * berlaku di jalur ini juga.
 */
class TaskCreqMigrasiTest extends TestCase
{
    use BuildsPindahPopScenario, RefreshDatabase;

    protected User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->setUpPindahPop();

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);
    }

    private function makeCreqTask($customer): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-MIGRASI-'.random_int(1000, 9999),
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'task_type' => TaskType::CREQ->value,
            'title' => 'Migrasi Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        return $task;
    }

    private function basePayload(): array
    {
        return [
            'kendala_teknis' => 'Pelanggan migrasi ke cabang baru.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
        ];
    }

    #[Test]
    public function migrasi_wajib_tikor_dan_pop_tujuan(): void
    {
        $customer = $this->pelangganAktifDiJetis();
        $task = $this->makeCreqTask($customer);

        $response = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'migrasi',
            ]);

        $response->assertSessionHasErrors([
            'creq_tikor_lama_lat', 'creq_tikor_lama_lng', 'creq_tikor_baru_lat', 'creq_tikor_baru_lng',
            'creq_target_pop_id',
        ]);
        $this->assertSame($this->jetis->id, (int) $customer->fresh()->pop_id);
    }

    #[Test]
    public function pop_tujuan_sama_dengan_pop_sekarang_ditolak(): void
    {
        $customer = $this->pelangganAktifDiJetis();
        $task = $this->makeCreqTask($customer);

        $response = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'migrasi',
                'creq_target_pop_id' => $this->jetis->id,
                'creq_tikor_lama_lat' => '-7.8654321',
                'creq_tikor_lama_lng' => '111.4567890',
                'creq_tikor_baru_lat' => '-7.8600000',
                'creq_tikor_baru_lng' => '111.4500000',
            ]);

        $response->assertSessionHasErrors('creq_target_pop_id');
        $this->assertSame($this->jetis->id, (int) $customer->fresh()->pop_id);
    }

    #[Test]
    public function migrasi_sukses_memindahkan_pop_pelanggan_dan_tersimpan_sebagai_pindah_lokasi(): void
    {
        $customer = $this->pelangganAktifDiJetis();
        $task = $this->makeCreqTask($customer);

        $response = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'migrasi',
                'creq_target_pop_id' => $this->sandya->id,
                'creq_tikor_lama_lat' => '-7.8654321',
                'creq_tikor_lama_lng' => '111.4567890',
                'creq_tikor_baru_lat' => '-7.8600000',
                'creq_tikor_baru_lng' => '111.4500000',
            ]);

        $response->assertSessionHasNoErrors();
        $task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $task->status);

        $this->assertSame($this->sandya->id, (int) $customer->fresh()->pop_id);
        // Kolektor dilepas, hierarki Mini POP/Distribusi ikut dilepas (CustomerObserver) —
        // efek samping standar pindah Cabang, bukan sesuatu yang perlu diduplikasi di sini.
        $this->assertNull($customer->fresh()->mini_pop_id);

        $detail = $task->creqDetail;
        $this->assertNotNull($detail);
        $this->assertSame('migrasi', $detail->category->value);
        $this->assertSame($this->sandya->id, $detail->target_pop_id);
        $this->assertSame('pindah_lokasi', $detail->category->toManualInvoiceCategory()->value);
    }

    #[Test]
    public function migrasi_ditolak_selama_pelanggan_masih_punya_piutang(): void
    {
        $customer = $this->pelangganAktifDiJetis();
        $this->tagihan($customer, self::BULAN_LALU, InvoiceStatus::BELUM_DIBAYAR);
        $task = $this->makeCreqTask($customer);

        $response = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'migrasi',
                'creq_target_pop_id' => $this->sandya->id,
                'creq_tikor_lama_lat' => '-7.8654321',
                'creq_tikor_lama_lng' => '111.4567890',
                'creq_tikor_baru_lat' => '-7.8600000',
                'creq_tikor_baru_lng' => '111.4500000',
            ]);

        $response->assertSessionHas('error');
        $this->assertSame($this->jetis->id, (int) $customer->fresh()->pop_id);
        $this->assertNotEquals(TaskStatus::SELESAI, $task->fresh()->status);
    }
}
