<?php

namespace App\Services\Receipts;

/**
 * Ukuran fisik lembar kwitansi — NCR 2-ply blangko polos, continuous form
 * 1/2 part: **9,5 × 5,5 inci** (lebar × tinggi, dikonfirmasi user 2026-09-22
 * — persegi panjang landscape, ~16:9, separuh dari lembar 9,5"×11" utuh).
 *
 * SATU sumber angka ini dipakai dua tempat yang sebelumnya gampang menyimpang
 * kalau ditulis manual dua kali (koreksi 2026-09-26 — Portal sempat dikasih
 * A4, lalu sempat dikasih ukuran potret 5,5×9,5 yang kebalik dari fisik
 * aslinya): `payments/receipt.blade.php` (`@page` CSS, jalur cetak fisik
 * staf) dan `PortalPaymentController::receiptPdf()` (`->setPaper()`, dompdf).
 * PDF Portal SENGAJA disamakan persis dengan lembar fisik staf — PDF-nya
 * cuma dilihat/diunduh (tak pernah dicetak ke printer NCR kantor), jadi
 * ukurannya bebas ditentukan, dan dipilih sama biar satu tempat saja yang
 * perlu diubah kalau ukuran kertas berubah lagi.
 */
final class ReceiptPaperSize
{
    public const WIDTH_IN = 9.5;

    public const HEIGHT_IN = 5.5;

    public static function widthPt(): float
    {
        return self::WIDTH_IN * 72;
    }

    public static function heightPt(): float
    {
        return self::HEIGHT_IN * 72;
    }
}
