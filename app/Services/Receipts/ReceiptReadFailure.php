<?php

namespace App\Services\Receipts;

use RuntimeException;

/**
 * Kegagalan TEKNIS saat membaca kwitansi — decoder meledak, API OCR mati,
 * berkas tak bisa dibuka.
 *
 * Sengaja dibedakan dari "nomor tidak ditemukan" (yang mengembalikan `null`):
 * yang ini layak dicoba ulang oleh queue, yang itu tidak akan pernah berubah
 * hasilnya berapa kali pun diulang.
 */
class ReceiptReadFailure extends RuntimeException {}
