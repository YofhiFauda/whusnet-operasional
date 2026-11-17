<?php

namespace App\Console\Commands;

use App\Models\QrScanLog;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Pengganti halaman Riwayat Scan QR (dihapus 2026-11-17, keputusan user):
 * absen QR masih tahap uji, angka radius/GPS-nya belum pasti akurat, jadi
 * pembacanya developer dulu — bukan owner/atasan/FOP lewat UI.
 *
 * Sengaja TANPA POP scope: artisan cuma bisa dijalankan orang yang punya
 * akses server, yang memang sudah melihat seluruh database. Kalau nanti
 * dibangun ulang sebagai halaman, POP scope WAJIB kembali (CLAUDE.md RBAC
 * larangan #3) — lihat versi lama QrScanLogController di git history.
 *
 * Scan gagal (out_of_radius, bad_signature, dst) SENGAJA ikut tampil — itu
 * sinyal "orang main di lapangan", bukan sampah.
 */
class QrScanLogsCommand extends Command
{
    protected $signature = 'qr:scan-logs
        {--days=1 : Rentang hari ke belakang, 1 = hari ini saja}
        {--user= : Filter teknisi, ID atau email}
        {--result= : Filter hasil, mis. out_of_radius, success, tanpa_koordinat}
        {--purpose= : Filter tujuan scan, mis. attendance}
        {--limit=50 : Maksimal baris ditampilkan}';

    protected $description = 'Tampilkan riwayat scan QR pelanggan (termasuk yang gagal) + ringkasan per hasil';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('--days minimal 1.');

            return self::FAILURE;
        }

        $userId = null;
        if ($this->option('user')) {
            $needle = (string) $this->option('user');
            $userId = User::query()
                ->when(ctype_digit($needle), fn ($q) => $q->whereKey((int) $needle), fn ($q) => $q->where('email', $needle))
                ->value('id');

            if (! $userId) {
                $this->error("User tidak ditemukan: {$needle}");

                return self::FAILURE;
            }
        }

        $query = QrScanLog::query()
            ->where('scanned_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($this->option('result'), fn ($q, $result) => $q->where('result', $result))
            ->when($this->option('purpose'), fn ($q, $purpose) => $q->where('purpose', $purpose));

        $summary = (clone $query)->toBase()
            ->selectRaw('result, COUNT(*) as total')
            ->groupBy('result')
            ->orderByDesc('total')
            ->get();

        if ($summary->isEmpty()) {
            $this->info('Tidak ada scan QR pada periode ini.');

            return self::SUCCESS;
        }

        $logs = $query->with(['user:id,name', 'customer:id,full_name', 'task:id,task_number'])
            ->latest('scanned_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $this->table(
            ['Waktu', 'Teknisi', 'Pelanggan', 'Tujuan', 'Hasil', 'Alasan', 'Jarak (m)', 'Task'],
            $logs->map(fn (QrScanLog $log) => [
                $log->scanned_at?->format('d-m H:i:s'),
                $log->user?->name ?? '-',
                $log->customer?->full_name ?? '-',
                $log->purpose,
                $log->result,
                $log->reason ?? '-',
                $log->distance_meters ?? '-',
                $log->task?->task_number ?? '-',
            ])
        );

        $this->newLine();
        $this->table(['Hasil', 'Jumlah'], $summary->map(fn ($row) => [$row->result, $row->total]));

        return self::SUCCESS;
    }
}
