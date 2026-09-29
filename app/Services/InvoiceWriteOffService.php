<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Support\BookPeriod;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hapus buku piutang (ADHOC-90): tagihan bulan lalu diakui tak akan tertagih.
 *
 * Bukan pembatalan (BATAL) — tagihannya sah dan tetap tercatat; yang berubah
 * cuma statusnya keluar dari hitungan piutang dan nominal sisanya jadi baris
 * "Piutang tak Tertagih" di Laporan Bulanan Admin Collector. Jejak perubahan
 * kolom tercatat otomatis oleh `RecordsAuditLogs` di model Invoice.
 */
class InvoiceWriteOffService
{
    public function writeOff(Invoice $invoice, User $actor, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $reason): Invoice {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            // Hanya piutang (bulan lalu ke belakang, masih ada sisa). Tagihan
            // bulan berjalan belum boleh dinyatakan tak tertagih — pelanggan
            // masih boleh membayar sampai akhir bulan.
            if (! $locked->isPiutang() || Money::isZero($locked->remaining_amount)) {
                throw ValidationException::withMessages([
                    'reason' => 'Hanya piutang (tagihan bulan lalu yang masih punya sisa) yang bisa dihapus buku.',
                ]);
            }

            // Hapus buku selalu dicatat di bulan berjalan (Blok 2 bulan ini),
            // yang tidak pernah terkunci — jadi tidak perlu cek kunci periode.
            $locked->update([
                'invoice_status' => InvoiceStatus::TAK_TERTAGIH->value,
                'written_off_at' => now(),
                'written_off_by' => $actor->id,
                'written_off_amount' => $locked->remaining_amount,
                'write_off_reason' => $reason,
                // Siklus hapus buku baru — jejak pemulihan siklus sebelumnya
                // (kalau ada) tidak berlaku lagi untuk siklus ini. Riwayat
                // siklus lama tetap ada di audit log model Invoice.
                'write_off_reversed_at' => null,
                'write_off_reversed_by' => null,
            ]);

            return $locked;
        });
    }

    /**
     * "Kembalikan Semua" di List Putus Langganan (ADHOC-105): batalkan hapus
     * buku seluruh invoice `tak_tertagih` milik satu pelanggan dalam SATU
     * transaksi. Kalau satu invoice ditolak `reverse()`, semuanya dibatalkan —
     * jangan sampai sebagian terkembalikan diam-diam. Pemanggil bertanggung
     * jawab memeriksa POP scope pelanggan.
     *
     * @return Collection<int, Invoice>
     */
    public function reverseAllForCustomer(Customer $customer, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($customer, $actor): Collection {
            $invoices = Invoice::query()
                ->where('customer_id', $customer->id)
                ->where('invoice_status', InvoiceStatus::TAK_TERTAGIH->value)
                ->orderBy('id')
                ->get();

            if ($invoices->isEmpty()) {
                throw ValidationException::withMessages([
                    'reason' => 'Pelanggan ini tidak punya tagihan tak tertagih.',
                ]);
            }

            return $invoices->map(fn (Invoice $invoice) => $this->reverse($invoice, $actor));
        });
    }

    /**
     * Batalkan hapus buku. Status dihitung ulang dari payment, jadi sisa
     * tagihan yang asli kembali utuh.
     *
     * Hapus buku di periode BERJALAN dibatalkan seperti biasa: kolom
     * `written_off_*` dikosongkan, seolah tak pernah terjadi (laporan bulan
     * ini belum final). Hanya penanda `write_off_reversed_*` yang tersisa.
     *
     * Hapus buku di periode TERKUNCI tetap boleh dikembalikan (ADHOC-105,
     * opsi A2) — pelanggan putus harus selalu bisa membayar tagihannya —
     * tapi jejaknya DIPERTAHANKAN: hanya `write_off_reversed_at/by` yang
     * diisi. Laporan Bulanan periode hapus buku itu tetap memuat angkanya
     * (sah per tanggal, pola sama `payments.rejected_at`), dan pemulihannya
     * dibukukan di bulan terjadinya.
     */
    public function reverse(Invoice $invoice, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->invoice_status !== InvoiceStatus::TAK_TERTAGIH) {
                throw ValidationException::withMessages([
                    'reason' => 'Tagihan ini tidak sedang berstatus tak tertagih.',
                ]);
            }

            $writtenOffPeriod = $locked->written_off_at?->format('Y-m');

            // Status keluar dari TAK_TERTAGIH dulu, baru recalculate —
            // recalculateFromPayments() sengaja early-return untuk status ini.
            if (BookPeriod::isLocked($writtenOffPeriod)) {
                $locked->update([
                    'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
                    'write_off_reversed_at' => now(),
                    'write_off_reversed_by' => $actor?->id ?? auth()->id(),
                ]);
            } else {
                // `written_off_*` dikosongkan (laporan bulan ini belum final),
                // tapi `write_off_reversed_*` TETAP diisi sebagai penanda
                // "pernah dikembalikan admin": job hapus buku otomatis
                // pelanggan putus melewati invoice bertanda ini, kalau tidak
                // Kembalikan di bulan hapus bukunya cuma bertahan sampai
                // tanggal 1 berikutnya. Laporan tak terpengaruh — kolom
                // `written_off_at` null berarti tak pernah dihitung.
                $locked->update([
                    'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
                    'written_off_at' => null,
                    'written_off_by' => null,
                    'written_off_amount' => null,
                    'write_off_reason' => null,
                    'write_off_reversed_at' => now(),
                    'write_off_reversed_by' => $actor?->id ?? auth()->id(),
                ]);
            }

            $locked->refresh()->recalculateFromPayments();

            return $locked->refresh();
        });
    }
}
