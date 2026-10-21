<?php

namespace App\Console\Commands;

use App\Models\InfrastructureLegacyMapping;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\Pop;
use App\Models\User;
use App\Services\InfrastructureLegacyDecisionService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Alat operator untuk kasus legacy ambigu (rancangan hierarki §7.3, §16.4).
 * Tanpa opsi --decide: hanya menampilkan daftar (read-only). Dengan
 * --decide: menjalankan SATU keputusan eksplisit lewat
 * InfrastructureLegacyDecisionService (beralasan, beraudit).
 */
class InfrastructureLegacyMappingsCommand extends Command
{
    protected $signature = 'infrastructure:legacy-mappings
        {--status=ambiguous : Filter status (ambiguous|mapped|rejected|all)}
        {--decide= : ID mapping yang diputuskan}
        {--action= : map-pon|map-odp|map-switch|restore-location|reject}
        {--slot= : ID infrastructure_slots (map-pon)}
        {--pon= : Nomor PON baru (map-pon) / ID infrastructure_ports (map-odp)}
        {--mini-pop= : ID Mini POP tujuan (map-switch)}
        {--apply : Terapkan restore-location; default hanya pratinjau}
        {--capacity-unit= : Konfirmasi satuan kapasitas lama active_customers (map-pon)}
        {--override : Abaikan PON leluhur yang sudah dipetakan (map-odp)}
        {--override-reason= : Alasan eksplisit override PON (map-odp)}
        {--clear-self-uplink : Kosongkan uplink Mini POP yang menunjuk Switch itu sendiri (map-switch)}
        {--verified : Tandai koneksi terverifikasi (map-odp)}
        {--operator= : ID user operator}
        {--reason= : Alasan keputusan}';

    protected $description = 'Daftar dan keputusan operator atas pemetaan infrastruktur legacy yang ambigu';

    public function handle(InfrastructureLegacyDecisionService $decisions): int
    {
        if ($this->option('decide') === null) {
            $status = $this->option('status');
            $rows = InfrastructureLegacyMapping::query()
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->orderBy('legacy_type')->orderBy('legacy_id')->get()
                ->map(fn (InfrastructureLegacyMapping $mapping): array => [
                    $mapping->id, $mapping->legacy_type, $mapping->legacy_id, $mapping->status,
                    $mapping->pop_id, $mapping->evidence['code'] ?? '', $mapping->reason,
                ]);
            $this->table(['ID', 'Tipe lama', 'ID lama', 'Status', 'POP', 'Kode', 'Alasan'], $rows);

            return self::SUCCESS;
        }

        $mapping = InfrastructureLegacyMapping::findOrFail((int) $this->option('decide'));
        $operator = User::findOrFail((int) $this->option('operator'));
        $reason = (string) $this->option('reason');

        try {
            if ($this->option('action') === 'restore-location') {
                $plan = $decisions->restoreOrphanLocation($mapping, $operator, $reason, ! (bool) $this->option('apply'));
                $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            match ($this->option('action')) {
                'map-pon' => $decisions->mapPonNode($mapping, InfrastructureSlot::findOrFail((int) $this->option('slot')), (int) $this->option('pon'), $operator, $reason, $this->option('capacity-unit')),
                'map-odp' => $decisions->mapOdpUplink($mapping, InfrastructurePort::findOrFail((int) $this->option('pon')), $operator, $reason, (bool) $this->option('verified'), (bool) $this->option('override'), $this->option('override-reason')),
                'map-switch' => $decisions->mapAggregationSwitch($mapping, Pop::findOrFail((int) $this->option('mini-pop')), $operator, $reason, (bool) $this->option('clear-self-uplink')),
                'reject' => $decisions->reject($mapping, $operator, $reason),
                default => throw new InvalidArgumentException('Aksi harus map-pon, map-odp, map-switch, restore-location, atau reject.'),
            };
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('action') === 'map-pon' && $this->option('capacity-unit') === null) {
            $this->warn('Satuan kapasitas belum dikonfirmasi; kapasitas dan ambang lama tidak disalin. Isi manual setelah satuan pelanggan aktif dipastikan.');
        }

        $this->info("Mapping #{$mapping->id} diputuskan: ".$mapping->fresh()->status);

        return self::SUCCESS;
    }
}
