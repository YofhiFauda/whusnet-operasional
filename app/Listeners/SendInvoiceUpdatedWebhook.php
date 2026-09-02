<?php

namespace App\Listeners;

use App\Events\InvoiceStatusUpdated;
use App\Jobs\SendWebhookOutboxJob;
use App\Models\WebhookOutbox;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kabar `invoice.updated` ke portal pelanggan (docs/api/api-portal-pelanggan/,
 * Fase 3, business-logic.md §3 Bagian B). Auto-discovery Laravel 11+ (tidak
 * ada EventServiceProvider manual di repo) — cukup `handle()` di-type-hint
 * `InvoiceStatusUpdated`, tidak perlu registrasi eksplisit di mana pun.
 *
 * Titik picu SATU-SATUNYA: `Invoice::recalculateFromPayments()` sudah
 * men-dispatch `InvoiceStatusUpdated` di baris terakhirnya — listener ini
 * TIDAK mengubah `Invoice.php` sama sekali. Event itu SUDAH dipanggil dari
 * semua jalur pembayaran (satuan, bulk, batch kolektor, reject) lewat satu
 * method yang sama, dan SUDAH early-return untuk invoice BATAL sebelum
 * dispatch — jadi listener ini otomatis tidak pernah terpanggil untuk
 * invoice batal, tanpa guard tambahan.
 *
 * Sengaja BUKAN ShouldQueue (pola sama SendInstallationActivatedWebhooks) —
 * jalan sinkron di dalam transaksi pemicu, cuma tulis baris `webhook_outbox`.
 * HTTP baru terjadi di SendWebhookOutboxJob, dikirim afterCommit().
 */
class SendInvoiceUpdatedWebhook
{
    public function handle(InvoiceStatusUpdated $event): void
    {
        $invoice = $event->invoice;
        $invoice->loadMissing('customer.portalAccount');
        $customer = $invoice->customer;

        // Tanpa akun portal (belum diklaim/diprovision) tidak ada login_id
        // untuk dikirim — payload tanpa login_id tidak berguna bagi portal.
        // Bukan error, cuma tidak ada yang perlu dikabari.
        if (! $customer || ! $customer->portalAccount) {
            return;
        }

        $eventId = (string) Str::uuid();

        // Reuse WebhookOutbox::nextActivationNumber() — generik (split by
        // ':', ambil segmen terakhir yang numerik), bukan cuma buat skema
        // "installation:...:activation:N" API 1. Guard superseded di
        // SendWebhookOutboxJob otomatis berfungsi tanpa perubahan model.
        $sequence = WebhookOutbox::nextActivationNumber($customer->id, 'invoice.updated');
        $idempotencyKey = "invoice:{$customer->id}:updated:{$sequence}";

        $payload = [
            'event_id' => $eventId,
            'event' => 'invoice.updated',
            'occurred_at' => now()->toIso8601String(),
            'customer' => [
                'login_id' => $customer->portalAccount->login_id,
            ],
            'invoice' => [
                'invoice_number' => $invoice->invoice_number,
                'invoice_status' => $invoice->invoice_status->value,
                'total_amount' => Money::decimalString($invoice->total_amount),
                'paid_amount' => Money::decimalString($invoice->paid_amount),
                'remaining_amount' => Money::decimalString($invoice->remaining_amount),
            ],
        ];

        $row = WebhookOutbox::create([
            'destination' => 'customer_portal',
            'event' => 'invoice.updated',
            'event_id' => $eventId,
            'idempotency_key' => $idempotencyKey,
            'customer_id' => $customer->id,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        $this->dispatchAfterCommitSafely($row->id);
    }

    /**
     * Sama persis alasan SendInstallationActivatedWebhooks::dispatchAfterCommitSafely()
     * — try/catch eksplisit di sini, bukan cuma diserahkan ke ->afterCommit(),
     * supaya kegagalan dispatch job (mis. queue sync yang melempar inline)
     * tidak numpang rollback transaksi pembayaran yang memicunya.
     */
    private function dispatchAfterCommitSafely(int $outboxId): void
    {
        DB::afterCommit(function () use ($outboxId) {
            try {
                SendWebhookOutboxJob::dispatch($outboxId);
            } catch (Throwable $e) {
                Log::error('Gagal dispatch SendWebhookOutboxJob (customer_portal)', [
                    'outbox_id' => $outboxId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
