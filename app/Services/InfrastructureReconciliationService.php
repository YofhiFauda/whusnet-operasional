<?php

namespace App\Services;

use App\Enums\SerialStatus;
use App\Models\InfrastructureReconciliationRun;
use App\Models\InventorySerial;
use App\Models\NetworkNode;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bukti cutover §16.4 #6: bandingkan empat metrik per Cabang — ODP,
 * pelanggan terhubung, port pelanggan terpakai, aset DEPLOYED — antara
 * DATA LAMA (dihitung langsung dari tabel lama: node ODP via
 * `resolvePop()`, `is_used`, assignment aktif, kolom lokasi serial) dan
 * PROYEKSI BARU (`InfrastructureHierarchyService`). Selisih ≠ 0 berarti ada
 * data yang tidak terjangkau proyeksi (mis. node yatim) dan wajib direview.
 *
 * Jalur tulis lama baru boleh ditutup setelah selisih nol selama SATU
 * siklus tutup periode penuh, tercatat di `infrastructure_reconciliation_runs`.
 */
class InfrastructureReconciliationService
{
    private const EMPTY = ['odp' => 0, 'customers' => 0, 'used_ports' => 0, 'assets' => 0];

    public function __construct(
        private readonly InfrastructureHierarchyService $hierarchy,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @return Collection<int, InfrastructureReconciliationRun>
     */
    public function run(string $period, ?User $actor = null): Collection
    {
        $legacy = $this->legacyMetrics();
        $projection = $this->hierarchy->metricsByCabang(null);
        $runKey = (string) Str::uuid();

        return DB::transaction(function () use ($legacy, $projection, $period, $actor, $runKey): Collection {
            $rows = collect();
            foreach (array_unique([...array_keys($legacy), ...array_keys($projection)]) as $cabangId) {
                $old = $legacy[$cabangId] ?? self::EMPTY;
                $new = $cabangId === 0 ? self::EMPTY : ($projection[$cabangId] ?? self::EMPTY);
                $diff = array_map(fn (string $metric): int => $new[$metric] - $old[$metric], array_combine(array_keys(self::EMPTY), array_keys(self::EMPTY)));
                $isZero = array_filter($diff) === [];
                if ($cabangId === 0 && $old === self::EMPTY) {
                    continue;
                }

                $rows->push(InfrastructureReconciliationRun::create([
                    'run_key' => $runKey,
                    'period' => $period,
                    'pop_id' => $cabangId === 0 ? null : $cabangId,
                    'legacy_metrics' => $old,
                    'projection_metrics' => $new,
                    'diff' => $diff,
                    'is_zero' => $isZero,
                    'created_by' => $actor?->id,
                ]));
            }

            return $rows;
        });
    }

    /**
     * Eksekusi TERAKHIR periode itu selisih nol di semua Cabang.
     */
    public function zeroForPeriod(string $period): bool
    {
        $latest = InfrastructureReconciliationRun::query()->where('period', $period)->latest('id')->value('run_key');
        if ($latest === null) {
            return false;
        }

        return ! InfrastructureReconciliationRun::query()->where('run_key', $latest)->where('is_zero', false)->exists();
    }

    /**
     * Metrik dari representasi LAMA, tanpa proyeksi. Kunci 0 = data yang tidak
     * bisa ditautkan ke Cabang mana pun.
     *
     * @return array<int, array{odp: int, customers: int, used_ports: int, assets: int}>
     */
    public function legacyMetrics(): array
    {
        $metrics = [];
        $bump = function (?int $cabangId, string $metric, int $amount = 1) use (&$metrics): void {
            $key = $cabangId ?? 0;
            $metrics[$key] ??= self::EMPTY;
            $metrics[$key][$metric] += $amount;
        };

        NetworkNode::query()->with('nodeType')
            ->withCount(['ports as used_ports_count' => fn ($query) => $query->where('is_used', true)])
            ->with(['ports.activeAssignment'])
            ->get()
            ->each(function (NetworkNode $odp) use ($bump): void {
                $cabangId = $odp->resolvePop()?->id;
                if ($odp->nodeType?->code === 'odp') {
                    $bump($cabangId, 'odp');
                    $bump($cabangId, 'customers', $odp->ports->filter(fn ($port): bool => $port->activeAssignment->first()?->customer_id !== null)->count());
                }
                $bump($cabangId, 'used_ports', (int) $odp->used_ports_count);
            });

        InventorySerial::query()->where('status', SerialStatus::DEPLOYED->value)->get()
            ->each(function (InventorySerial $serial) use ($bump): void {
                try {
                    $cabangId = $this->inventory->currentLocationOf($serial)?->cabangId;
                } catch (\InvalidArgumentException) {
                    $cabangId = null;
                }
                $bump($cabangId, 'assets');
            });

        return $metrics;
    }
}
