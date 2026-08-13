<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ringkasan beban kerja teknisi (status, jumlah task, lokasi terakhir) untuk
 * sidebar dashboard FOP dan antrean verifikasi.
 *
 * Sebelumnya logika ini ada DUA salinan utuh — `FopDashboardController::getTeknisiList()`
 * dan `CustomerVerificationController::getTeknisiList()` — dan keduanya melakukan
 * dua query di dalam loop per teknisi, masing-masing memakai `whereHas` (subquery
 * dependen). Ditambah `clean_address` yang menyentuh tiga relasi, biayanya 5 query
 * per teknisi; 20 teknisi = 100 query hanya untuk mengisi satu sidebar, dan
 * dashboard FOP auto-refresh sehingga biaya itu berulang tanpa interaksi user.
 *
 * Di sini jumlah query TETAP (tidak tumbuh mengikuti jumlah teknisi), apa pun
 * jumlah teknisinya.
 */
class TeknisiWorkloadService
{
    /**
     * @param  array<int>  $allowedPopIds
     * @return Collection<int, array{id:int, name:string, initials:string, status:string, task_count:int, location:string}>
     */
    public function summarize(bool $hasAllPopAccess, array $allowedPopIds, ?Carbon $today = null): Collection
    {
        $today ??= Carbon::today();

        $teknisi = User::query()
            ->whereHas('role', fn ($q) => $q->where('code', 'teknisi'))
            ->when(
                ! $hasAllPopAccess,
                fn ($q) => $q->whereHas('roleScopes.targets', fn ($t) => $t->whereIn('pop_id', $allowedPopIds))
            )
            ->orderBy('name')
            ->get(['id', 'name']);

        $teknisiIds = $teknisi->pluck('id')->all();

        if ($teknisiIds === []) {
            return collect();
        }

        $activeByUser = $this->activeTaskByUser($teknisiIds);
        $countByUser = $this->scheduledCountByUser($teknisiIds, $today);

        return $teknisi->map(function (User $t) use ($activeByUser, $countByUser) {
            $activeTask = $activeByUser->get($t->id);
            $counts = $countByUser[$t->id] ?? ['total' => 0, 'overdue' => 0];
            $taskCount = (int) $counts['total'];
            $overdueCount = (int) $counts['overdue'];

            return [
                'id' => $t->id,
                'name' => $t->name,
                'initials' => $this->initials($t->name),
                'status' => match (true) {
                    $activeTask !== null => 'aktif',
                    $taskCount > 0 => 'terjadwal',
                    default => 'standby',
                },
                'task_count' => $taskCount,
                // Pecahan tunggakan dari `task_count`. Angka gabungan saja
                // menyamarkan beda antara "3 task hari ini" (normal) dan "3 task
                // yang seharusnya kelar 3 hari lalu" (perlu ditangani) — padahal
                // keduanya menentukan boleh-tidaknya teknisi ini dapat task baru.
                'overdue_count' => $overdueCount,
                'location' => $activeTask
                    ? ($activeTask->customer?->clean_address ?? 'Tidak Diketahui')
                    : '-',
            ];
        });
    }

    /**
     * Task `in_progress` terbaru per teknisi — satu query untuk semua teknisi.
     *
     * Relasi alamat pelanggan sengaja ikut di-eager-load: `clean_address` membaca
     * village/district/city, jadi tanpa ini tiga query tambahan per teknisi yang
     * sedang bertugas.
     *
     * @param  array<int>  $teknisiIds
     * @return Collection<int, Task> keyed by user_id
     */
    private function activeTaskByUser(array $teknisiIds): Collection
    {
        return Task::query()
            ->join('task_teams', 'task_teams.task_id', '=', 'tasks.id')
            ->whereIn('task_teams.user_id', $teknisiIds)
            ->where('tasks.status', TaskStatus::IN_PROGRESS->value)
            ->with(['customer.village', 'customer.district', 'customer.city'])
            ->orderByDesc('tasks.started_at')
            ->select('tasks.*', 'task_teams.user_id as team_user_id')
            ->get()
            // Sudah diurut started_at DESC, jadi baris pertama per teknisi = yang
            // terbaru — sama dengan `latest('started_at')->first()` versi lama.
            ->unique('team_user_id')
            ->keyBy('team_user_id');
    }

    /**
     * Jumlah task terjadwal hari ini + task terjadwal/in_progress yang sudah
     * lewat tanggalnya (overdue), per teknisi — satu query agregat.
     *
     * Perbandingan tanggal ditulis sebagai rentang (`>=` … `<=`), bukan
     * `whereDate()`. `scheduled_at` bertipe datetime, dan `whereDate()`
     * membungkusnya jadi `DATE(scheduled_at)` sehingga index apa pun di kolom
     * itu tidak akan pernah terpakai.
     *
     * Hasilnya dipecah: `total` (hari ini + tunggakan) dan `overdue` (tunggakan
     * saja), supaya tabel bisa menunjukkan komposisinya, bukan cuma angka bulat.
     *
     * @param  array<int>  $teknisiIds
     * @return Collection<int, array{total:int, overdue:int}> keyed by user_id
     */
    private function scheduledCountByUser(array $teknisiIds, Carbon $today): Collection
    {
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay = $today->copy()->endOfDay();

        return Task::query()
            ->join('task_teams', 'task_teams.task_id', '=', 'tasks.id')
            ->whereIn('task_teams.user_id', $teknisiIds)
            ->where(function ($q) use ($startOfDay, $endOfDay) {
                $q->where(function ($hariIni) use ($startOfDay, $endOfDay) {
                    $hariIni->where('tasks.status', TaskStatus::TERJADWAL->value)
                        ->whereBetween('tasks.scheduled_at', [$startOfDay, $endOfDay]);
                })->orWhere(function ($overdue) use ($startOfDay) {
                    $overdue->whereIn('tasks.status', [TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value])
                        ->where('tasks.scheduled_at', '<', $startOfDay);
                });
            })
            ->groupBy('task_teams.user_id')
            ->selectRaw(
                'task_teams.user_id as user_id, COUNT(*) as total, SUM(CASE WHEN tasks.scheduled_at < ? THEN 1 ELSE 0 END) as overdue',
                [$startOfDay]
            )
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->user_id => [
                    'total' => (int) $row->total,
                    'overdue' => (int) $row->overdue,
                ],
            ]);
    }

    private function initials(string $name): string
    {
        $words = explode(' ', trim($name));

        return strtoupper(
            implode('', array_map(fn ($w) => substr($w, 0, 1), array_slice($words, 0, 2)))
        );
    }
}
