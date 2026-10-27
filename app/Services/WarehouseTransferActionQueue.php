<?php

namespace App\Services;

use App\Enums\TransferStatus;
use App\Models\InventoryTransfer;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class WarehouseTransferActionQueue
{
    public function __construct(private readonly EffectiveAccessService $access) {}

    /**
     * @return array<int, int>
     */
    public function receivablePopIds(User $user): array
    {
        if (! $user->hasPermission('warehouse_transfer.view') || ! $user->hasPermission('warehouse_transfer.receive')) {
            return [];
        }

        $pops = Pop::query();

        if (! $this->access->hasAllPopAccess($user)) {
            $pops->whereIn('id', $this->access->getAllowedPopIds($user));
        }

        if ($user->role?->code === 'pop_admin') {
            $pops->whereDoesntHave('gudangPics');
        }

        if ($user->role?->code === 'pic_gudang') {
            $pops->whereHas('gudangPics', fn (Builder $query) => $query->whereKey($user->id));
        }

        return $pops->pluck('id')->all();
    }

    /**
     * @return Builder<InventoryTransfer>
     */
    public function forUser(User $user): Builder
    {
        return InventoryTransfer::query()
            ->whereDoesntHave('branchSale')
            ->whereIn('status', [TransferStatus::IN_TRANSIT->value, TransferStatus::RECEIVED_PARTIAL->value])
            ->where('reference_number', 'like', 'TRF-%')
            ->whereIn('to_pop_id', $this->receivablePopIds($user));
    }
}
