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

    public function mapPonNode(InfrastructureLegacyMapping $mapping, InfrastructureSlot $slot, int $ponNumber, User $operator, string $decision): InfrastructurePort
    {
        return DB::transaction(function () use ($mapping, $slot, $ponNumber, $operator, $decision): InfrastructurePort {
            $mapping = $this->lockAmbiguous($mapping, 'pon_port_node', $decision);
            $legacy = NetworkNode::query()->findOrFail($mapping->legacy_id);
            if ($slot->network_node_id !== $legacy->parent_id) {
                throw new InvalidArgumentException('Slot harus milik chassis OLT induk node PON lama.');
            }

            $port = $this->positions->createPort($slot, $ponNumber, InfrastructurePortKind::PON, [
                'code' => $legacy->code,
                'legacy_network_node_id' => $legacy->id,
                'status' => InfrastructureRecordStatus::TERVERIFIKASI,
            ]);
            $this->decide($mapping, 'mapped', 'infrastructure_port', $port->id, $operator, $decision);

            return $port;
        });
    }

    public function mapOdpUplink(InfrastructureLegacyMapping $mapping, InfrastructurePort $pon, User $operator, string $decision, bool $verified = false): InfrastructureConnection
    {
        return DB::transaction(function () use ($mapping, $pon, $operator, $decision, $verified): InfrastructureConnection {
            $mapping = $this->lockAmbiguous($mapping, 'odp_uplink', $decision);
            $odp = NetworkNode::query()->findOrFail($mapping->legacy_id);
            $connection = $this->connections->connect($pon, $operator, targetNode: $odp, verified: $verified,
                notes: 'Pemetaan legacy: '.trim($decision));
            $this->decide($mapping, 'mapped', 'infrastructure_connection', $connection->id, $operator, $decision);

            return $connection;
        });
    }

    public function mapAggregationSwitch(InfrastructureLegacyMapping $mapping, Pop $miniPop, User $operator, string $decision): NetworkNode
    {
        return DB::transaction(function () use ($mapping, $miniPop, $operator, $decision): NetworkNode {
            $mapping = $this->lockAmbiguous($mapping, 'aggregation_switch_node', $decision);
            $node = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($mapping->legacy_id);
            if ($miniPop->type !== 'mini_pop' || Pop::resolveCabangId($miniPop->id) !== Pop::resolveCabangId($node->resolvePop()?->id)) {
                throw new InvalidArgumentException('Mini POP tujuan harus berada di Cabang yang sama dengan Switch lama.');
            }
            if ($node->children()->exists()) {
                throw new InvalidArgumentException('Switch lama masih punya node anak — petakan anaknya dahulu.');
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

    public function reject(InfrastructureLegacyMapping $mapping, User $operator, string $decision): InfrastructureLegacyMapping
    {
        return DB::transaction(function () use ($mapping, $operator, $decision): InfrastructureLegacyMapping {
            $mapping = $this->lockAmbiguous($mapping, null, $decision);
            $this->decide($mapping, InfrastructureLegacyMapping::REJECTED, null, null, $operator, $decision);

            return $mapping;
        });
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
