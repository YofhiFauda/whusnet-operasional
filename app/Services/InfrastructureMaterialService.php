<?php

namespace App\Services;

use App\Enums\CustodyStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\InfrastructureRecovery;
use App\Models\InventoryRoll;
use App\Models\InventoryTransaction;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Support\InfrastructureLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Koreksi administratif & cabut fisik material qty/roll yang sudah dicatat
 * terpasang lewat laporan O-REQ/INFR-REQ. Semua mutasi append-only ke
 * ledger, idempoten lewat `action_key` + `action_fingerprint`.
 *
 * Lokasi asal/tujuan memakai posisi tepat (`InfrastructureLocation`: POP,
 * Mini POP, node, Slot, Port — Task 6/7). Dua jenis barang, dua aturan:
 *
 * - QTY non-kabel: punya saldo lokasi (`infrastructure_balances`) yang ikut
 *   berpindah/berkurang.
 * - KABEL (roll): TIDAK punya saldo terpasang (§16.4 #2). Koreksi hanya
 *   memulihkan sisa roll dan memindah lokasi KERJA di ledger; cabut fisik
 *   membuat hasil cabut menunggu inspeksi Gudang.
 *
 * Lokasi tujuan koreksi wajib lolos irisan scope task (§10.3); inspeksi
 * Gudang hanya memeriksa Cabang + POP scope pemeriksa.
 */
class InfrastructureMaterialService
{
    public function __construct(
        private readonly EffectiveAccessService $access,
        private readonly InventoryService $inventory,
        private readonly InfrastructureLocationResolver $locations,
        private readonly FopTaskInfrastructureScopeService $scopes,
    ) {}

    public function correct(
        InventoryTransaction $source,
        NetworkNode|InfrastructureLocation $target,
        float $correctQty,
        FopTask $fopTask,
        User $actor,
        string $reason,
        string $actionKey,
    ): InventoryTransaction {
        $target = $this->toLocation($target);
        $correctUnits = $this->units($correctQty, allowZero: true);
        $this->assertReasonAndKey($reason, $actionKey);
        $fingerprint = $this->fingerprint('correct', $source->id, $target->key(), $correctUnits, $fopTask->id, $reason);

        return DB::transaction(function () use ($source, $target, $correctUnits, $fopTask, $actor, $reason, $actionKey, $fingerprint): InventoryTransaction {
            $source = InventoryTransaction::query()->lockForUpdate()->findOrFail($source->id);
            $this->assertSource($source, $fopTask, $actor);
            $existing = $this->existingAction($actionKey, $fingerprint, $fopTask);
            if ($existing !== null) {
                return $existing;
            }
            $this->assertLocationInTask($target, $fopTask, $actor);
            $placement = $this->currentPlacement($source);
            $sourceLocation = $placement['location'];
            $this->assertLocationInTask($sourceLocation, $fopTask, $actor);
            $remainingUnits = $placement['remaining_units'];

            if ($correctUnits > $remainingUnits) {
                throw new InvalidArgumentException('Penambahan jumlah harus melalui aksi DEPLOY baru dari custody.');
            }

            if ($correctUnits === $remainingUnits && $sourceLocation->key() === $target->key()) {
                throw new InvalidArgumentException('Koreksi tidak mengubah jumlah maupun lokasi.');
            }

            $decreaseUnits = $remainingUnits - $correctUnits;
            if ($decreaseUnits > 0) {
                $this->restoreUnusedSource($source, $decreaseUnits);
            }

            if ($this->hasLocationBalance($source)) {
                $this->inventory->adjustInfrastructureBalance($source->item_id, $sourceLocation, $this->balanceLot($source), -$remainingUnits / 100);
            }
            $reference = 'ICR-'.Str::upper(Str::random(12));
            $negative = InventoryTransaction::create([
                'type' => InventoryTransactionType::ADJUSTMENT,
                'reference_number' => $reference,
                'item_id' => $source->item_id,
                'lot_no' => $source->lot_no,
                'roll_id' => $source->roll_id,
                'qty' => -$remainingUnits / 100,
                'unit_price_snapshot' => $source->unit_price_snapshot,
                ...$this->inventory->transactionLocationColumns('from', $this->positionOnly($sourceLocation)),
                'from_pop_id' => $sourceLocation->cabangId,
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
                if ($this->hasLocationBalance($source)) {
                    $this->inventory->adjustInfrastructureBalance($source->item_id, $target, $this->balanceLot($source), $correctUnits / 100);
                }
                InventoryTransaction::create([
                    'type' => InventoryTransactionType::ADJUSTMENT,
                    'reference_number' => $reference,
                    'item_id' => $source->item_id,
                    'lot_no' => $source->lot_no,
                    'roll_id' => $source->roll_id,
                    'qty' => $correctUnits / 100,
                    'unit_price_snapshot' => $source->unit_price_snapshot,
                    ...$this->inventory->transactionLocationColumns('to', $this->positionOnly($target)),
                    'to_pop_id' => $target->cabangId,
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
        NetworkNode|InfrastructureLocation $target,
        float $correctQty,
        FopTask $fopTask,
        User $actor,
        string $reason,
        string $actionKey,
    ): InventoryTransaction {
        $target = $this->toLocation($target);
        $correctUnits = $this->units($correctQty);
        $this->assertReasonAndKey($reason, $actionKey);
        $fingerprint = $this->fingerprint('correct_roll', $source->id, $replacementRoll->id, $target->key(), $correctUnits, $fopTask->id, $reason);

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

            $this->assertLocationInTask($target, $fopTask, $actor);
            $technicians = User::query()->whereIn('id', $fopTask->task->teamMembers()->pluck('user_id'))->get();
            $this->correct($source, $target, 0, $fopTask, $actor, $reason, hash('sha256', $actionKey.'|reverse'));

            return $this->inventory->consumeInfrastructureRoll(
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

            $location = $placement['location'];
            $this->assertLocationInTask($location, $fopTask, $actor);
            if ($this->hasLocationBalance($source)) {
                $this->inventory->adjustInfrastructureBalance($source->item_id, $location, $this->balanceLot($source), -$requestedUnits / 100);
            }

            if ($fopTask->task_id === null) {
                throw new InvalidArgumentException('Task FOP belum terhubung ke Task pekerjaan.');
            }

            $transaction = InventoryTransaction::create([
                'type' => InventoryTransactionType::RETRIEVE_INFRA,
                'reference_number' => 'RTR-'.Str::upper(Str::random(12)),
                'item_id' => $source->item_id,
                'roll_id' => $source->roll_id,
                'lot_no' => $source->lot_no,
                'qty' => $requestedUnits / 100,
                'unit_price_snapshot' => $source->unit_price_snapshot,
                ...$this->inventory->transactionLocationColumns('from', $this->positionOnly($location)),
                'from_pop_id' => $location->cabangId,
                'to_technician_id' => $actor->id,
                'fop_task_id' => $fopTask->id,
                'source_inventory_transaction_id' => $source->id,
                'source_custody_id' => $source->source_custody_id,
                'action_key' => $actionKey,
                'action_fingerprint' => $fingerprint,
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);

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
            $location = $this->inventory->transactionLocation($source, 'to')
                ?? throw new InvalidArgumentException('Transaksi asal tidak memiliki lokasi.');
            $fopTask = FopTask::query()->findOrFail($recovery->retrieveTransaction->fop_task_id);
            $this->assertLocationReach($location, $fopTask, $inspector);

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

    private function toLocation(NetworkNode|InfrastructureLocation $target): InfrastructureLocation
    {
        return $target instanceof NetworkNode ? $this->locations->forNode($target) : $target;
    }

    /**
     * Kolom posisi ledger untuk lokasi POP Cabang dibiarkan kosong — POP-nya
     * sudah tercatat di `{arah}_pop_id` (snapshot Cabang), lihat
     * `InventoryService::transactionLocation()`.
     */
    private function positionOnly(InfrastructureLocation $location): ?InfrastructureLocation
    {
        return $location->kind === InfrastructureLocation::POP ? null : $location;
    }

    private function hasLocationBalance(InventoryTransaction $source): bool
    {
        // Kabel tidak punya saldo terpasang (§16.4 #2) — hanya qty non-kabel.
        return $source->roll_id === null;
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
        $location = $this->inventory->transactionLocation($source, 'to');

        if ($source->type !== InventoryTransactionType::DEPLOY || $source->serial_id !== null
            || $location === null || $source->qty <= 0
            || ! in_array($fopTask->category, [TaskType::OREQ, TaskType::INFR], true)
            || $fopTask->task_id === null) {
            throw new InvalidArgumentException('Transaksi asal bukan pemasangan qty/roll pada Task O-REQ/INFR-REQ.');
        }

        $this->assertLocationReach($location, $fopTask, $actor);
    }

    /**
     * Irisan penuh scope task (§10.3) — untuk lokasi yang DIPILIH pada laporan
     * (tujuan koreksi, lokasi cabut).
     */
    private function assertLocationInTask(InfrastructureLocation $location, FopTask $fopTask, User $actor): void
    {
        try {
            $this->scopes->assertLocationInTaskScope($fopTask, $location, $actor);
        } catch (ValidationException $exception) {
            throw new InvalidArgumentException(collect($exception->errors())->flatten()->first() ?? 'Lokasi di luar scope task.');
        }
    }

    /**
     * Cabang task + POP scope aktor saja — untuk transaksi asal & inspeksi
     * Gudang (pemeriksa bukan anggota subtree pekerjaan).
     */
    private function assertLocationReach(InfrastructureLocation $location, FopTask $fopTask, User $actor): void
    {
        $taskCabang = Pop::resolveCabangId($fopTask->pop_id);
        if ($taskCabang === null || $taskCabang !== $location->cabangId) {
            throw new InvalidArgumentException('Lokasi berada di luar cabang Task.');
        }

        try {
            $this->scopes->assertActorCanReach($location, $actor, 'infra_material_action');
        } catch (ValidationException) {
            throw new InvalidArgumentException('Lokasi berada di luar scope aktor.');
        }
    }

    private function balanceLot(InventoryTransaction $source): string
    {
        return $source->roll_id === null ? '' : (string) $source->lot_no;
    }

    /**
     * Posisi aktif + sisa unit dari transaksi asal, diturunkan dari ledger:
     * koreksi terakhir (pasangan negatif/positif ber-reference sama) lalu
     * dikurangi cabut fisik sesudahnya.
     *
     * @return array{location: InfrastructureLocation, remaining_units: int}
     */
    private function currentPlacement(InventoryTransaction $source): array
    {
        $lastCorrection = InventoryTransaction::query()
            ->where('source_inventory_transaction_id', $source->id)
            ->where('type', InventoryTransactionType::ADJUSTMENT->value)
            ->where('reason', 'infra_record_correction')
            ->where('qty', '<', 0)
            ->orderByDesc('id')
            ->first();

        $active = $lastCorrection === null
            ? $source
            : InventoryTransaction::query()
                ->where('reference_number', $lastCorrection->reference_number)
                ->where('source_inventory_transaction_id', $source->id)
                ->where('qty', '>', 0)
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

        $location = $active !== null
            ? $this->inventory->transactionLocation($active, 'to')
            : $this->inventory->transactionLocation($lastCorrection, 'from');

        return [
            'location' => $location ?? throw new InvalidArgumentException('Lokasi transaksi asal tidak terbaca.'),
            'remaining_units' => $activeUnits - $retrievedUnits,
        ];
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
