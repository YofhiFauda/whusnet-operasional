<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Daftar bukti barang gudang yang terpasang netap di infrastruktur
 * (ODC/ODP/dst) — padanan "modem terpasang di pelanggan", tapi untuk aset
 * jaringan. Sumbernya ledger `DEPLOY` (bukan proyeksi), supaya tanggal
 * pasang, teknisi (`from_technician_id`), dan Task (`fop_task_id`) ikut
 * terbawa.
 *
 * Aturan baris:
 * - SN (serialized): hanya deploy TERAKHIR per SN, dan hanya kalau SN-nya
 *   masih `DEPLOYED`. SN yang sudah dicabut (`RETRIEVE_INFRA`) atau sudah
 *   dipasang ulang di tempat lain tidak tampil ganda.
 * - QTY/ROLL: setiap baris `DEPLOY` adalah bukti satu kejadian pemasangan.
 *   Tidak ada jalur cabut untuk QTY/ROLL, jadi semua baris dianggap masih
 *   terpasang (sama dengan `infrastructure_balances`).
 *
 * Scope POP lewat lokasi node SEKARANG (`inPops()`), bukan snapshot
 * `to_pop_id`: orang yang berhak atas cabang itu hari ini yang boleh melihat
 * barangnya. Snapshot tetap ditampilkan sebagai riwayat di ledger.
 */
class NetworkInventoryService
{
    public function __construct(private readonly EffectiveAccessService $access) {}

    /**
     * @param  array{q?: ?string, pop_id?: ?int, tipe?: ?string}  $filters
     * @return LengthAwarePaginator<int, InventoryTransaction>
     */
    public function paginate(User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->filtered($user, $filters)
            ->with([
                'item',
                'serial',
                'fromTechnician',
                'fopTask.task',
                'toNetworkNode.parent',
                'toNetworkNode.anchorPop.parent',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{q?: ?string, pop_id?: ?int, tipe?: ?string}  $filters
     * @return array{sn: int, qty: int, nodes: int}
     */
    public function summary(User $user, array $filters): array
    {
        $base = $this->filtered($user, $filters);

        return [
            'sn' => (clone $base)->whereNotNull('serial_id')->count(),
            'qty' => (clone $base)->whereNull('serial_id')->count(),
            'nodes' => (clone $base)->distinct()->count('to_network_node_id'),
        ];
    }

    /**
     * Cabang yang boleh dipilih di filter: sesuai scope user, dan hanya tipe
     * cabang (Mini POP ikut lewat `parent_id` saat filter diterapkan).
     *
     * @return Collection<int, Pop>
     */
    public function cabangOptions(User $user)
    {
        return Pop::forUser($user)
            ->where('status', 'active')
            ->where('type', 'cabang')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{q?: ?string, pop_id?: ?int, tipe?: ?string}  $filters
     * @return Builder<InventoryTransaction>
     */
    private function filtered(User $user, array $filters): Builder
    {
        $latestSerialDeploy = InventoryTransaction::query()
            ->selectRaw('MAX(id)')
            ->where('type', InventoryTransactionType::DEPLOY->value)
            ->whereNotNull('serial_id')
            ->groupBy('serial_id');

        $query = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::DEPLOY->value)
            ->where(function (Builder $rows) use ($latestSerialDeploy): void {
                $rows->whereNull('serial_id')
                    ->orWhere(function (Builder $serial) use ($latestSerialDeploy): void {
                        $serial->whereIn('id', $latestSerialDeploy)
                            ->whereHas('serial', fn (Builder $sn) => $sn->where('status', SerialStatus::DEPLOYED->value));
                    });
            });

        $popIds = $this->access->hasAllPopAccess($user) ? null : $this->access->getAllowedPopIds($user);

        if (! empty($filters['pop_id'])) {
            // Cabang yang dipilih + Mini POP di bawahnya, lalu dibatasi scope
            // user. Pilihan di luar scope jadi himpunan kosong (deny-by-default).
            $selected = Pop::query()
                ->where('id', (int) $filters['pop_id'])
                ->orWhere('parent_id', (int) $filters['pop_id'])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $popIds = $popIds === null ? $selected : array_values(array_intersect($popIds, $selected));
        }

        if ($popIds !== null) {
            $query->whereHas('toNetworkNode', fn (Builder $node) => $node->inPops($popIds));
        }

        $tipe = $filters['tipe'] ?? null;
        if ($tipe === 'sn') {
            $query->whereNotNull('serial_id');
        } elseif ($tipe === 'roll') {
            $query->whereNotNull('roll_id');
        } elseif ($tipe === 'qty') {
            $query->whereNull('serial_id')->whereNull('roll_id');
        }

        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $like = '%'.addcslashes($keyword, '%_\\').'%';
            $query->where(function (Builder $search) use ($like): void {
                $search->where('lot_no', 'like', $like)
                    ->orWhereHas('serial', fn (Builder $sn) => $sn->where('serial_number', 'like', $like))
                    ->orWhereHas('item', fn (Builder $item) => $item->where('name', 'like', $like)->orWhere('code', 'like', $like))
                    ->orWhereHas('toNetworkNode', fn (Builder $node) => $node->where('name', 'like', $like)->orWhere('code', 'like', $like))
                    ->orWhereHas('fopTask', fn (Builder $task) => $task->where('task_number', 'like', $like))
                    ->orWhereHas('fopTask.task', fn (Builder $task) => $task->where('task_number', 'like', $like));
            });
        }

        return $query;
    }
}
