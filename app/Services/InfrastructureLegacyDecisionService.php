<?php

namespace App\Services;

use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureRecordStatus;
use App\Models\AuditLog;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructureLegacyMapping;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\NetworkPortAssignment;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Keputusan operator atas kasus legacy ambigu (§7.3, §16.4): pemetaan data
 * NYATA wajib eksplisit, beralasan, tercatat pelaku + audit. Satu mapping
 * hanya bisa diputuskan sekali (status `ambiguous` → `mapped`/`rejected`).
 * Baris lama tidak pernah dihapus atau diubah parent-nya — kecuali Switch
 * agregasi yang memang dipindah ke Mini POP atas keputusan operator (§16.2).
 */
class InfrastructureLegacyDecisionService
{
    public function __construct(
        private readonly InfrastructurePositionService $positions,
        private readonly InfrastructureConnectionService $connections,
    ) {}

    public function mapPonNode(InfrastructureLegacyMapping $mapping, InfrastructureSlot $slot, int $ponNumber, User $operator, string $decision, ?string $confirmedCapacityUnit = null): InfrastructurePort
    {
        return DB::transaction(function () use ($mapping, $slot, $ponNumber, $operator, $decision, $confirmedCapacityUnit): InfrastructurePort {
            $mapping = $this->lockAmbiguous($mapping, 'pon_port_node', $decision);
            $legacy = NetworkNode::query()->findOrFail($mapping->legacy_id);
            if ($slot->network_node_id !== $legacy->parent_id) {
                throw new InvalidArgumentException('Slot harus milik chassis OLT induk node PON lama.');
            }

            if ($confirmedCapacityUnit !== null && $confirmedCapacityUnit !== 'active_customers') {
                throw new InvalidArgumentException('Satuan kapasitas harus dikonfirmasi sebagai active_customers.');
            }
            $capacity = $confirmedCapacityUnit === 'active_customers' ? $legacy->capacity : null;
            $threshold = $confirmedCapacityUnit === 'active_customers' ? $legacy->nodeType?->red_zone_threshold : null;
            $port = $this->positions->createPort($slot, $ponNumber, InfrastructurePortKind::PON, [
                'code' => $legacy->code,
                'legacy_network_node_id' => $legacy->id,
                'capacity' => $capacity, 'red_zone_threshold' => $threshold,
                'status' => InfrastructureRecordStatus::TERVERIFIKASI,
            ]);
            $this->auditEffect('legacy_pon_capacity', InfrastructurePort::class, $port->id, $operator,
                ['legacy_capacity' => $legacy->capacity, 'legacy_red_zone_threshold' => $legacy->nodeType?->red_zone_threshold],
                ['capacity' => $capacity, 'red_zone_threshold' => $threshold, 'confirmed_capacity_unit' => $confirmedCapacityUnit,
                    'reason' => trim($decision)]);
            $this->decide($mapping, 'mapped', 'infrastructure_port', $port->id, $operator, $decision);

            return $port;
        });
    }

    public function mapOdpUplink(InfrastructureLegacyMapping $mapping, InfrastructurePort $pon, User $operator, string $decision, bool $verified = false, bool $override = false, ?string $overrideReason = null): InfrastructureConnection
    {
        return DB::transaction(function () use ($mapping, $pon, $operator, $decision, $verified, $override, $overrideReason): InfrastructureConnection {
            $mapping = $this->lockAmbiguous($mapping, 'odp_uplink', $decision);
            $odp = NetworkNode::query()->findOrFail($mapping->legacy_id);
            if ($override && trim($overrideReason ?? '') === '') {
                throw new InvalidArgumentException('Alasan override uplink wajib diisi.');
            }
            $legacyAncestorIds = [];
            $ancestor = $odp->parent;
            $seen = [$odp->id];
            while ($ancestor !== null) {
                if (in_array($ancestor->id, $seen, true)) {
                    throw new InvalidArgumentException('Rantai parent ODP melingkar.');
                }
                $seen[] = $ancestor->id;
                if ($ancestor->nodeType?->code === 'pon_port') {
                    $legacyAncestorIds[] = $ancestor->id;
                }
                $ancestor = $ancestor->parent;
            }
            foreach ($mapping->evidence['location_recovery']['proof']['chain'] ?? [] as $provenAncestor) {
                $legacyAncestorIds[] = $provenAncestor['node_id'];
            }
            $expectedPorts = InfrastructurePort::query()->whereIn('legacy_network_node_id', array_unique($legacyAncestorIds))->get();
            foreach ($expectedPorts as $expected) {
                if ($expected->id !== $pon->id) {
                    if (! $override) {
                        throw new InvalidArgumentException('PON tujuan berbeda dari PON leluhur lama yang sudah dipetakan. Pilih override dengan alasan.');
                    }
                    $this->auditEffect('legacy_odp_uplink_override', NetworkNode::class, $odp->id, $operator,
                        ['source_infrastructure_port_id' => $expected->id, 'legacy_ancestor_id' => $expected->legacy_network_node_id],
                        ['source_infrastructure_port_id' => $pon->id, 'reason' => trim($overrideReason)]);
                }
            }
            $connection = $this->connections->connect($pon, $operator, targetNode: $odp, verified: $verified,
                notes: 'Pemetaan legacy: '.trim($decision));
            $this->decide($mapping, 'mapped', 'infrastructure_connection', $connection->id, $operator, $decision);

            return $connection;
        });
    }

    public function mapAggregationSwitch(InfrastructureLegacyMapping $mapping, Pop $miniPop, User $operator, string $decision, bool $clearSelfUplink = false): NetworkNode
    {
        return DB::transaction(function () use ($mapping, $miniPop, $operator, $decision, $clearSelfUplink): NetworkNode {
            $mapping = $this->lockAmbiguous($mapping, 'aggregation_switch_node', $decision);
            $node = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($mapping->legacy_id);
            if ($miniPop->type !== 'mini_pop' || Pop::resolveCabangId($miniPop->id) !== Pop::resolveCabangId($node->resolvePop()?->id)) {
                throw new InvalidArgumentException('Mini POP tujuan harus berada di Cabang yang sama dengan Switch lama.');
            }
            if ($node->children()->exists()) {
                throw new InvalidArgumentException('Switch lama masih punya node anak — petakan anaknya dahulu.');
            }

            $miniPop = Pop::query()->lockForUpdate()->findOrFail($miniPop->id);
            if ($miniPop->uplink_node_id === $node->id) {
                if (! $clearSelfUplink) {
                    throw new InvalidArgumentException('Mini POP menunjuk Switch yang akan ditempatkan di dalamnya. Pilih pengosongan uplink secara eksplisit.');
                }
                $miniPop->update(['uplink_node_id' => null]);
                $this->auditEffect('legacy_switch_uplink_clear', Pop::class, $miniPop->id, $operator,
                    ['uplink_node_id' => $node->id], ['uplink_node_id' => null, 'reason' => trim($decision)]);
            }
            $switchType = NetworkNodeType::query()->where('code', 'switch_mini_pop')->firstOrFail();
            $old = $node->only(['node_type_id', 'anchor_pop_id', 'parent_id']);
            $node->update(['node_type_id' => $switchType->id, 'anchor_pop_id' => $miniPop->id, 'parent_id' => null]);
            $this->positions->ensureLogicalSlot($node->fresh());

            AuditLog::create([
                'user_id' => $operator->id, 'module' => 'Node Jaringan', 'action' => 'legacy_switch_remap',
                'auditable_type' => NetworkNode::class, 'auditable_id' => $node->id,
                'old_values' => $old, 'new_values' => $node->only(['node_type_id', 'anchor_pop_id', 'parent_id']),
                'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'created_at' => now(),
            ]);
            $this->decide($mapping, 'mapped', 'network_node', $node->id, $operator, $decision);

            return $node->fresh('nodeType');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreOrphanLocation(InfrastructureLegacyMapping $mapping, User $operator, string $reason, bool $dryRun = true): array
    {
        return DB::transaction(function () use ($mapping, $operator, $reason, $dryRun): array {
            $mapping = $this->lockAmbiguous($mapping, 'odp_uplink', $reason);
            $odp = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($mapping->legacy_id);
            if ($odp->nodeType?->code !== 'odp' || $odp->parent_id !== null || $odp->anchor_pop_id !== null) {
                throw new InvalidArgumentException('Pemulihan lokasi hanya untuk ODP yatim tanpa parent dan anchor.');
            }
            if (InfrastructureConnection::query()->active()->where('target_network_node_id', $odp->id)->exists()) {
                throw new InvalidArgumentException('ODP sudah memiliki koneksi kanonis; lokasi harus direkonsiliasi operator.');
            }

            $nodeAudit = $this->latestLocationAudit($odp->id, $mapping);
            $placement = $this->auditedPlacement($nodeAudit, $odp->id);
            $evidence = $mapping->evidence ?? [];
            if (! array_key_exists('parent_id', $evidence) || ! array_key_exists('anchor_pop_id', $evidence)
                || $placement['parent_id'] !== $evidence['parent_id'] || $placement['anchor_pop_id'] !== $evidence['anchor_pop_id']) {
                throw new InvalidArgumentException('Bukti mapping berbeda dari penempatan terakhir pada audit ODP.');
            }

            $chain = [];
            $auditIds = [$nodeAudit->id];
            $seen = [$odp->id];
            $parentId = $placement['parent_id'];
            $anchorId = $placement['anchor_pop_id'];
            while ($parentId !== null) {
                if (in_array($parentId, $seen, true)) {
                    throw new InvalidArgumentException('Rantai bukti lokasi melingkar.');
                }
                $seen[] = $parentId;
                $ancestor = NetworkNode::query()->lockForUpdate()->find($parentId);
                $audit = $this->latestLocationAudit($parentId, $mapping);
                $historical = $this->auditedPlacement($audit, $parentId);
                if ($ancestor !== null && ($ancestor->parent_id !== $historical['parent_id'] || $ancestor->anchor_pop_id !== $historical['anchor_pop_id'])) {
                    throw new InvalidArgumentException('Penempatan leluhur saat ini berbeda dari bukti audit.');
                }
                if ($ancestor === null && ! in_array($audit->action, ['create', 'created', 'move'], true)) {
                    throw new InvalidArgumentException('Leluhur yang hilang harus dibuktikan audit create/move.');
                }
                $chain[] = ['node_id' => $parentId, 'source' => $ancestor === null ? 'audit' : 'live',
                    ...$historical, 'audit_id' => $audit->id];
                $auditIds[] = $audit->id;
                $parentId = $historical['parent_id'];
                $anchorId = $historical['anchor_pop_id'];
            }
            $miniPop = Pop::query()->lockForUpdate()->find($anchorId);
            if ($miniPop === null || $miniPop->type !== 'mini_pop') {
                throw new InvalidArgumentException('Rantai bukti tidak berakhir pada Mini POP yang tersedia.');
            }
            $cabangId = Pop::resolveCabangId($miniPop->id);
            if ($cabangId === null || $cabangId !== $mapping->pop_id) {
                throw new InvalidArgumentException('Cabang Mini POP berbeda dari scope mapping.');
            }
            $access = app(EffectiveAccessService::class);
            if (! $access->hasAllPopAccess($operator) && ! in_array($cabangId, $access->getAllowedPopIds($operator), true)) {
                throw new InvalidArgumentException('Operator tidak memiliki scope cabang pemulihan.');
            }
            $customers = NetworkPortAssignment::query()->with('customer')
                ->whereHas('port', fn ($query) => $query->where('network_node_id', $odp->id))
                ->where('active_key', 1)->whereNotNull('customer_id')->get();
            foreach ($customers as $assignment) {
                if ($assignment->customer === null || Pop::resolveCabangId($assignment->customer->pop_id) !== $cabangId) {
                    throw new InvalidArgumentException('Scope pelanggan aktif berbeda dari cabang pemulihan.');
                }
            }

            $proof = ['mapping_id' => $mapping->id, 'mapping_evidence' => array_diff_key($evidence, ['location_recovery' => true]),
                'node_placement_audit_id' => $nodeAudit->id, 'audit_ids' => array_values(array_unique($auditIds)), 'chain' => $chain];
            $plan = ['mapping_id' => $mapping->id, 'network_node_id' => $odp->id, 'mini_pop_id' => $miniPop->id,
                'cabang_id' => $cabangId, 'before' => $odp->only(['parent_id', 'anchor_pop_id']),
                'after' => ['parent_id' => null, 'anchor_pop_id' => $miniPop->id], 'proof' => $proof, 'dry_run' => $dryRun];
            if (! $dryRun) {
                $odp->update($plan['after']);
                $mapping->update(['evidence' => [...$evidence, 'location_recovery' => [
                    'operator_id' => $operator->id, 'reason' => trim($reason), 'restored_at' => now()->toIso8601String(), 'proof' => $proof,
                ]]]);
                $this->auditEffect('legacy_orphan_location_restore', NetworkNode::class, $odp->id, $operator,
                    $plan['before'], [...$plan['after'], 'mapping_id' => $mapping->id, 'reason' => trim($reason), 'proof' => $proof]);
            }

            return $plan;
        });
    }

    private function latestLocationAudit(int $nodeId, InfrastructureLegacyMapping $mapping): AuditLog
    {
        $audit = AuditLog::query()->where('auditable_type', NetworkNode::class)->where('auditable_id', $nodeId)
            ->orderByDesc('created_at')->orderByDesc('id')->get()
            ->first(fn (AuditLog $row): bool => array_key_exists('parent_id', $row->new_values ?? [])
                || array_key_exists('anchor_pop_id', $row->new_values ?? []));
        if ($audit === null || $audit->created_at->greaterThan($mapping->created_at)) {
            throw new InvalidArgumentException('Bukti audit lokasi tidak tersedia atau lebih baru dari snapshot mapping.');
        }

        return $audit;
    }

    /**
     * @return array{parent_id: ?int, anchor_pop_id: ?int}
     */
    private function auditedPlacement(AuditLog $audit, int $nodeId): array
    {
        $values = $audit->new_values ?? [];
        if (! array_key_exists('parent_id', $values) || ! array_key_exists('anchor_pop_id', $values)
            || (isset($values['id']) && $values['id'] !== $nodeId)) {
            throw new InvalidArgumentException('Audit penempatan tidak lengkap atau merujuk ID node lain.');
        }
        foreach (['parent_id', 'anchor_pop_id'] as $field) {
            if ($values[$field] !== null && (! is_int($values[$field]) || $values[$field] < 1)) {
                throw new InvalidArgumentException('ID pada audit penempatan tidak valid.');
            }
        }
        if (($values['parent_id'] === null) === ($values['anchor_pop_id'] === null)) {
            throw new InvalidArgumentException('Audit tidak membuktikan tepat satu penempatan.');
        }

        return ['parent_id' => $values['parent_id'], 'anchor_pop_id' => $values['anchor_pop_id']];
    }

    public function reject(InfrastructureLegacyMapping $mapping, User $operator, string $decision): InfrastructureLegacyMapping
    {
        return DB::transaction(function () use ($mapping, $operator, $decision): InfrastructureLegacyMapping {
            $mapping = $this->lockAmbiguous($mapping, null, $decision);
            $this->decide($mapping, InfrastructureLegacyMapping::REJECTED, null, null, $operator, $decision);

            return $mapping;
        });
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function auditEffect(string $action, string $type, int $id, User $operator, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => $operator->id, 'module' => 'Pemetaan Infrastruktur', 'action' => $action,
            'auditable_type' => $type, 'auditable_id' => $id, 'old_values' => $old, 'new_values' => $new,
            'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'created_at' => now(),
        ]);
    }

    private function lockAmbiguous(InfrastructureLegacyMapping $mapping, ?string $expectedType, string $decision): InfrastructureLegacyMapping
    {
        if (trim($decision) === '') {
            throw new InvalidArgumentException('Alasan keputusan operator wajib diisi.');
        }

        $mapping = InfrastructureLegacyMapping::query()->lockForUpdate()->findOrFail($mapping->id);
        if ($mapping->status !== InfrastructureLegacyMapping::AMBIGUOUS) {
            throw new InvalidArgumentException('Mapping ini sudah diputuskan.');
        }
        if ($expectedType !== null && $mapping->legacy_type !== $expectedType) {
            throw new InvalidArgumentException("Mapping bertipe {$mapping->legacy_type}, bukan {$expectedType}.");
        }

        return $mapping;
    }

    private function decide(InfrastructureLegacyMapping $mapping, string $status, ?string $newType, ?int $newId, User $operator, string $decision): void
    {
        $old = $mapping->only(['status', 'new_type', 'new_id']);
        $mapping->update([
            'status' => $status, 'new_type' => $newType, 'new_id' => $newId,
            'decision' => trim($decision), 'decided_by' => $operator->id, 'decided_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $operator->id, 'module' => 'Pemetaan Infrastruktur', 'action' => 'legacy_decision',
            'auditable_type' => InfrastructureLegacyMapping::class, 'auditable_id' => $mapping->id,
            'old_values' => $old, 'new_values' => [...$mapping->only(['status', 'new_type', 'new_id']), 'decision' => trim($decision)],
            'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'created_at' => now(),
        ]);
    }
}
