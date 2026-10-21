<?php

namespace App\Services;

use App\Enums\SerialStatus;
use App\Models\AuditLog;
use App\Models\InfrastructureLegacyMapping;
use App\Models\InfrastructureReconciliationRun;
use App\Models\InfrastructureSlot;
use App\Models\InventorySerial;
use App\Models\NetworkNode;
use App\Models\OltSlot;
use App\Models\User;
use App\Support\BookPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    private const SLOT_EMPTY = ['legacy_slots' => 0, 'legacy_cards' => 0];

    public function __construct(
        private readonly InfrastructureHierarchyService $hierarchy,
        private readonly InventoryService $inventory,
    ) {}

    public const EVIDENCE_VERSION = 1;

    /**
     * Finalization can only name a closed calendar period. Observation time
     * always comes from the server clock, never from the requested period.
     *
     * @return Collection<int, InfrastructureReconciliationRun>
     */
    public function run(string $period, ?User $actor = null, string $trigger = 'manual'): Collection
    {
        $this->assertClosedPeriod($period);

        return $this->snapshot($period, $actor, $trigger, 'closing');
    }

    /**
     * Daily observations carry the actual current period; callers cannot
     * backdate them through a period option.
     *
     * @return Collection<int, InfrastructureReconciliationRun>
     */
    public function observe(?User $actor = null, string $trigger = 'manual'): Collection
    {
        return $this->snapshot(now()->format('Y-m'), $actor, $trigger, 'observation');
    }

    /**
     * @return Collection<int, InfrastructureReconciliationRun>
     */
    private function snapshot(string $period, ?User $actor, string $trigger, string $kind): Collection
    {
        if (! in_array($trigger, ['manual', 'schedule'], true)) {
            throw ValidationException::withMessages(['trigger' => 'Pemicu rekonsiliasi tidak valid.']);
        }

        return DB::transaction(function () use ($period, $actor, $trigger, $kind): Collection {
            $legacy = $this->legacyMetrics();
            $projection = $this->hierarchy->metricsByCabang(null);
            $slotMetrics = $this->legacySlotMetrics();
            $runKey = (string) Str::uuid();
            $observedAt = now();
            $rows = collect();
            $cabangIds = array_unique([...array_keys($legacy), ...array_keys($projection), ...array_keys($slotMetrics['legacy'])]);
            // Empty installations still leave an observation, never silent success.
            if ($cabangIds === []) {
                $cabangIds = [0];
            }
            foreach ($cabangIds as $cabangId) {
                $old = $legacy[$cabangId] ?? self::EMPTY;
                $new = $cabangId === 0 ? self::EMPTY : ($projection[$cabangId] ?? self::EMPTY);
                $old += $slotMetrics['legacy'][$cabangId] ?? self::SLOT_EMPTY;
                $new += $cabangId === 0 ? self::SLOT_EMPTY : ($slotMetrics['projection'][$cabangId] ?? self::SLOT_EMPTY);
                $diff = [];
                foreach ($old as $metric => $value) {
                    $diff[$metric] = $new[$metric] - $value;
                }
                $rows->push(InfrastructureReconciliationRun::create([
                    'run_key' => $runKey,
                    'period' => $period,
                    'pop_id' => $cabangId === 0 ? null : $cabangId,
                    'legacy_metrics' => $old,
                    'projection_metrics' => $new,
                    'diff' => $diff,
                    'is_zero' => array_filter($diff) === [],
                    'created_by' => $actor?->id,
                    'trigger' => $trigger,
                    'kind' => $kind,
                    'observed_at' => $observedAt,
                    'evidence_version' => self::EVIDENCE_VERSION,
                ]));
            }

            return $rows;
        });
    }

    private function assertClosedPeriod(string $period): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $period) || ! BookPeriod::isLocked($period)) {
            throw ValidationException::withMessages(['period' => 'Periode harus berformat YYYY-MM dan sudah ditutup.']);
        }
    }

    /**
     * A complete cycle needs a scheduled observation on every calendar day,
     * followed by a scheduled closing on day one of the following month.
     * Every known mismatch counts, including older and manual snapshots.
     * Unversioned historical snapshots cannot supply missing daily coverage.
     */
    public function zeroForPeriod(string $period): bool
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $period) || ! BookPeriod::isLocked($period)) {
            return false;
        }
        $start = CarbonImmutable::createFromFormat('!Y-m', $period);
        $end = $start->addMonth();
        $rows = InfrastructureReconciliationRun::query()
            ->where(fn ($query) => $query->where('period', $period)
                ->orWhere(fn ($within) => $within->where('observed_at', '>=', $start)->where('observed_at', '<', $end))
                ->orWhere(fn ($old) => $old->whereNull('observed_at')->where('created_at', '>=', $start)->where('created_at', '<', $end)))
            ->get();
        if ($rows->isEmpty() || $rows->contains(fn (InfrastructureReconciliationRun $row): bool => ! $this->rowIsZero($row))) {
            return false;
        }
        $versioned = $rows->where('evidence_version', self::EVIDENCE_VERSION)->where('trigger', 'schedule');
        for ($date = $start; $date->lt($end); $date = $date->addDay()) {
            $observations = $versioned->filter(fn (InfrastructureReconciliationRun $row): bool => $row->kind === 'observation'
                && $row->observed_at?->isSameDay($date));
            if (! $this->hasPopulation($observations)) {
                return false;
            }
        }
        $closing = $versioned->filter(fn (InfrastructureReconciliationRun $row): bool => $row->period === $period
            && $row->kind === 'closing' && $row->observed_at?->isSameDay($end));

        return $this->hasPopulation($closing);
    }

    private function rowIsZero(InfrastructureReconciliationRun $row): bool
    {
        return $row->is_zero
            && array_filter($row->diff ?? []) === []
            && $row->legacy_metrics === $row->projection_metrics;
    }

    /**
     * @param  Collection<int, InfrastructureReconciliationRun>  $rows
     */
    private function hasPopulation(Collection $rows): bool
    {
        return $rows->sum(fn (InfrastructureReconciliationRun $row): int => array_sum($row->legacy_metrics ?? [])) > 0;
    }

    /**
     * Requirements that need human review carry seven explicit written
     * references. #6, unresolved mappings and the live state are also checked
     * automatically. The audit is the durable switch; env is emergency only.
     *
     * @param  array<int, array{status: string, reference: string}>  $evidence
     */
    public function closeLegacyWrites(string $period, array $evidence, User $actor): void
    {
        $this->assertClosedPeriod($period);
        foreach (range(1, 7) as $number) {
            $item = $evidence[$number] ?? null;
            if (! is_array($item) || ($item['status'] ?? null) !== 'complete'
                || ! is_string($item['reference'] ?? null) || trim($item['reference']) === '') {
                throw ValidationException::withMessages(['evidence' => "Bukti tertulis selesai #{$number} wajib disertakan."]);
            }
        }
        if (InfrastructureLegacyMapping::query()->where('status', InfrastructureLegacyMapping::AMBIGUOUS)->exists()) {
            throw ValidationException::withMessages(['evidence' => 'Mapping ambigu harus diselesaikan operator sebelum cutover.']);
        }
        if (! $this->zeroForPeriod($period)) {
            throw ValidationException::withMessages(['period' => 'Belum ada bukti selisih nol satu siklus tutup periode penuh.']);
        }
        // A failed live comparison remains written evidence of the mismatch.
        $live = $this->run($period, $actor);
        if (! $this->hasPopulation($live) || $live->contains(fn (InfrastructureReconciliationRun $row): bool => ! $this->rowIsZero($row))) {
            throw ValidationException::withMessages(['evidence' => 'Kondisi terkini berbeda atau populasi kosong; cutover ditolak.']);
        }
        DB::transaction(function () use ($period, $evidence, $actor, $live): void {
            if (InfrastructureLegacyMapping::query()->where('status', InfrastructureLegacyMapping::AMBIGUOUS)->exists()) {
                throw ValidationException::withMessages(['evidence' => 'Mapping ambigu harus diselesaikan operator sebelum cutover.']);
            }
            if (! $this->zeroForPeriod($period)) {
                throw ValidationException::withMessages(['period' => 'Belum ada bukti selisih nol satu siklus tutup periode penuh.']);
            }
            AuditLog::create([
                'user_id' => $actor->id, 'module' => 'InfrastructureCutover', 'action' => 'legacy_writes_closed',
                'auditable_type' => User::class, 'auditable_id' => $actor->id,
                'old_values' => ['legacy_writes_enabled' => true],
                'new_values' => ['legacy_writes_enabled' => false, 'period' => $period, 'evidence' => $evidence,
                    'live_run_key' => $live->first()->run_key, 'evidence_version' => self::EVIDENCE_VERSION],
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Verify each old slot/card against its own canonical mapping. New-only
     * slots are intentionally outside this parity check.
     *
     * @return array{legacy: array<int, array<string, int>>, projection: array<int, array<string, int>>}
     */
    private function legacySlotMetrics(): array
    {
        $legacy = [];
        $projection = [];
        $slots = InfrastructureSlot::query()->get()->keyBy('id');
        $mappings = InfrastructureLegacyMapping::query()->whereIn('legacy_type', ['olt_slot', 'olt_card_node'])->get()
            ->keyBy(fn (InfrastructureLegacyMapping $mapping): string => $mapping->legacy_type.':'.$mapping->legacy_id);
        foreach (OltSlot::query()->with('chassis.nodeType')->get() as $old) {
            $cabangId = $old->chassis?->resolvePop()?->id ?? 0;
            $legacy[$cabangId] ??= ['legacy_slots' => 0, 'legacy_cards' => 0];
            $projection[$cabangId] ??= ['legacy_slots' => 0, 'legacy_cards' => 0];
            $legacy[$cabangId]['legacy_slots']++;
            $mapping = $mappings->get('olt_slot:'.$old->id);
            $slot = $mapping?->status === InfrastructureLegacyMapping::MAPPED && $mapping->new_type === 'infrastructure_slot'
                ? $slots->get($mapping->new_id) : null;
            $matches = $slot !== null
                && $slot->network_node_id === (int) $old->chassis_node_id
                && $slot->number === (int) $old->number
                && (int) $slot->legacy_olt_slot_id === (int) $old->id;
            $projection[$cabangId]['legacy_slots'] += (int) $matches;
            if ($old->card_node_id !== null) {
                $legacy[$cabangId]['legacy_cards']++;
                $card = $mappings->get('olt_card_node:'.$old->card_node_id);
                $projection[$cabangId]['legacy_cards'] += (int) ($matches && $card?->status === InfrastructureLegacyMapping::MAPPED
                    && $card->new_type === 'infrastructure_slot' && (int) $card->new_id === $slot->id
                    && (int) ($card->evidence['olt_slot_id'] ?? 0) === (int) $old->id);
            }
        }

        return ['legacy' => $legacy, 'projection' => $projection];
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
