<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'name',
    'work_date',
    'created_by',
])]
class FopTaskTeam extends Model
{
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'fop_task_team_user', 'fop_task_team_id', 'user_id')
            ->withTimestamps();
    }

    public function fopTasks(): HasMany
    {
        return $this->hasMany(FopTask::class, 'team_id');
    }

    /**
     * Tim dianggap aktif selama masih ada task yang belum Selesai/Cancel.
     */
    public function isActive(): bool
    {
        return $this->fopTasks()->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])->exists();
    }

    /**
     * Ringkasan beban kerja per anggota tim: [user_id => jumlah task].
     */
    public function workloadSummary(): array
    {
        return $this->fopTasks()
            ->with('technicians:id')
            ->get()
            ->flatMap(fn (FopTask $t) => $t->technicians->pluck('id'))
            ->countBy()
            ->toArray();
    }

    /**
     * Cek konflik: teknisi yang mau dimasukin ke team ini, apa udah kepasang
     * di team lain yang masih aktif di tanggal (work_date) yang sama.
     *
     * @param  array<int>  $memberIds
     * @return array<int, array{user_name: string, team_name: string}> keyed by user_id
     */
    public static function findMemberConflicts(array $memberIds, string $workDate, ?int $excludeTeamId = null): array
    {
        $workDate = Carbon::parse($workDate)->toDateString();

        $teams = static::with('members:id,name')
            ->where('work_date', $workDate)
            ->when($excludeTeamId, fn ($q) => $q->where('id', '!=', $excludeTeamId))
            ->get()
            ->filter->isActive();

        $conflicts = [];
        foreach ($teams as $team) {
            foreach ($team->members as $member) {
                if (in_array($member->id, $memberIds)) {
                    $conflicts[$member->id] = [
                        'user_name' => $member->name,
                        'team_name' => $team->name,
                    ];
                }
            }
        }

        return $conflicts;
    }
}
