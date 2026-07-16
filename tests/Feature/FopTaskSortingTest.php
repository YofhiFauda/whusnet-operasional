<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

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
            'task_number' => 'TFOP-2026-SORT-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Task ' . $seq,
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Issue ' . $seq,
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
}
