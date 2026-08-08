<?php

namespace App\Console\Commands;

use App\Enums\InvoiceType;
use App\Models\AuditLog;
use App\Models\CustomerService;
use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('billing:backfill-activation-date
    {--force : Benar-benar menulis. Tanpa ini command hanya mencetak daftar usulan}
    {--limit= : Batasi jumlah baris yang diproses, untuk uji coba bertahap}')]
#[Description('Betulkan customer_services.activation_date yang masih berisi tanggal daftar, bukan tanggal layanan menyala. Default hanya mencetak usulan.')]
class BackfillActivationDateCommand extends Command
{
    /**
     * Membetulkan data lama peninggalan bug BILLING-B0.
     *
     * Waktu pendaftaran, `activation_date` diisi `registration_date` — tanggal
     * DAFTAR, bukan tanggal layanan menyala (CustomerController::store). Dulu
     * kolom itu tidak pernah ditimpa saat aktivasi, jadi pelanggan yang daftar
     * Juni lalu aktif Juli menyimpan tanggal Juni. Aktivasi baru sudah benar
     * sejak finalVerify() menimpanya dengan `issue_date`; command ini mengurus
     * baris yang terlanjur salah.
     *
     * Urutan sumber tanggal (keputusan bisnis 2026-07-21):
     *
     *   1. `issue_date` invoice AWAL milik pelanggan itu — paling dekat dengan
     *      momen aktivasi, dan justru tanggal inilah yang dipakai menghitung
     *      prorata, jadi konsisten dengan nominal yang sudah tertagih.
     *   2. `customer_installations.finished_date` — tanggal teknisi menuntaskan
     *      pemasangan. Bisa beda beberapa hari dari verifikasi admin.
     *   3. Kalau dua-duanya kosong: JANGAN MENEBAK. Dilaporkan sebagai perlu
     *      review manual.
     *
     * Baris hasil migrasi legacy (`old_request_id`/`old_cost_id` terisi)
     * sengaja dilewati seluruhnya. Di jalur import, `activation_date` diisi dari
     * `finished_at` sistem lama (CustomerController::importCustomerServices),
     * jadi nilainya memang sudah tanggal aktivasi — bukan placeholder
     * pendaftaran. Menimpanya justru merusak data yang sudah benar.
     *
     * Default dry-run. `--force` baru menulis, dan setiap perubahan masuk
     * audit log supaya bisa ditelusuri kalau ternyata ada yang keliru.
     */
    public function handle(): int
    {
        $write = (bool) $this->option('force');
        $limit = $this->option('limit');

        if ($limit !== null && (! ctype_digit((string) $limit) || (int) $limit < 1)) {
            $this->error('--limit harus bilangan bulat positif.');

            return self::INVALID;
        }

        $query = CustomerService::query()
            ->with(['customer'])
            ->whereHas('customer', fn ($q) => $q->whereIn('status', ['active', 'suspended']))
            // Baris legacy punya activation_date dari `finished_at` sistem lama.
            ->whereNull('old_request_id')
            ->whereNull('old_cost_id')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit((int) $limit);
        }

        $services = $query->get();

        $this->info($write
            ? 'Backfill activation_date — MODE TULIS'
            : 'Backfill activation_date — mode daftar saja (tambahkan --force untuk menulis)');
        $this->newLine();

        $rows = [];
        $akanDiubah = [];
        $perluManual = 0;
        $sudahBenar = 0;

        foreach ($services as $service) {
            $sekarang = $service->activation_date?->format('Y-m-d');
            [$usulan, $sumber] = $this->tentukanTanggal($service);

            if ($usulan === null) {
                $perluManual++;
                $rows[] = [
                    $service->customer->customer_code ?? '—',
                    mb_strimwidth((string) ($service->customer->full_name ?? '—'), 0, 22, '…'),
                    $sekarang ?? '(kosong)',
                    '—',
                    'tidak ada sumber',
                    'REVIEW MANUAL',
                ];

                continue;
            }

            if ($usulan === $sekarang) {
                $sudahBenar++;

                continue;
            }

            $akanDiubah[] = [$service, $usulan, $sumber];
            $rows[] = [
                $service->customer->customer_code ?? '—',
                mb_strimwidth((string) ($service->customer->full_name ?? '—'), 0, 22, '…'),
                $sekarang ?? '(kosong)',
                $usulan,
                $sumber,
                $write ? 'DITULIS' : 'usulan',
            ];
        }

        if ($rows === []) {
            $this->line('Tidak ada baris yang perlu dibetulkan.');
            $this->line("Sudah benar: {$sudahBenar}");

            return self::SUCCESS;
        }

        $this->table(
            ['Kode', 'Nama', 'Sekarang', 'Usulan', 'Sumber', 'Status'],
            $rows
        );

        if ($write) {
            $this->tulis($akanDiubah);
        }

        $this->newLine();
        $this->line('Diperiksa       : '.$services->count());
        $this->line('Sudah benar     : '.$sudahBenar);
        $this->line(($write ? 'Diubah          : ' : 'Akan diubah     : ').count($akanDiubah));
        $this->line('Perlu manual    : '.$perluManual);

        if ($perluManual > 0) {
            $this->warn('Baris "REVIEW MANUAL" tidak disentuh — tidak ada invoice AWAL maupun catatan pemasangan untuk ditebak. Isi manual lewat halaman pelanggan.');
        }

        if (! $write && $akanDiubah !== []) {
            $this->newLine();
            $this->warn('Belum ada yang ditulis. Periksa daftar di atas, lalu jalankan ulang dengan --force.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function tentukanTanggal(CustomerService $service): array
    {
        $invoiceAwal = Invoice::where('customer_id', $service->customer_id)
            ->where('invoice_type', InvoiceType::AWAL->value)
            ->whereNotNull('issue_date')
            ->orderBy('issue_date')
            ->first();

        if ($invoiceAwal !== null) {
            return [$invoiceAwal->issue_date->format('Y-m-d'), 'invoice AWAL'];
        }

        $pemasangan = $service->customer
            ?->installations()
            ->whereNotNull('finished_date')
            ->orderByDesc('finished_date')
            ->first();

        if ($pemasangan !== null) {
            return [$pemasangan->finished_date->format('Y-m-d'), 'pemasangan'];
        }

        return [null, 'tidak ada sumber'];
    }

    /**
     * @param  list<array{0: CustomerService, 1: string, 2: string}>  $akanDiubah
     */
    private function tulis(array $akanDiubah): void
    {
        foreach ($akanDiubah as [$service, $usulan, $sumber]) {
            DB::transaction(function () use ($service, $usulan, $sumber) {
                $lama = $service->activation_date?->format('Y-m-d');

                $service->update(['activation_date' => $usulan]);

                // Jejak wajib: ini menulis massal ke kolom yang menentukan bulan
                // mana yang dilewati tagihan bulanan. Kalau ada yang keliru,
                // harus bisa ditelusuri baris per baris dan sumbernya apa.
                AuditLog::create([
                    'user_id' => null,
                    'module' => 'Data Pelanggan',
                    'action' => 'backfill_activation_date',
                    'auditable_type' => CustomerService::class,
                    'auditable_id' => $service->id,
                    'old_values' => ['activation_date' => $lama],
                    'new_values' => ['activation_date' => $usulan, 'sumber' => $sumber],
                    'created_at' => now(),
                ]);
            });
        }
    }
}
