<?php

namespace App\Observers;

use App\Models\InventoryTransaction;
use LogicException;

/**
 * Menegakkan append-only di `inventory_transactions` — ledger ini SATU-SATUNYA
 * sumber histori inventory (§25 warehouse_inventory_asset_traceability_analysis.md).
 * Baris yang sudah tercatat tidak boleh diubah/dihapus siapa pun, TERMASUK
 * owner — salah catat dilawan baris koreksi baru (`ADJUSTMENT`), bukan edit
 * baris lama (§6 docs/plan/warehouse/kontrol-anti-manipulasi.md).
 *
 * BATASAN yang sadar diakui, bukan disembunyikan: guard ini menangkap
 * `$transaction->update()`/`->delete()` (jalur normal Eloquent lewat Service),
 * TAPI TIDAK menangkap bulk update lewat query builder
 * (`InventoryTransaction::where(...)->update()`) atau raw SQL
 * (`DB::table('inventory_transactions')->update()`) — Eloquent events tidak
 * pernah terpicu buat dua jalur itu. Sama persis batasan `PaymentObserver`
 * (guard di `creating()`, bukan constraint DB). Jangan pernah menulis Service
 * yang manggil `InventoryTransaction::query()->update(...)` — kalau ada
 * kebutuhan begitu, itu tanda arsitekturnya salah, bukan alasan buat nulis
 * query builder buat "lewatin" guard ini.
 */
class InventoryTransactionObserver
{
    public function updating(InventoryTransaction $transaction): void
    {
        throw new LogicException(
            'inventory_transactions itu ledger append-only — baris yang sudah tercatat tidak boleh diubah. '
            .'Salah catat? Buat baris ADJUSTMENT baru yang mengoreksi, jangan edit baris lama.'
        );
    }

    public function deleting(InventoryTransaction $transaction): void
    {
        throw new LogicException(
            'inventory_transactions itu ledger append-only — baris yang sudah tercatat tidak boleh dihapus, '
            .'termasuk oleh owner. Ini berlaku permanen, bukan cuma sampai baris ini diverifikasi benar.'
        );
    }
}
