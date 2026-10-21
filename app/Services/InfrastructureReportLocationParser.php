<?php

namespace App\Services;

use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureSlotKind;
use App\Models\FopTask;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\User;
use App\Support\InfrastructureLocation;
use Illuminate\Validation\ValidationException;

/**
 * Menerjemahkan dropdown lokasi bertingkat satu baris laporan menjadi lokasi
 * akhir (§10.1–10.2). Tingkat: POP → Mini POP → OLT/Switch → Slot →
 * PON/Port → ODP.
 *
 * Nilai per tingkat:
 * - ID angka  = dipilih.
 * - `none`    = «Dikosongkan» (null eksplisit): lokasi berhenti di induk;
 *               seluruh tingkat di bawahnya WAJIB kosong/`none`.
 * - kosong    = «Belum dipilih» — BUKAN izin memasang di induk; ditolak bila
 *               induknya terisi (kecuali tingkat ODP di jalur Switch yang
 *               memang tidak ada).
 *
 * Tingkat akar scope task wajib terisi dan sama dengan akar (tidak bisa
 * «Dikosongkan»); tingkat di atas akar hanya konteks (boleh kosong, kalau
 * diisi harus cocok). Hubungan induk–anak dicek berbasis FK — ID yang sah
 * secara global tetapi bukan anak induk terpilih ditolak. Hasil akhir tetap
 * lewat irisan scope `FopTaskInfrastructureScopeService`.
 */
class InfrastructureReportLocationParser
{
    public const NONE = 'none';

    /** @var list<string> */
    public const LEVELS = ['loc_pop', 'loc_mini_pop', 'loc_device', 'loc_slot', 'loc_port', 'loc_odp'];

    private const UNSET = '__unset__';

    public function __construct(
        private readonly InfrastructureLocationResolver $resolver,
        private readonly FopTaskInfrastructureScopeService $scopes,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function hasLocationInput(array $row): bool
    {
        foreach (self::LEVELS as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function parse(FopTask $fopTask, array $row, User $actor, string $field = 'infra_actions'): InfrastructureLocation
    {
        $values = [];
        foreach (self::LEVELS as $key) {
            $values[] = $this->normalize($row[$key] ?? null, $field);
        }

        $root = $this->scopes->rootFor($fopTask);
        $expected = $this->expectedPath($root);
        $rootDepth = array_key_last($expected);

        foreach ($expected as $depth => $expectedId) {
            $value = $values[$depth];
            if ($depth === $rootDepth && ($value === self::UNSET || $value === self::NONE)) {
                $this->fail($field, "Lokasi minimum (akar scope task: {$root->breadcrumb()}) wajib dipilih dan tidak bisa dikosongkan.");
            }
            if ($value !== self::UNSET && $value !== $expectedId) {
                $this->fail($field, "Lokasi harus berada di dalam scope task ({$root->breadcrumb()}).");
            }
        }

        $current = $root;
        $cleared = false;
        $isSwitchPath = $this->isSwitchPath($root);

        for ($depth = $rootDepth + 1; $depth < count(self::LEVELS); $depth++) {
            $value = $values[$depth];

            if ($cleared) {
                if ($value !== self::UNSET && $value !== self::NONE) {
                    $this->fail($field, 'Pilihan di bawah tingkat «Dikosongkan» tidak boleh diisi.');
                }

                continue;
            }

            if ($value === self::NONE) {
                $cleared = true;

                continue;
            }

            if ($value === self::UNSET) {
                if ($depth === 5 && $isSwitchPath) {
                    break;
                }
                $label = ['POP', 'Mini POP', 'OLT/Switch', 'Slot', 'PON/Port', 'ODP'][$depth];
                $this->fail($field, "{$label} belum dipilih. Pilih lokasi atau «Dikosongkan».");
            }

            $current = $this->child($current, $depth, (int) $value, $field);
            if ($depth === 2) {
                $isSwitchPath = $this->isSwitchPath($current);
            }
        }

        $this->scopes->assertLocationInTaskScope($fopTask, $current, $actor, $field);

        return $current;
    }

    /**
     * Ujung sambungan kabel, format `pop:ID`, `mini_pop:ID`, `node:ID`, atau
     * `port:ID` (PON/Port kanonis). Tetap wajib di dalam scope task.
     */
    public function parseEnd(FopTask $fopTask, ?string $raw, User $actor, string $field = 'infra_actions'): ?InfrastructureLocation
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        if (! preg_match('/^(pop|mini_pop|node|port):(\d+)$/', trim($raw), $matches)) {
            $this->fail($field, 'Ujung sambungan tidak dikenali.');
        }

        try {
            $location = $this->resolver->resolve($matches[1] === 'mini_pop' ? InfrastructureLocation::POP : $matches[1], (int) $matches[2]);
        } catch (\Throwable) {
            $this->fail($field, 'Ujung sambungan tidak ditemukan.');
        }

        $this->scopes->assertLocationInTaskScope($fopTask, $location, $actor, $field);

        return $location;
    }

    private function normalize(mixed $raw, string $field): int|string
    {
        if ($raw === null || $raw === '') {
            return self::UNSET;
        }
        if ($raw === self::NONE) {
            return self::NONE;
        }
        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            return (int) $raw;
        }

        $this->fail($field, 'Nilai lokasi tidak valid.');
    }

    /**
     * ID per tingkat dari Cabang sampai akar scope (indeks = tingkat).
     *
     * @return array<int, int>
     */
    private function expectedPath(InfrastructureLocation $root): array
    {
        $path = [0 => (int) $root->cabangId];
        $afterPort = false;
        foreach ($root->ancestors as $ancestor) {
            match ($ancestor['kind']) {
                InfrastructureLocation::MINI_POP => $path[1] = $ancestor['id'],
                InfrastructureLocation::SLOT => $path[3] = $ancestor['id'],
                InfrastructureLocation::PORT => [$path[4] = $ancestor['id'], $afterPort = true],
                InfrastructureLocation::NODE => $afterPort ? $path[5] = $ancestor['id'] : $path[2] = $ancestor['id'],
                default => null,
            };
        }
        ksort($path);

        // Tingkat yang hilang di tengah (mis. OLT legacy langsung di Cabang
        // tanpa Mini POP) tidak didukung bentuk dropdown ini.
        if (array_keys($path) !== range(0, count($path) - 1)) {
            throw ValidationException::withMessages(['infra_actions' => 'Akar scope task tidak mengikuti hierarki POP → Mini POP → perangkat.']);
        }

        return $path;
    }

    private function child(InfrastructureLocation $parent, int $depth, int $id, string $field): InfrastructureLocation
    {
        switch ($depth) {
            case 1:
                $miniPop = Pop::find($id);
                if ($miniPop === null || $miniPop->type !== 'mini_pop' || $miniPop->parent_id !== $parent->id) {
                    $this->fail($field, 'Mini POP bukan bagian POP terpilih.');
                }

                return $this->resolver->forPop($miniPop);
            case 2:
                $device = NetworkNode::query()->with('nodeType')->find($id);
                if ($device === null || $device->anchor_pop_id !== $parent->id
                    || InfrastructureSlotKind::forDeviceTypeCode($device->nodeType?->code) === null) {
                    $this->fail($field, 'OLT/Switch bukan milik Mini POP terpilih.');
                }

                return $this->resolver->forNode($device);
            case 3:
                $slot = InfrastructureSlot::find($id);
                if ($slot === null || $slot->network_node_id !== $parent->id) {
                    $this->fail($field, 'Slot bukan milik perangkat terpilih.');
                }

                return $this->resolver->forSlot($slot);
            case 4:
                $port = InfrastructurePort::find($id);
                if ($port === null || $port->infrastructure_slot_id !== $parent->id) {
                    $this->fail($field, 'PON/Port bukan milik slot terpilih.');
                }

                return $this->resolver->forPort($port);
            default:
                $port = InfrastructurePort::find($parent->id);
                if ($parent->kind !== InfrastructureLocation::PORT || $port?->kind !== InfrastructurePortKind::PON) {
                    $this->fail($field, 'Jalur Switch berhenti di Port — ODP tidak tersedia.');
                }
                $odp = NetworkNode::query()->with('nodeType')->find($id);
                $connected = $odp !== null && $odp->nodeType?->code === 'odp'
                    && InfrastructureConnection::query()->active()
                        ->where('source_infrastructure_port_id', $port->id)
                        ->where('target_network_node_id', $odp->id)->exists();
                if (! $connected) {
                    $this->fail($field, 'ODP tidak tersambung ke PON terpilih.');
                }

                return $this->resolver->forNode($odp);
        }
    }

    /**
     * Jalur Switch dikenali dari perangkat di rantai lokasi.
     */
    private function isSwitchPath(InfrastructureLocation $location): bool
    {
        foreach ($location->ancestors as $ancestor) {
            if ($ancestor['kind'] === InfrastructureLocation::NODE && $ancestor['type'] === 'switch_mini_pop') {
                return true;
            }
        }

        return false;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
