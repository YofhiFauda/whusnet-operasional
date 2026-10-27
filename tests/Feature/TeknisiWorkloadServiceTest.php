<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskTeam;
use App\Models\TaskWorkTool;
use App\Models\User;
use App\Services\TeknisiWorkloadService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeknisiWorkloadServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ringkasan_mengisi_bar_beban_dan_badge_alat_task_aktif(): void
    {
        $this->seed(RoleSeeder::class);

        $pop = Pop::create(['code' => 'RAD', 'name' => 'POP Radar', 'type' => 'cabang', 'status' => 'active']);
        $technician = User::factory()->create([
            'role_id' => Role::where('code', 'teknisi')->firstOrFail()->id,
        ]);

        for ($index = 1; $index <= 6; $index++) {
            $task = Task::create([
                'task_number' => 'TASK-RAD-'.$index,
                'pop_id' => $pop->id,
                'task_type' => TaskType::MAINTENANCE->value,
                'title' => 'Task radar '.$index,
                'status' => $index === 1 ? TaskStatus::IN_PROGRESS->value : TaskStatus::TERJADWAL->value,
                'scheduled_at' => now(),
                'started_at' => $index === 1 ? now() : null,
                'created_by' => $technician->id,
            ]);
            TaskTeam::create(['task_id' => $task->id, 'user_id' => $technician->id]);

            if ($index === 1) {
                $fopTask = FopTask::create([
                    'task_number' => 'TFOP-RAD-1',
                    'task_date' => now(),
                    'category' => TaskType::MAINTENANCE->value,
                    'task_id' => $task->id,
                    'tugas' => 'Task radar 1',
                    'pop_id' => $pop->id,
                    'status' => TaskStatus::IN_PROGRESS->value,
                    'priority' => 'Medium',
                ]);
                TaskWorkTool::create([
                    'fop_task_id' => $fopTask->id,
                    'tool_name' => 'Fusion Splicer',
                    'recorded_by' => $technician->id,
                ]);
            }
        }

        $row = app(TeknisiWorkloadService::class)->summarize(true, [$pop->id])->firstWhere('id', $technician->id);

        $this->assertSame(5, $row['task_count']);
        $this->assertSame(100, $row['workload_percent']);
        $this->assertTrue($row['overloaded']);
        $this->assertSame(['Fusion Splicer'], $row['tool_badges']);
    }
}
