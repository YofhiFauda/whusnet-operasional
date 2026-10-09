<?php

namespace App\Services;

use App\Enums\BalanceMutationSource;
use App\Enums\CollectorRole;
use App\Enums\InvoiceType;
use App\Enums\NotificationType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ScopeType;
use App\Events\CollectorActivityUpdated;
use App\Models\BankAccount;
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
    public function __construct(
        private readonly CollectorVisitService $visits,
        private readonly CustomerBalanceService $balances,
    ) {}

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
     * @param  array<int, array{invoice_id: int, amount: float|string, payment_method: string, collected_date: string, note?: string}>  $rows
     * @return array<int, array{invoice_id?: mixed, reason: string}>
     */
    public function validateRows(User $collector, array $rows, User $viewer, CollectorRole $source = CollectorRole::KOLEKTOR): array
    {
        $failures = [];
        // Saldo yang sudah dialokasikan ke tiap pelanggan oleh baris-baris sebelumnya.
        $balanceUsedByCustomer = [];
        // Total tunai + saldo yang sudah dialokasikan ke tiap tagihan oleh baris-baris sebelumnya.
        $appliedByInvoice = [];

        $invoices = Invoice::query()
            ->applyUserScope($viewer)
            ->with('customer')
            ->whereIn('id', array_column($rows, 'invoice_id'))
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            // Akumulasi kuota hanya dicatat kalau baris ini lolos semua cek (lihat
            // di akhir loop). Baris yang gagal tidak boleh ikut memakan sisa tagihan
            // atau saldo milik baris berikutnya.
            $failuresBeforeRow = count($failures);
            $invoice = $invoices->get($row['invoice_id']);

            if (! $invoice) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => 'Invoice tidak ditemukan atau di luar scope POP Anda.',
                ];

                continue;
            }

            // Kepemilikan pelanggan hanya berlaku untuk kolektor (penugasan
            // admin). Teknisi mencatat pelanggan mana pun dalam POP scope-nya
            // (rancangan-pembayaran-teknisi §5) — POP scope tetap dicek di
            // `applyUserScope($viewer)` di atas.
            if (! $invoice->customer) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: pelanggan tidak ditemukan.",
                ];

                continue;
            }

            if ($source === CollectorRole::KOLEKTOR && (int) $invoice->customer->collector_id !== $collector->id) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: pelanggan ini bukan milik kolektor {$collector->name}.",
                ];

                continue;
            }

            if (in_array($invoice->invoice_status->value, ['lunas', 'batal', 'tak_tertagih'], true)) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: sudah {$invoice->invoice_status->label()}.",
                ];

                continue;
            }

            // Tagihan periode mendatang belum boleh ditagih lewat jalur mana pun
            // (worklist kolektor & pencarian teknisi juga tidak menampilkannya).
            // Ditegakkan di sini supaya jalur web, kolektor, dan teknisi ikut kena,
            // bukan hanya Portal.
            if ((string) $invoice->billing_period > now()->format('Y-m')) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: periode tagihan belum berjalan.",
                ];

                continue;
            }

            $amount = Money::of($row['amount']);
            $useBalance = Money::of($row['use_balance_amount'] ?? 0);

            if (Money::lessThan(Money::add($amount, $useBalance), 1)) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: nominal harus lebih dari nol atau pakai Saldo Pelanggan.",
                ];

                continue;
            }

            // Batch TIDAK menerima lebih bayar (keputusan user 2026-10-05): tunai +
            // saldo per tagihan tidak boleh melebihi sisanya. Kelebihan uang harus
            // diinput lewat form Tagihan admin, bukan dari kolektor/teknisi/portal.
            // Dijumlahkan per invoice, bukan per baris: dua baris untuk tagihan
            // yang sama masing-masing "muat" tapi totalnya tetap lebih bayar.
            $invoiceId = (int) $invoice->id;
            $alreadyAppliedToInvoice = $appliedByInvoice[$invoiceId] ?? 0;
            $totalForInvoice = Money::add(Money::add($amount, $useBalance), $alreadyAppliedToInvoice);

            if (Money::greaterThan($totalForInvoice, $invoice->remaining_amount)) {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: nominal tunai ditambah saldo melebihi sisa tagihan. Kelebihan hanya bisa dicatat lewat Tagihan admin.",
                ];

                continue;
            }

            // Satu pelanggan bisa punya beberapa tagihan dalam satu batch, dan
            // saldonya SATU. Dijumlahkan per pelanggan, bukan dicek per baris,
            // supaya tidak ada dua baris yang sama-sama "merasa" saldonya cukup.
            if (Money::greaterThan($useBalance, 0)) {
                $customerId = (int) $invoice->customer_id;
                $alreadyUsed = $balanceUsedByCustomer[$customerId] ?? 0;
                $available = Money::sub($this->balances->balance($invoice->customer), $alreadyUsed);

                if (Money::greaterThan($useBalance, $available)) {
                    $failures[] = [
                        'invoice_id' => $row['invoice_id'],
                        'reason' => "{$invoice->invoice_number}: Saldo pelanggan tidak cukup. Saldo tersedia: Rp ".number_format(max($available, 0), 0, ',', '.').'.',
                    ];

                    continue;
                }

            }

            // Transfer wajib rekening tujuan yang masih aktif — sama dengan
            // PaymentService::resolveActiveBankAccount() di jalur admin.
            if (($row['payment_method'] ?? null) === 'transfer') {
                $bankAccount = ! empty($row['bank_account_id']) ? BankAccount::find($row['bank_account_id']) : null;

                if (! $bankAccount || ! $bankAccount->is_active) {
                    $failures[] = [
                        'invoice_id' => $row['invoice_id'],
                        'reason' => "{$invoice->invoice_number}: pilih rekening tujuan yang aktif untuk metode Transfer.",
                    ];

                    continue;
                }
            }

            // Lebih bayar TIDAK diterima dari batch (lihat cek totalForInvoice di
            // atas). Kelebihan hanya bisa dicatat lewat form Tagihan admin.

            // Metode Lainnya wajib menjelaskan metode apa persisnya —
            // PaymentMethod::requiresDescription().
            if (($row['payment_method'] ?? null) === 'lainnya' && trim((string) ($row['note'] ?? '')) === '') {
                $failures[] = [
                    'invoice_id' => $row['invoice_id'],
                    'reason' => "{$invoice->invoice_number}: metode Lainnya wajib diisi keterangannya.",
                ];
            }

            // Baris lolos semua cek → baru dicatat ke akumulasi.
            if (count($failures) === $failuresBeforeRow) {
                $appliedByInvoice[$invoiceId] = $totalForInvoice;

                if (Money::greaterThan($useBalance, 0)) {
                    $customerId = (int) $invoice->customer_id;
                    $balanceUsedByCustomer[$customerId] = Money::add($balanceUsedByCustomer[$customerId] ?? 0, $useBalance);
                }
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
     * @param  array<int, array{invoice_id: int, amount: float|string, payment_method: string, collected_date: string, note?: string}>  $rows
     * @return array{already_processed: bool, batch_id: int, processed: int, results: array<int, array<string, mixed>>}
     */
    public function record(User $collector, User $actor, string $idempotencyKey, array $rows, CollectorRole $source = CollectorRole::KOLEKTOR): array
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

        [$batchId, $results] = DB::transaction(function () use ($collector, $actor, $rows, $idempotencyKey, $source) {
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
                if (in_array($lockedInvoice->invoice_status->value, ['lunas', 'batal', 'tak_tertagih'], true)) {
                    throw new \RuntimeException("Invoice {$lockedInvoice->invoice_number}: sudah {$lockedInvoice->invoice_status->label()} (berubah sejak form dibuka).");
                }

                // Auto-split: bagian yang menutup tagihan dulu, sisanya (kalau
                // ada) jadi overpay_amount — pola sama persis
                // PaymentService::record() jalur admin (ADHOC-84 §2.5, §4.4).
                // Dipisah di ranah sen (lihat Money::class) supaya "bayar pas"
                // tak melahirkan lebih bayar Rp0,000001 hantu.
                // Uang TUNAI yang diserahkan (di luar saldo). Dipakai juga
                // untuk notifikasi & total setoran — saldo bukan uang fisik.
                $cashReceived = Money::of($row['amount']);
                $useBalance = Money::of($row['use_balance_amount'] ?? 0);
                $totalReceived = Money::add($cashReceived, $useBalance);
                $appliedAmount = Money::min($totalReceived, $lockedInvoice->remaining_amount);
                $overpayAmount = Money::sub($totalReceived, $appliedAmount);

                // Batch tidak boleh menghasilkan lebih bayar sama sekali. validateRows()
                // sudah menolaknya, tapi di sini yang otoritatif: kalau sisa tagihan
                // berubah sejak form dibuka (dibayar jalur lain), jangan diam-diam
                // masuk saldo — gagalkan seluruh batch.
                if (Money::greaterThan($overpayAmount, 0)) {
                    throw new \RuntimeException("Invoice {$lockedInvoice->invoice_number}: nominal melebihi sisa tagihan. Batch tidak menerima lebih bayar — kelebihan hanya bisa dicatat lewat Tagihan admin.");
                }

                $description = trim((string) ($row['note'] ?? ''));
                if ($row['payment_method'] === 'lainnya' && $description === '') {
                    throw new \RuntimeException("Invoice {$lockedInvoice->invoice_number}: metode Lainnya wajib diisi keterangannya.");
                }

                // Sama dengan PaymentService::record(): tanpa uang tunai tapi
                // saldo menutup, pembayaran tercatat sebagai metode Saldo.
                $isFullSaldo = Money::isZero($cashReceived) && Money::greaterThan($useBalance, 0);
                $method = $isFullSaldo ? PaymentMethod::SALDO : PaymentMethod::from($row['payment_method']);

                // Snapshot rekening dari master, bukan dari input — sama dengan
                // jalur admin (ADHOC-95).
                $bankAccount = $method->requiresBankDetails() ? BankAccount::find($row['bank_account_id']) : null;

                // 'Batch kolektor: ...' dipertahankan sebagai penanda sumber
                // (dipakai pembaca lain yang mengharap format ini) — keterangan
                // Lainnya ditambahkan sesudahnya, bukan menggantikannya.
                $note = 'Batch '.strtolower($source->label()).': '.$collector->name;
                if ($description !== '') {
                    $note .= ' — '.$description;
                }

                if ($method->requiresBankDetails() && ! $bankAccount) {
                    throw new \RuntimeException("Invoice {$lockedInvoice->invoice_number}: pilih rekening tujuan untuk metode Transfer.");
                }

                $payment = Payment::create([
                    'payment_number' => Payment::generatePaymentNumber($lockedInvoice, $appliedAmount),
                    'invoice_id' => $lockedInvoice->id,
                    'payment_batch_id' => $batch->id,
                    'customer_id' => $lockedInvoice->customer_id,
                    'pop_id' => $lockedInvoice->pop_id,
                    'payment_date' => now()->format('Y-m-d'),
                    'collected_date' => $row['collected_date'],
                    'payment_method' => $method->value,
                    // Snapshot dari master (bukan input), sama seperti PaymentService.
                    'bank_account_id' => $bankAccount?->id,
                    'bank_name' => $bankAccount?->bank_name,
                    'account_number' => $bankAccount?->account_number,
                    'sender_name' => $method->requiresSenderName() ? $this->nullIfBlank($row['sender_name'] ?? null) : null,
                    'amount' => $appliedAmount,
                    'balance_used_amount' => $useBalance,
                    'overpay_amount' => Money::isZero($overpayAmount) ? null : $overpayAmount,
                    'received_by' => $actor->id,
                    'collected_by' => $collector->id,
                    'collected_by_role' => $source->value,
                    'payment_status' => PaymentStatus::VALID->value,
                    'note' => $note,
                ]);

                // Saldo didebit SETELAH payment ada (debit() butuh $payment
                // sebagai sumber mutasi). Saldo tak cukup → InvalidArgumentException
                // → transaksi batch rollback total (record() → recordBatch() → 422).
                if (Money::greaterThan($useBalance, 0)) {
                    $this->balances->debit(
                        $lockedInvoice->customer,
                        $useBalance,
                        $payment,
                        source: BalanceMutationSource::PAKAI_MANUAL,
                    );
                }

                if (Money::greaterThan($overpayAmount, 0)) {
                    // Sumber kredit mengikuti PaymentService::record() — overpay
                    // di invoice AWAL = titip saldo di muka (ADHOC-92).
                    $creditSource = $lockedInvoice->invoice_type === InvoiceType::AWAL
                        ? BalanceMutationSource::BAYAR_DI_MUKA
                        : BalanceMutationSource::KELEBIHAN_BAYAR;

                    $this->balances->credit($lockedInvoice->customer, $overpayAmount, $payment, source: $creditSource);
                }

                $lockedInvoice->recalculateFromPayments();

                // Kunjungan "bayar" diturunkan dari payment yang BENAR-BENAR
                // tersimpan, tak pernah dari input manual (§12). Ditulis di
                // dalam transaksi yang sama supaya mustahil ada payment tanpa
                // jejak kunjungan — kalau dua-duanya bisa gagal terpisah,
                // laporan aging bohong tepat di baris yang paling penting.
                // Log kunjungan HANYA untuk kolektor: buku kunjungan adalah
                // daftar kerja penagihan (aging) miliknya. Teknisi tidak
                // punya daftar itu — pembayarannya cukup tercatat di payment.
                if ($source === CollectorRole::KOLEKTOR) {
                    $this->visits->recordPaid(
                        $collector,
                        (int) $lockedInvoice->customer_id,
                        $payment->id,
                        $row['collected_date'],
                    );
                }

                $results[] = [
                    'invoice_id' => $lockedInvoice->id,
                    'customer_id' => $lockedInvoice->customer_id,
                    'payment_id' => $payment->id,
                    'invoice_status' => $lockedInvoice->invoice_status->value,
                    'remaining_amount' => (float) $lockedInvoice->remaining_amount,
                    'pop_id' => $lockedInvoice->pop_id,
                    // Total uang DITERIMA (applied + overpay) — bukan cuma
                    // bagian yang menutup invoice, supaya notifikasi/setoran
                    // admin menghitung uang fisik yang benar-benar masuk.
                    'amount' => $cashReceived,
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
     * Sama dengan PaymentService::nullIfBlank() — string kosong dari form
     * disimpan sebagai NULL, bukan "" yang mengotori laporan nama pengirim.
     */
    private function nullIfBlank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
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
