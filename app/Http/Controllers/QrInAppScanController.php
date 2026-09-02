<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Scan QR Internal — halaman staf (2026-08-27, keputusan eksplisit user).
 *
 * Kenapa ada, padahal `QrScanController::dispatch()` udah bisa dibuka
 * lewat app scanner/kamera apa pun: cabang STAF di dispatch() ditentukan
 * `auth()->check()` (cookie sesi) — app scanner/kamera pihak ketiga buka
 * link di WebView/browser TERPISAH yang TIDAK bawa cookie sesi Operasional,
 * jadi staf yang scan lewat app luar salah kebaca sebagai TAMU. Halaman
 * ini WAJIB dibuka dari DALAM app (staf udah login duluan buat sampai ke
 * sini) — begitu kamera nemu QR pelanggan valid, JS (resources/js/qr-scan.js)
 * navigasi browser LANGSUNG ke `/q1/{code}` di TAB YANG SAMA, otomatis bawa
 * cookie yang benar. Nol logic dispatch/resolve diduplikasi di sini — cuma
 * pintu masuk yang identitasnya terjamin.
 *
 * Kamera pelanggan (gerbang tagihan/klaim Portal) TIDAK lewat sini —
 * itu tetap boleh app apa pun, gak butuh identitas apa pun (lihat komentar
 * QrScanController).
 */
class QrInAppScanController extends Controller
{
    public function show(): View
    {
        return view('qr.scan');
    }
}
