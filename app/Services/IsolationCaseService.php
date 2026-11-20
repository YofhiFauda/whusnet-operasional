<?php

namespace App\Services;

use App\Enums\IsolationActionStatus;
use App\Enums\IsolationActionType;
use App\Enums\IsolationAdminDecision;
use App\Enums\IsolationCaseStatus;
use App\Enums\IsolationEventType;
use App\Enums\IsolationTriggerType;
use App\Events\IsolationCaseQueueChanged;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Invoice;
use App\Models\IsolationAction;
use App\Models\IsolationCase;
use App\Models\IsolationCaseInvoice;
use App\Models\IsolationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya sumber kebenaran lifecycle Monitoring Isolir
 * (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §21). Menggantikan
 * `CustomerIsolirQueueService` (ADHOC-306, belum pernah commit) total —
 * BUKAN ekstensi dari service itu.
 *
 * Prinsip yang WAJIB dijaga tiap metode di sini (§4 dokumen di atas):
 * - Status layanan (`Customer`/`CustomerService`) TIDAK pernah diubah dari
 *   sini — itu tetap kerjaan `CustomerController::toggleSuspend()`. Service
 *   ini CUMA mencatat lifecycle kasus & timeline, dipanggil SETELAH status
 *   layanan berubah (lihat pemanggil di `toggleSuspend()`).
 * - Setiap perubahan status kasus menghasilkan `IsolationEvent` baru
 *   (append-only, §4.4) — tidak ada baris yang ditimpa/dihapus.
 * - Pembayaran lunas TIDAK langsung = kasus selesai (BR-08) — cuma
 *   memindahkan kasus ke `restoration_pending`, NOC yang mengonfirmasi lewat
 *   `markRestorationSucceeded()`.
 */
class IsolationCaseService
{
    public function __construct(private readonly NumberSequenceService $numbers) {}

    /**
     * Query dasar seluruh kasus yang kelihatan user (POP-scoped).
     *
     * @return Builder<IsolationCase>
     */
    public function casesQuery(?User $viewer = null): Builder
    {
        return IsolationCase::query()->applyUserScope($viewer);
    }

    /** @return Builder<IsolationCase> */
    public function waitingIsolationQuery(?User $viewer = null): Builder
    {
        return $this->casesQuery($viewer)->whereIn('status', array_map(fn ($s) => $s->value, IsolationCaseStatus::waitingIsolationGroup()));
    }

    /** @return Builder<IsolationCase> */
    public function readyForIsolationQuery(?User $viewer = null): Builder
    {
        return $this->casesQuery($viewer)->whereIn('status', array_map(fn ($s) => $s->value, IsolationCaseStatus::readyForIsolationGroup()));
    }

    /** @return Builder<IsolationCase> */
    public function suspendedQuery(?User $viewer = null): Builder
    {
        return $this->casesQuery($viewer)->where('status', IsolationCaseStatus::SUSPENDED->value);
    }

    /** @return Builder<IsolationCase> */
    public function restorationQuery(?User $viewer = null): Builder
    {
        return $this->casesQuery($viewer)->whereIn('status', array_map(fn ($s) => $s->value, IsolationCaseStatus::restorationGroup()));
    }

    /** @return Builder<IsolationCase> */
    public function historyQuery(?User $viewer = null): Builder
    {
        return $this->casesQuery($viewer)->whereIn('status', array_map(fn ($s) => $s->value, IsolationCaseStatus::historyGroup()));
    }

    /**
     * Kartu ringkasan/KPI (§8.2) — satu query `GROUP BY status` + satu query
     * "selesai hari ini", bukan 7 query count() terpisah.
     *
     * @return array{waiting_isolation: int, ready_for_isolation: int, isolation_in_progress: int, suspended: int,
     *     restoration_pending: int, restoration_in_progress: int, failed: int, completed_today: int}
     */
    public function kpiSummary(?User $viewer = null): array
    {
        $byStatus = $this->casesQuery($viewer)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $completedToday = $this->casesQuery($viewer)
            ->where('status', IsolationCaseStatus::COMPLETED->value)
            ->whereDate('closed_at', now()->toDateString())
            ->count();

        return [
            'waiting_isolation' => (int) ($byStatus[IsolationCaseStatus::WAITING_ISOLATION->value] ?? 0),
            'ready_for_isolation' => (int) ($byStatus[IsolationCaseStatus::READY_FOR_ISOLATION->value] ?? 0),
            'isolation_in_progress' => (int) ($byStatus[IsolationCaseStatus::ISOLATION_IN_PROGRESS->value] ?? 0),
            'suspended' => (int) ($byStatus[IsolationCaseStatus::SUSPENDED->value] ?? 0),
            'restoration_pending' => (int) ($byStatus[IsolationCaseStatus::RESTORATION_PENDING->value] ?? 0),
            'restoration_in_progress' => (int) ($byStatus[IsolationCaseStatus::RESTORATION_IN_PROGRESS->value] ?? 0),
            'failed' => (int) (($byStatus[IsolationCaseStatus::ISOLATION_FAILED->value] ?? 0) + ($byStatus[IsolationCaseStatus::RESTORATION_FAILED->value] ?? 0)),
            'completed_today' => $completedToday,
        ];
    }

    /**
     * Kasus AKTIF (BR-03: maksimal satu) untuk satu layanan, kalau ada.
     */
    public function findActiveCaseForService(CustomerService $service): ?IsolationCase
    {
        return IsolationCase::where('service_id', $service->id)->where('active_slot', 1)->first();
    }

    /**
     * Buat kasus baru untuk layanan ini kalau belum ada kasus aktif, atau
     * sinkronkan invoice piutang terbaru ke kasus aktif yang sudah ada.
     * Dipanggil dari: command harian deteksi tunggakan, "Tandai Manual", dan
     * `markIsolationSucceeded()` (kalau isolir dieksekusi tanpa kasus
     * terdeteksi lebih dulu).
     */
    public function createOrSyncCase(CustomerService $service, IsolationTriggerType $trigger, ?User $actor = null, ?string $note = null): IsolationCase
    {
        return DB::transaction(function () use ($service, $trigger, $actor, $note) {
            $case = $this->findActiveCaseForService($service);

            if ($case) {
                $this->syncInvoices($case);

                return $case;
            }

            $customer = $service->customer;

            $case = new IsolationCase([
                'case_number' => $this->numbers->isolationCaseNumber(),
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'pop_id' => $customer->pop_id,
                'status' => IsolationCaseStatus::WAITING_ISOLATION,
                'trigger_type' => $trigger,
                'detected_at' => now(),
                'active_slot' => 1,
            ]);
            $case->save();

            $this->syncInvoices($case);
            $case->update(['outstanding_amount_at_creation' => $case->current_outstanding_amount]);

            $this->recordEvent($case, IsolationEventType::CASE_CREATED, $actor, $note);

            IsolationCaseQueueChanged::dispatch($case->pop_id);

            return $case;
        });
    }

    /**
     * Tandai manual dari List Pelanggan/Quick Hub — sama dengan
     * `createOrSyncCase()` ber-trigger MANUAL, nama metode sendiri supaya
     * jelas dipakai dari jalur UI yang mana.
     */
    public function markManual(CustomerService $service, User $actor, string $note): IsolationCase
    {
        return $this->createOrSyncCase($service, IsolationTriggerType::MANUAL, $actor, $note);
    }

    /**
     * Keputusan Admin: "Eksekusi Isolir" atau "Toleransi" (follow-up
     * 2026-11-19, diperkuat lagi hari ini) — Admin yang memutuskan, NOC yang
     * mengeksekusi, DUA TAB TERPISAH biar gak ada miss komunikasi "NOC pikir
     * sudah di-acc, padahal belum". "Eksekusi" bukan cuma menandai kolom
     * `admin_decision` — status KASUS beneran pindah dari "Menunggu Isolir"
     * (antrean Admin) ke "Siap Isolir" (antrean NOC,
     * `IsolationCaseStatus::READY_FOR_ISOLATION`), begitu juga sebaliknya:
     * "Toleransi" menarik kasus balik ke "Menunggu Isolir" meski sebelumnya
     * sudah di antrean NOC (Admin berubah pikiran SEBELUM NOC eksekusi).
     *
     * Berlaku selama kasus masih di salah satu dari dua tab itu
     * (`preSuspensionGroup()`); kasus yang sudah lewat fase itu (sudah
     * diisolir/dibatalkan/selesai) tidak lagi relevan untuk diputuskan.
     */
    public function setAdminDecision(IsolationCase $case, IsolationAdminDecision $decision, User $actor, ?string $note, ?string $until = null): IsolationCase
    {
        if (! in_array($case->status, IsolationCaseStatus::preSuspensionGroup(), true)) {
            return $case;
        }

        return DB::transaction(function () use ($case, $decision, $actor, $note, $until) {
            $fromStatus = $case->status;

            $case->admin_decision = $decision;
            $case->admin_decision_note = $note;
            $case->admin_decision_until = $decision === IsolationAdminDecision::TOLERATE ? $until : null;
            $case->admin_decided_by = $actor->id;
            $case->admin_decided_at = now();
            $case->status = $decision === IsolationAdminDecision::EXECUTE
                ? IsolationCaseStatus::READY_FOR_ISOLATION
                : IsolationCaseStatus::WAITING_ISOLATION;
            $case->save();

            $eventType = $decision === IsolationAdminDecision::EXECUTE
                ? IsolationEventType::ADMIN_MARKED_EXECUTE
                : IsolationEventType::ADMIN_MARKED_TOLERATE;
            $this->recordEvent($case, $eventType, $actor, $note, $fromStatus, $case->status);

            IsolationCaseQueueChanged::dispatch($case->pop_id);

            return $case;
        });
    }

    /**
     * Batalkan kasus SEBELUM isolir dieksekusi (status masih di salah satu
     * dari tab "Menunggu Isolir"/"Siap Isolir" — `preSuspensionGroup()`).
     * Kasus yang SUDAH `suspended` tidak dibatalkan di sini — itu harus
     * lewat jalur pemulihan (`markRestorationSucceeded()`), karena isolir
     * itu sendiri sudah benar-benar terjadi di lapangan.
     */
    public function cancelCase(IsolationCase $case, ?User $actor, string $reason): void
    {
        if (! in_array($case->status, IsolationCaseStatus::preSuspensionGroup(), true)) {
            return;
        }

        DB::transaction(function () use ($case, $actor, $reason) {
            $fromStatus = $case->status;
            $case->status = IsolationCaseStatus::CANCELLED;
            $case->cancelled_at = now();
            $case->cancellation_reason = $reason;
            $case->closed_at = now();
            $case->syncActiveSlot();
            $case->save();

            $this->recordEvent($case, IsolationEventType::CASE_CANCELLED, $actor, $reason, $fromStatus, IsolationCaseStatus::CANCELLED);

            // Hanya pembatalan MANUAL (actor terisi) yang butuh broadcast
            // khusus di sini — pembatalan otomatis "paid_before_isolation"
            // (actor null, dari `handlePaymentVerified()`) sudah tercakup
            // broadcast `InvoiceStatusUpdated` yang memicunya.
            if ($actor) {
                IsolationCaseQueueChanged::dispatch($case->pop_id);
            }
        });
    }

    /**
     * Isolir berhasil dieksekusi (dipanggil SETELAH `Customer`/`CustomerService`
     * sudah diubah ke suspended oleh pemanggil). Kalau belum ada kasus
     * terdeteksi untuk layanan ini (mis. isolir langsung tanpa command
     * deteksi jalan dulu), kasus dibuat di sini juga (trigger MANUAL) —
     * supaya SETIAP isolir yang terjadi selalu punya kasus, tidak ada yang
     * luput dari Monitoring Isolir.
     */
    public function markIsolationSucceeded(CustomerService $service, ?User $actor = null, string $actorType = 'system'): IsolationCase
    {
        return DB::transaction(function () use ($service, $actor, $actorType) {
            $case = $this->createOrSyncCase($service, IsolationTriggerType::MANUAL, $actor);

            $fromStatus = $case->status;
            $this->recordEvent($case, IsolationEventType::ISOLATION_STARTED, $actor, null, $fromStatus, IsolationCaseStatus::ISOLATION_IN_PROGRESS, actorType: $actorType);

            $this->recordAction($case, IsolationActionType::SUSPEND, $actor);

            $case->status = IsolationCaseStatus::SUSPENDED;
            $case->isolation_started_at ??= now();
            $case->suspended_at = now();
            $case->syncActiveSlot();
            $case->save();

            $this->recordEvent($case, IsolationEventType::ISOLATION_SUCCEEDED, $actor, null, IsolationCaseStatus::ISOLATION_IN_PROGRESS, IsolationCaseStatus::SUSPENDED, actorType: $actorType);

            return $case;
        });
    }

    /**
     * Pemulihan dikonfirmasi NOC (dipanggil SETELAH `Customer`/`CustomerService`
     * sudah diubah balik ke active). Boleh dipanggil kapan pun kasus masih
     * terbuka (bukan cuma dari `restoration_pending`) — NOC berhak
     * mengaktifkan kembali secara manual meski piutang belum lunas; kalau
     * itu terjadi, tercatat eksplisit di event, tidak diam-diam.
     *
     * Return null kalau layanan ini tidak punya kasus terbuka sama sekali
     * (data lama / status diubah di luar jalur kasus) — tidak ada yang perlu
     * diselesaikan, tapi aksi aktifkan-kembali di `toggleSuspend()` tidak
     * boleh gagal karena ini.
     */
    public function markRestorationSucceeded(CustomerService $service, User $actor): ?IsolationCase
    {
        $case = $this->findActiveCaseForService($service);

        if (! $case) {
            return null;
        }

        return DB::transaction(function () use ($case, $actor) {
            $this->syncInvoices($case);

            $fromStatus = $case->status;
            $this->recordEvent($case, IsolationEventType::RESTORATION_STARTED, $actor, null, $fromStatus, IsolationCaseStatus::RESTORATION_IN_PROGRESS);

            $this->recordAction($case, IsolationActionType::RESTORE, $actor);

            $outstanding = (float) $case->current_outstanding_amount;
            $description = $outstanding > 0
                ? sprintf('Diaktifkan kembali manual meski piutang kasus ini belum lunas (sisa Rp %s).', number_format($outstanding, 0, ',', '.'))
                : null;

            $case->status = IsolationCaseStatus::COMPLETED;
            $case->restoration_started_at ??= now();
            $case->restored_at = now();
            $case->closed_at = now();
            $case->syncActiveSlot();
            $case->save();

            $this->recordEvent(
                $case,
                IsolationEventType::RESTORATION_SUCCEEDED,
                $actor,
                $description,
                IsolationCaseStatus::RESTORATION_IN_PROGRESS,
                IsolationCaseStatus::COMPLETED,
                ['outstanding_at_restore' => $outstanding]
            );

            return $case;
        });
    }

    /**
     * Dipanggil dari listener `InvoiceStatusUpdated` (satu-satunya titik
     * broadcast perubahan invoice karena pembayaran, lihat
     * `Invoice::recalculateFromPayments()`) — BUKAN dipanggil manual dari
     * controller mana pun. Evaluasi ulang kelayakan pemulihan/pembatalan
     * tiap kali SATU invoice milik layanan yang punya kasus aktif berubah
     * saldo.
     *
     * Dicari lewat `customer_service_id` invoice, BUKAN lewat baris
     * `isolation_case_invoices` yang sudah ada — invoice itu baru benar-benar
     * TERTAUT ke kasus lewat `syncInvoices()` DI BAWAH, bukan prasyarat untuk
     * ditemukan di sini. Kalau gatenya pakai tautan yang sudah ada, invoice
     * yang jadi piutang SETELAH kasus dibuat (atau kasus dari trigger MANUAL
     * yang dibuat saat pelanggan belum ada piutang sama sekali) tidak akan
     * pernah ketemu kasusnya — pembayaran kelihatan "tidak ada aksi apa pun"
     * padahal listenernya jalan (bug nyata, 2026-11-19).
     */
    public function handlePaymentVerified(Invoice $invoice): void
    {
        if (! $invoice->customer_service_id) {
            return;
        }

        $case = IsolationCase::where('service_id', $invoice->customer_service_id)
            ->where('active_slot', 1)
            ->first();

        if (! $case) {
            return;
        }

        DB::transaction(function () use ($case, $invoice) {
            $this->syncInvoices($case);

            $this->recordEvent($case, IsolationEventType::PAYMENT_VERIFIED, null, null, null, null, [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'remaining_amount' => (float) $invoice->remaining_amount,
            ]);

            if ((float) $case->current_outstanding_amount > 0) {
                return;
            }

            if (in_array($case->status, IsolationCaseStatus::preSuspensionGroup(), true)) {
                $this->cancelCase($case, null, 'paid_before_isolation');

                return;
            }

            if ($case->status === IsolationCaseStatus::SUSPENDED) {
                $fromStatus = $case->status;
                $case->status = IsolationCaseStatus::RESTORATION_PENDING;
                $case->paid_at = now();
                $case->syncActiveSlot();
                $case->save();

                $this->recordEvent($case, IsolationEventType::RESTORATION_QUEUED, null, null, $fromStatus, IsolationCaseStatus::RESTORATION_PENDING);
            }
        });
    }

    /**
     * Tautkan invoice yang lewat jatuh tempo TERBARU milik layanan ini ke
     * kasus (kalau belum tertaut), lalu hitung ulang
     * `current_outstanding_amount` dari SELURUH invoice yang tertaut —
     * bukan dari `Invoice::scopeJatuhTempoUntukIsolir()` langsung, supaya
     * invoice yang sudah ditautkan tetap dihitung meski sudah lunas
     * sebagian/periodenya sudah lewat (BR-06). Pakai
     * `scopeJatuhTempoUntukIsolir()` (due_date < hari ini) — BUKAN
     * `scopePiutang()` pembukuan — supaya tagihan periode berjalan yang
     * sudah lewat tgl 10 ikut tertaut, bukan menunggu bulan berganti.
     */
    private function syncInvoices(IsolationCase $case): void
    {
        $alreadyLinked = $case->caseInvoices()->pluck('invoice_id');

        $newInvoices = Invoice::query()
            ->where('customer_service_id', $case->service_id)
            ->jatuhTempoUntukIsolir()
            ->whereNotIn('id', $alreadyLinked)
            ->get();

        foreach ($newInvoices as $invoice) {
            IsolationCaseInvoice::create([
                'isolation_case_id' => $case->id,
                'invoice_id' => $invoice->id,
                'amount_at_link' => $invoice->remaining_amount,
                'created_at' => now(),
            ]);

            if (! $case->billing_period) {
                $case->billing_period = $invoice->billing_period;
            }
        }

        $currentOutstanding = Invoice::query()
            ->whereIn('id', $case->caseInvoices()->pluck('invoice_id'))
            ->sum('remaining_amount');

        $case->current_outstanding_amount = $currentOutstanding;
        $case->save();
    }

    private function recordEvent(
        IsolationCase $case,
        IsolationEventType $type,
        ?User $actor,
        ?string $description = null,
        ?IsolationCaseStatus $from = null,
        ?IsolationCaseStatus $to = null,
        array $metadata = [],
        string $actorType = 'system',
    ): IsolationEvent {
        return IsolationEvent::create([
            'isolation_case_id' => $case->id,
            'event_type' => $type,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'actor_type' => $actor ? 'user' : $actorType,
            'actor_id' => $actor?->id,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function recordAction(IsolationCase $case, IsolationActionType $type, ?User $actor): IsolationAction
    {
        $attemptNumber = $case->actions()->where('action_type', $type->value)->count() + 1;

        return IsolationAction::create([
            'isolation_case_id' => $case->id,
            'action_type' => $type,
            'status' => IsolationActionStatus::SUCCEEDED,
            'idempotency_key' => "{$case->id}:{$type->value}:{$attemptNumber}",
            'attempt_number' => $attemptNumber,
            'requested_by' => $actor?->id,
            'executed_by' => $actor?->id,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
