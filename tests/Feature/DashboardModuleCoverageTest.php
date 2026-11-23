<?php

namespace Tests\Feature;

use App\Enums\BillingWaiverSource;
use App\Enums\CableCrossingStatus;
use App\Enums\CReqCategory;
use App\Enums\CReqVerificationStatus;
use App\Enums\IsolationCaseStatus;
use App\Enums\IsolationTriggerType;
use App\Enums\PaymentMethod;
use App\Enums\PengajuanBarangStatus;
use App\Enums\ScopeType;
use App\Enums\StockRequestStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\VisitResult;
use App\Models\Agent;
use App\Models\CollectorVisit;
use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerBillingWaiver;
use App\Models\CustomerCableCrossing;
use App\Models\CustomerPackageChange;
use App\Models\CustomerService;
use App\Models\CustomerTerminationReason;
use App\Models\InternetPackage;
use App\Models\IsolationCase;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Payment;
use App\Models\PengajuanBarang;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\StockRequest;
use App\Models\Task;
use App\Models\TaskCreqDetail;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dashboard Owner Fase 2 (ADHOC-313, docs/plan/analisa-dashboard-owner-fase-2.md):
 * 13 kartu modul yang sebelumnya sama sekali tidak terlihat di dashboard.
 * Fokus test: angka benar, gerbang permission halaman modul ditaati (kartu
 * gak dihitung sama sekali kalau gerbang tertutup), dan POP scope gak bocor.
 */
class DashboardModuleCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Pop $otherPop;

    private User $owner;

    private InternetPackage $package;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(now()->parse('2026-11-15 10:00:00'));

        $this->pop = $this->makePop('MOD1');
        $this->otherPop = $this->makePop('MOD2');
        $this->package = InternetPackage::query()->firstOrFail();

        $this->owner = User::factory()->create([
            'role_id' => Role::where('name', 'Owner')->firstOrFail()->id,
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    // ── P1 ──────────────────────────────────────────────────────────────

    #[Test]
    public function kasus_isolir_dihitung_per_tahap_antrean_dan_gagal_masuk_action_center(): void
    {
        $this->makeIsolationCase(IsolationCaseStatus::WAITING_ISOLATION);
        $this->makeIsolationCase(IsolationCaseStatus::READY_FOR_ISOLATION);
        $this->makeIsolationCase(IsolationCaseStatus::ISOLATION_FAILED);
        $this->makeIsolationCase(IsolationCaseStatus::SUSPENDED);
        $this->makeIsolationCase(IsolationCaseStatus::RESTORATION_FAILED);
        $this->makeIsolationCase(IsolationCaseStatus::COMPLETED);

        $response = $this->actingAs($this->owner)->get('/');
        $isolation = $response->viewData('stats')['modules']['isolation'];

        $this->assertSame(1, $isolation['waiting']);
        $this->assertSame(2, $isolation['ready']); // READY + ISOLATION_FAILED (grup tab "Siap")
        $this->assertSame(1, $isolation['suspended']);
        $this->assertSame(1, $isolation['restoration']);
        $this->assertSame(2, $isolation['failed']);
        $response->assertSee('2 Isolir / Buka Isolir Gagal');
    }

    #[Test]
    public function alasan_putus_periode_dan_antrean_ambil_alat_terhitung(): void
    {
        $pindah = CustomerTerminationReason::create(['name' => 'Pindah Rumah', 'default_penalty_amount' => 0, 'is_active' => true]);
        $mahal = CustomerTerminationReason::create(['name' => 'Terlalu Mahal', 'default_penalty_amount' => 0, 'is_active' => true]);

        $this->makeCustomer('terminated', ['terminated_at' => now(), 'termination_reason_id' => $pindah->id]);
        $this->makeCustomer('terminated', ['terminated_at' => now(), 'termination_reason_id' => $pindah->id]);
        $this->makeCustomer('terminated', ['terminated_at' => now(), 'termination_reason_id' => $mahal->id]);
        // Putus bulan lalu — di luar periode dashboard default (bulan berjalan).
        $this->makeCustomer('terminated', ['terminated_at' => now()->subMonth(), 'termination_reason_id' => $mahal->id]);

        $deacCustomer = $this->makeCustomer('terminated', ['terminated_at' => now()->subMonths(2)]);
        $this->makeTask(TaskType::AMBIL_MODEM, TaskStatus::TERJADWAL, $deacCustomer);
        $this->makeTask(TaskType::AMBIL_MODEM, TaskStatus::SELESAI, $deacCustomer, ['completed_at' => now()]);

        $modules = $this->actingAs($this->owner)->get('/')->viewData('stats')['modules'];

        $this->assertSame([
            ['name' => 'Pindah Rumah', 'total' => 2],
            ['name' => 'Terlalu Mahal', 'total' => 1],
        ], $modules['termination_reasons']);
        $this->assertSame(1, $modules['deac_queue']);
        $this->assertSame(0, $modules['retrieval_reviews_pending']);
    }

    #[Test]
    public function antrean_verifikasi_pendaftaran_bd_dan_tagihan_creq_terhitung(): void
    {
        $this->makeCustomer('registered');
        $this->makeCustomer('registered');
        $this->makeCustomer('waiting_business_development_verification');

        $pendingTask = $this->makeTask(TaskType::CREQ, TaskStatus::SELESAI, $this->makeCustomer('active'));
        $this->makeCreqDetail($pendingTask, CReqVerificationStatus::PENDING);
        $verifiedTask = $this->makeTask(TaskType::CREQ, TaskStatus::SELESAI, $this->makeCustomer('active'));
        $this->makeCreqDetail($verifiedTask, CReqVerificationStatus::VERIFIED);

        $response = $this->actingAs($this->owner)->get('/');
        $modules = $response->viewData('stats')['modules'];

        $this->assertSame(2, $modules['registration_verification_pending']);
        $this->assertSame(1, $modules['business_verification_pending']);
        $this->assertSame(1, $modules['creq_billing_verification_pending']);
        $response->assertSee('4 Menunggu Verifikasi');
    }

    #[Test]
    public function pembebasan_tagihan_dihitung_per_sumber_dalam_rentang_periode(): void
    {
        $customer = $this->makeCustomer('terminated');
        $this->makeWaiver($customer, '2026-11', BillingWaiverSource::TERMINATION);
        $this->makeWaiver($customer, '2026-10', BillingWaiverSource::TERMINATION);
        $this->makeWaiver($this->makeCustomer('active'), '2026-11', BillingWaiverSource::LEAVE);

        $waivers = $this->actingAs($this->owner)->get('/')->viewData('stats')['modules']['billing_waivers'];
        $this->assertEquals([BillingWaiverSource::TERMINATION->value => 1, BillingWaiverSource::LEAVE->value => 1], $waivers);

        $waivers = $this->actingAs($this->owner)
            ->get('/?period_from=2026-10&period_to=2026-11')
            ->viewData('stats')['modules']['billing_waivers'];
        $this->assertSame(2, $waivers[BillingWaiverSource::TERMINATION->value]);
    }

    #[Test]
    public function rekonsiliasi_modem_memakai_ringkasan_service_halaman_modulnya(): void
    {
        $modem = $this->actingAs($this->owner)->get('/')->viewData('stats')['modules']['modem_reconciliation'];

        $this->assertSame(['total' => 0, 'anomaly' => 0, 'waiting' => 0, 'unbilled_amount' => 0.0], $modem);
    }

    // ── P2 ──────────────────────────────────────────────────────────────

    #[Test]
    public function mrr_arpu_dan_churn_dihitung_dari_pelanggan_aktif(): void
    {
        $this->makeCustomer('active', [], 150000);
        $this->makeCustomer('active', [], 250000);
        // Tagihan pelanggan putus TIDAK ikut MRR walau layanannya masih tercatat.
        $this->makeCustomer('terminated', ['terminated_at' => now()], 300000);

        $revenue = $this->actingAs($this->owner)->get('/')->viewData('stats')['modules']['revenue_quality'];

        $this->assertEquals(400000, $revenue['mrr']);
        $this->assertEquals(200000, $revenue['arpu']);
        // Aktif awal periode ≈ 2 aktif + 1 putus − 0 aktif baru = 3 → 1/3.
        $this->assertEquals(33.33, $revenue['churn_rate']);
    }

    #[Test]
    public function churn_null_kalau_belum_ada_pelanggan_sama_sekali(): void
    {
        $revenue = $this->actingAs($this->owner)->get('/')->viewData('stats')['modules']['revenue_quality'];

        $this->assertNull($revenue['churn_rate']);
        $this->assertNull($revenue['arpu']);
    }

    #[Test]
    public function perubahan_paket_dipisah_naik_turun_dengan_selisih_tagihan_bulanan(): void
    {
        $this->makePackageChange(150000, 250000, '2026-11');
        $this->makePackageChange(250000, 200000, '2026-11');
        $this->makePackageChange(100000, 300000, '2026-09'); // di luar periode

        $changes = $this->actingAs($this->owner)->get('/')->viewData('stats')['modules']['package_changes'];

        $this->assertSame(1, $changes['upgrade_count']);
        $this->assertSame(1, $changes['downgrade_count']);
        $this->assertEquals(50000, $changes['monthly_delta']);
    }

    #[Test]
    public function kinerja_kolektor_menghitung_konversi_dan_janji_bayar_yang_lewat(): void
    {
        $collector = User::factory()->create(['status' => 'active']);

        // Tanggal 1 wajib ikut terhitung (visited_at kolom DATE).
        $this->makeVisit($collector, $this->makeCustomer('active'), '2026-11-01', VisitResult::BAYAR);
        $this->makeVisit($collector, $this->makeCustomer('active'), '2026-11-02', VisitResult::TIDAK_ADA_ORANG);

        $ingkar = $this->makeCustomer('active');
        $this->makeVisit($collector, $ingkar, '2026-11-03', VisitResult::JANJI_BAYAR, '2026-11-10');

        $tepati = $this->makeCustomer('active');
        $this->makeVisit($collector, $tepati, '2026-11-03', VisitResult::JANJI_BAYAR, '2026-11-10');
        $this->makePayment($tepati, '2026-11-09');

        // Janjinya masih ke depan — belum ingkar.
        $this->makeVisit($collector, $this->makeCustomer('active'), '2026-11-14', VisitResult::JANJI_BAYAR, '2026-11-20');

        $performance = $this->actingAs($this->owner)->get('/')->viewData('stats')['modules']['collector_performance'];

        $this->assertSame(5, $performance['total']);
        $this->assertSame(1, $performance['by_result'][VisitResult::BAYAR->value]);
        $this->assertSame(3, $performance['by_result'][VisitResult::JANJI_BAYAR->value]);
        $this->assertEquals(20.0, $performance['conversion_rate']);
        $this->assertSame(1, $performance['broken_promises']);
    }

    #[Test]
    public function akuisisi_dikelompokkan_per_agen_dalam_periode(): void
    {
        $agent = Agent::create(['code' => 'AG-MOD', 'name' => 'Agen Uji Dashboard']);

        foreach (range(1, 2) as $i) {
            CustomerAcquisition::create([
                'customer_id' => $this->makeCustomer('waiting_survey', ['agent_id' => $agent->id])->id,
                'periode' => '2026-11',
                'verified_at' => now(),
            ]);
        }
        CustomerAcquisition::create([
            'customer_id' => $this->makeCustomer('waiting_survey', ['agent_id' => $agent->id])->id,
            'periode' => '2026-08',
            'verified_at' => now()->subMonths(3),
        ]);

        $topAgents = collect($this->actingAs($this->owner)->get('/')->viewData('stats')['modules']['top_agents']);

        $this->assertSame(2, $topAgents->firstWhere('name', 'Agen Uji Dashboard')['total']);
    }

    // ── P3 ──────────────────────────────────────────────────────────────

    #[Test]
    public function okupansi_port_dan_red_zone_dari_node_berkapasitas(): void
    {
        $type = NetworkNodeType::factory()->create(['has_ports' => true, 'monitors_capacity' => true, 'red_zone_threshold' => 80]);
        $full = NetworkNode::factory()->create(['anchor_pop_id' => $this->pop->id, 'node_type_id' => $type->id]);
        $empty = NetworkNode::factory()->create(['anchor_pop_id' => $this->pop->id, 'node_type_id' => $type->id]);
        $this->makePorts($full, 4, 4);
        $this->makePorts($empty, 4, 0);

        $ports = $this->actingAs($this->owner)->get('/')->viewData('stats')['modules']['port_occupancy'];

        $this->assertSame(8, $ports['total_ports']);
        $this->assertSame(4, $ports['used_ports']);
        $this->assertEquals(50.0, $ports['occupancy_rate']);
        $this->assertSame(1, $ports['red_zone_count']);
    }

    #[Test]
    public function kabel_jalan_di_pelanggan_putus_dihitung_wajib_ditarik(): void
    {
        CustomerCableCrossing::factory()->create(['customer_id' => $this->makeCustomer('terminated')->id]);
        // Pelanggan masih aktif — kabel memang harus tetap terpasang.
        CustomerCableCrossing::factory()->create(['customer_id' => $this->makeCustomer('active')->id]);
        CustomerCableCrossing::factory()->create([
            'customer_id' => $this->makeCustomer('terminated')->id,
            'status' => CableCrossingStatus::TIDAK_BISA_DITARIK,
            'alasan_tidak_ditarik' => 'Tiang roboh',
        ]);

        $cable = $this->actingAs($this->owner)->get('/')->viewData('stats')['modules']['cable_crossings'];

        $this->assertSame(['pending_removal' => 1, 'cannot_remove' => 1], $cable);
    }

    #[Test]
    public function task_non_psb_dihitung_per_tipe_plus_pending_dan_lapor_nanti(): void
    {
        $customer = $this->makeCustomer('active');
        $this->makeTask(TaskType::MAINTENANCE, TaskStatus::SELESAI, $customer, ['completed_at' => now()]);
        $this->makeTask(TaskType::MAINTENANCE, TaskStatus::TERJADWAL, $customer);
        $this->makeTask(TaskType::MAINTENANCE, TaskStatus::PENDING, $customer);
        $this->makeTask(TaskType::INFR, TaskStatus::LAPOR_NANTI, $customer);
        $this->makeTask(TaskType::PEMASANGAN, TaskStatus::PENDING, $customer); // PSB: cuma ikut total pending

        $tasks = $this->actingAs($this->owner)->get('/')->viewData('stats')['modules']['non_psb_tasks'];

        $this->assertSame(['completed' => 1, 'queued' => 2], $tasks['by_type'][TaskType::MAINTENANCE->value]);
        $this->assertSame(['completed' => 0, 'queued' => 1], $tasks['by_type'][TaskType::INFR->value]);
        $this->assertArrayNotHasKey(TaskType::PEMASANGAN->value, $tasks['by_type']);
        $this->assertSame(2, $tasks['pending']);
        $this->assertSame(1, $tasks['lapor_nanti']);
    }

    #[Test]
    public function antrean_gudang_permintaan_stok_dan_pengajuan_barang(): void
    {
        StockRequest::create(['reference_number' => 'PST-MOD-1', 'cabang_pop_id' => $this->pop->id, 'status' => StockRequestStatus::PENDING, 'requested_by' => $this->owner->id]);
        StockRequest::create(['reference_number' => 'PST-MOD-2', 'cabang_pop_id' => $this->pop->id, 'status' => StockRequestStatus::PARTIAL, 'requested_by' => $this->owner->id]);
        StockRequest::create(['reference_number' => 'PST-MOD-3', 'cabang_pop_id' => $this->pop->id, 'status' => StockRequestStatus::FULFILLED, 'requested_by' => $this->owner->id]);
        PengajuanBarang::create(['nomor_request' => 'SPB-MOD-1', 'pengaju_id' => $this->owner->id, 'nama_kegiatan' => 'Uji', 'pop_id' => $this->pop->id, 'status' => PengajuanBarangStatus::MENUNGGU_FOP]);

        $modules = $this->actingAs($this->owner)->get('/')->viewData('stats')['modules'];

        $this->assertSame(2, $modules['stock_requests_open']);
        $this->assertSame(1, $modules['pengajuan_barang_pending']);
    }

    // ── Gerbang & Scope ─────────────────────────────────────────────────

    /**
     * Role yang cuma punya dashboard.view: kartu modul ber-gerbang TIDAK
     * dihitung sama sekali (key-nya gak ada), kartu turunan pelanggan &
     * tagihan tetap ada, dan link drilldown gak pernah dirender.
     */
    #[Test]
    public function role_tanpa_permission_halaman_modul_tidak_mendapat_kartunya(): void
    {
        $this->makeIsolationCase(IsolationCaseStatus::ISOLATION_FAILED);

        $role = Role::create(['code' => 'dashboard_only', 'name' => 'Dashboard Only', 'is_system' => false]);
        $role->permissions()->attach(Permission::where('code', 'dashboard.view')->firstOrFail()->id);
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        $response = $this->actingAs($user)->get('/');
        $response->assertOk();
        $modules = $response->viewData('stats')['modules'];

        foreach (['isolation', 'termination_reasons', 'retrieval_reviews_pending', 'deac_queue', 'registration_verification_pending',
            'business_verification_pending', 'creq_billing_verification_pending', 'billing_waivers', 'modem_reconciliation',
            'collector_performance', 'port_occupancy', 'cable_crossings', 'material_inspections_pending', 'non_psb_tasks',
            'stock_requests_open', 'pengajuan_barang_pending'] as $gatedKey) {
            $this->assertArrayNotHasKey($gatedKey, $modules, "Kartu {$gatedKey} bocor ke role tanpa permission halamannya.");
        }

        $this->assertArrayHasKey('revenue_quality', $modules);
        $this->assertArrayHasKey('package_changes', $modules);
        $this->assertArrayHasKey('top_agents', $modules);
        $response->assertDontSee('Pelanggan Diisolir');
        // Pakai angka di depannya — teks tanpa angka juga ada di komentar HTML.
        $response->assertDontSee('1 Isolir / Buka Isolir Gagal');
        $response->assertDontSee(route('customers.isolir-queue.index'), false);
    }

    #[Test]
    public function pop_admin_cuma_melihat_angka_cabangnya_sendiri(): void
    {
        $this->makeIsolationCase(IsolationCaseStatus::SUSPENDED, $this->otherPop);
        $this->makeIsolationCase(IsolationCaseStatus::SUSPENDED);
        $this->makeCustomer('registered', ['pop_id' => $this->otherPop->id]);
        $this->makeCustomer('active', ['pop_id' => $this->otherPop->id], 999000);

        $popAdminRole = Role::where('name', 'POP Admin')->firstOrFail();
        $popAdminRole->permissions()->syncWithoutDetaching(
            Permission::whereIn('code', ['customers.isolir_queue.view', 'customer_registration_verification.view'])->pluck('id')->all()
        );
        $popAdmin = User::factory()->create(['role_id' => $popAdminRole->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $popAdmin->id, 'role_id' => $popAdminRole->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

        $modules = $this->actingAs($popAdmin)->get('/')->viewData('stats')['modules'];

        $this->assertSame(1, $modules['isolation']['suspended']);
        $this->assertSame(0, $modules['registration_verification_pending']);
        // Cuma tagihan pelanggan kasus isolir di cabangnya sendiri (150rb);
        // 999rb + 150rb milik cabang lain gak boleh ikut.
        $this->assertEquals(150000, $modules['revenue_quality']['mrr']);
    }

    #[Test]
    public function filter_pop_dashboard_menyaring_kartu_modul(): void
    {
        $this->makeIsolationCase(IsolationCaseStatus::SUSPENDED, $this->otherPop);
        $this->makeIsolationCase(IsolationCaseStatus::SUSPENDED);

        $modules = $this->actingAs($this->owner)->get('/?pop_id='.$this->otherPop->id)->viewData('stats')['modules'];

        $this->assertSame(1, $modules['isolation']['suspended']);
    }

    // ── Fixture ─────────────────────────────────────────────────────────

    private function makePop(string $code): Pop
    {
        return Pop::create([
            'name' => 'POP '.$code,
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C',
            'cid_prefix' => substr($code, -1),
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeCustomer(string $status, array $extra = [], ?float $monthlyBill = null): Customer
    {
        $code = 'MOD-'.(++$this->seq);

        $customer = Customer::create(array_merge([
            'full_name' => 'Pelanggan '.$code,
            'customer_code' => $code,
            'primary_phone' => '0813'.str_pad((string) $this->seq, 8, '0', STR_PAD_LEFT),
            'gender' => 'Laki-laki',
            'pop_id' => $this->pop->id,
            'status' => $status,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => '2020-01-01',
        ], $extra));

        if ($monthlyBill !== null) {
            $this->makeService($customer, $monthlyBill);
        }

        return $customer;
    }

    private function makeService(Customer $customer, float $monthlyBill): CustomerService
    {
        return CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => 'Paket Uji',
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => $monthlyBill,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $monthlyBill,
            'activation_date' => '2026-01-01',
            'due_date' => '2026-01-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);
    }

    private function makeIsolationCase(IsolationCaseStatus $status, ?Pop $pop = null): IsolationCase
    {
        $pop ??= $this->pop;
        $customer = $this->makeCustomer('active', ['pop_id' => $pop->id]);
        $service = $this->makeService($customer, 150000);

        return IsolationCase::create([
            'case_number' => 'ISO-MOD-'.$this->seq,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'pop_id' => $pop->id,
            'status' => $status,
            'trigger_type' => IsolationTriggerType::cases()[0],
            'detected_at' => now(),
        ]);
    }

    private function makeTask(TaskType $type, TaskStatus $status, Customer $customer, array $extra = []): Task
    {
        return Task::create(array_merge([
            'task_number' => 'TASK-MOD-'.(++$this->seq),
            'task_type' => $type->value,
            'title' => $type->value.' '.$customer->id,
            'pop_id' => $customer->pop_id,
            'customer_id' => $customer->id,
            'status' => $status->value,
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ], $extra));
    }

    private function makeCreqDetail(Task $task, CReqVerificationStatus $status): TaskCreqDetail
    {
        return TaskCreqDetail::create([
            'task_id' => $task->id,
            'category' => CReqCategory::PINDAH_KABEL->value,
            'is_billable' => true,
            'verification_status' => $status->value,
        ]);
    }

    private function makeWaiver(Customer $customer, string $period, BillingWaiverSource $source): CustomerBillingWaiver
    {
        return CustomerBillingWaiver::create([
            'customer_id' => $customer->id,
            'billing_period' => $period,
            'source' => $source->value,
            'reason' => 'Uji dashboard',
            'created_by' => $this->owner->id,
        ]);
    }

    private function makePackageChange(float $old, float $new, string $period): CustomerPackageChange
    {
        $customer = $this->makeCustomer('active');
        $service = $this->makeService($customer, $new);

        return CustomerPackageChange::create([
            'customer_id' => $customer->id,
            'customer_service_id' => $service->id,
            'old_internet_package_id' => $this->package->id,
            'new_internet_package_id' => $this->package->id,
            'old_monthly_price' => $old,
            'new_monthly_price' => $new,
            'billing_period' => $period,
            'effective_date' => $period.'-10',
            'days_in_period' => 30,
            'days_old_used' => 9,
            'days_new_used' => 21,
            'prorate_old_amount' => 0,
            'prorate_new_amount' => 0,
            'total_recomputed' => 0,
            'previously_paid' => 0,
        ]);
    }

    private function makeVisit(User $collector, Customer $customer, string $date, VisitResult $result, ?string $promised = null): CollectorVisit
    {
        return CollectorVisit::create([
            'collector_id' => $collector->id,
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'visited_at' => $date,
            'result' => $result->value,
            'promised_date' => $promised,
        ]);
    }

    private function makePayment(Customer $customer, string $date): void
    {
        // Insert mentah (tanpa observer/service): yang diuji cuma "ada
        // pembayaran valid sejak kunjungan", bukan alur pembayaran — itu
        // punya test sendiri. Periode tagihan sengaja bulan lalu supaya
        // invoice ini gak ikut mengubah angka lain di dashboard yang diuji.
        $service = $this->makeService($customer, 150000);
        $invoiceId = DB::table('invoices')->insertGetId([
            'invoice_number' => 'INV-MOD-'.$this->seq,
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'subtotal' => 150000,
            'total_amount' => 150000,
            'remaining_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table((new Payment)->getTable())->insert([
            'invoice_id' => $invoiceId,
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'payment_number' => 'PAY-MOD-'.$this->seq,
            'payment_method' => PaymentMethod::cases()[0]->value,
            'amount' => 150000,
            'payment_date' => $date,
            'payment_status' => 'valid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePorts(NetworkNode $node, int $total, int $used): void
    {
        foreach (range(1, $total) as $number) {
            NetworkNodePort::create(['network_node_id' => $node->id, 'number' => $number, 'is_used' => $number <= $used]);
        }
    }
}
