<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\MaterialKind;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\FopTask;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMaterial;
use App\Models\TaskWorkTool;
use App\Models\User;
use App\Models\WorkTool;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\FopAnalyticsFeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Dashboard Analitik FOP — permission gate, isolasi POP scope, dan
 * kebenaran agregasi tiap section (docs/plan/analisa-dashboard-analitik-fop.md).
 */
class FopAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;

    private Pop $popB;

    private District $districtA;

    private District $districtB;

    private Customer $customerA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(FopAnalyticsFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->popA = Pop::create([
            'code' => 'FAA', 'pop_code' => 'FAA', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Analitik A', 'type' => 'cabang', 'status' => 'active',
        ]);
        $this->popB = Pop::create([
            'code' => 'FAB', 'pop_code' => 'FAB', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Analitik B', 'type' => 'cabang', 'status' => 'active',
        ]);

        $city = City::create(['name' => 'Ponorogo']);
        $this->districtA = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $this->districtB = District::create(['city_id' => $city->id, 'name' => 'Siman']);

        $this->customerA = Customer::create([
            'customer_code' => 'FA-A-001', 'full_name' => 'Pelanggan A', 'primary_phone' => '0811111111',
            'status' => 'active', 'pop_id' => $this->popA->id, 'district_id' => $this->districtA->id,
            'data_completeness_status' => 'draft', 'registration_date' => now(),
        ]);
        $this->customerB = Customer::create([
            'customer_code' => 'FA-B-001', 'full_name' => 'Pelanggan B', 'primary_phone' => '0822222222',
            'status' => 'active', 'pop_id' => $this->popB->id, 'district_id' => $this->districtB->id,
            'data_completeness_status' => 'draft', 'registration_date' => now(),
        ]);
    }

    private function makeOwner(): User
    {
        $ownerRole = Role::where('code', 'owner')->first();
        $owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);
        $owner->roleScopes()->create(['role_id' => $ownerRole->id, 'scope_type' => ScopeType::ALL_POP->value]);
        app(EffectiveAccessService::class)->clearCache($owner);

        return $owner;
    }

    private function makePopAdmin(Pop $pop): User
    {
        $role = Role::where('code', 'pop_admin')->first();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = $user->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP->value]);
        $scope->targets()->create(['pop_id' => $pop->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    private function makeTask(User $actor, Customer $customer, Pop $pop, TaskType $type, TaskStatus $status, string $number, array $extra = []): Task
    {
        return Task::create(array_merge([
            'task_number' => $number,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => $type->value,
            'title' => 'Task '.$number,
            'status' => $status->value,
            'scheduled_at' => now(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ], $extra));
    }

    private function makeFopTask(Pop $pop, Customer $customer, TaskType $category, string $number, ?Task $task = null): FopTask
    {
        return FopTask::create([
            'task_number' => $number,
            'task_date' => now(),
            'category' => $category->value,
            'tugas' => 'Task Analitik',
            'pop_id' => $pop->id,
            'customer_id' => $customer->id,
            'task_id' => $task?->id,
            'issue' => 'Test analitik',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
    }

    public function test_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $this->actingAs($teknisi)->get(route('fop.analytics'))->assertForbidden();
    }

    public function test_owner_bisa_akses_halaman(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('fop.analytics'))->assertOk();
    }

    public function test_pop_scope_terisolasi_di_ranking_wilayah(): void
    {
        $owner = $this->makeOwner();
        $this->makeTask($owner, $this->customerA, $this->popA, TaskType::PEMASANGAN, TaskStatus::SELESAI, 'TASK-FA-A1');
        $this->makeTask($owner, $this->customerB, $this->popB, TaskType::PEMASANGAN, TaskStatus::SELESAI, 'TASK-FA-B1');

        $popAdminA = $this->makePopAdmin($this->popA);

        $response = $this->actingAs($popAdminA)->get(route('fop.analytics'));

        $response->assertOk();
        $response->assertSee('Babadan');
        $response->assertDontSee('Siman');
    }

    /**
     * Chart 1 "Ranking Barang Gudang Terpakai" (ADHOC-58 susulan) — sumbernya
     * `task_materials` (barang GUDANG), BUKAN `task_work_tools` (alat kerja
     * checklist) lagi. GROUP BY `item_name` (snapshot string, pola sama alat
     * kerja) — barang yang master `items`-nya sudah dihapus tetap teragregasi
     * lewat snapshot ini (item_id null).
     */
    public function test_ranking_barang_gudang_group_by_item_name_termasuk_yang_master_dihapus(): void
    {
        $owner = $this->makeOwner();
        $fopTask = $this->makeFopTask($this->popA, $this->customerA, TaskType::SURVEY, 'TFOP-FA-1');

        $item = Item::create(['code' => 'FC-SC', 'name' => 'Fast Connector SC', 'unit' => 'pcs']);
        TaskMaterial::create(['fop_task_id' => $fopTask->id, 'kind' => MaterialKind::TERPAKAI->value, 'item_id' => $item->id, 'item_type' => 'aksesoris_pasang', 'item_name' => 'Fast Connector SC', 'qty' => 2, 'unit' => 'pcs', 'recorded_by' => $owner->id]);
        TaskMaterial::create(['fop_task_id' => $fopTask->id, 'kind' => MaterialKind::TERPAKAI->value, 'item_id' => $item->id, 'item_type' => 'aksesoris_pasang', 'item_name' => 'Fast Connector SC', 'qty' => 3, 'unit' => 'pcs', 'recorded_by' => $owner->id]);
        // Barang yang master-nya sudah dihapus (FK null) — tetap teragregasi lewat snapshot item_name.
        TaskMaterial::create(['fop_task_id' => $fopTask->id, 'kind' => MaterialKind::TERPAKAI->value, 'item_id' => null, 'item_type' => 'kabel_dropcore', 'item_name' => 'Kabel Dropcore 1 Core', 'qty' => 10, 'unit' => 'meter', 'recorded_by' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('fop.analytics'));

        $response->assertOk();
        $response->assertSee('Fast Connector SC');
        $response->assertSee('Kabel Dropcore 1 Core');
    }

    /**
     * Modem/ONT (ADHOC, 2026-09-12) — jalur BEDA dari ranking barang gudang
     * biasa: modem `tracking_type=SERIALIZED`, dicatat `installSerial()` ke
     * `inventory_transactions` type INSTALL (BUKAN `task_materials` —
     * `consumeFromCustody()` menolak item serialized). Instalasi vs
     * Maintenance dibaca dari `fop_tasks.category` pada task pemicunya.
     */
    public function test_modem_terpasang_dipecah_instalasi_vs_maintenance(): void
    {
        $owner = $this->makeOwner();
        $category = ItemCategory::create(['code' => 'modem_ont', 'name' => 'Modem/ONT Pelanggan', 'default_unit' => 'pcs']);
        $modem = Item::create([
            'code' => 'ONT-TEST', 'name' => 'Modem ONT Test', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);

        $fopInstalasi = $this->makeFopTask($this->popA, $this->customerA, TaskType::PEMASANGAN, 'TFOP-FA-MODEM-1');
        $fopMaintenance = $this->makeFopTask($this->popA, $this->customerA, TaskType::MAINTENANCE, 'TFOP-FA-MODEM-2');

        InventoryTransaction::create(['type' => 'install', 'item_id' => $modem->id, 'qty' => 1, 'fop_task_id' => $fopInstalasi->id, 'created_by' => $owner->id]);
        InventoryTransaction::create(['type' => 'install', 'item_id' => $modem->id, 'qty' => 1, 'fop_task_id' => $fopInstalasi->id, 'created_by' => $owner->id]);
        InventoryTransaction::create(['type' => 'install', 'item_id' => $modem->id, 'qty' => 1, 'fop_task_id' => $fopMaintenance->id, 'created_by' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('fop.analytics'));

        $response->assertOk();
        $response->assertSee('Modem ONT Test');
        $this->assertSame(2, $response->viewData('modemInstalasiTotal'));
        $this->assertSame(1, $response->viewData('modemMaintenanceTotal'));
    }

    public function test_beban_tugas_dihitung_per_assignment_bukan_per_task(): void
    {
        $owner = $this->makeOwner();
        $teknisi1 = User::factory()->create(['status' => 'active']);
        $teknisi2 = User::factory()->create(['status' => 'active']);

        $task = $this->makeTask($owner, $this->customerA, $this->popA, TaskType::PEMASANGAN, TaskStatus::TERJADWAL, 'TASK-FA-TEAM');
        $task->teamMembers()->create(['user_id' => $teknisi1->id, 'role_in_task' => 'lead']);
        $task->teamMembers()->create(['user_id' => $teknisi2->id, 'role_in_task' => 'member']);

        $response = $this->actingAs($owner)->get(route('fop.analytics'));

        $response->assertOk();
        $response->assertSee($teknisi1->name);
        $response->assertSee($teknisi2->name);
    }

    public function test_backlog_hanya_draft_terjadwal_pending_bukan_selesai_atau_dibatalkan(): void
    {
        $owner = $this->makeOwner();
        $this->makeTask($owner, $this->customerA, $this->popA, TaskType::PEMASANGAN, TaskStatus::DRAFT, 'TASK-FA-BACKLOG');
        $this->makeTask($owner, $this->customerA, $this->popA, TaskType::PEMASANGAN, TaskStatus::SELESAI, 'TASK-FA-DONE', [
            'started_at' => now()->subHour(), 'completed_at' => now(),
        ]);
        $this->makeTask($owner, $this->customerA, $this->popA, TaskType::PEMASANGAN, TaskStatus::DIBATALKAN, 'TASK-FA-CANCEL');

        $response = $this->actingAs($owner)->get(route('fop.analytics'));

        $response->assertOk();
        $response->assertSee('TASK-FA-BACKLOG');
        // TASK-FA-DONE (selesai) sengaja TIDAK dicek assertDontSee — task itu
        // punya started_at/completed_at, jadi wajar muncul di section 5 (durasi
        // terlama), cuma harus TIDAK muncul di section 4 (backlog).
        $response->assertDontSee('TASK-FA-CANCEL');
    }

    public function test_backlog_dipaginate_bukan_dipotong_diam_diam(): void
    {
        $owner = $this->makeOwner();

        // 18 baris > 15 per halaman — cukup buat butuh 2 halaman. created_at
        // di-backdate berurutan (task ke-1 paling tua) biar urutan `orderBy
        // ('created_at')` deterministik: halaman 1 isi task 1-15, halaman 2
        // isi task 16-18.
        for ($i = 1; $i <= 18; $i++) {
            $task = $this->makeTask($owner, $this->customerA, $this->popA, TaskType::PEMASANGAN, TaskStatus::DRAFT, sprintf('TASK-FA-PG-%02d', $i));
            $task->forceFill(['created_at' => now()->subDays(30 - $i)])->saveQuietly();
        }

        $page1 = $this->actingAs($owner)->get(route('fop.analytics'));
        $page1->assertOk();
        $page1->assertSee('TASK-FA-PG-01'); // task tertua, tampil di halaman 1
        $page1->assertDontSee('TASK-FA-PG-16'); // task termuda, harus di halaman 2, BUKAN kepotong diam-diam
        $page1->assertSee('18 total'); // KPI backlog tetap hitung SEMUA baris, bukan cuma yang tampil di halaman aktif

        $page2 = $this->actingAs($owner)->get(route('fop.analytics', ['page' => 2]));
        $page2->assertOk();
        $page2->assertSee('TASK-FA-PG-16');
        $page2->assertDontSee('TASK-FA-PG-01'); // sudah di halaman 1, gak dobel muncul di halaman 2
    }

    public function test_durasi_terlama_pakai_actual_duration_minutes(): void
    {
        $owner = $this->makeOwner();
        $task = $this->makeTask($owner, $this->customerA, $this->popA, TaskType::MAINTENANCE, TaskStatus::SELESAI, 'TASK-FA-DUR', [
            'started_at' => now()->subHours(3),
            'completed_at' => now(),
            'completed_by' => $owner->id,
        ]);

        $this->assertSame(180, $task->actualDurationMinutes());

        $response = $this->actingAs($owner)->get(route('fop.analytics'));

        $response->assertOk();
        $response->assertSee('TASK-FA-DUR');
    }

    public function test_filter_pop_id_mempersempit_ranking_wilayah(): void
    {
        $owner = $this->makeOwner();
        $this->makeTask($owner, $this->customerA, $this->popA, TaskType::PEMASANGAN, TaskStatus::SELESAI, 'TASK-FA-FILT-A');
        $this->makeTask($owner, $this->customerB, $this->popB, TaskType::PEMASANGAN, TaskStatus::SELESAI, 'TASK-FA-FILT-B');

        $response = $this->actingAs($owner)->get(route('fop.analytics', ['pop_id' => $this->popA->id]));

        $response->assertOk();
        $response->assertSee('Babadan');
        $response->assertDontSee('Siman');
    }

    /**
     * Regresi — sebelum fix, filter dropdown POP cuma narrow ranking wilayah/
     * beban tugas, tapi backlog/solving leaderboard/durasi terlama diam-diam
     * tetap gabungan semua POP dalam scope user (ignore pilihan dropdown).
     */
    public function test_filter_pop_id_mempersempit_backlog_solving_dan_durasi(): void
    {
        $owner = $this->makeOwner();

        $this->makeTask($owner, $this->customerA, $this->popA, TaskType::PEMASANGAN, TaskStatus::DRAFT, 'TASK-FA-BL-POPA');
        $this->makeTask($owner, $this->customerB, $this->popB, TaskType::PEMASANGAN, TaskStatus::DRAFT, 'TASK-FA-BL-POPB');

        $teknisiA = User::factory()->create(['name' => 'Teknisi Solve A', 'status' => 'active']);
        $teknisiB = User::factory()->create(['name' => 'Teknisi Solve B', 'status' => 'active']);
        $this->makeTask($owner, $this->customerA, $this->popA, TaskType::MAINTENANCE, TaskStatus::SELESAI, 'TASK-FA-SLV-A', [
            'started_at' => now()->subHour(), 'completed_at' => now(), 'completed_by' => $teknisiA->id,
        ]);
        $this->makeTask($owner, $this->customerB, $this->popB, TaskType::MAINTENANCE, TaskStatus::SELESAI, 'TASK-FA-SLV-B', [
            'started_at' => now()->subHour(), 'completed_at' => now(), 'completed_by' => $teknisiB->id,
        ]);

        $this->makeTask($owner, $this->customerA, $this->popA, TaskType::MAINTENANCE, TaskStatus::SELESAI, 'TASK-FA-DUR-A', [
            'started_at' => now()->subHours(2), 'completed_at' => now(),
        ]);
        $this->makeTask($owner, $this->customerB, $this->popB, TaskType::MAINTENANCE, TaskStatus::SELESAI, 'TASK-FA-DUR-B', [
            'started_at' => now()->subHours(2), 'completed_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('fop.analytics', ['pop_id' => $this->popA->id]));

        $response->assertOk();
        $response->assertSee('TASK-FA-BL-POPA');
        $response->assertDontSee('TASK-FA-BL-POPB');
        $response->assertSee('Teknisi Solve A');
        $response->assertDontSee('Teknisi Solve B');
        $response->assertSee('TASK-FA-DUR-A');
        $response->assertDontSee('TASK-FA-DUR-B');
    }

    /**
     * Regresi — sebelum fix, ranking wilayah/leaderboard `limit(20)` mentah
     * tanpa indikasi apa pun kalau sebenarnya ada lebih banyak baris.
     */
    public function test_ranking_wilayah_kasih_tahu_kalau_dipotong_top_20(): void
    {
        $owner = $this->makeOwner();
        $city = City::create(['name' => 'Kota Banyak Kecamatan']);

        for ($i = 1; $i <= 21; $i++) {
            $district = District::create(['city_id' => $city->id, 'name' => "Kecamatan {$i}"]);
            $customer = Customer::create([
                'customer_code' => "FA-MANY-{$i}", 'full_name' => "Pelanggan Many {$i}", 'primary_phone' => '0899999'.$i,
                'status' => 'active', 'pop_id' => $this->popA->id, 'district_id' => $district->id,
                'data_completeness_status' => 'draft', 'registration_date' => now(),
            ]);
            $this->makeTask($owner, $customer, $this->popA, TaskType::PEMASANGAN, TaskStatus::SELESAI, "TASK-FA-MANY-{$i}");
        }

        $response = $this->actingAs($owner)->get(route('fop.analytics'));

        $response->assertOk();
        $response->assertSee('Menampilkan top 20 dari 21 kecamatan');
    }

    /**
     * Regresi — trend chart sumbernya `task_materials` (barang GUDANG,
     * ADHOC-58), BUKAN `task_work_tools` (alat kerja checklist) lagi. Section
     * "Tren pemakaian per periode" dari dokumen analisa awalnya cuma hitung
     * `$toolTrend` tanpa visual sama sekali — sekarang dirender, tapi
     * datanya barang gudang yang punya qty (meter/pcs), bukan alat kerja
     * yang cuma checklist bawa-pulang tanpa angka pemakaian berarti.
     */
    public function test_tren_pemakaian_barang_gudang_dirender(): void
    {
        $owner = $this->makeOwner();
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-FA-TREND',
            'task_date' => now(),
            'category' => TaskType::SURVEY->value,
            'tugas' => 'Task Tren',
            'pop_id' => $this->popA->id,
            'customer_id' => $this->customerA->id,
            'issue' => 'Test tren',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
        TaskMaterial::create([
            'fop_task_id' => $fopTask->id,
            'customer_id' => $this->customerA->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_type' => 'kabel_dropcore',
            'item_name' => 'Kabel Dropcore',
            'qty' => 20,
            'unit' => 'meter',
            'recorded_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('fop.analytics'));

        $response->assertOk();
        $response->assertSee('id="materialTrendChart"', false);
        $response->assertDontSee('Gak ada tren pemakaian barang gudang');
    }

    /**
     * Regresi — KPI "N/M jenis alat dipakai" dari dokumen analisa §1
     * ("Jumlah jenis alat aktif dipakai") sebelumnya gak pernah dibikin.
     */
    public function test_kpi_jenis_alat_dipakai_muncul(): void
    {
        $owner = $this->makeOwner();
        $tool = WorkTool::create(['code' => 'TREND-TOOL', 'name' => 'Alat Tren', 'is_active' => true, 'sort_order' => 1]);
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-FA-KPI',
            'task_date' => now(),
            'category' => TaskType::SURVEY->value,
            'tugas' => 'Task KPI',
            'pop_id' => $this->popA->id,
            'customer_id' => $this->customerA->id,
            'issue' => 'Test KPI',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
        TaskWorkTool::create(['fop_task_id' => $fopTask->id, 'work_tool_id' => $tool->id, 'tool_name' => $tool->name, 'recorded_by' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('fop.analytics'));

        $response->assertOk();
        $response->assertSee('1/1 jenis');
    }

    /**
     * KPI "Task Dibatalkan" WAJIB scalar total (`buildCancelledTotal()`),
     * bukan `sum($regionCancelled['rows'])` — kalau >20 kecamatan yang
     * pernah punya pembatalan, versi lama undercount karena rows-nya udah
     * dipotong top 20 (lihat `buildRegionRanking()`).
     */
    public function test_kpi_task_dibatalkan_pakai_total_scalar_bukan_sum_top_20(): void
    {
        $owner = $this->makeOwner();
        $city = City::create(['name' => 'Kota Banyak Dibatalkan']);

        for ($i = 1; $i <= 21; $i++) {
            $district = District::create(['city_id' => $city->id, 'name' => "Kecamatan Batal {$i}"]);
            $customer = Customer::create([
                'customer_code' => "FA-CANCEL-{$i}", 'full_name' => "Pelanggan Cancel {$i}", 'primary_phone' => '0877777'.$i,
                'status' => 'active', 'pop_id' => $this->popA->id, 'district_id' => $district->id,
                'data_completeness_status' => 'draft', 'registration_date' => now(),
            ]);
            $this->makeTask($owner, $customer, $this->popA, TaskType::MAINTENANCE, TaskStatus::DIBATALKAN, "TASK-FA-CANCEL-{$i}");
        }

        $response = $this->actingAs($owner)->get(route('fop.analytics'));

        $response->assertOk();
        // 21 kecamatan x 1 task dibatalkan = 21 total, BUKAN 20 (kalau kena
        // bug sum-top-20 lagi).
        $response->assertSee('>21<', false);
    }

    /**
     * Komparasi periode sebelumnya — jendela sama panjang, langsung
     * menempel sebelum periode aktif. 2 pemakaian alat di periode aktif vs
     * 1 di periode sebelumnya = naik 100%.
     */
    public function test_kpi_menampilkan_komparasi_periode_sebelumnya(): void
    {
        $owner = $this->makeOwner();

        $currentFrom = Carbon::parse('2026-06-10');
        $currentTo = Carbon::parse('2026-06-19');
        // Jendela 10 hari (10-19 Jun) → sebelumnya 10 hari juga, langsung
        // menempel: 31 Mei - 9 Jun.
        $previousDay = Carbon::parse('2026-06-01');

        $fopCurrent = $this->makeFopTask($this->popA, $this->customerA, TaskType::SURVEY, 'TFOP-FA-CMP-CUR');
        TaskWorkTool::create(['fop_task_id' => $fopCurrent->id, 'work_tool_id' => null, 'tool_name' => 'Tangga', 'recorded_by' => $owner->id])
            ->forceFill(['created_at' => $currentFrom->copy()->addDay()])->saveQuietly();
        TaskWorkTool::create(['fop_task_id' => $fopCurrent->id, 'work_tool_id' => null, 'tool_name' => 'Splicer', 'recorded_by' => $owner->id])
            ->forceFill(['created_at' => $currentFrom->copy()->addDays(2)])->saveQuietly();

        $fopPrev = $this->makeFopTask($this->popA, $this->customerA, TaskType::SURVEY, 'TFOP-FA-CMP-PREV');
        TaskWorkTool::create(['fop_task_id' => $fopPrev->id, 'work_tool_id' => null, 'tool_name' => 'Tangga', 'recorded_by' => $owner->id])
            ->forceFill(['created_at' => $previousDay])->saveQuietly();

        $response = $this->actingAs($owner)->get(route('fop.analytics', [
            'from' => $currentFrom->format('Y-m-d'),
            'to' => $currentTo->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertSee('▲ 100,0%', false);
    }

    /**
     * Regresi — role `fop` (audiens UTAMA dashboard ini) sebelumnya gak
     * digrant `fop_analytics.view` sama sekali (cuma owner/atasan/admin/
     * pop_admin, mirror `warehouse_report.view` yang audiensnya beda).
     */
    public function test_role_fop_bisa_akses_halaman(): void
    {
        $fopRole = Role::where('code', 'fop')->first();
        $fopUser = User::factory()->create(['role_id' => $fopRole->id, 'status' => 'active']);
        $fopUser->roleScopes()->create(['role_id' => $fopRole->id, 'scope_type' => ScopeType::ALL_POP->value]);
        app(EffectiveAccessService::class)->clearCache($fopUser);

        $this->actingAs($fopUser)->get(route('fop.analytics'))->assertOk();
    }
}
