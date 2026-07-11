<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FopTask;
use App\Models\FopTaskTeam;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FopTaskTeamService
{
    /**
     * Rebuild seluruh struktur FopTaskTeam untuk 1 tanggal (task_date) berdasar
     * graf overlap teknisi (connected components), sesuai analisa Skenario A/B/C1/C2/C3
     * di docs/fop-task/analisa-auto-team.md.
     *
     * - Skenario A/B: task multi-teknisi otomatis jadi/bergabung ke 1 team (union-find).
     * - Skenario C1: solo task auto-merge ke team existing kalau teknisinya udah overlap.
     * - Skenario C2: solo task tanpa overlap dibiarkan team_id = null (manual drop-in di luar service ini).
     * - Skenario C3: task yang narik teknisi dari >=2 team existing berbeda TIDAK di-auto-union,
     *   dikembalikan sebagai conflict buat FOP putuskan manual.
     * - Task dengan manual_override_at terisi (hasil drop-in manual) tidak pernah disentuh di sini,
     *   kecuali sebagai sumber roster buat team yang jadi anchor-nya.
     *
     * @return array{conflicts: array<int, array{task_id:int, task_number:string, candidates: array<int, array{team_id:int, team_name:string}>}>}
     */
    public function rebuildTeamsForDate(Carbon $date): array
    {
        return DB::transaction(function () use ($date) {
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();

            $tasks = FopTask::with('technicians:id,name')
                ->whereBetween('task_date', [$start, $end])
                ->whereIn('status', ['Proses', 'Pending'])
                ->orderBy('id')
                ->get();

            $locked = $tasks->filter(fn (FopTask $t) => $t->manual_override_at !== null);
            $open = $tasks->reject(fn (FopTask $t) => $t->manual_override_at !== null);

            // Snapshot posisi team SEBELUM rebuild (termasuk dari task locked) —
            // dipakai buat deteksi Skenario C3 (narik dari >=2 team existing berbeda).
            $existingTeamOf = [];
            foreach ($tasks as $t) {
                if ($t->team_id === null) {
                    continue;
                }
                foreach ($t->technicians as $tech) {
                    $existingTeamOf[$tech->id] ??= $t->team_id;
                }
            }

            $parent = [];
            $find = function (int $id) use (&$parent, &$find) {
                $parent[$id] ??= $id;
                if ($parent[$id] !== $id) {
                    $parent[$id] = $find($parent[$id]);
                }

                return $parent[$id];
            };
            $union = function (int $a, int $b) use (&$parent, &$find) {
                $rootA = $find($a);
                $rootB = $find($b);
                if ($rootA !== $rootB) {
                    $parent[$rootB] = $rootA;
                }
            };

            $rootTeam = [];
            $conflicts = [];
            $soloTasks = [];
            $groupedTasks = [];

            foreach ($open as $task) {
                $ids = $task->technicians->pluck('id')->all();

                if (count($ids) === 0) {
                    continue;
                }

                if (count($ids) === 1) {
                    $soloTasks[] = $task;
                    continue;
                }

                $distinctTeams = collect($ids)
                    ->map(fn ($id) => $existingTeamOf[$id] ?? null)
                    ->filter()
                    ->unique()
                    ->values();

                if ($distinctTeams->count() >= 2) {
                    $candidates = FopTaskTeam::whereIn('id', $distinctTeams)->get()
                        ->map(fn (FopTaskTeam $team) => ['team_id' => $team->id, 'team_name' => $team->name])
                        ->values()
                        ->all();

                    $conflicts[] = [
                        'task_id' => $task->id,
                        'task_number' => $task->task_number,
                        'candidates' => $candidates,
                    ];

                    if ($task->team_id !== null) {
                        $task->team_id = null;
                        $task->save();
                    }

                    continue;
                }

                $first = $ids[0];
                foreach ($ids as $id) {
                    $union($first, $id);
                }

                $root = $find($first);
                if ($distinctTeams->count() === 1) {
                    $rootTeam[$root] = $distinctTeams->first();
                }

                $groupedTasks[] = $task;
            }

            foreach ($soloTasks as $task) {
                $id = $task->technicians->first()->id;
                $root = $find($id);

                $groupSize = collect(array_keys($parent))
                    ->filter(fn ($k) => $find($k) === $root)
                    ->count();

                // Anchor tambahan: teknisi ini udah punya team existing dari SEBELUM
                // rebuild ini jalan (lewat task ini sendiri atau task lain) — misal task
                // yang tadinya multi-teknisi di-shrink jadi solo. Tanpa ini, satu-satunya
                // teknisi yang tersisa bakal kelempar keluar dari teamnya sendiri dan
                // teamnya ikut kehapus di step cleanup (lihat analisa-sync-execution-task.md).
                $existingTeam = $existingTeamOf[$id] ?? null;
                if ($existingTeam !== null && !isset($rootTeam[$root])) {
                    $rootTeam[$root] = $existingTeam;
                }

                if (isset($rootTeam[$root]) || $groupSize > 1) {
                    $groupedTasks[] = $task;
                } elseif ($task->team_id !== null) {
                    $task->team_id = null;
                    $task->save();
                }
            }

            $groups = [];
            foreach ($groupedTasks as $task) {
                $anyId = $task->technicians->first()->id;
                $root = $find($anyId);
                $groups[$root][] = $task;
            }

            $touchedTeamIds = [];

            foreach ($groups as $root => $groupTasks) {
                $teamId = $rootTeam[$root] ?? collect($groupTasks)->pluck('team_id')->filter()->first();
                $team = $teamId ? FopTaskTeam::find($teamId) : null;
                $oldValues = $team ? $team->load('members:id,name')->toArray() : null;

                if (!$team) {
                    $team = FopTaskTeam::create([
                        'name' => $this->nextTeamName($date),
                        'work_date' => $date->toDateString(),
                        'created_by' => auth()->id(),
                    ]);
                }

                $memberIds = collect($groupTasks)
                    ->flatMap(fn (FopTask $t) => $t->technicians->pluck('id'))
                    ->merge(
                        $locked->where('team_id', $team->id)
                            ->flatMap(fn (FopTask $t) => $t->technicians->pluck('id'))
                    )
                    ->unique()
                    ->values();

                $team->members()->sync($memberIds->all());

                foreach ($groupTasks as $task) {
                    if ($task->team_id !== $team->id) {
                        $task->team_id = $team->id;
                        $task->save();
                    }
                }

                $touchedTeamIds[] = $team->id;

                if (class_exists(AuditLog::class)) {
                    AuditLog::log($team, $oldValues ? 'rebuild_update' : 'rebuild_create', $oldValues, [
                        'name' => $team->name,
                        'member_ids' => $memberIds->all(),
                        'task_ids' => collect($groupTasks)->pluck('id')->all(),
                    ]);
                }
            }

            // Hapus team di tanggal ini yang udah gak punya task aktif sama sekali.
            FopTaskTeam::whereDate('work_date', $date->toDateString())
                ->get()
                ->each(function (FopTaskTeam $team) {
                    if (!$team->isActive()) {
                        $oldValues = $team->load('members:id,name')->toArray();
                        $team->members()->detach();
                        $team->delete();

                        if (class_exists(AuditLog::class)) {
                            AuditLog::log($team, 'rebuild_delete', $oldValues, null);
                        }
                    }
                });

            // Sinkron nama team ke execution Task (`tasks.title`) buat semua task yang
            // ke-sentuh rebuild ini — biar teknisi di /tasks-saya & detail task gak lihat
            // nama team basi (lihat analisa-sync-execution-task.md bagian 6).
            foreach ($tasks as $task) {
                $this->syncExecutionTaskTitle($task);
            }

            return ['conflicts' => $conflicts];
        });
    }

    /**
     * Refresh `Task.title` (execution layer) supaya prefix "[Nama Team]"-nya
     * selalu cerminan roster/nama `FopTaskTeam` yang sebenarnya saat ini —
     * bukan nama ad-hoc yang di-bake sekali pas task dibuat/di-edit.
     * Sengaja update kolom langsung (bukan lewat TaskService::update()) biar
     * gak mancing notifikasi/reassignment/side-effect lain, dan gak recurse
     * balik manggil rebuildTeamsForDate() lewat TaskService::syncToFopTask().
     */
    private function syncExecutionTaskTitle(FopTask $fopTask): void
    {
        if (!$fopTask->task_id) {
            return;
        }

        $execTask = $fopTask->task ?: Task::find($fopTask->task_id);
        if (!$execTask) {
            return;
        }

        $teamName = $fopTask->team_id ? $fopTask->team?->name : null;
        $baseTitle = 'FOP: ' . $fopTask->tugas;
        $newTitle = $teamName ? "[{$teamName}] {$baseTitle}" : $baseTitle;

        if ($execTask->title !== $newTitle) {
            $execTask->title = $newTitle;
            $execTask->save();
        }
    }

    /**
     * Nama Team baru: "Team 1", "Team 2", dst — increment per `work_date`,
     * pakai nomor terkecil yang belum kepakai (biar celah dari team yang
     * kehapus ke-reuse, bukan cuma nambah terus).
     */
    public function nextTeamName(Carbon $date): string
    {
        $usedNumbers = FopTaskTeam::whereDate('work_date', $date->toDateString())
            ->pluck('name')
            ->map(fn ($name) => (int) preg_replace('/[^0-9]/', '', $name))
            ->filter()
            ->all();

        $n = 1;
        while (in_array($n, $usedNumbers, true)) {
            $n++;
        }

        return 'Team ' . $n;
    }
}
