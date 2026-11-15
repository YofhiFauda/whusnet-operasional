<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TestWebPushNotification;
use Illuminate\Console\Command;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;

/**
 * Alat uji manual Web Push (ADHOC-288) — kirim notifikasi tes ke satu atau
 * lebih user yang sudah subscribe, buat verifikasi end-to-end (server →
 * push service → device) sebelum event bisnis nyata disambungkan.
 */
class WebPushTestCommand extends Command
{
    protected $signature = 'webpush:test {users* : ID atau email user yang akan dikirimi notifikasi tes}';

    protected $description = 'Kirim notifikasi Web Push tes ke satu/lebih user (untuk verifikasi manual, bukan event bisnis nyata)';

    public function handle(): int
    {
        // ReportHandler package cuma dispatch event, gak ada listener bawaan
        // yang nge-log — tanpa ini gagal kirim (endpoint expired, VAPID
        // salah, dll) SENYAP, keliatannya "sukses" padahal push service
        // nolak. Didaftarin cuma buat command tes ini, bukan permanen.
        $this->laravel['events']->listen(NotificationSent::class, function (NotificationSent $event) {
            $this->line("  <fg=green>✓</> push service terima (endpoint ...{$this->tail($event->subscription->endpoint)})");
        });

        $this->laravel['events']->listen(NotificationFailed::class, function (NotificationFailed $event) {
            $report = $event->report;
            $this->error("  ✗ GAGAL (endpoint ...{$this->tail($event->subscription->endpoint)}): ".$report->getReason());
        });

        foreach ($this->argument('users') as $identifier) {
            $user = is_numeric($identifier)
                ? User::find($identifier)
                : User::where('email', $identifier)->first();

            if (! $user) {
                $this->error("User '{$identifier}' tidak ditemukan.");

                continue;
            }

            $subscriptionCount = $user->pushSubscriptions()->count();

            if ($subscriptionCount === 0) {
                $this->error("User {$user->name} ({$user->email}) belum punya push subscription aktif.");

                continue;
            }

            $this->info("Mengirim ke {$user->name} ({$user->email}) — {$subscriptionCount} subscription:");
            $user->notifyNow(new TestWebPushNotification);
        }

        return self::SUCCESS;
    }

    private function tail(string $endpoint): string
    {
        return substr($endpoint, -24);
    }
}
