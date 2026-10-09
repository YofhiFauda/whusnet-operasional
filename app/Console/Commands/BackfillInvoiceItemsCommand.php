<?php

namespace App\Console\Commands;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\RevenueCategory;
use App\Models\RevenueSubcategory;
use App\Services\InvoiceItemBuilder;
use App\Support\Money;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Mengisi `invoice_items` untuk tagihan yang terbit SEBELUM ADHOC-60.
 *
 * ## Dua populasi, dua perlakuan — jangan disamakan
 *
 * Survei 6.092 tagihan (2026-09-09) menemukan bahwa kolom biaya lama TIDAK
 * berarti sama di seluruh data:
 *
 *   non-legacy (`old_invoice_id` NULL) : 4.364 — kolom biaya tambahan 0/NULL,
 *                                        `subtotal` = harga langganan.
 *   legacy     (`old_invoice_id` ADA)  : 1.728 — kolom biaya tambahan TERISI
 *                                        tapi TIDAK pernah ikut ditagihkan.
 *
 * Contoh legacy yang menentukan keputusan ini:
 *
 *   INV-IN000011-AWAL   subtotal=110.000  prorate=110.000
 *                       instalasi=250.000  lain=11.000   total=110.000
 *
 * `subtotal == total_amount == prorate`, sementara `extra_installation_fee` dan
 * `other_fee` berisi angka yang tidak pernah masuk hitungan. Memetakan kolom →
 * baris untuk tagihan seperti ini membuat rinciannya berjumlah 371.000 padahal
 * yang ditagihkan 110.000 — laporan pendapatan menggelembung sampai 4×, dan
 * angkanya masuk sebagai fakta tanpa penanda apa pun.
 *
 * Karena itu tagihan legacy dipetakan jadi SATU baris senilai `subtotal`, dan
 * kolom biaya tambahannya sengaja diabaikan. Nilainya tetap utuh di `invoices`
 * (tidak dihapus), jadi kalau suatu saat ada keputusan bisnis untuk
 * menagihkannya susulan, datanya masih ada.
 *
 * ## Tidak ada opsi menambal selisih
 *
 * Sengaja tidak disediakan `--force-balance`. Satu-satunya sumber
 * ketidakseimbangan yang diketahui sudah ditangani lewat pemisahan populasi di
 * atas; menyediakan pintu tambal otomatis berarti mengundang angka karangan
 * masuk ke laporan keuangan. Tagihan yang tetap tidak seimbang DILEWATI dan
 * DILAPORKAN supaya dilihat manusia.
 */
#[Signature('billing:backfill-invoice-items {--period= : Batasi ke satu periode billing YYYY-MM} {--dry-run : Tampilkan rencana tanpa menulis apa pun}')]
#[Description('Isi rincian kategori pendapatan (invoice_items) untuk tagihan yang terbit sebelum ADHOC-60.')]
class BackfillInvoiceItemsCommand extends Command
{
    public function handle(InvoiceItemBuilder $builder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $period = $this->option('period');

        if ($period !== null && ! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            $this->error('Format --period harus YYYY-MM.');

            return self::FAILURE;
        }

        $query = Invoice::query()
            // Idempotent: tagihan yang sudah punya rincian dilewati, BUKAN
            // ditimpa. Menimpa akan menghapus rincian yang sudah dikoreksi
            // manual oleh admin.
            ->whereDoesntHave('items')
            ->when($period, fn ($q) => $q->where('billing_period', $period))
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Tidak ada tagihan yang perlu diisi rinciannya.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Memproses {$total} tagihan tanpa rincian...");

        $stats = ['legacy' => 0, 'non_legacy' => 0, 'dilewati' => 0];
        $skipped = [];

        $query->chunkById(200, function ($invoices) use ($builder, $dryRun, &$stats, &$skipped) {
            foreach ($invoices as $invoice) {
                $isLegacy = ! empty($invoice->old_invoice_id);

                try {
                    $lines = $isLegacy
                        ? $this->legacyLines($invoice)
                        : $this->columnLines($invoice);
                } catch (InvalidArgumentException $e) {
                    $stats['dilewati']++;
                    $skipped[] = [$invoice->invoice_number, $e->getMessage()];

                    continue;
                }

                if (! $dryRun) {
                    try {
                        $builder->rebuildFor($invoice, $lines);
                    } catch (Throwable $e) {
                        $stats['dilewati']++;
                        $skipped[] = [$invoice->invoice_number, $e->getMessage()];

                        continue;
                    }
                }

                $stats[$isLegacy ? 'legacy' : 'non_legacy']++;
            }
        });

        $this->newLine();
        $this->table(['Populasi', 'Jumlah'], [
            ['Legacy (satu baris = subtotal)', $stats['legacy']],
            ['Non-legacy (pemetaan per kolom)', $stats['non_legacy']],
            ['Dilewati', $stats['dilewati']],
        ]);

        if ($skipped !== []) {
            $this->newLine();
            $this->warn('Tagihan berikut DILEWATI dan perlu ditinjau manual:');
            $this->table(['No. Tagihan', 'Alasan'], array_slice($skipped, 0, 50));

            if (count($skipped) > 50) {
                $this->warn('... dan '.(count($skipped) - 50).' lainnya.');
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('DRY RUN — tidak ada yang ditulis. Jalankan ulang tanpa --dry-run setelah tabel di atas ditinjau.');
        }

        return self::SUCCESS;
    }

    /**
     * Tagihan hasil migrasi: SATU baris senilai `subtotal`. Kolom biaya
     * tambahannya sengaja tidak dipetakan — nilainya tidak pernah ikut
     * ditagihkan (lihat docblock kelas).
     *
     * @return list<array<string, mixed>>
     */
    private function legacyLines(Invoice $invoice): array
    {
        $subtotal = Money::of($invoice->subtotal);

        if (Money::compare($subtotal, 0) <= 0) {
            throw new InvalidArgumentException('Subtotal nol atau negatif — tidak ada yang bisa dirinci.');
        }

        $type = $invoice->invoice_type instanceof InvoiceType
            ? $invoice->invoice_type
            : InvoiceType::tryFrom((string) $invoice->invoice_type);

        return [[
            'category_code' => RevenueCategory::CODE_JASA_LAYANAN_INTERNET,
            'subcategory_code' => $type === InvoiceType::AWAL
                ? RevenueSubcategory::CODE_PRORATA
                : RevenueSubcategory::CODE_LANGGANAN_BULANAN,
            'description' => 'Migrasi data lama — rincian biaya tidak dapat diverifikasi',
            'amount' => $subtotal,
        ]];
    }

    /**
     * Tagihan yang terbit dari sistem ini: kolom biaya dipetakan satu per satu,
     * dan sisanya (`subtotal` dikurangi seluruh kolom itu) jadi baris langganan.
     * Baris langganan berperan sebagai RESIDUAL, sehingga invarian
     * `SUM(baris) == subtotal` terpenuhi by construction.
     *
     * @return list<array<string, mixed>>
     */
    private function columnLines(Invoice $invoice): array
    {
        $subtotal = Money::of($invoice->subtotal);

        if (Money::compare($subtotal, 0) <= 0) {
            throw new InvalidArgumentException('Subtotal nol atau negatif — tidak ada yang bisa dirinci.');
        }

        $mapped = [
            [
                'category_code' => RevenueCategory::CODE_JASA_LAYANAN_INTERNET,
                'subcategory_code' => RevenueSubcategory::CODE_PRORATA,
                'description' => 'Langganan prorata bulan aktivasi',
                'amount' => Money::atLeastZero($invoice->prorate_amount ?? 0),
            ],
            [
                'category_code' => RevenueCategory::CODE_JASA_INSTALASI,
                'subcategory_code' => RevenueSubcategory::CODE_BIAYA_AKTIVASI,
                'description' => null,
                'amount' => Money::atLeastZero($invoice->extra_installation_fee ?? 0),
            ],
            [
                'category_code' => RevenueCategory::CODE_JASA_PERBAIKAN,
                'subcategory_code' => 'tambah_kabel',
                'description' => null,
                'amount' => Money::atLeastZero($invoice->extra_cable_fee ?? 0),
            ],
            [
                'category_code' => RevenueCategory::CODE_JASA_PERBAIKAN,
                'subcategory_code' => 'tambah_tiang',
                'description' => null,
                'amount' => Money::atLeastZero($invoice->extra_pole_fee ?? 0),
            ],
            [
                'category_code' => RevenueCategory::CODE_LAINNYA,
                'custom_name' => 'Materai / Biaya Lain',
                'description' => null,
                'amount' => Money::atLeastZero($invoice->other_fee ?? 0),
            ],
        ];

        $residual = Money::sub($subtotal, Money::sum(array_column($mapped, 'amount')));

        if (Money::compare($residual, 0) < 0) {
            throw new InvalidArgumentException(
                'Jumlah kolom biaya melebihi subtotal — kombinasi yang belum teridentifikasi, perlu ditinjau manusia.'
            );
        }

        array_unshift($mapped, [
            'category_code' => RevenueCategory::CODE_JASA_LAYANAN_INTERNET,
            'subcategory_code' => RevenueSubcategory::CODE_LANGGANAN_BULANAN,
            'description' => "Langganan {$invoice->billing_period}",
            'amount' => $residual,
        ]);

        return $mapped;
    }
}
