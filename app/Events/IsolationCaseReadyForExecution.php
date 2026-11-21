<?php

namespace App\Events;

use App\Models\IsolationCase;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Kasus benar-benar pindah ke `READY_FOR_ISOLATION` (Admin "Eksekusi Isolir",
 * docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md). Event
 * domain murni — BUKAN broadcast realtime (beda dari `IsolationCaseQueueChanged`
 * yang payload-nya cuma `pop_id` buat badge sidebar). Didengarkan
 * `SendIsolationReadyWebhook` untuk kirim webhook ke Web A.
 */
class IsolationCaseReadyForExecution
{
    use Dispatchable;

    public function __construct(public IsolationCase $case) {}
}
