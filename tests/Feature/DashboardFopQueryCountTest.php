<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penjaga N+1 dashboard FOP.
 *
 * Dashboard ini auto-refresh (lihat FopDashboardAutoRefreshTest), jadi setiap
 * query per-baris yang lolos ke sini biayanya berulang terus tanpa interaksi
 * user. Sebelum Fase 1, sidebar teknisi saja menghabiskan 5 query per teknisi
 * (dua `whereHas` di dalam loop + tiga relasi yang disentuh `clean_address`).
 *
 * Yang diuji di sini bukan angka absolutnya — itu akan ikut berubah tiap kali
 * ada fitur baru dan cuma jadi test yang rewel. Yang diuji adalah **bentuk
 * pertumbuhannya**: menaikkan jumlah teknisi dan task tidak boleh menaikkan
 * jumlah query. Itu invariannya, dan itu yang hilang kalau seseorang tanpa
 * sengaja mengembalikan lazy load ke dalam loop.
 */
class DashboardFopQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private Pop $pop;

    private Role $teknisiRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);

        $this->pop = Pop::create([
            'code' => 'QRY',
            'pop_code' => 'QRY',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Query Count',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $ownerRole = Role::firstOrCreate(['code' => 'owner'], ['name' => 'Owner']);
        $this->teknisiRole = Role::firstOrCreate(['code' => 'teknisi'], ['name' => 'Teknisi']);

        $this->fopUser = User::factory()->create(['role_id' => $ownerRole->id]);
        $this->fopUser->roleScopes()->create([
            'role_id' => $ownerRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        app(EffectiveAccessService::class)->clearCache($this->fopUser);
    }

    public function test_jumlah_query_dashboard_fop_tidak_tumbuh_mengikuti_jumlah_teknisi_dan_task(): void
    {
        $this->seedTeknisiDenganTask(2);
        $queriesKecil = $this->countQueriesOnDashboard();

        $this->seedTeknisiDenganTask(8);
        $queriesBesar = $this->countQueriesOnDashboard();

        $this->assertSame(
            $queriesKecil,
            $queriesBesar,
            "Jumlah query dashboard FOP naik dari {$queriesKecil} jadi {$queriesBesar} setelah teknisi & task ditambah. "
                .'Ini tanda N+1 balik lagi — kemungkinan besar ada relasi yang disentuh di dalam loop/Blade '
                .'tanpa eager load, atau agregat yang dihitung per baris.'
        );
    }

    public function test_dashboard_fop_tidak_memicu_lazy_load(): void
    {
        // preventLazyLoading aktif di non-produksi (AppServiceProvider), jadi
        // lazy load apa pun melempar LazyLoadingViolationException dan halaman
        // jadi 500. Assert 200 = jaminan tidak ada relasi yang lolos eager load.
        $this->seedTeknisiDenganTask(5);

        $this->actingAs($this->fopUser)
            ->get(route('fop.dashboard'))
            ->assertStatus(200);
    }

    private function countQueriesOnDashboard(): int
    {
        // Cache permission/scope di-clear dulu supaya hit/miss cache tidak ikut
        // terhitung sebagai selisih antara dua pengukuran.
        app(EffectiveAccessService::class)->clearCache($this->fopUser);
        $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        app(EffectiveAccessService::class)->clearCache($this->fopUser);

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));
        $response->assertStatus(200);

        return $count;
    }

    private function seedTeknisiDenganTask(int $jumlah): void
    {
        for ($i = 0; $i < $jumlah; $i++) {
            $teknisi = User::factory()->create([
                'role_id' => $this->teknisiRole->id,
                'status' => 'active',
            ]);

            $teknisi->roleScopes()->create([
                'role_id' => $this->teknisiRole->id,
                'scope_type' => ScopeType::ALL_POP->value,
            ]);

            $customer = Customer::factory()->create([
                'pop_id' => $this->pop->id,
                'status' => 'active',
            ]);

            $task = Task::create([
                'task_number' => 'TASK-2026-'.str_pad((string) Task::count() + 1, 4, '0', STR_PAD_LEFT),
                'pop_id' => $this->pop->id,
                'customer_id' => $customer->id,
                'task_type' => TaskType::MAINTENANCE->value,
                'title' => 'Task uji beban query',
                'status' => TaskStatus::IN_PROGRESS->value,
                'scheduled_at' => now(),
                'started_at' => now(),
                'sla_minutes' => 120,
                'created_by' => $this->fopUser->id,
                'updated_by' => $this->fopUser->id,
            ]);

            $task->teamMembers()->create(['user_id' => $teknisi->id]);
        }
    }
}
