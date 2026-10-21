<?php

namespace App\Services;

use App\Enums\TaskType;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\FopTask;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\OltSlot;
use App\Models\Pop;
use App\Models\Task;
use App\Models\TaskInfrastructureAction;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TaskInfrastructureActionService
{
    use AuthorizesWarehousePop;

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly EffectiveAccessService $access,
        private readonly NetworkPortAssignmentService $ports,
    ) {}

    /** @param array<int, array<string, mixed>> $rows */
    public function record(Task $task, FopTask $fopTask, array $rows, iterable $technicians, User $actor): void
    {
        if (! in_array($task->task_type, [TaskType::OREQ, TaskType::INFR], true)) {
            throw new InvalidArgumentException('Aksi infrastruktur hanya untuk O-REQ/INFR-REQ.');
        }
        $seen = [];
        foreach ($rows as $row) {
            $key = $row['action_key'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['infra_actions' => 'Kunci aksi berulang dalam laporan.']);
            }
            $seen[$key] = true;
            $mutation = array_filter([
                'action_type' => $row['action_type'],
                'network_node_id' => isset($row['network_node_id']) ? (int) $row['network_node_id'] : null,
                'serial_id' => isset($row['serial_id']) ? (int) $row['serial_id'] : null,
                'old_serial_id' => isset($row['old_serial_id']) ? (int) $row['old_serial_id'] : null,
                'item_id' => isset($row['item_id']) ? (int) $row['item_id'] : null,
                'qty' => isset($row['qty']) ? (float) $row['qty'] : null,
                'roll_id' => isset($row['roll_id']) ? (int) $row['roll_id'] : null,
                'meters' => isset($row['meters']) ? (float) $row['meters'] : null,
                'slot_id' => isset($row['slot_id']) ? (int) $row['slot_id'] : null,
                'port_id' => isset($row['port_id']) ? (int) $row['port_id'] : null,
                'target_node_id' => isset($row['target_node_id']) ? (int) $row['target_node_id'] : null,
                'target_port_id' => isset($row['target_port_id']) ? (int) $row['target_port_id'] : null,
            ], static fn ($value) => $value !== null);
            $hash = hash('sha256', json_encode($mutation, JSON_THROW_ON_ERROR));
            $existing = TaskInfrastructureAction::query()->where('task_id', $task->id)
                ->where('action_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->mutation_hash !== $hash) {
                    throw ValidationException::withMessages(['infra_actions' => 'Mutasi aksi lama berubah. Buat aksi koreksi baru.']);
                }
                $existing->update(['result_note' => $row['result_note'] ?? null]);

                continue;
            }
            $this->validateActionFields($mutation);
            $node = isset($mutation['network_node_id'])
                ? NetworkNode::query()->lockForUpdate()->findOrFail($mutation['network_node_id']) : null;
            if ($node) {
                $this->assertTaskNode($task, $node, $actor);
            } elseif ($row['action_type'] !== 'inspect' || $task->task_type === TaskType::INFR) {
                throw ValidationException::withMessages(['infra_actions' => 'Node wajib dipilih untuk aksi ini.']);
            }
            $action = TaskInfrastructureAction::create([
                'task_id' => $task->id, 'fop_task_id' => $fopTask->id,
                'action_key' => $key, 'action_type' => $row['action_type'],
                'network_node_id' => $node?->id, 'mutation_hash' => $hash,
                'mutation' => $mutation, 'result_note' => $row['result_note'] ?? null,
                'performed_by' => $actor->id, 'performed_at' => now(),
            ]);
            foreach ($this->apply($mutation, $node, $fopTask, $technicians, $actor, $task) as $id) {
                $action->transactions()->attach($id);
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function validateActionFields(array $row): void
    {
        $allowed = match ($row['action_type']) {
            'inspect' => ['action_type', 'network_node_id', 'port_id'],
            'install' => ['action_type', 'network_node_id', 'serial_id', 'item_id', 'qty', 'roll_id', 'meters', 'slot_id'],
            'retrieve' => ['action_type', 'network_node_id', 'old_serial_id'],
            'replace' => ['action_type', 'network_node_id', 'old_serial_id', 'serial_id', 'slot_id'],
            'port_connect' => ['action_type', 'network_node_id', 'port_id', 'target_node_id', 'target_port_id'],
            'port_release' => ['action_type', 'network_node_id', 'port_id'],
            default => throw ValidationException::withMessages(['infra_actions' => 'Jenis aksi tidak dikenal.']),
        };
        if (array_diff(array_keys($row), $allowed) !== []) {
            throw ValidationException::withMessages(['infra_actions' => 'Isian aksi tidak sesuai dengan jenis tindakan.']);
        }
        if (in_array($row['action_type'], ['retrieve', 'replace'], true) && ! isset($row['old_serial_id'])) {
            throw ValidationException::withMessages(['infra_actions' => 'SN lama wajib dipilih untuk cabut atau ganti.']);
        }
        if (in_array($row['action_type'], ['port_connect', 'port_release'], true) && ! isset($row['port_id'])) {
            throw ValidationException::withMessages(['infra_actions' => 'Port asal wajib dipilih.']);
        }
        if ($row['action_type'] === 'port_connect' && (! isset($row['target_node_id']) || ! isset($row['target_port_id']))) {
            throw ValidationException::withMessages(['infra_actions' => 'Node dan port tujuan wajib dipilih.']);
        }
        if (isset($row['qty']) !== isset($row['item_id']) || isset($row['meters']) !== isset($row['roll_id'])) {
            throw ValidationException::withMessages(['infra_actions' => 'Jumlah harus mengikuti barang qty atau roll yang dipilih.']);
        }
        if ($row['action_type'] === 'install' && count(array_intersect(['serial_id', 'item_id', 'roll_id'], array_keys($row))) !== 1) {
            throw ValidationException::withMessages(['infra_actions' => 'Pemasangan wajib memilih tepat satu barang.']);
        }
        if ($row['action_type'] === 'replace' && ! isset($row['serial_id'])) {
            throw ValidationException::withMessages(['infra_actions' => 'Ganti aset wajib memilih SN baru.']);
        }
    }

    private function assertTaskNode(Task $task, NetworkNode $node, User $actor): void
    {
        if (Pop::resolveCabangId($task->pop_id) === null || Pop::resolveCabangId($task->pop_id) !== Pop::resolveCabangId($node->resolvePop()?->id)) {
            throw ValidationException::withMessages(['infra_actions' => 'Node aksi berada di luar cabang task.']);
        }
        $this->assertNetworkNodeInScope($node, $actor, $this->access);
    }

    /** @param array<string, mixed> $row
     * @return array<int, int>
     */
    private function apply(array $row, ?NetworkNode $node, FopTask $fopTask, iterable $technicians, User $actor, Task $task): array
    {
        $type = $row['action_type'];
        if ($type === 'inspect') {
            if (count(array_diff(array_keys($row), ['action_type', 'network_node_id', 'port_id'])) > 0) {
                throw new InvalidArgumentException('Pemeriksaan tidak boleh berisi mutasi aset.');
            }
            if (isset($row['port_id']) && NetworkNodePort::findOrFail($row['port_id'])->network_node_id !== $node?->id) {
                throw new InvalidArgumentException('Port pemeriksaan bukan milik node.');
            }

            return [];
        }
        if (in_array($type, ['port_connect', 'port_release'], true)) {
            $port = NetworkNodePort::findOrFail($row['port_id'] ?? 0);
            if ($port->network_node_id !== $node?->id) {
                throw new InvalidArgumentException('Port bukan milik node aksi.');
            }
            if ($type === 'port_connect') {
                $target = NetworkNode::findOrFail($row['target_node_id'] ?? 0);
                $this->assertTaskNode($task, $target, $actor);
                $targetPort = NetworkNodePort::findOrFail($row['target_port_id']);
                $this->ports->assignNode($port, $target, $actor, $targetPort);
            } else {
                if (! $port->activeAssignment()->exists()) {
                    throw new InvalidArgumentException('Port tidak sedang tersambung.');
                }
                $this->ports->release($port, $actor, 'Laporan '.$fopTask->task_number);
            }

            return [];
        }
        if (! in_array($type, ['install', 'retrieve', 'replace'], true) || ! $node) {
            throw new InvalidArgumentException('Jenis aksi infrastruktur tidak valid.');
        }
        $ids = [];
        if (in_array($type, ['retrieve', 'replace'], true)) {
            $ids[] = $this->inventory->retrieveSerialFromLocation(
                InventorySerial::findOrFail($row['old_serial_id'] ?? 0), $fopTask, $actor, $node
            )->id;
        }
        if (in_array($type, ['install', 'replace'], true)) {
            $kinds = count(array_filter(['serial_id', 'item_id', 'roll_id'], fn ($field) => isset($row[$field])));
            if ($kinds !== 1 || ($type === 'replace' && ! isset($row['serial_id']))) {
                throw new InvalidArgumentException('Pilih tepat satu barang; ganti wajib memilih SN baru.');
            }
            if ($node->nodeType?->code === 'olt_card' && ! isset($row['serial_id'])) {
                throw ValidationException::withMessages(['infra_actions' => 'Kartu OLT wajib memakai SN aset kartu.']);
            }
            if ($node->nodeType?->code === 'olt_card' && ! isset($row['slot_id'])) {
                throw ValidationException::withMessages(['infra_actions' => 'Slot wajib dipilih untuk kartu OLT.']);
            }
            if (isset($row['slot_id'])) {
                $this->attachCardSlot($row['slot_id'], $node);
            }
            if (isset($row['serial_id'])) {
                $ids[] = $this->inventory->deploySerial(
                    InventorySerial::findOrFail($row['serial_id']), $node, $fopTask, $technicians, $actor
                )->id;
            } elseif (isset($row['item_id'])) {
                foreach ($this->inventory->deployQuantity(
                    $technicians, Item::findOrFail($row['item_id']), $row['qty'] ?? 0, $node, $fopTask, $actor
                ) as $lot) {
                    $ids[] = $lot['transaction_id'];
                }
            } else {
                $ids[] = $this->inventory->deployRoll(
                    InventoryRoll::findOrFail($row['roll_id']), $row['meters'] ?? 0,
                    $node, $technicians, $fopTask, $actor
                )->id;
            }
        }

        return $ids;
    }

    private function attachCardSlot(int $slotId, NetworkNode $card): void
    {
        $slot = OltSlot::query()->lockForUpdate()->findOrFail($slotId);
        if ($card->nodeType?->code !== 'olt_card' || $card->parent_id !== $slot->chassis_node_id) {
            throw new InvalidArgumentException('Node kartu harus anak chassis pemilik slot.');
        }
        if ($slot->card_node_id !== null && $slot->card_node_id !== $card->id) {
            throw new InvalidArgumentException('Slot OLT sudah terisi.');
        }
        if (OltSlot::query()->where('card_node_id', $card->id)->whereKeyNot($slot->id)->exists()) {
            throw new InvalidArgumentException('Kartu sudah terpasang di slot lain.');
        }
        $slot->update(['card_node_id' => $card->id]);
    }
}
