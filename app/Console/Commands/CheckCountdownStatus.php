<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

class CheckCountdownStatus extends Command
{
    protected $signature = 'check:countdown {--minutes=60 : Jumlah menit SLA}';
    protected $description = 'Cek durasi pelanggan di status survey/pemasangan yang melebihi batas waktu (SLA) dan kirim notifikasi Telegram';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\TelegramBotService $telegram)
    {
        $minutes = (int) $this->option('minutes');
        $limitTime = now()->subMinutes($minutes);

        $customers = \App\Models\Customer::whereIn('status', ['survey_in_progress', 'installation_in_progress'])
            ->where('updated_at', '<=', $limitTime)
            ->get();

        if ($customers->isEmpty()) {
            $this->info("Tidak ada proses yang melebihi SLA {$minutes} menit.");
            return 0;
        }

        foreach ($customers as $customer) {
            $cacheKey = "sla_notified_customer_{$customer->id}_{$customer->status}";

            if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                $processName = $customer->status === 'survey_in_progress' ? 'Survey Lapangan' : 'Pemasangan';
                $duration = $customer->updated_at->diffForHumans(now(), true); // e.g., '1 hour'

                $message = "⚠️ <b>Peringatan SLA Terlampaui</b>\n";
                $message .= "Pelanggan: {$customer->full_name}\n";
                $message .= "Proses: {$processName}\n";
                $message .= "Durasi Berjalan: {$duration} (Lebih dari {$minutes} menit)\n";
                $message .= "Mulai Proses: {$customer->updated_at->format('d/m/Y H:i:s')}\n";
                $message .= "Silakan segera lapor penyelesaian proses ini.";

                $telegram->sendMessage($message);
                $this->info("Notifikasi SLA dikirim untuk Pelanggan ID: {$customer->id} ({$processName})");
                
                // Set cache for 24 hours so it won't spam every 5 minutes
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addHours(24));
            }
        }

        return 0;
    }
}
