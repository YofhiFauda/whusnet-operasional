<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\ScopeType;
use App\Events\CollectorActivityUpdated;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentBatch;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan pembayaran batch atas nama SATU kolektor. Dipakai dua jalur
 * masuk yang berbeda audiens, dengan satu logika yang sama persis:
 *
 *   - Admin  → PaymentBatchController (rute ber-{collector})
 *   - Kolektor → CollectorPaymentController (rute TANPA {collector},
 *     `$collector` dipaksa `auth()->user()` di controller)
 *
 * Logikanya sengaja di service, bukan di salah satu controller lalu dipanggil
 * silang: dua jalur masuk dengan jaminan berbeda (atomicity, idempotency,
 * batas nominal) adalah cara tercepat bikin dua kebenaran yang menyimpang.
 *
 * Jaminan yang dipegang di sini:
 *   - Satu batch = satu kolektor. Struktural: `$collector` parameter, tak
 *     pernah dibaca dari body request.
 *   - Satu transaksi DB untuk seluruh batch — gagal satu baris = batch
 *     ditolak semua, dengan daftar gagal + alasan per baris.
 *   - `payment_batches.idempotency_key` cegah submit dobel.
 *   - Payment tetap 1-invoice-1-payment; "bayar semua tunggakan" = banyak
 *     payment sekaligus, bukan satu payment dipecah ke banyak invoice.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §9, §11.3.
 */
class CollectorPaymentService
{
    public function __construct(private readonly CollectorVisitService $visits) {}

    /**
     * Validasi cepat tanpa lock — supaya pesan gagal per baris bisa
     * dikembalikan tanpa perlu masuk transaksi dulu untuk kasus umum.
     *
     * `$viewer` menentukan POP scope yang dipakai: admin memakai scope-nya
     * sendiri, kolektor memakai scope dirinya. Dua-duanya harus lolos —
     * pelanggan di luar scope penagih tetap ditolak walau `collector_id`-nya
     * cocok (guard §14.2 no. 4: scope POP bisa dipersempit setelah assign).
     *
     * SENGAJA tidak mengecek jendela jatuh tempo (§10). Jendela itu aturan
     * TAMPILAN — kalau ikut ditegakkan di tulis, pembayaran sah bisa ditolak
     * cuma karena batas harinya lewat tengah malam saat form masih terbuka.
     * Otorisasi yang sesungguhnya = kepemilikan pelanggan, dicek di bawah.
     *
     * @param  array<int, array{invoice_id: int, amount: float|string, payment_method: string, collected_date: string}>  $rows
     * @return array<int, array{invoice_id?: mixed, reason: string}>
     */
    public function validateRows(User $collector, array $rows, User $viewer): array
    {
        $failures = [];

        $invoices = Invoice::query()
            ->applyUserScope($viewer)
            ->with('customer')
            ->whereIn('id', array_column($rows, 'invoice_id'))
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

            $amount = Money::of($row['amount']);
            if ($amount <= 0) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: nominal harus lebih dari nol.",
                ];

                continue;
            }

            if (Money::greaterThan($amount, $invoice->remaining_amount)) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: nominal Rp".number_format($amount, 0, ',', '.').' melebihi sisa Rp'.number_format((float) $invoice->remaining_amount, 0, ',', '.').'.',
                ];
            }
        }

        return $failures;
    }

    /**
     * Batch yang sudah pernah diproses dengan key ini, kalau ada.
     *
     * WAJIB dicek pemanggil SEBELUM validateRows(), bukan sesudah: pada submit
     * ulang, invoice-nya sudah lunas dari submit pertama, jadi validasi pasti
     * gagal dan pengguna menerima 422 "sudah lunas" untuk pembayaran yang
     * sebenarnya berhasil. Idempotensi harus mendahului validasi, bukan
     * mengekor di belakangnya.
     */
    public function findProcessedBatch(string $idempotencyKey): ?PaymentBatch
    {
        return PaymentBatch::where('idempotency_key', $idempotencyKey)->first();
    }

    /**
     * Simpan satu batch. Melempar \RuntimeException kalau ada baris yang gagal
     * di dalam transaksi — pemanggil menerjemahkannya jadi response 422.
     *
     * @param  array<int, array{invoice_id: int, amount: float|string, payment_method: string, collected_date: string}>  $rows
     * @return array{already_processed: bool, batch_id: int, processed: int, results: array<int, array<string, mixed>>}
     */
    public function record(User $collector, User $actor, string $idempotencyKey, array $rows): array
    {
        // Jaring pengaman terakhir kalau pemanggil lupa findProcessedBatch():
        // submit ulang dgn key sama = diabaikan, bukan dobel-simpan.
        $existingBatch = $this->findProcessedBatch($idempotencyKey);
        if ($existingBatch) {
            return [
                'already_processed' => true,
                'batch_id' => $existingBatch->id,
                'processed' => 0,
                'results' => [],
            ];
        }

        [$batchId, $results] = DB::transaction(function () use ($collector, $actor, $rows, $idempotencyKey) {
            $batch = PaymentBatch::create([
                'idempotency_key' => $idempotencyKey,
                'submitted_by' => $actor->id,
                'collector_id' => $collector->id,
                'submitted_at' => now(),
            ]);

            $results = [];

            foreach ($rows as $row) {
                // Rekunci & re-validasi di dalam transaksi — pengecekan di
                // validateRows() cuma optimasi UX, ini yang otoritatif terhadap
                // race (mis. invoice dibayar dari jalur lain di antara dua
                // fase). Kalau tetap gagal di sini, lempar supaya SELURUH batch
                // rollback (all-or-nothing), bukan tersimpan separuh.
                $lockedInvoice = Invoice::query()
                    ->whereKey($row['invoice_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                // Status DIPERIKSA ULANG di sini, bukan cuma di validateRows().
                //
                // Khususnya `batal`: Invoice::recalculateFromPayments()
                // early-return untuk invoice batal, jadi `remaining_amount`-nya
                // tetap utuh dan pemeriksaan nominal saja lolos. Kalau admin
                // membatalkan invoice di antara dua fase, payment tetap
                // tersimpan, invoice tetap `batal`, dan uangnya masuk saldo
                // kolektor menempel pada tagihan yang sudah mati.
                if (in_array($lockedInvoice->invoice_status->value, ['lunas', 'batal'], true)) {
                    throw new \RuntimeException("Invoice {$lockedInvoice->invoice_number}: sudah {$lockedInvoice->invoice_status->label()} (berubah sejak form dibuka).");
                }

                $amount = Money::of($row['amount']);
                if (Money::greaterThan($amount, $lockedInvoice->remaining_amount)) {
                    throw new \RuntimeException("Invoice {$lockedInvoice->invoice_number}: nominal melebihi sisa tagihan (kemungkinan berubah sejak form dibuka).");
                }

                $payment = Payment::create([
                    'payment_number' => Payment::generatePaymentNumber(now()->format('Y-m-d')),
                    'invoice_id' => $lockedInvoice->id,
                    'payment_batch_id' => $batch->id,
                    'customer_id' => $lockedInvoice->customer_id,
                    'pop_id' => $lockedInvoice->pop_id,
                    'payment_date' => now()->format('Y-m-d'),
                    'collected_date' => $row['collected_date'],
                    'payment_method' => $row['payment_method'],
                    'amount' => $amount,
                    'received_by' => $actor->id,
                    'collected_by' => $collector->id,
                    'payment_status' => PaymentStatus::VALID->value,
                    'note' => 'Batch kolektor: '.$collector->name,
                ]);

                $lockedInvoice->recalculateFromPayments();

                // Kunjungan "bayar" diturunkan dari payment yang BENAR-BENAR
                // tersimpan, tak pernah dari input manual (§12). Ditulis di
                // dalam transaksi yang sama supaya mustahil ada payment tanpa
                // jejak kunjungan — kalau dua-duanya bisa gagal terpisah,
                // laporan aging bohong tepat di baris yang paling penting.
                $this->visits->recordPaid(
                    $collector,
                    (int) $lockedInvoice->customer_id,
                    $payment->id,
                    $row['collected_date'],
                );

                $results[] = [
                    'invoice_id' => $lockedInvoice->id,
                    'customer_id' => $lockedInvoice->customer_id,
                    'payment_id' => $payment->id,
                    'invoice_status' => $lockedInvoice->invoice_status->value,
                    'remaining_amount' => (float) $lockedInvoice->remaining_amount,
                    'pop_id' => $lockedInvoice->pop_id,
                    'amount' => $amount,
                ];
            }

            return [$batch->id, $results];
        });

        // SENGAJA TIDAK memanggil notifyPopAdmins() di sini.
        //
        // Titik ini sudah SESUDAH commit — uangnya permanen. Pemanggil
        // membungkus record() dengan try/catch untuk menangkap kegagalan
        // penyimpanan; kalau notifikasi ikut di dalamnya, satu exception dari
        // dispatch (broadcast mati, queue penuh) dijawab 422 "Batch ditolak"
        // padahal payment sudah tersimpan. Kolektor lalu menekan Bayar lagi
        // dan pelanggan terkredit dua kali.
        //
        // Notifikasi dikirim pemanggil, di luar wilayah try — lihat
        // RecordsCollectorBatch::recordBatch().
        return [
            'already_processed' => false,
            'batch_id' => $batchId,
            'processed' => count($rows),
            'results' => $results,
        ];
    }

    /**
     * Notif ke pop_admin per POP yang kena setoran ini — sistem ini gak punya
     * role "Finance Pusat", jadi penerima paling pas adalah pop_admin: dialah
     * yang pegang payments.validate/reject buat POP-nya. Di-grup per pop_id
     * karena satu batch kolektor secara teknis bisa nyentuh invoice lintas POP
     * walau jarang terjadi di praktik.
     *
     * Pesan murni informatif ("dicatat"). Verifikasi setoran kas baru masuk di
     * Fase 2 (collector_deposits) — jangan bikin pesan ini menyiratkan ada
     * langkah approve yang belum ada tombolnya.
     *
     * Kegagalan di sini TIDAK boleh menggagalkan pembayaran — uangnya sudah
     * tersimpan dan sudah diterima dari pelanggan. Exception ditelan dan
     * dilaporkan ke log; kabar yang tak terkirim adalah masalah operasional,
     * bukan alasan menganulir transaksi.
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    public function notifyPopAdmins(User $collector, array $results): void
    {
        try {
            $this->dispatchPopAdminNotifications($collector, $results);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function dispatchPopAdminNotifications(User $collector, array $results): void
    {
        collect($results)->groupBy('pop_id')->each(function (Collection $rowsInPop, $popId) use ($collector) {
            if (! $popId) {
                return;
            }

            // Total yang dikabarkan ke admin & disiarkan realtime — dijumlahkan
            // di ranah sen supaya angka di notifikasi tak pernah meleset dari
            // jumlah baris yang benar-benar tercatat.
            $total = Money::sum($rowsInPop->pluck('amount'));

            $this->notifyRoleUsersInPop(
                (int) $popId,
                'pop_admin',
                'Setoran Kolektor: '.$collector->name,
                $rowsInPop->count().' pembayaran (total Rp'.number_format($total, 0, ',', '.').") dicatat kolektor {$collector->name}.",
                route('collector-worksheet.show', $collector->id)
            );

            // Notifikasi saja tidak cukup: ia mendarat di lonceng, sementara
            // Worksheet yang sedang TERBUKA tetap menampilkan saldo dan daftar
            // tunggakan yang sudah basi. `InvoiceStatusUpdated` memang tersiar
            // dari Invoice::recalculateFromPayments(), tapi ke kanal
            // `invoices.{popId}` yang tidak didengarkan halaman ini.
            CollectorActivityUpdated::dispatch(
                $collector,
                (int) $popId,
                'pembayaran_dicatat',
                $rowsInPop->count(),
                (float) $total,
            );
        });
    }

    /**
     * Semua user berrole $roleCode yang punya akses ke POP $popId — pola sama
     * persis `TicketService::usersWithRoleInPop()`.
     */
    private function notifyRoleUsersInPop(int $popId, string $roleCode, string $title, string $message, string $actionUrl): void
    {
        $users = User::whereHas('role', fn ($q) => $q->where('code', $roleCode))
            ->where(function ($query) use ($popId) {
                $query->whereHas('roleScopes', fn ($q) => $q->where('scope_type', ScopeType::ALL_POP->value))
                    ->orWhereHas('roleScopes', fn ($q) => $q->whereIn('scope_type', [ScopeType::SELECTED_POP->value, ScopeType::POP_TREE->value])
                        ->whereHas('targets', fn ($t) => $t->where('pop_id', $popId))
                    );
            })
            ->get();

        foreach ($users as $user) {
            $user->notify(new AppNotification(
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                type: NotificationType::INFO
            ));
        }
    }
}
