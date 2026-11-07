<?php

namespace App\Console\Commands\Scheduled;

use App\Services\TechnicianCustodyExpiryService;
use Illuminate\Console\Command;

/**
 * Auto-expire barang di tangan teknisi (gap #1 laporan user 2026-11-05) —
 * custody qty, SN, dan Roll kabel yang sudah ganti hari sejak diambil
 * AUTO-DIKREDIT ke stok Gudang Cabang asal (keputusan user 2026-11-06, lihat
 * docblock `TechnicianCustodyExpiryService`). Dijalankan tengah malam, pola
 * sama `tasks:auto-pending-overdue`.
 */
class ExpireOverdueTechnicianCustody extends Command
{
    protected $signature = 'inventory:expire-overdue-custody';

    protected $description = 'Auto-return custody qty, SN, dan roll teknisi yang sudah ganti hari sejak diambil, belum dikembalikan/dipakai';

    public function handle(TechnicianCustodyExpiryService $service): int
    {
        $result = $service->expireOverdue();

        if ($result['custody'] === 0 && $result['serial'] === 0 && $result['roll'] === 0) {
            $this->info('Tidak ada custody yang perlu di-expire.');

            return self::SUCCESS;
        }

        $this->info("Berhasil auto-return {$result['custody']} custody qty, {$result['serial']} SN, dan {$result['roll']} roll.");

        return self::SUCCESS;
    }
}
