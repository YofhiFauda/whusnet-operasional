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
        stream = null;
        videoEl.srcObject = null; // lepas referensi stream mati — biar video gak nampilin frame beku terakhir.
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

    return { start, stop, isScanning: () => scanning };
}

// Self-init — dipasang lewat @vite() di qr/scan.blade.php (satu-satunya
// halaman yang nge-load bundle ini).
//
// Dibungkus `mountQrScanner()` (ADHOC-297 P5): dengan `<body hx-boost="true">`
// module ini cuma DIEKSEKUSI SEKALI per tab, jadi pemasangan di top-level dulu
// bikin kunjungan ke-2 ke /qr/scan kamera gak pernah nyala. Sekarang dipanggil
// saat module dimuat DAN tiap `htmx:afterSettle`; video yang sudah terpasang
// ditandai `data-qr-mounted`.
let activeQrScanner = null;

function mountQrScanner() {
    const videoEl = document.getElementById('qr-scan-video');

    if (!videoEl || videoEl.dataset.qrMounted) {
        return;
    }
    videoEl.dataset.qrMounted = '1';

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
    activeQrScanner = { videoEl, scanner };

    // Izin kamera bisa baru keluar SETELAH staf pindah halaman — stream yang
    // terlanjur terbuka untuk halaman yang sudah ditinggal langsung dimatikan.
    const startGuarded = () => scanner.start().then(() => {
        if (!videoEl.isConnected) {
            scanner.stop();
        }
    });

    startGuarded().then(() => {
        if (scanner.isScanning()) {
            statusEl.textContent = 'Mengarahkan kamera…';
        }
    });

    // Tombol Stop/Nyalakan manual (diminta eksplisit 2026-11-10) — staf bisa
    // matikan kamera sendiri tanpa harus pindah/muat ulang halaman (privasi
    // & baterai), lalu nyalakan lagi kalau mau lanjut scan.
    const toggleBtn = document.getElementById('qr-scan-toggle-btn');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', async () => {
            if (scanner.isScanning()) {
                scanner.stop();
                statusEl.textContent = 'Kamera dimatikan.';
                toggleBtn.textContent = 'Nyalakan Kamera';

                return;
            }

            toggleBtn.disabled = true;
            statusEl.textContent = 'Meminta izin kamera…';
            await startGuarded();
            toggleBtn.disabled = false;

            if (scanner.isScanning()) {
                statusEl.textContent = 'Mengarahkan kamera…';
                toggleBtn.textContent = 'Matikan Kamera';
            }
        });
    }
}

/**
 * Kamera WAJIB berhenti begitu staf pindah halaman — jangan biarin nyala di
 * background (baterai + privasi). `pagehide` saja TIDAK cukup: navigasi
 * hx-boost gak memicunya, dulu kamera tetap merekam sesudah pindah menu.
 *
 * @param {boolean} onlyDetached true = cuma kalau videonya sudah lepas dari DOM.
 */
function stopQrScanner(onlyDetached) {
    if (!activeQrScanner || (onlyDetached && activeQrScanner.videoEl.isConnected)) {
        return;
    }

    activeQrScanner.scanner.stop();

    if (!activeQrScanner.videoEl.isConnected) {
        activeQrScanner = null;
    }
}

mountQrScanner();

document.addEventListener('htmx:beforeSwap', (event) => {
    if (event.detail.target === document.body) {
        stopQrScanner(false);
    }
});
document.addEventListener('htmx:afterSettle', () => {
    stopQrScanner(true);
    mountQrScanner();
});
window.addEventListener('pagehide', () => stopQrScanner(false));
