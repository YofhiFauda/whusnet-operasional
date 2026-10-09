<?php

namespace Tests\Feature\QrCode;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\QrScanLog;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CustomerQrTokenService;
use App\Services\QrAttendanceService;
use App\Services\TaskService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\QrFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md §6.3 — Fungsi C (absen
 * teknisi via QR). Guard yang diuji: token → POP scope → penugasan & jadwal
 * hari ini → radius GPS → TaskService::start().
 */
class QrAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Customer $customer;

    private User $owner;

    private User $teknisi;

    protected function setUp(): void
    {
        parent::setUp();

        config(['qr.secret' => 'test-qr-hmac-secret-absen', 'qr.portal_base_url' => 'https://portal.test']);

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(TicketFeatureSeeder::class);
        $this->seed(QrFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'QAT-A', 'pop_code' => 'QAA', 'registration_prefix' => 'C', 'cid_prefix' => 'A',
            'name' => 'POP Absen QR', 'type' => 'cabang', 'status' => 'active',
        ]);

        // Koordinat pelanggan = titik acuan. Jarak diuji relatif terhadap ini.
        $this->customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'customer_code' => 'RQ003001',
            'latitude' => -7.5000000,
            'longitude' => 111.5000000,
        ]);

        $this->owner = $this->scopedUser('owner', $this->pop);
        $this->teknisi = $this->scopedUser('teknisi', $this->pop);
    }

    private function scopedUser(string $roleCode, Pop $onlyPop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $onlyPop->id]);

        return $user;
    }

    /**
     * Task terjadwal HARI INI untuk pelanggan, dengan $teknisi sebagai anggota.
     */
    private function scheduledTaskToday(array $teamMemberIds = []): Task
    {
        return app(TaskService::class)->create([
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Perbaikan jalur',
            'team_member_ids' => $teamMemberIds ?: [$this->teknisi->id],
            'scheduled_at' => now()->toDateTimeString(),
        ], $this->owner);
    }

    private function code(): string
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $signature = $service->signature($this->pop->id, $this->customer->customer_code, $token->token);

        return "{$token->token}.{$signature}";
    }

    #[Test]
    public function haversine_satu_millidrajat_lintang_kira_kira_111_meter(): void
    {
        $meters = app(QrAttendanceService::class)->haversineMeters(0.0, 0.0, 0.001, 0.0);

        $this->assertEqualsWithDelta(111, $meters, 1);
    }

    #[Test]
    public function absen_di_lokasi_memulai_task_dan_menulis_jejak_qr_scan(): void
    {
        $task = $this->scheduledTaskToday();

        $response = $this->actingAs($this->teknisi)->post(route('qr.attendance.store', ['code' => $this->code()]), [
            'latitude' => -7.5000000,
            'longitude' => 111.5000000,
            'accuracy' => 12,
        ]);

        $response->assertRedirect(route('tasks.show', $task));

        $task->refresh();
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->status);
        $this->assertSame('qr_scan', $task->started_via);
        $this->assertSame(0, $task->started_distance_meters);
        $this->assertSame(12, $task->started_accuracy_meters);
        $this->assertDatabaseHas('qr_scan_logs', [
            'purpose' => 'attendance',
            'result' => 'success',
            'user_id' => $this->teknisi->id,
        ]);
    }

    #[Test]
    public function absen_di_luar_radius_ditolak_dan_task_tetap_terjadwal(): void
    {
        $task = $this->scheduledTaskToday();

        // ~1,1 km di utara — di atas hard limit 500 m.
        $response = $this->actingAs($this->teknisi)->post(route('qr.attendance.store', ['code' => $this->code()]), [
            'latitude' => -7.4900000,
            'longitude' => 111.5000000,
        ]);

        $response->assertRedirect(route('qr.scan.show'));
        $response->assertSessionHas('error');
        $this->assertSame(TaskStatus::TERJADWAL, $task->fresh()->status);
        $this->assertDatabaseHas('qr_scan_logs', ['purpose' => 'attendance', 'result' => 'out_of_radius']);
    }

    #[Test]
    public function absen_antara_radius_normal_dan_hard_limit_lolos_dengan_flag_perlu_review(): void
    {
        $task = $this->scheduledTaskToday();

        // ~222 m — di antara 150 m dan 500 m.
        $response = $this->actingAs($this->teknisi)->post(route('qr.attendance.store', ['code' => $this->code()]), [
            'latitude' => -7.4980000,
            'longitude' => 111.5000000,
        ]);

        $response->assertRedirect(route('tasks.show', $task));
        $task->refresh();
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->status);
        $this->assertGreaterThan(150, $task->started_distance_meters);
        $this->assertLessThanOrEqual(500, $task->started_distance_meters);
        $this->assertDatabaseHas('qr_scan_logs', ['result' => 'success', 'reason' => 'perlu_review']);
    }

    #[Test]
    public function tanpa_koordinat_gps_ditolak_dan_task_tidak_dimulai(): void
    {
        // Dulu absen tanpa koordinat lolos: teknisi bisa "absen" dari rumah tanpa
        // bukti lokasi. Sekarang wajib GPS; task tetap belum jalan.
        $task = $this->scheduledTaskToday();

        $response = $this->actingAs($this->teknisi)->post(route('qr.attendance.store', ['code' => $this->code()]), []);

        $response->assertRedirect(route('qr.scan.show'));
        $response->assertSessionHas('error', 'Absen wajib dengan lokasi GPS. Aktifkan lokasi di perangkat lalu coba lagi.');
        $task->refresh();
        $this->assertNotSame(TaskStatus::IN_PROGRESS, $task->status);
        $this->assertNull($task->started_via);
        $this->assertDatabaseHas('qr_scan_logs', ['result' => 'tanpa_koordinat']);
    }

    #[Test]
    public function pelanggan_tanpa_koordinat_ditolak_dengan_pesan_data_pelanggan(): void
    {
        $this->customer->update(['latitude' => null, 'longitude' => null]);
        $task = $this->scheduledTaskToday();

        $response = $this->actingAs($this->teknisi)->post(route('qr.attendance.store', ['code' => $this->code()]), [
            'latitude' => -7.9,
            'longitude' => 110.0,
        ]);

        $response->assertRedirect(route('qr.scan.show'));
        $response->assertSessionHas('error', 'Koordinat pelanggan belum diisi. Minta admin melengkapinya sebelum absen.');
        $this->assertNotSame(TaskStatus::IN_PROGRESS, $task->fresh()->status);
    }

    #[Test]
    public function teknisi_bukan_anggota_tim_tidak_bisa_absen(): void
    {
        $lainnya = $this->scopedUser('teknisi', $this->pop);
        $task = $this->scheduledTaskToday([$lainnya->id]);

        $response = $this->actingAs($this->teknisi)->post(route('qr.attendance.store', ['code' => $this->code()]), [
            'latitude' => -7.5,
            'longitude' => 111.5,
        ]);

        $response->assertRedirect(route('qr.scan.show'));
        $response->assertSessionHas('error');
        $this->assertSame(TaskStatus::TERJADWAL, $task->fresh()->status);
        $this->assertDatabaseHas('qr_scan_logs', ['result' => 'no_eligible_task']);
    }

    #[Test]
    public function task_bukan_hari_ini_tidak_bisa_diabsen(): void
    {
        $task = app(TaskService::class)->create([
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Jadwal besok',
            'team_member_ids' => [$this->teknisi->id],
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ], $this->owner);

        $response = $this->actingAs($this->teknisi)->post(route('qr.attendance.store', ['code' => $this->code()]), [
            'latitude' => -7.5,
            'longitude' => 111.5,
        ]);

        $response->assertRedirect(route('qr.scan.show'));
        $this->assertSame(TaskStatus::TERJADWAL, $task->fresh()->status);
    }

    #[Test]
    public function pelanggan_di_luar_pop_scope_ditolak_403(): void
    {
        $popLain = Pop::create([
            'code' => 'QAT-B', 'pop_code' => 'QAB', 'registration_prefix' => 'C', 'cid_prefix' => 'B',
            'name' => 'POP Lain', 'type' => 'cabang', 'status' => 'active',
        ]);
        $teknisiLuar = $this->scopedUser('teknisi', $popLain);
        $this->scheduledTaskToday([$teknisiLuar->id]);

        $response = $this->actingAs($teknisiLuar)->post(route('qr.attendance.store', ['code' => $this->code()]), [
            'latitude' => -7.5,
            'longitude' => 111.5,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function user_tanpa_permission_absen_ditolak_oleh_route(): void
    {
        $role = Role::where('code', 'sales')->firstOrFail();
        $sales = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->actingAs($sales)->get(route('qr.attendance.show', ['code' => $this->code()]))
            ->assertForbidden();
    }

    #[Test]
    public function token_tidak_valid_mengembalikan_404(): void
    {
        $response = $this->actingAs($this->teknisi)->get('/q1/'.'A'.str_repeat('A', 25).'.'.str_repeat('A', 10).'/absen');

        $response->assertNotFound();
    }

    #[Test]
    public function scan_qr_oleh_teknisi_yang_punya_task_hari_ini_diarahkan_ke_absen(): void
    {
        $this->scheduledTaskToday();

        $response = $this->actingAs($this->teknisi)->get('/q1/'.$this->code());

        $response->assertRedirect(route('qr.attendance.show', ['code' => $this->code()]));
    }

    #[Test]
    public function owner_tanpa_penugasan_tidak_dialihkan_ke_absen(): void
    {
        $this->scheduledTaskToday();

        $response = $this->actingAs($this->owner)->get('/q1/'.$this->code());

        $this->assertFalse(
            str_contains((string) $response->headers->get('Location'), '/absen'),
            'Owner tanpa penugasan tidak boleh masuk jalur absen.'
        );
        $this->assertSame(0, QrScanLog::where('purpose', 'attendance')->count());
    }
}
