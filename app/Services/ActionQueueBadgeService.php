<?php

namespace App\Services;

use App\Enums\CashDepositStatus;
use App\Enums\DepositStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\PengajuanBarangStatus;
use App\Enums\SerialStatus;
use App\Enums\StockRequestStatus;
use App\Enums\TransferStatus;
use App\Models\CashDeposit;
use App\Models\CollectorDeposit;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\IsolationCase;
use App\Models\PengajuanBarang;
use App\Models\StockRequest;
use App\Models\TaskDeviceRetrieval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ActionQueueBadgeService
{
    public function __construct(
        private readonly EffectiveAccessService $access,
        private readonly IsolationCaseService $isolationCaseService,
    ) {}

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

    /** @return Builder<PengajuanBarang> */
    public function pengajuanBarang(User $user): Builder
    {
        $query = PengajuanBarang::query()->where('status', PengajuanBarangStatus::MENUNGGU_FOP->value);

        if (! $user->hasPermission('warehouse_pengajuan_barang.view') || ! $user->hasPermission('warehouse_pengajuan_barang.approve')) {
            return $query->whereRaw('1 = 0');
        }

        if (! $user->hasFullAccess() && ! $user->is_pengajuan_barang_approver) {
            return $query->whereRaw('1 = 0');
        }

        if (! $this->access->hasAllPopAccess($user)) {
            $query->whereIn('pop_id', $this->access->getAllowedPopIds($user));
        }

        return $query;
    }

    /**
     * Laporan penarikan alat GAGAL yang menunggu keputusan FOP (Review
     * Penarikan Alat). Badge hanya untuk pemegang `device_retrieval_review.review`
     * — CS/BD yang cuma bisa melihat tidak perlu ditagih angka antrian yang
     * tidak bisa mereka selesaikan. Query & POP scope satu sumber dengan
     * halaman (DeviceRetrievalReviewService::pendingForUser()).
     *
     * @return Builder<TaskDeviceRetrieval>
     */
    public function pendingRetrievalReviews(User $user): Builder
    {
        $query = app(DeviceRetrievalReviewService::class)->pendingForUser($user);

        if (! $user->hasPermission('device_retrieval_review.view') || ! $user->hasPermission('device_retrieval_review.review')) {
            return $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * Modem retur yang masih transit di teknisi, menunggu "Terima Retur" di
     * gudang cabang. Basis query daftar `WarehouseReturnReceiveController::index()`
     * juga — kalau salah satu diubah sendiri, angka badge dan isi daftar beda.
     *
     * `current_technician_id` wajib terisi: SN yang SUDAH diterima cabang (atau
     * walk-in dari pelanggan) tetap `RETURNED` tapi teknisinya dilepas — itu
     * antrian "Kirim ke Pusat", bukan antrian ini, dan
     * `InventoryReassignService::confirmReturnedSerial()` menolak menerimanya lagi.
     *
     * @return Builder<InventorySerial>
     */
    public function pendingReturnReceipts(User $user): Builder
    {
        $query = InventorySerial::query()
            ->where('status', SerialStatus::RETURNED->value)
            ->whereNotNull('current_technician_id');

        if (! $user->hasPermission('warehouse_reassign.create')) {
            return $query->whereRaw('1 = 0');
        }

        if (! $this->access->hasAllPopAccess($user)) {
            $query->whereIn('issued_from_pop_id', $this->access->getAllowedPopIds($user));
        }

        return $query;
    }

    /**
     * Modem retur yang sudah dikirim Cabang dan menunggu konfirmasi "Terima di
     * Pusat". SATU sumber query untuk badge DAN `WarehouseReturnPusatController`
     * (daftar + guard halaman create) — jangan salin lagi ke controller.
     *
     * Dispatch retur dibedakan dari transfer stok biasa (Pusat→Cabang) murni
     * lewat arah `to_pop.type = pusat` pada transfer IN_TRANSIT, tanpa kolom
     * penanda baru (lihat docblock `InventoryReturnTransferService`).
     *
     * @return Builder<InventorySerial>
     */
    public function pendingPusatReturns(User $user): Builder
    {
        $query = InventorySerial::query()->where('status', SerialStatus::TRANSFERRED->value);

        if (! $user->hasPermission('warehouse_reassign.create') || ! $user->hasPermission('warehouse_transfer.create')) {
            return $query->whereRaw('1 = 0');
        }

        $allPop = $this->access->hasAllPopAccess($user);
        $popIds = $allPop ? [] : $this->access->getAllowedPopIds($user);

        return $query->whereIn('id', InventoryTransaction::query()
            ->select('serial_id')
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->whereNotNull('from_pop_id')
            ->whereNull('to_pop_id')
            ->whereNotNull('inventory_transfer_id')
            ->whereHas('transfer', fn (Builder $transfer) => $transfer
                ->where('status', TransferStatus::IN_TRANSIT->value)
                ->whereHas('toPop', fn (Builder $pop) => $pop->where('type', 'pusat'))
                ->when(! $allPop, fn (Builder $scoped) => $scoped->whereIn('to_pop_id', $popIds))));
    }

    /**
     * Monitoring Isolir — total kasus yang masih perlu dipantau/ditindaklanjuti
     * (menunggu isolir + sudah diisolir + pemulihan), TIDAK termasuk riwayat
     * (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §21). SATU sumber
     * query dengan halaman (`IsolationCaseService`), bukan dihitung ulang
     * di sini.
     *
     * @return Builder<IsolationCase>
     */
    public function isolirQueue(User $user): Builder
    {
        if (! $user->hasPermission('customers.isolir_queue.view')) {
            return IsolationCase::query()->whereRaw('1 = 0');
        }

        return $this->isolationCaseService->casesQuery($user)->where('active_slot', 1);
    }
}
