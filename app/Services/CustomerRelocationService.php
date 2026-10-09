<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aturan keuangan pindah Cabang (pop_id) pelanggan — satu sumber untuk guard
 * validasi Edit, guard observer, pemindahan tagihan, dan keterangan di form
 * (ADHOC-107 R4; keputusan user 2026-09-28, K6/K7/K8 di
 * docs/plan/rancangan-pindah-pop-lanjutan.md).
 *
 * Garis batasnya BULAN BERJALAN — sama dengan garis kunci buku sistem:
 * periode dikunci menurut kalender (BookPeriod::isLocked()) dan bulan
 * berjalan tidak pernah terkunci. Maka:
 *
 * - PENGHALANG (wajib lunas dulu, pindah ditolak):
 *   - piutang = outstanding dengan billing_period < bulan berjalan
 *     (definisi yang sama dengan Invoice::scopePiutang(), K6);
 *   - tagihan bulan berjalan/sesudahnya yang sudah dicicil (`sebagian`) —
 *     kalau ikut pindah, cicilannya tetap tercatat di cabang lama sementara
 *     tagihannya di cabang baru, dan laporan dua cabang tidak sinambung (K8);
 *   - tagihan `belum_dibayar` yang ternyata sudah punya baris pembayaran
 *     (data tidak konsisten) — alasan yang sama dengan `sebagian`;
 *   (invoices.billing_period NOT NULL di skema, jadi tidak ada tagihan tanpa
 *   periode yang perlu ditangani.)
 * - IKUT PINDAH: tagihan `belum_dibayar` tanpa pembayaran dengan
 *   billing_period ≥ bulan berjalan — belum ada uang masuk, periodenya
 *   belum terkunci, dan pembayarannya nanti tercatat di cabang baru
 *   (`payments.pop_id = invoice.pop_id`).
 * - TIDAK PERNAH DISENTUH: tagihan lunas/batal/write-off, baris payments,
 *   mutasi saldo (saldo lebih bayar terbawa ke cabang baru, K7).
 */
class CustomerRelocationService
{
    /**
     * Cuma pembayaran `valid` yang dihitung — sama dengan laporan bulanan
     * (CollectorMonthlyReportService::countedAsOf()). Pembayaran `ditolak`
     * bukan uang yang diterima; kalau ikut dihitung, pelanggan bisa tertahan
     * pindah selamanya gara-gara transaksi yang sudah dibatalkan.
     */
    private static function validPayment(Builder $query): void
    {
        $query->where('payment_status', PaymentStatus::VALID->value);
    }

    public static function currentPeriod(): string
    {
        // Sama persis dengan Invoice::scopePiutang() — jangan hitung dengan
        // cara lain, supaya "piutang" di guard ini = "piutang" di laporan.
        return now()->format('Y-m');
    }

    /**
     * @return Builder<Invoice>
     */
    public static function blockingInvoicesQuery(Customer $customer): Builder
    {
        $currentPeriod = self::currentPeriod();

        return Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('invoice_status', Invoice::OUTSTANDING_STATUSES)
            ->where(fn (Builder $q) => $q
                ->where('billing_period', '<', $currentPeriod)
                ->orWhere('invoice_status', InvoiceStatus::SEBAGIAN->value)
                ->orWhereHas('payments', self::validPayment(...)));
    }

    /**
     * @return array{count: int, total: float}
     */
    public static function blockingSummary(Customer $customer): array
    {
        $invoices = self::blockingInvoicesQuery($customer)->get(['id', 'remaining_amount']);

        return [
            'count' => $invoices->count(),
            'total' => (float) $invoices->sum('remaining_amount'),
        ];
    }

    /**
     * Tagihan yang ikut pindah ke cabang baru saat pop_id berganti.
     *
     * @return Builder<Invoice>
     */
    public static function movableInvoicesQuery(Customer $customer): Builder
    {
        return Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('invoice_status', InvoiceStatus::BELUM_DIBAYAR->value)
            ->where('billing_period', '>=', self::currentPeriod())
            ->whereDoesntHave('payments', self::validPayment(...));
    }

    /**
     * @return array{count: int, total: float}
     */
    public static function movableSummary(Customer $customer): array
    {
        $invoices = self::movableInvoicesQuery($customer)->get(['id', 'remaining_amount']);

        return [
            'count' => $invoices->count(),
            'total' => (float) $invoices->sum('remaining_amount'),
        ];
    }

    public static function blockingMessage(array $summary): string
    {
        return "Pelanggan masih punya {$summary['count']} tagihan yang wajib lunas dulu (sisa Rp "
            .number_format($summary['total'], 0, ',', '.')
            .'): piutang bulan-bulan sebelumnya atau tagihan yang sudah dicicil sebagian. Lunasi dulu sebelum pindah Cabang.';
    }

    /**
     * Pindahkan tagihan bulan berjalan yang belum dibayar ke POP baru. Per
     * model (bukan query update massal) supaya tiap perpindahan tercatat di
     * audit_logs (RecordsAuditLogs pada Invoice).
     */
    public static function moveCurrentInvoices(Customer $customer, int $newPopId): int
    {
        $invoices = self::movableInvoicesQuery($customer)
            ->where('pop_id', '!=', $newPopId)
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->update(['pop_id' => $newPopId]);
        }

        return $invoices->count();
    }
}
