<?php

namespace App\Services;

use App\Enums\SerialStatus;
use App\Models\AuditLog;
use App\Models\InfrastructureBalance;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\InventorySerial;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\NetworkPortAssignment;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Memindahkan sebuah `NetworkNode` ke penempatan baru — ke dalam node induk
 * lain (`parent_id`) ATAU ditambatkan ke POP (`anchor_pop_id`), tidak pernah
 * dua-duanya (aturan XOR, lihat komentar di `NetworkNode`). Satu-satunya
 * titik di aplikasi yang boleh mengubah penempatan node setelah dibuat —
 * `assertPlacement()` dipakai juga oleh create/update supaya aturan XOR
 * cuma ada di satu tempat.
 */
class NetworkNodeService
{
    /**
     * Pindahkan `$node` ke `$newParent` (di dalam node lain) atau `$newPop`
     * (ditambatkan ke POP) — persis salah satu, tidak pernah dua-duanya.
     *
     * Seluruh mutasi (lock baris, cek siklus, update, tulis audit) terjadi
     * dalam satu `DB::transaction()` — kalau ada yang melempar exception
     * (siklus, dll), semuanya dibatalkan, tidak ada state parsial.
     */
    public function move(NetworkNode $node, ?NetworkNode $newParent, ?Pop $newPop, User $actor): void
    {
        self::assertPlacement($newParent, $newPop);

        DB::transaction(function () use ($node, $newParent, $newPop, $actor) {
            // Re-fetch + lock baris di dalam transaksi — bukan $node yang
            // diterima dari caller — supaya dua pemindahan node yang sama
            // yang datang nyaris bersamaan tidak saling menimpa.
            $node = NetworkNode::lockForUpdate()->findOrFail($node->id);

            if ($newParent && $this->isSelfOrDescendant($newParent, $node)) {
                throw new InvalidArgumentException('Node tidak boleh dipindah ke dalam dirinya sendiri atau turunannya.');
            }

            $this->assertTypePlacement($node->nodeType, $newParent, $newPop);

            $fromPopId = $node->resolvePop()?->id;
            $toPopIdBeforeMove = $newParent?->resolvePop()?->id
                ?? ($newPop !== null ? Pop::resolveCabangId($newPop->id) : null);
            $access = app(EffectiveAccessService::class);
            $allowedPopIds = $access->getAllowedPopIds($actor);
            abort_unless(
                $fromPopId !== null
                    && $toPopIdBeforeMove !== null
                    && ($access->hasAllPopAccess($actor)
                        || (in_array($fromPopId, $allowedPopIds, true)
                            && in_array($toPopIdBeforeMove, $allowedPopIds, true))),
                403,
                'Aktor harus memiliki scope cabang asal dan tujuan.'
            );
            if ($fromPopId !== $toPopIdBeforeMove) {
                $this->assertCrossBranchSubtreeIsEmpty($node->id);
            }

            $oldParentId = $node->parent_id;
            $oldAnchorPopId = $node->anchor_pop_id;

            $node->update([
                'parent_id' => $newParent?->id,
                'anchor_pop_id' => $newPop?->id,
            ]);

            $node->refresh();

            // resolvePop() memo lewat once() berdasarkan instance objek — kalau
            // dipanggil ulang di instance $node yang sama, hasilnya bisa balik
            // cache SEBELUM update (bug: fromPopId == toPopId walau anchor
            // beda). Re-fetch instance baru di sini supaya resolvePop() benar2
            // menghitung ulang rantai parent dengan kolom terbaru.
            $toPopId = NetworkNode::find($node->id)?->resolvePop()?->id;

            // Audit lewat mekanisme generik AuditLog yang sudah ada
            // (Open Item #6, RESOLVED) — ditulis manual dengan $actor->id
            // EKSPLISIT, bukan AuditLog::log() yang hardcode auth()->id(),
            // karena $actor pemanggil move() bisa berbeda dari user yang
            // sedang login (mis. artisan command). pop_id = cabang hasil
            // resolvePop() (bisa null kalau subtree belum punya anchor di
            // puncak), parent_id/anchor_pop_id = kolom mentah sebelum &
            // sesudah, supaya "pindah dalam cabang yang sama" (pop_id tetap,
            // parent_id berubah) bisa dibedakan dari "pindah lintas cabang".
            AuditLog::create([
                'user_id' => $actor->id,
                'module' => 'Network Node',
                'action' => 'move',
                'auditable_type' => NetworkNode::class,
                'auditable_id' => $node->id,
                'old_values' => [
                    'pop_id' => $fromPopId,
                    'parent_id' => $oldParentId,
                    'anchor_pop_id' => $oldAnchorPopId,
                ],
                'new_values' => [
                    'pop_id' => $toPopId,
                    'parent_id' => $node->parent_id,
                    'anchor_pop_id' => $node->anchor_pop_id,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Aturan XOR penempatan node: persis satu dari `$parent`/`$anchor` wajib
     * terisi. Dipanggil `move()` dan (nanti) create/update node, supaya
     * aturan ini cuma ada di satu tempat.
     */
    public static function assertPlacement(?NetworkNode $parent, ?Pop $anchor): void
    {
        if ($parent !== null && $anchor !== null) {
            throw new InvalidArgumentException('Node tidak boleh punya parent dan anchor POP sekaligus.');
        }

        if ($parent === null && $anchor === null) {
            throw new InvalidArgumentException('Node wajib punya parent atau anchor POP — tidak boleh keduanya kosong.');
        }
    }

    /**
     * Naik dari `$candidate` lewat rantai `parent` sampai puncak — kalau
     * ketemu `$node`, berarti `$candidate` adalah `$node` sendiri atau
     * turunannya, dan pemindahan itu bikin siklus di pohon.
     *
     * Loop biasa, bukan recursive CTE — kedalaman pohon ini kecil (lihat
     * brief Task 6), jadi query rekursif cuma overhead yang tidak perlu.
     */
    /**
     * Rules are opt-in for existing custom types. Once a child type has at least
     * one rule, every new placement must match a managed parent or POP type.
     */
    public function assertTypePlacement(NetworkNodeType $type, ?NetworkNode $parent, ?Pop $anchor): void
    {
        self::assertPlacement($parent, $anchor);

        $rules = DB::table('network_node_type_rules')
            ->where('child_type_id', $type->id)
            ->get(['parent_type_id', 'anchor_pop_type']);

        if ($rules->isEmpty()) {
            return;
        }

        $allowed = $rules->contains(function (object $rule) use ($parent, $anchor): bool {
            if ($parent !== null) {
                return $rule->parent_type_id !== null
                    && (int) $rule->parent_type_id === $parent->node_type_id;
            }

            return $rule->anchor_pop_type !== null && $rule->anchor_pop_type === $anchor?->type;
        });

        if (! $allowed) {
            throw new InvalidArgumentException("Tipe {$type->name} tidak boleh ditempatkan di induk atau POP tersebut.");
        }
    }

    /**
     * A subtree with live physical connections or deployed stock needs a
     * dedicated relocation workflow so its customer and ledger scope stays
     * correct. Lock its ports before checking to serialize against assignment.
     */
    private function assertCrossBranchSubtreeIsEmpty(int $nodeId): void
    {
        $rows = DB::select(
            'WITH RECURSIVE subtree AS (
                SELECT id FROM network_nodes WHERE id = ?
                UNION ALL
                SELECT child.id FROM network_nodes child JOIN subtree parent ON child.parent_id = parent.id
            ) SELECT id FROM subtree',
            [$nodeId]
        );
        $nodeIds = array_map(static fn (object $row): int => (int) $row->id, $rows);
        $portIds = NetworkNodePort::query()->whereIn('network_node_id', $nodeIds)
            ->orderBy('id')->lockForUpdate()->pluck('id');

        $hasAssignments = NetworkPortAssignment::query()->where('active_key', 1)
            ->where(function ($query) use ($portIds, $nodeIds) {
                $query->whereIn('network_node_port_id', $portIds)
                    ->orWhereIn('target_node_id', $nodeIds);
            })->exists();
        // Termasuk aset di Slot/Port milik node ini (posisi kanonis Task 6) —
        // kartu di Slot 1 OLT ikut pindah cabang kalau OLT-nya dipindah.
        $slotIds = InfrastructureSlot::query()->whereIn('network_node_id', $nodeIds)->pluck('id');
        $hasSerials = InventorySerial::query()->where('status', SerialStatus::DEPLOYED)
            ->where(fn ($query) => $query->whereIn('current_network_node_id', $nodeIds)
                ->orWhereIn('current_infrastructure_slot_id', $slotIds)
                ->orWhereIn('current_infrastructure_port_id', InfrastructurePort::query()->whereIn('infrastructure_slot_id', $slotIds)->select('id')))
            ->exists();
        $hasBalances = InfrastructureBalance::query()->whereIn('network_node_id', $nodeIds)
            ->where('qty', '>', 0)->exists();

        // Koneksi kanonis (Task 3): node jadi tujuan sambungan aktif (mis. ODP
        // ber-uplink PON), atau PON/Port milik slot node ini masih tersambung.
        // Memindah lintas cabang tanpa rekonsiliasi membuat uplink menunjuk
        // lintas Cabang dan merusak scope laporan.
        $portIds = InfrastructurePort::query()
            ->whereIn('infrastructure_slot_id', InfrastructureSlot::query()->whereIn('network_node_id', $nodeIds)->select('id'))
            ->pluck('id');
        $hasConnections = InfrastructureConnection::query()->active()
            ->where(fn ($query) => $query->whereIn('target_network_node_id', $nodeIds)
                ->orWhereIn('source_infrastructure_port_id', $portIds)
                ->orWhereIn('target_infrastructure_port_id', $portIds))
            ->exists();

        if ($hasAssignments || $hasSerials || $hasBalances || $hasConnections) {
            throw new InvalidArgumentException(
                'Node dengan koneksi pelanggan, sambungan jaringan, atau aset terpasang tidak boleh dipindah lintas cabang.'
            );
        }
    }

    private function isSelfOrDescendant(NetworkNode $candidate, NetworkNode $node): bool
    {
        $current = $candidate;

        while ($current !== null) {
            if ($current->getKey() === $node->getKey()) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }
}
