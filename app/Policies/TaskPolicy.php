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
        // 'cancel' sengaja DIKELUARIN dari bypass wildcard — SRV/PSB gak boleh
        // dibatalkan dari Task sama sekali (harus lewat halaman Customer),
        // aturan ini berlaku buat SEMUA role termasuk owner/admin.
        if ($ability === 'cancel') {
            return null;
        }

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
     * Akses utility API terkait task: pencarian pelanggan & cek konflik jadwal.
     * (Dulu dipakai utk gate form "Buat Task" — form itu sudah dihapus,
     * permission ini sekarang murni utility API.)
     */
    public function lookup(User $user): bool
    {
        return $user->can('task.lookup');
    }

    /**
     * Mengedit task (judul, deskripsi, catatan).
     */
    public function edit(User $user, Task $task): bool
    {
        if (!$user->hasPermission('task.manage')) {
            return false;
        }

        // Hanya bisa edit jika status masih bisa diedit
        return $task->status->isEditable();
    }

    /**
     * Mengubah tipe task pada form edit.
     */
    public function editType(User $user, Task $task): bool
    {
        return $user->hasPermission('task.edit.type') && $task->status->isEditable();
    }

    /**
     * Menjadwalkan atau menjadwal ulang task.
     */
    public function schedule(User $user, Task $task): bool
    {
        if ($user->hasPermission('task.manage') || $user->hasPermission('task.assign.team')) {
            return $task->status->isEditable();
        }
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
        // SRV/PSB terikat ke workflow Customer (List Pelanggan Gagal) — cancel
        // buat 2 tipe ini WAJIB lewat halaman Customer (CustomerSurveyController/
        // CustomerInstallationController::cancel()), bukan tombol Task langsung.
        if (in_array($task->task_type, [\App\Enums\TaskType::SURVEY, \App\Enums\TaskType::PEMASANGAN], true)) {
            return false;
        }

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
     * FOP: Reject pending task
     */
    public function fopReject(User $user, Task $task): bool
    {
        return $user->hasPermission('task.reject') && $task->status->value === 'pending';
    }

    /**
     * FOP: Set scheduled task to pending
     */
    public function fopPending(User $user, Task $task): bool
    {
        return $user->hasPermission('task.manage') && in_array($task->status->value, ['terjadwal', 'in_progress']);
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
        $canTransition = $this->canTransitionTo($user, $task, 'in_progress');
        
        $statusValue = $task->status instanceof \App\Enums\TaskStatus ? $task->status->value : $task->status;

        if (!$canTransition && ($user->hasPermission('task.execute') || $task->task_type->value === \App\Enums\TaskType::MAINTENANCE->value)) {
            $canTransition = in_array($statusValue, ['terjadwal', 'pending']);
        }

        return $canTransition && $task->isMember($user->id);
    }

    /**
     * Selesaikan task.
     */
    public function statusComplete(User $user, Task $task): bool
    {
        $canTransition = $this->canTransitionTo($user, $task, 'selesai');
        if (!$canTransition && $task->status === \App\Enums\TaskStatus::PENDING && in_array($task->task_type->value, [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value])) {
            $canTransition = true;
        }
        return $canTransition && $task->isMember($user->id);
    }

    /**
     * Set task ke Pending.
     */
    public function statusPending(User $user, Task $task): bool
    {
        return $user->hasPermission('task.execute') && $task->isMember($user->id);
    }

    /**
     * Pending top-level (reschedule penuh) — beda dari statusPending (Lapor Nanti,
     * assignment tetap) dan fopPending (FOP-side, assignment tetap). Ini lepas
     * assignment & balik ke antrian FOP, cuma boleh sebelum task selesai.
     */
    public function statusReschedule(User $user, Task $task): bool
    {
        return $user->hasPermission('task.execute')
            && $task->isMember($user->id)
            && in_array($task->status->value, ['terjadwal', 'in_progress']);
    }

    /**
     * Upload foto bukti.
     */
    public function uploadEvidence(User $user, Task $task): bool
    {
        return $user->hasPermission('task.execute') && $task->isMember($user->id);
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
