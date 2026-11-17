<?php

namespace App\Console\Commands;

use App\Enums\PushDeliveryStatus;
use App\Models\PushDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Pengganti Dashboard Delivery Push Notification (dihapus 2026-11-17,
 * keputusan user): web push masih tahap uji, delivery rate per merek HP
 * cukup dibaca developer. Logika hitungnya dipindah utuh dari
 * WebPushDashboardController lama.
 *
 * Angkanya APPROXIMATE: `push_deliveries` dicatat per NOTIFIABLE, bukan per
 * device — satu delivery diatribusikan ke SEMUA merek device yang dipunyai
 * penerima SAAT INI (bukan snapshot waktu kirim). Cukup buat sinyal kasar
 * "merek mana yang perlu onboarding ulang battery saver", bukan angka presisi.
 *
 * Dihitung di SQL per notifiable (review ADHOC-297 #11) — jangan balik ke
 * memuat seluruh push_deliveries sebagai model; tabelnya tumbuh satu baris
 * per push.
 */
class WebPushDeliveryStatsCommand extends Command
{
    protected $signature = 'webpush:delivery-stats {--days=30 : Rentang hari ke belakang}';

    protected $description = 'Delivery rate push notification per merek HP (terkirim vs ack vs eskalasi)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('--days minimal 1.');

            return self::FAILURE;
        }

        /** @var class-string $subscriptionModel */
        $subscriptionModel = Config::string('webpush.model');

        $devicesByNotifiable = $subscriptionModel::query()
            ->get(['subscribable_type', 'subscribable_id', 'device_name'])
            ->groupBy(fn ($row) => $row->subscribable_type.'|'.$row->subscribable_id)
            ->map(fn ($rows) => $rows->pluck('device_name')->filter()->unique()->values());

        $stats = [];

        PushDelivery::query()
            ->where('sent_at', '>=', now()->subDays($days))
            ->groupBy('notifiable_type', 'notifiable_id')
            ->selectRaw(
                'notifiable_type, notifiable_id, COUNT(*) as sent_count,'
                .' SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered_count,'
                .' SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as escalated_count',
                [PushDeliveryStatus::Delivered->value, PushDeliveryStatus::Escalated->value]
            )
            ->toBase()
            ->cursor()
            ->each(function (object $row) use (&$stats, $devicesByNotifiable) {
                $brands = $devicesByNotifiable->get($row->notifiable_type.'|'.$row->notifiable_id);
                if (! $brands || $brands->isEmpty()) {
                    $brands = collect(['Tidak diketahui']);
                }

                foreach ($brands as $brand) {
                    $stats[$brand] ??= ['sent' => 0, 'delivered' => 0, 'escalated' => 0];
                    $stats[$brand]['sent'] += (int) $row->sent_count;
                    $stats[$brand]['delivered'] += (int) $row->delivered_count;
                    $stats[$brand]['escalated'] += (int) $row->escalated_count;
                }
            });

        if ($stats === []) {
            $this->info("Tidak ada push terkirim dalam {$days} hari terakhir.");

            return self::SUCCESS;
        }

        $rows = collect($stats)
            ->sortByDesc('sent')
            ->map(fn (array $counts, string $brand) => [
                $brand,
                $counts['sent'],
                $counts['delivered'],
                $counts['escalated'],
                round($counts['delivered'] / $counts['sent'] * 100, 1).'%',
            ])
            ->values();

        $this->info("Periode {$days} hari terakhir (perkiraan — dihitung per penerima, bukan per device).");
        $this->table(['Merek', 'Terkirim', 'Sampai', 'Eskalasi', 'Delivery Rate'], $rows);

        return self::SUCCESS;
    }
}
