<?php

namespace App\Services;

use App\Enums\DeviceOwnership;
use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Enums\TransferStatus;
use App\Models\BranchInventorySale;
use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BranchInventorySaleService
{
    /**
     * @param  list<array{serial_id:int, sale_price:string|int|float}>  $lines
     */
    public function create(Pop $fromCabang, Pop $toCabang, array $lines, User $actor): BranchInventorySale
    {
        if (! $fromCabang->isCabang() || ! $toCabang->isCabang() || $fromCabang->id === $toCabang->id) {
            throw new InvalidArgumentException('Jual beli harus dari satu Cabang ke Cabang lain yang berbeda.');
        }
        if ($lines === []) {
            throw new InvalidArgumentException('Pilih minimal satu SN untuk dijual.');
        }

        usort($lines, fn (array $a, array $b) => $a['serial_id'] <=> $b['serial_id']);

        return DB::transaction(function () use ($fromCabang, $toCabang, $lines, $actor) {
            $serials = [];
            $prices = [];
            foreach ($lines as $line) {
                $serialId = (int) ($line['serial_id'] ?? 0);
                if (isset($serials[$serialId])) {
                    throw new InvalidArgumentException('SN yang sama tidak boleh muncul dua kali dalam satu penjualan.');
                }
                $serial = InventorySerial::query()->with('item')->lockForUpdate()->findOrFail($serialId);
                if ($serial->item?->tracking_type !== TrackingType::SERIALIZED
                    || $serial->status !== SerialStatus::AVAILABLE
                    || $serial->current_pop_id !== $fromCabang->id
                    || $serial->current_technician_id !== null
                    || $serial->ownership === DeviceOwnership::CUSTOMER
                    || ! $serial->isClearedForIssue()
                    || InventorySerialSale::query()->where('inventory_serial_id', $serial->id)->exists()) {
                    throw new InvalidArgumentException("SN {$serial->serial_number} tidak layak dijual dari {$fromCabang->name}.");
                }

                $price = (string) ($line['sale_price'] ?? '');
                if (! preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/', $price) || Money::cents($price) <= 0) {
                    throw new InvalidArgumentException("Harga jual SN {$serial->serial_number} harus positif dan maksimal dua desimal.");
                }
                $serials[$serialId] = $serial;
                $prices[$serialId] = Money::decimalString($price);
            }

            $transferLines = collect($serials)->groupBy(fn (InventorySerial $serial) => $serial->item_id)
                ->map(fn ($group, $itemId) => [
                    'item_id' => (int) $itemId,
                    'serial_numbers' => $group->pluck('serial_number')->all(),
                ])->values()->all();

            $transfer = app(InventoryTransferService::class)->createBranchSaleTransfer(
                $fromCabang, $toCabang, $transferLines, $actor
            );
            $sale = BranchInventorySale::create([
                'reference_number' => app(NumberSequenceService::class)->ledgerNumber('JBC'),
                'inventory_transfer_id' => $transfer->id,
                'from_pop_id' => $fromCabang->id,
                'to_pop_id' => $toCabang->id,
                'payment_status' => 'not_tracked',
                'created_by' => $actor->id,
            ]);
            foreach ($serials as $serialId => $serial) {
                $sale->lines()->create([
                    'inventory_serial_id' => $serialId,
                    'sale_price' => $prices[$serialId],
                ]);
            }

            return $sale->load(['transfer', 'lines']);
        });
    }

    /**
     * @param  list<int>  $serialIds
     */
    public function receive(BranchInventorySale $sale, array $serialIds, User $actor): BranchInventorySale
    {
        return DB::transaction(function () use ($sale, $serialIds, $actor) {
            $sale = BranchInventorySale::query()->lockForUpdate()->findOrFail($sale->id);
            $sale->load(['lines.serial', 'transfer']);
            $allowedIds = $sale->lines->pluck('inventory_serial_id')->all();
            if (count($serialIds) !== count(array_unique($serialIds)) || array_diff($serialIds, $allowedIds) !== []) {
                throw new InvalidArgumentException('Daftar SN diterima tidak sesuai dengan dokumen jual beli.');
            }
            $numbers = $sale->lines->whereIn('inventory_serial_id', $serialIds)
                ->map(fn ($line) => $line->serial->serial_number)->values()->all();
            app(InventoryTransferService::class)->receiveTransfer($sale->transfer, $numbers, [], $actor);
            $this->postWhenClosed($sale);

            return $sale->fresh(['transfer', 'lines']);
        });
    }

    public function closeMissing(BranchInventorySale $sale, string $reason, string $evidencePath, User $actor): BranchInventorySale
    {
        return DB::transaction(function () use ($sale, $reason, $evidencePath, $actor) {
            $sale = BranchInventorySale::query()->lockForUpdate()->findOrFail($sale->id);
            app(InventoryTransferService::class)->closeMissingTransfer($sale->transfer, $reason, $evidencePath, $actor);
            $this->postWhenClosed($sale);

            return $sale->fresh(['transfer', 'lines']);
        });
    }

    private function postWhenClosed(BranchInventorySale $sale): void
    {
        $transfer = $sale->transfer->fresh();
        if (! in_array($transfer->status, [TransferStatus::RECEIVED, TransferStatus::CLOSED_LOST], true)) {
            return;
        }

        $receivedIds = InventoryTransaction::query()
            ->where('inventory_transfer_id', $transfer->id)
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->where('to_pop_id', $sale->to_pop_id)
            ->whereNotNull('serial_id')
            ->pluck('serial_id')->all();
        $total = Money::sum($sale->lines()->whereIn('inventory_serial_id', $receivedIds)->pluck('sale_price'));
        $sale->update([
            'posted_total' => Money::decimalString($total),
            'payment_status' => $total > 0 ? 'not_tracked' : 'not_due',
            'posted_at' => now(),
        ]);
    }
}
