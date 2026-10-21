<?php

namespace App\Services;

use App\Enums\CustodyStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\InfrastructureBalance;
use App\Models\InfrastructureRecovery;
use App\Models\InventoryRoll;
use App\Models\InventoryTransaction;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InfrastructureMaterialService
{
    public function __construct(private readonly EffectiveAccessService $access, private readonly InventoryService $inventory) {}

    public function correct(
        InventoryTransaction $source,
        NetworkNode $target,
        float $correctQty,
        FopTask $fopTask,
        User $actor,
        string $reason,
        string $actionKey,
    ): InventoryTransaction {
        $correctUnits = $this->units($correctQty, allowZero: true);
        $this->assertReasonAndKey($reason, $actionKey);
        $fingerprint = $this->fingerprint('correct', $source->id, $target->id, $correctUnits, $fopTask->id, $reason);

        return DB::transaction(function () use ($source, $target, $correctUnits, $fopTask, $actor, $reason, $actionKey, $fingerprint): InventoryTransaction {
            $source = InventoryTransaction::query()->lockForUpdate()->findOrFail($source->id);
            $this->assertSource($source, $fopTask, $actor);
            $existing = $this->existingAction($actionKey, $fingerprint, $fopTask);
            if ($existing !== null) {
                return $existing;
            }
            $this->assertNode($target, $fopTask, $actor);
            $placement = $this->currentPlacement($source);
            $sourceNode = NetworkNode::findOrFail($placement['node_id']);
            $this->assertNode($sourceNode, $fopTask, $actor);
            $this->lockNodes($sourceNode, $target);
            $remainingUnits = $placement['remaining_units'];

            if ($correctUnits > $remainingUnits) {
                throw new InvalidArgumentException('Penambahan jumlah harus melalui aksi DEPLOY baru dari custody.');
            }

            if ($correctUnits === $remainingUnits && $sourceNode->id === $target->id) {
                throw new InvalidArgumentException('Koreksi tidak mengubah jumlah maupun node.');
            }

            $decreaseUnits = $remainingUnits - $correctUnits;
            if ($decreaseUnits > 0) {
                $this->restoreUnusedSource($source, $decreaseUnits);
            }

            $this->reduceBalance($source, $sourceNode, $remainingUnits);
            $reference = 'ICR-'.Str::upper(Str::random(12));
            $negative = InventoryTransaction::create([
                'type' => InventoryTransactionType::ADJUSTMENT,
                'reference_number' => $reference,
                'item_id' => $source->item_id,
                'lot_no' => $source->lot_no,
                'roll_id' => $source->roll_id,
                'qty' => -$remainingUnits / 100,
                'unit_price_snapshot' => $source->unit_price_snapshot,
                'from_network_node_id' => $sourceNode->id,
                'from_pop_id' => $sourceNode->resolvePop()?->id,
                'fop_task_id' => $fopTask->id,
                'source_inventory_transaction_id' => $source->id,
                'source_custody_id' => $source->source_custody_id,
                'action_key' => $actionKey,
                'action_fingerprint' => $fingerprint,
                'reason' => 'infra_record_correction',
                'notes' => $reason,
                'created_by' => $actor->id,
            ]);

            if ($correctUnits > 0) {
                $this->increaseBalance($source->item_id, $target->id, $this->balanceLot($source), $correctUnits);
                InventoryTransaction::create([
                    'type' => InventoryTransactionType::ADJUSTMENT,
                    'reference_number' => $reference,
                    'item_id' => $source->item_id,
                    'lot_no' => $source->lot_no,
                    'roll_id' => $source->roll_id,
                    'qty' => $correctUnits / 100,
                    'unit_price_snapshot' => $source->unit_price_snapshot,
                    'to_network_node_id' => $target->id,
                    'to_pop_id' => $target->resolvePop()?->id,
                    'fop_task_id' => $fopTask->id,
                    'source_inventory_transaction_id' => $source->id,
                    'source_custody_id' => $source->source_custody_id,
                    'reason' => 'infra_record_correction',
                    'notes' => $reason,
                    'created_by' => $actor->id,
                ]);
            }

            return $negative;
        }, 3);
    }

    public function correctRoll(
        InventoryTransaction $source,
        InventoryRoll $replacementRoll,
        NetworkNode $target,
        float $correctQty,
        FopTask $fopTask,
        User $actor,
        string $reason,
        string $actionKey,
    ): InventoryTransaction {
        $correctUnits = $this->units($correctQty);
        $this->assertReasonAndKey($reason, $actionKey);
        $fingerprint = $this->fingerprint('correct_roll', $source->id, $replacementRoll->id, $target->id, $correctUnits, $fopTask->id, $reason);

        return DB::transaction(function () use ($source, $replacementRoll, $target, $correctUnits, $fopTask, $actor, $reason, $actionKey, $fingerprint): InventoryTransaction {
            $source = InventoryTransaction::query()->lockForUpdate()->findOrFail($source->id);
            $this->assertSource($source, $fopTask, $actor);
            $existing = $this->existingAction($actionKey, $fingerprint, $fopTask);
            if ($existing !== null) {
                return $existing;
            }

            if ($source->roll_id === null || $source->roll_id === $replacementRoll->id || $source->item_id !== $replacementRoll->item_id) {
                throw new InvalidArgumentException('Roll pengganti harus berbeda dan memakai item yang sama.');
            }

            if (InventoryTransaction::query()->where('source_inventory_transaction_id', $source->id)
                ->where('type', InventoryTransactionType::RETRIEVE_INFRA->value)->exists()) {
                throw new InvalidArgumentException('Roll asal sudah pernah dicabut secara fisik; perlu rekonsiliasi Gudang.');
            }

            $technicians = User::query()->whereIn('id', $fopTask->task->teamMembers()->pluck('user_id'))->get();
            $this->correct($source, $target, 0, $fopTask, $actor, $reason, hash('sha256', $actionKey.'|reverse'));

            return $this->inventory->deployRoll(
                $replacementRoll, $correctUnits / 100, $target, $technicians, $fopTask, $actor,
                $source, $actionKey, $fingerprint,
            );
        }, 3);
    }

    public function retrieve(
        InventoryTransaction $source,
        float $qty,
        FopTask $fopTask,
        User $actor,
        string $reason,
        string $actionKey,
    ): InfrastructureRecovery {
        $requestedUnits = $this->units($qty);
        $this->assertReasonAndKey($reason, $actionKey);
        $fingerprint = $this->fingerprint('retrieve', $source->id, $requestedUnits, $fopTask->id, $actor->id, $reason);

        return DB::transaction(function () use ($source, $requestedUnits, $fopTask, $actor, $reason, $actionKey, $fingerprint): InfrastructureRecovery {
            $source = InventoryTransaction::query()->lockForUpdate()->findOrFail($source->id);
            $this->assertSource($source, $fopTask, $actor);
            $existing = $this->existingAction($actionKey, $fingerprint, $fopTask);
            if ($existing !== null) {
                return InfrastructureRecovery::query()->where('retrieve_transaction_id', $existing->id)->firstOrFail();
            }
            $placement = $this->currentPlacement($source);
            if ($requestedUnits > $placement['remaining_units']) {
                throw new InvalidArgumentException('Jumlah cabut melebihi sisa yang terpasang dari transaksi asal.');
            }

            $node = NetworkNode::query()->lockForUpdate()->findOrFail($placement['node_id']);
            $this->assertNode($node, $fopTask, $actor);
            $this->reduceBalance($source, $node, $requestedUnits);

            $transaction = InventoryTransaction::create([
                'type' => InventoryTransactionType::RETRIEVE_INFRA,
                'reference_number' => 'RTR-'.Str::upper(Str::random(12)),
                'item_id' => $source->item_id,
                'roll_id' => $source->roll_id,
                'lot_no' => $source->lot_no,
                'qty' => $requestedUnits / 100,
                'unit_price_snapshot' => $source->unit_price_snapshot,
                'from_network_node_id' => $node->id,
                'from_pop_id' => $node->resolvePop()?->id,
                'to_technician_id' => $actor->id,
                'fop_task_id' => $fopTask->id,
                'source_inventory_transaction_id' => $source->id,
                'source_custody_id' => $source->source_custody_id,
                'action_key' => $actionKey,
                'action_fingerprint' => $fingerprint,
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);

            if ($fopTask->task_id === null) {
                throw new InvalidArgumentException('Task FOP belum terhubung ke Task pekerjaan.');
            }

            return InfrastructureRecovery::create([
                'source_transaction_id' => $source->id,
                'retrieve_transaction_id' => $transaction->id,
                'task_id' => $fopTask->task_id,
                'item_id' => $source->item_id,
                'roll_id' => $source->roll_id,
                'technician_id' => $actor->id,
                'qty' => $requestedUnits / 100,
                'status' => 'pending_inspection',
            ]);
        }, 3);
    }

    public function inspect(InfrastructureRecovery $recovery, string $outcome, User $inspector, string $note, ?float $verifiedQty = null): InfrastructureRecovery
    {
        if (! in_array($outcome, ['reusable', 'damaged'], true) || trim($note) === '') {
            throw new InvalidArgumentException('Kondisi dan catatan pemeriksaan wajib diisi.');
        }

        if (! $inspector->hasPermission('warehouse_reassign.create')) {
            throw new InvalidArgumentException('Aktor tidak berwenang memeriksa hasil cabut.');
        }

        return DB::transaction(function () use ($recovery, $outcome, $inspector, $note, $verifiedQty): InfrastructureRecovery {
            $recovery = InfrastructureRecovery::query()->lockForUpdate()->findOrFail($recovery->id);
            $verifiedUnits = $this->units($verifiedQty ?? (float) $recovery->qty);
            if ($verifiedUnits !== $this->units((float) $recovery->qty)) {
                throw new InvalidArgumentException('Jumlah fisik berbeda dari laporan cabut; tahan material dan lakukan rekonsiliasi Gudang.');
            }

            $source = InventoryTransaction::query()->findOrFail($recovery->source_transaction_id);
            $node = NetworkNode::query()->findOrFail($source->to_network_node_id);
            $fopTask = FopTask::query()->findOrFail($recovery->retrieveTransaction->fop_task_id);
            $this->assertNode($node, $fopTask, $inspector);

            if ($recovery->status !== 'pending_inspection') {
                if ($recovery->status === $outcome && $recovery->inspection_note === $note && $recovery->inspected_by === $inspector->id) {
                    return $recovery;
                }

                throw new InvalidArgumentException('Hasil cabut ini sudah diperiksa dengan keputusan berbeda.');
            }

            $recoveredCustodyId = null;
            $recoveredRollId = null;
            if ($outcome === 'reusable' && $recovery->roll_id !== null) {
                $oldRoll = InventoryRoll::query()->findOrFail($recovery->roll_id);
                $newRoll = InventoryRoll::create([
                    'item_id' => $recovery->item_id,
                    'roll_code' => 'POT-IR-'.$recovery->id,
                    'length_total' => $recovery->qty,
                    'length_remaining' => $recovery->qty,
                    'unit_price_snapshot' => $source->unit_price_snapshot,
                    'status' => RollStatus::ISSUED,
                    'current_technician_id' => $recovery->technician_id,
                    'issued_from_pop_id' => $oldRoll->issued_from_pop_id ?? Pop::resolveCabangId($source->to_pop_id),
                    'received_at' => now(),
                ]);
                $recoveredRollId = $newRoll->id;
            } elseif ($outcome === 'reusable') {
                $custody = TechnicianCustody::create([
                    'technician_id' => $recovery->technician_id,
                    'issued_from_pop_id' => Pop::resolveCabangId($source->to_pop_id),
                    'item_id' => $recovery->item_id,
                    'lot_no' => $source->lot_no,
                    'qty_remaining' => $recovery->qty,
                    'unit_price_snapshot' => $source->unit_price_snapshot,
                    'status' => CustodyStatus::ISSUED,
                    'issued_at' => now(),
                ]);
                $recoveredCustodyId = $custody->id;
            }

            InventoryTransaction::create([
                'type' => InventoryTransactionType::ADJUSTMENT,
                'reference_number' => 'INSP-IR-'.$recovery->id,
                'item_id' => $recovery->item_id,
                'roll_id' => $recoveredRollId ?? $recovery->roll_id,
                'lot_no' => $recoveredRollId !== null ? 'POT-IR-'.$recovery->id : $source->lot_no,
                'qty' => $outcome === 'reusable' ? $recovery->qty : -$recovery->qty,
                'unit_price_snapshot' => $source->unit_price_snapshot,
                'to_technician_id' => $outcome === 'reusable' ? $recovery->technician_id : null,
                'from_pop_id' => $source->to_pop_id,
                'fop_task_id' => $fopTask->id,
                'source_inventory_transaction_id' => $recovery->retrieve_transaction_id,
                'action_key' => 'infra-inspection-'.$recovery->id,
                'action_fingerprint' => $this->fingerprint($outcome, $note, $inspector->id),
                'reason' => 'infra_recovery_'.$outcome,
                'notes' => $note,
                'created_by' => $inspector->id,
            ]);

            $recovery->update([
                'status' => $outcome,
                'verified_qty' => $verifiedUnits / 100,
                'inspection_note' => $note,
                'inspected_by' => $inspector->id,
                'inspected_at' => now(),
                'recovered_custody_id' => $recoveredCustodyId,
                'recovered_roll_id' => $recoveredRollId,
            ]);

            return $recovery;
        }, 3);
    }

    private function units(float $qty, bool $allowZero = false): int
    {
        if (! is_finite($qty) || $qty < 0 || (! $allowZero && $qty <= 0) || abs($qty * 100 - round($qty * 100)) > 0.00001) {
            throw new InvalidArgumentException('Jumlah harus positif dengan maksimal dua angka desimal.');
        }

        return (int) round($qty * 100);
    }

    private function assertReasonAndKey(string $reason, string $actionKey): void
    {
        if (trim($reason) === '' || trim($actionKey) === '' || strlen($actionKey) > 80) {
            throw new InvalidArgumentException('Alasan dan kunci aksi yang valid wajib diisi.');
        }
    }

    private function fingerprint(mixed ...$parts): string
    {
        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }

    private function existingAction(string $actionKey, string $fingerprint, FopTask $fopTask): ?InventoryTransaction
    {
        $existing = InventoryTransaction::query()->where('action_key', $actionKey)->lockForUpdate()->first();

        if ($existing !== null && ($existing->action_fingerprint !== $fingerprint || $existing->fop_task_id !== $fopTask->id)) {
            throw new InvalidArgumentException('Kunci aksi sudah dipakai untuk mutasi lain.');
        }

        return $existing;
    }

    private function assertSource(InventoryTransaction $source, FopTask $fopTask, User $actor): void
    {
        if ($source->type !== InventoryTransactionType::DEPLOY || $source->serial_id !== null
            || $source->to_network_node_id === null || $source->qty <= 0
            || ! in_array($fopTask->category, [TaskType::OREQ, TaskType::INFR], true)
            || $fopTask->task_id === null) {
            throw new InvalidArgumentException('Transaksi asal bukan pemasangan qty/roll pada Task O-REQ/INFR-REQ.');
        }

        $node = NetworkNode::findOrFail($source->to_network_node_id);
        $this->assertNode($node, $fopTask, $actor);
    }

    private function assertNode(NetworkNode $node, FopTask $fopTask, User $actor): void
    {
        $taskCabang = Pop::resolveCabangId($fopTask->pop_id);
        $nodeCabang = Pop::resolveCabangId($node->resolvePop()?->id);
        if ($taskCabang === null || $taskCabang !== $nodeCabang) {
            throw new InvalidArgumentException('Node berada di luar cabang Task.');
        }

        if (! $this->access->hasAllPopAccess($actor)
            && ! NetworkNode::query()->inPops($this->access->getAllowedPopIds($actor))->whereKey($node->id)->exists()) {
            throw new InvalidArgumentException('Node berada di luar scope aktor.');
        }
    }

    private function lockNodes(NetworkNode $first, NetworkNode $second): void
    {
        NetworkNode::query()->whereKey(array_unique([$first->id, $second->id]))->orderBy('id')->lockForUpdate()->get();
    }

    private function balanceLot(InventoryTransaction $source): string
    {
        return $source->roll_id === null ? '' : (string) $source->lot_no;
    }

    /**
     * @return array{node_id: int, remaining_units: int}
     */
    private function currentPlacement(InventoryTransaction $source): array
    {
        $lastCorrection = InventoryTransaction::query()
            ->where('source_inventory_transaction_id', $source->id)
            ->where('type', InventoryTransactionType::ADJUSTMENT->value)
            ->where('reason', 'infra_record_correction')
            ->whereNotNull('from_network_node_id')
            ->orderByDesc('id')
            ->first();

        $active = $lastCorrection === null
            ? $source
            : InventoryTransaction::query()
                ->where('reference_number', $lastCorrection->reference_number)
                ->where('source_inventory_transaction_id', $source->id)
                ->whereNotNull('to_network_node_id')
                ->first();

        $activeUnits = $active === null ? 0 : $this->units((float) $active->qty, allowZero: true);
        $retrievedUnits = InventoryTransaction::query()
            ->where('source_inventory_transaction_id', $source->id)
            ->where('type', InventoryTransactionType::RETRIEVE_INFRA->value)
            ->when($lastCorrection !== null, fn ($query) => $query->where('id', '>', $lastCorrection->id))
            ->get()
            ->sum(fn (InventoryTransaction $transaction): int => $this->units((float) $transaction->qty));

        if ($retrievedUnits > $activeUnits) {
            throw new InvalidArgumentException('Riwayat transaksi melebihi jumlah pemasangan aktif.');
        }

        return [
            'node_id' => (int) ($active?->to_network_node_id ?? $lastCorrection?->from_network_node_id ?? $source->to_network_node_id),
            'remaining_units' => $activeUnits - $retrievedUnits,
        ];
    }

    private function reduceBalance(InventoryTransaction $source, NetworkNode $node, int $units): void
    {
        $balance = InfrastructureBalance::query()
            ->where('item_id', $source->item_id)
            ->where('network_node_id', $node->id)
            ->where('lot_no', $this->balanceLot($source))
            ->lockForUpdate()
            ->first();

        if ($balance === null || $this->units((float) $balance->qty, allowZero: true) < $units) {
            throw new InvalidArgumentException('Saldo node tidak cukup untuk koreksi atau cabut.');
        }

        $balance->update(['qty' => ($this->units((float) $balance->qty, allowZero: true) - $units) / 100]);
    }

    private function increaseBalance(int $itemId, int $nodeId, string $lotNo, int $units): void
    {
        $balance = InfrastructureBalance::query()->lockForUpdate()->firstOrCreate(
            ['item_id' => $itemId, 'network_node_id' => $nodeId, 'lot_no' => $lotNo],
            ['qty' => 0],
        );
        $balance->increment('qty', $units / 100);
    }

    private function restoreUnusedSource(InventoryTransaction $source, int $units): void
    {
        if ($source->roll_id !== null) {
            $roll = InventoryRoll::query()->lockForUpdate()->findOrFail($source->roll_id);
            if (! in_array($roll->status, [RollStatus::ISSUED, RollStatus::IN_USE, RollStatus::DEPLETED], true)) {
                throw new InvalidArgumentException('Roll asal sudah keluar dari custody aktif; koreksi jumlah memerlukan rekonsiliasi Gudang.');
            }

            $restored = $this->units((float) $roll->length_remaining, allowZero: true) + $units;
            if ($restored > $this->units((float) $roll->length_total)) {
                throw new InvalidArgumentException('Koreksi melebihi panjang asli roll.');
            }

            $roll->update([
                'length_remaining' => $restored / 100,
                'status' => RollStatus::IN_USE,
            ]);

            return;
        }

        if ($source->source_custody_id === null) {
            throw new InvalidArgumentException('Lot custody asal belum tercatat; koreksi jumlah memerlukan rekonsiliasi Gudang.');
        }

        $custody = TechnicianCustody::query()->lockForUpdate()->findOrFail($source->source_custody_id);
        if ($custody->status === CustodyStatus::RETURNED || $custody->technician_id !== $source->from_technician_id) {
            throw new InvalidArgumentException('Custody asal sudah berubah; koreksi jumlah memerlukan rekonsiliasi Gudang.');
        }

        $custody->update([
            'qty_remaining' => ($this->units((float) $custody->qty_remaining, allowZero: true) + $units) / 100,
            'status' => CustodyStatus::PARTIALLY_USED,
        ]);
    }
}
