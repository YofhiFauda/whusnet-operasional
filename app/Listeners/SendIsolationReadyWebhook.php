<?php

namespace App\Listeners;

use App\Events\IsolationCaseReadyForExecution;
use App\Jobs\SendWebhookOutboxJob;
use App\Models\WebhookOutbox;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kabar `isolation.ready_for_execution` ke Web A
 * (docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md). Sengaja
 * BUKAN `ShouldQueue` (pola sama `SendInvoiceUpdatedWebhook`) — jalan sinkron
 * di dalam transaksi `setAdminDecision()`, cuma tulis baris `webhook_outbox`.
 * HTTP baru terjadi di `SendWebhookOutboxJob`, dikirim `afterCommit()`.
 */
class SendIsolationReadyWebhook
{
    public function handle(IsolationCaseReadyForExecution $event): void
    {
        $case = $event->case;
        $customer = $case->customer;
        $eventName = 'isolation.ready_for_execution';

        $eventId = (string) Str::uuid();
        $sequence = WebhookOutbox::nextActivationNumber($case->customer_id, $eventName);
        $idempotencyKey = "isolation:{$case->id}:ready:{$sequence}";

        $payload = [
            'event_id' => $eventId,
            'event' => $eventName,
            'occurred_at' => now()->toIso8601String(),
            'case_number' => $case->case_number,
            'cid' => $customer->cid,
            'customer_name' => $customer->full_name,
            'package_name' => $case->service?->internetPackage?->name,
            'outstanding_amount' => Money::decimalString($case->current_outstanding_amount),
        ];

        $row = WebhookOutbox::create([
            'destination' => 'web_a',
            'event' => $eventName,
            'event_id' => $eventId,
            'idempotency_key' => $idempotencyKey,
            'customer_id' => $case->customer_id,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        $this->dispatchAfterCommitSafely($row->id);
    }

    /**
     * Sama alasan `SendInvoiceUpdatedWebhook::dispatchAfterCommitSafely()` —
     * try/catch eksplisit di sini, bukan cuma diserahkan ke `->afterCommit()`,
     * supaya kegagalan dispatch job (mis. queue sync yang melempar inline)
     * tidak numpang rollback transaksi keputusan Admin yang memicunya.
     */
    private function dispatchAfterCommitSafely(int $outboxId): void
    {
        DB::afterCommit(function () use ($outboxId) {
            try {
                SendWebhookOutboxJob::dispatch($outboxId);
            } catch (Throwable $e) {
                Log::error('Gagal dispatch SendWebhookOutboxJob (web_a)', [
                    'outbox_id' => $outboxId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
