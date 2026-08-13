<?php

namespace App\Traits;

use App\Models\User;
use App\Services\CollectorPaymentService;
use App\Support\RupiahInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bentuk request & response batch pembayaran kolektor, dipakai bersama oleh
 * dua controller dengan audiens berbeda (PaymentBatchController untuk admin,
 * CollectorPaymentController untuk kolektor).
 *
 * Sengaja trait, bukan pewarisan antar-controller: yang dibagi cuma bentuk
 * I/O, sementara otorisasi & sumber `$collector` justru HARUS beda di dua
 * jalur itu (route parameter vs `auth()->user()`). Pewarisan bikin perbedaan
 * yang disengaja itu gampang hilang tanpa sengaja.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §9.
 */
trait RecordsCollectorBatch
{
    /**
     * Nominal tiap baris dinormalkan SEBELUM validasi: kolektor mengetik
     * `150.000` di tabel penagihan, dan titik ribuan yang dibaca sebagai
     * desimal Inggris membuat pembayaran tercatat 1.000 kali lebih kecil tanpa
     * error apa pun. Sama seperti jalur Tagihan (`PaymentController::store`).
     */
    protected function normalizeBatchAmounts(Request $request): void
    {
        $rows = $request->input('rows');

        if (! is_array($rows)) {
            return;
        }

        $request->merge([
            'rows' => array_map(
                fn ($row) => is_array($row) ? RupiahInput::parseKeys($row, 'amount') : $row,
                $rows
            ),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function batchValidationRules(): array
    {
        return [
            'idempotency_key' => 'required|string|max:191',
            'rows' => 'required|array|min:1',
            'rows.*.invoice_id' => 'required|integer',
            'rows.*.amount' => 'required|numeric|min:1',
            'rows.*.payment_method' => 'required|in:cash,transfer,qris,lainnya',
            // Batas atas WAJIB. Tanpa `before_or_equal:today`, kolektor bisa
            // mengirim `2030-01-01`: nilainya mendarat di
            // `payments.collected_date` (merusak pemotongan pendapatan per
            // periode — justru itu alasan kolom ini dibuat, §B-8 no. 8) dan
            // diteruskan ke CollectorVisitService::recordPaid() jadi kunjungan
            // bertanggal masa depan. Jalur kunjungan sudah melarang persis ini;
            // jalur bayar sempat melewatinya.
            'rows.*.collected_date' => 'required|date|before_or_equal:today',
        ];
    }

    /**
     * @param  array{idempotency_key: string, rows: array<int, array<string, mixed>>}  $validated
     */
    protected function recordBatch(User $collector, User $actor, array $validated): JsonResponse
    {
        $service = app(CollectorPaymentService::class);

        // Idempotensi DULU, baru validasi. Kebalikannya bikin submit ulang
        // (klik dobel, jaringan putus lalu retry) ditolak 422 "invoice sudah
        // lunas" — padahal yang terjadi justru pembayarannya sudah berhasil.
        $existingBatch = $service->findProcessedBatch($validated['idempotency_key']);
        if ($existingBatch) {
            return response()->json([
                'success' => true,
                'message' => 'Batch ini sudah pernah diproses sebelumnya — tidak diproses ulang.',
                'batch_id' => $existingBatch->id,
                'already_processed' => true,
            ]);
        }

        $failures = $service->validateRows($collector, $validated['rows'], $actor);

        if ($failures !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Batch ditolak — ada baris tidak valid. Tidak ada payment yang tersimpan.',
                'failures' => $failures,
            ], 422);
        }

        try {
            $outcome = $service->record(
                $collector,
                $actor,
                $validated['idempotency_key'],
                $validated['rows']
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch ditolak: '.$e->getMessage(),
                'failures' => [['reason' => $e->getMessage()]],
            ], 422);
        }

        if ($outcome['already_processed']) {
            return response()->json([
                'success' => true,
                'message' => 'Batch ini sudah pernah diproses sebelumnya — tidak diproses ulang.',
                'batch_id' => $outcome['batch_id'],
                'already_processed' => true,
            ]);
        }

        // Notifikasi DI LUAR try di atas. Batas transaksi dan batas penanganan
        // error harus sejajar: begitu payment commit, tak ada apa pun sesudahnya
        // yang boleh membuat response jadi "gagal". Kalau notifikasi ikut
        // dijaga try, satu exception dispatch dijawab 422 sementara uangnya
        // sudah tercatat — dan retry kolektor menyimpan payment kedua.
        $service->notifyPopAdmins($collector, $outcome['results']);

        return response()->json([
            'success' => true,
            'message' => "{$outcome['processed']} pembayaran berhasil dicatat untuk kolektor {$collector->name}.",
            'processed' => $outcome['processed'],
            'results' => $outcome['results'],
        ]);
    }
}
