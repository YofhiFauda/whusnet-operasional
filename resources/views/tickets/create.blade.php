@extends('layouts.app')

@section('title', 'Worksheet Helpdesk - Create Service Ticket')
@section('page_title', 'Worksheet Helpdesk — New Ticket')

@section('content')
{{--
    Layout worksheet (rancangan whusnet_helpdesk_ticketing_system.html):
    dua kolom full-bleed — panel form kiri yang bisa dilipat + panel antrean
    kanan yang melebar otomatis begitu form dilipat. Bukan grid 12-kolom biasa
    karena panel kanan harus punya area scroll sendiri, bukan ikut scroll
    halaman.

    `-m-4 sm:-m-6 lg:-m-8` sengaja mengimbangi padding <main> di
    layouts/app.blade.php supaya panel benar-benar nempel ke tepi konten;
    tingginya dikunci 100dvh dikurangi tinggi header (h-16 = 4rem) supaya
    <main> sendiri gak ikut scroll.
--}}
{{--
    `relative` dipakai buat jangkar panel form yang di bawah xl jadi overlay
    (absolute inset-y-0) — lihat komentar panel form di bawah.
--}}
<div x-data="ticketPage()" @keydown.window="handleShortcut($event)"
     @ticket-drawer-action.window="handleDrawerAction($event.detail)"
     {{--
         Row-navigasi keyboard (Arrow/C/V/B, lihat handleShortcut()) HARUS
         nonaktif selagi drawer detail kebuka — drawer punya scroll/fokusnya
         sendiri, dobel-handle bikin baris di belakang drawer diam-diam
         kegeser tanpa user sadar.

         Dengerin 'ticket-drawer-shown'/'hidden' (state SEBENARNYA, dispatch
         dari x-effect di detail-drawer.blade.php), BUKAN 'open-ticket-drawer'/
         'close-ticket-drawer' (itu PERMINTAAN, bukan notifikasi — drawer yang
         ditutup manual lewat tombol X/backdrop/Escape gak pernah lewat
         'close-ticket-drawer', jadi drawerOpen bakal nyangkut true selamanya
         kalau dengerin event yang salah — bug yang sempet kejadian, lihat
         docs/plan/analisa-percepatan-alur-helpdesk-noc.md).
     --}}
     @ticket-drawer-shown.window="drawerOpen = true"
     @ticket-drawer-hidden.window="drawerOpen = false"
     class="relative -m-4 sm:-m-6 lg:-m-8 h-[calc(100dvh-4rem)] flex overflow-hidden bg-background">

    {{-- Toast Notification --}}
    <div x-show="toast.show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-4 translate-x-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 translate-x-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 translate-x-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-2 translate-x-2 scale-95"
         class="fixed top-4 right-4 sm:top-5 sm:right-6 z-[9999] max-w-sm sm:max-w-md w-auto rounded-xl shadow-2xl border px-4 py-3 flex items-start justify-between gap-3 backdrop-blur-md pointer-events-auto"
         :class="toast.type === 'error'
             ? 'bg-rose-50/95 dark:bg-rose-900/90 border-rose-200 dark:border-rose-700 text-rose-800 dark:text-rose-100'
             : 'bg-emerald-50/95 dark:bg-emerald-900/90 border-emerald-200 dark:border-emerald-700 text-emerald-800 dark:text-emerald-100'">
        <div class="flex items-start gap-2.5 min-w-0">
            <svg class="h-5 w-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path x-show="toast.type !== 'error'" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                <path x-show="toast.type === 'error'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="text-xs font-semibold leading-relaxed" x-text="toast.message"></span>
        </div>
        <button type="button" @click="toast.show = false" class="p-1 -mr-1 -mt-0.5 rounded-lg opacity-70 hover:opacity-100 hover:bg-black/5 dark:hover:bg-white/10 transition-all shrink-0 cursor-pointer" title="Tutup">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{--
        Modal "Tambah Pelanggan Terdampak" (revisi Worksheet Helpdesk poin 4)
        — SENGAJA modal (bukan halaman create), lihat catatan di batchModal
        state. Submit AJAX, error tampil inline di modal, gak pernah nutup
        sendiri gara-gara validasi gagal.
    --}}
    <div x-show="batchModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 dark:bg-slate-950/70"
         @keydown.escape.window="closeBatchModal()">
        <div @click.outside="closeBatchModal()" class="w-full max-w-md rounded-xl bg-surface border border-border shadow-2xl overflow-hidden">
            <div class="px-4 py-3 border-b border-border flex items-center justify-between">
                <h3 class="text-sm font-bold text-text-main">Tambah Pelanggan Terdampak</h3>
                <button type="button" @click="closeBatchModal()" class="text-text-muted hover:text-text-main cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-4 space-y-3">
                <div class="space-y-1.5 relative">
                    <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">Cari CID / Nama Pelanggan</label>
                    <input type="text" x-model="batchModal.cidQuery" @input.debounce.300ms="searchBatchCustomer()"
                           :disabled="batchModal.selected !== null"
                           placeholder="Ketik CID atau nama..."
                           class="w-full text-sm rounded-lg border border-border bg-background px-3 py-2.5 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 disabled:bg-surface-muted transition-all">
                    <button type="button" x-show="batchModal.selected" x-cloak
                            @click="batchModal.selected = null; batchModal.cidQuery = ''"
                            class="absolute right-3 top-8 text-xs font-bold text-sky-600 hover:text-sky-700 underline cursor-pointer">
                        Ganti
                    </button>
                    <div x-show="batchModal.results.length > 0 && !batchModal.selected" x-cloak
                         class="absolute z-10 mt-1 w-full bg-surface border border-border rounded-lg shadow-xl max-h-48 overflow-y-auto divide-y divide-border">
                        <template x-for="r in batchModal.results" :key="r.id">
                            <button type="button" @click="pickBatchCustomer(r)" class="w-full text-left px-3 py-2 text-sm hover:bg-sky-50 dark:hover:bg-slate-800 cursor-pointer">
                                <div class="font-bold text-text-main" x-text="r.nama"></div>
                                <div class="text-xs text-text-muted font-mono" x-text="r.cid"></div>
                            </button>
                        </template>
                    </div>
                </div>

                <p class="text-[11px] text-text-muted">Pilih dari pencarian buat auto-isi Nama/No. HP di bawah (tetap bisa diedit), atau isi manual kalau gak ketemu.</p>

                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">Nama Pelanggan</label>
                    <input type="text" x-model="batchModal.customerName"
                           class="w-full text-sm rounded-lg border border-border bg-background px-3 py-2.5 text-text-main transition-all">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">No. HP</label>
                    <input type="text" x-model="batchModal.phone"
                           class="w-full text-sm rounded-lg border border-border bg-background px-3 py-2.5 text-text-main transition-all">
                </div>

                <p x-show="batchModal.error" x-cloak class="text-[11px] font-semibold text-rose-600 dark:text-rose-400" x-text="batchModal.error"></p>
            </div>
            <div class="px-4 py-3 border-t border-border flex items-center justify-end gap-2 bg-surface-muted/60 dark:bg-slate-900/40">
                <button type="button" @click="closeBatchModal()" class="px-3 py-2 rounded-lg text-xs font-bold text-text-muted hover:bg-surface border border-transparent hover:border-border cursor-pointer">
                    Batal
                </button>
                <button type="button" @click="submitBatchMember()" :disabled="batchModal.submitting"
                        class="px-3 py-2 rounded-lg bg-violet-600 text-white text-xs font-bold hover:bg-violet-700 disabled:opacity-50 cursor-pointer">
                    <span x-show="!batchModal.submitting">Tambah</span>
                    <span x-show="batchModal.submitting" x-cloak>Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>

    {{--
        STRIP TERLIPAT — pengganti panel form waktu dilipat. Klik di mana pun
        pada strip membuka form lagi (target klik selebar strip, bukan cuma
        ikonnya).

        SENGAJA gak pakai x-show/x-transition: display:none bikin lebarnya
        hilang mendadak, panel kanan nge-snap, dan gerakan slide strip nabrak
        gerakan panel form — itu sumber kesan "bounce". Di sini strip & panel
        form sama-sama cuma menganimasikan LEBAR dengan durasi + easing yang
        identik, jadi total lebar dua panel selalu konstan dan panel kanan
        melebar mulus tanpa lompatan.
    --}}
    <button type="button" @click="setFormOpen(true)" tabindex="-1"
            :aria-hidden="formOpen ? 'true' : 'false'"
            :tabindex="formOpen ? '-1' : '0'"
            title="Buka form tiket baru (N / Alt+N)"
            class="group shrink-0 overflow-hidden flex flex-col items-center justify-between py-6 bg-surface border-r border-border hover:bg-sky-50 dark:hover:bg-slate-800/60 cursor-pointer panel-motion"
            :class="[
                formOpen ? 'w-0 border-r-0 opacity-0 pointer-events-none' : 'w-11 opacity-100',
                animReady ? '' : 'panel-motion-off',
            ]">
        <div class="flex flex-col items-center gap-4">
            <span class="w-7 h-7 rounded-lg bg-sky-100 dark:bg-sky-900/40 border border-sky-300 dark:border-sky-700 text-sky-600 dark:text-sky-400 flex items-center justify-center transition-transform duration-200 group-hover:scale-110 group-hover:bg-sky-600 group-hover:text-white">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
            </span>
            <span class="[writing-mode:vertical-rl] text-[11px] font-bold uppercase tracking-[0.35em] text-text-muted group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors">
                Ticketing
            </span>
        </div>
        <svg class="h-3.5 w-3.5 text-text-muted group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-all duration-200 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m13 5 7 7-7 7M5 5l7 7-7 7" />
        </svg>
    </button>

    {{--
        ══════════ PANEL KIRI: WORKSHEET FORM ══════════
        DUA REZIM, dipisah di `xl`:

        • < xl — form jadi OVERLAY (`absolute inset-y-0 left-0 w-full`) di atas
          panel antrean, buka/tutup dengan geser (`translate-x`). Sidebar app
          lebarnya 256px dan static dari `md`, jadi panel yang dulu `w-screen`
          (= 100vw) selalu LEBIH LEBAR dari area konten — itu penyebab layout
          tablet/laptop kecil berantakan & scroll horizontal. `w-full` ngukur
          area konten, bukan viewport.

        • ≥ xl — kembali jadi panel statis dua kolom yang menganimasikan LEBAR,
          karena di lebar ini form + tabel muat berdampingan.

        Lebar dikunci 360px (xl) / 400px (2xl): sisanya dikasih ke tabel antrean
        supaya kolomnya fit tanpa scroll horizontal.
    --}}
    {{--
        Backdrop khusus rezim overlay (< xl) — klik di sisa area antrean nutup
        form, pola yang sama kayak sidebar app di mobile. Di xl ke atas
        dimatikan total karena panelnya udah gak numpuk.
    --}}
    <div x-show="formOpen" x-cloak @click="setFormOpen(false)"
         x-transition.opacity.duration.200ms
         class="absolute inset-0 z-10 bg-slate-900/40 dark:bg-slate-950/60 xl:hidden"></div>

    {{-- inert waktu terlipat — field form yang lebarnya 0 gak boleh kejaring Tab. --}}
    <div class="absolute inset-y-0 left-0 z-20 w-full max-w-[440px] sm:max-w-[400px] lg:static lg:z-auto lg:max-w-none shrink-0 overflow-hidden flex panel-motion"
         :inert="!formOpen"
         :class="[
             formOpen
                 ? 'translate-x-0 opacity-100 lg:w-[380px] xl:w-[400px] 2xl:w-[440px]'
                 : '-translate-x-full opacity-0 lg:translate-x-0 lg:w-0',
             animReady ? '' : 'panel-motion-off',
         ]">
    <div class="w-full lg:w-[380px] xl:w-[400px] 2xl:w-[440px] shrink-0 flex flex-col min-w-0 bg-surface border-r border-border shadow-xl z-10">

        {{-- Header Panel --}}
        <div class="shrink-0 px-4 sm:px-5 py-3 border-b border-border bg-surface flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xs font-bold text-text-main uppercase tracking-wider truncate">Buat Tiket Baru</h1>
                    <p class="text-[10px] text-text-muted truncate">Worksheet input cepat (Ctrl+Enter)</p>
                </div>
            </div>

            {{-- Tombol lipat panel (bukan navigasi keluar halaman) --}}
            <button type="button" @click="setFormOpen(false)" title="Tutup form (Esc / Alt+N)"
                    class="shrink-0 p-1.5 rounded-lg text-text-muted hover:bg-surface-muted hover:text-text-main active:scale-95 transition-all duration-200 cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{--
            Submit mode: fetch() POST JSON (Accept: application/json) ke /tickets.
        --}}
        <form action="{{ route('tickets.store') }}" method="POST" @submit.prevent="submitForm()" enctype="multipart/form-data" class="flex-1 flex flex-col min-h-0">
            @csrf

            <div x-ref="formBody" class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-3.5">

                {{-- Row 1: Klasifikasi & Prioritas --}}
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-text-secondary uppercase tracking-wider">
                            Tipe Tiket <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <select x-model="ticketType" @change="delete errors.type" required
                                    class="w-full text-xs font-medium rounded-lg border bg-surface px-2.5 py-2 text-text-main appearance-none focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all font-mono"
                                    :class="errors.type ? 'border-rose-400 bg-rose-50/20' : 'border-border'">
                                <option value="" disabled>-- Pilih Tipe --</option>
                                @foreach($typeOptions as $opt)
                                    <option value="{{ $opt['value'] }}">{{ $opt['value'] }} — {{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                            <svg class="h-3.5 w-3.5 absolute right-2.5 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <p x-show="errors.type" x-cloak x-text="errors.type" class="text-[10px] text-rose-500 font-semibold"></p>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-text-secondary uppercase tracking-wider">
                            Prioritas <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <select x-model="priority" required class="w-full text-xs font-medium rounded-lg border border-border bg-surface px-2.5 py-2 text-text-main appearance-none focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                                @foreach($priorityOptions as $p)
                                    <option value="{{ $p->value }}">{{ $p->value }}</option>
                                @endforeach
                            </select>
                            <svg class="h-3.5 w-3.5 absolute right-2.5 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Row 2: Customer Search / Selection --}}
                <div class="space-y-1.5 pt-1">
                    <div class="flex items-center justify-between">
                        <label class="block text-[10px] font-bold text-text-secondary uppercase tracking-wider">
                            Data Pelanggan <span class="text-rose-500">*</span>
                        </label>
                        <span x-show="selected" x-cloak class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Terpilih
                        </span>
                    </div>

                    {{-- Search Input (shown when customer not selected) --}}
                    <div class="relative" x-show="!selected">
                        <div class="relative flex items-center">
                            <svg class="h-3.5 w-3.5 absolute left-3 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>

                            <input type="text" x-ref="searchInput" x-model="cidQuery"
                                   @input.debounce.300ms="searchCustomer()"
                                   @keydown.enter.prevent="if (results.length > 0) pick(results[0])"
                                   placeholder="Ketik CID, nama, atau label batch..."
                                   class="w-full text-xs rounded-lg border bg-surface pl-8 pr-8 py-2 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all font-medium"
                                   :class="errors.cid_query || errors.customer_id ? 'border-rose-400 bg-rose-50/20' : 'border-border'">

                            <button type="button" x-show="cidQuery" x-cloak @click="cidQuery = ''; results = []" class="absolute right-2.5 text-text-muted hover:text-text-main transition-colors cursor-pointer">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Autocomplete Dropdown --}}
                        <div x-show="results.length > 0 && !selected" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-1 scale-98"
                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                             class="absolute z-30 mt-1 w-full bg-surface border border-border rounded-xl shadow-2xl max-h-56 overflow-y-auto custom-scrollbar divide-y divide-border/30">
                            <div class="px-2.5 py-1 text-[9px] font-bold text-sky-600 dark:text-sky-400 bg-sky-50/70 dark:bg-sky-950/40 uppercase tracking-wider flex items-center justify-between">
                                <span>Hasil Pencarian:</span>
                                <span class="font-normal font-mono text-[9px]">Tekan Enter atau Klik</span>
                            </div>
                            <template x-for="r in results" :key="r.id">
                                <button type="button" @click="pick(r)" class="w-full text-left px-3 py-2 text-xs hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors flex items-center justify-between gap-2 cursor-pointer group">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-text-main group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors truncate" x-text="r.nama"></div>
                                        <div class="text-[10px] text-text-muted font-mono" x-text="r.cid"></div>
                                    </div>
                                    <span class="inline-block px-1.5 py-0.5 rounded bg-surface-muted text-text-secondary border border-border font-bold text-[9px] shrink-0" x-text="r.pop || 'NO POP'"></span>
                                </button>
                            </template>
                        </div>

                        <p x-show="searching" x-cloak class="text-[10px] text-sky-600 dark:text-sky-400 mt-1 flex items-center gap-1 font-medium">
                            <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Mencari pelanggan...
                        </p>

                        <p x-show="!searching && searched && results.length === 0 && !selected" x-cloak class="text-[10px] text-sky-600 dark:text-sky-400 mt-1 font-medium">
                            Pelanggan tidak ditemukan. Teks di atas digunakan sebagai label tiket. Tentukan POP di bawah:
                        </p>

                        <p x-show="errors.cid_query || errors.customer_id" x-cloak x-text="errors.cid_query || errors.customer_id" class="text-[10px] text-rose-500 font-semibold mt-1"></p>
                    </div>

                    {{-- Sleek Compact Verified Customer Summary Card --}}
                    <div x-show="selected" x-cloak
                         class="rounded-xl border border-sky-200/80 dark:border-sky-800/60 bg-sky-50/20 dark:bg-sky-950/15 p-2.5 space-y-2 text-xs transition-all shadow-2xs">
                        {{-- Row 1: Nama, CID, and Ganti button --}}
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                <span class="font-bold text-text-main truncate text-xs" x-text="selected?.nama || '—'"></span>
                                <span class="font-mono text-[10px] font-bold text-sky-600 dark:text-sky-400 bg-surface px-1.5 py-0.2 rounded border border-sky-200 dark:border-sky-800 shrink-0" x-text="selected?.cid || '—'"></span>
                            </div>
                            <button type="button" @click="clearSelection()" class="text-[10px] font-bold text-sky-600 dark:text-sky-400 hover:text-rose-600 hover:underline cursor-pointer shrink-0">
                                Ganti
                            </button>
                        </div>

                        {{-- Row 2: WhatsApp Link, Paket, and POP / ODP Chips --}}
                        <div class="flex items-center gap-1.5 flex-wrap pt-1.5 border-t border-border/40 text-[10px]">
                            <template x-if="selected?.no_hp">
                                <a :href="'https://wa.me/' + selected.no_hp" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-mono font-bold hover:underline"
                                   title="Buka WhatsApp">
                                    <svg class="h-2.5 w-2.5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/>
                                    </svg>
                                    <span x-text="selected.no_hp"></span>
                                </a>
                            </template>
                            <span class="px-1.5 py-0.5 rounded bg-surface text-text-secondary border border-border font-medium truncate" x-text="selected?.paket || 'Tanpa Paket'"></span>
                            <span class="px-1.5 py-0.5 rounded bg-surface text-text-secondary border border-border font-medium truncate" x-text="(selected?.pop || '—') + ' / ' + (selected?.odp || '—')"></span>
                        </div>

                        {{-- Row 3: Alamat & Maps Link --}}
                        <div class="flex items-center justify-between gap-2 text-[10px] text-text-muted pt-0.5">
                            <div class="flex items-center gap-1 min-w-0 truncate" :title="selected?.alamat">
                                <svg class="h-3 w-3 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="truncate" x-text="selected?.alamat || '—'"></span>
                            </div>
                            <template x-if="selected?.maps_url">
                                <a :href="selected.maps_url" target="_blank" rel="noopener" class="text-sky-600 dark:text-sky-400 font-bold hover:underline shrink-0 flex items-center gap-0.5">
                                    <span>Maps</span>
                                    <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                </a>
                            </template>
                        </div>
                    </div>

                    {{-- POP Selector (only when manual non-customer mode) --}}
                    <div class="space-y-1 relative" x-show="!selected && searched && results.length === 0" x-cloak>
                        <div class="flex items-center justify-between">
                            <label class="block text-[10px] font-bold text-text-secondary uppercase tracking-wider">
                                Wilayah Jaringan / POP <span class="text-rose-500">*</span>
                            </label>
                            <span class="text-[9px] font-mono text-text-muted" x-text="allowedPops.length + ' POP'"></span>
                        </div>

                        {{-- Trigger Box --}}
                        <div class="relative">
                            <button type="button" x-show="!selectedPop" @click="openPopPicker()"
                                    class="w-full text-left px-2.5 py-2 text-xs rounded-lg border bg-surface flex items-center justify-between gap-2 cursor-pointer transition-all font-medium"
                                    :class="errors.pop_id ? 'border-rose-400 bg-rose-50/20 text-rose-600' : 'border-border text-text-muted hover:border-sky-500 hover:text-text-main'">
                                <span class="flex items-center gap-1.5 truncate">
                                    <svg class="h-3.5 w-3.5 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                    </svg>
                                    <span>-- Pilih POP / Cabang / Mini POP --</span>
                                </span>
                                <svg class="h-3.5 w-3.5 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div x-show="selectedPop" x-cloak
                                 class="w-full px-2.5 py-1.5 text-xs rounded-lg border bg-surface flex items-center justify-between gap-2 shadow-2xs"
                                 :class="{
                                     'border-purple-300 dark:border-purple-800 bg-purple-50/30 dark:bg-purple-950/20': selectedPop?.type === 'pusat',
                                     'border-sky-300 dark:border-sky-800 bg-sky-50/30 dark:bg-sky-950/20': selectedPop?.type === 'cabang',
                                     'border-emerald-300 dark:border-emerald-800 bg-emerald-50/30 dark:bg-emerald-950/20': selectedPop?.type === 'mini_pop'
                                 }">
                                <div class="flex items-center gap-1.5 min-w-0 flex-1 cursor-pointer" @click="openPopPicker()" title="Klik untuk mengganti POP">
                                    <span class="px-1.5 py-0.2 rounded text-[8px] font-bold uppercase font-mono tracking-wide shrink-0"
                                          :class="{
                                              'bg-purple-600 text-white': selectedPop?.type === 'pusat',
                                              'bg-sky-600 text-white': selectedPop?.type === 'cabang',
                                              'bg-emerald-600 text-white': selectedPop?.type === 'mini_pop'
                                          }"
                                          x-text="selectedPop?.type === 'pusat' ? 'PUSAT' : (selectedPop?.type === 'cabang' ? 'CABANG' : 'MINI POP')">
                                    </span>
                                    <span class="font-bold text-text-main truncate text-xs" x-text="selectedPop?.name"></span>
                                    <span x-show="selectedPop?.code" class="text-[9px] font-mono text-text-muted shrink-0" x-text="'[' + selectedPop?.code + ']'"></span>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button type="button" @click="openPopPicker()" class="px-1.5 py-0.5 text-[10px] font-semibold text-sky-600 dark:text-sky-400 hover:underline cursor-pointer">
                                        Ganti
                                    </button>
                                    <button type="button" @click="clearPop()" class="p-1 rounded text-text-muted hover:text-rose-500 cursor-pointer" title="Hapus pilihan">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Floating Popover --}}
                            <div x-show="popPickerOpen" x-cloak
                                 @click.outside="closePopPicker()"
                                 @keydown="onPopKeydown($event)"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1 scale-98"
                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                 class="absolute z-40 top-full left-0 right-0 mt-1 rounded-xl border border-border bg-surface p-2 space-y-1.5 shadow-2xl ring-1 ring-black/5 dark:ring-white/10">
                                
                                <div class="space-y-1.5">
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-text-muted">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <input type="text" x-ref="popSearchInput" x-model="popSearchQuery"
                                               @input="popHighlightedIndex = 0"
                                               placeholder="Ketik nama POP, kode, cabang, atau mini pop..."
                                               class="w-full pl-8 pr-7 py-1.5 text-xs rounded-lg border border-border bg-background text-text-main placeholder:text-text-muted focus:outline-none focus:ring-1 focus:ring-sky-500 font-medium">
                                        <button type="button" x-show="popSearchQuery" @click="popSearchQuery = ''; popHighlightedIndex = 0"
                                                class="absolute inset-y-0 right-0 pr-2 flex items-center text-text-muted hover:text-text-main cursor-pointer">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                    <div class="flex items-center gap-1 bg-surface-muted/60 dark:bg-slate-900/60 p-0.5 rounded-lg text-[10px] font-bold">
                                        <button type="button" @click="popTypeFilter = 'all'; popHighlightedIndex = 0"
                                                :class="popTypeFilter === 'all' ? 'bg-surface text-text-main shadow-2xs' : 'text-text-muted hover:text-text-main'"
                                                class="flex-1 py-0.5 px-1 rounded text-center transition-all cursor-pointer">
                                            Semua
                                        </button>
                                        <button type="button" @click="popTypeFilter = 'cabang'; popHighlightedIndex = 0"
                                                :class="popTypeFilter === 'cabang' ? 'bg-surface text-sky-600 dark:text-sky-400 shadow-2xs' : 'text-text-muted hover:text-text-main'"
                                                class="flex-1 py-0.5 px-1 rounded text-center transition-all cursor-pointer">
                                            Cabang
                                        </button>
                                        <button type="button" @click="popTypeFilter = 'mini_pop'; popHighlightedIndex = 0"
                                                :class="popTypeFilter === 'mini_pop' ? 'bg-surface text-emerald-600 dark:text-emerald-400 shadow-2xs' : 'text-text-muted hover:text-text-main'"
                                                class="flex-1 py-0.5 px-1 rounded text-center transition-all cursor-pointer">
                                            Mini POP
                                        </button>
                                        <button type="button" @click="popTypeFilter = 'pusat'; popHighlightedIndex = 0"
                                                :class="popTypeFilter === 'pusat' ? 'bg-surface text-purple-600 dark:text-purple-400 shadow-2xs' : 'text-text-muted hover:text-text-main'"
                                                class="flex-1 py-0.5 px-1 rounded text-center transition-all cursor-pointer">
                                            Pusat
                                        </button>
                                    </div>
                                </div>

                                <div class="max-h-52 overflow-y-auto custom-scrollbar divide-y divide-border/20 pr-0.5">
                                    <template x-for="(item, idx) in displayPopList" :key="item.id">
                                        <div>
                                            <template x-if="item.is_header">
                                                <div class="text-[9px] font-extrabold uppercase tracking-wider text-text-muted px-2 pt-2 pb-1 bg-surface-muted/30 flex items-center gap-1.5 select-none"
                                                     x-text="item.header_title"></div>
                                            </template>

                                            <template x-if="!item.is_header">
                                                <button type="button"
                                                        :id="'pop-opt-' + selectablePops.findIndex(p => p.id === item.id)"
                                                        @click="selectPop(item)"
                                                        @mouseenter="popHighlightedIndex = selectablePops.findIndex(p => p.id === item.id)"
                                                        class="w-full text-left px-2 py-1.5 rounded-md transition-all flex items-center justify-between gap-2 cursor-pointer group"
                                                        :class="{
                                                            'pl-5': item.indent,
                                                            'bg-sky-50 dark:bg-sky-950/60 ring-1 ring-sky-400/50 dark:ring-sky-600/50': selectablePops[popHighlightedIndex]?.id === item.id,
                                                            'hover:bg-surface-muted dark:hover:bg-slate-800/60': selectablePops[popHighlightedIndex]?.id !== item.id,
                                                            'border-l-2 border-purple-500': item.type === 'pusat' && !item.indent,
                                                            'border-l-2 border-sky-500': item.type === 'cabang' && !item.indent,
                                                            'border-l-2 border-emerald-500': item.type === 'mini_pop' && !item.indent
                                                        }">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <span x-show="item.indent" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-mono select-none">↳</span>
                                                        <span class="px-1.5 py-0.2 rounded text-[8px] font-bold uppercase font-mono tracking-wider shrink-0"
                                                              :class="{
                                                                  'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300': item.type === 'pusat',
                                                                  'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300': item.type === 'cabang',
                                                                  'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300': item.type === 'mini_pop'
                                                              }"
                                                              x-text="item.type_label || (item.type === 'pusat' ? 'PUSAT' : (item.type === 'cabang' ? 'CABANG' : 'MINI POP'))">
                                                        </span>
                                                        <span class="text-xs font-semibold text-text-main group-hover:text-sky-600 dark:group-hover:text-sky-400 truncate"
                                                              :class="{'font-bold': item.type === 'pusat' || item.type === 'cabang'}"
                                                              x-text="item.name"></span>
                                                        <span x-show="item.code" class="text-[9px] font-mono text-text-muted shrink-0" x-text="'[' + item.code + ']'"></span>
                                                    </div>

                                                    <div class="flex items-center gap-1 shrink-0">
                                                        <span x-show="String(item.id) === String(popId)" class="text-emerald-600 text-[10px] font-bold">✓ Aktif</span>
                                                        <span x-show="String(item.id) !== String(popId)" class="text-[10px] text-sky-600 dark:text-sky-400 font-bold opacity-0 group-hover:opacity-100 transition-opacity">Pilih ❯</span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                <div class="pt-1 border-t border-border/40 flex items-center justify-between text-[9px] text-text-muted px-1">
                                    <span>Gunakan <kbd class="px-1 py-0.2 bg-surface-muted rounded border border-border font-mono">↑</kbd><kbd class="px-1 py-0.2 bg-surface-muted rounded border border-border font-mono">↓</kbd> &amp; <kbd class="px-1 py-0.2 bg-surface-muted rounded border border-border font-mono">Enter</kbd></span>
                                    <span class="font-mono" x-text="selectablePops.length + ' pilihan'"></span>
                                </div>
                            </div>
                        </div>

                        <p x-show="errors.pop_id" x-text="errors.pop_id" class="text-[10px] text-rose-500 font-medium"></p>
                    </div>

                    {{-- No. HP Pelapor (Optional) --}}
                    <div class="space-y-1 pt-0.5">
                        <label class="block text-[10px] font-bold text-text-secondary uppercase tracking-wider">
                            No. HP Pelapor <span class="normal-case font-normal text-text-muted">(opsional jika beda kontak)</span>
                        </label>
                        <input type="text" x-model="reporterPhone"
                               placeholder="Kosongkan = pakai No. HP pelanggan"
                               class="w-full text-xs rounded-lg border border-border bg-surface px-2.5 py-1.5 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all font-medium">
                    </div>

                    {{-- Duplicate Ticket Warning --}}
                    <div x-show="selected && duplicateTickets.length > 0" x-cloak
                         class="flex items-start gap-2 p-2.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                        <svg class="h-4 w-4 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-[11px] font-semibold text-amber-700 dark:text-amber-300 leading-tight">
                            Pelanggan punya <span x-text="duplicateTickets.length"></span> tiket aktif:
                            <template x-for="d in duplicateTickets" :key="d.id">
                                <span class="font-mono font-bold" x-text="d.code + ' '"></span>
                            </template>
                        </p>
                    </div>
                </div>

                {{-- Row 3: Kategori Issue & Detail Keluhan --}}
                <div class="space-y-3 pt-1 border-t border-border/60">
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="block text-[10px] font-bold text-text-secondary uppercase tracking-wider">
                                Kategori Issue <span class="text-rose-500">*</span>
                            </label>
                            <span x-show="selectedCategorySlaSource" x-cloak class="text-[9px] font-semibold text-sky-600 dark:text-sky-400">
                                SLA: <span x-text="selectedCategorySlaSource === 'paket' ? 'Paket Internet' : 'Prioritas'"></span>
                            </span>
                        </div>
                        <div class="relative">
                            <select x-model="issueCategoryId" @change="onIssueCategoryChange()" required class="w-full text-xs font-medium rounded-lg border border-border bg-surface px-2.5 py-2 text-text-main appearance-none focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                                <option value="" disabled>-- Pilih Kategori Issue --</option>
                                <template x-for="c in issueCategories" :key="c.id">
                                    <option :value="c.id" x-text="c.name"></option>
                                </template>
                                <option value="lainnya">Lainnya (isi manual)</option>
                            </select>
                            <svg class="h-3.5 w-3.5 absolute right-2.5 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-text-secondary uppercase tracking-wider">
                            Detail Keluhan <span class="text-rose-500">*</span>
                        </label>
                        <textarea x-model="detailKeluhan" @input="delete errors.detail_keluhan" rows="3" required maxlength="2000"
                                  placeholder="Jelaskan kendala yang dilaporkan (mis. LOS merah, internet lambat, dsb)..."
                                  :class="errors.detail_keluhan ? 'border-rose-400 bg-rose-50/20' : 'border-border'"
                                  class="w-full text-xs rounded-lg border bg-surface p-2.5 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all resize-none"></textarea>
                        <p x-show="errors.detail_keluhan" x-cloak class="text-[10px] font-semibold text-rose-500" x-text="errors.detail_keluhan"></p>
                    </div>

                    {{-- Collapsible Catatan Teknis & Lampiran --}}
                    <div class="pt-0.5">
                        <button type="button" @click="showExtra = !showExtra" class="inline-flex items-center gap-1 text-[11px] font-bold text-sky-600 dark:text-sky-400 hover:underline cursor-pointer">
                            <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="showExtra ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                            </svg>
                            <span x-text="showExtra ? 'Sembunyikan Detail Tambahan' : '+ Catatan Teknis & Lampiran (Opsional)'"></span>
                        </button>

                        <div x-show="showExtra" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="space-y-3 pt-2">
                            <div class="space-y-1">
                                <label class="block text-[10px] font-bold text-text-secondary uppercase tracking-wider">
                                    Catatan Teknis (NOC / FOP)
                                </label>
                                <textarea x-model="catatanTeknis" rows="2" maxlength="2000"
                                          placeholder="Hasil ping, redaman OPM (-dBm), atau catatan untuk teknisi..."
                                          class="w-full font-mono text-xs rounded-lg border border-border bg-surface-muted/30 p-2 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all resize-none"></textarea>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-[10px] font-bold text-text-secondary uppercase tracking-wider">
                                    Lampiran Foto / File
                                </label>
                                <label class="flex flex-col items-center justify-center w-full h-16 border-2 border-border border-dashed rounded-lg cursor-pointer bg-surface hover:bg-sky-50/50 dark:hover:bg-slate-800/40 hover:border-sky-400 transition-colors">
                                    <div class="flex items-center gap-2 text-text-muted">
                                        <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                        <span class="text-[11px] font-medium text-text-secondary"><span class="font-bold text-sky-600">Pilih file</span> (Maks. 5 file, @5MB)</span>
                                    </div>
                                    <input type="file" x-ref="fileInput" @change="attachments = Array.from($event.target.files)" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden">
                                </label>
                                <p x-show="attachments.length > 0" x-cloak class="text-[10px] text-text-muted font-mono" x-text="attachments.length + ' file dipilih'"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action Bar --}}
            <div class="shrink-0 px-4 py-2.5 border-t border-border bg-surface flex items-center justify-between gap-2">
                <button type="button" @click="resetForm()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-text-muted hover:text-text-main hover:bg-surface-muted active:scale-95 transition-all cursor-pointer">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>Batal</span> <span class="hidden sm:inline opacity-60 font-normal font-mono text-[10px]">(Esc)</span>
                </button>

                <button type="submit" :disabled="submitting" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-sky-600 text-white text-xs font-bold hover:bg-sky-700 shadow-sm hover:shadow active:scale-95 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!submitting">Buat Tiket <span class="hidden sm:inline opacity-80 font-normal font-mono text-[10px]">(Ctrl+Enter)</span></span>
                    <span x-show="submitting" x-cloak>Menyimpan...</span>
                    <svg x-show="!submitting" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    <svg x-show="submitting" x-cloak class="h-3.5 w-3.5 animate-spin shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
    </div>

    {{--
        ══════════ PANEL KANAN: LIST TASK TICKETING ══════════
        rancangan bagian E. Initial load lewat data server-side ($initialTasks).
        Update dari aktor lain masuk lewat broadcast Reverb (initEchoListeners());
        item baru hasil submit di-prepend lokal (optimistik) dari respons JSON.

        Isinya dibungkus lapisan lebar-minimum yang cuma DIPOTONG (overflow-hidden),
        bukan disembunyikan pakai `hidden`: waktu form kebuka di layar sempit panel
        ini kegeser keluar layar dengan mulus, dan kartu di dalamnya gak reflow
        tiap frame animasi.
    --}}
    <div class="flex-1 flex min-w-0 overflow-hidden bg-background ticket-queue-container">
    <div class="flex-1 flex flex-col min-w-[280px] overflow-hidden">

        {{--
            Layout toolbar: DUA baris di bawah `lg` (tab full-width lalu
            kontrol full-width), SATU baris di `lg` ke atas (tab + kontrol
            berbagi baris via ml-auto). Sebelumnya semua elemen ini ada di
            satu flex-wrap — di layar mobile/tablet elemen ke-4/5 (select
            prioritas, toggle table/cards, refresh, bantuan) kepental ke
            baris berikutnya dalam urutan gak terduga & toolbar jadi tinggi
            banget. Kontrol sekarang SELALU nowrap (gak ada lagi jalur wrap
            yang nyembunyiin tombol) — input cari jadi `flex-1` biar nyerap
            sisa lebar, elemen lain fixed-width di sebelahnya.
        --}}
        <div class="shrink-0 p-3 border-b border-border bg-surface flex flex-col lg:flex-row lg:items-center gap-2 queue-toolbar">
            {{--
                Filter Tabs — value = TicketHandler->value asli (helpdesk/noc/fop),
                BUKAN TicketBucket lagi. Tab di sini nunjukin "tiket ini lagi
                di tangan siapa", bukan status pengerjaannya:
                  Ticket     = masih di tangan pembuat, belum dikirim ke mana pun
                  Assign NOC = udah dikirim ke NOC (langsung diproses NOC)
                  Assign FOP = udah dikirim ke FOP (pantau status Task FOP)

                Badge angka = jumlah tiket per handler SEBELUM filter prioritas
                (tabCounts), biar user tetap lihat antrean penuh tiap tab walau
                sedang menyaring prioritas tertentu.
            --}}
            <div class="w-full lg:w-auto lg:flex-1 lg:min-w-[180px] flex items-center gap-1 bg-surface-muted dark:bg-slate-900 p-1 rounded-lg text-xs font-medium text-text-muted queue-toolbar-tabs">
                <template x-for="tab in tabs" :key="tab.value">
                    <button type="button" @click="setTab(tab.value)"
                            :class="taskFilter === tab.value ? 'bg-surface text-text-main font-bold shadow-sm' : 'hover:text-text-main'"
                            class="flex-1 min-w-0 py-1.5 px-1.5 sm:px-2 rounded-md transition-all duration-200 flex items-center justify-center gap-1 sm:gap-1.5 cursor-pointer">
                        {{-- Label pendek di layar sempit ("NOC"), lengkap dari sm ke atas. --}}
                        <span class="truncate sm:hidden" x-text="tab.shortLabel"></span>
                        <span class="truncate hidden sm:inline" x-text="tab.label"></span>
                        <span class="shrink-0 px-1.5 py-px rounded-full text-[10px] font-bold font-mono text-white" :class="tab.badgeClass" x-text="tabCounts[tab.value]"></span>
                    </button>
                </template>
            </div>

            <div class="w-full lg:w-auto shrink-0 flex items-center gap-2 lg:ml-auto queue-toolbar-controls">
                {{-- Input Cari Tiket Aktif — flex-1 di semua breakpoint: nyerap sisa lebar biar elemen shrink-0 di sebelahnya gak pernah kepental baris baru. --}}
                <div class="relative flex-1 min-w-0 lg:flex-none lg:min-w-[130px] lg:max-w-[180px]">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-text-muted">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" x-model="searchQuery"
                           placeholder="Cari tiket..."
                           class="w-full pl-8 pr-7 py-1.5 text-xs rounded-lg border border-border bg-surface-muted dark:bg-slate-900 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/30 transition-all font-medium">
                    <button type="button" x-show="searchQuery" @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 pr-2 flex items-center text-text-muted hover:text-text-main cursor-pointer"
                            title="Hapus pencarian">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{--
                    Filter prioritas — murni client-side atas array `tasks` yang
                    udah dimuat. `w-9 sm:w-auto` + `truncate` biar di layar sempit
                    cuma nampilin ikon corong (opsi tetep kebaca pas dropdown
                    dibuka), gak makan lebar buat teks "Semua Prioritas".
                --}}
                <select x-model="filterPriority" title="Filter prioritas"
                        class="shrink-0 w-24 sm:w-auto sm:max-w-[9.5rem] truncate bg-surface-muted dark:bg-slate-900 border border-border text-xs font-medium rounded-lg px-2 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30">
                    <option value="ALL">Semua Prioritas</option>
                    <option value="Urgent">🔴 Urgent</option>
                    <option value="High">🟠 High</option>
                    <option value="Medium">🟡 Medium</option>
                    <option value="low">🔵 Low</option>
                </select>

                {{--
                    Table vs Card — pilihan disimpan biar gak reset tiap buka
                    halaman. Toggle disembunyiin di bawah `lg`: di lebar itu
                    tampilan dipaksa kartu (activeViewMode), jadi tombolnya cuma
                    bikin bingung.
                --}}
                <div class="hidden lg:flex items-center gap-0.5 p-0.5 rounded-lg bg-surface-muted dark:bg-slate-900 border border-border">
                    <button type="button" @click="setViewMode('table')" title="Tampilan tabel padat"
                            :class="viewMode === 'table' ? 'bg-surface text-sky-600 dark:text-sky-400 shadow-sm' : 'text-text-muted hover:text-text-main'"
                            class="p-1.5 rounded-md transition-all duration-200 cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M3 10h18M3 15h18M3 20h18" />
                        </svg>
                    </button>
                    <button type="button" @click="setViewMode('cards')" title="Tampilan kartu"
                            :class="viewMode === 'cards' ? 'bg-surface text-sky-600 dark:text-sky-400 shadow-sm' : 'text-text-muted hover:text-text-main'"
                            class="p-1.5 rounded-md transition-all duration-200 cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" />
                        </svg>
                    </button>
                </div>

                {{--
                    Refresh manual + auto-refresh via broadcast Reverb
                    (App\Events\TicketQueueUpdated, Gap #3). Auto-refresh
                    jalan sendiri kalau Echo kekoneksi; tombol ini fallback
                    kalau Reverb down/gak jalan di browser user.
                --}}
                <button type="button" @click="refreshWorksheet()" :disabled="refreshing" title="Refresh antrean tiket"
                        class="p-1.5 rounded-lg text-text-muted hover:text-sky-600 dark:hover:text-sky-400 hover:bg-surface-muted dark:hover:bg-slate-900 disabled:opacity-50 transition-colors cursor-pointer">
                    <svg class="h-4 w-4" :class="refreshing ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
                    
            </div>
        </div>

        <div class="flex-1 overflow-y-auto overflow-x-hidden custom-scrollbar">
            <template x-if="filteredTasks.length === 0">
                <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-surface-muted dark:bg-slate-800/80 border border-border flex items-center justify-center text-text-muted mb-3 shadow-2xs">
                        <svg class="h-7 w-7 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-text-main">Belum ada tiket di tab ini.</h3>
                    <p class="text-xs text-text-muted mt-1 max-w-sm" x-text="searchQuery || filterPriority !== 'ALL' ? 'Tidak ada tiket yang cocok dengan kata kunci pencarian atau filter yang dipilih.' : 'Antrean tiket pada tab ini sedang kosong atau semua tiket sudah diproses.'"></p>
                </div>
            </template>

            {{--
                ── MODE TABEL (default, Frame 139) ──
                Satu tiket = satu baris, 6 kolom tetap. Dipilih sebagai default
                karena posisi kolom yang konsisten bikin mata Helpdesk cepat
                nyisir antrean panjang — lebih cepat dari kartu yang tiap
                blok posisinya bergeser.
            --}}
            <div x-show="activeViewMode === 'table' && filteredTasks.length > 0" class="border border-border bg-surface overflow-hidden shadow-xs">
                {{--
                    `table-fixed` + lebar kolom persen = tabel MUAT di lebar
                    panel, gak ada scroll horizontal. Tanpa fixed, kolom
                    Keluhan/Pelanggan melar ngikutin teks terpanjang dan
                    tabelnya kedorong keluar layar. Konsekuensinya tiap sel
                    WAJIB punya truncate/line-clamp sendiri (lihat di bawah) —
                    kalau nggak, teks panjang nembus batas kolom.
                --}}
                {{--
                    Jumlah kolom ikut lebar layar, BUKAN cuma dikecilin:
                      < 2xl → 5 kolom (Lokasi/POP/ODP disembunyiin; POP & ODP
                              nyempil jadi baris kecil di kolom Pelanggan)
                      ≥ 2xl → 6 kolom penuh sesuai Frame 139

                    Lebar kolom ditaruh di <th> (bukan <colgroup>): `display:none`
                    di <col> gak diakui browser buat nyembunyiin kolom, jadi
                    kolom yang disembunyiin harus lewat th/td `hidden`.

                    `min-w-[660px]` = ambang 5 kolom masih kebaca. Di bawah itu
                    yang nge-scroll cuma kontainer tabelnya, halaman sendiri gak
                    pernah scroll horizontal. Dari 2xl min-width dilepas biar
                    `table-fixed` pas ngepasin lebar panel (fit, tanpa scroll).
                --}}
                <div class="overflow-x-auto 2xl:overflow-x-hidden custom-scrollbar">
                    <table class="w-full min-w-[660px] 2xl:min-w-0 table-fixed text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-surface-muted/60 dark:bg-slate-900/40 text-text-muted border-b border-border uppercase tracking-wider text-[10px] font-bold">
                                {{--
                                    3 kolom sortable (docs/plan/analisa-percepatan-alur-helpdesk-noc.md §6.1):
                                    Ticket ID & Time → by `code` (nomor tiket), Status/Issue → by
                                    `issue_category`, Lokasi/POP/ODP → by `odp`. Pelanggan SENGAJA
                                    gak sortable (keputusan user). Klik header = toggle ASC/DESC,
                                    klik header lain = pindah kolom sort mulai dari ASC lagi.
                                --}}
                                <th class="py-2.5 px-3 w-[16%] 2xl:w-[14%] whitespace-nowrap">
                                    <button type="button" @click="sortBy('code')" class="inline-flex items-center gap-1 cursor-pointer hover:text-text-main">
                                        Ticket ID &amp; Time
                                        <svg class="h-3 w-3 shrink-0 transition-transform" :class="sortField === 'code' && sortDir === 'desc' ? 'rotate-180' : ''" :style="sortField === 'code' ? '' : 'opacity:.35'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                </th>
                                <th class="py-2.5 px-3 w-[14%] 2xl:w-[12%] whitespace-nowrap">
                                    <button type="button" @click="sortBy('issue_category')" class="inline-flex items-center gap-1 cursor-pointer hover:text-text-main">
                                        Status / Issue
                                        <svg class="h-3 w-3 shrink-0 transition-transform" :class="sortField === 'issue_category' && sortDir === 'desc' ? 'rotate-180' : ''" :style="sortField === 'issue_category' ? '' : 'opacity:.35'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                </th>
                                <th class="py-2.5 px-3 w-[20%] 2xl:w-[18%]">Pelanggan (CID &amp; Contact)</th>
                                <th class="py-2.5 px-3 hidden 2xl:table-cell 2xl:w-[15%]">
                                    <button type="button" @click="sortBy('odp')" class="inline-flex items-center gap-1 cursor-pointer hover:text-text-main">
                                        Lokasi / POP / ODP
                                        <svg class="h-3 w-3 shrink-0 transition-transform" :class="sortField === 'odp' && sortDir === 'desc' ? 'rotate-180' : ''" :style="sortField === 'odp' ? '' : 'opacity:.35'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                </th>
                                <th class="py-2.5 px-3 w-[24%] 2xl:w-[22%]">Keluhan (Detail)</th>
                                <th class="py-2.5 px-3 text-right w-[26%] 2xl:w-[19%] whitespace-nowrap">Quick Dispatch Actions</th>
                            </tr>
                        </thead>
                        {{--
                            `<tbody>` per tiket (bukan `<tr>` langsung) — biar
                            tiket batch bisa nyisipin baris child tambahan
                            (revisi poin 4) TANPA melanggar batasan Alpine
                            x-for (template cuma boleh punya SATU root
                            element). Beberapa `<tbody>` bersaudara langsung di
                            bawah `<table>` itu valid HTML.
                        --}}
                        <template x-for="task in sortedTasks" :key="task.id">
                        <tbody class="divide-y divide-border">
                            <tr class="hover:bg-surface-muted/60 dark:hover:bg-slate-800/40 transition-colors align-top group"
                                :data-ticket-row="task.id"
                                :class="task.id === focusedTicketId ? 'bg-sky-50/60 dark:bg-sky-950/30 ring-1 ring-inset ring-sky-400/60' : ''">

                                    {{-- Ticket ID & Time --}}
                                    <td class="py-2.5 px-3">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            {{--
                                                Detail buka DRAWER kanan, bukan halaman
                                                /tickets/{id}: worksheet ini halaman kerja —
                                                keluar halaman berarti kehilangan form yang
                                                sedang diisi, filter tab, & posisi scroll.
                                                Navigasi halaman penuh disisakan buat halaman
                                                arsip (Ticket Selesai/Dibatalkan/History).
                                            --}}
                                            <button type="button" @click="openTicketDetail(task.id)"
                                                    class="font-mono font-extrabold text-sky-600 dark:text-sky-400 hover:underline truncate cursor-pointer text-left" x-text="task.code"></button>
                                            <span class="shrink-0 px-1.5 py-px rounded text-[9px] font-extrabold uppercase" :class="priorityBadgeClass(task.priority)" x-text="task.priority"></span>
                                        </div>
                                        {{--
                                            Target SLA — label statis dari payload
                                            (worksheetCardPayload), bukan countdown
                                            live per detik (worksheet refresh ngikut
                                            broadcast/reload, cukup). Kosong kalau
                                            tiket lama sebelum kolom SLA ada.
                                            Lihat docs/plan/analisa-target-sla-ticketing.md.
                                        --}}
                                        <div class="mt-0.5" x-show="task.sla_label">
                                            <span class="inline-block px-1.5 py-px rounded text-[9px] font-bold border" :class="task.sla_badge_class" x-text="task.sla_label"></span>
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-1 text-[10px] text-text-muted font-mono min-w-0">
                                            <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="9"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"></path>
                                            </svg>
                                            <span class="truncate" x-text="task.time + ' (' + task.time_at + ')'"></span>
                                        </div>
                                    </td>

                                    {{-- Status / Issue --}}
                                    <td class="py-2.5 px-3">
                                        <span class="block truncate px-2 py-0.5 rounded text-[10px] font-bold" :class="issueBadgeClass(task.issue_category)"
                                              :title="task.issue_category" x-text="task.issue_category || 'Tanpa Kategori'"></span>
                                        <div class="mt-1 flex items-center gap-1 text-[10px] text-text-muted min-w-0">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="bucketDotClass(task.bucket)"></span>
                                            <span class="truncate" x-text="task.status_label"></span>
                                        </div>
                                    </td>

                                    {{-- Pelanggan --}}
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-text-main truncate" :title="task.customer_name" x-text="task.customer_name"></div>
                                        <template x-if="!task.is_batch">
                                            <div class="mt-0.5 flex items-center gap-1.5 text-[11px] font-mono text-text-muted min-w-0">
                                                <span class="font-bold text-text-secondary truncate" x-text="task.cid"></span>
                                                <span class="shrink-0">•</span>
                                                <a :href="'https://wa.me/' + task.customer_phone" target="_blank" rel="noopener"
                                                   class="text-emerald-600 dark:text-emerald-400 hover:underline truncate" x-text="task.customer_phone"></a>
                                            </div>
                                        </template>
                                        <template x-if="task.is_batch">
                                            <div class="mt-0.5 flex items-center gap-1.5 text-[10px] min-w-0">
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded font-bold font-mono bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 border border-violet-300 dark:border-violet-800">
                                                    ⚡ BATCH
                                                </span>
                                                <span class="text-violet-600 dark:text-violet-400 font-bold font-mono" x-text="(task.batch_members ? task.batch_members.length : 0) + ' Pelanggan'"></span>
                                            </div>
                                        </template>
                                        {{-- Pengganti kolom Lokasi waktu kolomnya disembunyiin (< 2xl). --}}
                                        <div class="2xl:hidden mt-0.5 text-[10px] text-text-muted truncate" :title="task.pop + ' / ' + task.odp + ' — ' + task.address"
                                             x-text="task.pop + ' / ' + task.odp"></div>
                                    </td>

                                    {{-- Lokasi / POP / ODP --}}
                                    <td class="py-2.5 px-3 text-[11px] hidden 2xl:table-cell">
                                        <div class="font-semibold text-text-secondary truncate" :title="task.pop + ' / ' + task.odp">
                                            <span x-text="task.pop"></span>
                                            <span class="text-text-muted font-normal" x-text="' / ' + task.odp"></span>
                                        </div>
                                        <div class="text-[10px] text-text-muted truncate" :title="task.address" x-text="task.address"></div>
                                    </td>

                                    {{-- Keluhan --}}
                                    <td class="py-2.5 px-3">
                                        <p class="text-[11px] text-text-secondary line-clamp-2 leading-snug" :title="task.desc" x-text="task.desc"></p>
                                        <div class="flex items-center gap-2 flex-wrap text-[10px] text-text-muted mt-0.5"
                                             x-show="task.escalated_noc_by || task.escalated_fop_by || task.returned_to_helpdesk_by || task.closed_by">
                                            <span x-show="task.escalated_noc_by">→ NOC: <span class="font-semibold text-text-secondary" x-text="task.escalated_noc_by"></span></span>
                                            <span x-show="task.escalated_fop_by">→ FOP: <span class="font-semibold text-text-secondary" x-text="task.escalated_fop_by"></span></span>
                                            <span x-show="task.returned_to_helpdesk_by">↩ Helpdesk: <span class="font-semibold text-text-secondary" x-text="task.returned_to_helpdesk_by"></span></span>
                                        </div>
                                    </td>

                                    {{--
                                        Quick Dispatch Actions — `flex-wrap`:
                                        kolomnya lebar tetap (17%), jadi tombol
                                        turun baris waktu sempit, BUKAN nembus
                                        keluar tabel.
                                    --}}
                                    <td class="py-2.5 px-3">
                                        <div class="flex items-center justify-end gap-1 sm:gap-1.5 flex-nowrap">
                                            <button type="button" x-show="task.actions?.can_close" :disabled="actionLoadingId === task.id"
                                                    @click="closeTicket(task)"
                                                    class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 cursor-pointer shrink-0 shadow-2xs">
                                                Selesai
                                            </button>
                                            <button type="button" x-show="task.actions?.can_escalate_noc" :disabled="actionLoadingId === task.id"
                                                    @click="escalateTicket(task, 'noc')"
                                                    class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-amber-600 text-white hover:bg-amber-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 cursor-pointer shrink-0 shadow-2xs">
                                                Ke NOC
                                            </button>
                                            <button type="button" x-show="task.actions?.can_escalate_fop" :disabled="actionLoadingId === task.id"
                                                    @click="escalateTicket(task, 'fop')"
                                                    class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-sky-600 text-white hover:bg-sky-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 cursor-pointer shrink-0 shadow-2xs">
                                                Ke FOP
                                            </button>
                                            <button type="button" x-show="task.actions?.can_return_to_helpdesk" :disabled="actionLoadingId === task.id"
                                                    @click="returnTicketToHelpdesk(task)"
                                                    class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-slate-600 text-white hover:bg-slate-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 cursor-pointer shrink-0 shadow-2xs">
                                                Kembalikan
                                            </button>
                                            {{--
                                                Tambah pelanggan terdampak — cuma tiket
                                                batch (revisi poin 4). Expand/collapse
                                                dulu (nampilin child yang udah ada),
                                                bukan langsung buka modal — biar staf
                                                lihat daftar existing sebelum nambah lagi.
                                            --}}
                                            <button type="button" x-show="task.is_batch"
                                                    @click="toggleBatchExpand(task)"
                                                    class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-violet-600 text-white hover:bg-violet-700 active:scale-95 transition-all duration-200 cursor-pointer shrink-0 shadow-2xs inline-flex items-center gap-1">
                                                <svg class="h-3 w-3 shrink-0 transition-transform" :class="expandedBatchTicketId === task.id ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                                </svg>
                                                Tambah
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                {{--
                                    Baris CHILD — pelanggan terdampak tiket batch (1-Baris List Format).
                                --}}
                                <template x-if="task.is_batch && expandedBatchTicketId === task.id">
                                    <tr class="bg-violet-50/50 dark:bg-violet-950/20 border-b border-violet-200/60 dark:border-violet-900/40">
                                        <td colspan="6" class="px-4 py-3">
                                            <div class="pl-3 border-l-2 border-violet-500 dark:border-violet-400 space-y-2.5">
                                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-[11px] font-black uppercase tracking-wider text-violet-700 dark:text-violet-300 flex items-center gap-1.5">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse"></span>
                                                            Pelanggan Terdampak
                                                        </span>
                                                        <span class="px-2 py-0.2 rounded-full text-[10px] font-bold font-mono bg-violet-100 dark:bg-violet-900/70 text-violet-700 dark:text-violet-300 border border-violet-300 dark:border-violet-700"
                                                              x-text="(task.batch_members ? task.batch_members.length : 0) + ' Pelanggan'"></span>
                                                    </div>
                                                    <button type="button" @click="openBatchModal(task)"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-violet-600 text-white hover:bg-violet-700 active:scale-95 transition-all cursor-pointer shadow-2xs">
                                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                                        + Tambah Pelanggan
                                                    </button>
                                                </div>

                                                {{-- 1-Baris List Roster Pelanggan Terdampak (CID, Nama, No HP / WhatsApp) --}}
                                                <div class="space-y-1.5 pt-0.5" x-show="task.batch_members && task.batch_members.length > 0">
                                                    <template x-for="(m, idx) in task.batch_members" :key="m.id">
                                                        <div class="p-2 px-3 rounded-xl border border-border bg-surface hover:bg-violet-50/40 dark:hover:bg-violet-950/30 transition-colors flex items-center justify-between gap-3 shadow-2xs">
                                                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                                                <span class="text-[10px] font-mono font-bold text-text-muted w-4 text-center shrink-0" x-text="idx + 1"></span>
                                                                
                                                                {{-- CID Badge --}}
                                                                <span class="font-mono text-[10px] font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded-md border border-sky-200 dark:border-sky-900 shrink-0 select-all"
                                                                      x-text="m.cid || '—'"></span>

                                                                {{-- Nama Pelanggan --}}
                                                                <span class="font-bold text-text-main text-xs truncate" :title="m.customer_name" x-text="m.customer_name"></span>

                                                                {{-- Detail Tambahan (Paket & Lokasi jika ada) --}}
                                                                <span x-show="m.package && m.package !== '—'" class="text-[10px] text-text-muted hidden md:inline truncate" x-text="'• ' + m.package"></span>
                                                                <span x-show="m.address && m.address !== '—'" class="text-[10px] text-text-muted hidden lg:inline truncate max-w-xs" :title="m.address" x-text="'• ' + m.address"></span>
                                                            </div>

                                                            {{-- No. HP / WhatsApp (1-Baris WA button) --}}
                                                            <div class="shrink-0 flex items-center gap-2">
                                                                <template x-if="m.phone && m.phone !== '—'">
                                                                    <a :href="'https://wa.me/' + m.phone" target="_blank" rel="noopener"
                                                                       class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800/60 transition-all text-xs font-mono font-bold shadow-2xs"
                                                                       :title="'WhatsApp ' + m.phone">
                                                                        <svg class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                                                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/>
                                                                        </svg>
                                                                        <span x-text="m.phone"></span>
                                                                    </a>
                                                                </template>
                                                                <template x-if="!m.phone || m.phone === '—'">
                                                                    <span class="text-text-muted italic text-[11px]">—</span>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                                <p x-show="!task.batch_members || task.batch_members.length === 0" class="text-[11px] text-text-muted italic py-1">
                                                    Belum ada pelanggan terdampak yang dicatat untuk tiket batch ini.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                        </tbody>
                        </template>
                    </table>
                </div>
            </div>

            {{-- ── MODE KARTU ── menyatu dalam satu blok list terpadu (seperti tampilan Desktop) --}}
            <div x-show="activeViewMode === 'cards' && filteredTasks.length > 0" class="border border-border bg-surface shadow-xs divide-y divide-border overflow-hidden">
                <template x-for="(task, index) in sortedTasks" :key="task.id">
                    {{--
                        Tepi kiri kartu diwarnai per prioritas (border-l-4).
                        Menyatu dalam satu kontainer list terpadu dengan divider tipis antar baris, persis seperti baris pada tampilan desktop.
                    --}}
                    <div class="ticket-card flex flex-col 2xl:flex-row items-stretch 2xl:items-center justify-between gap-2.5 p-3 2xl:py-2.5 border-l-4 bg-surface hover:bg-surface-muted/60 dark:hover:bg-slate-800/40 transition-colors duration-150 group relative z-0"
                         :data-ticket-row="task.id"
                         :class="{
                             'border-l-rose-500': task.priority === 'Urgent',
                             'border-l-amber-500': task.priority === 'High',
                             'border-l-slate-300 dark:border-l-slate-700': task.priority !== 'Urgent' && task.priority !== 'High',
                             'ring-1 ring-inset ring-sky-400/60 bg-sky-50/70 dark:bg-sky-950/30 z-10': task.id === focusedTicketId,
                         }"
                         :style="`animation-delay:${Math.min(index, 8) * 30}ms`">
                        
                        {{-- Core Content Link --}}
                        {{--
                            TIGA REZIM, karena lebar area konten beda jauh
                            (sidebar app 256px static dari md, plus panel form):

                              < md   → 1 kolom, semuanya bertumpuk
                              md–2xl → GRID 2 kolom: baris identitas tiket
                                       ngambil lebar penuh, di bawahnya
                                       "pelanggan | keluhan" berdampingan.
                                       Ini yang benerin kartu jangkung &
                                       berantakan di 768–1024.
                              ≥ 2xl  → balik ke satu baris flex (4 kolom)
                        --}}
                        {{-- Klik kartu → drawer detail (bukan pindah halaman, lihat catatan di mode tabel) --}}
                        <div @click="openTicketDetail(task.id)" role="button" tabindex="0"
                             @keydown.enter="openTicketDetail(task.id)"
                             class="flex-1 min-w-0 cursor-pointer ticket-card-inner grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2 2xl:flex 2xl:flex-row 2xl:items-center 2xl:gap-2.5">

                            {{-- Col 1: Kode Tiket, Prioritas, & Status Bucket --}}
                            {{--
                                Lebar minimum kolom cuma dipasang dari 2xl (waktu
                                kartu jadi satu baris). Di bawah itu kartu numpuk
                                vertikal — min-width tetap malah bikin badge
                                nembus tepi kartu di layar sempit.
                            --}}
                            <div class="ticket-card-col1 flex items-center gap-1.5 flex-wrap min-w-0 md:col-span-2 md:pb-2 md:border-b md:border-border/70 2xl:col-span-1 2xl:pb-0 2xl:border-b-0 2xl:shrink-0 2xl:min-w-[210px]">
                                <span class="text-xs font-bold font-mono text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800/60 px-2 py-0.5 rounded shadow-2xs" x-text="task.code"></span>
                                
                                <span class="px-1.5 py-0.5 text-[9px] font-bold rounded uppercase shrink-0"
                                    :class="{
                                        'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800': task.priority === 'Urgent',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800': task.priority === 'High',
                                        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700': task.priority === 'Medium' || task.priority === 'low'
                                    }"
                                    x-text="task.priority">
                                </span>

                                {{-- Target SLA — lihat catatan di mode tabel di atas. --}}
                                <span class="px-1.5 py-0.5 text-[9px] font-bold rounded border shrink-0" x-show="task.sla_label" :class="task.sla_badge_class" x-text="task.sla_label"></span>

                                <span class="font-semibold text-[10px] flex items-center gap-1 shrink-0 px-2 py-0.5 rounded border"
                                    :class="{
                                        'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800': task.bucket === 'diproses',
                                        'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40 border-sky-200 dark:border-sky-800': task.bucket === 'masuk',
                                        'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800': task.bucket === 'selesai',
                                        'text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700': task.bucket === 'dibatalkan'
                                    }">
                                    <span class="w-1.5 h-1.5 rounded-full animate-pulse"
                                        :class="{
                                            'bg-amber-500': task.bucket === 'diproses',
                                            'bg-sky-500': task.bucket === 'masuk',
                                            'bg-emerald-500': task.bucket === 'selesai',
                                            'bg-slate-400': task.bucket === 'dibatalkan'
                                        }">
                                    </span>
                                    <span x-text="bucketLabel(task.bucket)"></span>
                                </span>

                                {{--
                                    Waktu nempel di baris pertama (kanan) buat
                                    < 2xl. Dulu ikut baris tombol aksi — tiket
                                    yang gak punya aksi (udah di tangan FOP)
                                    jadi kehilangan timestamp sama sekali.
                                --}}
                                <span class="2xl:hidden ml-auto shrink-0 flex items-center gap-1 text-[10px] text-text-muted font-mono">
                                    <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="9"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"></path>
                                    </svg>
                                    <span x-text="task.time"></span>
                                </span>
                            </div>

                            {{-- Col 2: Pelanggan, CID & Telepon --}}
                            <div class="min-w-0 2xl:w-auto 2xl:min-w-[190px] 2xl:max-w-[240px] 2xl:shrink-0">
                                <div class="text-xs font-bold text-text-main group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors truncate" x-text="task.customer_name"></div>
                                <template x-if="!task.is_batch">
                                    <div class="flex items-center gap-2 text-[11px] text-text-muted mt-0.5 flex-wrap">
                                        <span class="font-mono text-sky-600 dark:text-sky-400 font-semibold" x-text="'CID: ' + task.cid"></span>
                                        <span class="text-text-muted">•</span>
                                        <span class="flex items-center gap-1 font-mono text-[10px]">
                                            <svg class="h-3 w-3 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            <span x-text="task.customer_phone"></span>
                                        </span>
                                    </div>
                                </template>
                                <template x-if="task.is_batch">
                                    <div class="flex items-center gap-1.5 text-[10px] text-violet-600 dark:text-violet-400 font-bold font-mono mt-0.5">
                                        <span x-text="(task.batch_members ? task.batch_members.length : 0) + ' Pelanggan Terdampak'"></span>
                                    </div>
                                </template>
                                {{--
                                    Lokasi cuma ada di kartu < 2xl: di 2xl kartu
                                    balik jadi satu baris dan ruangnya dipakai
                                    kolom keluhan.
                                --}}
                                <div class="2xl:hidden mt-0.5 flex items-center gap-1 text-[10px] text-text-muted min-w-0">
                                    <svg class="h-3 w-3 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="truncate" :title="task.address" x-text="task.pop + ' / ' + task.odp + ' — ' + task.address"></span>
                                </div>
                            </div>

                            {{-- Col 3: Judul Issue, Keluhan Ringkas & Atribusi Escalation --}}
                            {{--
                                Garis pemisah cuma di rezim grid (md–2xl):
                                nandain batas "identitas pelanggan | masalahnya".
                                Di satu baris (2xl) jaraknya udah jelas, jadi
                                garisnya dimatikan biar gak nambah noise.
                            --}}
                            <div class="ticket-card-col3 min-w-0 pr-1 md:border-l md:border-border/70 md:pl-4 2xl:w-auto 2xl:flex-1 2xl:border-l-0 2xl:pl-0">
                                {{--
                                    `title` di payload = nama kategori issue kalau
                                    kategorinya keisi (lihat worksheetCardPayload()),
                                    jadi badge + judul sering PERSIS SAMA — itu
                                    yang bikin "Backbone CUT" kebaca dobel di
                                    kartu. Judul cuma dirender kalau beda dari
                                    badge kategori.
                                --}}
                                <div class="flex items-center gap-2 min-w-0">
                                    <template x-if="task.issue_category">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-surface-muted dark:bg-slate-800 text-text-secondary border border-border shrink-0 truncate max-w-[60%]" x-text="task.issue_category"></span>
                                    </template>
                                    <template x-if="task.is_batch">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 border border-violet-300 dark:border-violet-800 shrink-0 inline-flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse"></span>
                                            <span x-text="'BATCH (' + (task.batch_members ? task.batch_members.length : 0) + ')'"></span>
                                        </span>
                                    </template>
                                    <h4 x-show="task.title && task.title !== task.issue_category"
                                        class="text-xs font-bold text-text-main truncate" x-text="task.title"></h4>
                                </div>
                                {{-- 2 baris di kartu bertumpuk, 1 baris waktu kartu jadi satu baris (2xl). --}}
                                <p class="text-[11px] text-text-muted mt-0.5 italic leading-snug line-clamp-2 2xl:line-clamp-1" x-text="task.desc" :title="task.desc"></p>
                                
                                {{-- Atribusi Escalation --}}
                                <div class="flex items-center gap-2 flex-wrap text-[10px] text-text-muted font-medium mt-0.5" x-show="task.escalated_noc_by || task.escalated_fop_by || task.returned_to_helpdesk_by || task.closed_by">
                                    <span x-show="task.escalated_noc_by">→ NOC: <span class="font-semibold text-text-secondary" x-text="task.escalated_noc_by"></span></span>
                                    <span x-show="task.escalated_fop_by">→ FOP: <span class="font-semibold text-text-secondary" x-text="task.escalated_fop_by"></span></span>
                                    <span x-show="task.returned_to_helpdesk_by">↩ Ret Helpdesk: <span class="font-semibold text-text-secondary" x-text="task.returned_to_helpdesk_by"></span></span>
                                    <span x-show="task.closed_by">✓ Selesai: <span class="font-semibold text-emerald-600 dark:text-emerald-400" x-text="task.closed_by"></span></span>
                                </div>
                            </div>

                            {{-- Col 4: Waktu / Diff time (tampil di kanan khusus 2xl) --}}
                            <div class="hidden 2xl:block shrink-0 text-right min-w-[85px]">
                                <span class="text-[10px] text-text-muted font-mono flex items-center justify-end gap-1">
                                    <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="9"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"></path>
                                    </svg>
                                    <span x-text="task.time"></span>
                                </span>
                            </div>
                        </div>

                        {{-- Action Buttons & Timestamp Row (< 2xl) --}}
                        <div x-show="(task.actions && (task.actions.can_close || task.actions.can_escalate_noc || task.actions.can_escalate_fop || task.actions.can_return_to_helpdesk)) || task.is_batch"
                             class="pt-2 2xl:pt-0 border-t 2xl:border-t-0 border-border/60 flex items-center justify-end gap-2 w-full 2xl:w-auto">
                            
                            {{-- Action Buttons Group --}}
                            <div class="flex items-center gap-1.5 w-full sm:w-auto justify-stretch sm:justify-end flex-wrap">
                                <button type="button" x-show="task.is_batch"
                                        @click="toggleBatchExpand(task)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-violet-600 text-white hover:bg-violet-700 active:scale-95 transition-all duration-200 cursor-pointer shadow-2xs shrink-0">
                                    <svg class="h-3 w-3 shrink-0 transition-transform" :class="expandedBatchTicketId === task.id ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                    </svg>
                                    <span x-text="expandedBatchTicketId === task.id ? 'Tutup' : 'Pelanggan (' + (task.batch_members ? task.batch_members.length : 0) + ')'"></span>
                                </button>
                                <button type="button" x-show="task.actions?.can_close" :disabled="actionLoadingId === task.id"
                                        @click="closeTicket(task)"
                                        class="flex-1 sm:flex-initial text-center justify-center inline-flex items-center px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 transition-all duration-200 cursor-pointer shadow-2xs shrink-0">
                                    Selesai
                                </button>
                                <button type="button" x-show="task.actions?.can_escalate_noc" :disabled="actionLoadingId === task.id"
                                        @click="escalateTicket(task, 'noc')"
                                        class="flex-1 sm:flex-initial text-center justify-center inline-flex items-center px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-amber-600 text-white hover:bg-amber-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 transition-all duration-200 cursor-pointer shadow-2xs shrink-0">
                                    Ke NOC
                                </button>
                                <button type="button" x-show="task.actions?.can_escalate_fop" :disabled="actionLoadingId === task.id"
                                        @click="escalateTicket(task, 'fop')"
                                        class="flex-1 sm:flex-initial text-center justify-center inline-flex items-center px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-sky-600 text-white hover:bg-sky-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 transition-all duration-200 cursor-pointer shadow-2xs shrink-0">
                                    Ke FOP
                                </button>
                                <button type="button" x-show="task.actions?.can_return_to_helpdesk" :disabled="actionLoadingId === task.id"
                                        @click="returnTicketToHelpdesk(task)"
                                        class="flex-1 sm:flex-initial text-center justify-center inline-flex items-center px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-slate-600 text-white hover:bg-slate-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 transition-all duration-200 cursor-pointer shadow-2xs shrink-0">
                                    Kembalikan
                                </button>
                            </div>
                        </div>

                        {{-- Expanded Batch Members Container for Card View Mode (1-Baris List) --}}
                        <div x-show="task.is_batch && expandedBatchTicketId === task.id" x-cloak
                             class="w-full pt-3 mt-2 border-t border-violet-200/80 dark:border-violet-900/50 bg-violet-50/40 dark:bg-violet-950/20 -mx-3 -mb-3 p-3 space-y-2.5">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <span class="text-[11px] font-black uppercase tracking-wider text-violet-700 dark:text-violet-300 flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse"></span>
                                    Daftar Pelanggan Terdampak (<span x-text="task.batch_members ? task.batch_members.length : 0"></span>)
                                </span>
                                <button type="button" @click="openBatchModal(task)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-violet-600 text-white hover:bg-violet-700 active:scale-95 transition-all cursor-pointer shadow-2xs">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    + Tambah Pelanggan
                                </button>
                            </div>
                            <div class="space-y-1.5 pt-0.5" x-show="task.batch_members && task.batch_members.length > 0">
                                <template x-for="(m, idx) in task.batch_members" :key="m.id">
                                    <div class="p-2 px-3 rounded-xl border border-border bg-surface hover:bg-violet-50/40 dark:hover:bg-violet-950/30 transition-colors flex items-center justify-between gap-3 shadow-2xs">
                                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                            <span class="text-[10px] font-mono font-bold text-text-muted w-4 text-center shrink-0" x-text="idx + 1"></span>
                                            
                                            {{-- CID Badge --}}
                                            <span class="font-mono text-[10px] font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded-md border border-sky-200 dark:border-sky-900 shrink-0 select-all"
                                                  x-text="m.cid || '—'"></span>

                                            {{-- Nama Pelanggan --}}
                                            <span class="font-bold text-text-main text-xs truncate" :title="m.customer_name" x-text="m.customer_name"></span>

                                            {{-- Detail Tambahan (Paket & Lokasi jika ada) --}}
                                            <span x-show="m.package && m.package !== '—'" class="text-[10px] text-text-muted hidden md:inline truncate" x-text="'• ' + m.package"></span>
                                            <span x-show="m.address && m.address !== '—'" class="text-[10px] text-text-muted hidden lg:inline truncate max-w-xs" :title="m.address" x-text="'• ' + m.address"></span>
                                        </div>

                                        {{-- No. HP / WhatsApp (1-Baris WA button) --}}
                                        <div class="shrink-0 flex items-center gap-2">
                                            <template x-if="m.phone && m.phone !== '—'">
                                                <a :href="'https://wa.me/' + m.phone" target="_blank" rel="noopener"
                                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800/60 transition-all text-xs font-mono font-bold shadow-2xs"
                                                   :title="'WhatsApp ' + m.phone">
                                                    <svg class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/>
                                                    </svg>
                                                    <span x-text="m.phone"></span>
                                                </a>
                                            </template>
                                            <template x-if="!m.phone || m.phone === '—'">
                                                <span class="text-text-muted italic text-[11px]">—</span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p x-show="!task.batch_members || task.batch_members.length === 0" class="text-[11px] text-text-muted italic py-1">
                                Belum ada pelanggan terdampak yang dicatat untuk tiket batch ini.
                            </p>
                        </div>
                    </div>
                </template>
            </div>

            {{--
                Gap #4 (docs/plan/analisa-efektivitas-worksheet-ticketing.md)
                — panel cuma nampilin WORKSHEET_DISPLAY_LIMIT (30) tiket
                terbaru. Sebelumnya sisanya diem-diem ilang tanpa indikator.
                worksheetTotalCount dari server (activeForWorksheet()->count())
                gak kena limit, jadi bisa dibandingin buat tau ada berapa lagi.
            --}}
            <template x-if="worksheetTotalCount > tasks.length">
                <p class="block text-center text-xs font-semibold text-text-muted py-3 border-t border-border/60 mt-3">
                    <span x-text="'+ ' + (worksheetTotalCount - tasks.length) + ' tiket aktif lainnya (di luar 30 terbaru)'"></span>
                </p>
            </template>

            {{-- Panel ini cuma antrean kerja; tiket final punya halaman sendiri. --}}
            <p class="mt-3 text-[10px] text-text-muted italic">
                Menampilkan <span x-text="filteredTasks.length"></span> tiket.
                Selesai &amp; Dibatalkan gak masuk antrean kerja — ada di halaman sendiri.
            </p>
        </div>
    </div>
    </div>
</div>
@include('tickets.partials.action-dialog')
{{--
    Drawer detail kanan — partial BERSAMA dengan Worksheet NOC. Dirender di luar
    kontainer `overflow-hidden` di atas biar panelnya gak kepotong.
--}}
@include('tickets.partials.detail-drawer')
@endsection

@push('scripts')
<script>
    function ticketPage() {
        return {
            // Form state
            cidQuery: '',
            results: [],
            selected: null,
            searching: false,
            searched: false,
            ticketType: 'MTN',
            priority: 'Medium',
            issueCategoryId: '',
            detailKeluhan: '',
            catatanTeknis: '',
            // Revisi Worksheet Helpdesk poin 1-3: kategori ber-checklist Batch
            // (mis. "ODP LOS") bikin Search Customer Data jadi label bebas
            // (bukan wajib match CID) + POP dipilih manual + No. HP Pelapor
            // opsional. `popId`/`reporterPhone` cuma dipakai dua mode itu
            // kalau relevan — lihat selectedCategoryIsBatch & submitForm().
            popId: '',
            popPickerOpen: false,
            popSearchQuery: '',
            popTypeFilter: 'all',
            popHighlightedIndex: 0,
            reporterPhone: '',
            // Daftar POP buat dropdown manual — {id, name}, disuplai server
            // (Pop::forUser()), beda dari allowedPopIds yang cuma ID buat
            // subscribe channel realtime.
            allowedPops: @json($allowedPops),
            attachments: [],
            showExtra: false,
            toast: { show: false, type: 'success', message: '' },
            errors: {},
            submitting: false,
            taskFilter: 'helpdesk',
            actionLoadingId: null,
            refreshing: false,

            // Sort kolom panel kanan (docs/plan/analisa-percepatan-alur-helpdesk-noc.md
            // §6.1) — clientside di atas `filteredTasks`, null = urutan asli dari
            // server (priority/SLA dulu, lihat worksheetTasks()). 'code' = Ticket ID
            // & Time, 'issue_category' = Status/Issue, 'odp' = Lokasi/POP/ODP.
            // Pelanggan sengaja gak sortable (keputusan user).
            sortField: null,
            sortDir: 'asc',

            // Row yang lagi "fokus" keyboard (§6.3/§6.4) — disimpan sebagai id,
            // BUKAN index array, biar gak "loncat" kalau ada tiket baru nyempil
            // lewat broadcast realtime di tengah user navigasi Arrow Up/Down.
            focusedTicketId: null,

            // Arrow/C/V/B row-navigasi WAJIB nonaktif selagi drawer detail kebuka
            // (lihat komentar di listener open-ticket-drawer/close-ticket-drawer
            // di root elemen) — di-toggle lewat event, bukan baca DOM drawer.
            drawerOpen: false,

            // Batch (revisi Worksheet Helpdesk poin 4) — id tiket yang lagi
            // expand baris child-nya di List Task (null = semua collapsed).
            expandedBatchTicketId: null,

            // Modal "Tambah Pelanggan Terdampak" — SENGAJA modal + submit AJAX
            // (bukan halaman create terpisah, pola default aksi mutasi data),
            // override sadar: validasi gagal balik JSON langsung dibaca modal
            // (TicketController::storeBatchMember()), gak numpang
            // back()->withErrors() yang bisa nutup modal & nampilin List
            // kosong tanpa pesan (ADHOC-20) — makanya aman dipakai di sini.
            batchModal: {
                open: false,
                ticket: null,
                cidQuery: '',
                results: [],
                selected: null,
                customerName: '',
                phone: '',
                submitting: false,
                error: null,
            },

            // Panel kanan: tabel (default) atau kartu. Disimpan di localStorage
            // sama kayak formOpen — dibaca sebelum render pertama biar gak
            // kedip ganti mode pas halaman dimuat.
            viewMode: localStorage.getItem('ticket-view-mode') === 'cards' ? 'cards' : 'table',

            // Filter prioritas panel kanan — client-side atas array `tasks`.
            // Nilainya WAJIB sama persis App\Enums\FopTaskPriority (perhatikan
            // 'low' huruf kecil, sisanya kapital — itu memang value enumnya).
            filterPriority: 'ALL',

            // Input pencarian cepat panel kanan (nomor tiket, nama, CID, desa, keluhan)
            searchQuery: '',

            // Layar sempit (< lg) — dipaksa mode kartu lewat activeViewMode.
            // Ambangnya lg, bukan md: sidebar app 256px static dari md, jadi di
            // tablet 768px area kontennya cuma ~512px — tabel 5 kolom pun jadi
            // scroll horizontal terus. Pilihan `viewMode` user TETAP disimpan
            // apa adanya, biar balik sendiri begitu layarnya lebar lagi.
            narrowViewport: window.matchMedia('(max-width: 1023px)').matches,

            get activeViewMode() {
                return this.narrowViewport ? 'cards' : this.viewMode;
            },

            // Label tab = "di tangan siapa" (TicketHandler), bukan bucket.
            // shortLabel dipakai di layar sempit — "Assign NOC" kepanjangan
            // buat tab sepertiga lebar layar HP.
            tabs: [
                { value: 'helpdesk', label: 'Ticket', shortLabel: 'Ticket', badgeClass: 'bg-slate-700 dark:bg-slate-600' },
                { value: 'noc', label: 'Assign NOC', shortLabel: 'NOC', badgeClass: 'bg-amber-600' },
                { value: 'fop', label: 'Assign FOP', shortLabel: 'FOP', badgeClass: 'bg-sky-600' },
            ],

            // Panel form kebuka/kelipat — dibaca SEBELUM render pertama
            // (bukan di init()) supaya gak ada kedipan panel kebuka lalu
            // langsung nutup pas halaman dimuat dalam kondisi terlipat.
            formOpen: localStorage.getItem('ticket-form-open') !== 'false',

            // Transisi lebar panel baru diaktifkan setelah frame pertama.
            // Tanpa ini, halaman yang dimuat dalam kondisi terlipat bakal
            // "menganimasikan" dirinya sendiri dari 0 pas load — kelihatan
            // seperti panel mental sendiri.
            animReady: false,

            // Master Issue — dari Master Data sungguhan (Task 1 rancangan), bukan mock lagi.
            issueCategories: @json($issueCategories),

            // Snapshot awal panel kanan — data server-side saat halaman dimuat.
            // Item baru hasil submit di-prepend lokal (optimistik) di submitForm(),
            // update dari aktor lain masuk lewat broadcast Reverb (initEchoListeners()).
            tasks: @json($initialTasks),

            // Total tiket aktif TANPA kena cap panel (Gap #4) — dibandingin sama
            // tasks.length buat nampilin indikator "+N lainnya".
            worksheetTotalCount: {{ $worksheetTotalCount }},

            // POP yang kelihatan user — subscribe Echo.private('tickets.{popId}')
            // per POP ini (Gap #3).
            allowedPopIds: @json($allowedPopIds),

            // Prefill dari scan QR (QrTicketController — Fungsi B,
            // docs/plan/qr-code/rancangan-qr-pelanggan-final.md §6.2). Bentuk
            // objeknya SAMA PERSIS hasil lookup-customer (lihat
            // TicketController::customerPayload()), jadi pick() yang sudah
            // ada bisa langsung dipakai apa adanya — nol logic baru.
            prefillCustomer: @json($prefillCustomer ?? null),

            init() {
                if (this.prefillCustomer) {
                    this.pick(this.prefillCustomer);
                    this.setFormOpen(true);
                }
                this.initEchoListeners();
                const narrow = window.matchMedia('(max-width: 1023px)');
                const updateNarrow = () => { this.narrowViewport = narrow.matches; };
                narrow.addEventListener('change', updateNarrow);
                window.addEventListener('resize', updateNarrow);
                // Dua rAF: satu buat nunggu Alpine selesai render class awal,
                // satu lagi buat memastikan browser sudah melukisnya sebelum
                // properti transition dipasang.
                requestAnimationFrame(() => requestAnimationFrame(() => { this.animReady = true; }));
            },

            setFormOpen(open) {
                this.formOpen = open;
                localStorage.setItem('ticket-form-open', open ? 'true' : 'false');
                // Fokus baru dipindah setelah animasi lebar kelar — fokus ke
                // elemen yang lagi bergerak bikin browser auto-scroll dan
                // gerakannya kelihatan tersendat.
                if (open) {
                    setTimeout(() => this.$refs.searchInput?.focus(), 320);
                }
            },

            /**
             * Auto-refresh (Gap #3) — subscribe channel tickets.{popId} per POP
             * yang kelihatan user, dengerin App\Events\TicketQueueUpdated (broadcast
             * dari TicketService setelah create/close/escalate commit). Retry loop
             * nunggu window.Echo kebentuk sama kayak pola fop/dashboard.blade.php
             * (Echo diinisialisasi script terpisah, kadang belum ready pas Alpine init()).
             */
            initEchoListeners() {
                const popIds = this.allowedPopIds;
                let attempts = 0;
                const setup = () => {
                    if (typeof window.Echo === 'undefined' || !window.Echo) {
                        attempts++;
                        if (attempts < 20) setTimeout(setup, 100);
                        return;
                    }
                    popIds.forEach(popId => {
                        window.Echo.private(`tickets.${popId}`)
                            .listen('.TicketQueueUpdated', () => this.refreshWorksheet());
                    });
                };
                setup();
            },

            async refreshWorksheet() {
                this.refreshing = true;
                try {
                    const res = await fetch('{{ route('tickets.worksheet-tasks') }}', {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) return;
                    const body = await res.json();
                    this.tasks = body.tasks;
                    this.worksheetTotalCount = body.total;
                } catch (e) {
                    // Diam-diam gagal — tombol Refresh manual tetap bisa dicoba lagi.
                } finally {
                    this.refreshing = false;
                }
            },

            get selectedCategorySlaSource() {
                if (!this.issueCategoryId || this.issueCategoryId === 'lainnya') return null;
                return this.issueCategories.find(c => c.id == this.issueCategoryId)?.sla_source || null;
            },

            // Kategori "Batch" (Master Issue, mis. "ODP LOS") — Search Customer
            // Data boleh jadi label bebas + POP dipilih manual (revisi
            // Worksheet Helpdesk poin 1-2). false selama belum pilih kategori
            // atau pilih "Lainnya".
            get selectedCategoryIsBatch() {
                if (!this.issueCategoryId || this.issueCategoryId === 'lainnya') return false;
                return !!this.issueCategories.find(c => c.id == this.issueCategoryId)?.is_batch;
            },

            // ── POP Hierarchical Picker Helpers & Getters ──
            get selectedPop() {
                if (!this.popId) return null;
                return this.allowedPops.find(p => String(p.id) === String(this.popId)) || null;
            },

            // Single unified flat list with headers for high-density, space-efficient rendering
            get displayPopList() {
                const q = (this.popSearchQuery || '').toLowerCase().trim();
                const type = this.popTypeFilter;
                const pops = this.allowedPops || [];

                // Filter / Search mode
                if (q !== '' || type !== 'all') {
                    const filtered = pops.filter(p => {
                        if (type !== 'all' && p.type !== type) return false;
                        if (!q) return true;
                        const name = (p.name || '').toLowerCase();
                        const code = (p.code || '').toLowerCase();
                        const parent = (p.parent_name || '').toLowerCase();
                        const typeLabel = (p.type_label || '').toLowerCase();
                        return name.includes(q) || code.includes(q) || parent.includes(q) || typeLabel.includes(q);
                    });
                    return filtered.map(p => ({
                        ...p,
                        is_header: false,
                        indent: false,
                    }));
                }

                // Default natural hierarchy stream
                const list = [];
                const pusatList = pops.filter(p => p.type === 'pusat');
                const cabangList = pops.filter(p => p.type === 'cabang');
                const standaloneMiniList = pops.filter(p => p.type === 'mini_pop' && (!p.parent_id || !cabangList.some(c => c.id === p.parent_id)));

                if (pusatList.length > 0) {
                    list.push({ is_header: true, header_title: 'Kantor Pusat', id: 'hdr-pusat' });
                    pusatList.forEach(p => list.push({ ...p, is_header: false, indent: false }));
                }

                if (cabangList.length > 0) {
                    list.push({ is_header: true, header_title: 'Cabang & Mini POP', id: 'hdr-cabang' });
                    cabangList.forEach(c => {
                        list.push({ ...c, is_header: false, indent: false, is_cabang: true });
                        const children = pops.filter(p => p.type === 'mini_pop' && p.parent_id === c.id);
                        children.forEach(ch => {
                            list.push({ ...ch, is_header: false, indent: true, is_mini: true });
                        });
                    });
                }

                if (standaloneMiniList.length > 0) {
                    list.push({ is_header: true, header_title: 'Mini POP Mandiri', id: 'hdr-standalone' });
                    standaloneMiniList.forEach(s => list.push({ ...s, is_header: false, indent: false, is_mini: true }));
                }

                return list;
            },

            get selectablePops() {
                return this.displayPopList.filter(item => !item.is_header);
            },

            openPopPicker() {
                this.popPickerOpen = true;
                this.popSearchQuery = '';
                this.popTypeFilter = 'all';
                this.popHighlightedIndex = 0;
                this.$nextTick(() => {
                    this.$refs.popSearchInput?.focus();
                });
            },

            closePopPicker() {
                this.popPickerOpen = false;
            },

            onPopKeydown(e) {
                const items = this.selectablePops;
                if (!items.length) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.popHighlightedIndex = (this.popHighlightedIndex + 1) % items.length;
                    this.scrollToHighlightedPop();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.popHighlightedIndex = (this.popHighlightedIndex - 1 + items.length) % items.length;
                    this.scrollToHighlightedPop();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (items[this.popHighlightedIndex]) {
                        this.selectPop(items[this.popHighlightedIndex]);
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.closePopPicker();
                }
            },

            scrollToHighlightedPop() {
                this.$nextTick(() => {
                    const el = document.getElementById('pop-opt-' + this.popHighlightedIndex);
                    if (el) {
                        el.scrollIntoView({ block: 'nearest' });
                    }
                });
            },

            selectPop(pop) {
                this.popId = pop.id;
                this.closePopPicker();
                if (this.errors.pop_id) delete this.errors.pop_id;
            },

            clearPop() {
                this.popId = '';
                this.openPopPicker();
            },

            // Form boleh disubmit kalau: (a) pelanggan sungguhan terpilih
            // atau (b) label tiket diisi + POP dipilih manual.
            get canSubmit() {
                if (this.selected) return true;
                return this.cidQuery.trim() !== '' && this.popId !== '';
            },

            setViewMode(mode) {
                this.viewMode = mode;
                localStorage.setItem('ticket-view-mode', mode);
            },

            // Jumlah tiket per tab — SENGAJA tanpa filter prioritas, biar badge
            // tetap nunjukin antrean penuh tiap tab walau list sedang disaring.
            get tabCounts() {
                return {
                    helpdesk: this.tasks.filter(t => t.handler === 'helpdesk').length,
                    noc: this.tasks.filter(t => t.handler === 'noc').length,
                    fop: this.tasks.filter(t => t.handler === 'fop').length,
                };
            },

            priorityBadgeClass(priority) {
                return {
                    Urgent: 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300',
                    High: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
                    Medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
                }[priority] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
            },

            // Warna badge kategori issue di-derive dari NAMA kategori (Master
            // Issue bisa nambah kapan aja, jadi gak ada peta warna hardcode
            // lengkap) — kategori yang gak dikenal jatuh ke netral, bukan error.
            issueBadgeClass(category) {
                const name = (category || '').toLowerCase();
                if (name.includes('los') || name.includes('down') || name.includes('mati')) return 'bg-rose-600 text-white';
                if (name.includes('lemot') || name.includes('lambat') || name.includes('latency')) return 'bg-amber-500 text-white';
                if (name.includes('putus') || name.includes('kabel') || name.includes('cut')) return 'bg-purple-600 text-white';
                if (!category) return 'bg-surface-muted dark:bg-slate-800 text-text-secondary border border-border';
                return 'bg-sky-600 text-white';
            },

            bucketDotClass(bucket) {
                return {
                    masuk: 'bg-sky-500',
                    diproses: 'bg-amber-500',
                    selesai: 'bg-emerald-500',
                    dibatalkan: 'bg-slate-400',
                }[bucket] || 'bg-slate-400';
            },

            // Label persis App\Enums\TicketBucket::label() — jangan bikin teks bebas.
            bucketLabel(bucket) {
                return { masuk: 'Ticket Masuk', diproses: 'Ticket di Proses', selesai: 'Ticket Selesai', dibatalkan: 'Ticket Dibatalkan' }[bucket] || bucket;
            },

            // Semua aksi lewat window.confirmTicketAction() — dialog global
            // (components/dialog.blade.php), BUKAN confirm() native. Selain
            // tampilannya seragam sama seluruh app, dialog ini bisa nampung
            // textarea alasan yang kekirim sebagai `reason` ke
            // ticket_histories (lihat tickets/partials/action-dialog.blade.php).
            /**
             * Detail tiket = drawer kanan (tickets/partials/detail-drawer.blade.php),
             * BUKAN navigasi ke /tickets/{id}. Partial-nya yang fetch isinya dari
             * tickets.detail-json; halaman ini cuma ngasih id.
             */
            openTicketDetail(id) {
                // Klik mouse & Enter (row focus keyboard) sama-sama lewat sini —
                // sinkronin focusedTicketId biar highlight row selalu ngikutin
                // detail yang lagi dibuka, gak peduli dibuka lewat mouse atau keyboard.
                this.focusedTicketId = id;
                window.dispatchEvent(new CustomEvent('open-ticket-drawer', { detail: { id } }));
            },

            /**
             * Tombol aksi DI DALAM drawer cuma men-dispatch niat — konfirmasi &
             * POST tetap lewat fungsi di bawah ini, satu jalur dengan tombol di
             * tabel/kartu (biar array `tasks` & counter tetap ke-update sekali).
             */
            handleDrawerAction({ id, action }) {
                const task = this.tasks.find(t => t.id === id);

                if (! task) {
                    return;
                }

                switch (action) {
                    case 'close': this.closeTicket(task); break;
                    case 'noc': this.escalateTicket(task, 'noc'); break;
                    case 'fop': this.escalateTicket(task, 'fop'); break;
                    case 'return': this.returnTicketToHelpdesk(task); break;
                    case 'cancel': this.cancelTicket(task); break;
                }
            },

            closeTicket(task) {
                window.confirmTicketAction({
                    title: 'Selesaikan Tiket',
                    message: `Tandai tiket ${task.code} selesai?`,
                    label: 'Apa yang sudah dikerjakan? (opsional)',
                    required: false,
                    confirmText: 'Ya, Selesaikan',
                    icon: 'success',
                    onConfirm: (reason) => this.performTicketAction(
                        task.id, `{{ url('/tickets') }}/${task.id}/close`, { reason }
                    ),
                });
            },

            escalateTicket(task, target) {
                const label = target === 'noc' ? 'NOC' : 'FOP';

                window.confirmTicketAction({
                    title: `Kirim Tiket ke ${label}`,
                    message: target === 'fop'
                        ? `Kirim tiket ${task.code} ke FOP? Task FOP baru akan dibuat.`
                        : `Kirim tiket ${task.code} ke NOC?`,
                    label: `Catatan buat ${label} (opsional)`,
                    required: false,
                    confirmText: `Ya, Kirim ke ${label}`,
                    onConfirm: (reason) => this.performTicketAction(
                        task.id, `{{ url('/tickets') }}/${task.id}/escalate`, { target, reason }
                    ),
                });
            },

            // Gap #7 — jalur pemulihan kalau NOC salah terima/pencet.
            returnTicketToHelpdesk(task) {
                window.confirmTicketAction({
                    title: 'Kembalikan ke Helpdesk',
                    message: `Kembalikan tiket ${task.code} ke Helpdesk?`,
                    label: 'Alasan dikembalikan (opsional)',
                    required: false,
                    confirmText: 'Ya, Kembalikan',
                    onConfirm: (reason) => this.performTicketAction(
                        task.id, `{{ url('/tickets') }}/${task.id}/return-to-helpdesk`, { reason }
                    ),
                });
            },

            /**
             * Pembatalan pra-FOP — permission `tickets.cancel`, alasan WAJIB
             * (ReasonValidationRule di server). Cuma muncul dari drawer: tombol
             * merah di tabel/kartu bikin aksi destruktif kepencet sambil scroll.
             */
            cancelTicket(task) {
                window.confirmTicketAction({
                    title: 'Batalkan Tiket',
                    message: `Batalkan tiket ${task.code}?`,
                    label: 'Alasan pembatalan (wajib diisi)',
                    required: true,
                    confirmText: 'Ya, Batalkan',
                    confirmType: 'danger',
                    icon: 'error',
                    onConfirm: (reason) => this.performTicketAction(
                        task.id, `{{ url('/tickets') }}/${task.id}/cancel`, { reason }
                    ),
                });
            },

            /**
             * Dipakai closeTicket()/escalateTicket() — POST JSON, lalu update
             * item task.id di array `tasks` in-place dari respons server
             * (worksheetCardPayload() balikin bentuk yang sama persis kayak
             * initial load, biar card gak "lompat" bentuk).
             */
            async performTicketAction(taskId, url, payload) {
                this.actionLoadingId = taskId;
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(payload),
                    });
                    const body = await res.json();

                    if (!res.ok) {
                        this.showToast(body.message || 'Aksi gagal, coba lagi.', 'error');
                        return;
                    }

                    const idx = this.tasks.findIndex(t => t.id === taskId);
                    // Close bikin bucket jadi 'selesai' — keluar dari scope
                    // activeForWorksheet() di server, jadi dihapus juga di sini
                    // (bukan diganti), biar konsisten sama query backend.
                    const stillActive = body.ticket && (body.ticket.bucket === 'masuk' || body.ticket.bucket === 'diproses');
                    if (idx !== -1) {
                        if (stillActive) {
                            this.tasks[idx] = body.ticket;
                        } else {
                            this.tasks.splice(idx, 1);
                            this.worksheetTotalCount = Math.max(0, this.worksheetTotalCount - 1);
                        }
                    }

                    this.showToast(body.message);
                    // Tiket yang baru diaksi udah gak relevan lagi di drawer —
                    // flag aksinya pasti berubah, dan barisnya bisa hilang dari
                    // daftar. Tutup, jangan biarkan nampilin state basi.
                    window.dispatchEvent(new CustomEvent('close-ticket-drawer'));
                } catch (e) {
                    this.showToast('Aksi gagal, coba lagi.', 'error');
                } finally {
                    this.actionLoadingId = null;
                }
            },

            // Filter per TAB = per `handler` (di tangan siapa tiketnya), BUKAN
            // per bucket/status pengerjaan. Juga mendukung filter prioritas & pencarian cepat (searchQuery).
            get filteredTasks() {
                const q = (this.searchQuery || '').toLowerCase().trim();

                return this.tasks.filter(t => {
                    if (t.handler !== this.taskFilter) return false;
                    if (this.filterPriority !== 'ALL' && t.priority !== this.filterPriority) return false;

                    if (q) {
                        const code = (t.code || '').toLowerCase();
                        const name = (t.customer_name || t.customer?.name || '').toLowerCase();
                        const cid = (t.cid || t.customer?.cid || '').toLowerCase();
                        const phone = (t.customer_phone || t.phone || '').toLowerCase();
                        const address = (t.address || t.customer?.village || '').toLowerCase();
                        const pop = (t.pop || '').toLowerCase();
                        const odp = (t.odp || '').toLowerCase();
                        const title = (t.title || '').toLowerCase();
                        const desc = (t.desc || '').toLowerCase();

                        return code.includes(q) || name.includes(q) || cid.includes(q) || phone.includes(q) || address.includes(q) || pop.includes(q) || odp.includes(q) || title.includes(q) || desc.includes(q);
                    }

                    return true;
                });
            },

            /**
             * `filteredTasks` + sort manual (§6.1) — dipisah dari filteredTasks
             * biar count/empty-state di tempat lain (gak peduli urutan) tetap
             * baca filteredTasks apa adanya, cuma dua x-for (tabel & kartu) yang
             * pakai getter ini.
             */
            get sortedTasks() {
                if (! this.sortField) {
                    return this.filteredTasks;
                }

                const field = this.sortField;
                const dir = this.sortDir === 'desc' ? -1 : 1;

                return [...this.filteredTasks].sort((a, b) => {
                    const av = (a[field] || '').toString().toLowerCase();
                    const bv = (b[field] || '').toString().toLowerCase();

                    if (av < bv) return -1 * dir;
                    if (av > bv) return 1 * dir;

                    return 0;
                });
            },

            /**
             * Klik header kolom — sama kolom kepencet lagi → toggle ASC/DESC,
             * kolom lain → pindah sort, mulai dari ASC. Manual sort OVERRIDE
             * default urutan priority/SLA dari server sampai user ganti tab
             * atau reload halaman (§6.1).
             */
            sortBy(field) {
                if (this.sortField === field) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortField = field;
                    this.sortDir = 'asc';
                }
            },

            /**
             * Index row yang lagi fokus di `sortedTasks` (BUKAN filteredTasks —
             * navigasi jalan di urutan yang KELIATAN user, ngikut sort manual
             * kalau lagi aktif). -1 kalau gak ada yang fokus / tiketnya udah
             * hilang dari daftar (mis. abis di-close).
             */
            get focusedRowIndex() {
                return this.sortedTasks.findIndex(t => t.id === this.focusedTicketId);
            },

            /**
             * Arrow Up/Down (§6.3) — gerak fokus antar row, clamp di ujung
             * (gak wrap-around), scroll row ke viewport kalau kegeser keluar.
             */
            moveRowFocus(delta) {
                const list = this.sortedTasks;
                if (list.length === 0) return;

                const current = this.focusedRowIndex;
                const next = current === -1 ? 0 : Math.min(Math.max(current + delta, 0), list.length - 1);

                this.focusedTicketId = list[next].id;

                this.$nextTick(() => {
                    document.querySelector(`[data-ticket-row="${this.focusedTicketId}"]`)
                        ?.scrollIntoView({ block: 'nearest' });
                });
            },

            focusFirstRow() {
                this.focusedTicketId = this.sortedTasks[0]?.id ?? null;
            },

            /**
             * Ganti tab Ticket/Assign NOC/Assign FOP — dipakai klik tombol tab
             * MAUPUN Arrow Left/Right (switchTabByDelta di bawah). Ganti tab
             * = isi `filteredTasks` beda total, jadi fokus row WAJIB direset ke
             * baris pertama (dikonfirmasi user, §6.3) — index lama gak nyambung
             * ke tiket yang sama di tab baru.
             */
            setTab(value) {
                this.taskFilter = value;
                this.focusFirstRow();
            },

            switchTabByDelta(delta) {
                const values = this.tabs.map(t => t.value);
                const current = values.indexOf(this.taskFilter);
                const next = Math.min(Math.max(current + delta, 0), values.length - 1);

                this.setTab(values[next]);
            },

            // Tiket open milik customer terpilih (masuk/diproses) — bantu Helpdesk
            // sadar udah ada tiket berjalan sebelum bikin duplikat.
            //
            // Gap #5 (docs/plan/analisa-efektivitas-worksheet-ticketing.md) —
            // SENGAJA query server-side (checkDuplicates()), BUKAN filter array
            // `tasks` lokal kayak sebelumnya. `tasks` kena cap 30 (Gap #4),
            // tiket lama customer yang udah kegeser dari cap gak bakal
            // kedeteksi kalau cuma nyisir array lokal.
            duplicateTickets: [],

            async checkDuplicates(customerId) {
                try {
                    const res = await fetch(`{{ route('tickets.duplicates') }}?customer_id=${customerId}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    this.duplicateTickets = res.ok ? await res.json() : [];
                } catch (e) {
                    this.duplicateTickets = [];
                }
            },

            async searchCustomer() {
                const q = this.cidQuery.trim();
                if (q.length < 2) {
                    this.results = [];
                    this.searched = false;
                    return;
                }
                this.searching = true;
                try {
                    const res = await fetch(`{{ route('tickets.lookup-customer') }}?q=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    this.results = res.ok ? await res.json() : [];
                } catch (e) {
                    this.results = [];
                } finally {
                    this.searching = false;
                    this.searched = true;
                }
            },

            pick(customer) {
                this.selected = customer;
                this.cidQuery = customer.label;
                this.results = [];
                this.popPickerOpen = false;
                this.popId = '';
                this.checkDuplicates(customer.id);
            },

            clearSelection() {
                this.selected = null;
                this.cidQuery = '';
                this.results = [];
                this.searched = false;
                this.duplicateTickets = [];
                this.popPickerOpen = false;
                this.popSearchQuery = '';
                this.popTypeFilter = 'all';
                this.popId = '';
                this.$nextTick(() => this.$refs.searchInput?.focus());
            },

            // Kategori issue auto-fill prioritas — user tetap bisa override manual.
            onIssueCategoryChange() {
                if (this.issueCategoryId === 'lainnya' || this.issueCategoryId === '') return;
                const cat = this.issueCategories.find(c => c.id == this.issueCategoryId);
                if (cat) this.priority = cat.default_priority;
            },

            // ── Batch: expand/collapse + modal Tambah Pelanggan Terdampak ──
            // (revisi Worksheet Helpdesk poin 4)

            toggleBatchExpand(task) {
                this.expandedBatchTicketId = this.expandedBatchTicketId === task.id ? null : task.id;
            },

            openBatchModal(task) {
                this.batchModal = {
                    open: true,
                    ticket: task,
                    cidQuery: '',
                    results: [],
                    selected: null,
                    customerName: '',
                    phone: '',
                    submitting: false,
                    error: null,
                };
            },

            closeBatchModal() {
                this.batchModal.open = false;
            },

            async searchBatchCustomer() {
                const q = this.batchModal.cidQuery.trim();
                if (q.length < 2) {
                    this.batchModal.results = [];
                    return;
                }
                try {
                    const res = await fetch(`{{ route('tickets.lookup-customer') }}?q=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    this.batchModal.results = res.ok ? await res.json() : [];
                } catch (e) {
                    this.batchModal.results = [];
                }
            },

            pickBatchCustomer(customer) {
                this.batchModal.selected = customer;
                this.batchModal.cidQuery = customer.label;
                this.batchModal.results = [];
                // Auto-isi Nama/No. HP dari data master — TETAP bisa diedit
                // manual sebelum submit (input gak di-disable), lihat
                // TicketService::addBatchMember() (input yang diketik menang
                // atas data master).
                this.batchModal.customerName = customer.nama || '';
                this.batchModal.phone = customer.no_hp || '';
            },

            async submitBatchMember() {
                if (this.batchModal.submitting) return;
                if (! this.batchModal.selected && ! this.batchModal.customerName.trim()) {
                    this.batchModal.error = 'Pilih pelanggan dari pencarian, atau isi nama manual.';
                    return;
                }
                this.batchModal.submitting = true;
                this.batchModal.error = null;

                try {
                    // URL dirender server-side (batch_members_store_url dari
                    // worksheetCardPayload), BUKAN dirakit di klien dari
                    // ticket.id — lihat CLAUDE.md § Konvensi Kode (ADHOC-20).
                    const res = await fetch(this.batchModal.ticket.batch_members_store_url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            customer_id: this.batchModal.selected?.id ?? null,
                            customer_name: this.batchModal.customerName || null,
                            phone: this.batchModal.phone || null,
                        }),
                    });

                    const body = await res.json();

                    if (!res.ok) {
                        this.batchModal.error = body?.message || 'Gagal menambahkan pelanggan.';
                        return;
                    }

                    const task = this.tasks.find(t => t.id === this.batchModal.ticket.id);
                    if (task) task.batch_members.push(body.member);
                    this.showToast('Pelanggan terdampak ditambahkan.');
                    this.closeBatchModal();
                } catch (e) {
                    this.batchModal.error = 'Gagal menambahkan pelanggan — periksa koneksi internet.';
                } finally {
                    this.batchModal.submitting = false;
                }
            },

            toastTimer: null,
            showToast(message, type = 'success') {
                if (window.Toast) {
                    const title = type === 'error' ? 'Gagal' : (type === 'warning' ? 'Peringatan' : 'Berhasil');
                    if (type === 'error') window.Toast.error(title, message);
                    else if (type === 'warning') window.Toast.warning(title, message);
                    else window.Toast.success(title, message);
                    return;
                }
                if (this.toastTimer) clearTimeout(this.toastTimer);
                this.toast = { show: true, type, message };
                this.toastTimer = setTimeout(() => { this.toast.show = false; }, 4000);
            },

            resetForm() {
                this.clearSelection();
                this.ticketType = 'MTN';
                this.priority = 'Medium';
                this.issueCategoryId = '';
                this.detailKeluhan = '';
                this.catatanTeknis = '';
                this.attachments = [];
                this.showExtra = false;
                this.errors = {};
                this.popId = '';
                this.reporterPhone = '';
                this.popPickerOpen = false;
                this.popSearchQuery = '';
                this.popTypeFilter = 'all';
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                this.$nextTick(() => {
                    if (this.$refs.formBody) this.$refs.formBody.scrollTop = 0;
                    this.$refs.searchInput?.focus();
                });
            },

            handleShortcut(e) {
                // Abaikan jika dialog konfirmasi aksi tiket sedang terbuka
                if (window.Dialog && window.Dialog.isOpen) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        window.Dialog.close();
                    }
                    return;
                }

                // Abaikan jika modal batch sedang terbuka
                if (this.batchModal && this.batchModal.open) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        this.closeBatchModal();
                    }
                    return;
                }

                // Abaikan jika popup picker POP sedang terbuka
                if (this.popPickerOpen) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        this.closePopPicker();
                    }
                    return;
                }

                // Ctrl+Enter / Cmd+Enter: Submit form tiket baru dari mana saja
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    if (!this.submitting) this.submitForm();
                    return;
                }

                // Alt+N / Option+N: Universal toggle buka/tutup form tiket baru dari mana saja
                if ((e.key === 'n' || e.key === 'N') && (e.altKey || (e.ctrlKey && e.altKey))) {
                    e.preventDefault();
                    this.setFormOpen(!this.formOpen);
                    return;
                }

                if (e.key === 'Escape') {
                    // Drawer detail punya listener Escape SENDIRI
                    if (this.drawerOpen) return;

                    // Jika form sedang terbuka, Escape akan menutup form panel
                    if (this.formOpen) {
                        e.preventDefault();
                        if (document.activeElement && typeof document.activeElement.blur === 'function') {
                            document.activeElement.blur();
                        }
                        this.setFormOpen(false);
                        return;
                    }

                    this.resetForm();
                    return;
                }

                // "N" tunggal buka/tutup panel form — cuma waktu fokus TIDAK di field input
                if ((e.key === 'n' || e.key === 'N') && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    if (this.isTypingTarget(e)) return;
                    e.preventDefault();
                    this.setFormOpen(!this.formOpen);
                    return;
                }

                // Row-navigasi panel kanan (docs/plan/analisa-percepatan-alur-helpdesk-noc.md
                // §6.3/§6.4) — Arrow Up/Down gerak fokus antar row, Arrow Left/Right
                // ganti tab (reset fokus ke row pertama), Enter buka drawer,
                // C/V/B dispatch aksi row yang fokus (modal konfirmasi TETAP
                // muncul, tombol Quick Dispatch existing gak disentuh/dihapus —
                // ini jalur TAMBAHAN, bukan pengganti). Semua nonaktif kalau
                // fokus lagi di input/textarea/select ATAU drawer detail kebuka
                // (drawer punya navigasi/scroll sendiri).
                if (this.isTypingTarget(e) || this.drawerOpen || e.ctrlKey || e.metaKey || e.altKey) {
                    return;
                }

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.moveRowFocus(1);
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.moveRowFocus(-1);
                    return;
                }
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    this.switchTabByDelta(1);
                    return;
                }
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    this.switchTabByDelta(-1);
                    return;
                }

                const focusedTask = this.sortedTasks.find(t => t.id === this.focusedTicketId);
                if (! focusedTask) return;

                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.openTicketDetail(focusedTask.id);
                    return;
                }
                // C/V/B cuma nembak kalau aksinya emang kebuka buat tiket ini
                // (`task.actions` — sumber yang sama persis dipakai gerbang
                // tombol Quick Dispatch, lihat Ticket::actionFlagsFor()) — biar
                // gak ada jalur belakang yang lolosin aksi yang mestinya
                // ke-disable.
                if (e.key === 'c' || e.key === 'C') {
                    if (!focusedTask.actions?.can_close) return;
                    e.preventDefault();
                    this.closeTicket(focusedTask);
                    return;
                }
                if (e.key === 'v' || e.key === 'V') {
                    if (!focusedTask.actions?.can_escalate_noc) return;
                    e.preventDefault();
                    this.escalateTicket(focusedTask, 'noc');
                    return;
                }
                if (e.key === 'b' || e.key === 'B') {
                    if (!focusedTask.actions?.can_escalate_fop) return;
                    e.preventDefault();
                    this.escalateTicket(focusedTask, 'fop');
                }
            },

            /**
             * True kalau target event ini field yang nerima ketikan —
             * dipakai SEMUA shortcut (N, Arrow, C/V/B) biar gak ke-hijack
             * waktu user lagi ngetik di search box / textarea keluhan / dsb.
             */
            isTypingTarget(e) {
                const tag = (e.target.tagName || '').toLowerCase();

                return tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;
            },

            async submitForm() {
                if (this.submitting) return;
                this.errors = {};

                // Validasi Proaktif Sisi Klien
                if (!this.ticketType) {
                    this.errors.type = 'Pilih klasifikasi ticket (MTN / C-REQ).';
                    this.showToast('Pilih Ticket Type terlebih dahulu.', 'error');
                    return;
                }

                if (!this.selected && (!this.cidQuery.trim() || !this.popId)) {
                    if (!this.cidQuery.trim()) {
                        this.errors.cid_query = 'Cari data pelanggan atau masukkan label tiket.';
                        this.showToast('Cari data pelanggan terlebih dahulu.', 'error');
                        this.$refs.searchInput?.focus();
                        return;
                    }
                    if (!this.popId) {
                        this.errors.pop_id = 'Pilih Wilayah Jaringan / POP terlebih dahulu.';
                        this.showToast('Tentukan Wilayah Jaringan / POP.', 'error');
                        this.openPopPicker();
                        return;
                    }
                }

                if (!this.detailKeluhan.trim()) {
                    this.errors.detail_keluhan = 'Detail keluhan wajib diisi.';
                    this.showToast('Isi detail keluhan terlebih dahulu.', 'error');
                    return;
                }

                this.submitting = true;

                const formData = new FormData();
                formData.append('type', this.ticketType);
                formData.append('priority', this.priority);
                // Mode batch (revisi poin 1-2/4): gak ada pelanggan tunggal —
                // kirim label bebas + POP manual. Mode normal: customer_id
                // dari hasil pick(). Dua mode ini saling eksklusif, lihat
                // canSubmit getter & TicketService::create().
                if (this.selected) {
                    formData.append('customer_id', this.selected.id);
                } else {
                    formData.append('search_label', this.cidQuery);
                    formData.append('pop_id', this.popId);
                }
                if (this.reporterPhone) formData.append('reporter_phone', this.reporterPhone);
                if (this.issueCategoryId && this.issueCategoryId !== 'lainnya') {
                    formData.append('issue_category_id', this.issueCategoryId);
                }
                formData.append('detail_keluhan', this.detailKeluhan);
                if (this.catatanTeknis) formData.append('catatan_teknis', this.catatanTeknis);
                this.attachments.forEach(file => formData.append('attachments[]', file));

                try {
                    const res = await fetch('{{ route('tickets.store') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: formData,
                    });

                    if (res.status === 422) {
                        const body = await res.json();
                        const flat = {};
                        Object.entries(body.errors || {}).forEach(([field, msgs]) => { flat[field] = msgs[0]; });
                        this.errors = flat;
                        this.showToast('Validasi gagal, periksa kembali form.', 'error');
                        return;
                    }

                    if (!res.ok) {
                        // Gap #8 (docs/plan/analisa-efektivitas-worksheet-ticketing.md)
                        // — coba baca pesan server dulu (kalau ada), jangan cuma
                        // teks generik. Form & lampiran yang udah dipilih TETAP
                        // gak dihapus di jalur ini (resetForm() cuma dipanggil pas
                        // sukses) — user cuma perlu klik submit ulang, bukan isi
                        // dari nol.
                        let message = 'Gagal membuat tiket, coba lagi.';
                        try {
                            const body = await res.json();
                            if (body?.message) message = body.message;
                        } catch (e) { /* respons bukan JSON — pakai pesan default */ }
                        if (this.attachments.length > 0) {
                            message += ' Lampiran yang sudah dipilih masih tersimpan, tinggal submit ulang.';
                        }
                        this.showToast(message, 'error');
                        return;
                    }

                    const body = await res.json();
                    this.showToast(`${body.ticket.code} dibuat & disinkronkan ke FOP.`);
                    this.tasks.unshift(body.ticket);
                    this.worksheetTotalCount++;
                    this.resetForm();
                } catch (e) {
                    // fetch() sendiri gagal (network putus/timeout) — beda dari
                    // respons !res.ok di atas, di sini gak ada respons server
                    // sama sekali buat dibaca.
                    let message = 'Gagal membuat tiket — periksa koneksi internet, coba lagi.';
                    if (this.attachments.length > 0) {
                        message += ' Lampiran yang sudah dipilih masih tersimpan.';
                    }
                    this.showToast(message, 'error');
                } finally {
                    this.submitting = false;
                }
            },
        };
    }
</script>
@endpush
