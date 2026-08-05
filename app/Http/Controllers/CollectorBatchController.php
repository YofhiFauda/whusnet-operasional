<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentBatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint bayar batch untuk SATU kolektor — dipakai dari tab "Worklist &
 * Bayar" di CollectorController::show(). Inti fitur (docs/plan/analisa-
 * billing-tagihan-pembayaran-kolektor.md §B-5B, §E2.5):
 *
 *   - Satu batch = satu kolektor (§D-9 no. 1) — dijamin struktural di sini
 *     karena route-nya sudah terikat ke {collector}, bukan diterima dari body.
 *   - Satu transaksi DB untuk seluruh batch — gagal satu baris = batch
 *     ditolak semua, dengan daftar gagal + alasan per baris (§B-7 no. 2 & 7).
 *   - `payment_batches.idempotency_key` cegah submit dobel.
 *   - Payment tetap 1-invoice-1-payment (§D-3, disederhanakan) — "bayar
 *     semua tunggakan" = banyak payment tercipta sekaligus, bukan satu
 *     payment dipecah ke banyak invoice.
 *   - Endpoint yang SAMA dipakai buat bayar 1-by-1 (kirim `rows` isi 1) dan
 *     bayar massal (kirim `rows` banyak) — dua tombol beda di UI, satu jalur
 *     backend, satu jaminan konsistensi (all-or-nothing, idempotency).
 */
class CollectorBatchController extends Controller
{
    public function store(Request $request, User $collector): JsonResponse
    {
        $this->authorizeCollector($collector);

        $validated = $request->validate([
            'idempotency_key' => 'required|string|max:191',
            'rows' => 'required|array|min:1',
            'rows.*.invoice_id' => 'required|integer',
            'rows.*.amount' => 'required|numeric|min:1',
            'rows.*.payment_method' => 'required|in:cash,transfer,qris,lainnya',
            'rows.*.collected_date' => 'required|date',
        ]);

        // Idempotent: submit ulang dgn key yang sama = diabaikan, bukan error.
        $existingBatch = PaymentBatch::where('idempotency_key', $validated['idempotency_key'])->first();
        if ($existingBatch) {
            return response()->json([
                'success' => true,
                'message' => 'Batch ini sudah pernah diproses sebelumnya — tidak diproses ulang.',
                'batch_id' => $existingBatch->id,
                'already_processed' => true,
            ]);
        }

        // Fase validasi cepat (tanpa lock) — supaya pesan gagal per baris bisa
        // dikembalikan tanpa perlu masuk transaksi dulu untuk kasus umum.
        $rows = $validated['rows'];
        $failures = $this->validateRows($collector, $rows);

        if ($failures !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Batch ditolak — ada baris tidak valid. Tidak ada payment yang tersimpan.',
                'failures' => $failures,
            ], 422);
        }

        try {
            [$paymentCount, $results] = DB::transaction(function () use ($collector, $rows, $validated) {
                $batch = PaymentBatch::create([
                    'idempotency_key' => $validated['idempotency_key'],
                    'submitted_by' => auth()->id(),
                    'collector_id' => $collector->id,
                    'submitted_at' => now(),
                ]);

                $results = [];

                foreach ($rows as $row) {
                    // Rekunci & re-validasi di dalam transaksi — pengecekan di
                    // luar tadi cuma optimasi UX, ini yang otoritatif terhadap
                    // race (mis. invoice dibayar dari jalur lain di antara dua
                    // fase). Kalau tetap gagal di sini, lempar supaya SELURUH
                    // batch rollback (all-or-nothing), bukan tersimpan separuh.
                    $lockedInvoice = Invoice::query()
                        ->whereKey($row['invoice_id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    $amount = round((float) $row['amount'], 2);
                    if ($amount > round((float) $lockedInvoice->remaining_amount, 2)) {
                        throw new \RuntimeException("Invoice {$lockedInvoice->invoice_number}: nominal melebihi sisa tagihan (kemungkinan berubah sejak form dibuka).");
                    }

                    Payment::create([
                        'payment_number' => Payment::generatePaymentNumber(now()->format('Y-m-d')),
                        'invoice_id' => $lockedInvoice->id,
                        'payment_batch_id' => $batch->id,
                        'customer_id' => $lockedInvoice->customer_id,
                        'pop_id' => $lockedInvoice->pop_id,
                        'payment_date' => now()->format('Y-m-d'),
                        'collected_date' => $row['collected_date'],
                        'payment_method' => $row['payment_method'],
                        'amount' => $amount,
                        'received_by' => auth()->id(),
                        'collected_by' => $collector->id,
                        'payment_status' => PaymentStatus::VALID->value,
                        'note' => 'Batch kolektor: '.$collector->name,
                    ]);

                    $lockedInvoice->recalculateFromPayments();

                    $results[] = [
                        'invoice_id' => $lockedInvoice->id,
                        'invoice_status' => $lockedInvoice->invoice_status->value,
                        'remaining_amount' => (float) $lockedInvoice->remaining_amount,
                    ];
                }

                return [count($rows), $results];
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch ditolak: '.$e->getMessage(),
                'failures' => [['reason' => $e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "{$paymentCount} pembayaran berhasil dicatat untuk kolektor {$collector->name}.",
            'processed' => $paymentCount,
            'results' => $results,
        ]);
    }

    /**
     * @param  array<int, array{invoice_id: int, amount: float|string, payment_method: string, collected_date: string}>  $rows
     * @return array<int, array{invoice_id: mixed, reason: string}>
     */
    private function validateRows(User $collector, array $rows): array
    {
        $failures = [];

        $invoiceIds = array_column($rows, 'invoice_id');
        $invoices = Invoice::query()
            ->applyUserScope()
            ->with('customer')
            ->whereIn('id', $invoiceIds)
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $invoice = $invoices->get($row['invoice_id']);

            if (! $invoice) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => 'Invoice tidak ditemukan atau di luar scope POP Anda.',
                ];

                continue;
            }

            if (! $invoice->customer || (int) $invoice->customer->collector_id !== $collector->id) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: pelanggan ini bukan milik kolektor {$collector->name}.",
                ];

                continue;
            }

            if (in_array($invoice->invoice_status->value, ['lunas', 'batal'], true)) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: sudah {$invoice->invoice_status->label()}.",
                ];

                continue;
            }

            $amount = round((float) $row['amount'], 2);
            if ($amount <= 0) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: nominal harus lebih dari nol.",
                ];

                continue;
            }

            if ($amount > round((float) $invoice->remaining_amount, 2)) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: nominal Rp".number_format($amount, 0, ',', '.').' melebihi sisa Rp'.number_format((float) $invoice->remaining_amount, 0, ',', '.').'.',
                ];
            }
        }

        return $failures;
    }

    private function authorizeCollector(User $collector): void
    {
        abort_unless($collector->hasRole('kolektor'), 404, 'User ini bukan kolektor.');
    }
}
