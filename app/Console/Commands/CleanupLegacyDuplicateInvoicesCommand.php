<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('billing:cleanup-legacy-duplicate-invoices
    {--force : Benar-benar menulis. Tanpa ini command hanya mencetak rencana}')]
#[Description('Bereskan 4 tabrakan invoice per (pelanggan, jenis, periode) peninggalan migrasi legacy, supaya unique index anti-tagihan-dobel bisa dipasang.')]
class CleanupLegacyDuplicateInvoicesCommand extends Command
{
    /**
     * Perbaikan sekali-jalan untuk 4 tabrakan yang tersisa dari migrasi legacy.
     * Sengaja memakai daftar eksplisit, bukan deteksi otomatis: tiap kasus sudah
     * ditelusuri satu per satu ke `riwayat_pelanggan` dan tabel bukti transaksi,
     * dan kesimpulannya BERBEDA-BEDA. Menebaknya lewat aturan umum justru
     * berisiko menghapus tagihan yang sah.
     *
     * Kasus 1 — HAPUS (duplikat sungguhan).
     * RQ000308 Sugianto: `riwayat_pelanggan` mencatat "Berhasil Active" DUA KALI
     * (2023-01-10 09:16:27 dan 09:19:42), dan tiap event melahirkan satu baris
     * `biaya_tagihan` 4 detik sesudahnya. Admin mengulang aktivasi untuk
     * membetulkan BIAYALAINLAIN 11.000 → 191.000. Dedup importer tidak
     * menangkapnya justru karena nominal itu berbeda. `buktitransaksilunas`
     * (kwitansi kasir asli) kosong untuk keduanya, jadi baris "pembayaran"
     * 85.516 itu log aktivasi, bukan uang yang benar-benar masuk. Yang sah
     * tinggal tagihan 265.516.
     *
     * Kasus 2-4 — KOREKSI JENIS (bukan duplikat, uangnya nyata).
     * RQ000311, RQ000306, RQ000289: invoice keduanya terbit dari batch
     * penagihan 2022-12-27 03:23:09 yang menerbitkan 31 invoice sekaligus —
     * bukan dari event aktivasi. Keduanya tagihan sah dan lunas. Yang keliru
     * hanya `invoice_type`: importer menandai baris registrasi vs bulanan
     * berdasarkan ada-tidaknya BIAYAPASANG/BIAYALAINLAIN, dan batch 27 Des
     * ikut menyalin biaya pendaftaran sehingga tagihan bulanan tersalah-tandai
     * jadi 'awal'. Dikembalikan sesuai asal-usulnya: lahir dari aktivasi =
     * 'awal', lahir dari batch penagihan = 'bulanan'. Nominal dan periode tidak
     * disentuh sama sekali.
     *
     * Default dry-run. `--force` baru menulis, dan setiap perubahan masuk audit
     * log — termasuk salinan lengkap baris yang dihapus, supaya masih bisa
     * direkonstruksi kalau ternyata keliru.
     */
    private const HAPUS = [
        // old_cost_id => nominal yang diharapkan, sebagai pagar pengaman
        'IN000336-AWAL' => 85516.0,
    ];

    private const KOREKSI_JENIS = [
        // old_cost_id => [dari, ke]
        'IN000289-BULANAN' => ['bulanan', 'awal'],
        'IN000295-AWAL' => ['awal', 'bulanan'],
        'IN000303-AWAL' => ['awal', 'bulanan'],
    ];

    public function handle(): int
    {
        $write = (bool) $this->option('force');

        $this->info($write
            ? 'Bereskan invoice dobel legacy — MODE TULIS'
            : 'Bereskan invoice dobel legacy — mode rencana saja (tambahkan --force untuk menulis)');
        $this->newLine();

        $rencanaHapus = [];
        $rencanaKoreksi = [];
        $rows = [];

        foreach (self::HAPUS as $costId => $nominalHarapan) {
            $invoice = Invoice::where('old_cost_id', $costId)->first();

            if ($invoice === null) {
                $rows[] = [$costId, 'hapus', '—', 'SUDAH TIDAK ADA'];

                continue;
            }

            // Pagar pengaman: kalau nominalnya bukan yang kita telusuri, berarti
            // datanya sudah berubah sejak analisa. Berhenti, jangan hapus.
            if (abs((float) $invoice->total_amount - $nominalHarapan) > 0.01) {
                $this->error("Nominal {$costId} ({$invoice->total_amount}) tidak cocok dengan hasil analisa ({$nominalHarapan}). Dibatalkan.");

                return self::FAILURE;
            }

            $payments = Payment::where('invoice_id', $invoice->id)->get();
            $rencanaHapus[] = [$invoice, $payments];
            $rows[] = [
                $costId,
                'hapus',
                number_format((float) $invoice->total_amount, 0, ',', '.').' + '.$payments->count().' pembayaran',
                $write ? 'DIHAPUS' : 'rencana',
            ];
        }

        foreach (self::KOREKSI_JENIS as $costId => [$dari, $ke]) {
            $invoice = Invoice::where('old_cost_id', $costId)->first();

            if ($invoice === null) {
                $rows[] = [$costId, 'koreksi jenis', '—', 'TIDAK DITEMUKAN'];

                continue;
            }

            if ($invoice->invoice_type->value === $ke) {
                $rows[] = [$costId, 'koreksi jenis', $ke, 'SUDAH BENAR'];

                continue;
            }

            if ($invoice->invoice_type->value !== $dari) {
                $this->error("Jenis {$costId} sekarang '{$invoice->invoice_type->value}', bukan '{$dari}' seperti hasil analisa. Dibatalkan.");

                return self::FAILURE;
            }

            $rencanaKoreksi[] = [$invoice, $ke];
            $rows[] = [$costId, 'koreksi jenis', $dari.' → '.$ke, $write ? 'DIUBAH' : 'rencana'];
        }

        $this->table(['Invoice legacy', 'Aksi', 'Rincian', 'Status'], $rows);

        if ($write) {
            $this->jalankan($rencanaHapus, $rencanaKoreksi);
        }

        $this->newLine();
        $this->line(($write ? 'Dihapus         : ' : 'Akan dihapus    : ').count($rencanaHapus).' invoice');
        $this->line(($write ? 'Jenis diperbaiki: ' : 'Jenis diperbaiki: ').count($rencanaKoreksi).' invoice');

        $sisa = $this->hitungTabrakan();
        $this->line('Tabrakan tersisa: '.$sisa);

        if ($sisa === 0) {
            $this->info('Tidak ada lagi tabrakan (pelanggan, jenis, periode) — unique index siap dipasang.');
        }

        if (! $write && ($rencanaHapus !== [] || $rencanaKoreksi !== [])) {
            $this->newLine();
            $this->warn('Belum ada yang ditulis. Periksa rencana di atas, lalu jalankan ulang dengan --force.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{0: Invoice, 1: Collection<int, Payment>}>  $rencanaHapus
     * @param  list<array{0: Invoice, 1: string}>  $rencanaKoreksi
     */
    private function jalankan(array $rencanaHapus, array $rencanaKoreksi): void
    {
        foreach ($rencanaHapus as [$invoice, $payments]) {
            DB::transaction(function () use ($invoice, $payments) {
                // Simpan salinan utuh sebelum dihapus. Ini satu-satunya jalan
                // merekonstruksi baris kalau kesimpulan "duplikat aktivasi"
                // ternyata keliru — dump legacy saja tidak cukup karena nomor
                // invoice/pembayaran versi baru digenerate saat import.
                AuditLog::create([
                    'user_id' => null,
                    'module' => 'Billing',
                    'action' => 'hapus_invoice_dobel_legacy',
                    'auditable_type' => Invoice::class,
                    'auditable_id' => $invoice->id,
                    'old_values' => [
                        'invoice' => $invoice->toArray(),
                        'payments' => $payments->map->toArray()->all(),
                    ],
                    'new_values' => [
                        'alasan' => 'Duplikat event aktivasi legacy: "Berhasil Active" tercatat dua kali, tiap event melahirkan satu baris biaya_tagihan. Tidak ada kwitansi kasir (buktitransaksilunas) untuk baris ini.',
                    ],
                    'created_at' => now(),
                ]);

                Payment::whereIn('id', $payments->pluck('id'))->delete();
                $invoice->delete();
            });
        }

        foreach ($rencanaKoreksi as [$invoice, $ke]) {
            DB::transaction(function () use ($invoice, $ke) {
                $dari = $invoice->invoice_type->value;
                $invoice->update(['invoice_type' => $ke]);

                AuditLog::create([
                    'user_id' => null,
                    'module' => 'Billing',
                    'action' => 'koreksi_jenis_invoice_legacy',
                    'auditable_type' => Invoice::class,
                    'auditable_id' => $invoice->id,
                    'old_values' => ['invoice_type' => $dari],
                    'new_values' => [
                        'invoice_type' => $ke,
                        'alasan' => 'Jenis ditentukan ulang dari asal-usulnya: lahir dari event aktivasi = awal, lahir dari batch penagihan 2022-12-27 = bulanan. Nominal dan periode tidak diubah.',
                    ],
                    'created_at' => now(),
                ]);
            });
        }
    }

    private function hitungTabrakan(): int
    {
        return DB::table('invoices')
            ->select('customer_id', 'billing_period', 'invoice_type')
            ->groupBy('customer_id', 'billing_period', 'invoice_type')
            ->havingRaw('count(*) > 1')
            ->get()
            ->count();
    }
}
