<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CollectorBalanceService;
use Illuminate\Console\Command;

/**
 * Peringatan "belum setor" ke teknisi yang masih memegang uang pembayaran
 * melewati tutup hari (ADHOC-122, rancangan-pembayaran-teknisi §7.1).
 *
 * Hanya NOTIFIKASI — tidak mengubah saldo, tidak memblokir apa pun.
 * Dijadwalkan di routes/console.php pada jam tutup hari.
 */
class NotifyUnsettledTechnicianCommand extends Command
{
    protected $signature = 'technicians:notify-unsettled';

    protected $description = 'Kirim peringatan belum setor ke teknisi yang saldo pembayarannya belum disetor sampai tutup hari';

    public function handle(CollectorBalanceService $balance): int
    {
        $notified = 0;

        foreach ($balance->technicianSettlementStatus() as $row) {
            if (! $row['overdue']) {
                continue;
            }

            /** @var User $technician */
            $technician = $row['user'];

            $technician->notify(new AppNotification(
                title: 'Belum setor saldo pembayaran',
                message: 'Saldo pembayaran Rp '.number_format($row['balance'], 0, ',', '.').' belum disetor. Silakan setorkan sebelum melanjutkan pekerjaan besok.',
                actionUrl: route('technician-payments.index'),
                type: NotificationType::INFO
            ));

            $notified++;
        }

        $this->info("Peringatan terkirim ke {$notified} teknisi.");

        return self::SUCCESS;
    }
}
