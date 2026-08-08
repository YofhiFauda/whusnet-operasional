<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:audit-duplicate-invoices
    {--period= : Batasi ke satu periode saja, format YYYY-MM}
    {--strict : Keluar dengan exit code 1 kalau ada temuan (untuk monitoring)}')]
#[Description('Laporkan pelanggan yang punya lebih dari satu tagihan langganan pada periode yang sama. Read-only.')]
class AuditDuplicateInvoicesCommand extends Command
{
    /**
     * Jaring deteksi, bukan pencegahan.
     *
     * Pencegahannya ada di dua tempat: pengecekan lintas-jenis di
     * GenerateMonthlyInvoicesCommand dan InvoiceObserver::creating(). Command
     * ini ada untuk kasus yang lolos dari keduanya — jalur insert yang belum
     * terpikir, atau data yang masuk sebelum guard itu dipasang. Tujuannya
     * supaya tagihan dobel ketahuan dalam hitungan hari, bukan waktu pelanggan
     * menelepon marah.
     *
     * Sengaja read-only. Tidak ada `--fix`: keputusan membatalkan tagihan mana
     * dan bagaimana memperlakukan uang yang sudah terlanjur dibayar adalah
     * keputusan bisnis per kasus, bukan sesuatu yang boleh diputuskan massal
     * oleh command. Lihat docs/billing-pembayaran/analisa-pencegahan-tagihan-dobel.md
     * bagian 5.
     *
     * Catatan scope: command CLI berjalan tanpa user login, jadi laporannya
     * lintas-POP. Itu disengaja — auditnya memang untuk owner/pusat. Kalau
     * suatu saat hasil ini diekspos lewat HTTP, wajib dibatasi
     * EffectiveAccessService::getAllowedPopIds() dulu.
     */
    public function handle(): int
    {
        $period = $this->option('period');

        if ($period !== null && preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            $this->error('Format --period harus YYYY-MM, contoh: --period=2026-07');

            return self::INVALID;
        }

        // Kelompok yang dicari: pelanggan + periode yang punya >1 tagihan
        // LANGGANAN. REAKTIVASI tidak dihitung (suspend lalu aktif lagi di bulan
        // sama itu sah) dan BATAL tidak dihitung (tagihan yang sudah dianulir
        // bukan tagihan berjalan).
        $groups = Invoice::query()
            ->selectRaw('customer_id, billing_period, COUNT(*) as jumlah')
            ->whereIn('invoice_type', [InvoiceType::AWAL->value, InvoiceType::BULANAN->value])
            ->where('invoice_status', '!=', InvoiceStatus::BATAL->value)
            ->when($period !== null, fn ($q) => $q->where('billing_period', $period))
            ->groupBy('customer_id', 'billing_period')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('billing_period')
            ->orderBy('customer_id')
            ->get();

        $judul = $period !== null
            ? "Audit tagihan langganan dobel — periode {$period}"
            : 'Audit tagihan langganan dobel — semua periode';

        $this->info($judul);

        if ($groups->isEmpty()) {
            $this->line('Tidak ada temuan.');

            return self::SUCCESS;
        }

        $rows = [];
        $totalTerbayar = 0.0;
        $grupLegacy = 0;

        foreach ($groups as $group) {
            $invoices = Invoice::with('customer')
                ->where('customer_id', $group->customer_id)
                ->where('billing_period', $group->billing_period)
                ->whereIn('invoice_type', [InvoiceType::AWAL->value, InvoiceType::BULANAN->value])
                ->where('invoice_status', '!=', InvoiceStatus::BATAL->value)
                ->orderBy('id')
                ->get();

            // Grup yang SEMUA barisnya bertanda legacy adalah warisan migrasi,
            // bukan bug jalur berjalan. Dipisahkan supaya temuan baru tidak
            // tenggelam di antara data lama yang memang sudah diketahui kotor.
            $semuaLegacy = $invoices->every(fn (Invoice $i) => ! empty($i->old_invoice_id));
            if ($semuaLegacy) {
                $grupLegacy++;
            }

            $terbayar = (float) $invoices->sum('paid_amount');
            $totalTerbayar += $terbayar;

            $customer = $invoices->first()->customer;

            $rows[] = [
                $customer->customer_code ?? '—',
                mb_strimwidth((string) ($customer->full_name ?? '—'), 0, 24, '…'),
                $group->billing_period,
                $group->jumlah,
                $invoices->map(fn (Invoice $i) => $i->invoice_type instanceof \BackedEnum ? $i->invoice_type->value : $i->invoice_type)->implode(', '),
                number_format((float) $invoices->sum('total_amount'), 0, ',', '.'),
                number_format($terbayar, 0, ',', '.'),
                $semuaLegacy ? 'legacy' : 'PERLU CEK',
            ];
        }

        $this->table(
            ['Kode', 'Nama', 'Periode', 'Jml', 'Jenis', 'Total', 'Terbayar', 'Sumber'],
            $rows
        );

        $baru = $groups->count() - $grupLegacy;

        $this->newLine();
        $this->line("Total grup dobel : {$groups->count()}");
        $this->line("  warisan legacy : {$grupLegacy}  (BILLING-B0e — pembersihan data lama)");
        $this->line("  perlu dicek    : {$baru}");
        $this->line('Nominal terbayar di grup dobel: Rp '.number_format($totalTerbayar, 0, ',', '.'));

        if ($baru > 0) {
            $this->warn('Ada tagihan dobel di luar data legacy. Periksa jalur pembuatan invoice — dua lapis guard seharusnya menahannya.');
        }

        if ($totalTerbayar > 0) {
            $this->warn('Sebagian tagihan dobel sudah dibayar. Uangnya tidak boleh menguap: jadikan kredit periode berikutnya atau kembalikan.');
        }

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
