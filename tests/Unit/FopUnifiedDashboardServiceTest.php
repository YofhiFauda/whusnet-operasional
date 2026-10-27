<?php

namespace Tests\Unit;

use App\Enums\FopTaskPriority;
use App\Enums\MaterialKind;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMaterial;
use App\Models\TaskWorkTool;
use App\Models\Ticket;
use App\Models\User;
use App\Services\FopUnifiedDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FopUnifiedDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FopUnifiedDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FopUnifiedDashboardService::class);
    }

    #[Test]
    public function pipeline_summary_menghitung_jumlah_pelanggan_per_stage_sesuai_scope_pop(): void
    {
        $pop1 = Pop::create([
            'code' => 'POP1',
            'pop_code' => 'POP1',
            'name' => 'POP 1',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $pop2 = Pop::create([
            'code' => 'POP2',
            'pop_code' => 'POP2',
            'name' => 'POP 2',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        Customer::factory()->create(['pop_id' => $pop1->id, 'status' => 'waiting_survey']);
        Customer::factory()->create(['pop_id' => $pop1->id, 'status' => 'waiting_installation']);
        Customer::factory()->create(['pop_id' => $pop1->id, 'status' => 'verification_admin']);
        Customer::factory()->create(['pop_id' => $pop2->id, 'status' => 'waiting_survey']);

        $summary = $this->service->getCustomerPipelineSummary(false, [$pop1->id]);

        $this->assertEquals(1, $summary['waiting_survey']);
        $this->assertEquals(1, $summary['waiting_installation']);
        $this->assertEquals(1, $summary['verification_admin']);
        $this->assertEquals(3, $summary['total_in_delivery']);
        $this->assertEquals(0, $summary['survey_review']);
    }

    #[Test]
    public function pipeline_summary_mendukung_all_pop_access(): void
    {
        $pop1 = Pop::create(['code' => 'P1', 'pop_code' => 'P1', 'name' => 'P1', 'type' => 'cabang', 'status' => 'active']);
        $pop2 = Pop::create(['code' => 'P2', 'pop_code' => 'P2', 'name' => 'P2', 'type' => 'cabang', 'status' => 'active']);

        Customer::factory()->create(['pop_id' => $pop1->id, 'status' => 'waiting_survey']);
        Customer::factory()->create(['pop_id' => $pop2->id, 'status' => 'waiting_survey']);

        $summary = $this->service->getCustomerPipelineSummary(true, []);

        $this->assertEquals(2, $summary['waiting_survey']);
    }

    #[Test]
    public function hero_metrics_menghitung_persentase_dan_indikator_dengan_aman_saat_data_kosong(): void
    {
        $user = User::factory()->create();
        $stats = [
            'antrian_survey' => 0,
            'perlu_aksi_fop' => 0,
            'berjalan' => 0,
            'selesai_hari_ini' => 0,
            'total_hari_ini' => 0,
            'overdue_survey' => 0,
            'overdue_installation' => 0,
        ];

        $metrics = $this->service->getHeroMetrics($user, true, [], Carbon::today(), $stats, []);

        $this->assertIsArray($metrics);
        $this->assertEquals(0, $metrics['delivery_task']['progress_percent']);
        $this->assertEquals(100, $metrics['sla_health']['compliance_rate']);
    }

    #[Test]
    public function material_usage_detail_membedakan_pelanggan_dan_infrastruktur_serta_hormat_scope_pop(): void
    {
        $pop1 = Pop::create(['code' => 'PU1', 'pop_code' => 'PU1', 'name' => 'POP Usage 1', 'type' => 'cabang', 'status' => 'active']);
        $pop2 = Pop::create(['code' => 'PU2', 'pop_code' => 'PU2', 'name' => 'POP Usage 2', 'type' => 'cabang', 'status' => 'active']);
        $teknisi = User::factory()->create(['name' => 'Budi Teknisi']);
        $customer = Customer::factory()->create(['pop_id' => $pop1->id, 'full_name' => 'Pelanggan A']);

        $taskPelanggan = FopTask::create([
            'task_number' => 'TFOP-USAGE-001',
            'task_date' => now(),
            'category' => TaskType::PEMASANGAN->value,
            'tugas' => 'Pasang Baru',
            'pop_id' => $pop1->id,
            'customer_id' => $customer->id,
            'issue' => 'Pemasangan baru',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
        $taskPelanggan->technicians()->attach($teknisi->id);

        $taskInfra = FopTask::create([
            'task_number' => 'TFOP-USAGE-002',
            'task_date' => now(),
            'category' => TaskType::INFR->value,
            'tugas' => 'Perbaikan ODP',
            'pop_id' => $pop1->id,
            'customer_id' => null,
            'issue' => 'ODP rusak',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
        $taskInfra->technicians()->attach($teknisi->id);

        $taskLuarScope = FopTask::create([
            'task_number' => 'TFOP-USAGE-003',
            'task_date' => now(),
            'category' => TaskType::PEMASANGAN->value,
            'tugas' => 'Pasang Baru Luar Scope',
            'pop_id' => $pop2->id,
            'customer_id' => null,
            'issue' => 'Pemasangan',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        TaskMaterial::create([
            'fop_task_id' => $taskPelanggan->id,
            'customer_id' => $customer->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_type' => 'kabel',
            'item_name' => 'Drop Core',
            'qty' => 20,
            'unit' => 'm',
        ]);

        TaskWorkTool::create([
            'fop_task_id' => $taskInfra->id,
            'customer_id' => null,
            'tool_name' => 'Fusion Splicer',
        ]);

        TaskMaterial::create([
            'fop_task_id' => $taskLuarScope->id,
            'customer_id' => null,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_type' => 'lainnya',
            'item_name' => 'Barang Luar Scope',
            'qty' => 1,
            'unit' => 'pcs',
        ]);

        $rows = $this->service->getMaterialUsageDetail(false, [$pop1->id], Carbon::today());

        $this->assertCount(2, $rows);

        $materialRow = $rows->firstWhere('label', 'Drop Core (20 m)');
        $this->assertNotNull($materialRow);
        $this->assertSame('pelanggan', $materialRow['target_type']);
        $this->assertSame('Pelanggan A', $materialRow['target_label']);
        $this->assertSame(['Budi Teknisi'], $materialRow['technicians']);

        $toolRow = $rows->firstWhere('label', 'Fusion Splicer');
        $this->assertNotNull($toolRow);
        $this->assertSame('infrastruktur', $toolRow['target_type']);
        $this->assertSame('Perbaikan ODP', $toolRow['target_label']);
    }

    /**
     * Regresi — "SLA Compliance %" dulu rasio buatan (campur survey queue +
     * perlu aksi + task hari ini jadi satu pembagi, ADHOC audit 2026-10-12).
     * Sekarang breach rate sungguhan: tiket `isSlaBreached()` + task hari ini
     * `isOverSla()`. Fixture: 1 tiket breach + 1 tiket aman, 1 task SURVEY
     * overdue (scheduled kemarin) + 1 task aman (scheduled besok) → populasi
     * 4, breach 2 → compliance 50%.
     */
    #[Test]
    public function sla_compliance_rate_dihitung_dari_breach_sungguhan_bukan_campuran_antrean(): void
    {
        $ownerRole = Role::firstOrCreate(['code' => 'owner'], ['name' => 'Owner']);
        $user = User::factory()->create(['role_id' => $ownerRole->id]);
        $pop = Pop::create(['code' => 'SLA1', 'pop_code' => 'SLA1', 'name' => 'POP SLA', 'type' => 'cabang', 'status' => 'active']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        Ticket::create([
            'ticket_number' => 'TKT-SLA-BREACH',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'detail_keluhan' => 'Breach',
            'priority' => 'High',
            'handler' => 'helpdesk',
            'status' => 'open',
            'created_by' => $user->id,
            'sla_deadline_at' => now()->subHour(),
        ]);
        Ticket::create([
            'ticket_number' => 'TKT-SLA-OK',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'detail_keluhan' => 'Aman',
            'priority' => 'High',
            'handler' => 'helpdesk',
            'status' => 'open',
            'created_by' => $user->id,
            'sla_deadline_at' => now()->addDay(),
        ]);

        Task::create([
            'task_number' => 'TASK-SLA-OVERDUE',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Overdue',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->subDay(),
        ]);
        Task::create([
            'task_number' => 'TASK-SLA-OK',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Aman',
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now()->addDay(),
        ]);

        $metrics = $this->service->getHeroMetrics($user, true, [], Carbon::today(), [], []);

        $this->assertSame(50, $metrics['sla_health']['compliance_rate']);
        $this->assertSame(1, $metrics['sla_health']['total_overdue']);
    }

    /**
     * Regresi — "Armada Teknisi Utilization%" dulu ikut hitung teknisi
     * TERJADWAL (belum mulai kerja) sebagai "utilized" (ADHOC audit
     * 2026-10-12: 3 aktif + 2 terjadwal dari 10 = 50%, padahal cuma 3 yang
     * beneran kerja). Sekarang murni aktif/total.
     */
    #[Test]
    public function armada_teknisi_utilization_hanya_hitung_yang_aktif(): void
    {
        $user = User::factory()->create();
        $teknisiList = collect([
            ['status' => 'aktif'], ['status' => 'aktif'], ['status' => 'aktif'],
            ['status' => 'terjadwal'], ['status' => 'terjadwal'],
            ['status' => 'standby'], ['status' => 'standby'], ['status' => 'standby'],
            ['status' => 'standby'], ['status' => 'standby'],
        ]);

        $metrics = $this->service->getHeroMetrics($user, true, [], Carbon::today(), [], $teknisiList);

        $this->assertSame(30, $metrics['fleet_tech']['utilization_percent']);
    }
}
