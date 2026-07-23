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
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi ANALISA_REDUNDANSI_LOGIC.md §6: FopTask::slaDeadline() cabang
 * PEMASANGAN pakai Collection::where('task_type'/'status', ...) di memory,
 * dibandingin ke ->value string — padahal task_type & status di-cast enum.
 * Enum vs string loose-compare gak pernah match, jadi $surveyTask SELALU
 * null, deadline PEMASANGAN SELALU fallback ke customer.updated_at (bukan
 * completed_at survey yg sebenarnya) — sama kelas bug kayak §0.
 */
class FopTaskSlaDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Village $village;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $city = City::create(['name' => 'Kota']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Distrik']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Desa', 'postal_code' => '11111']);

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    public function test_pemasangan_sla_deadline_uses_completed_survey_task_date_not_customer_updated_at(): void
    {
        $surveyCompletedAt = Carbon::parse('2026-06-01 10:00:00');
        $customerUpdatedAt = Carbon::parse('2026-01-01 00:00:00'); // sengaja beda jauh biar ketauan kalau salah fallback

        $customer = Customer::create([
            'customer_code' => 'C00RQ000097',
            'full_name' => 'Pelanggan Sudah Survey',
            'phone' => '081200000097',
            'registration_date' => now(),
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'status' => 'surveyed',
        ]);
        DB::table('customers')
            ->where('id', $customer->id)
            ->update(['updated_at' => $customerUpdatedAt]);

        Task::create([
            'task_number' => 'TASK-2026-SLA01',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Pelanggan',
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => $surveyCompletedAt,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $installTask = FopTask::create([
            'task_number' => 'TFOP-2026-SLA01',
            'task_date' => now(),
            'category' => TaskType::PEMASANGAN->value,
            'tugas' => 'Pemasangan Baru',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'issue' => 'Auto-sync',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        $deadline = $installTask->slaDeadline();

        // Deadline harus = completed_at survey + 72 jam (default handling SLA
        // PEMASANGAN), BUKAN customer.updated_at + 72 jam.
        $expected = $surveyCompletedAt->copy()->addHours(TaskType::PEMASANGAN->defaultHandlingSlaHours());
        $this->assertTrue($deadline->eq($expected), "Deadline harus dari completed_at survey ({$expected}), dapat {$deadline}.");
        $this->assertFalse(
            $deadline->eq($customerUpdatedAt->copy()->addHours(TaskType::PEMASANGAN->defaultHandlingSlaHours())),
            'Deadline gak boleh fallback ke customer.updated_at padahal survey completed_at ada.'
        );
    }
}
