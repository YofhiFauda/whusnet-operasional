<?php

namespace App\Http\Resources\CustomerPortal;

use App\Enums\CustomerBalanceMutationType;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * Item riwayat mutasi saldo pelanggan, dipakai `GET /me/balance`
 * (docs/api/api-portal-pelanggan/, Fase 3 — dikonfirmasi user 2026-08-25:
 * saldo + riwayat, bukan angka doang).
 *
 * `pop_id`/`created_by` (nama staf) HARAM — sama semangat dengan
 * `received_by`/`collected_by` di Payment. `note` AMAN masuk apa adanya —
 * diverifikasi mandiri (2026-08-25): SEMUA caller nyata
 * `CustomerBalanceService::credit()`/`debit()` di repo tidak pernah
 * mengirim `$note` custom, selalu default auto-generated
 * ("Lebih bayar dari {payment_number}") yang tidak pernah menyebut nama
 * pegawai.
 *
 * `CustomerBalanceMutationType` TIDAK punya method `label()` — mapping
 * dibuat inline di sini, SENGAJA tidak menambah method ke enum (scope
 * minimal, tidak ada modul lain yang butuh label ini).
 */
class CustomerBalanceMutationResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->created_at?->toIso8601String(),
            'type' => $this->type,
            'type_label' => $this->type === CustomerBalanceMutationType::CREDIT->value ? 'Masuk' : 'Keluar',
            // Kolom `amount` di DB SELALU positif — arah ditentukan `type`,
            // bukan tanda minus (database-schema.md, migrasi
            // customer_balance_mutations). Dipertahankan positif di sini
            // juga, konsisten sumbernya.
            'amount' => $this->money($this->amount),
            'note' => $this->note,
        ];
    }
}
