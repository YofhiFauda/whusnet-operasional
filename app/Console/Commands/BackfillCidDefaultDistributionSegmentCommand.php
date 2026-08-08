<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('customers:backfill-cid-xx-segment
    {--force : Benar-benar menulis. Tanpa ini command hanya mencetak daftar usulan}
    {--limit= : Batasi jumlah baris yang diproses, untuk uji coba bertahap}')]
#[Description('Betulkan CID lama yang segmen distribusinya "XX" (bug default lama) jadi "0" sesuai skema resmi di ID_NUMBERING_RULES.md. Default hanya mencetak usulan.')]
class BackfillCidDefaultDistributionSegmentCommand extends Command
{
    /**
     * Membetulkan data lama peninggalan bug default CID.
     *
     * Dulu Pop::generateComplexCid() memakai "XX" sebagai default segmen
     * distribusi kalau customer belum di-assign distribusi manapun. Itu
     * BUKAN bagian dari skema penomoran resmi (lihat ID_NUMBERING_RULES.md,
     * Skema 2/3) — default yang benar adalah "0". Sudah diperbaiki di
     * Pop::generateComplexCid() untuk CID baru; command ini membetulkan
     * CID lama yang terlanjur ke-generate dengan "XX".
     *
     * SENGAJA cuma menyasar customer dengan distribution_id NULL DAN cid
     * mengandung "XX" tepat sebelum segmen registration_prefix milik
     * POP-nya (mis. "...XXRQ000004"). Segmen mini POP TIDAK disentuh sama
     * sekali — kode "1"/"C1"/dst di CID lama bisa jadi mini POP sungguhan
     * yang di-assign lewat Master POP, bukan hasil fallback bug, dan tidak
     * bisa dibedakan cuma dari string CID-nya. Lihat diskusi di commit ini.
     *
     * Default dry-run. `--force` baru menulis, dan setiap perubahan masuk
     * audit log.
     */
    public function handle(): int
    {
        $write = (bool) $this->option('force');
        $limit = $this->option('limit');

        if ($limit !== null && (! ctype_digit((string) $limit) || (int) $limit < 1)) {
            $this->error('--limit harus bilangan bulat positif.');

            return self::INVALID;
        }

        $query = Customer::query()
            ->with(['pop'])
            ->whereNotNull('cid')
            ->whereNull('distribution_id')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit((int) $limit);
        }

        $this->info($write
            ? 'Backfill segmen distribusi CID (XX -> 0) — MODE TULIS'
            : 'Backfill segmen distribusi CID (XX -> 0) — mode daftar saja (tambahkan --force untuk menulis)');
        $this->newLine();

        $rows = [];
        $akanDiubah = [];
        $diperiksa = 0;

        foreach ($query->cursor() as $customer) {
            $diperiksa++;

            $pop = $customer->pop;
            $regPrefix = $pop?->registration_prefix;
            $cid = (string) $customer->cid;

            if (! $pop || ! $regPrefix || ! str_contains($cid, 'XX'.$regPrefix)) {
                continue;
            }

            $cidBaru = str_replace('XX'.$regPrefix, '0'.$regPrefix, $cid);

            if ($cidBaru === $cid) {
                continue;
            }

            $akanDiubah[] = [$customer, $cid, $cidBaru];
            $rows[] = [
                $customer->customer_code ?? '—',
                mb_strimwidth((string) ($customer->full_name ?? '—'), 0, 22, '…'),
                $cid,
                $cidBaru,
                $write ? 'DITULIS' : 'usulan',
            ];
        }

        if ($rows === []) {
            $this->line('Tidak ada CID yang perlu dibetulkan.');
            $this->line("Diperiksa: {$diperiksa}");

            return self::SUCCESS;
        }

        $this->table(
            ['Kode Registrasi', 'Nama', 'CID Lama', 'CID Baru', 'Status'],
            $rows
        );

        if ($write) {
            $this->tulis($akanDiubah);
        }

        $this->newLine();
        $this->line('Diperiksa       : '.$diperiksa);
        $this->line(($write ? 'Diubah          : ' : 'Akan diubah     : ').count($akanDiubah));

        if (! $write) {
            $this->newLine();
            $this->warn('Belum ada yang ditulis. Periksa daftar di atas — CID dipakai juga sbg dasar PPPOE username, cek dulu ke Mikrotik/RADIUS kalau perlu — lalu jalankan ulang dengan --force.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{0: Customer, 1: string, 2: string}>  $akanDiubah
     */
    private function tulis(array $akanDiubah): void
    {
        foreach ($akanDiubah as [$customer, $cidLama, $cidBaru]) {
            DB::transaction(function () use ($customer, $cidLama, $cidBaru) {
                $customer->update(['cid' => $cidBaru]);

                AuditLog::create([
                    'user_id' => null,
                    'module' => 'Data Pelanggan',
                    'action' => 'backfill_cid_xx_segment',
                    'auditable_type' => Customer::class,
                    'auditable_id' => $customer->id,
                    'old_values' => ['cid' => $cidLama],
                    'new_values' => ['cid' => $cidBaru],
                    'created_at' => now(),
                ]);
            });
        }
    }
}
