<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\MaterialKind;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TaskMaterial;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\FopAnalyticsFeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FopUnifiedDashboardVisualTest extends TestCase
{
    use RefreshDatabase;

    protected User $fopUser;

    protected Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(FopAnalyticsFeatureSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'JTS',
            'pop_code' => 'JTS',
            'registration_prefix' => 'J',
            'cid_prefix' => 'J',
            'name' => 'POP Jetis',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $roleFop = Role::firstOrCreate(['code' => 'fop'], ['name' => 'FOP']);
        $this->fopUser = User::factory()->create([
            'role_id' => $roleFop->id,
        ]);

        app(EffectiveAccessService::class)->clearCache($this->fopUser);

        $this->fopUser->roleScopes()->create([
            'role_id' => $roleFop->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);
    }

    #[Test]
    public function visual_dashboard_menampilkan_komponen_komando_lengkap(): void
    {
        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();

        // 0. Header Dual Mode Switcher
        $response->assertSee('⚡ Operasional Harian');
        $response->assertSee('📊 Rekapitulasi & Analitik');

        // 1. Zona Ticker & Hero Cards
        $response->assertSee('emergency-ticker-container');
        $response->assertSee('stat-cards-container');

        // 2. Zona Stepper Funnel Pipeline
        $response->assertSee('customer-pipeline-funnel');
        $response->assertSee('Antrean Survey');
        $response->assertSee('Pemasangan');

        // 3. Zona Tactical Board & Tab Tiket
        $response->assertSee('fop-teams-board');
        $response->assertSee('antrian-survey-container');
        $response->assertSee('tactical-tickets-board');

        // 4. Zona Telemetry Kanan (Teknisi, Gudang & Distribusi)
        $response->assertSee('status-teknisi-container');
        $response->assertSee('gudang-stats-container');
        $response->assertSee('warehouse-telemetry-widget');
        $response->assertSee('odp-capacity-widget');
    }

    /**
     * Regresi — "Terkoneksi" badge di widget Distribusi dulu HARDCODED
     * (gak diturunkan dari field apa pun di `getOdpSummary()`),
     * dekoratif murni. Dicopot (2026-10-12) biar widget gak kelihatan
     * "berfungsi" padahal cuma angka statis.
     */
    #[Test]
    public function distribution_widget_tidak_lagi_punya_badge_terkoneksi_palsu(): void
    {
        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Terkoneksi');
        $response->assertSee('Kapasitas ODP');
        $response->assertDontSee('Total Titik Distribusi Aktif');
    }

    /**
     * Regresi — FOP butuh akses eksplisit ke Rekap Pemakaian Material
     * (/warehouse/usage, preset harian/mingguan/bulanan/custom) langsung dari
     * Dashboard FOP, bukan cuma "Lihat Laporan Gudang" yang scope-nya beda
     * (`warehouse_report.view`, agregat bulanan, bukan ledger transaksi).
     */
    #[Test]
    public function dashboard_fop_punya_link_ke_rekap_pemakaian_material(): void
    {
        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertSee(route('warehouse.usage.index'), false);
        $this->actingAs($this->fopUser)->get(route('warehouse.usage.index'))->assertOk();
    }

    /**
     * Regresi — task_number di "Rincian Pemakaian Hari Ini" dulu teks polos
     * walau datanya udah punya fop_task_id (ADHOC audit dashboard,
     * 2026-10-12). Sekarang wajib link ke detail riwayat FopTask.
     */
    #[Test]
    public function rincian_pemakaian_hari_ini_task_number_adalah_link(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->pop->id]);
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-VISUAL-LINK',
            'task_date' => now(),
            'category' => TaskType::PEMASANGAN->value,
            'tugas' => 'Pasang Baru',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'issue' => 'Pemasangan baru',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
        TaskMaterial::create([
            'fop_task_id' => $fopTask->id,
            'customer_id' => $customer->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_type' => 'kabel',
            'item_name' => 'Drop Core Link Test',
            'qty' => 10,
            'unit' => 'm',
        ]);

        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertSee(route('fop-tasks.history.show', $fopTask->id), false);
    }

    /**
     * Regresi — tiket yang sudah eskalasi ke FOP dulu cuma badge statis
     * "Task FOP Aktif" tanpa link, walau `fop_task_id`-nya udah ada di data
     * (ADHOC audit dashboard, 2026-10-12). Sekarang wajib link ke riwayat
     * FopTask-nya.
     */
    #[Test]
    public function tiket_dengan_fop_task_aktif_linknya_ke_riwayat_foptask(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->pop->id]);
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-TICKET-LINK',
            'task_date' => now(),
            'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Gangguan Internet',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'issue' => 'Gangguan Internet',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
        Ticket::create([
            'ticket_number' => 'TKT-TICKET-LINK',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Gangguan Internet',
            'priority' => 'High',
            'handler' => 'fop',
            'status' => 'open',
            'created_by' => $this->fopUser->id,
            'fop_task_id' => $fopTask->id,
        ]);

        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertSee(route('fop-tasks.history.show', $fopTask->id), false);
    }
}
