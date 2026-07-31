<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerSurvey;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tanggal request pemasangan di papan FOP:
 * - diturunkan dari survey (dan dipulihkan tiap sync, bukan sekali salin)
 * - membebaskan task dari SLA selama tanggalnya belum tiba
 * - jadi deadline countdown begitu tanggalnya tiba/lewat
 */
class FopTaskClientRequestDateTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private Village $village;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $ownerRole = Role::where('code', 'owner')->first();
        $this->fopUser = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $this->village = Village::create([
            'district_id' => $district->id,
            'name' => 'Polorejo',
            'postal_code' => '63491',
        ]);

        $this->pop = Pop::create([
            'name' => 'POP Polorejo',
            'code' => 'POP-PLR',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
            'city_id' => $city->id,
        ]);
    }

    private function makeCustomer(?string $requestedDate, string $status = 'waiting_installation'): Customer
    {
        static $seq = 0;
        $seq++;

        $customer = Customer::create([
            'customer_code' => 'CRD-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'full_name' => 'Pelanggan '.$seq,
            'primary_phone' => '08120000'.$seq,
            'status' => $status,
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        CustomerSurvey::create([
            'customer_id' => $customer->id,
            'survey_status' => 'completed',
            'nearest_odp' => 'ODP-01',
            'cable_estimation_meter' => 50,
            'requested_installation_date' => $requestedDate,
        ]);

        return $customer;
    }

    private function makePsbTask(Customer $customer, array $overrides = []): FopTask
    {
        static $seq = 0;
        $seq++;

        return FopTask::create(array_merge([
            'task_number' => 'TFOP-2026-CRD-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'task_date' => now(),
            'category' => TaskType::PEMASANGAN->value,
            'tugas' => 'Pemasangan '.$seq,
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'issue' => 'Auto-Sync dari antrean pemasangan',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ], $overrides));
    }

    public function test_auto_sync_mengambil_tanggal_request_dari_survey(): void
    {
        $requested = Carbon::today()->addDays(21)->toDateString();
        $customer = $this->makeCustomer($requested);

        $this->actingAs($this->fopUser)->get(route('fop-tasks.index'))->assertOk();

        $task = FopTask::where('customer_id', $customer->id)
            ->where('category', TaskType::PEMASANGAN->value)
            ->first();

        $this->assertNotNull($task, 'Auto-sync harus membuat FopTask Pemasangan untuk pelanggan waiting_installation.');
        $this->assertSame($requested, $task->client_request_date->toDateString());
    }

    public function test_tanggal_request_dipulihkan_setelah_dinolkan_transisi_status(): void
    {
        $requested = Carbon::today()->addDays(10)->toDateString();
        $customer = $this->makeCustomer($requested);

        // Simulasi FopTaskController::update() yang me-null-kan field ini tiap
        // status keluar dari Pending — tanpa langkah refresh, tanggal request
        // pelanggan hilang permanen begitu FOP menjadwalkan task.
        $task = $this->makePsbTask($customer, ['client_request_date' => null, 'status' => TaskStatus::TERJADWAL->value]);

        $this->actingAs($this->fopUser)->get(route('fop-tasks.index'))->assertOk();

        $this->assertSame($requested, $task->fresh()->client_request_date->toDateString());
    }

    public function test_tanggal_request_masa_depan_tidak_kena_sla_dan_dipaksa_low(): void
    {
        $customer = $this->makeCustomer(Carbon::today()->addDays(21)->toDateString());

        // Survey selesai jauh di masa lalu: tanpa guard, SLA Pemasangan sudah
        // lewat dan prioritasnya bakal naik ke URGENT.
        $customer->update(['updated_at' => Carbon::now()->subDays(30)]);

        $task = $this->makePsbTask($customer, ['priority' => FopTaskPriority::URGENT->value]);

        $this->actingAs($this->fopUser)->get(route('fop-tasks.index'))->assertOk();

        $this->assertSame(FopTaskPriority::LOW, $task->fresh()->priority);
    }

    public function test_deadline_memakai_akhir_hari_tanggal_request(): void
    {
        $requested = Carbon::today()->addDays(5);
        $customer = $this->makeCustomer($requested->toDateString());
        $task = $this->makePsbTask($customer, ['client_request_date' => $requested->toDateString()]);

        $task->refresh()->loadMissing('customer');

        $this->assertTrue($task->usesClientRequestDeadline());
        $this->assertSame(
            $requested->copy()->endOfDay()->toDateTimeString(),
            $task->slaDeadline()->toDateTimeString()
        );
        $this->assertSame(86400, $task->slaTotalSeconds());
    }

    public function test_tanggal_request_lewat_menghasilkan_sisa_waktu_negatif(): void
    {
        $requested = Carbon::today()->subDay();
        $customer = $this->makeCustomer(null);
        $task = $this->makePsbTask($customer, ['client_request_date' => $requested->toDateString()]);

        $task->refresh()->loadMissing('customer');

        // Sisa detik negatif = komponen countdown-timer masuk state TERLAMBAT
        // dan menampilkan −HH:MM:SS.
        $this->assertLessThan(0, $task->slaDeadline()->diffInSeconds(Carbon::now(), false) * -1);
        $this->assertFalse($task->isScheduledForFutureClientDate());
    }

    public function test_tanpa_tanggal_request_perilaku_sla_lama_tidak_berubah(): void
    {
        $customer = $this->makeCustomer(null);
        $task = $this->makePsbTask($customer);

        $task->refresh()->loadMissing('customer');

        $this->assertFalse($task->usesClientRequestDeadline());
        $this->assertNotSame(86400, $task->slaTotalSeconds());
    }
}
