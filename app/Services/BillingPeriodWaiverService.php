<?php

namespace App\Services;

use App\Enums\BillingWaiverSource;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Events\InvoiceStatusUpdated;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerBillingWaiver;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Membebaskan tagihan langganan per periode (ADHOC-87) — mengganti sebagian
 * fungsi prorate: pemakaian yang tidak terjadi (koneksi mati, cuti) dikeluarkan
 * dari tagihan per bulan penuh oleh admin, bukan dihitung harian (ADHOC-69
 * sengaja tanpa prorate).
 *
 * Rancangan awal (§4.2): "satu kemampuan, dua pintu" (Request Putus
 * Langganan + Cuti Berlangganan, beda `BillingWaiverSource`). Disederhanakan
 * 2026-09-24 (keputusan user) jadi SATU pintu saja —
 * `CustomerBillingWaiverController` ("Bebaskan Tagihan Periode" di Detail
 * Pelanggan), dipakai independen dari terminasi. `BillingWaiverSource::TERMINATION`
 * dipertahankan di enum (masih berguna sebagai model data & kasus uji G1-G3)
 * tapi TIDAK ADA lagi pemanggil yang memproduksinya — jangan heran kalau
 * cuma `LEAVE` yang muncul di data nyata.
 *
 * SEMUA aturan (G1-G7) ditegakkan di sini, dipakai bareng oleh
 * `eligiblePeriods()` (UI) dan `waive()` (submit) supaya keduanya tidak
 * pernah menyimpang.
 *
 * Rancangan: docs/plan/billing/analisa-rancangan-request-deaktivasi-bebas-tagihan-periode.md
 */
class BillingPeriodWaiverService
{
    /**
     * Berapa bulan KE DEPAN yang ditawarkan di dropdown Cuti Berlangganan
     * untuk periode yang belum terbit. Bukan batas keras (G3 tidak membatasi
     * periode masa depan) — cuma supaya dropdown tidak menampilkan ratusan
     * bulan ke depan tanpa guna.
     */
    private const LEAVE_FUTURE_WINDOW_MONTHS = 3;

    /**
     * Daftar periode + status eligibilitas untuk mengisi dropdown form —
     * sumber tunggal supaya UI (kenapa suatu periode nonaktif) dan validasi
     * server (`waive()`) memakai aturan yang SAMA persis.
     *
     * @return Collection<int, array{billing_period: string, invoice: ?Invoice, eligible: bool, reason: ?string}>
     */
    public function eligiblePeriods(Customer $customer, BillingWaiverSource $source): Collection
    {
        $existingInvoices = Invoice::where('customer_id', $customer->id)
            ->where('invoice_type', InvoiceType::BULANAN->value)
            ->orderByDesc('billing_period')
            ->limit(6)
            ->get();

        $rows = collect();

        foreach ($existingInvoices as $invoice) {
            [$eligible, $reason] = $this->evaluatePeriod($customer, $invoice->billing_period, $invoice, $source);
            $rows->push([
                'billing_period' => $invoice->billing_period,
                'invoice' => $invoice,
                'eligible' => $eligible,
                'reason' => $reason,
            ]);
        }

        // Periode ke depan yang belum terbit cuma relevan buat Cuti — Request
        // Putus Langganan cuma membebaskan tagihan yang SUDAH ada (§2 tabel
        // rancangan: "Periode yang boleh dipilih" beda antara dua pintu).
        if ($source === BillingWaiverSource::LEAVE) {
            $existingPeriods = $existingInvoices->pluck('billing_period')->all();

            for ($i = 0; $i <= self::LEAVE_FUTURE_WINDOW_MONTHS; $i++) {
                $period = now()->addMonths($i)->format('Y-m');

                if (in_array($period, $existingPeriods, true)) {
                    continue;
                }

                [$eligible, $reason] = $this->evaluatePeriod($customer, $period, null, $source);
                $rows->push([
                    'billing_period' => $period,
                    'invoice' => null,
                    'eligible' => $eligible,
                    'reason' => $reason,
                ]);
            }
        }

        return $rows->sortBy('billing_period')->values();
    }

    /**
     * @param  list<string>  $periods
     * @return Collection<int, CustomerBillingWaiver>
     */
    public function waive(Customer $customer, array $periods, string $reason, BillingWaiverSource $source, User $actor): Collection
    {
        $periods = array_values(array_unique(array_filter($periods, fn ($p) => trim((string) $p) !== '')));

        if ($periods === []) {
            throw ValidationException::withMessages([
                'periods' => 'Pilih minimal satu periode yang ingin dibebaskan.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Catatan wajib diisi.',
            ]);
        }

        // G6 (disederhanakan 2026-09-24) — "Bebaskan Tagihan Periode" sekarang
        // SATU pintu, dipakai independen dari terminasi (CustomerTerminationService
        // TIDAK lagi memanggil waive() sama sekali — form Request Putus
        // Langganan cuma alasan+denda). Admin boleh membebaskan tagihan
        // pelanggan yang masih aktif/isolir MAUPUN yang sudah putus (mis.
        // lupa dibebaskan sebelum diputus) — cuma status "belum pernah jadi
        // pelanggan berlangganan" (registered/rejected/dst, tidak pernah
        // punya tagihan bulanan) yang ditolak.
        if (! in_array($customer->status, ['active', 'suspended', 'terminated'], true)) {
            throw ValidationException::withMessages([
                'periods' => 'Pembebasan tagihan cuma berlaku untuk pelanggan yang pernah berlangganan aktif (aktif, terisolir, atau sudah putus).',
            ]);
        }

        $touchedInvoices = [];

        $created = DB::transaction(function () use ($customer, $periods, $reason, $source, $actor, &$touchedInvoices) {
            $rows = collect();

            foreach ($periods as $period) {
                // Kunci baris invoice-nya (kalau ada) SEBELUM re-validasi —
                // invoice bisa saja baru saja dibayar kolektor di sela-sela
                // form ini dibuka (§4.2 rancangan). SENGAJA tidak difilter
                // `invoice_type=bulanan` di query: kalau satu-satunya invoice
                // di periode ini bertipe lain (mis. AWAL), evaluatePeriod()
                // yang harus menolaknya lewat G1 — bukan diam-diam dianggap
                // "belum ada invoice" dan lolos sebagai periode masa depan.
                $invoice = Invoice::where('customer_id', $customer->id)
                    ->where('billing_period', $period)
                    ->orderByRaw("CASE WHEN invoice_type = 'bulanan' THEN 0 ELSE 1 END")
                    ->lockForUpdate()
                    ->first();

                [$eligible, $ineligibleReason] = $this->evaluatePeriod($customer, $period, $invoice, $source);

                if (! $eligible) {
                    throw ValidationException::withMessages([
                        'periods' => "Periode {$period}: {$ineligibleReason}",
                    ]);
                }

                $waiver = CustomerBillingWaiver::create([
                    'customer_id' => $customer->id,
                    'billing_period' => $period,
                    'source' => $source->value,
                    'reason' => $reason,
                    'invoice_id' => $invoice?->id,
                    'created_by' => $actor->id,
                ]);

                if ($invoice) {
                    // `total_amount` TIDAK diubah — jejak nominal asli tetap
                    // ada. `remaining_amount` di-nol-kan sebagai sabuk
                    // pengaman untuk query yang menjumlahkan kolom itu tanpa
                    // filter status `batal` (§4.2).
                    $invoice->update([
                        'invoice_status' => InvoiceStatus::BATAL->value,
                        'remaining_amount' => 0,
                    ]);
                    $touchedInvoices[] = $invoice;
                }

                $rows->push($waiver);
            }

            return $rows;
        });

        // Dispatch SETELAH commit (§4.2) — konsisten dengan semua perubahan
        // status invoice lain (recalculateFromPayments(), InvoiceWriteOffService).
        foreach ($touchedInvoices as $invoice) {
            InvoiceStatusUpdated::dispatch($invoice);
        }

        return $created;
    }

    /**
     * Cabut pembebasan = hapus baris (riwayat ada di audit log lewat
     * `RecordsAuditLogs` pada model). Invoice yang sudah `batal` TIDAK
     * dihidupkan lagi (K6) — kalau perlu ditagih ulang, jalankan
     * `billing:generate-monthly-invoices --period=…` setelah ini.
     */
    public function revoke(CustomerBillingWaiver $waiver, User $actor, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pencabutan wajib diisi.',
            ]);
        }

        DB::transaction(function () use ($waiver, $reason, $actor) {
            $locked = CustomerBillingWaiver::whereKey($waiver->id)->lockForUpdate()->firstOrFail();

            // RecordsAuditLogs otomatis menulis baris 'delete' dengan snapshot
            // atribut lama saat delete() di bawah dieksekusi — baris manual
            // ini MELENGKAPI itu dengan alasan pencabutan (bukan kolom
            // `revoked_reason` di model, sesuai K6: tanpa kolom revoked_*).
            AuditLog::create([
                'user_id' => $actor->id,
                'module' => 'Pembebasan Tagihan',
                'action' => 'revoke',
                'auditable_type' => CustomerBillingWaiver::class,
                'auditable_id' => $locked->id,
                'old_values' => $locked->only(['customer_id', 'billing_period', 'source', 'reason', 'invoice_id']),
                'new_values' => ['revoke_reason' => $reason],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);

            $locked->delete();
        });
    }

    /**
     * Satu-satunya tempat aturan G1-G3/G7 dievaluasi — dipakai `eligiblePeriods()`
     * (dropdown UI) dan `waive()` (validasi ulang di dalam transaksi).
     *
     * @return array{0: bool, 1: ?string}
     */
    private function evaluatePeriod(Customer $customer, string $period, ?Invoice $invoice, BillingWaiverSource $source): array
    {
        // G7 — periode yang sudah punya waiver ditolak (idempoten, bukan 500).
        $alreadyWaived = CustomerBillingWaiver::where('customer_id', $customer->id)
            ->where('billing_period', $period)
            ->exists();

        if ($alreadyWaived) {
            return [false, 'Sudah dibebaskan sebelumnya'];
        }

        if ($invoice) {
            // G1 — hanya tagihan langganan BULANAN. Aktivasi/Tagihan
            // Manual/denda/reaktivasi tidak boleh dibebaskan lewat jalur ini.
            if ($invoice->invoice_type?->value !== InvoiceType::BULANAN->value) {
                return [false, 'Bukan tagihan langganan bulanan'];
            }

            if ($invoice->invoice_status?->value === InvoiceStatus::BATAL->value) {
                return [false, 'Sudah dibatalkan'];
            }

            if ($invoice->invoice_status?->value === InvoiceStatus::TAK_TERTAGIH->value) {
                return [false, 'Sudah dihapus buku (tak tertagih)'];
            }

            // G2 — hanya invoice TANPA pembayaran valid.
            if (! Money::isZero($invoice->paid_amount)) {
                $reason = match ($invoice->invoice_status?->value) {
                    InvoiceStatus::LUNAS->value => 'Sudah lunas',
                    InvoiceStatus::SEBAGIAN->value => 'Sudah sebagian dibayar',
                    default => 'Sudah ada pembayaran',
                };

                return [false, $reason];
            }
        }

        // G3 — jendela periode: bulan berjalan & N bulan sebelumnya (config),
        // plus periode ke depan yang belum terbit KHUSUS Cuti.
        $currentPeriod = now()->format('Y-m');
        $minPeriod = now()->subMonths((int) config('billing.waiver_backdate_window_months'))->format('Y-m');

        if ($period < $minPeriod) {
            return [false, 'Di luar jendela mundur ('.config('billing.waiver_backdate_window_months').' bulan)'];
        }

        if ($period > $currentPeriod && $source !== BillingWaiverSource::LEAVE) {
            return [false, 'Periode masa depan cuma bisa dibebaskan lewat Cuti Berlangganan'];
        }

        return [true, null];
    }
}
