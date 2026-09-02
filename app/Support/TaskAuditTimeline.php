<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Menyaring `audit_logs` sebuah Task jadi timeline yang layak dibaca manusia di
 * halaman Detail Task ("Riwayat Perubahan Status").
 *
 * Kenapa perlu disaring: Task punya DUA lapis pencatat, dan itu memang disengaja.
 *
 *  1. Trait `RecordsAuditLogs` di model — menempel di event Eloquent, jadi
 *     menangkap perubahan dari SEMUA jalur (service, controller, artisan, tinker)
 *     lengkap dengan kolom apa yang berubah. Aksinya generik: `create`/`update`.
 *  2. Panggilan manual `AuditLog::log()` di service/controller — memberi NAMA pada
 *     peristiwa bisnis yang tidak bisa disimpulkan dari perubahan kolom:
 *     `completed`, `cancelled`, `reassigned`, `pending`, `reschedule`, `approved`,
 *     `rejected`.
 *
 * Keduanya bernilai untuk audit, tapi kalau dirender apa adanya satu klik user
 * tampil sebagai dua baris. Sampai 2026-08-13 kondisinya lebih buruk lagi: service
 * juga menulis `created`/`updated` yang isinya sama persis dengan tulisan trait —
 * duplikat murni, sudah dicabut.
 *
 * Yang dilakukan di sini murni PENYAJIAN. Tidak ada baris audit yang dihapus dari
 * database: `audit_logs` tetap utuh sebagai jejak forensik, dan halaman Audit Log
 * tetap menampilkan semuanya.
 */
class TaskAuditTimeline
{
    /**
     * Aksi generik dari trait. Selain yang ada di daftar ini dianggap peristiwa
     * bisnis bernama dan selalu ditampilkan.
     *
     * @var array<int, string>
     */
    private const GENERIC_ACTIONS = ['create', 'update', 'delete'];

    /**
     * Kolom yang perubahannya berarti sesuatu bagi pembaca timeline. Perubahan di
     * luar ini (mis. `title` yang diberi prefix `[Team 1]` oleh
     * FopTaskTeamService::rebuildTeamsForDate(), atau `updated_by`) adalah derau
     * mesin — tetap tersimpan di audit_logs, tapi tidak ditampilkan.
     *
     * @var array<int, string>
     */
    private const MEANINGFUL_FIELDS = [
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'completed_by',
        'pending_reason',
        'report_deferred',
        'cancel_reason',
        'reject_reason',
        'fop_review_status',
    ];

    /**
     * Jendela waktu untuk menganggap baris trait dan baris bisnis berasal dari
     * SATU aksi user. Keduanya ditulis dalam transaksi yang sama, jadi selisihnya
     * praktis nol; 5 detik memberi ruang untuk request yang lambat.
     */
    private const SAME_ACTION_WINDOW_SECONDS = 5;

    /**
     * @return Collection<int, AuditLog>
     */
    public static function for(Task $task): Collection
    {
        $logs = $task->auditLogs instanceof Collection
            ? $task->auditLogs
            : collect($task->auditLogs);

        $logs = $logs->sortByDesc('created_at')->values();

        $business = $logs->reject(fn (AuditLog $log) => in_array($log->action, self::GENERIC_ACTIONS, true));

        return $logs
            ->filter(function (AuditLog $log) use ($business) {
                if ($log->action !== 'update') {
                    // `create` selalu tampil (titik lahir task), begitu juga semua
                    // peristiwa bisnis bernama.
                    return true;
                }

                if (! self::touchesMeaningfulField($log)) {
                    return false;
                }

                // Perubahan status yang SUDAH diceritakan baris bisnis di detik yang
                // sama — tampilkan yang bernama saja, jangan dua-duanya.
                return ! $business->contains(
                    fn (AuditLog $other) => abs($other->created_at->diffInSeconds($log->created_at)) <= self::SAME_ACTION_WINDOW_SECONDS
                );
            })
            ->values();
    }

    /**
     * Label bahasa Indonesia untuk timeline. Tanpa ini blade merender string
     * mentah dari kolom `action` — campur aduk bahasa Inggris teknis ("update",
     * "completed", "report_deferred") di halaman yang dibaca teknisi lapangan.
     *
     * `update` yang lolos filter selalu berarti perubahan status/jadwal yang
     * tidak punya baris bernama, jadi labelnya dibuat spesifik lewat kolom yang
     * berubah, bukan kata "Update" yang tidak menjelaskan apa pun.
     */
    public static function label(AuditLog $log): string
    {
        $named = [
            'create' => 'Task Dibuat',
            'started' => 'Mulai Dikerjakan',
            'completed' => 'Task Selesai',
            'cancelled' => 'Task Dibatalkan',
            'pending' => 'Ditunda (Pending)',
            'report_deferred' => 'Lapor Nanti',
            'reschedule' => 'Dijadwalkan Ulang',
            'reassigned' => 'Teknisi Diganti',
            'approved' => 'Disetujui FOP',
            'rejected' => 'Ditolak FOP',
            'delete' => 'Task Dihapus',
        ];

        if (isset($named[$log->action])) {
            return $named[$log->action];
        }

        if ($log->action === 'update') {
            $changed = array_keys((array) ($log->new_values ?? []));

            return match (true) {
                in_array('status', $changed, true) => 'Status Diubah',
                in_array('scheduled_at', $changed, true) => 'Jadwal Diubah',
                in_array('fop_review_status', $changed, true) => 'Status Review FOP Diubah',
                default => 'Perubahan Data',
            };
        }

        return ucfirst(str_replace('_', ' ', $log->action));
    }

    private static function touchesMeaningfulField(AuditLog $log): bool
    {
        $changed = array_keys((array) ($log->new_values ?? []));

        return array_intersect($changed, self::MEANINGFUL_FIELDS) !== [];
    }
}
