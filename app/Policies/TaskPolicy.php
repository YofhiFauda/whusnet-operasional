<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * TaskPolicy — semua pengecekan via $user->can() / permission dinamis.
 * TIDAK ADA pengecekan nama role (hasRole) di sini.
 */
class TaskPolicy
{
    /**
     * Owner dengan wildcard permission bypass semua.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasPermission('*')) {
            return true;
        }

        return null;
    }

    // ─── FOP permissions ─────────────────────────────────────────

    /**
     * Melihat semua task (FOP/Admin level).
     */
    public function viewAll(User $user): bool
    {
        return $user->can('task.view.all');
    }

    /**
     * Teknisi melihat hanya task di mana dia terdaftar sebagai anggota.
     */
    public function viewOwn(User $user): bool
    {
        return $user->can('task.view.own');
    }

    /**
     * Melihat detail satu task — FOP bisa view all, Teknisi hanya task miliknya.
     */
    public function view(User $user, Task $task): bool
    {
        if ($user->can('task.view.all')) {
            return true;
        }

        if ($user->can('task.view.own') && $task->isMember($user->id)) {
            return true;
        }

        return false;
    }

    /**
     * Membuat task baru.
     */
    public function create(User $user): bool
    {
        return $user->can('task.create');
    }

    /**
     * Mengedit task (judul, deskripsi, catatan).
     */
    public function edit(User $user, Task $task): bool
    {
        if (!$user->hasPermission('task.edit')) {
            return false;
        }

        // Hanya bisa edit jika status masih bisa diedit
        return $task->status->isEditable();
    }

    /**
     * Menjadwalkan atau menjadwal ulang task.
     */
    public function schedule(User $user, Task $task): bool
    {
        return $this->canTransitionTo($user, $task, 'terjadwal') && $task->status->isEditable();
    }

    /**
     * Mengassign anggota tim ke task.
     */
    public function assignTeam(User $user, Task $task): bool
    {
        return $user->hasPermission('task.assign.team') && $task->status->isEditable();
    }

    /**
     * Membatalkan task.
     */
    public function cancel(User $user, Task $task): bool
    {
        return $this->canTransitionTo($user, $task, 'dibatalkan') && !in_array($task->status->value, ['selesai', 'dibatalkan']);
    }

    /**
     * Override konflik jadwal saat membuat/edit task.
     */
    public function conflictOverride(User $user): bool
    {
        return $user->hasPermission('task.conflict.override');
    }

    /**
     * Melihat laporan task.
     */
    public function viewReport(User $user): bool
    {
        return $user->hasPermission('task.report.view');
    }

    /**
     * FOP: Reject pending task
     */
    public function fopReject(User $user, Task $task): bool
    {
        return $this->canTransitionTo($user, $task, 'rejected') && $task->status->value === 'pending';
    }

    /**
     * FOP: Set scheduled task to pending
     */
    public function fopPending(User $user, Task $task): bool
    {
        return $this->canTransitionTo($user, $task, 'pending') && in_array($task->status->value, ['terjadwal', 'in_progress']);
    }

    /**
     * FOP: Review completed task
     */
    public function review(User $user, Task $task): bool
    {
        return $this->canTransitionTo($user, $task, 'approved') && $task->status->value === 'selesai' && $task->fop_review_status !== 'approved';
    }

    // ─── Teknisi permissions ─────────────────────────────────────

    /**
     * Mulai mengerjakan task.
     */
    public function statusStart(User $user, Task $task): bool
    {
        return $this->canTransitionTo($user, $task, 'in_progress') && $task->isMember($user->id);
    }

    /**
     * Selesaikan task.
     */
    public function statusComplete(User $user, Task $task): bool
    {
        return $this->canTransitionTo($user, $task, 'selesai') && $task->isMember($user->id);
    }

    /**
     * Set task ke Pending.
     */
    public function statusPending(User $user, Task $task): bool
    {
        return $this->canTransitionTo($user, $task, 'pending') && $task->isMember($user->id);
    }

    /**
     * Update item checklist.
     */
    public function updateChecklist(User $user, Task $task): bool
    {
        return $user->hasPermission('task.checklist.update') && $task->isMember($user->id);
    }

    /**
     * Upload foto bukti.
     */
    public function uploadEvidence(User $user, Task $task): bool
    {
        return $user->hasPermission('task.evidence.upload') && $task->isMember($user->id);
    }

    // ─── Dynamic Workflow Transitions Helper ──────────────────────────

    /**
     * Cek apakah user berhak melakukan transisi status task tertentu berdasarkan database configuration.
     */
    private function canTransitionTo(User $user, Task $task, string $newStatus): bool
    {
        $fromStatus = $task->status instanceof \App\Enums\TaskStatus ? $task->status->value : $task->status;

        $rule = \App\Models\WorkflowTransitionPermission::where('from_status', $fromStatus)
            ->where('to_status', $newStatus)
            ->first();

        if (!$rule) {
            return false;
        }

        // Cek apakah role user terhubung dengan rule transisi status ini
        $hasRole = $rule->roles()->where('roles.id', $user->role_id)->exists();
        if (!$hasRole) {
            return false;
        }

        // Cek apakah user memiliki permission string terkait
        return $user->hasPermission($rule->permission_name);
    }
}
