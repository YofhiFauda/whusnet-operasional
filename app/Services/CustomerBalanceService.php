<?php

namespace App\Services;

use App\Enums\BalanceMutationSource;
use App\Enums\CustomerBalanceMutationType;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\CustomerBalanceMutation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Saldo pelanggan (lebih bayar yang bisa dipakai di pembayaran berikutnya).
 *
 * Saldo = SUM(credit) - SUM(debit), SELALU DITURUNKAN dari
 * `customer_balance_mutations` — tak ada kolom `customers.balance` yang
 * di-increment/decrement. Sama alasannya dengan [[CollectorBalanceService]]
 * dan [[AdminCashBalanceService]]: kolom begitu berhenti benar begitu satu
 * payment sumbernya di-reject, dan angka uang yang bohong tak punya alarm.
 *
 * `overpay_amount` di `payments` TETAP ada dan TETAP diisi (catatan
 * informatif per-payment) — tabel ini yang menjadikannya saldo AKTIF yang
 * bisa dipakai. Dua hal berbeda, jangan disatukan.
 */
class CustomerBalanceService
{
    /**
     * Saldo berjalan pelanggan saat ini.
     */
    public function balance(Customer $customer): float
    {
        $credit = Money::of(
            CustomerBalanceMutation::query()
                ->where('customer_id', $customer->id)
                ->where('type', CustomerBalanceMutationType::CREDIT->value)
                ->sum('amount')
        );

        $debit = Money::of(
            CustomerBalanceMutation::query()
                ->where('customer_id', $customer->id)
                ->where('type', CustomerBalanceMutationType::DEBIT->value)
                ->sum('amount')
        );

        return Money::sub($credit, $debit);
    }

    /**
     * Saldo berjalan banyak pelanggan sekaligus — satu query agregat untuk
     * seluruh baris tabel bayar, bukan `balance()` per baris (N+1).
     *
     * @param  array<int, int>  $customerIds
     * @return array<int, float> saldo per customer_id (0 kalau belum punya mutasi)
     */
    public function balancesForCustomers(array $customerIds): array
    {
        $customerIds = array_values(array_unique(array_map('intval', $customerIds)));

        if ($customerIds === []) {
            return [];
        }

        $totals = CustomerBalanceMutation::query()
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, type, SUM(amount) as total')
            ->groupBy('customer_id', 'type')
            ->get();

        $credit = [];
        $debit = [];
        foreach ($totals as $row) {
            $key = (int) $row->customer_id;

            // `type` di-cast ke enum oleh model (`casts()`), jadi dibanding enum-nya,
            // BUKAN string — string compare selalu false → semua kredit terbaca debit.
            if ($row->type === CustomerBalanceMutationType::CREDIT) {
                $credit[$key] = Money::of($row->total);
            } else {
                $debit[$key] = Money::of($row->total);
            }
        }

        $result = [];
        foreach ($customerIds as $customerId) {
            $result[$customerId] = Money::sub($credit[$customerId] ?? 0, $debit[$customerId] ?? 0);
        }

        return $result;
    }

    /**
     * Saldo berjalan dengan baris ledger customer ini DIKUNCI
     * (`lockForUpdate`) — dipanggil di dalam transaction yang sama dengan
     * penguncian invoice, supaya dua pembayaran simultan yang sama-sama
     * memakai saldo tak lolos berbarengan (satu harus menunggu baris
     * ledger yang lain rilis).
     *
     * Kalau pelanggan belum pernah punya mutasi sama sekali, tak ada baris
     * untuk dikunci — itu sudah pasti berarti saldo 0, aman tanpa lock.
     *
     * PUBLIC (bukan cuma dipakai `debit()`/`applyToOpenInvoices()` di sini) —
     * `PaymentService::revise()` (ADHOC-108) juga memanggilnya untuk mengecek
     * kecukupan saldo NILAI BARU sebelum `applyCorrection()` menulis delta,
     * di dalam transaction yang sama yang sudah mengunci payment & invoice.
     */
    public function lockedBalance(Customer $customer): float
    {
        CustomerBalanceMutation::query()
            ->where('customer_id', $customer->id)
            ->lockForUpdate()
            ->get(['id']);

        return $this->balance($customer);
    }

    /**
     * Catat kredit — dipanggil saat sebuah payment menghasilkan lebih bayar
     * (`overpay_amount > 0`). Payment sumbernya WAJIB terisi supaya kredit
     * ini bisa dibalik kalau payment tsb di-reject.
     */
    public function credit(
        Customer $customer,
        float $amount,
        Payment $sourcePayment,
        ?string $note = null,
        BalanceMutationSource $source = BalanceMutationSource::KELEBIHAN_BAYAR,
    ): CustomerBalanceMutation {
        if (Money::isZero($amount) || Money::lessThan($amount, 0)) {
            throw new InvalidArgumentException('Nominal kredit saldo pelanggan harus positif.');
        }

        return CustomerBalanceMutation::create([
            'customer_id' => $customer->id,
            'type' => CustomerBalanceMutationType::CREDIT->value,
            'source' => $source->value,
            'amount' => Money::of($amount),
            'payment_id' => $sourcePayment->id,
            'pop_id' => $sourcePayment->pop_id,
            'created_by' => auth()->id(),
            'note' => $note ?? "Lebih bayar dari {$sourcePayment->payment_number}",
        ]);
    }

    /**
     * Catat pemakaian saldo — dipanggil saat pelanggan memakai saldonya
     * untuk menutup sebagian/seluruh payment baru. WAJIB dipanggil di
     * dalam transaction yang sama dengan lock invoice
     * (lihat PaymentService::record()) supaya `lockedBalance()` efektif.
     *
     * @throws InvalidArgumentException kalau saldo tak cukup.
     */
    public function debit(
        Customer $customer,
        float $amount,
        Payment $consumingPayment,
        ?string $note = null,
        BalanceMutationSource $source = BalanceMutationSource::PAKAI_MANUAL,
    ): CustomerBalanceMutation {
        if (Money::isZero($amount) || Money::lessThan($amount, 0)) {
            throw new InvalidArgumentException('Nominal pemakaian saldo pelanggan harus positif.');
        }

        $available = $this->lockedBalance($customer);

        if (Money::greaterThan($amount, $available)) {
            throw new InvalidArgumentException(
                "Saldo pelanggan tidak cukup: tersedia Rp {$available}, diminta Rp {$amount}."
            );
        }

        return CustomerBalanceMutation::create([
            'customer_id' => $customer->id,
            'type' => CustomerBalanceMutationType::DEBIT->value,
            'source' => $source->value,
            'amount' => Money::of($amount),
            'payment_id' => $consumingPayment->id,
            'pop_id' => $consumingPayment->pop_id,
            'created_by' => auth()->id(),
            'note' => $note ?? "Dipakai untuk {$consumingPayment->payment_number}",
        ]);
    }

    /**
     * Catat kredit yang BUKAN turunan dari payment — dipakai
     * `CustomerPackageService` saat downgrade paket menghasilkan deposit
     * (`sudah_dibayar` periode ini > tagihan baru hasil prorate). `payment_id`
     * nullable di skema tabel ini sudah dari awal untuk kasus non-payment
     * semacam ini (lihat komentar migration) — kalau perlu dibalik (ganti
     * paket salah input), balikkan lewat `debit()` biasa, BUKAN
     * `reverseCreditForPayment()` yang semantiknya "payment sumber ditolak".
     */
    public function creditWithoutPayment(
        Customer $customer,
        float $amount,
        int $popId,
        string $note,
        ?BalanceMutationSource $source = null,
    ): CustomerBalanceMutation {
        if (Money::isZero($amount) || Money::lessThan($amount, 0)) {
            throw new InvalidArgumentException('Nominal kredit saldo pelanggan harus positif.');
        }

        return CustomerBalanceMutation::create([
            'customer_id' => $customer->id,
            'type' => CustomerBalanceMutationType::CREDIT->value,
            'source' => $source?->value,
            'amount' => Money::of($amount),
            'payment_id' => null,
            'pop_id' => $popId,
            'created_by' => auth()->id(),
            'note' => $note,
        ]);
    }

    /**
     * Koreksi saldo dari Edit Pembayaran (ADHOC-108, K7) — SATU baris DELTA
     * per revisi, BUKAN balik-lalu-terapkan-ulang seperti
     * `reverseCreditForPayment()`/`reverseDebitForPayment()` (keduanya
     * dibuat untuk Kembalikan: sekali-jalan, dan akan bentrok unique index
     * kalau dipanggil dua kali untuk payment yang sama — lihat
     * docs/plan/billing/rancangan-edit-pembayaran-penuh.md §F2).
     *
     * ```
     * Δoverpay = overpayBaru − overpayLama
     * Δpakai   = saldoDipakaiBaru − saldoDipakaiLama
     * net      = Δoverpay − Δpakai   // efek bersih ke saldo pelanggan
     * ```
     * `net > 0` → CREDIT sebesar net; `net < 0` → DEBIT sebesar |net|;
     * `net = 0` → tidak menulis apa pun. Nilai LAMA diambil dari
     * kolom `payments` (parameter, bukan dibaca ulang dari ledger) supaya
     * tetap benar untuk payment lama/backfill yang ledger-nya tak lengkap.
     *
     * SENGAJA tidak lewat `debit()` (yang menolak kalau saldo tak cukup):
     * pembalikan overpay yang sudah terlanjur dipakai pelanggan BOLEH
     * membuat saldo negatif (piutang terlihat) — filosofi sama dengan
     * `reverseCreditForPayment()`. Kecukupan saldo untuk NILAI BARU yang
     * mau dipakai divalidasi terpisah oleh pemanggil (PaymentService::revise())
     * SEBELUM method ini dipanggil.
     *
     * Wajib dipanggil di dalam transaction yang sudah mengunci baris
     * payment ini (mencegah dua koreksi berebut nomor `revision`).
     */
    public function applyCorrection(
        Payment $payment,
        float $oldOverpayAmount,
        float $oldBalanceUsedAmount,
        float $newOverpayAmount,
        float $newBalanceUsedAmount,
    ): ?CustomerBalanceMutation {
        $deltaOverpay = Money::sub($newOverpayAmount, $oldOverpayAmount);
        $deltaPakai = Money::sub($newBalanceUsedAmount, $oldBalanceUsedAmount);
        $net = Money::sub($deltaOverpay, $deltaPakai);

        if (Money::isZero($net)) {
            return null;
        }

        $customer = $payment->customer;

        if (! $customer) {
            // Tak mungkin terjadi dalam praktik (use_balance_amount/overpay
            // mensyaratkan invoice ber-customer) — dijaga eksplisit supaya
            // gagal jelas alih-alih menulis mutasi tanpa pemilik saldo.
            throw new InvalidArgumentException('Payment ini tidak terhubung pelanggan — saldo tidak bisa dikoreksi.');
        }

        // Revisi ke-berapa untuk payment ini — dihitung dari baris KOREKSI
        // yang sudah ada (lintas type: net cuma pernah CREDIT atau DEBIT per
        // panggilan, tak pernah dua-duanya sekaligus, jadi satu urutan global
        // per payment sudah cukup dan tak pernah bentrok unique index).
        $revision = (int) (CustomerBalanceMutation::query()
            ->where('payment_id', $payment->id)
            ->where('source', BalanceMutationSource::KOREKSI->value)
            ->max('revision') ?? 0) + 1;

        $type = Money::greaterThan($net, 0)
            ? CustomerBalanceMutationType::CREDIT
            : CustomerBalanceMutationType::DEBIT;

        return CustomerBalanceMutation::create([
            'customer_id' => $customer->id,
            'type' => $type->value,
            'source' => BalanceMutationSource::KOREKSI->value,
            'revision' => $revision,
            'amount' => abs($net),
            'payment_id' => $payment->id,
            'pop_id' => $payment->pop_id,
            'created_by' => auth()->id(),
            'note' => "Koreksi Edit Pembayaran {$payment->payment_number} (revisi ke-{$revision}).",
        ]);
    }

    /**
     * Balikkan kredit yang sudah tercatat dari payment ini, dipanggil saat
     * payment sumber overpay-nya di-reject (PaymentController::reject()).
     * Idempotent: kredit yang sudah pernah dibalik tak dibalik dua kali.
     *
     * SENGAJA tidak lewat debit() (yang menolak kalau saldo tak cukup):
     * kalau kreditnya sudah keburu dipakai pelanggan sebelum payment
     * sumbernya ditolak, saldo boleh jatuh negatif — itu justru piutang
     * yang harus terlihat, sama seperti "Kurang Setor" di sisi kolektor,
     * bukan error yang harus disembunyikan.
     */
    public function reverseCreditForPayment(Payment $payment): void
    {
        $credit = CustomerBalanceMutation::query()
            ->where('payment_id', $payment->id)
            ->where('type', CustomerBalanceMutationType::CREDIT->value)
            ->where('source', '!=', BalanceMutationSource::PEMBATALAN->value)
            ->first();

        if (! $credit) {
            return;
        }

        $alreadyReversed = CustomerBalanceMutation::query()
            ->where('payment_id', $payment->id)
            ->where('type', CustomerBalanceMutationType::DEBIT->value)
            ->where('source', BalanceMutationSource::PEMBATALAN->value)
            ->where('note', 'like', 'Pembalikan kredit%')
            ->exists();

        if ($alreadyReversed) {
            return;
        }

        CustomerBalanceMutation::create([
            'customer_id' => $credit->customer_id,
            'type' => CustomerBalanceMutationType::DEBIT->value,
            'source' => BalanceMutationSource::PEMBATALAN->value,
            'amount' => $credit->amount,
            'payment_id' => $payment->id,
            'pop_id' => $credit->pop_id,
            'created_by' => auth()->id(),
            'note' => "Pembalikan kredit — payment {$payment->payment_number} ditolak.",
        ]);
    }

    /**
     * Balikkan (kembalikan) saldo yang dipakai payment ini, dipanggil saat
     * payment yang MEMAKAI saldo (`balance_used_amount > 0`) di-reject
     * (G5, PaymentController::reject()). Kebalikan dari reverseCreditForPayment:
     * di sini yang dibalik adalah DEBIT (pemakaian), jadi baris pembaliknya
     * berupa CREDIT — saldo pelanggan naik lagi sebesar yang dulu dipakai.
     * Idempotent, sama pola reverseCreditForPayment.
     */
    public function reverseDebitForPayment(Payment $payment): void
    {
        $debit = CustomerBalanceMutation::query()
            ->where('payment_id', $payment->id)
            ->where('type', CustomerBalanceMutationType::DEBIT->value)
            ->where('source', '!=', BalanceMutationSource::PEMBATALAN->value)
            ->first();

        if (! $debit) {
            return;
        }

        $alreadyReversed = CustomerBalanceMutation::query()
            ->where('payment_id', $payment->id)
            ->where('type', CustomerBalanceMutationType::CREDIT->value)
            ->where('source', BalanceMutationSource::PEMBATALAN->value)
            ->where('note', 'like', 'Pembalikan pemakaian saldo%')
            ->exists();

        if ($alreadyReversed) {
            return;
        }

        CustomerBalanceMutation::create([
            'customer_id' => $debit->customer_id,
            'type' => CustomerBalanceMutationType::CREDIT->value,
            'source' => BalanceMutationSource::PEMBATALAN->value,
            'amount' => $debit->amount,
            'payment_id' => $payment->id,
            'pop_id' => $debit->pop_id,
            'created_by' => auth()->id(),
            'note' => "Pembalikan pemakaian saldo — payment {$payment->payment_number} ditolak.",
        ]);
    }

    /**
     * Auto-pakai saldo pelanggan ke tagihan BULANAN terbuka, FIFO periode
     * terlama dulu (KEPUTUSAN 2026-09-21). Dipanggil dari
     * GenerateMonthlyInvoicesCommand (dalam transaksi yang sama dengan
     * pembuatan invoice) dan dari command catch-up `billing:apply-balance`.
     *
     * Satu Payment (method SALDO) dibuat PER invoice yang tersentuh, bukan
     * satu payment lintas-invoice — supaya `installmentContext()`/riwayat
     * cicilan tetap benar per invoice (KEPUTUSAN "bentuk catatan").
     *
     * Hanya invoice `BULANAN` — AWAL/INSIDENTAL/MANUAL tetap
     * manual (asumsi rancangan §3.1 poin 1). `Invoice::OUTSTANDING_STATUSES`
     * (belum_dibayar/sebagian) otomatis mengecualikan batal & tak_tertagih.
     *
     * @return list<Payment> payment SALDO yang baru dibuat
     */
    public function applyToOpenInvoices(Customer $customer): array
    {
        return DB::transaction(function () use ($customer): array {
            $created = [];

            $saldo = $this->lockedBalance($customer);

            if (Money::isZero($saldo) || Money::lessThan($saldo, 0)) {
                return $created;
            }

            $openInvoices = Invoice::query()
                ->where('customer_id', $customer->id)
                ->where('invoice_type', InvoiceType::BULANAN->value)
                ->whereIn('invoice_status', Invoice::OUTSTANDING_STATUSES)
                ->orderBy('billing_period')
                ->lockForUpdate()
                ->get();

            foreach ($openInvoices as $invoice) {
                if (Money::isZero($saldo) || Money::lessThan($saldo, 0)) {
                    break;
                }

                $remaining = Money::of($invoice->remaining_amount);

                if (Money::isZero($remaining)) {
                    continue;
                }

                $pakai = Money::min($saldo, $remaining);

                if (Money::isZero($pakai)) {
                    continue;
                }

                // Urutan ke berapa untuk invoice ini — invoice `sebagian` bisa
                // disentuh auto-pay lebih dari sekali (saldo baru masuk lagi
                // sebelum invoice ini lunas/berganti admin/kolektor yang
                // melunasi sisanya). `idempotency_key` per invoice SAJA akan
                // bertabrakan pada sentuhan kedua tanpa nomor urut ini. Urutan
                // diambil dari NumberSequenceService (atomik), bukan count()+1.
                $urutan = app(NumberSequenceService::class)->autoSaldoOrdinal($invoice);

                $payment = Payment::create([
                    'payment_number' => Payment::generatePaymentNumber($invoice, $pakai),
                    'idempotency_key' => "auto-saldo:{$invoice->id}:{$urutan}",
                    'invoice_id' => $invoice->id,
                    'customer_id' => $customer->id,
                    'pop_id' => $invoice->pop_id,
                    'payment_date' => now()->format('Y-m-d'),
                    'payment_method' => PaymentMethod::SALDO->value,
                    'amount' => $pakai,
                    'balance_used_amount' => $pakai,
                    'received_by' => null,
                    'payment_status' => PaymentStatus::VALID->value,
                    'note' => 'Dibayar otomatis dari Saldo Pelanggan (ADHOC-92).',
                ]);

                $this->debit($customer, $pakai, $payment, source: BalanceMutationSource::PAKAI_OTOMATIS);

                $invoice->recalculateFromPayments();

                $saldo = Money::sub($saldo, $pakai);
                $created[] = $payment;
            }

            return $created;
        });
    }
}
