/**
 * Scan Barcode 1D (Linear) via kamera — dipakai tab "Scan Masuk" di
 * Lacak Barang/SN (Single Assign & Batch Assign per Kategori), lewat
 * `<x-warehouse.barcode-scanner>`. BEDA dari `qr-scan.js`:
 *
 * 1. BUKAN QR — barcode 1D (CODE128/EAN/UPC/dst) yang nempel di kemasan
 *    modem/ONT/router/perangkat aktif lain, bukan QR pelanggan.
 * 2. CONTINUOUS, bukan sekali baca lalu pindah halaman — satu sesi kamera
 *    dipakai buat scan BANYAK SN berturut-turut (terutama tab Batch),
 *    kamera baru berhenti kalau staf klik "Tutup Kamera" atau pindah
 *    halaman.
 * 3. BISA lebih dari satu blok scanner aktif di satu halaman (tab Single
 *    Assign & Batch Assign masing-masing punya bloknya sendiri) — makanya
 *    self-init di sini jalan per-elemen `[data-barcode-scanner]`, bukan
 *    satu id global kayak `qr-scan.js`.
 * 4. Nangkal kamera Ultra-Wide default di HP flagship — lihat blok
 *    "Pemilihan lensa" di bawah.
 * 5. DECODE DI-CROP KE JENDELA BINGKAI, bukan frame penuh (2026-09-04,
 *    laporan user) — lihat blok "Crop ke jendela bingkai" di bawah. Label
 *    modem real (mis. FiberHome HG6145F) numpuk 3-4 barcode linear +
 *    QR MAC dalam satu label sempit; decode full-frame gampang kepilih
 *    barcode SEBELAH yang gak dimaksud staf.
 *
 * Native `BarcodeDetector` API DOANG (Chrome/Edge/Android WebView) — TANPA
 * fallback library JS (beda dari jsQR punya `qr-scan.js`). Decode 1D di JS
 * murni berat & kurang akurat buat real-time, dan menambah dependency baru
 * cuma buat fallback butuh persetujuan tersendiri (CLAUDE.md — jangan ubah
 * dependency tanpa approval). Browser tanpa dukungan native (Safari/iOS,
 * sebagian Android lama) diarahkan pakai scanner fisik USB/Bluetooth atau
 * ketik manual — dua-duanya SUDAH jalan lewat kolom input teks biasa
 * (keyboard wedge), jadi degradasi ini bukan fitur hilang, cuma jalur
 * kamera yang gak tersedia di browser itu.
 */

const SCAN_INTERVAL_MS = 200; // sedikit lebih longgar dari qr-scan.js — 1D decode native tetap ringan, gak perlu se-agresif QR.
const SAME_CODE_COOLDOWN_MS = 2000; // cegah satu SN yang masih di depan kamera ke-dispatch berkali-kali tiap frame.
const STALL_HINT_MS = 6000; // belum kebaca sama sekali setelah sekian lama → kasih hint, bukan diem aja kayak macet.
const FULL_FRAME_FALLBACK_INTERVAL_MS = 400; // begitu macet, seberapa sering nyoba decode FULL FRAME (lebih berat, gak dijalanin tiap siklus).
const FULL_FRAME_MAX_WIDTH = 960; // downscale full-frame biar fallback ini gak sebanding beratnya sama decode jendela yang di-crop.
const CONTRAST_STRETCH_THRESHOLD = 140; // rentang gelap-terang di bawah ini (dari 255) dianggap "kontras lemah" — baru diapain, biar capture yang udah tajam gak dibebani proses percuma.

/*
 * ── Deteksi salah pilih Barang lewat prefix vendor SN (2026-09-04) ────
 * Kasus nyata dari user: kategori "Modem" + Barang "FiberHome" dipilih di
 * dropdown, tapi unit FISIK yang di-scan ternyata ZTE/Huawei — sistem
 * sebelum ini gak punya cara nolak/ngingetin, SN langsung nyangkut ke
 * Barang yang salah tanpa peringatan apa pun.
 *
 * SN ONT/GPON ikutin format standar ITU-T G.984: 4 KARAKTER PERTAMA =
 * kode vendor terdaftar (bukan acak) — mis. `FHTT9A231A38` prefix `FHTT`
 * itu emang beneran ID resmi FiberHome. Dipakai buat SOFT WARNING doang
 * (toast, gak nge-block submit): kalau prefix DIKENAL tapi nama Barang
 * yang lagi dipilih gak nyebut vendor itu, kasih tau staf buat cek ulang.
 *
 * SENGAJA gak nge-block keras — daftar prefix ini TIDAK LENGKAP (banyak
 * ONT/router/switch/SFP lain gak ikut konvensi ini sama sekali), false
 * positive dari daftar yang gak lengkap lebih berbahaya (staf jengkel,
 * akhirnya matiin fitur) daripada sesekali warning yang gak relevan.
 */
const SN_VENDOR_PREFIX_MAP = {
    ZTEG: 'ZTE',
    HWTC: 'Huawei',
    FHTT: 'FiberHome',
    FITL: 'FiberHome',
    ALCL: 'Nokia/Alcatel',
};

/**
 * Balikin nama vendor kalau prefix SN DIKENAL tapi gak disebut di nama
 * Barang yang lagi dipilih — `null` kalau prefix gak dikenal (gak bisa
 * nilai apa-apa, DIAM, jangan nuduh) ATAU udah cocok.
 */
function detectSnVendorMismatch(code, itemLabel) {
    const prefix = (code || '').trim().slice(0, 4).toUpperCase();
    const vendor = SN_VENDOR_PREFIX_MAP[prefix];

    if (!vendor) {
        return null;
    }

    const label = (itemLabel || '').toLowerCase();
    const vendorKeyword = vendor.toLowerCase().split('/')[0]; // "Nokia/Alcatel" → cek "nokia" doang cukup.

    return label.includes(vendorKeyword) ? null : vendor;
}

window.detectSnVendorMismatch = detectSnVendorMismatch;

// Format 1D yang didukung `BarcodeDetector` — SENGAJA gak masukin 'qr_code'/
// 'data_matrix'/dst, halaman ini murni buat barcode linear kemasan
// perangkat, bukan scanner umum.
const BARCODE_1D_FORMATS = [
    'code_128', 'code_39', 'code_93', 'codabar',
    'ean_13', 'ean_8', 'itf', 'upc_a', 'upc_e',
];

// Jendela bingkai — fraksi (0..1) dari kotak video, SATU-SATUNYA sumber
// kebenaran buat DUA hal sekaligus: (a) posisi mask/bracket visual di CSS,
// (b) area yang BENERAN di-crop buat decode. WYSIWYG — apa yang staf lihat
// di dalam bingkai itu PERSIS yang dibaca detector, gak ada beda diam-diam.
// Sempit di sumbu-Y (16% tinggi) SENGAJA — cukup buat nangkep SATU baris
// barcode linear, biar gak ikut kebaca barcode lain yang numpuk di atas/
// bawahnya di label yang sama.
// x0/x1 dilebarin 12%→4% margin (2026-09-04, laporan user: "harus
// bener-bener dipaskan lebarnya") — barcode 1D lebih sering KEPOTONG
// horizontal (lebih lebar dari layar HP dari jarak wajar) daripada
// vertikal, staf kepaksa presisi-in posisi cuma buat masuk ke jendela
// sempit. Y TETAP sempit (16%) — itu yang isolasi dari barcode LAIN yang
// numpuk di atas/bawahnya di label yang sama (§ komentar di atas), gak
// ada hubungannya sama lebar, jadi aman dilebarin horizontal tanpa
// balik ke masalah salah baca barcode tetangga.
const WINDOW_FRACTION = { x0: 0.04, x1: 0.96, y0: 0.42, y1: 0.58 };

/*
 * ── Pemilihan lensa (Ultra-Wide default bug) ──────────────────────────
 * Masalah: HP flagship modern (multi-lensa belakang: ultrawide/wide/tele)
 * SERING kasih track kamera "belakang" default ke lensa Ultra-Wide begitu
 * scan `getUserMedia({facingMode:'environment'})` — bukan bug Laravel/app
 * ini, itu perilaku Android Camera2/browser vendor (Chrome, Samsung
 * Internet) milih lensa index pertama yang match `facing=back`, dan di
 * banyak flagship itu justru si ultrawide, BUKAN lensa utama. Efeknya:
 * barcode kelihatan kecil-jauh & susah fokus dari jarak wajar.
 *
 * Web punya DUA sinyal buat ngakalin ini, dua-duanya HEURISTIC (gak ada
 * standar W3C buat "pilih lensa utama" secara eksplisit):
 *
 * 1. `enumerateDevices()` label — device LABEL cuma keisi SETELAH izin
 *    kamera diberikan (privasi browser). Sebagian vendor nyantumin kata
 *    "ultra wide"/"wide angle" di label fisik lensa (`guessMainDeviceId`
 *    di bawah nyaring itu), tapi TIDAK semua browser/vendor konsisten.
 * 2. `zoom` capability — kalau kamera belakangnya "combined multi-lens"
 *    (satu track logis nyakup ultrawide→tele via zoom kontinu, makin umum
 *    di flagship terbaru), `zoom` minimum di bawah 1 nandain ultrawide
 *    ikut ke-cover di rentang itu — `nudgeAwayFromUltraWide` naikin
 *    zoom-nya dikit ke arah lensa utama.
 *
 * Karena dua-duanya heuristic (gak dijamin akurat semua HP), tombol
 * "Ganti Lensa" (`switchCamera`) TETAP disediakan sebagai jalan keluar
 * manual kalau tebakannya salah — satu-satunya cara yang PASTI benar
 * adalah staf lihat sendiri preview-nya lalu ganti kalau perlu.
 */
function guessMainDeviceId(devices) {
    const rear = devices.filter((d) => d.kind === 'videoinput');
    const nonUltraWide = rear.filter((d) => !/ultra[- ]?wide|wide[- ]?angle|0\.5x/i.test(d.label));

    // Label kosong (browser gak expose nama lensa) → gak ada dasar milih,
    // biarin browser yang mutusin (fallback facingMode di `openStream`).
    const candidates = nonUltraWide.length > 0 ? nonUltraWide : rear;
    const labeledBack = candidates.find((d) => /back|rear|environment/i.test(d.label));

    return {
        rear,
        deviceId: labeledBack?.deviceId ?? candidates[0]?.deviceId ?? null,
    };
}

async function nudgeAwayFromUltraWide(track) {
    try {
        const caps = track.getCapabilities?.();

        if (caps?.zoom && typeof caps.zoom.min === 'number' && caps.zoom.min < 1) {
            const target = Math.min(caps.zoom.max ?? 1, Math.max(1, caps.zoom.min * 2));
            await track.applyConstraints({ advanced: [{ zoom: target }] });
        }
    } catch {
        // Capability zoom manual gak didukung semua browser — gagal diam-diam, bukan fatal.
    }
}

/*
 * ── Blur pas scan dari jarak dekat (2026-09-04, laporan user) ──────────
 * Banyak kamera Android (khususnya lewat `getUserMedia`, beda dari app
 * kamera bawaan) BUKAN autofocus terus-menerus by default — sekali fokus
 * dapet pas kamera baru nyala (biasanya buat objek JAUH), lalu diem di situ
 * walau staf dekatin HP ke barcode buat baca detail. Barcode 1D di label
 * kemasan modem itu KECIL, wajar staf mepetin HP biar kebaca jelas — kalau
 * fokusnya gak ikut gerak, hasilnya blur PERSIS pas paling deket (paling
 * sering staf lakuin).
 *
 * `focusMode: 'continuous'` itu Media Capture/Image Capture API standar
 * (bukan cuma Chrome doang, tapi dukungannya emang gak merata) — diminta
 * lewat `applyConstraints` TERPISAH dari zoom di atas (bukan digabung satu
 * objek `advanced`): browser boleh nolak SATU ENTRY `advanced` utuh kalau
 * ADA SATU property di dalamnya yang gak dikenal, jadi digabung berisiko
 * zoom ikut gagal gara-gara focusMode gak didukung device itu (atau
 * sebaliknya) — dipisah, gagalnya satu gak nyeret yang lain.
 */
async function enableContinuousAutofocus(track) {
    try {
        const caps = track.getCapabilities?.();

        if (Array.isArray(caps?.focusMode) && caps.focusMode.includes('continuous')) {
            await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
        }
    } catch {
        // Gak semua browser/device expose capability ini — gagal diam-diam, bukan fatal.
    }
}

/*
 * ── Dukungan format di Android (laporan Android 10 gak auto-assign) ────
 * `BarcodeDetector` konstruktor MELEMPAR (`NotSupportedError`) kalau salah
 * satu format di `formats` gak didukung implementasi device-nya — versi
 * SEBELUMNYA bikin `new window.BarcodeDetector({formats: BARCODE_1D_FORMATS})`
 * TANPA try/catch, jadi kalau satu Android/Chrome version gak kenal mis.
 * 'codabar', konstruktor throw, exception itu GAK KETANGKEP dan ngebubarin
 * seluruh `forEach` self-init di bawah — blok scanner setelahnya (kalau
 * ada) ikut gak ke-init sama sekali, TANPA pesan error apa pun ke staf.
 * Diperbaiki: filter dulu ke format yang BENERAN didukung device (lewat
 * `getSupportedFormats()`, kalau method itu sendiri ada), baru konstruksi,
 * semuanya dibungkus try/catch — gagal paling parah jatuh ke fallback
 * "browser gak dukung", bukan diem-diem mati.
 */
async function createDetector() {
    if (!('BarcodeDetector' in window)) {
        return null;
    }

    try {
        const supported = (await window.BarcodeDetector.getSupportedFormats?.()) ?? BARCODE_1D_FORMATS;
        const formats = BARCODE_1D_FORMATS.filter((f) => supported.includes(f));

        if (formats.length === 0) {
            return null;
        }

        return new window.BarcodeDetector({ formats });
    } catch {
        return null;
    }
}

/*
 * ── Crop ke jendela bingkai ─────────────────────────────────────────
 * `<video>` ditampilkan `object-cover` (native resolution biasanya BEDA
 * rasio dari kotak tampilan, jadi sebagian frame native KEPOTONG biar
 * ngisi penuh kotak tanpa gepeng) — buat nemuin pixel native yang PERSIS
 * ketutup jendela bingkai di layar, harus dibalik dulu matematika
 * object-cover-nya (skala + offset crop), bukan asumsi 1:1.
 */
function computeCropRect(video, frame) {
    const cw = frame.clientWidth;
    const ch = frame.clientHeight;
    const vw = video.videoWidth;
    const vh = video.videoHeight;

    if (!cw || !ch || !vw || !vh) {
        return null;
    }

    const scale = Math.max(cw / vw, ch / vh);
    const offsetX = (vw * scale - cw) / 2;
    const offsetY = (vh * scale - ch) / 2;

    const toVideoPx = (fx, fy) => [
        (fx * cw + offsetX) / scale,
        (fy * ch + offsetY) / scale,
    ];

    const [sx0, sy0] = toVideoPx(WINDOW_FRACTION.x0, WINDOW_FRACTION.y0);
    const [sx1, sy1] = toVideoPx(WINDOW_FRACTION.x1, WINDOW_FRACTION.y1);

    return { sx: sx0, sy: sy0, sw: sx1 - sx0, sh: sy1 - sy0 };
}

// Posisi mask gelap + bingkai jendela — MURNI CSS pixel di kotak tampilan
// (beda dari `computeCropRect` yang kerja di pixel native video), tapi
// pakai fraksi yang SAMA (`WINDOW_FRACTION`) biar area gelap di luar
// bingkai itu SECARA VISUAL persis nunjukin area yang beneran di-crop.
function layoutMask(refs) {
    const cw = refs.frame.clientWidth;
    const ch = refs.frame.clientHeight;

    if (!cw || !ch) {
        return;
    }

    const x0 = WINDOW_FRACTION.x0 * cw;
    const x1 = WINDOW_FRACTION.x1 * cw;
    const y0 = WINDOW_FRACTION.y0 * ch;
    const y1 = WINDOW_FRACTION.y1 * ch;

    refs.maskTop.style.height = `${y0}px`;
    refs.maskBottom.style.height = `${ch - y1}px`;

    Object.assign(refs.maskLeft.style, { top: `${y0}px`, left: '0px', width: `${x0}px`, height: `${y1 - y0}px` });
    Object.assign(refs.maskRight.style, { top: `${y0}px`, right: '0px', width: `${cw - x1}px`, height: `${y1 - y0}px` });
    Object.assign(refs.windowEl.style, { left: `${x0}px`, top: `${y0}px`, width: `${x1 - x0}px`, height: `${y1 - y0}px` });
}

/*
 * ── Peregangan kontras (2026-09-04, laporan user) ──────────────────────
 * Label yang nempel di permukaan LENGKUNG/glossy (mis. cekungan oval ONT
 * XPON) sering kena silau/pantulan cahaya gak merata — sebagian bar hitam
 * ke-baca abu-abu terang oleh sensor kamera, kontrasnya "pudar" dibanding
 * label FLAT yang kena cahaya rata (contoh FiberHome). Detector native
 * (`BarcodeDetector`) ngebinarize sendiri di dalam, tapi kalau rentang
 * gelap-terang aslinya udah sempit duluan, hasil binarize-nya gampang
 * salah putus bar.
 *
 * Peregangan histogram sederhana: cari nilai paling gelap & paling terang
 * di crop, tarik ke rentang penuh 0-255. MURAH (satu pass tambahan di
 * canvas yang UDAH kecil, cuma jendela bingkai/downscale full-frame, bukan
 * video mentah) dan CUMA jalan kalau rentang aslinya emang sempit
 * (`CONTRAST_STRETCH_THRESHOLD`) — capture yang udah tajam (kasus normal)
 * dilewatin gitu aja, gak dibebanin proses yang gak perlu.
 */
function maybeStretchContrast(ctx, width, height) {
    if (width <= 0 || height <= 0) {
        return;
    }

    const imageData = ctx.getImageData(0, 0, width, height);
    const data = imageData.data;

    let min = 255;
    let max = 0;

    // Sampling tiap 4 pixel (bukan tiap 1) buat nyari rentang — cukup
    // akurat buat estimasi, jauh lebih murah daripada baca semua pixel.
    for (let i = 0; i < data.length; i += 16) {
        const luminance = (data[i] * 299 + data[i + 1] * 587 + data[i + 2] * 114) / 1000;

        if (luminance < min) min = luminance;
        if (luminance > max) max = luminance;
    }

    const range = max - min;

    if (range >= CONTRAST_STRETCH_THRESHOLD || range < 5) {
        return; // udah tajam (gak perlu), ATAU nyaris rata semua (flat gelap/flat terang) — stretch di sini cuma nambah noise.
    }

    const scale = 255 / range;

    for (let i = 0; i < data.length; i += 4) {
        data[i] = Math.min(255, Math.max(0, (data[i] - min) * scale));
        data[i + 1] = Math.min(255, Math.max(0, (data[i + 1] - min) * scale));
        data[i + 2] = Math.min(255, Math.max(0, (data[i + 2] - min) * scale));
    }

    ctx.putImageData(imageData, 0, 0);
}

function initBarcodeScan({ videoEl, frameEl, statusEl, maskRefs, target, onError, onCameraCountChange }) {
    let detector = null;
    let stream = null;
    let scanning = false;
    let lastScanAt = 0;
    let rafId = null;
    let lastCode = null;
    let lastCodeAt = 0;
    let rearCameras = [];
    let currentDeviceIndex = -1;
    let cropRect = null;
    let stallTimer = null;
    let stalled = false; // di-set true begitu hint macet kepicu — lihat armStallHint()/decodeFullFrameFallback().
    let lastFullFrameAttemptAt = 0;

    // `willReadFrequently` — kita manggil `getImageData` tiap siklus scan
    // (buat `maybeStretchContrast`), bukan sekali-sekali; tanpa hint ini
    // sebagian browser milih backend canvas yang lambat buat baca balik.
    const cropCanvas = document.createElement('canvas');
    const cropCtx = cropCanvas.getContext('2d', { willReadFrequently: true });
    const fullFrameCanvas = document.createElement('canvas');
    const fullFrameCtx = fullFrameCanvas.getContext('2d', { willReadFrequently: true });

    function refreshLayout() {
        layoutMask(maskRefs);
        cropRect = computeCropRect(videoEl, frameEl);
    }

    function armStallHint() {
        clearTimeout(stallTimer);
        stalled = false;
        stallTimer = setTimeout(() => {
            // Begitu macet lama, ikutan coba decode FULL FRAME juga (lihat
            // `decodeFullFrame()`+`loop()`) — kalau jendela bingkai yang
            // presisi ternyata gak pernah pas ketemu posisi barcode-nya
            // (label melengkung/dua baris deket/dst, laporan user
            // 2026-09-04 "udah didekat-jauhin tapi masih gagal"), full
            // frame gak punya batasan posisi sama sekali.
            stalled = true;
            statusEl.textContent = 'Belum kebaca — dekatkan/jauhkan kamera sampai satu baris barcode pas di dalam bingkai, atau ketik manual kalau tetap gagal.';
        }, STALL_HINT_MS);
    }

    async function decodeFrame() {
        if (!detector || !cropRect || cropRect.sw <= 0 || cropRect.sh <= 0) {
            return null;
        }

        cropCanvas.width = Math.max(1, Math.round(cropRect.sw));
        cropCanvas.height = Math.max(1, Math.round(cropRect.sh));
        cropCtx.drawImage(videoEl, cropRect.sx, cropRect.sy, cropRect.sw, cropRect.sh, 0, 0, cropCanvas.width, cropCanvas.height);

        // Contrast-stretch DICABUT dari jalur ini (2026-09-04, laporan
        // user: "malah makin susah" tepat setelah ini ditambah) —
        // `getImageData`/`putImageData` maksa GPU→CPU readback SINKRON
        // tiap ~150ms, dan sekarang crop-nya lebih gede pixel-nya (efek
        // resolusi 1920×1080 di bawah) — dua-duanya numpuk bikin main
        // thread keteteran, preview lag, decode malah JARANG kena bukan
        // makin sering. Contrast-stretch TETAP ada, tapi cuma jalan di
        // `decodeFullFrame()` yang emang udah di-gate di belakang stall
        // 6 detik + interval 400ms sendiri — gak numpang di jalur cepat.
        try {
            const codes = await detector.detect(cropCanvas);
            const best = codes.find(c => /^[A-Z0-9]{8,}$/.test(c.rawValue)) ?? codes[0];
            return best?.rawValue ?? null;
        } catch { return null; }
    }

    // Fallback begitu macet (lihat `armStallHint`) — decode SELURUH frame
    // video, bukan cuma jendela bingkai. Didownscale (`FULL_FRAME_MAX_WIDTH`)
    // biar tetap ringan walau capture-nya udah 1920×1080. Dipanggil pada
    // interval SENDIRI (`FULL_FRAME_FALLBACK_INTERVAL_MS`), lebih jarang
    // dari decode jendela biasa — cuma nyala pas beneran struggling.
    async function decodeFullFrame() {
        if (!detector || !videoEl.videoWidth) {
            return null;
        }

        const scale = Math.min(1, FULL_FRAME_MAX_WIDTH / videoEl.videoWidth);
        fullFrameCanvas.width = Math.max(1, Math.round(videoEl.videoWidth * scale));
        fullFrameCanvas.height = Math.max(1, Math.round(videoEl.videoHeight * scale));
        fullFrameCtx.drawImage(videoEl, 0, 0, fullFrameCanvas.width, fullFrameCanvas.height);
        maybeStretchContrast(fullFrameCtx, fullFrameCanvas.width, fullFrameCanvas.height);

        try {
            const codes = await detector.detect(fullFrameCanvas);
            const best = codes.find(c => /^[A-Z0-9]{8,}$/.test(c.rawValue)) ?? codes[0];
            return best?.rawValue ?? null;
        } catch {
            return null;
        }
    }

    function stop() {
        scanning = false;
        clearTimeout(stallTimer);
        if (rafId) {
            cancelAnimationFrame(rafId);
        }
        window.removeEventListener('resize', refreshLayout);
        window.removeEventListener('orientationchange', refreshLayout);
        stream?.getTracks().forEach((track) => track.stop());
        videoEl.srcObject = null;
    }

    async function loop(timestamp) {
        if (!scanning) {
            return;
        }

        if (timestamp - lastScanAt >= SCAN_INTERVAL_MS) {
            lastScanAt = timestamp;

            let raw = await decodeFrame();

            if (!raw && stalled && (timestamp - lastFullFrameAttemptAt) >= FULL_FRAME_FALLBACK_INTERVAL_MS) {
                lastFullFrameAttemptAt = timestamp;
                raw = await decodeFullFrame();
            }

            if (raw) {
                const now = performance.now();
                const isRepeatWhileStillInFrame = raw === lastCode && (now - lastCodeAt) < SAME_CODE_COOLDOWN_MS;

                if (!isRepeatWhileStillInFrame) {
                    lastCode = raw;
                    lastCodeAt = now;
                    statusEl.textContent = `Terbaca: ${raw}`;
                    window.dispatchEvent(new CustomEvent('barcode-detected', { detail: { code: raw, target } }));
                    armStallHint(); // reset hint — hitung mundur lagi buat kode BERIKUTNYA (scan continuous).
                }
            }
        }

        rafId = requestAnimationFrame(loop);
    }

    async function openStream(deviceId) {
        // `width`/`height` SEBELUM ini gak diminta sama sekali — browser
        // bebas milih resolusi capture-nya sendiri, banyak yang jatuh ke
        // default rendah (640×480-an) yang jauh dari cukup buat baca
        // barcode 1D PADAT (mis. CODE128 `HWTC073A8FFF` — bar tipis rapat, 
        // laporan user, 2026-09-04). `ideal` (bukan `exact`/`min`) — kalau
        // device/browser gak sanggup segini, browser turun sendiri ke
        // resolusi terdekat yang bisa, gak nolak stream sama sekali.
        //
        // DITURUNIN dari 1920×1080 ke 1280×720 (laporan user berikutnya:
        // "malah makin susah") — 1080p bikin tiap `drawImage`/`detect()`
        // per siklus scan makin berat (lebih banyak pixel diproses), dan
        // itu numpuk sama biaya crop/decode yang emang udah jalan tiap
        // ~150ms. 720p masih JAUH lebih tajam dari default browser
        // (~480p), tapi gak seberat 1080p buat HP kelas menengah-bawah.
        const resolution = { width: { ideal: 1280 }, height: { ideal: 720 } };
// 3840 × 2160 piksel
        stream = await navigator.mediaDevices.getUserMedia(
            deviceId
                ? { video: { deviceId: { exact: deviceId }, ...resolution } }
                : { video: { facingMode: { ideal: 'environment' }, ...resolution } }
        );

        videoEl.srcObject = stream;
        await videoEl.play();

        const track = stream.getVideoTracks()[0];
        await nudgeAwayFromUltraWide(track);
        await enableContinuousAutofocus(track);
        refreshLayout();
    }

    async function start() {
        detector ??= await createDetector();

        if (!detector) {
            statusEl.textContent = 'Browser/perangkat ini belum dukung scan kamera — pakai scanner fisik USB/Bluetooth atau ketik manual.';
            onError?.(new Error('BarcodeDetector unsupported'));

            return;
        }

        try {
            // Buka apa adanya dulu (fallback facingMode) — label device baru
            // keisi SETELAH izin diberikan, jadi belum bisa milih deviceId
            // spesifik di percobaan pertama ini.
            await openStream(null);

            const devices = await navigator.mediaDevices.enumerateDevices();
            const guess = guessMainDeviceId(devices);
            rearCameras = guess.rear;

            const openedDeviceId = stream.getVideoTracks()[0]?.getSettings().deviceId;

            // Kalau tebakan "lensa utama" beda dari yang otomatis kepilih
            // browser, buka ulang spesifik ke situ.
            if (guess.deviceId && guess.deviceId !== openedDeviceId) {
                stream.getTracks().forEach((t) => t.stop());
                await openStream(guess.deviceId);
            }

            const finalDeviceId = stream.getVideoTracks()[0]?.getSettings().deviceId;
            currentDeviceIndex = rearCameras.findIndex((d) => d.deviceId === finalDeviceId);
            onCameraCountChange?.(rearCameras.length);
        } catch (err) {
            onError?.(err);

            return;
        }

        window.addEventListener('resize', refreshLayout);
        window.addEventListener('orientationchange', refreshLayout);

        scanning = true;
        lastScanAt = 0;
        lastCode = null;
        rafId = requestAnimationFrame(loop);
        statusEl.textContent = 'Arahkan kamera ke barcode…';
        armStallHint();
    }

    // Jalan keluar manual — lihat docblock "Pemilihan lensa" di atas kenapa
    // heuristic auto-pick gak bisa dijamin selalu benar semua HP.
    async function switchCamera() {
        if (rearCameras.length < 2) {
            return;
        }

        currentDeviceIndex = (currentDeviceIndex + 1) % rearCameras.length;
        stream?.getTracks().forEach((t) => t.stop());

        try {
            await openStream(rearCameras[currentDeviceIndex].deviceId);
            scanning = true;
            lastCode = null;
            rafId = requestAnimationFrame(loop);
            statusEl.textContent = 'Kamera diganti — arahkan ke barcode…';
            armStallHint();
        } catch (err) {
            onError?.(err);
        }
    }

    return { start, stop, switchCamera };
}

// Self-init tiap blok scanner di halaman — lihat poin 3 di docblock atas.
document.querySelectorAll('[data-barcode-scanner]').forEach((wrapper) => {
    const target = wrapper.dataset.barcodeScanner;
    const frameEl = wrapper.querySelector('[data-barcode-frame]');
    const videoEl = wrapper.querySelector('[data-barcode-video]');
    const statusEl = wrapper.querySelector('[data-barcode-status]');
    const toggleBtn = wrapper.querySelector('[data-barcode-toggle]');
    const toggleLabel = wrapper.querySelector('[data-barcode-toggle-label]');
    const viewfinder = wrapper.querySelector('[data-barcode-viewfinder]');
    const switchBtn = wrapper.querySelector('[data-barcode-switch]');
    const maskRefs = {
        frame: frameEl,
        maskTop: wrapper.querySelector('[data-mask-top]'),
        maskBottom: wrapper.querySelector('[data-mask-bottom]'),
        maskLeft: wrapper.querySelector('[data-mask-left]'),
        maskRight: wrapper.querySelector('[data-mask-right]'),
        windowEl: wrapper.querySelector('[data-barcode-window]'),
    };

    const hasAllMaskRefs = Object.values(maskRefs).every(Boolean);

    if (!frameEl || !videoEl || !statusEl || !toggleBtn || !toggleLabel || !viewfinder || !hasAllMaskRefs) {
        return;
    }

    const scanner = initBarcodeScan({
        videoEl,
        frameEl,
        statusEl,
        maskRefs,
        target,
        onError(err) {
            statusEl.textContent = err?.name === 'NotAllowedError'
                ? 'Izin kamera ditolak — aktifkan lewat pengaturan browser, lalu coba lagi.'
                : (statusEl.textContent || 'Kamera gak bisa diakses.');
        },
        onCameraCountChange(count) {
            // Tombol "Ganti Lensa" cuma relevan kalau HP-nya emang punya
            // lebih dari satu kamera belakang buat dipilih.
            if (switchBtn) {
                switchBtn.hidden = count < 2;
            }
        },
    });

    let active = false;

    toggleBtn.addEventListener('click', () => {
        active = !active;
        viewfinder.hidden = !active;
        toggleLabel.textContent = active ? 'Tutup Kamera' : 'Buka Kamera';
        toggleBtn.className = active ? toggleBtn.dataset.classActive : toggleBtn.dataset.classInactive;

        if (active) {
            // `hidden` baru kelepas frame ini — tunggu satu repaint biar
            // `frameEl.clientWidth/Height` kebaca bener sebelum layout mask
            // dihitung (di dalam `scanner.start()` → `refreshLayout()`).
            requestAnimationFrame(() => scanner.start());
        } else {
            scanner.stop();
            if (switchBtn) {
                switchBtn.hidden = true;
            }
        }
    });

    switchBtn?.addEventListener('click', () => scanner.switchCamera());

    // Kamera WAJIB berhenti begitu staf pindah halaman — jangan biarin nyala
    // di background (baterai + privasi, kamera ngerekam terus). Beda dari
    // qr-scan.js yang cuma satu instance, di sini bisa ada 2 blok sekaligus
    // (tab Single & Batch) — masing-masing daftar listener `pagehide`-nya
    // sendiri, aman karena `stop()` pada scanner yang gak pernah `start()`
    // cuma no-op (stream null).
    window.addEventListener('pagehide', () => scanner.stop());
});
