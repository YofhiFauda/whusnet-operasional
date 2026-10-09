<?php

namespace App\Traits;

use App\Enums\CollectorRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\StaffPortalToken;
use App\Models\User;
use App\Services\CollectorPaymentService;
use App\Services\CustomerQrTokenService;
use App\Services\EffectiveAccessService;
use App\Support\RupiahInput;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                fn ($row) => is_array($row) ? RupiahInput::parseKeys($row, 'amount', 'use_balance_amount') : $row,
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
            // min:0, bukan min:1: baris yang seluruhnya dibayar dari saldo
            // pelanggan boleh tanpa uang tunai. Minimal Rp 1 (tunai + saldo)
            // dijaga di CollectorPaymentService::validateRows().
            'rows.*.amount' => 'required|numeric|min:0',
            'rows.*.use_balance_amount' => 'nullable|numeric|min:0',
            'rows.*.payment_method' => 'required|in:cash,transfer,lainnya',
            // Wajib untuk transfer — dicek di validateRows() bersama aturan
            // lain supaya pesan gagalnya per baris, bukan validasi generik.
            'rows.*.bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'rows.*.sender_name' => 'nullable|string|max:150',
            // Metode Lainnya wajib menjelaskan metode apa persisnya (mis.
            // "OVO") — dicek lagi di CollectorPaymentService::validateRows()
            // supaya konsisten dengan pesan gagal per baris yang sudah ada,
            // bukan validasi generik Laravel.
            'rows.*.note' => 'nullable|string|max:1000',
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
     * Jalur pembayaran dari Portal (token one-shot `StaffPortalToken`).
     *
     * Urutan dalam SATU transaksi, supaya token tidak bisa dipakai dua kali:
     *   1. kunci baris token (`lockForUpdate`) + pastikan belum terkonsumsi;
     *   2. izin `kolektor.qr.pay` & POP scope dicek ulang (bisa dicabut setelah scan);
     *   3. setiap invoice harus milik pelanggan yang terikat ke token — tanpa ini
     *      token pelanggan A bisa dipakai membayar tagihan pelanggan B;
     *   4. hanya periode berjalan (tagihan mendatang belum boleh ditagih);
     *   5. catat pembayaran (`recordBatch`, tanpa notifikasi) lalu konsumsi token.
     *
     * Notifikasi pop_admin dikirim SETELAH commit. Mengirim notifikasi di dalam
     * transaksi berarti kegagalan dispatch bisa menahan atau membatalkan transaksi
     * yang uangnya sudah diterima.
     *
     * @param  array{idempotency_key: string, rows: array<int, array<string, mixed>>}  $validated
     */
    protected function recordStaffPortalBatch(StaffPortalToken $token, User $collector, array $validated, CollectorRole $source): JsonResponse
    {
        $outcome = DB::transaction(function () use ($token, $collector, $validated, $source) {
            $locked = StaffPortalToken::query()->whereKey($token->id)->lockForUpdate()->first();

            // expires_at ikut dicek DI BAWAH lock: middleware sudah mengecek sebelum
            // transaksi, tapi token bisa lewat TTL saat request antre. Tanpa ini
            // batch tetap diproses setelah token kedaluwarsa.
            if (! $locked || $locked->consumed_at !== null || $locked->expires_at->isPast()) {
                abort(401, 'Token staf tidak valid, sudah dipakai, atau sudah kedaluwarsa.');
            }

            $customer = Customer::findOrFail($locked->customer_id);
            $this->assertStaffPortalAuthorized($collector, $customer);

            $rowInvoiceIds = array_column($validated['rows'], 'invoice_id');

            $foreignIds = Invoice::query()
                ->whereIn('id', $rowInvoiceIds)
                ->where('customer_id', '!=', $customer->id)
                ->pluck('id')
                ->all();

            if ($foreignIds !== []) {
                return ['response' => $this->staffPortalRejection(array_map(fn ($id) => [
                    'invoice_id' => $id,
                    'reason' => 'Tagihan ini bukan milik pelanggan pada QR yang discan.',
                ], $foreignIds))];
            }

            $futureInvoices = Invoice::query()
                ->whereIn('id', $rowInvoiceIds)
                ->where('billing_period', '>', now()->format('Y-m'))
                ->get(['id', 'invoice_number']);

            if ($futureInvoices->isNotEmpty()) {
                return ['response' => $this->staffPortalRejection($futureInvoices->map(fn (Invoice $invoice) => [
                    'invoice_id' => $invoice->id,
                    'reason' => "{$invoice->invoice_number}: periode tagihan belum berjalan.",
                ])->values()->all())];
            }

            $response = $this->recordBatch($collector, $collector, $validated, $source, notify: false);
            $payload = $response->getData(true);

            $recorded = ($payload['success'] ?? false) === true && ($payload['already_processed'] ?? false) === false;
            if ($recorded) {
                $locked->consume();
            }

            return ['response' => $response, 'recorded' => $recorded, 'results' => $payload['results'] ?? []];
        });

        if ($outcome['recorded'] ?? false) {
            app(CollectorPaymentService::class)->notifyPopAdmins($collector, $outcome['results']);
        }

        return $outcome['response'];
    }

    /**
     * Resolve QR `$code` → `Customer`, DAN pastikan itu pelanggan yang SAMA dengan
     * yang tertaut ke `$token`. Token diterbitkan untuk satu pelanggan; `$code` di
     * URL hanya bukti halaman Portal masih benar, bukan sumber identitas. Mencegah
     * staf menukar `code` di URL untuk memakai token pelanggan A ke pelanggan B.
     */
    protected function resolveStaffPortalCustomer(StaffPortalToken $token, string $code): Customer
    {
        [$rawToken, $signature] = array_pad(explode('.', $code, 2), 2, '');
        $resolution = app(CustomerQrTokenService::class)->resolve($rawToken, $signature);

        if ($resolution['status'] !== 'success' || (int) $resolution['qrToken']->customer_id !== $token->customer_id) {
            abort(404);
        }

        return Customer::findOrFail($token->customer_id);
    }

    /**
     * Izin `kolektor.pay` DAN `kolektor.qr.pay` + POP scope pelanggan. Dicek ulang
     * di setiap request Portal, karena token berumur 15 menit dan izin bisa dicabut
     * di antaranya.
     *
     * Dua permission sengaja dipasang bersama: `kolektor.qr.pay` memang terpisah
     * dari `kolektor.pay` (lihat QrScanController), tapi jalur web teknisi digerbang
     * `kolektor.pay`. Dengan syarat ganda, mencabut salah satunya langsung
     * menutup jalur QR juga — dulu hanya `kolektor.qr.pay` yang dicek di sini.
     */
    protected function assertStaffPortalAuthorized(User $user, Customer $customer): void
    {
        abort_unless(
            $user->hasPermission('kolektor.pay') && $user->hasPermission('kolektor.qr.pay'),
            403,
            'Izin pembayaran QR Anda sudah dicabut.'
        );

        $access = app(EffectiveAccessService::class);
        $inScope = $access->hasAllPopAccess($user)
            || in_array((int) $customer->pop_id, $access->getAllowedPopIds($user), true);

        abort_unless($inScope, 403, 'Pelanggan ini di luar POP scope Anda.');
    }

    /**
     * @param  array<int, array{invoice_id: mixed, reason: string}>  $failures
     */
    private function staffPortalRejection(array $failures): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Batch ditolak — ada baris tidak valid. Tidak ada payment yang tersimpan.',
            'failures' => $failures,
        ], 422);
    }

    /**
     * @param  array{idempotency_key: string, rows: array<int, array<string, mixed>>}  $validated
     */
    protected function recordBatch(User $collector, User $actor, array $validated, CollectorRole $source = CollectorRole::KOLEKTOR, bool $notify = true): JsonResponse
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

        $failures = $service->validateRows($collector, $validated['rows'], $actor, $source);

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
                $validated['rows'],
                $source
            );
        } catch (\Throwable $e) {
            // Dua submit paralel dengan idempotency_key sama: yang kalah kena unique
            // violation, padahal batch pemenangnya sudah tercatat. Balas seperti
            // replay, jangan "gagal" (kolektor bisa membayar ulang secara manual).
            $winner = $service->findProcessedBatch($validated['idempotency_key']);
            if ($winner) {
                return response()->json([
                    'success' => true,
                    'message' => 'Batch ini sudah pernah diproses sebelumnya — tidak diproses ulang.',
                    'batch_id' => $winner->id,
                    'already_processed' => true,
                ]);
            }

            // Pesan SQL mentah jangan sampai ke klien. Hanya error domain (mis.
            // "tagihan sudah lunas") yang pesannya ditampilkan.
            if ($e instanceof QueryException) {
                report($e);

                return response()->json([
                    'success' => false,
                    'message' => 'Batch ditolak karena gangguan sistem. Silakan coba lagi.',
                    'failures' => [['reason' => 'Gangguan sistem, belum ada pembayaran yang tersimpan.']],
                ], 422);
            }

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
        // `$notify = false` dipakai jalur Portal (recordStaffPortalBatch): notifikasi
        // dikirim SETELAH transaksi kunci-token commit, bukan di dalamnya.
        if ($notify) {
            $service->notifyPopAdmins($collector, $outcome['results']);
        }

        return response()->json([
            'success' => true,
            'message' => "{$outcome['processed']} pembayaran berhasil dicatat untuk kolektor {$collector->name}.",
            'processed' => $outcome['processed'],
            'results' => $outcome['results'],
        ]);
    }
}
