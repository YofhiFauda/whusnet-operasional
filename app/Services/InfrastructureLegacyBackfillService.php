<?php

namespace App\Services;

use App\Enums\InfrastructureCapacityUnit;
use App\Enums\InfrastructureRecordStatus;
use App\Enums\InfrastructureSlotKind;
use App\Models\AuditLog;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructureLegacyMapping;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use App\Models\OltSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Backfill representasi lama ke posisi kanonis — HANYA hubungan yang
 * terbukti FK + nomor unik (§7.3, Task 3):
 *
 * - `olt_slots` (FK chassis + nomor unik per chassis) → `infrastructure_slots`.
 * - `olt_slots.card_node_id` → dicatat sebagai isi slot (kartu = isi slot, §2).
 *
 * Yang TIDAK bisa dibuktikan masuk `infrastructure_legacy_mappings` status
 * `ambiguous` untuk keputusan operator — tidak pernah disambung otomatis:
 * node `pon_port` (tanpa nomor slot/PON), `aggregation_switch` di POP
 * Cabang (Mini POP tujuan tidak diketahui; JANGAN buat Mini POP fiktif),
 * dan uplink ODP lama (feeder/PON node, bukan koneksi PON terverifikasi).
 *
 * Idempoten: mapping yang sudah ada (termasuk keputusan operator) tidak
 * ditimpa. Tabel lama tidak pernah diubah.
 */
class InfrastructureLegacyBackfillService
{
    /**
     * @return array{slots_created: int, slots_linked: int, ambiguous: int}
     */
    public function run(): array
    {
        $summary = ['slots_created' => 0, 'slots_linked' => 0, 'ambiguous' => 0];

        DB::transaction(function () use (&$summary): void {
            OltSlot::query()->with('chassis.nodeType')->orderBy('id')->get()
                ->each(function (OltSlot $legacy) use (&$summary): void {
                    $this->mapOltSlot($legacy, $summary);
                });

            $this->nodesOfType('pon_port')->each(function (NetworkNode $node) use (&$summary): void {
                $summary['ambiguous'] += (int) $this->ambiguous('pon_port_node', $node,
                    'PON lama berupa node tanpa nomor slot/PON. Operator memilih Slot OLT dan nomor PON yang benar sebelum dipetakan.');
            });

            $this->nodesOfType('aggregation_switch')->each(function (NetworkNode $node) use (&$summary): void {
                $summary['ambiguous'] += (int) $this->ambiguous('aggregation_switch_node', $node,
                    'Switch agregasi lama berada di POP Cabang. Mini POP tujuan tidak dapat dibuktikan; tidak dipindah otomatis dan tidak dibuat Mini POP baru.');
            });

            $this->nodesOfType('odp')->each(function (NetworkNode $node) use (&$summary): void {
                if (InfrastructureConnection::query()->active()->where('target_network_node_id', $node->id)->exists()) {
                    return;
                }
                $summary['ambiguous'] += (int) $this->ambiguous('odp_uplink', $node,
                    'Uplink ODP lama melalui parent feeder/PON node, bukan koneksi PON terverifikasi. Operator menghubungkan ODP ke PON yang benar.',
                    ['parent_id' => $node->parent_id, 'anchor_pop_id' => $node->anchor_pop_id]);
            });
        });

        return $summary;
    }

    public function syncOltSlot(OltSlot $legacy): InfrastructureSlot
    {
        return DB::transaction(function () use ($legacy): InfrastructureSlot {
            $legacy = OltSlot::query()->lockForUpdate()->findOrFail($legacy->id);
            $summary = ['slots_created' => 0, 'slots_linked' => 0, 'ambiguous' => 0];
            $this->mapOltSlot($legacy, $summary);
            $mapping = InfrastructureLegacyMapping::query()->where('legacy_type', 'olt_slot')->where('legacy_id', $legacy->id)->firstOrFail();
            if ($mapping->status !== InfrastructureLegacyMapping::MAPPED || $mapping->new_type !== 'infrastructure_slot') {
                throw new RuntimeException('Slot lama belum memiliki pemetaan kanonis yang terverifikasi.');
            }

            return InfrastructureSlot::query()->findOrFail($mapping->new_id);
        });
    }

    /**
     * Membatalkan hasil backfill. Ditolak bila slot hasil backfill sudah
     * dipakai (punya port, koneksi, atau aset) — menghapusnya akan memutus
     * data baru yang sah.
     */
    public function rollback(): void
    {
        AuditLog::create([
            'user_id' => auth()->id(), 'module' => 'Pemetaan Infrastruktur', 'action' => 'legacy_rollback_snapshot',
            'auditable_type' => InfrastructureLegacyMapping::class, 'auditable_id' => 0,
            'old_values' => ['mappings' => InfrastructureLegacyMapping::query()->get()->toArray()],
            'new_values' => null, 'created_at' => now(),
        ]);

        DB::transaction(function (): void {
            if (InfrastructureConnection::query()->exists()) {
                throw new RuntimeException('Rollback ditolak: koneksi kanonis harus dipertahankan.');
            }
            $all = InfrastructureLegacyMapping::query()->lockForUpdate()->get();
            foreach ($all as $mapping) {
                $automatic = $mapping->status === InfrastructureLegacyMapping::AMBIGUOUS
                    || ($mapping->status === InfrastructureLegacyMapping::MAPPED
                        && (($mapping->legacy_type === 'olt_slot' && array_key_exists('created', $mapping->evidence ?? []))
                            || ($mapping->legacy_type === 'olt_card_node' && isset($mapping->evidence['olt_slot_id']))));
                if (! $automatic || $mapping->decided_by !== null || $mapping->decided_at !== null || $mapping->decision !== null) {
                    throw new RuntimeException('Rollback ditolak: keputusan operator atau hasil pemetaan manual harus dipertahankan.');
                }
            }
            $mappings = InfrastructureLegacyMapping::query()->where('legacy_type', 'olt_slot')
                ->where('new_type', 'infrastructure_slot')->get();
            $createdIds = $mappings->filter(fn (InfrastructureLegacyMapping $mapping): bool => (bool) ($mapping->evidence['created'] ?? false))
                ->pluck('new_id')->all();
            $linkedIds = $mappings->pluck('new_id')->diff($createdIds)->values()->all();

            if ($createdIds !== [] && ($this->slotsInUse($createdIds))) {
                throw new RuntimeException('Slot hasil backfill sudah dipakai (port/aset). Rollback dibatalkan; rekonsiliasi manual diperlukan.');
            }

            InfrastructureSlot::query()->whereIn('id', $linkedIds)->update(['legacy_olt_slot_id' => null]);
            InfrastructureSlot::query()->whereIn('id', $createdIds)->delete();
            InfrastructureLegacyMapping::query()->delete();
        });
    }

    /**
     * @param  array{slots_created: int, slots_linked: int, ambiguous: int}  $summary
     */
    private function mapOltSlot(OltSlot $legacy, array &$summary): void
    {
        $mapped = InfrastructureLegacyMapping::query()->where('legacy_type', 'olt_slot')->where('legacy_id', $legacy->id)->first();
        if ($mapped !== null) {
            if ($mapped->status === InfrastructureLegacyMapping::MAPPED && $mapped->new_type === 'infrastructure_slot') {
                $this->syncCardMapping($legacy, InfrastructureSlot::query()->findOrFail($mapped->new_id), $mapped->pop_id);
            }

            return;
        }

        $chassis = $legacy->chassis;
        if ($chassis === null || InfrastructureSlotKind::forDeviceTypeCode($chassis->nodeType?->code) !== InfrastructureSlotKind::OLT) {
            $summary['ambiguous'] += (int) $this->ambiguousRaw('olt_slot', $legacy->id, null,
                'Slot lama tidak menunjuk chassis OLT yang valid.', ['chassis_node_id' => $legacy->chassis_node_id]);

            return;
        }

        $existing = InfrastructureSlot::query()->where('network_node_id', $chassis->id)->where('number', $legacy->number)->first();
        $created = false;
        if ($existing !== null && $existing->legacy_olt_slot_id !== null && $existing->legacy_olt_slot_id !== $legacy->id) {
            $summary['ambiguous'] += (int) $this->ambiguousRaw('olt_slot', $legacy->id, $chassis,
                'Nomor slot sudah dipetakan ke slot lama lain.', ['existing_slot_id' => $existing->id]);

            return;
        }

        if ($existing === null) {
            $existing = InfrastructureSlot::create([
                'network_node_id' => $chassis->id,
                'number' => $legacy->number,
                'kind' => InfrastructureSlotKind::OLT,
                'status' => InfrastructureRecordStatus::BELUM_DIDATA,
                'capacity_unit' => InfrastructureCapacityUnit::PON,
                'legacy_olt_slot_id' => $legacy->id,
            ]);
            $created = true;
            $summary['slots_created']++;
        } else {
            $existing->update(['legacy_olt_slot_id' => $legacy->id]);
            $summary['slots_linked']++;
        }

        $popId = $chassis->resolvePop()?->id;
        InfrastructureLegacyMapping::create([
            'legacy_type' => 'olt_slot',
            'legacy_id' => $legacy->id,
            'new_type' => 'infrastructure_slot',
            'new_id' => $existing->id,
            'status' => InfrastructureLegacyMapping::MAPPED,
            'pop_id' => $popId,
            'reason' => 'Terbukti lewat FK chassis_node_id + nomor slot unik per chassis.',
            'evidence' => ['created' => $created, 'chassis_node_id' => $chassis->id, 'number' => $legacy->number],
        ]);

        $this->syncCardMapping($legacy, $existing, $popId);
    }

    private function syncCardMapping(OltSlot $legacy, InfrastructureSlot $existing, ?int $popId): void
    {
        if ($legacy->card_node_id !== null) {
            $mapping = InfrastructureLegacyMapping::query()->firstOrCreate(
                ['legacy_type' => 'olt_card_node', 'legacy_id' => $legacy->card_node_id],
                [
                    'new_type' => 'infrastructure_slot',
                    'new_id' => $existing->id,
                    'status' => InfrastructureLegacyMapping::MAPPED,
                    'pop_id' => $popId,
                    'reason' => 'Kartu lama tercatat di olt_slots.card_node_id; menjadi isi slot.',
                    'evidence' => ['olt_slot_id' => $legacy->id],
                ],
            );
            if ($mapping->status !== InfrastructureLegacyMapping::MAPPED || $mapping->new_type !== 'infrastructure_slot' || $mapping->new_id !== $existing->id) {
                throw new RuntimeException('Kartu lama sudah memiliki pemetaan lain; keputusan operator tidak ditimpa.');
            }
        }
    }

    /**
     * @return Collection<int, NetworkNode>
     */
    private function nodesOfType(string $typeCode)
    {
        return NetworkNode::query()->whereHas('nodeType', fn ($query) => $query->where('code', $typeCode))
            ->orderBy('id')->get();
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function ambiguous(string $legacyType, NetworkNode $node, string $reason, array $evidence = []): bool
    {
        return $this->ambiguousRaw($legacyType, $node->id, $node, $reason, $evidence + ['code' => $node->code]);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function ambiguousRaw(string $legacyType, int $legacyId, ?NetworkNode $node, string $reason, array $evidence): bool
    {
        $mapping = InfrastructureLegacyMapping::query()->firstOrCreate(
            ['legacy_type' => $legacyType, 'legacy_id' => $legacyId],
            [
                'status' => InfrastructureLegacyMapping::AMBIGUOUS,
                'pop_id' => $node?->resolvePop()?->id,
                'reason' => $reason,
                'evidence' => $evidence,
            ],
        );

        return $mapping->wasRecentlyCreated;
    }

    /**
     * @param  list<int>  $slotIds
     */
    private function slotsInUse(array $slotIds): bool
    {
        if (DB::table('infrastructure_ports')->whereIn('infrastructure_slot_id', $slotIds)->exists()) {
            return true;
        }

        return Schema::hasColumn('inventory_serials', 'current_infrastructure_slot_id')
            && DB::table('inventory_serials')->whereIn('current_infrastructure_slot_id', $slotIds)->exists();
    }
}
