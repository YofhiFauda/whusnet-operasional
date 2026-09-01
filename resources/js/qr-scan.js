import jsQR from 'jsqr';

/**
 * Scan QR Internal (staf) — docs/plan/qr-code/rancangan-qr-pelanggan-final.md
 * §5, koreksi 2026-08-27: staf WAJIB scan dari DALAM app ini (halaman ini),
 * bukan app kamera/scanner pihak ketiga. Alasannya bukan preferensi UI —
 * `QrScanController::dispatch()` membedakan tamu/staf lewat `auth()->check()`
 * (cookie sesi), dan app scanner eksternal buka link di WebView/browser
 * TERPISAH yang TIDAK bawa cookie sesi Operasional. Hasilnya: staf yang scan
 * lewat app luar salah kebaca sebagai TAMU. Halaman ini hidup di DALAM
 * tab/browser yang sudah pasti login duluan (syarat masuk sini), jadi
 * navigasi ke URL hasil scan otomatis bawa cookie yang benar — TANPA perlu
 * duplikasi logic dispatch/resolve apa pun di sini. Satu-satunya tugas file
 * ini: baca kamera secepat mungkin, begitu ketemu QR valid, pindah halaman.
 *
 * Optimasi kecepatan baca (diminta eksplisit):
 * 1. `BarcodeDetector` API native browser DICOBA DULU (Chrome/Edge/Android
 *    WebView) — decode di-hardware-accelerate, jauh lebih cepat dari JS
 *    murni. `jsQR` (JS murni) cuma FALLBACK buat browser yang gak dukung
 *    (Safari/iOS lama).
 * 2. Frame video di-downscale ke canvas KECIL (max 480px) sebelum decode —
 *    jsQR itu O(pixel), gambar kecil = decode jauh lebih cepat, resolusi
 *    segitu masih lebih dari cukup buat baca QR dari jarak wajar.
 * 3. Loop dibatasi ~10 scan/detik (bukan tiap frame kamera/60fps) — cukup
 *    buat kerasa instan ke mata manusia, tapi gak bakar CPU/baterai HP
 *    percuma.
 * 4. Kamera + loop STOP LANGSUNG begitu satu QR valid kebaca — gak nunggu
 *    apa pun, biar transisi ke halaman berikutnya berasa instan.
 */

const SCAN_INTERVAL_MS = 100; // ~10 scan/detik — lihat poin 3 di atas.
const DOWNSCALE_MAX_WIDTH = 480; // lihat poin 2 di atas.

// Path QR pelanggan (docs/plan/qr-code/) — SATU-SATUNYA bentuk yang boleh
// dinavigasi otomatis dari sini. QR APA PUN LAIN (barcode toko, link acak)
// yang kebaca kamera ditolak — halaman ini bukan scanner umum.
const QR_PATH_PATTERN = /\/q1\/[A-Z2-7]{26}\.[A-Z2-7]{10}$/;

function initQrScan({ videoEl, canvasEl, statusEl, onError }) {
    const ctx = canvasEl.getContext('2d', { willReadFrequently: true });
    let stream = null;
    let scanning = false;
    let lastScanAt = 0;
    let rafId = null;

    const detector = 'BarcodeDetector' in window
        ? new window.BarcodeDetector({ formats: ['qr_code'] })
        : null;

    async function decodeFrame() {
        if (detector) {
            try {
                const codes = await detector.detect(videoEl);

                return codes[0]?.rawValue ?? null;
            } catch {
                return null; // frame gagal decode itu normal (kamera masih fokus dst), bukan error.
            }
        }

        // Fallback jsQR — downscale dulu (poin 2), baru decode.
        const scale = Math.min(1, DOWNSCALE_MAX_WIDTH / videoEl.videoWidth);
        canvasEl.width = videoEl.videoWidth * scale;
        canvasEl.height = videoEl.videoHeight * scale;
        ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);

        const frame = ctx.getImageData(0, 0, canvasEl.width, canvasEl.height);
        const result = jsQR(frame.data, frame.width, frame.height, { inversionAttempts: 'dontInvert' });

        return result?.data ?? null;
    }

    function stop() {
        scanning = false;
        if (rafId) {
            cancelAnimationFrame(rafId);
        }
        stream?.getTracks().forEach((track) => track.stop());
    }

    async function loop(timestamp) {
        if (!scanning) {
            return;
        }

        if (timestamp - lastScanAt >= SCAN_INTERVAL_MS) {
            lastScanAt = timestamp;

            const raw = await decodeFrame();

            if (raw) {
                handleResult(raw);

                return; // stop() sudah dipanggil di handleResult, gak lanjut loop.
            }
        }

        rafId = requestAnimationFrame(loop);
    }

    function handleResult(raw) {
        let url;
        try {
            url = new URL(raw, window.location.origin);
        } catch {
            statusEl.textContent = 'QR terbaca tapi bukan tautan valid — coba lagi.';

            return; // loop tetap jalan, biar user bisa coba scan ulang.
        }

        // Anti-salah-arah: cuma navigasi kalau ORIGIN SAMA (bukan QR nyasar
        // ke domain lain) DAN path-nya persis pola /q1/{token}.{sig}
        // (bukan QR toko/produk lain yang kebetulan kekamera).
        if (url.origin !== window.location.origin || ! QR_PATH_PATTERN.test(url.pathname)) {
            statusEl.textContent = 'Ini bukan QR pelanggan Whusnet — arahkan ke QR yang benar.';

            return;
        }

        stop();
        statusEl.textContent = 'QR ditemukan, memproses…';
        window.location.href = url.href;
    }

    async function start() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
            });
        } catch (err) {
            onError(err);

            return;
        }

        videoEl.srcObject = stream;
        await videoEl.play();

        scanning = true;
        lastScanAt = 0;
        rafId = requestAnimationFrame(loop);
    }

    return { start, stop };
}

// Self-init — dipasang lewat @vite() di qr/scan.blade.php (satu-satunya
// halaman yang nge-load bundle ini). Elemen wajib ada di DOM sebelum ini
// jalan; `defer` bawaan Vite module script udah nunggu DOMContentLoaded,
// jadi gak perlu bungkus listener DOMContentLoaded lagi di sini.
const videoEl = document.getElementById('qr-scan-video');

if (videoEl) {
    const statusEl = document.getElementById('qr-scan-status');
    const scanner = initQrScan({
        videoEl,
        canvasEl: document.getElementById('qr-scan-canvas'),
        statusEl,
        onError(err) {
            // NotAllowedError (izin ditolak) paling sering — pesan spesifik
            // biar staf tau harus ngapain, bukan cuma "gagal".
            statusEl.textContent = err?.name === 'NotAllowedError'
                ? 'Izin kamera ditolak — aktifkan lewat pengaturan browser, lalu muat ulang halaman.'
                : 'Kamera gak bisa diakses. Coba muat ulang halaman.';
        },
    });

    scanner.start().then(() => {
        statusEl.textContent = 'Mengarahkan kamera…';
    });

    // Kamera WAJIB berhenti begitu staf pindah halaman — jangan biarin
    // nyala di background (baterai + privasi, kamera ngerekam terus).
    window.addEventListener('pagehide', () => scanner.stop());
}
