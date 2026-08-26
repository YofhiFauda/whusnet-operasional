<?php

namespace App\Http\Resources\CustomerPortal;

use App\Http\Resources\ApiResource;
use App\Services\Receipts\ReceiptPresenter;
use Illuminate\Http\Request;

/**
 * `GET /me/payments/{payment_number}/receipt` (docs/api/api-portal-pelanggan/
 * business-logic.md §3 Bagian A). Kwitansi TIDAK dibangun ulang — ambil dari
 * `ReceiptPresenter::for()` yang sama dipakai cetak internal (thermal/A4/
 * kartu kolektor), lalu dipangkas. Tanpa pemangkasan ini, satu endpoint
 * kwitansi membatalkan daftar putih `PaymentResource` di sebelahnya.
 *
 * `penerima`/`penagih` = nama pegawai, `catatan` = `payments.note` internal,
 * `dicetak` = timestamp cetak internal (dibuat now() tiap panggilan — tidak
 * stabil buat API, tidak relevan buat portal). Keempatnya DIBUANG.
 *
 * `status_valid` dan `keterangan_cicilan` WAJIB dipertahankan (presenter
 * sengaja punya dua kunci itu supaya kwitansi pembayaran ditolak/sebagian
 * tidak terbaca sebagai pelunasan penuh — buang keduanya sama bahayanya
 * dengan tidak memangkas data pegawai).
 */
class PaymentReceiptResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = app(ReceiptPresenter::class)->for($this->resource);

        unset($data['penerima'], $data['penagih'], $data['catatan'], $data['dicetak']);

        $data['dibayar_raw'] = $this->money($this->resource->amount);
        $data['tanggal_bayar_iso'] = $this->resource->payment_date?->toIso8601String();

        return $data;
    }
}
