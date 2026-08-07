<?php

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTransitionPermission;
use App\Services\EffectiveAccessService;

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
        // 'cancel' & 'cancelViaFopTask' sengaja DIKELUARIN dari bypass wildcard —
        // SRV/PSB gak boleh dibatalkan lewat jalur Task sama sekali (harus lewat
        // halaman Customer), aturan ini berlaku buat SEMUA role termasuk
        // owner/admin. Tanpa pengecualian ini, owner ber-permission '*' bakal
        // nembus invarian SRV/PSB di dua method itu.
        if (in_array($ability, ['cancel', 'cancelViaFopTask'], true)) {
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
            return $this->withinPopScope($user, $task);
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
        if (! $user->hasPermission('task.manage')) {
            return false;
        }

        if (! $this->withinPopScope($user, $task)) {
            return false;
        }

        // Hanya bisa edit jika status masih bisa diedit
        return $task->status->isEditable();
    }

    /**
     * Guard scope POP — `task.view.all`/`task.manage` cuma permission, gak
     * pernah cek `pop_id`. Tanpa ini, role apapun yang pegang permission itu
     * (lumayan umum, mis. FOP/Admin) bisa lihat/edit task dari POP manapun
     * lewat URL langsung (IDOR, lihat docs/plan/analisa-celah-scope-pop.md).
     */
    private function withinPopScope(User $user, Task $task): bool
    {
        $access = app(EffectiveAccessService::class);

        if ($access->hasAllPopAccess($user)) {
            return true;
        }

        return in_array((int) $task->pop_id, $access->getAllowedPopIds($user), true);
    }

    /**
     * Mengubah tipe task pada form edit.
     */
    public function editType(User $user, Task $task): bool
    {
        // Tipe auto-only (Survey/Pemasangan/Ambil Modem) gak boleh diubah dari
        // sini sama sekali — asalnya dari alur auto-sync (Registrasi Pelanggan
        // / tombol Ambil Alat), bukan pilihan manual. Lihat TaskType::autoOnlyValues().
        if (in_array($task->task_type->value, TaskType::autoOnlyValues(), true)) {
            return false;
        }

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
        if (in_array($task->task_type, [TaskType::SURVEY, TaskType::PEMASANGAN], true)) {
            return false;
        }

        return $this->canTransitionTo($user, $task, 'dibatalkan') && ! in_array($task->status->value, ['selesai', 'dibatalkan']);
    }

    /**
     * Membatalkan `Task` eksekusi sebagai EFEK IKUTAN dari pembatalan tiket di
     * /fop-tasks (FopTaskController::update()), bukan lewat tombol Cancel di
     * halaman Task.
     *
     * Kenapa otoritasnya `fop_tasks.cancel` dan BUKAN `task.cancel`:
     * dua permission itu menjaga dua pintu berbeda ke objek berbeda —
     * `task.cancel` buat /tasks (objek `Task`), `fop_tasks.cancel` buat
     * /fop-tasks (objek `FopTask`). Sengaja gak digabung: role `admin` di DB
     * punya `fop_tasks.*` TAPI gak punya `task.cancel`, jadi maksa `task.cancel`
     * di sini bakal bikin admin gak bisa lagi membatalkan tiket — padahal itu
     * kewenangan yang selama ini dia punya. Membatalkan tiket memang SEHARUSNYA
     * ikut membatalkan pekerjaannya; kalau enggak, Task-nya jadi yatim dan tetap
     * kelihatan aktif di /tasks-saya walau tiketnya udah batal.
     *
     * Nilai tambah method ini dibanding cascade langsung tanpa policy: invarian
     * dicek terhadap `Task` YANG BENERAN DIBATALIN (task_type & status-nya
     * sendiri), bukan terhadap `FopTask.category` seperti guard di controller.
     * Kalau dua kolom itu sampai menyimpang, tiket MTN gak bisa dipakai buat
     * diam-diam membatalkan Task SURVEY/PSB — jalur sah SRV/PSB tetap cuma
     * halaman Pelanggan, sama kayak cancel().
     */
    public function cancelViaFopTask(User $user, Task $task): bool
    {
        if (in_array($task->task_type, [TaskType::SURVEY, TaskType::PEMASANGAN], true)) {
            return false;
        }

        if (in_array($task->status->value, ['selesai', 'dibatalkan'], true)) {
            return false;
        }

        return $user->hasPermission('fop_tasks.cancel');
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

        $statusValue = $task->status instanceof TaskStatus ? $task->status->value : $task->status;

        if (! $canTransition && ($user->hasPermission('task.execute') || $task->task_type->value === TaskType::MAINTENANCE->value)) {
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
        if (! $canTransition && $task->status === TaskStatus::PENDING && in_array($task->task_type->value, [TaskType::SURVEY->value, TaskType::PEMASANGAN->value])) {
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

    // ─── Dynamic Workflow Transitions Helper ──────────────────────────

    /**
     * Cek apakah user berhak melakukan transisi status task tertentu berdasarkan database configuration.
     */
    private function canTransitionTo(User $user, Task $task, string $newStatus): bool
    {
        $fromStatus = $task->status instanceof TaskStatus ? $task->status->value : $task->status;

        $rule = WorkflowTransitionPermission::where('from_status', $fromStatus)
            ->where('to_status', $newStatus)
            ->first();

        if (! $rule) {
            return false;
        }

        // Cek apakah role user terhubung dengan rule transisi status ini
        $hasRole = $rule->roles()->where('roles.id', $user->role_id)->exists();
        if (! $hasRole) {
            return false;
        }

        // Cek apakah user memiliki permission string terkait
        return $user->hasPermission($rule->permission_name);
    }
}
