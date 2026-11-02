<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Models\BranchInventorySaleLine;
use App\Models\InventoryTransaction;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Laporan jual beli antar Cabang per SN (Task 5 rencana ADHOC-217).
 *
 * Satu baris = satu SN dalam satu dokumen. Status fisik DITURUNKAN dari ledger,
 * bukan disimpan: SN `diterima` bila ada baris TRANSFER ke Cabang pembeli,
 * selain itu masih `dalam_perjalanan` (tidak ada jalur "hilang": SN yang belum
 * tiba menunggu penerimaan). Laporan sengaja TIDAK memuat status bayar —
 * pembayaran dicocokkan manual di luar aplikasi dengan nominal "diterima" di sini.
 */
class BranchInventorySaleReportService
{
    public const STATUS_RECEIVED = 'diterima';

    public const STATUS_IN_TRANSIT = 'dalam_perjalanan';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_RECEIVED => 'Diterima',
        self::STATUS_IN_TRANSIT => 'Dalam perjalanan',
    ];

    /**
     * @param  array{from_pop_id?:?int, to_pop_id?:?int, date_from?:?string, date_to?:?string, status?:?string, q?:?string}  $filters
     * @param  list<int>|null  $allowedPopIds  null = akses semua POP. Dokumen terlihat bila Cabang
     *                                         penjual ATAU pembeli ada di scope (sama dengan index).
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(array $filters, ?array $allowedPopIds): Collection
    {
        $lines = BranchInventorySaleLine::query()
            ->with(['sale.fromPop', 'sale.toPop', 'sale.transfer', 'serial.item'])
            ->whereHas('sale', function ($sale) use ($filters, $allowedPopIds) {
                $sale->when($allowedPopIds !== null, fn ($q) => $q->where(
                    fn ($side) => $side->whereIn('from_pop_id', $allowedPopIds)->orWhereIn('to_pop_id', $allowedPopIds)
                ))
                    ->when(! empty($filters['from_pop_id']), fn ($q) => $q->where('from_pop_id', $filters['from_pop_id']))
                    ->when(! empty($filters['to_pop_id']), fn ($q) => $q->where('to_pop_id', $filters['to_pop_id']))
                    ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
                    ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']));
            })
            ->when(! empty($filters['q']), fn ($q) => $q->whereHas('serial', fn ($serial) => $serial->where(
                'serial_number', 'like', '%'.addcslashes((string) $filters['q'], '%_\\').'%'
            )))
            ->orderByDesc('branch_inventory_sale_id')->orderBy('inventory_serial_id')
            ->get();

        if ($lines->isEmpty()) {
            return collect();
        }

        $transferIds = $lines->pluck('sale.inventory_transfer_id')->unique()->all();
        $serialIds = $lines->pluck('inventory_serial_id')->unique()->all();

        // Dua kueri massal (bukan per baris): kiriman = baris ber-from_pop_id (membawa HPP
        // penjual), penerimaan = baris ber-to_pop_id.
        $ledger = InventoryTransaction::query()
            ->whereIn('inventory_transfer_id', $transferIds)
            ->whereIn('serial_id', $serialIds)
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->get(['inventory_transfer_id', 'serial_id', 'from_pop_id', 'to_pop_id', 'unit_price_snapshot']);
        $dispatched = $ledger->whereNotNull('from_pop_id')->keyBy(fn ($tx) => $tx->inventory_transfer_id.':'.$tx->serial_id);
        $received = $ledger->whereNotNull('to_pop_id')->keyBy(fn ($tx) => $tx->inventory_transfer_id.':'.$tx->serial_id);

        $rows = $lines->map(function (BranchInventorySaleLine $line) use ($dispatched, $received) {
            $sale = $line->sale;
            $key = $sale->inventory_transfer_id.':'.$line->inventory_serial_id;
            $status = $received->has($key) ? self::STATUS_RECEIVED : self::STATUS_IN_TRANSIT;

            return [
                'sale' => $sale,
                'reference_number' => $sale->reference_number,
                'created_at' => $sale->created_at,
                'from_pop' => $sale->fromPop,
                'to_pop' => $sale->toPop,
                'serial_number' => $line->serial->serial_number,
                'item_name' => $line->serial->item->name,
                'condition' => $line->serial->condition,
                'condition_checked' => $line->serial->condition_checked_at !== null,
                'status' => $status,
                'cost_price' => $dispatched->get($key)?->unit_price_snapshot,
                'sale_price' => $line->sale_price,
            ];
        });

        if (! empty($filters['status'])) {
            $rows = $rows->where('status', $filters['status']);
        }

        return $rows->values();
    }

    /**
     * Ringkasan nominal HANYA dari SN berstatus diterima — selaras dengan
     * `posted_total` dokumen. Bila user ber-scope, dipisah per arah supaya tidak
     * menjumlahkan penjualan dan pembelian menjadi angka yang tak mewakili siapa pun.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<int>|null  $allowedPopIds
     * @return array<string, array{units:int, cost:float, sale:float}>
     */
    public function summary(Collection $rows, ?array $allowedPopIds): array
    {
        $received = $rows->where('status', self::STATUS_RECEIVED);
        $make = fn (Collection $set) => [
            'units' => $set->count(),
            'cost' => Money::sum($set->pluck('cost_price')),
            'sale' => Money::sum($set->pluck('sale_price')),
        ];

        if ($allowedPopIds === null) {
            return ['Diterima pembeli' => $make($received)];
        }

        return [
            'Penjualan (Cabang Anda sebagai penjual)' => $make($received->filter(fn ($row) => in_array($row['from_pop']->id, $allowedPopIds, true))),
            'Pembelian (Cabang Anda sebagai pembeli)' => $make($received->filter(fn ($row) => in_array($row['to_pop']->id, $allowedPopIds, true))),
        ];
    }
}
