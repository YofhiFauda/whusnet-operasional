<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\FopTask;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Task;
use App\Models\Ticket;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya penghasil nomor dokumen (TKT, TFOP, TASK, invoice, payment).
 *
 * Nomor dihitung dari tabel `number_sequences` (satu baris per key), bukan
 * `count() + 1` atau `max() + 1` di atas tabel dokumen. Alasannya dua:
 * - Baris counter dikunci `UPDATE` (atomik), jadi dua request bersamaan tidak
 *   pernah dapat angka sama — termasuk hari pertama per prefix, saat belum ada
 *   baris dokumen untuk dikunci `lockForUpdate()`.
 * - Counter TIDAK turun saat baris dokumen di-hard delete (lihat
 *   CleanupLegacyDuplicateInvoicesCommand), jadi nomor yang pernah terbit tidak
 *   dipakai ulang.
 *
 * Baris counter baru di-seed dari dokumen yang sudah ada (lewat callback
 * `$seed`), supaya data lama tidak bentrok begitu counter pertama kali dipakai.
 * Pemanggil WAJIB berada di dalam transaksi yang sama dengan insert dokumennya,
 * supaya kalau insert gagal kenaikan counter ikut rollback (tidak ada nomor
 * bolong yang sia-sia). Metode ini membungkus sendiri `DB::transaction()` untuk
 * jaga-jaga, dan savepoint bersarang aman.
 */
class NumberSequenceService
{
    private const TABLE = 'number_sequences';

    public function ticketNumber(): string
    {
        $ymd = date('Ymd');
        $value = $this->next("TKT:{$ymd}", fn () => $this->lastSuffix(
            Ticket::where('ticket_number', 'like', "TKT-{$ymd}-%")->pluck('ticket_number')
        ));

        return sprintf('TKT-%s-%06d', $ymd, $value);
    }

    public function fopTaskNumber(): string
    {
        $ymd = date('Ymd');
        $value = $this->next("TFOP:{$ymd}", fn () => $this->lastSuffix(
            FopTask::where('task_number', 'like', "TFOP-{$ymd}-%")->pluck('task_number')
        ));

        return sprintf('TFOP-%s-%06d', $ymd, $value);
    }

    public function taskNumber(): string
    {
        $ymd = date('Ymd');
        $value = $this->next("TASK:{$ymd}", fn () => $this->lastSuffix(
            Task::where('task_number', 'like', "TASK-{$ymd}-%")->pluck('task_number')
        ));

        return sprintf('TASK-%s-%06d', $ymd, $value);
    }

    /**
     * @param  string  $prefix  `ACT`/`TAG`/`MTN`/`OTH`/`REL`
     * @param  string  $dateCode  `YYYYMMDD`
     */
    public function invoiceNumber(string $prefix, string $dateCode): string
    {
        $value = $this->next("INV:{$prefix}-{$dateCode}", fn () => $this->lastSuffix(
            Invoice::where('invoice_number', 'like', "{$prefix}-{$dateCode}-%")->pluck('invoice_number')
        ));

        return sprintf('%s-%s-%06d', $prefix, $dateCode, $value);
    }

    /**
     * Urutan pembayaran ke-N pada satu invoice. Angka ini tidak pernah dipakai
     * ulang, termasuk untuk baris yang nanti ditolak atau di-hard delete.
     */
    public function paymentOrdinal(Invoice $invoice): int
    {
        return $this->next("PAY:{$invoice->id}", function () use ($invoice) {
            $numberPrefix = "PAY-{$invoice->invoice_number}-";

            $legacySuffix = Payment::where('invoice_id', $invoice->id)
                ->pluck('payment_number')
                ->filter(fn ($number) => str_starts_with((string) $number, $numberPrefix))
                ->map(fn ($number) => substr((string) $number, strlen($numberPrefix)))
                ->filter(fn ($suffix) => ctype_digit($suffix))
                ->map(fn ($suffix) => (int) $suffix)
                ->max() ?? 0;

            return max($legacySuffix, Payment::where('invoice_id', $invoice->id)->count());
        });
    }

    /**
     * Urutan sentuhan auto-bayar Saldo Pelanggan ke-N pada satu invoice. Dulu
     * `count()+1` di atas tabel payments — dua auto-pay paralel membaca count
     * yang sama lalu bertabrakan di idempotency_key; hard delete juga membuat
     * angka dipakai ulang.
     */
    public function autoSaldoOrdinal(Invoice $invoice): int
    {
        return $this->next("AUTOSALDO:{$invoice->id}", fn () => Payment::where('invoice_id', $invoice->id)
            ->where('payment_method', PaymentMethod::SALDO->value)
            ->count());
    }

    /**
     * Mengembalikan angka berikutnya untuk `$key`, dan menaikkan counter-nya.
     */
    private function next(string $key, Closure $seed): int
    {
        return DB::transaction(function () use ($key, $seed) {
            if (! DB::table(self::TABLE)->where('key', $key)->exists()) {
                DB::table(self::TABLE)->insertOrIgnore([
                    'key' => $key,
                    'value' => $seed(),
                ]);
            }

            DB::table(self::TABLE)->where('key', $key)->increment('value');

            return (int) DB::table(self::TABLE)->where('key', $key)->value('value');
        });
    }

    /**
     * @param  Collection<int, string>  $numbers
     */
    private function lastSuffix(Collection $numbers): int
    {
        return $numbers
            ->map(fn (string $number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;
    }
}
