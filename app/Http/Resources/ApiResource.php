<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base Resource untuk API masuk berbentuk REST (docs/api/api-portal-pelanggan/,
 * Fase 0). Envelope `{data, meta}` didapat gratis lewat `with()` — Laravel
 * menggabungkan array ini ke level atas response di samping `data` hasil
 * `toArray()`, tanpa perlu override `toArray()`/`wrap` manual.
 *
 * Resource anak (Fase 2-4: InvoiceResource, PaymentResource, dst.) tinggal
 * `extends ApiResource` dan panggil `$this->money(...)` untuk tiap kolom
 * nominal — JANGAN format desimal sendiri di `toArray()`, itu jalur yang
 * sama dengan galat pembulatan yang `Money` coba hindari (lihat docblock
 * kelas itu).
 */
abstract class ApiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Serialisasi nominal SELALU string desimal ("150000.00"), tidak pernah
     * float JSON — daftar putih kolom di `docs/api/api-portal-pelanggan/business-logic.md`
     * §2 mewajibkan ini untuk semua nominal (invoice, payment, overpay).
     */
    protected function money(mixed $rupiah): string
    {
        return Money::decimalString($rupiah);
    }
}
