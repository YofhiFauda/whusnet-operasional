<?php

namespace App\Observers;

use App\Models\CustomerBalanceMutation;
use LogicException;

/**
 * Menegakkan append-only di `customer_balance_mutations` — ledger ini
 * SATU-SATUNYA sumber saldo pelanggan (G8, docs/plan/billing/
 * analisa-rancangan-saldo-pelanggan.md). Salah catat dilawan baris pembalik
 * baru (lihat CustomerBalanceService::reverseCreditForPayment()), bukan
 * edit/hapus baris lama — sama pola InventoryTransactionObserver.
 *
 * BATASAN yang sadar diakui: guard ini menangkap `->update()`/`->delete()`
 * Eloquent, TAPI TIDAK menangkap bulk update lewat query builder
 * (`CustomerBalanceMutation::where(...)->update()`) atau raw SQL.
 */
class CustomerBalanceMutationObserver
{
    public function updating(CustomerBalanceMutation $mutation): void
    {
        throw new LogicException(
            'customer_balance_mutations itu ledger append-only — baris yang sudah tercatat tidak boleh diubah. '
            .'Salah catat? Buat baris pembalik baru, jangan edit baris lama.'
        );
    }

    public function deleting(CustomerBalanceMutation $mutation): void
    {
        throw new LogicException(
            'customer_balance_mutations itu ledger append-only — baris yang sudah tercatat tidak boleh dihapus, '
            .'termasuk oleh owner.'
        );
    }
}
