<?php

namespace App\Enums;

/**
 * Arah satu baris ledger [[CustomerBalanceMutation]]. `amount` di baris itu
 * selalu disimpan positif — arahnya ditentukan kolom ini, bukan tanda minus,
 * supaya `SUM(amount)` per tipe tidak pernah ambigu.
 */
enum CustomerBalanceMutationType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';
}
