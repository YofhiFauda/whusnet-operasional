<?php

namespace App\Services;

use App\Enums\CashDepositStatus;
use App\Enums\DepositStatus;
use App\Enums\StockRequestStatus;
use App\Models\CashDeposit;
use App\Models\CollectorDeposit;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ActionQueueBadgeService
{
    public function __construct(private readonly EffectiveAccessService $access) {}

    /** @return Builder<StockRequest> */
    public function stockRequests(User $user): Builder
    {
        $query = StockRequest::query()->whereIn('status', [StockRequestStatus::PENDING->value, StockRequestStatus::PARTIAL->value]);

        if (! $user->hasPermission('warehouse_stock_request.view') || ! $user->hasPermission('warehouse_stock_request.approve')) {
            return $query->whereRaw('1 = 0');
        }

        if (! $this->access->hasAllPopAccess($user)) {
            $query->whereIn('cabang_pop_id', $this->access->getAllowedPopIds($user));
        }

        return $query;
    }

    /** @return Builder<CollectorDeposit> */
    public function collectorDeposits(User $user): Builder
    {
        $query = CollectorDeposit::query()
            ->where('status', DepositStatus::MENUNGGU_VERIFIKASI->value)
            ->where('collector_id', '!=', $user->id);

        if (! $user->hasPermission('collector_worksheet.view') || ! $user->hasPermission('collector_worksheet.validate')) {
            return $query->whereRaw('1 = 0');
        }

        if (! $this->access->hasAllPopAccess($user)) {
            $popIds = $this->access->getAllowedPopIds($user);
            $query->whereIn('pop_id', $popIds)
                ->whereDoesntHave('payments', fn (Builder $payments) => $payments->where(fn (Builder $source) => $source->whereNotIn('pop_id', $popIds)->orWhereNull('pop_id')));
        }

        return $query;
    }

    /** @return Builder<CashDeposit> */
    public function cashDeposits(User $user): Builder
    {
        $query = CashDeposit::query()->realDeposits()
            ->where('status', CashDepositStatus::MENUNGGU_VERIFIKASI->value)
            ->where('depositor_id', '!=', $user->id);

        if (! $user->hasPermission('cash_deposit.view') || ! $user->hasPermission('cash_deposit.validate')) {
            return $query->whereRaw('1 = 0');
        }

        if (! $this->access->hasAllPopAccess($user)) {
            $popIds = $this->access->getAllowedPopIds($user);
            $query->whereIn('pop_id', $popIds)
                ->whereDoesntHave('collectorDeposits', fn (Builder $deposits) => $deposits->where(fn (Builder $source) => $source->whereNotIn('pop_id', $popIds)->orWhereNull('pop_id')))
                ->whereDoesntHave('manualPayments', fn (Builder $payments) => $payments->where(fn (Builder $source) => $source->whereNotIn('pop_id', $popIds)->orWhereNull('pop_id')));
        }

        return $query;
    }
}
