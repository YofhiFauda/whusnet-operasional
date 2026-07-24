<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FopTaskSortingTest extends TestCase
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

        $fopRole = Role::where('code', 'fop')->first();
        $this->fopUser = User::factory()->create(['role_id' => $fopRole->id]);

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

    private function makeFopTask(array $overrides = []): FopTask
    {
        static $seq = 0;
        $seq++;

        return FopTask::create(array_merge([
            'task_number' => 'TFOP-2026-SORT-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Task '.$seq,
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Issue '.$seq,
            'status' => 'terjadwal',
            'priority' => 'low',
        ], $overrides));
    }

    public function test_task_with_future_client_request_date_sinks_below_others(): void
    {
        $upcoming = $this->makeFopTask([
            'priority' => 'Urgent',
            'client_request_date' => Carbon::today()->addDays(3),
        ]);
        $normal = $this->makeFopTask([
            'priority' => 'low',
        ]);

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
        $response->assertOk();

        $content = $response->getContent();
        $posNormal = strpos($content, $normal->task_number);
        $posUpcoming = strpos($content, $upcoming->task_number);

        $this->assertNotFalse($posNormal);
        $this->assertNotFalse($posUpcoming);
        $this->assertLessThan($posUpcoming, $posNormal, 'Task tanpa upcoming client_request_date harus tampil di atas task dengan client_request_date masa depan, walau priority-nya kalah.');
    }

    public function test_task_with_today_client_request_date_follows_normal_priority_sort(): void
    {
        $todayRequest = $this->makeFopTask([
            'priority' => 'Urgent',
            'client_request_date' => Carbon::today(),
        ]);
        $lowPriority = $this->makeFopTask([
            'priority' => 'low',
        ]);

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
        $response->assertOk();

        $content = $response->getContent();
        $posToday = strpos($content, $todayRequest->task_number);
        $posLow = strpos($content, $lowPriority->task_number);

        $this->assertNotFalse($posToday);
        $this->assertNotFalse($posLow);
        $this->assertLessThan($posLow, $posToday, 'Task dengan client_request_date hari ini harus ikut sorting normal (Urgent di atas low priority), bukan ke-sink ke bawah.');
    }

    public function test_priority_sorting_regression_unaffected(): void
    {
        $urgent = $this->makeFopTask(['priority' => 'Urgent']);
        $high = $this->makeFopTask(['priority' => 'High']);
        $medium = $this->makeFopTask(['priority' => 'Medium']);
        $low = $this->makeFopTask(['priority' => 'low']);

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
        $response->assertOk();

        $content = $response->getContent();
        $positions = [
            'Urgent' => strpos($content, $urgent->task_number),
            'High' => strpos($content, $high->task_number),
            'Medium' => strpos($content, $medium->task_number),
            'low' => strpos($content, $low->task_number),
        ];

        $this->assertLessThan($positions['High'], $positions['Urgent']);
        $this->assertLessThan($positions['Medium'], $positions['High']);
        $this->assertLessThan($positions['low'], $positions['Medium']);
    }

    public function test_category_survey_psb_created_at_ascending_regression_unaffected(): void
    {
        // Pakai category 'PSB' (bukan 'SURVEY') karena literal CASE existing di controller
        // ('Survey', 'PSB') persis match sama nilai backing enum TaskType::PEMASANGAN ('PSB'),
        // sementara TaskType::SURVEY backing value-nya 'SURVEY' (uppercase) — mismatch case
        // sama literal 'Survey' di CASE existing itu bug pre-existing di luar scope Task 8,
        // jangan disentuh di sini. Pakai 'PSB' biar regresi CASE existing ini teruji akurat.
        $olderSurvey = $this->makeFopTask(['category' => 'PSB']);
        $olderSurvey->created_at = now()->subMinutes(10);
        $olderSurvey->save();

        $newerSurvey = $this->makeFopTask(['category' => 'PSB']);
        $newerSurvey->created_at = now();
        $newerSurvey->save();

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
        $response->assertOk();

        $content = $response->getContent();
        $posOlder = strpos($content, $olderSurvey->task_number);
        $posNewer = strpos($content, $newerSurvey->task_number);

        $this->assertLessThan($posNewer, $posOlder, 'Category Survey/PSB harus tetap ASC by created_at (yang lama duluan).');
    }

    public function test_badge_jadwal_hari_ini_shown_when_client_request_date_is_today_or_past(): void
    {
        $task = $this->makeFopTask([
            'client_request_date' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
        $response->assertOk();
        $response->assertSee('JADWAL HARI INI');
        $response->assertSee($task->task_number);
    }

    public function test_badge_terjadwal_shown_when_client_request_date_is_future(): void
    {
        $futureDate = Carbon::today()->addDays(5);
        $task = $this->makeFopTask([
            'client_request_date' => $futureDate,
        ]);

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
        $response->assertOk();
        $response->assertSee('Terjadwal');
        $response->assertSee($futureDate->format('d/m/Y'));
    }

    public function test_end_to_end_survey_request_date_sinks_then_rises_when_scheduled(): void
    {
        // Alur nyata: Survey selesai, FOP set Task jadi Pending sambil isi
        // client_request_date lewat endpoint update() (bukan set atribut manual),
        // sesuai Task 13 — pastikan cuma 1 sorting query (Task 8) yang jalan.
        $surveyTask = $this->makeFopTask([
            'category' => 'SURVEY',
            'priority' => 'Urgent',
        ]);
        $otherTask = $this->makeFopTask([
            'priority' => 'low',
        ]);

        $requestedInstallDate = Carbon::today()->addDays(4);

        $updateResponse = $this->actingAs($this->fopUser)->put(route('fop-tasks.update', $surveyTask), [
            'status' => 'pending',
            'pending_reason' => 'Request tanggal pemasangan dari pelanggan saat survey',
            'client_request_date' => $requestedInstallDate->toDateString(),
        ]);
        $updateResponse->assertRedirect();

        $surveyTask->refresh();
        $this->assertNotNull($surveyTask->client_request_date);
        $this->assertTrue($surveyTask->client_request_date->isSameDay($requestedInstallDate));

        // Belum jadwalnya: task hasil survey (walau Urgent) harus tenggelam
        // di bawah task lain meski priority-nya kalah.
        $sunkResponse = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
        $sunkResponse->assertOk();
        $sunkContent = $sunkResponse->getContent();
        $posOtherSunk = strpos($sunkContent, $otherTask->task_number);
        $posSurveySunk = strpos($sunkContent, $surveyTask->task_number);
        $this->assertNotFalse($posOtherSunk);
        $this->assertNotFalse($posSurveySunk);
        $this->assertLessThan($posSurveySunk, $posOtherSunk, 'Sebelum jadwalnya tiba, task hasil request survey harus di bawah.');

        // Jadwalnya tiba: pura-pura hari ini sudah requestedInstallDate.
        Carbon::setTestNow($requestedInstallDate->copy()->startOfDay());
        try {
            $risenResponse = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
            $risenResponse->assertOk();
            $risenContent = $risenResponse->getContent();
            $posOtherRisen = strpos($risenContent, $otherTask->task_number);
            $posSurveyRisen = strpos($risenContent, $surveyTask->task_number);
            $this->assertNotFalse($posOtherRisen);
            $this->assertNotFalse($posSurveyRisen);
            $this->assertLessThan($posOtherRisen, $posSurveyRisen, 'Begitu jadwalnya tiba, task naik ke atas ikut sorting normal (Urgent > low).');
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Regresi ANALISA_REDUNDANSI_LOGIC.md §1: dedupe guard auto-sync survey
     * (FopTaskController::autoSyncAndCalculatePriority(), sebelumnya
     * `->where('category', 'Survey')`) sempat bandingin literal 'Survey' ke
     * kolom category yg isinya 'SURVEY' (TaskType enum di-uppercase-in) — gak
     * pernah match, jadi tiap kali index() diakses bikin FopTask survey
     * DUPLIKAT terus buat customer yg sama. Test ini pastikan customer yg
     * udah punya FopTask survey aktif gak didobel lagi walau index() diakses
     * berkali-kali.
     */
    public function test_auto_sync_does_not_duplicate_existing_active_survey_task(): void
    {
        $customer = Customer::create([
            'customer_code' => 'C00RQ000099',
            'full_name' => 'Pelanggan Antri Survey',
            'primary_phone' => '081200000099',
            'registration_date' => now(),
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'status' => 'waiting_survey',
        ]);

        $existingSurveyTask = $this->makeFopTask([
            'category' => TaskType::SURVEY->value,
            'status' => TaskStatus::DRAFT->value,
            'customer_id' => $customer->id,
        ]);

        // Akses index() dua kali — ini yg trigger autoSyncAndCalculatePriority()
        // tiap request. Sebelum fix, tiap panggilan nambah 1 FopTask duplikat.
        $this->actingAs($this->fopUser)->get(route('fop-tasks.index'))->assertOk();
        $this->actingAs($this->fopUser)->get(route('fop-tasks.index'))->assertOk();

        $surveyTaskCount = FopTask::where('customer_id', $customer->id)
            ->where('category', TaskType::SURVEY->value)
            ->count();

        $this->assertEquals(1, $surveyTaskCount, 'Auto-sync gak boleh bikin FopTask survey duplikat buat customer yg udah punya task aktif.');
        $this->assertTrue(FopTask::where('id', $existingSurveyTask->id)->exists());
    }

    /**
     * Regresi ANALISA_REDUNDANSI_LOGIC.md §2: SLA-window priority-recalc
     * sebelumnya hand-roll literal `addDay()`/86400 detik, sekarang pakai
     * `TaskType::defaultHandlingSlaHours()` (satu sumber kebenaran yg sama
     * dipakai FopTask.php). Refactor murni angka (24 jam == addDay()), tapi
     * belum ada test sama sekali buat logic priority-recalc ini sebelumnya —
     * test ini nutup gap-nya: customer survey yg SLA-nya (24 jam) udah
     * lewat harus otomatis naik jadi Urgent.
     */
    public function test_survey_task_priority_escalates_to_urgent_when_sla_overdue(): void
    {
        $customer = Customer::create([
            'customer_code' => 'C00RQ000098',
            'full_name' => 'Pelanggan SLA Lewat',
            'primary_phone' => '081200000098',
            'registration_date' => now()->subDays(2),
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'status' => 'waiting_survey',
        ]);
        // SLA survey defaultnya 24 jam (TaskType::SURVEY->defaultHandlingSlaHours()) —
        // paksa created_at 2 hari lalu biar udah lewat deadline.
        DB::table('customers')
            ->where('id', $customer->id)
            ->update(['created_at' => now()->subDays(2)]);

        $surveyTask = $this->makeFopTask([
            'category' => TaskType::SURVEY->value,
            'status' => TaskStatus::DRAFT->value,
            'priority' => 'low',
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($this->fopUser)->get(route('fop-tasks.index'))->assertOk();

        $surveyTask->refresh();
        $this->assertEquals(FopTaskPriority::URGENT->value, $surveyTask->priority->value);
    }
}
