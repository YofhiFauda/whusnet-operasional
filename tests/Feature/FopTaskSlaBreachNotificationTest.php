<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\NotificationType;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AppNotification;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `fop-tasks:check-sla-breach` — sebelumnya nol alert SLA breach sama sekali
 * (docs/plan/analisa-status-implementasi-notifikasi.md §5).
 */
class FopTaskSlaBreachNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected User $fopUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SLB1',
            'pop_code' => 'SLB1',
            'registration_prefix' => 'CS',
            'cid_prefix' => 'DS',
            'name' => 'POP SLA Breach Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $fopRole = Role::where('code', 'fop')->first();
        $this->fopUser = User::factory()->create(['role_id' => $fopRole->id, 'status' => 'active']);
        $this->fopUser->roleScopes()->create([
            'role_id' => $fopRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);
    }

    private function makeFopTask(array $overrides = []): FopTask
    {
        return FopTask::create(array_merge([
            'task_number' => 'TFOP-2026-'.random_int(1000, 9999),
            'task_date' => now()->subHours(5),
            'category' => TaskType::MAINTENANCE,
            'tugas' => 'C001_Pelanggan Breach Test',
            'pop_id' => $this->pop->id,
            'issue' => 'Internet mati',
            'status' => TaskStatus::DRAFT,
            'priority' => FopTaskPriority::MEDIUM,
            'handling_sla_hours' => 1,
        ], $overrides));
    }

    public function test_breached_fop_task_notifies_fop_role_and_marks_notified(): void
    {
        $fopTask = $this->makeFopTask();

        Notification::fake();

        $this->artisan('fop-tasks:check-sla-breach')->assertSuccessful();

        Notification::assertSentTo(
            $this->fopUser,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::WARNING
                && str_contains($notification->title, $fopTask->task_number)
        );

        $this->assertNotNull($fopTask->fresh()->sla_breach_notified_at);
    }

    public function test_does_not_renotify_already_flagged_task(): void
    {
        $fopTask = $this->makeFopTask(['sla_breach_notified_at' => now()->subMinutes(10)]);

        Notification::fake();

        $this->artisan('fop-tasks:check-sla-breach')->assertSuccessful();

        Notification::assertNothingSentTo($this->fopUser);
        // sengaja unchanged dari nilai awal.
        $this->assertNotNull($fopTask->fresh()->sla_breach_notified_at);
    }

    public function test_task_not_yet_breached_is_not_notified(): void
    {
        $this->makeFopTask([
            'task_date' => now(),
            'handling_sla_hours' => 24,
        ]);

        Notification::fake();

        $this->artisan('fop-tasks:check-sla-breach')->assertSuccessful();

        Notification::assertNothingSentTo($this->fopUser);
    }

    public function test_completed_task_is_excluded_even_if_overdue(): void
    {
        $this->makeFopTask([
            'status' => TaskStatus::SELESAI,
        ]);

        Notification::fake();

        $this->artisan('fop-tasks:check-sla-breach')->assertSuccessful();

        Notification::assertNothingSentTo($this->fopUser);
    }

    public function test_rescheduling_task_date_resets_notified_flag(): void
    {
        $fopTask = $this->makeFopTask(['sla_breach_notified_at' => now()->subMinutes(10)]);

        $fopTask->update(['task_date' => now()->subHours(2)]);

        $this->assertNull($fopTask->fresh()->sla_breach_notified_at);
    }
}
