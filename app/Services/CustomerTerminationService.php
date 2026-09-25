<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\ManualInvoiceCategory;
use App\Enums\WorkflowTransition;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerTerminationReason;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Putus Langganan (ADHOC-69) — satu-satunya jalur yang boleh menerminasi
 * pelanggan lewat form. Digabungkan dari bekas `CustomerTerminationController`
 * (cuma update status, tidak menyentuh invoice/denda sama sekali) mengikuti
 * aturan CLAUDE.md "semua business logic di Service".
 *
 * Aturan denda (docs/plan/billing/analisa-rancangan-putus-langganan.md §3.1,
 * revisi 2026-09-22): masa langganan <=1 tahun → denda WAJIB diisi manual
 * (0 sah, field tidak boleh kosong), boleh diprefill dari
 * `default_penalty_amount` alasan tapi wajib dikonfirmasi admin. Masa >1
 * tahun → denda tidak berlaku SAMA SEKALI, `penalty_amount` klien diabaikan
 * sepenuhnya di sini (guard anti tamper) — bukan cuma di form.
 *
 * Tanpa prorate (keputusan 2026-09-19) — invoice Bulanan periode berjalan
 * dibiarkan apa adanya, cuma invoice DENDA (kalau eligible & >0) yang lahir
 * di sini.
 *
 * ADHOC-87 (2026-09-23, disederhanakan 2026-09-24) — form ini berlabel
 * "Request Putus Langganan" (label saja, tanpa persetujuan bertingkat).
 * SENGAJA TIDAK lagi membebaskan tagihan periode dalam transaksi yang sama
 * (deviasi dari §4.5 rancangan awal) — pembebasan tagihan (sudah/belum
 * terbit) sekarang SATU pintu saja: aksi "Bebaskan Tagihan Periode" di
 * Detail Pelanggan (`CustomerBillingWaiverController`/`BillingPeriodWaiverService`),
 * dipakai independen dari terminasi. Kalau ada tagihan yang mau dibebaskan
 * sebelum/sesudah putus, admin pakai aksi itu — putus langganan sendiri
 * murni alasan + denda + catatan.
 */
class CustomerTerminationService
{
    /**
     * Sub-nama Tagihan Manual/Lainnya untuk denda putus langganan — konstanta
     * bersama supaya laporan yang mengelompokkan per sub tidak terpecah oleh
     * salah ketik (§2.1 dokumen rancangan).
     */
    public const PENALTY_SUBTYPE_NAME = 'Denda Putus Langganan';

    public function __construct(
        private readonly CustomerWorkflowService $workflow,
        private readonly InvoiceNumberGenerator $numbers,
    ) {}

    /**
     * @param  array{termination_reason_id: int, termination_note: string|null, penalty_amount: string|float|int|null}  $validated
     */
    public function terminate(Customer $customer, array $validated, int $actorId): ?Invoice
    {
        $reason = CustomerTerminationReason::findOrFail($validated['termination_reason_id']);

        $service = $customer->customerService;
        $activationDate = $service?->activation_date;

        // NULL (data legacy/belum lengkap) diperlakukan <=1 tahun — jalur
        // paling aman, sistem tidak membebaskan denda dari data tidak lengkap.
        $eligibleForPenalty = $activationDate === null
            || now()->lessThanOrEqualTo(Carbon::parse($activationDate)->addYear());

        // Guard anti tamper: klien boleh kirim apa pun, server yang menang.
        $penaltyAmount = null;
        if ($eligibleForPenalty) {
            if (! isset($validated['penalty_amount']) || $validated['penalty_amount'] === '' || $validated['penalty_amount'] === null) {
                throw ValidationException::withMessages([
                    'penalty_amount' => 'Nominal denda wajib diisi (boleh 0) untuk pelanggan dengan masa langganan <= 1 tahun.',
                ]);
            }
            $penaltyAmount = (float) $validated['penalty_amount'];
        }

        $oldStatus = (string) $customer->status;
        $note = $reason->name.(filled($validated['termination_note'] ?? null) ? ' — '.$validated['termination_note'] : '');

        $invoice = null;

        DB::transaction(function () use ($customer, $service, $validated, $reason, $note, $oldStatus, $eligibleForPenalty, $penaltyAmount, $actorId, &$invoice) {
            $this->workflow->transition($customer, WorkflowTransition::TERMINATED, $note);

            $customer->forceFill([
                'termination_reason_id' => $reason->id,
                'termination_note' => $validated['termination_note'] ?? null,
            ])->save();

            if ($service) {
                $service->update(['service_status' => 'berhenti']);
            }

            // Audit 'customers'/'terminate' TETAP ditulis (dibaca import legacy
            // & sebagai jejak historis) — RendersCustomerList sekarang membaca
            // alasan dari relasi `termination_reason_id`, bukan lagi baris ini.
            AuditLog::create([
                'user_id' => $actorId,
                'module' => 'customers',
                'action' => 'terminate',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'status' => 'terminated',
                    'reason' => $note,
                    'termination_reason_id' => $reason->id,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            if ($eligibleForPenalty && $penaltyAmount > 0 && $service) {
                $billingPeriod = now()->format('Y-m');

                $invoice = Invoice::create([
                    'invoice_number' => $this->numbers->nextFor($billingPeriod),
                    'invoice_type' => InvoiceType::MANUAL->value,
                    'manual_category' => ManualInvoiceCategory::LAINNYA->value,
                    'manual_subtype_name' => self::PENALTY_SUBTYPE_NAME,
                    'description' => "Denda putus langganan — {$reason->name}",
                    'customer_id' => $customer->id,
                    'pop_id' => $customer->pop_id,
                    'customer_service_id' => $service->id,
                    'internet_package_id' => $service->internet_package_id,
                    'billing_period' => $billingPeriod,
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->toDateString(),
                    'subtotal' => $penaltyAmount,
                    'discount' => 0,
                    'ppn' => 0,
                    'total_amount' => $penaltyAmount,
                    'paid_amount' => 0,
                    'remaining_amount' => $penaltyAmount,
                    'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
                    'created_by' => $actorId,
                ]);
            }
        });

        return $invoice;
    }
}
