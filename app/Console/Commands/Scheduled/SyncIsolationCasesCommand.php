<?php

namespace App\Console\Commands\Scheduled;

use App\Enums\IsolationTriggerType;
use App\Models\CustomerService;
use App\Models\Invoice;
use App\Services\IsolationCaseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('isolir:sync-cases')]
#[Description('Deteksi layanan pelanggan aktif yang lewat jatuh tempo (tgl 10) dan belum bayar, belum punya kasus isolir aktif, lalu buat kasus "Menunggu Isolir" (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §21.1 #1).')]
class SyncIsolationCasesCommand extends Command
{
    /**
     * Dijadwalkan harian (routes/console.php). Idempoten lewat
     * `IsolationCaseService::createOrSyncCase()` — layanan yang sudah
     * punya kasus aktif cuma disinkronkan invoice-nya, tidak dibuat
     * duplikat (BR-03).
     *
     * Pakai `scopeJatuhTempoUntukIsolir()` (due_date < hari ini), BUKAN
     * `scopePiutang()` (pembukuan, nunggu pergantian bulan) — konfirmasi
     * user 2026-11-19: tagihan periode BERJALAN yang sudah lewat tgl 10
     * tetap harus masuk antrean isolir, tidak menunggu bulan berganti.
     */
    public function handle(IsolationCaseService $service): int
    {
        $serviceIds = Invoice::query()
            ->jatuhTempoUntukIsolir()
            ->whereNotNull('customer_service_id')
            ->distinct()
            ->pluck('customer_service_id');

        $created = 0;

        foreach ($serviceIds as $serviceId) {
            /** @var CustomerService|null $customerService */
            $customerService = CustomerService::with('customer')->find($serviceId);

            if (! $customerService || ! $customerService->customer || $customerService->customer->status !== 'active') {
                continue;
            }

            $existing = $service->findActiveCaseForService($customerService);
            $service->createOrSyncCase($customerService, IsolationTriggerType::OVERDUE_BILLING);

            if (! $existing) {
                $created++;
            }
        }

        $this->info("{$created} kasus isolir baru dibuat dari ".$serviceIds->count().' layanan yang lewat jatuh tempo.');

        return self::SUCCESS;
    }
}
