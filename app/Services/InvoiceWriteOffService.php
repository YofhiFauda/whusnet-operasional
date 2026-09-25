<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Support\BookPeriod;
use App\Support\Money;
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
            ]);

            return $locked;
        });
    }

    /**
     * Batalkan hapus buku. Status dihitung ulang dari payment, jadi sisa
     * tagihan yang asli kembali utuh.
     */
    public function reverse(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->invoice_status !== InvoiceStatus::TAK_TERTAGIH) {
                throw ValidationException::withMessages([
                    'reason' => 'Tagihan ini tidak sedang berstatus tak tertagih.',
                ]);
            }

            // Hapus buku yang jatuh di periode terkunci tak boleh dibatalkan:
            // laporan bulan itu sudah memuatnya, dan kuncinya permanen.
            $writtenOffPeriod = $locked->written_off_at?->format('Y-m');
            if (BookPeriod::isLocked($writtenOffPeriod)) {
                throw ValidationException::withMessages([
                    'reason' => "Hapus buku ini tercatat di periode {$writtenOffPeriod} yang sudah ditutup buku (terkunci permanen) — tidak bisa dibatalkan.",
                ]);
            }

            // Status keluar dari TAK_TERTAGIH dulu, baru recalculate —
            // recalculateFromPayments() sengaja early-return untuk status ini.
            $locked->update([
                'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
                'written_off_at' => null,
                'written_off_by' => null,
                'written_off_amount' => null,
                'write_off_reason' => null,
            ]);

            $locked->refresh()->recalculateFromPayments();

            return $locked->refresh();
        });
    }
}
