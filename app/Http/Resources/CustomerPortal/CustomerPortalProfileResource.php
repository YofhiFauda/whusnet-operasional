<?php

namespace App\Http\Resources\CustomerPortal;

use App\Http\Resources\ApiResource;
use App\Models\Customer;
use Illuminate\Http\Request;

/**
 * @mixin Customer
 *
 * `GET /me` (docs/api/api-portal-pelanggan/, Fase 2) — "profil ringkas,
 * status layanan, paket aktif". Dokumen tidak eksplisit daftar kolomnya
 * (beda dari Invoice/Payment yang eksplisit) — whitelist ini dikonfirmasi
 * pemilik produk 2026-08-24: alamat GENERIC (desa/kecamatan), bukan alamat
 * detail/koordinat — profil ringkas beneran ringkas, kurangi permukaan data
 * kalau token bocor.
 */
class CustomerPortalProfileResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'login_id' => $this->portalAccount?->login_id,
            'full_name' => $this->full_name,
            'status' => $this->status,
            'package' => $this->internetPackage?->name,
            'village' => $this->village?->name,
            'district' => $this->district?->name,
            'claimed_at' => $this->portalAccount?->claimed_at?->toIso8601String(),
        ];
    }
}
