<?php

namespace App\Services;

use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\OltSlot;
use App\Models\Task;
use App\Models\TaskInfrastructureAction;
use App\Models\User;
use App\Support\InfrastructureLocation;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Satu jalur tulis aksi laporan O-REQ/INFR-REQ (baris aksi jamak DAN form
 * tunggal lama yang dikonversi controller ke baris). Setiap baris:
 *
 * 1. Lokasi kerja akhir ditentukan `InfrastructureReportLocationParser`
 *    (dropdown bertingkat) atau, untuk baris format lama, `network_node_id`
 *    — keduanya lalu wajib lolos irisan scope (aktor ∩ Cabang task ∩ subtree).
 * 2. Ujung sambungan kabel, SN lama, SN baru, port, dan target sambungan
 *    diperiksa terhadap lokasi/scope yang sama.
 * 3. `action_key` + `mutation_hash` menjaga idempotensi; satu baris gagal =
 *    seluruh laporan rollback (pemanggil membungkus transaksi).
 *
 * O-REQ inspeksi nonjaringan boleh tanpa lokasi.
 */
class TaskInfrastructureActionService
{
    /** @var list<string> */
    private const COMMON_FIELDS = ['action_type', ...InfrastructureReportLocationParser::LEVELS, 'network_node_id'];

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly NetworkPortAssignmentService $ports,
        private readonly InfrastructureReportLocationParser $parser,
        private readonly InfrastructureLocationResolver $resolver,
        private readonly FopTaskInfrastructureScopeService $scopes,
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
            $mutation = $this->mutationOf($row);
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

            $location = $this->locationOf($fopTask, $mutation, $actor);
            if ($location === null && ($mutation['action_type'] !== 'inspect' || $task->task_type === TaskType::INFR)) {
                throw ValidationException::withMessages(['infra_actions' => 'Lokasi kerja wajib dipilih untuk aksi ini.']);
            }
            $endFrom = $this->parser->parseEnd($fopTask, $mutation['end_from'] ?? null, $actor);
            $endTo = $this->parser->parseEnd($fopTask, $mutation['end_to'] ?? null, $actor);

            $action = TaskInfrastructureAction::create([
                'task_id' => $task->id, 'fop_task_id' => $fopTask->id,
                'action_key' => $key, 'action_type' => $mutation['action_type'],
                ...$this->workColumns($location),
                'work_latitude' => $mutation['work_latitude'] ?? null,
                'work_longitude' => $mutation['work_longitude'] ?? null,
                'work_location_snapshot' => $location?->snapshot(),
                ...$this->endColumns('end_from', $endFrom),
                ...$this->endColumns('end_to', $endTo),
                'mutation_hash' => $hash, 'mutation' => $mutation,
                'scope_snapshot' => $fopTask->scope_snapshot ?? $this->scopes->rootFor($fopTask)->snapshot(),
                'result_note' => $row['result_note'] ?? null,
                'performed_by' => $actor->id, 'performed_at' => now(),
            ]);
            foreach ($this->apply($mutation, $location, $fopTask, $technicians, $actor) as $id) {
                $action->transactions()->attach($id);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mutationOf(array $row): array
    {
        $int = static fn (string $field): ?int => isset($row[$field]) && $row[$field] !== '' ? (int) $row[$field] : null;
        $float = static fn (string $field): ?float => isset($row[$field]) && $row[$field] !== '' ? (float) $row[$field] : null;
        $text = static fn (string $field): ?string => isset($row[$field]) && $row[$field] !== '' ? (string) $row[$field] : null;

        $location = [];
        foreach (InfrastructureReportLocationParser::LEVELS as $level) {
            $location[$level] = isset($row[$level]) && $row[$level] !== ''
                ? ($row[$level] === InfrastructureReportLocationParser::NONE ? InfrastructureReportLocationParser::NONE : (int) $row[$level])
                : null;
        }

        return array_filter([
            'action_type' => $row['action_type'],
            ...$location,
            'network_node_id' => $int('network_node_id'),
            'serial_id' => $int('serial_id'),
            'old_serial_id' => $int('old_serial_id'),
            'item_id' => $int('item_id'),
            'qty' => $float('qty'),
            'roll_id' => $int('roll_id'),
            'meters' => $float('meters'),
            'slot_id' => $int('slot_id'),
            'port_id' => $int('port_id'),
            'target_node_id' => $int('target_node_id'),
            'target_port_id' => $int('target_port_id'),
            'end_from' => $text('end_from'),
            'end_to' => $text('end_to'),
            'work_latitude' => $float('work_latitude'),
            'work_longitude' => $float('work_longitude'),
        ], static fn ($value) => $value !== null);
    }

    /** @param array<string, mixed> $row */
    private function validateActionFields(array $row): void
    {
        $specific = match ($row['action_type']) {
            'inspect' => ['port_id'],
            'install' => ['serial_id', 'item_id', 'qty', 'roll_id', 'meters', 'slot_id', 'end_from', 'end_to', 'work_latitude', 'work_longitude'],
            'retrieve' => ['old_serial_id'],
            'replace' => ['old_serial_id', 'serial_id', 'slot_id'],
            'port_connect' => ['port_id', 'target_node_id', 'target_port_id'],
            'port_release' => ['port_id'],
            default => throw ValidationException::withMessages(['infra_actions' => 'Jenis aksi tidak dikenal.']),
        };
        if (array_diff(array_keys($row), [...self::COMMON_FIELDS, ...$specific]) !== []) {
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
        // Ujung sambungan & titik koordinat hanya bermakna untuk pemakaian
        // kabel (§11.2) — barang lain tidak "menyambung" dua titik.
        $cableOnly = array_intersect(['end_from', 'end_to', 'work_latitude', 'work_longitude'], array_keys($row));
        if ($cableOnly !== [] && ! isset($row['roll_id'])) {
            throw ValidationException::withMessages(['infra_actions' => 'Ujung sambungan dan koordinat hanya untuk pemakaian kabel.']);
        }
        if (isset($row['work_latitude']) !== isset($row['work_longitude'])) {
            throw ValidationException::withMessages(['infra_actions' => 'Latitude dan longitude diisi berpasangan.']);
        }
        if (isset($row['work_latitude']) && (abs($row['work_latitude']) > 90 || abs($row['work_longitude']) > 180)) {
            throw ValidationException::withMessages(['infra_actions' => 'Koordinat tidak valid.']);
        }
    }

    /**
     * @param  array<string, mixed>  $mutation
     */
    private function locationOf(FopTask $fopTask, array $mutation, User $actor): ?InfrastructureLocation
    {
        if (InfrastructureReportLocationParser::hasLocationInput($mutation)) {
            if (isset($mutation['network_node_id'])) {
                throw ValidationException::withMessages(['infra_actions' => 'Pilih lokasi lewat dropdown bertingkat atau node lama, bukan keduanya.']);
            }

            return $this->parser->parse($fopTask, $mutation, $actor);
        }

        if (! isset($mutation['network_node_id'])) {
            return null;
        }

        // Format baris lama (satu node) — guard scope SAMA dengan dropdown.
        $node = NetworkNode::query()->lockForUpdate()->findOrFail($mutation['network_node_id']);
        $location = $this->resolver->forNode($node);
        $this->scopes->assertLocationInTaskScope($fopTask, $location, $actor);

        return $location;
    }

    /**
     * @return array{work_pop_id: ?int, network_node_id: ?int, work_infrastructure_slot_id: ?int, work_infrastructure_port_id: ?int}
     */
    private function workColumns(?InfrastructureLocation $location): array
    {
        return [
            'work_pop_id' => in_array($location?->kind, [InfrastructureLocation::POP, InfrastructureLocation::MINI_POP], true) ? $location->id : null,
            'network_node_id' => $location?->kind === InfrastructureLocation::NODE ? $location->id : null,
            'work_infrastructure_slot_id' => $location?->kind === InfrastructureLocation::SLOT ? $location->id : null,
            'work_infrastructure_port_id' => $location?->kind === InfrastructureLocation::PORT ? $location->id : null,
        ];
    }

    /**
     * @return array<string, ?int>
     */
    private function endColumns(string $prefix, ?InfrastructureLocation $end): array
    {
        return [
            "{$prefix}_pop_id" => in_array($end?->kind, [InfrastructureLocation::POP, InfrastructureLocation::MINI_POP], true) ? $end->id : null,
            "{$prefix}_network_node_id" => $end?->kind === InfrastructureLocation::NODE ? $end->id : null,
            "{$prefix}_infrastructure_port_id" => $end?->kind === InfrastructureLocation::PORT ? $end->id : null,
        ];
    }

    /** @param array<string, mixed> $row
     * @return array<int, int>
     */
    private function apply(array $row, ?InfrastructureLocation $location, FopTask $fopTask, iterable $technicians, User $actor): array
    {
        $type = $row['action_type'];
        $node = $location?->kind === InfrastructureLocation::NODE ? NetworkNode::query()->with('nodeType')->findOrFail($location->id) : null;

        if ($type === 'inspect') {
            if (isset($row['port_id']) && NetworkNodePort::findOrFail($row['port_id'])->network_node_id !== $node?->id) {
                throw new InvalidArgumentException('Port pemeriksaan bukan milik node.');
            }

            return [];
        }
        if (in_array($type, ['port_connect', 'port_release'], true)) {
            if ($node === null) {
                throw ValidationException::withMessages(['infra_actions' => 'Sambung/lepas port ODP memerlukan lokasi node.']);
            }
            $port = NetworkNodePort::findOrFail($row['port_id'] ?? 0);
            if ($port->network_node_id !== $node->id) {
                throw new InvalidArgumentException('Port bukan milik node aksi.');
            }
            if ($type === 'port_connect') {
                $target = NetworkNode::findOrFail($row['target_node_id'] ?? 0);
                $this->scopes->assertLocationInTaskScope($fopTask, $this->resolver->forNode($target), $actor);
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
        if (! in_array($type, ['install', 'retrieve', 'replace'], true) || $location === null) {
            throw new InvalidArgumentException('Jenis aksi infrastruktur tidak valid.');
        }

        $ids = [];
        if (in_array($type, ['retrieve', 'replace'], true)) {
            // SN lama wajib terpasang TEPAT di lokasi aksi — bukan di saudara
            // atau induknya (§10.4 poin 2).
            $ids[] = $this->inventory->retrieveSerialFromLocation(
                InventorySerial::findOrFail($row['old_serial_id'] ?? 0), $fopTask, $actor, expectedLocation: $location
            )->id;
        }
        if (in_array($type, ['install', 'replace'], true)) {
            if ($node?->nodeType?->code === 'olt_card') {
                // Jalur legacy kartu-sebagai-node (sebelum Slot kanonis).
                if (! isset($row['serial_id'])) {
                    throw ValidationException::withMessages(['infra_actions' => 'Kartu OLT wajib memakai SN aset kartu.']);
                }
                if (! isset($row['slot_id'])) {
                    throw ValidationException::withMessages(['infra_actions' => 'Slot wajib dipilih untuk kartu OLT.']);
                }
            }
            if (isset($row['slot_id'])) {
                if ($node === null) {
                    throw ValidationException::withMessages(['infra_actions' => 'Slot lama hanya untuk kartu OLT berbentuk node.']);
                }
                $this->attachCardSlot($row['slot_id'], $node);
            }
            if (isset($row['serial_id'])) {
                $ids[] = $this->inventory->deploySerialAt(
                    InventorySerial::findOrFail($row['serial_id']), $location, $fopTask, $technicians, $actor
                )->id;
            } elseif (isset($row['item_id'])) {
                foreach ($this->inventory->deployQuantityAt(
                    $technicians, Item::findOrFail($row['item_id']), $row['qty'] ?? 0, $location, $fopTask, $actor
                ) as $lot) {
                    $ids[] = $lot['transaction_id'];
                }
            } else {
                $ids[] = $this->inventory->consumeInfrastructureRoll(
                    InventoryRoll::findOrFail($row['roll_id']), $row['meters'] ?? 0,
                    $location, $technicians, $fopTask, $actor
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
