<?php

namespace App\Exceptions;

use DomainException;

/**
 * Dilempar CustomerObserver::updating() saat pop_id pelanggan diganti padahal
 * masih ada tagihan yang wajib lunas dulu (ADHOC-107 R4, keputusan user
 * 2026-09-28). Lapis kedua di belakang validasi Edit Pelanggan — menutup jalur
 * import/tinker/command yang tidak lewat form. Pola yang sama dengan
 * PaymentObserver::creating() yang menolak nominal ≤ 0 dari semua jalur.
 *
 * Kelas sendiri supaya pemanggil bisa menerjemahkannya jadi pesan validasi,
 * bukan halaman error 500.
 */
class CustomerRelocationBlockedException extends DomainException {}
