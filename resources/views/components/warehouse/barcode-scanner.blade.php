@props(['target'])

{{--
    Scan barcode 1D (linear) via kamera HP/laptop — pelengkap kolom input
    manual di sebelahnya, BUKAN pengganti. Scanner fisik USB/Bluetooth
    (keyboard wedge) tetap ketik langsung ke kolom teks seperti biasa;
    tombol "Buka Kamera" di sini cuma buat staf yang gak pegang scanner
    fisik.

    Bingkai pemandu SENGAJA persegi panjang PENDEK (pita tipis, bukan kotak
    kayak QR `qr/scan.blade.php`) — barcode linear itu lebar tapi TIDAK
    tinggi, dan label modem real (FiberHome/ZTE/Huawei dst) SERING numpuk
    3-4 barcode berbeda + QR MAC dalam satu label sempit (laporan user,
    2026-09-04, lihat contoh `Fiberhome-HG6145F.jpg`). Area DI LUAR bingkai
    digelapin/blur (`data-mask-*`, empat panel di keempat sisi) — bukan
    cuma kosmetik: `barcode-scan.js` BENERAN nge-crop decode-nya ke area
    yang ketutup bingkai ini doang (`WINDOW_FRACTION`, satu sumber
    kebenaran buat posisi mask CSS & area crop pixel), biar barcode
    tetangga yang numpuk gak ikut kebaca gara-gara decode full-frame.

    Logic murni JS vanilla di `resources/js/barcode-scan.js` (bukan Alpine)
    — dispatch `window` CustomEvent `barcode-detected` dengan
    `{ code, target }`. Parent (`x-data` tab Single/Batch) tinggal dengar
    lewat `@barcode-detected.window` dan filter `$event.detail.target`,
    karena satu halaman bisa punya lebih dari satu blok scanner (tab Single
    & Batch masing-masing punya sendiri).

    Tombol "Ganti Lensa" — `hidden` by default, cuma dimunculin JS begitu
    kedetek HP-nya punya >1 kamera belakang. Lihat docblock "Pemilihan
    lensa" di `barcode-scan.js`: HP flagship SERING default ke lensa
    Ultra-Wide (barcode kelihatan kecil/jauh, susah fokus) — auto-pick di
    JS itu heuristic (label device/zoom capability, gak dijamin akurat
    semua vendor), tombol ini jalan keluar manual kalau tebakannya salah.
--}}
<div data-barcode-scanner="{{ $target }}" class="border-t border-slate-100 dark:border-slate-700/60 pt-4 mt-4">
    <div class="flex items-center justify-between gap-3 mb-1">
        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide">Scan Kamera (Opsional)</span>
        <button type="button"
                data-barcode-toggle
                data-class-inactive="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors cursor-pointer shrink-0"
                data-class-active="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-white bg-rose-600 hover:bg-rose-700 transition-colors cursor-pointer shrink-0"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors cursor-pointer shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.174C3.244 7.54 2.5 8.352 2.5 9.318v9.132a2.25 2.25 0 002.25 2.25h14.5a2.25 2.25 0 002.25-2.25V9.318c0-.966-.744-1.778-1.552-1.914a48.11 48.11 0 00-1.134-.174 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>
            </svg>
            <span data-barcode-toggle-label>Buka Kamera</span>
        </button>
    </div>
    <p class="text-[10px] text-slate-400 mb-2.5">Scanner fisik USB/Bluetooth tetap bisa ketik langsung ke kolom di atas — tombol ini cuma buat scan pakai kamera HP/laptop.</p>

    <div data-barcode-viewfinder hidden>
        <div data-barcode-frame class="relative w-full bg-black rounded-2xl overflow-hidden shadow-lg" style="aspect-ratio: 16 / 9;">
            <video data-barcode-video autoplay playsinline muted class="absolute inset-0 w-full h-full object-cover"></video>

            {{-- "Ganti Lensa" — hidden default, dimunculin JS kalau HP-nya
                 kedetek punya >1 kamera belakang (lihat docblock atas). --}}
            <button type="button" data-barcode-switch hidden title="Ganti Lensa Kamera"
                    class="absolute top-3 right-3 z-20 w-9 h-9 rounded-full bg-black/50 hover:bg-black/70 text-white flex items-center justify-center backdrop-blur-sm transition-colors cursor-pointer">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
            </button>

            {{-- Mask gelap/blur DI LUAR jendela bingkai + bingkai itu sendiri.
                 Posisi 4 panel & jendela di-set inline style oleh JS
                 (`layoutMask()`), bukan Tailwind statis — ukurannya harus
                 PERSIS ngikut `WINDOW_FRACTION` yang sama dipakai buat
                 nge-crop area decode (lihat docblock atas & barcode-scan.js). --}}
            <div class="absolute inset-0 pointer-events-none z-10">
                <div data-mask-top class="absolute inset-x-0 top-0 bg-black/55 backdrop-blur-[2px]"></div>
                <div data-mask-bottom class="absolute inset-x-0 bottom-0 bg-black/55 backdrop-blur-[2px]"></div>
                <div data-mask-left class="absolute bg-black/55 backdrop-blur-[2px]"></div>
                <div data-mask-right class="absolute bg-black/55 backdrop-blur-[2px]"></div>

                <div data-barcode-window class="absolute">
                    <span class="absolute -top-0.5 -left-0.5 w-5 h-5 sm:w-6 sm:h-6 border-t-[3px] border-l-[3px] border-emerald-400 rounded-tl-md"></span>
                    <span class="absolute -bottom-0.5 -left-0.5 w-5 h-5 sm:w-6 sm:h-6 border-b-[3px] border-l-[3px] border-emerald-400 rounded-bl-md"></span>
                    <span class="absolute -top-0.5 -right-0.5 w-5 h-5 sm:w-6 sm:h-6 border-t-[3px] border-r-[3px] border-emerald-400 rounded-tr-md"></span>
                    <span class="absolute -bottom-0.5 -right-0.5 w-5 h-5 sm:w-6 sm:h-6 border-b-[3px] border-r-[3px] border-emerald-400 rounded-br-md"></span>
                    <span class="barcode-scanline absolute inset-x-0 h-0.5 bg-emerald-400 rounded-full shadow-[0_0_6px_1px_rgba(52,211,153,0.7)]"></span>
                </div>
            </div>

            <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/80 to-transparent px-4 py-3 z-10">
                <p data-barcode-status class="text-[11px] font-medium text-white text-center min-h-[1.4em]">Meminta izin kamera…</p>
            </div>
        </div>
    </div>
</div>
