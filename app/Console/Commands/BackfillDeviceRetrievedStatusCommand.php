<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ParsesLegacySqlDump;
use App\Models\Customer;
use App\Models\CustomerDevice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill `customer_devices.device_retrieved_at` buat pelanggan `terminated`
 * hasil migrasi legacy — sumbernya `prosedure_permintaan_wifi.STATUSTINDAKANALAT`
 * di dump SQL lama (kolom per-request, bukan `riwayat_pelanggan` yang IDPERMINTAAN-nya
 * kosong buat event "Alat telah kembali", jadi gak bisa dipetakan ke customer).
 */
class BackfillDeviceRetrievedStatusCommand extends Command
{
    use ParsesLegacySqlDump;

    protected $signature = 'app:backfill-device-retrieved {--dry-run : Preview tanpa nulis ke DB}';

    protected $description = 'Backfill Status Alat (device_retrieved_at) pelanggan terminated dari dump SQL legacy';

    /**
     * File dump → pop_id tujuan. Sesuai scoping old_request_id per-POP
     * (lihat docs/project_legacy_migration_id_collision.md) — RQ di kedua
     * file bisa sama angka, tapi customer-nya beda POP.
     */
    private const FILE_POP_MAP = [
        'jetis_db_aplikasi_jetis.sql' => 1, // Pop "Jetis"
        'sand_db_sandya.sql' => 9,          // Pop "Sandya"
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $totalRetrieved = 0;
        $totalNotRetrieved = 0;
        $totalUnmatched = 0;

        foreach (self::FILE_POP_MAP as $fileName => $popId) {
            $sqlPath = base_path($fileName);
            if (! file_exists($sqlPath)) {
                $this->warn("Lewati {$fileName} — file gak ditemukan di root project.");

                continue;
            }

            $this->info("Parsing {$fileName} (pop_id={$popId})...");
            $sql = file_get_contents($sqlPath);
            $rows = $this->parseTableData($sql, 'prosedure_permintaan_wifi');

            // Ambil baris PUTUS terakhir per IDPERMINTAAN (kalau ada duplikat RQ
            // di dump, baris belakangan yang menang — representasi state terkini).
            $putusByRequestId = [];
            foreach ($rows as $row) {
                if (($row['STATUS'] ?? null) !== 'PUTUS') {
                    continue;
                }
                $requestId = trim((string) ($row['IDPERMINTAAN'] ?? ''));
                if ($requestId === '') {
                    continue;
                }
                $putusByRequestId[$requestId] = $row;
            }

            $customers = Customer::where('pop_id', $popId)
                ->where('status', 'terminated')
                ->whereIn('old_request_id', array_keys($putusByRequestId))
                ->get();

            $this->line('  PUTUS rows: '.count($putusByRequestId).' | Customer terminated cocok: '.$customers->count());

            DB::transaction(function () use ($customers, $putusByRequestId, $dryRun, &$totalRetrieved, &$totalNotRetrieved) {
                foreach ($customers as $customer) {
                    $row = $putusByRequestId[$customer->old_request_id];
                    $statusAlat = trim((string) ($row['STATUSTINDAKANALAT'] ?? ''));
                    $retrieved = $statusAlat === 'Sudah diambil';

                    $retrievedAt = null;
                    if ($retrieved) {
                        $updatedAtRaw = $row['updated_at'] ?? null;
                        $retrievedAt = ($updatedAtRaw && ! str_starts_with($updatedAtRaw, '0000-00-00'))
                            ? Carbon::parse($updatedAtRaw)
                            : now();
                        $totalRetrieved++;
                    } else {
                        $totalNotRetrieved++;
                    }

                    if ($dryRun) {
                        continue;
                    }

                    $device = $customer->customerDevice;
                    if (! $device) {
                        // Belum pernah ada record CustomerDevice (customer ini gak
                        // pernah lewat alur instalasi sistem baru — hasil migrasi
                        // langsung). device_type wajib diisi, dikasih placeholder.
                        CustomerDevice::create([
                            'customer_id' => $customer->id,
                            'device_type' => 'Data Migrasi Legacy',
                            'device_retrieved_at' => $retrievedAt,
                        ]);
                    } elseif ($retrieved && ! $device->device_retrieved_at) {
                        $device->update(['device_retrieved_at' => $retrievedAt]);
                    }
                }
            });

            $totalUnmatched += count($putusByRequestId) - $customers->count();
        }

        $this->newLine();
        $this->info("Selesai{$this->dryRunSuffix($dryRun)}.");
        $this->line("  Sudah diambil: {$totalRetrieved}");
        $this->line("  Belum diambil (kosong/Proses ambil di data lama): {$totalNotRetrieved}");
        $this->line("  RQ PUTUS legacy tanpa customer terminated cocok di sistem baru: {$totalUnmatched}");

        return self::SUCCESS;
    }

    private function dryRunSuffix(bool $dryRun): string
    {
        return $dryRun ? ' (DRY RUN — gak ada perubahan ditulis)' : '';
    }
}
