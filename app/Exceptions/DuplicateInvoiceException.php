<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Dilempar `InvoiceObserver::creating()` saat tagihan kembar (pelanggan,
 * jenis, periode, nominal sama) terbit dalam jendela anti double-submit.
 *
 * Turunan `InvalidArgumentException` supaya pemanggil lama yang menangkap
 * tipe itu tetap jalan. Kelas sendiri supaya caller bisa menerjemahkannya
 * jadi pesan validasi yang jelas (mis. Putus Langganan diulang dengan denda
 * sama), bukan halaman error 500.
 */
class DuplicateInvoiceException extends InvalidArgumentException {}
