<?php

namespace App\Listeners;

use App\Events\InvoiceStatusUpdated;
use App\Services\IsolationCaseService;

/**
 * Evaluasi ulang kasus isolir setiap kali saldo invoice berubah karena
 * pembayaran (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §10.2).
 * Auto-discovery Laravel 11+ (tidak ada EventServiceProvider manual di
 * repo, sama seperti `SendInvoiceUpdatedWebhook`) — cukup `handle()`
 * di-type-hint `InvoiceStatusUpdated`.
 *
 * Titik picu SATU-SATUNYA: `Invoice::recalculateFromPayments()` sudah
 * men-dispatch event ini dari SEMUA jalur pembayaran (satuan, bulk, batch
 * kolektor, reject) — listener ini TIDAK mengubah `Invoice.php`.
 */
class SyncIsolationCaseOnPaymentVerified
{
    public function __construct(private readonly IsolationCaseService $service) {}

    public function handle(InvoiceStatusUpdated $event): void
    {
        $this->service->handlePaymentVerified($event->invoice);
    }
}
