<?php

namespace App\Http\Controllers;

use App\Enums\PushDeliveryStatus;
use App\Models\PushDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;

/**
 * Dashboard delivery rate push notification per merek HP (ADHOC-288
 * Tahap 8, ditunda dari Tahap 6 item 4). `push_deliveries` dicatat per
 * NOTIFIABLE (lihat komentar migration-nya), bukan per device — satu
 * notifikasi yang terkirim ke orang dengan 2 device beda merek TIDAK BISA
 * dipastikan device mana yang ack. Dashboard ini sengaja APPROXIMATE:
 * delivery dihitung ke SEMUA merek device yang dipunyai notifiable itu
 * SAAT INI (bukan snapshot waktu kirim) — cukup buat sinyal kasar "merek
 * mana yang perlu onboarding ulang", BUKAN angka presisi per device.
 */
class WebPushDashboardController extends Controller
{
    /** Pilihan periode (hari) di filter dashboard. */
    public const PERIODS = [7, 30, 90];

    public function index(Request $request): View
    {
        $days = (int) $request->query('days', 30);
        if (! in_array($days, self::PERIODS, true)) {
            $days = 30;
        }

        /** @var class-string $subscriptionModel */
        $subscriptionModel = Config::string('webpush.model');

        $devicesByNotifiable = $subscriptionModel::query()
            ->get(['subscribable_type', 'subscribable_id', 'device_name'])
            ->groupBy(fn ($row) => $row->subscribable_type.'|'.$row->subscribable_id)
            ->map(fn ($rows) => $rows->pluck('device_name')->filter()->unique()->values());

        $stats = [];

        // Dihitung di SQL per notifiable dalam periode terpilih (review
        // ADHOC-297 #11). Dulu SELURUH baris push_deliveries sepanjang masa
        // dimuat sebagai model lalu dihitung di PHP — tabel bertambah satu
        // baris per push, jadi halaman ini makin lambat sampai kena batas
        // memori/timeout. Sekarang jumlah baris yang ditarik = jumlah
        // penerima unik dalam periode, bukan jumlah push.
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

        $rows = collect($stats)
            ->map(function (array $counts, string $brand) {
                $rate = $counts['sent'] > 0 ? round($counts['delivered'] / $counts['sent'] * 100, 1) : 0.0;

                return [
                    'brand' => $brand,
                    'sent' => $counts['sent'],
                    'delivered' => $counts['delivered'],
                    'escalated' => $counts['escalated'],
                    'delivery_rate' => $rate,
                ];
            })
            ->sortByDesc('sent')
            ->values();

        return view('webpush.dashboard', [
            'rows' => $rows,
            'days' => $days,
            'periods' => self::PERIODS,
        ]);
    }
}
