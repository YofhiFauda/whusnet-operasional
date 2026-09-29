<?php

namespace Tests\Feature;

use App\Enums\CReqVerificationStatus;
use App\Enums\FopTaskPriority;
use App\Enums\MaterialKind;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Task;
use App\Models\TaskCreqDetail;
use App\Models\TaskMaterial;
use App\Models\TaskWorkTool;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\CreqBillingVerificationFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gejala: laporan C-REQ yang dikirim teknisi tidak tampil lengkap di Detail
 * Task, Riwayat Task FOP, dan halaman Verifikasi Biaya — Jenis Permintaan &
 * tikor hilang dari Detail Task/Riwayat FOP, material/alat kerja hilang dari
 * Verifikasi, alat kerja hilang dari Detail Task, kode roll kabel tidak
 * pernah tampil. Ketiga halaman kini wajib menampilkan isi laporan yang sama.
 */
class CreqReportIncompleteOnDetailPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Task $task;

    private FopTask $fopTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(CreqBillingVerificationFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->owner = $this->loginAsAdmin();
        $this->giveAllPopScope($this->owner);

        $pop = Pop::create(['code' => 'CRQ-RPT', 'pop_code' => 'CRR', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Laporan C-REQ', 'type' => 'cabang', 'status' => 'active']);

        $customer = Customer::create([
            'customer_code' => 'TEST-CREQ-RPT-001',
            'full_name' => 'Pelanggan Laporan C-REQ',
            'primary_phone' => '0812340077',
            'status' => 'active',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $this->task = Task::create([
            'task_number' => 'TASK-CREQ-RPT-0001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::CREQ->value,
            'title' => 'Customer Request Laporan Lengkap',
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => now(),
            'completed_by' => $this->owner->id,
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ]);

        $this->task->maintenanceReport()->create([
            'kendala_teknis' => 'Pelanggan minta router dipindah ke lantai dua.',
        ]);

        TaskCreqDetail::create([
            'task_id' => $this->task->id,
            'category' => 'pindah_lokasi',
            'tikor_lama_lat' => '-7.8654321',
            'tikor_lama_lng' => '111.4567890',
            'tikor_baru_lat' => '-7.8600000',
            'tikor_baru_lng' => '111.4500000',
            'is_billable' => true,
            'billing_note' => 'Biaya tarik kabel tambahan 30 meter.',
            'verification_status' => CReqVerificationStatus::PENDING->value,
        ]);

        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-CREQ-RPT-0001',
            'task_date' => now(),
            'category' => TaskType::CREQ->value,
            'task_id' => $this->task->id,
            'tugas' => 'TEST-CREQ-RPT-001_Pelanggan Laporan C-REQ',
            'pop_id' => $pop->id,
            'customer_id' => $customer->id,
            'issue' => 'Pindah router',
            'status' => TaskStatus::SELESAI->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        TaskMaterial::create([
            'fop_task_id' => $this->fopTask->id,
            'kind' => MaterialKind::TERPAKAI,
            'item_type' => 'lainnya',
            'item_name' => 'Kabel Dropcore 1 Core',
            'lot_no' => 'RL-CREQ-0001',
            'qty' => 30,
            'unit' => 'meter',
            'recorded_by' => $this->owner->id,
        ]);

        TaskWorkTool::create([
            'fop_task_id' => $this->fopTask->id,
            'tool_name' => 'Splicer Fujikura',
            'recorded_by' => $this->owner->id,
        ]);
    }

    #[Test]
    public function detail_task_menampilkan_jenis_permintaan_tikor_alat_kerja_dan_roll(): void
    {
        $response = $this->get(route('tasks.show', $this->task));

        $response->assertOk();
        $response->assertSee('Detail C-REQ');
        $response->assertSee('Pindah Lokasi');
        $response->assertSee('-7.8654321');
        $response->assertSee('Biaya tarik kabel tambahan 30 meter.');
        $response->assertSee('Roll RL-CREQ-0001');
        $response->assertSee('Splicer Fujikura');
    }

    #[Test]
    public function riwayat_task_fop_menampilkan_jenis_permintaan_tikor_dan_roll(): void
    {
        $response = $this->get(route('fop-tasks.history.show', $this->fopTask));

        $response->assertOk();
        $response->assertSee('Detail C-REQ');
        $response->assertSee('Pindah Lokasi');
        $response->assertSee('-7.8600000');
        $response->assertSee('Roll RL-CREQ-0001');
        $response->assertSee('Splicer Fujikura');
    }

    #[Test]
    public function verifikasi_biaya_menampilkan_material_roll_dan_alat_kerja(): void
    {
        $response = $this->get(route('tasks.creq-billing.show', $this->task));

        $response->assertOk();
        $response->assertSee('Pelanggan minta router dipindah ke lantai dua.');
        $response->assertSee('Kabel Dropcore 1 Core');
        $response->assertSee('Roll RL-CREQ-0001');
        $response->assertSee('Splicer Fujikura');
    }
}
