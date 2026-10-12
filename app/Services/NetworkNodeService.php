<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\NetworkNode;
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

            $fromPopId = $node->resolvePop()?->id;
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
