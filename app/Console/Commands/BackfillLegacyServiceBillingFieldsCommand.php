<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ParsesLegacySqlDump;
use App\Enums\InvoiceType;
use App\Models\AuditLog;
use App\Models\CustomerService;
use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('billing:backfill-legacy-service-fields
    {--force : Benar-benar menulis. Tanpa ini command hanya mencetak daftar usulan}
    {--limit= : Batasi jumlah baris yang diproses, untuk uji coba bertahap}
    {--dump=* : Dump SQL legacy sebagai sumber harga bulanan, mis. --dump=jetis_db_aplikasi_jetis.sql}')]
#[Description('Isi due_date dan monthly_price layanan hasil migrasi legacy yang kosong/0, lalu hitung ulang status kelengkapan data. Default hanya mencetak usulan.')]
class BackfillLegacyServiceBillingFieldsCommand extends Command
{
    use ParsesLegacySqlDump;

    /**
     * Harga bulanan dari dump legacy: old_request_id => BIAYABULANAN terakhir.
     *
     * @var array<string, int>
     */
    private array $hargaDariDump = [];

    /**
     * Membetulkan dua cacat pada baris hasil `app:import-legacy-sql` yang
     * terlanjur masuk sebelum command import-nya diperbaiki.
     *
     * 1. `due_date` — dulu sheet services mengirim string kosong, jadi kolom ini
     *    NULL untuk SELURUH pelanggan migrasi. Akibatnya tidak ada satu pun yang
     *    lolos `CustomerValidationService`: tanggal jatuh tempo termasuk field
     *    wajib, jadi 1.488 pelanggan aktif tertahan di 'perlu_dilengkapi'
     *    padahal mereka aktif dan ditagih tiap bulan. Diisi aktivasi + 1 bulan,
     *    mengikuti konvensi jalur manual (CustomerController::store).
     *
     * 2. `monthly_price` — paket legacy 'default'/'undefined' berharga 0, dan
     *    importer dulu selalu mengambil harga dari paket, bukan dari nominal
     *    yang benar-benar ditagih. Hasilnya puluhan layanan berharga Rp 0,
     *    sebagian di antaranya berstatus aktif — tagihan bulanan berikutnya akan
     *    terbit Rp 0 untuk pelanggan yang sebenarnya bayar penuh.
     *
     * Urutan sumber harga:
     *
     *   1. `biaya_tagihan.BIAYABULANAN` dari dump legacy (lewat --dump) — ini
     *      sumber paling benar: nominal yang memang ditagihkan tiap bulan di
     *      sistem lama. Pelanggan berpaket 'default' TIDAK punya jejak harga di
     *      database baru sama sekali, jadi tanpa dump mereka mustahil dipulihkan
     *      otomatis.
     *   2. Invoice BULANAN terakhir milik pelanggan itu — nominal rata bulanan,
     *      angka yang benar-benar pernah ditagihkan dan dibayar.
     *   3. Harga paket internet yang sekarang menempel di pelanggan, kalau > 0.
     *   4. Kalau semua kosong: JANGAN MENEBAK. Dilaporkan perlu review manual.
     *      Invoice AWAL sengaja tidak dipakai — nominalnya prorata dan sudah
     *      dibundel biaya pasang, jadi bukan harga bulanan.
     *
     * Hanya menyentuh baris legacy (`old_request_id` terisi). Baris yang dibuat
     * lewat pendaftaran normal sudah punya kedua kolom ini sejak awal.
     *
     * Default dry-run. `--force` baru menulis, dan setiap perubahan masuk audit
     * log supaya bisa ditelusuri kalau ada yang keliru.
     */
    public function handle(): int
    {
        $write = (bool) $this->option('force');
        $limit = $this->option('limit');

        if ($limit !== null && (! ctype_digit((string) $limit) || (int) $limit < 1)) {
            $this->error('--limit harus bilangan bulat positif.');

            return self::INVALID;
        }

        if (! $this->muatHargaDariDump()) {
            return self::INVALID;
        }

        $query = CustomerService::query()
            ->with(['customer.internetPackage'])
            ->whereNotNull('old_request_id')
            ->where(function ($q) {
                $q->whereNull('due_date')->orWhere('monthly_price', '<=', 0);
            })
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit((int) $limit);
        }

        $services = $query->get();

        $this->info($write
            ? 'Backfill due_date & monthly_price layanan legacy — MODE TULIS'
            : 'Backfill due_date & monthly_price layanan legacy — mode daftar saja (tambahkan --force untuk menulis)');
        $this->newLine();

        $rows = [];
        $akanDiubah = [];
        $tempoManual = 0;
        $hargaManual = 0;

        foreach ($services as $service) {
            $perubahan = [];
            $sumber = [];

            if ($service->due_date === null) {
                $tempo = $this->tentukanJatuhTempo($service);
                if ($tempo === null) {
                    $tempoManual++;
                } else {
                    $perubahan['due_date'] = $tempo;
                    $sumber[] = 'tempo: aktivasi +1 bulan';
                }
            }

            if ((float) $service->monthly_price <= 0) {
                [$harga, $sumberHarga] = $this->tentukanHarga($service);
                if ($harga === null) {
                    $hargaManual++;
                } else {
                    $perubahan['monthly_price'] = $harga;
                    $sumber[] = 'harga: '.$sumberHarga;
                }
            }

            if ($perubahan === []) {
                $rows[] = [
                    $service->customer->customer_code ?? '—',
                    mb_strimwidth((string) ($service->customer->full_name ?? '—'), 0, 22, '…'),
                    $service->due_date?->format('Y-m-d') ?? '(kosong)',
                    number_format((float) $service->monthly_price, 0, ',', '.'),
                    'tidak ada sumber',
                    'REVIEW MANUAL',
                ];

                continue;
            }

            $akanDiubah[] = [$service, $perubahan, implode(' | ', $sumber)];
            $rows[] = [
                $service->customer->customer_code ?? '—',
                mb_strimwidth((string) ($service->customer->full_name ?? '—'), 0, 22, '…'),
                $perubahan['due_date'] ?? ($service->due_date?->format('Y-m-d') ?? '(kosong)'),
                number_format((float) ($perubahan['monthly_price'] ?? $service->monthly_price), 0, ',', '.'),
                implode(' | ', $sumber),
                $write ? 'DITULIS' : 'usulan',
            ];
        }

        if ($rows === []) {
            $this->line('Tidak ada baris legacy yang perlu dibetulkan.');

            return self::SUCCESS;
        }

        $this->table(
            ['Kode', 'Nama', 'Jatuh Tempo', 'Harga Bulanan', 'Sumber', 'Status'],
            $rows
        );

        if ($write) {
            $this->tulis($akanDiubah);
        }

        $this->newLine();
        $this->line('Diperiksa            : '.$services->count());
        $this->line(($write ? 'Diubah               : ' : 'Akan diubah          : ').count($akanDiubah));
        $this->line('Tempo perlu manual   : '.$tempoManual);
        $this->line('Harga perlu manual   : '.$hargaManual);

        if ($tempoManual > 0) {
            $this->warn('Jatuh tempo tidak diisi untuk layanan tanpa tanggal aktivasi — sebagian besar berstatus ditolak/menunggu survei, jadi memang belum punya tempo.');
        }

        if ($hargaManual > 0) {
            $this->warn('Harga tidak ditebak untuk layanan tanpa invoice bulanan maupun paket berharga. Isi manual lewat halaman pelanggan.');
        }

        if (! $write && $akanDiubah !== []) {
            $this->newLine();
            $this->warn('Belum ada yang ditulis. Periksa daftar di atas, lalu jalankan ulang dengan --force.');
        }

        return self::SUCCESS;
    }

    /**
     * Baca `biaya_tagihan` dari dump yang disebut lewat --dump.
     *
     * Nilai TERBARU menang (baris biaya paling akhir), karena harga paket
     * pelanggan bisa naik di tengah masa langganan.
     */
    private function muatHargaDariDump(): bool
    {
        /** @var list<string> $files */
        $files = $this->option('dump') ?: [];

        foreach ($files as $file) {
            $path = file_exists($file) ? $file : base_path($file);

            if (! file_exists($path)) {
                $this->error("Dump '{$file}' tidak ditemukan.");

                return false;
            }

            $rows = $this->parseTableData(file_get_contents($path), 'biaya_tagihan');
            $sebelum = count($this->hargaDariDump);

            foreach ($rows as $row) {
                $requestId = trim((string) ($row['IDPERMINTAAN'] ?? ''));
                $monthlyFee = (int) ($row['BIAYABULANAN'] ?? 0);
                if ($requestId === '' || $monthlyFee <= 0) {
                    continue;
                }
                $this->hargaDariDump[$requestId] = $monthlyFee;
            }

            $this->line("Dump {$file}: ".(count($this->hargaDariDump) - $sebelum).' harga bulanan baru terbaca.');
        }

        return true;
    }

    private function tentukanJatuhTempo(CustomerService $service): ?string
    {
        if ($service->activation_date === null) {
            return null;
        }

        return $service->activation_date->copy()->addMonth()->format('Y-m-d');
    }

    /**
     * @return array{0: float|null, 1: string}
     */
    private function tentukanHarga(CustomerService $service): array
    {
        $dariDump = $this->hargaDariDump[(string) $service->old_request_id] ?? null;
        if ($dariDump !== null && $dariDump > 0) {
            return [(float) $dariDump, 'biaya_tagihan legacy'];
        }

        $invoiceBulanan = Invoice::where('customer_id', $service->customer_id)
            ->where('invoice_type', InvoiceType::BULANAN->value)
            ->where('total_amount', '>', 0)
            ->orderByDesc('issue_date')
            ->first();

        if ($invoiceBulanan !== null) {
            return [(float) $invoiceBulanan->total_amount, 'invoice BULANAN terakhir'];
        }

        $hargaPaket = (float) ($service->customer?->internetPackage?->monthly_price ?? 0);
        if ($hargaPaket > 0) {
            return [$hargaPaket, 'harga paket'];
        }

        return [null, 'tidak ada sumber'];
    }

    /**
     * @param  list<array{0: CustomerService, 1: array<string, mixed>, 2: string}>  $akanDiubah
     */
    private function tulis(array $akanDiubah): void
    {
        foreach ($akanDiubah as [$service, $perubahan, $sumber]) {
            DB::transaction(function () use ($service, $perubahan, $sumber) {
                $lama = [
                    'due_date' => $service->due_date?->format('Y-m-d'),
                    'monthly_price' => (float) $service->monthly_price,
                ];

                $service->update($perubahan);

                // Jatuh tempo baru bisa membuat pelanggan lolos jadi 'lengkap',
                // yang menentukan boleh-tidaknya masuk billing aktif. Hitung
                // ulang di sini, jangan tunggu sentuhan berikutnya.
                $service->customer?->recalculateCompleteness();

                AuditLog::create([
                    'user_id' => null,
                    'module' => 'Data Pelanggan',
                    'action' => 'backfill_legacy_service_fields',
                    'auditable_type' => CustomerService::class,
                    'auditable_id' => $service->id,
                    'old_values' => $lama,
                    'new_values' => $perubahan + ['sumber' => $sumber],
                    'created_at' => now(),
                ]);
            });
        }
    }
}
