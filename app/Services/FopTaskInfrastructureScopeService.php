<?php

namespace App\Services;

use App\Enums\InfrastructureSlotKind;
use App\Enums\TaskType;
use App\Models\AuditLog;
use App\Models\FopTask;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\User;
use App\Support\InfrastructureLocation;
use Illuminate\Validation\ValidationException;

/**
 * Akar scope Task FOP O-REQ/INFR-REQ sebagai batas subtree (§10.3).
 *
 * Lokasi aksi sah = izin POP aktor ∩ Cabang task ∩ subtree akar scope.
 * SEMUA jalur tulis laporan (baris aksi jamak, form tunggal lama, koreksi,
 * ujung sambungan, SN lama) wajib lewat `assertLocationInTaskScope()` —
 * filter dropdown di browser hanya membantu, bukan pengaman.
 */
class FopTaskInfrastructureScopeService
{
    /**
     * Urutan dropdown form FOP. Tingkat yang lebih dalam hanya sah bila
     * tingkat di atasnya terisi — celah (mis. perangkat tanpa Mini POP)
     * adalah request buatan dan ditolak.
     */
    public const INPUT_LEVELS = ['scope_mini_pop_id', 'scope_device_id', 'scope_slot_id', 'scope_port_id', 'scope_odp_id'];

    public function __construct(
        private readonly InfrastructureLocationResolver $resolver,
        private readonly EffectiveAccessService $access,
    ) {}

    public static function supportsCategory(TaskType|string|null $category): bool
    {
        $value = $category instanceof TaskType ? $category->value : $category;

        return in_array($value, [TaskType::OREQ->value, TaskType::INFR->value], true);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function hasScopeInput(array $input): bool
    {
        foreach (self::INPUT_LEVELS as $key) {
            if (($input[$key] ?? null) !== null && $input[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function resolveRequestedRoot(Pop $taskPop, array $input): InfrastructureLocation
    {
        $values = [];
        $gapAt = null;
        foreach (self::INPUT_LEVELS as $key) {
            $raw = $input[$key] ?? null;
            $value = ($raw === null || $raw === '') ? null : (int) $raw;
            if ($value !== null && $gapAt !== null) {
                $this->fail($key, 'Pilihan lokasi tidak berurutan: tingkat di atasnya belum dipilih.');
            }
            if ($value === null && $gapAt === null) {
                $gapAt = $key;
            }
            $values[$key] = $value;
        }

        $taskCabang = Pop::resolveCabangId($taskPop->id);

        if ($values['scope_mini_pop_id'] === null) {
            return $this->resolver->forPop($taskPop);
        }

        $miniPop = Pop::find($values['scope_mini_pop_id']);
        if ($miniPop === null || $miniPop->type !== 'mini_pop' || Pop::resolveCabangId($miniPop->id) !== $taskCabang) {
            $this->fail('scope_mini_pop_id', 'Mini POP harus berada di Cabang task.');
        }
        if ($values['scope_device_id'] === null) {
            return $this->resolver->forPop($miniPop);
        }

        $device = NetworkNode::query()->with('nodeType')->find($values['scope_device_id']);
        if ($device === null || $device->anchor_pop_id !== $miniPop->id
            || InfrastructureSlotKind::forDeviceTypeCode($device->nodeType?->code) === null) {
            $this->fail('scope_device_id', 'Perangkat harus OLT atau Switch di Mini POP terpilih.');
        }
        if ($values['scope_slot_id'] === null) {
            return $this->resolver->forNode($device);
        }

        $slot = NetworkNode::query()->with('nodeType')->find($values['scope_slot_id']);
        // Slot kini node anak langsung perangkat; "milik perangkat lain/cabang lain" cukup dicek lewat parent_id.
        if ($slot === null || ! $slot->isSlot() || $slot->parent_id !== $device->id) {
            $this->fail('scope_slot_id', 'Slot harus milik perangkat terpilih.');
        }
        if ($values['scope_port_id'] === null) {
            return $this->resolver->forSlot($slot);
        }

        $port = NetworkNode::query()->with('nodeType')->find($values['scope_port_id']);
        if ($port === null || ! $port->isPort() || $port->parent_id !== $slot->id) {
            $this->fail('scope_port_id', 'PON/Port harus milik slot terpilih.');
        }
        if ($values['scope_odp_id'] === null) {
            return $this->resolver->forPort($port);
        }

        if (! $port->isPon()) {
            $this->fail('scope_odp_id', 'Jalur Switch berhenti di Port — ODP tidak tersedia.');
        }
        $odp = NetworkNode::query()->with('nodeType')->find($values['scope_odp_id']);
        // ODP anak langsung PON (parent_id), bukan lagi lewat InfrastructureConnection.
        $isChild = $odp !== null && $odp->nodeType?->code === 'odp' && $odp->parent_id === $port->id;
        if (! $isChild) {
            $this->fail('scope_odp_id', 'ODP harus tersambung ke PON terpilih.');
        }

        return $this->resolver->forNode($odp);
    }

    public function rootFor(FopTask $fopTask): InfrastructureLocation
    {
        return match (true) {
            $fopTask->scope_infrastructure_port_id !== null => $this->resolver->resolve(InfrastructureLocation::PORT, $fopTask->scope_infrastructure_port_id),
            $fopTask->scope_infrastructure_slot_id !== null => $this->resolver->resolve(InfrastructureLocation::SLOT, $fopTask->scope_infrastructure_slot_id),
            $fopTask->scope_network_node_id !== null => $this->resolver->resolve(InfrastructureLocation::NODE, $fopTask->scope_network_node_id),
            $fopTask->scope_pop_id !== null => $this->resolver->resolve(InfrastructureLocation::POP, $fopTask->scope_pop_id),
            // Task lama (sebelum akar scope ada) — akar jatuh ke Cabang task.
            default => $this->resolver->resolve(InfrastructureLocation::POP, (int) $fopTask->pop_id),
        };
    }

    public function applyScope(FopTask $fopTask, InfrastructureLocation $root, User $actor): void
    {
        if (! self::supportsCategory($fopTask->category)) {
            $this->fail('scope_mini_pop_id', 'Akar scope infrastruktur hanya untuk Task O-REQ/INFR-REQ.');
        }

        $taskCabang = Pop::resolveCabangId($fopTask->pop_id);
        if ($taskCabang === null || $root->cabangId !== $taskCabang) {
            $this->fail('scope_mini_pop_id', 'Akar scope harus berada di Cabang task.');
        }
        $this->assertActorCanReach($root, $actor, 'scope_mini_pop_id');

        $columns = [
            'scope_pop_id' => in_array($root->kind, [InfrastructureLocation::POP, InfrastructureLocation::MINI_POP], true) ? $root->id : null,
            'scope_network_node_id' => $root->kind === InfrastructureLocation::NODE ? $root->id : null,
            'scope_infrastructure_slot_id' => $root->kind === InfrastructureLocation::SLOT ? $root->id : null,
            'scope_infrastructure_port_id' => $root->kind === InfrastructureLocation::PORT ? $root->id : null,
        ];
        $oldSnapshot = $fopTask->scope_snapshot;
        $newSnapshot = $root->snapshot();

        if (($oldSnapshot['key'] ?? null) === $newSnapshot['key']) {
            return;
        }

        $fopTask->forceFill([...$columns, 'scope_snapshot' => $newSnapshot])->save();

        if ($oldSnapshot !== null) {
            AuditLog::create([
                'user_id' => $actor->id,
                'module' => 'Task FOP',
                'action' => 'scope_change',
                'auditable_type' => FopTask::class,
                'auditable_id' => $fopTask->id,
                'old_values' => ['scope' => $oldSnapshot['key'] ?? null, 'breadcrumb' => $oldSnapshot['breadcrumb'] ?? null],
                'new_values' => ['scope' => $newSnapshot['key'], 'breadcrumb' => $newSnapshot['breadcrumb']],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Irisan wajib: Cabang task ∩ POP scope aktor ∩ subtree akar scope.
     */
    public function assertLocationInTaskScope(FopTask $fopTask, InfrastructureLocation $location, User $actor, string $field = 'infra_actions'): void
    {
        $taskCabang = Pop::resolveCabangId($fopTask->pop_id);
        if ($taskCabang === null || $location->cabangId !== $taskCabang) {
            $this->fail($field, "Lokasi {$location->label()} berada di luar Cabang task.");
        }

        $this->assertActorCanReach($location, $actor, $field);

        $root = $this->rootFor($fopTask);
        if (! $root->contains($location)) {
            $this->fail($field, "Lokasi {$location->label()} berada di luar scope task ({$root->breadcrumb()}).");
        }
    }

    public function assertActorCanReach(InfrastructureLocation $location, User $actor, string $field): void
    {
        if ($this->access->hasAllPopAccess($actor)) {
            return;
        }

        $popId = $location->deepestPopId();
        if ($popId === null || ! in_array($popId, $this->access->getAllowedPopIds($actor), true)) {
            $this->fail($field, 'Lokasi berada di luar POP scope Anda.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
