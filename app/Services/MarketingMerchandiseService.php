<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\StockRequestKind;
use App\Enums\StockRequestStatus;
use App\Enums\TrackingType;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MarketingMerchandiseService
{
    public function __construct(
        private readonly StockRequestService $stockRequests,
        private readonly InventoryTransferService $transfers,
    ) {}

    public function receive(Pop $pusat, Item $item, float $qty, User $actor, ?string $notes = null): InventoryTransaction
    {
        if (! $pusat->isPusat() || ! $item->isNonOperational() || $item->tracking_type !== TrackingType::QUANTITY || ! $item->is_active) {
            throw new InvalidArgumentException('Barang masuk nonoperasional hanya untuk barang aktif berjenis kuantitas di Gudang Pusat.');
        }

        if ($qty <= 0 || round($qty, 2) !== $qty) {
            throw new InvalidArgumentException('Jumlah barang masuk harus positif dan maksimal dua desimal.');
        }

        return DB::transaction(function () use ($pusat, $item, $qty, $actor, $notes): InventoryTransaction {
            $balance = InventoryBalance::query()->firstOrCreate(
                ['pop_id' => $pusat->id, 'item_id' => $item->id, 'lot_no' => ''],
                ['qty' => 0],
            );
            $balance->increment('qty', $qty);

            return InventoryTransaction::create([
                'type' => InventoryTransactionType::RECEIVE,
                'reference_number' => app(NumberSequenceService::class)->ledgerNumber('RCV'),
                'item_id' => $item->id,
                'qty' => $qty,
                'unit_price_snapshot' => null,
                'to_pop_id' => $pusat->id,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function request(Pop $pusat, Pop $cabang, Item $item, float $qty, User $actor, string $requestKey, ?string $notes = null): StockRequest
    {
        if (! $pusat->isPusat() || ! $cabang->isCabang()) {
            throw new InvalidArgumentException('Barang nonoperasional hanya dapat dipindahkan dari Pusat ke Cabang.');
        }

        if (! $item->isNonOperational() || $item->tracking_type !== TrackingType::QUANTITY || ! $item->is_active) {
            throw new InvalidArgumentException('Pilih barang nonoperasional aktif dengan jenis stok kuantitas.');
        }

        if ($qty <= 0 || round($qty, 2) !== $qty) {
            throw new InvalidArgumentException('Jumlah permintaan harus lebih besar dari nol dan maksimal dua desimal.');
        }

        try {
            return DB::transaction(function () use ($pusat, $cabang, $item, $qty, $actor, $requestKey, $notes): StockRequest {
                $existing = StockRequest::query()->where('request_key', $requestKey)->first();
                if ($existing !== null) {
                    return $this->matchingRequest($existing, $pusat, $cabang, $item, $qty, $actor, $notes);
                }

                $request = $this->stockRequests->create($cabang, [['item_id' => $item->id, 'qty_requested' => $qty]], $actor, $notes, true);
                $request->update(['request_kind' => StockRequestKind::MARKETING, 'request_key' => $requestKey]);

                $balances = InventoryBalance::query()
                    ->where('pop_id', $pusat->id)
                    ->where('item_id', $item->id)
                    ->where('qty', '>', 0)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $remaining = $qty;
                $lines = [];
                foreach ($balances as $balance) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $taken = min($remaining, (float) $balance->qty);
                    $lines[] = ['item_id' => $item->id, 'qty' => $taken, 'lot_no' => $balance->lot_no];
                    $remaining = round($remaining - $taken, 2);
                }

                if ($remaining > 0) {
                    throw new InvalidArgumentException('Stok merchandise di Gudang Pusat tidak cukup.');
                }

                $transfer = $this->transfers->createTransfer($pusat, $cabang, $lines, $actor, true);
                $confirmed = $transfer->transactions()->whereNotNull('from_pop_id')->get()
                    ->mapWithKeys(fn ($line) => [$line->id => (float) $line->qty])->all();
                $this->transfers->receiveTransfer($transfer, [], ['_lines' => $confirmed], $actor);

                $request->items()->update(['qty_fulfilled' => $qty]);
                $request->update([
                    'status' => StockRequestStatus::FULFILLED,
                    'inventory_transfer_id' => $transfer->id,
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                ]);

                return $request->fresh();
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = StockRequest::query()->where('request_key', $requestKey)->first();
            if ($existing === null) {
                throw $exception;
            }

            return $this->matchingRequest($existing, $pusat, $cabang, $item, $qty, $actor, $notes);
        }
    }

    private function matchingRequest(StockRequest $existing, Pop $pusat, Pop $cabang, Item $item, float $qty, User $actor, ?string $notes): StockRequest
    {
        $line = $existing->items()->first();
        if ($existing->request_kind !== StockRequestKind::MARKETING
            || $existing->cabang_pop_id !== $cabang->id
            || $existing->inventoryTransfer?->from_pop_id !== $pusat->id
            || $existing->requested_by !== $actor->id
            || $existing->notes !== $notes
            || $existing->items()->count() !== 1
            || $line?->item_id !== $item->id
            || round((float) $line?->qty_requested, 2) !== round($qty, 2)) {
            throw new InvalidArgumentException('Kunci permintaan sudah dipakai untuk transaksi lain.');
        }

        return $existing;

    }
}
