{{-- Modal Atur Mini POP & Distribusi + Customer Quick Hub (4 tab). Dipakai
     bareng partials/_list_table.blade.php; skriptnya di
     partials/_list_scripts.blade.php. --}}

{{-- ────────────────────────────────────────────────────────────
     MODAL 1: ATUR MINI POP & DISTRIBUSI (MOBILE FRIENDLY)
     Markupnya pindah ke partial supaya halaman lain (Antrean Verifikasi &
     Pemasangan) bisa memakai modal yang SAMA, bukan menyalinnya.
──────────────────────────────────────────────────────────── --}}
@include('customers.partials._network_assignment_modal')

{{-- ────────────────────────────────────────────────────────────
     MODAL 2: CUSTOMER QUICK HUB & OPERATIONAL ACTIONS MODAL (4 TABS)
──────────────────────────────────────────────────────────── --}}
<div id="actions-modal" class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 md:p-6 hidden">
    <!-- Backdrop Blur -->
    <div onclick="closeActionsModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity"></div>

    <!-- Modal Dialog Sheet -->
    <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl shadow-2xl w-full max-w-3xl overflow-hidden z-10 max-h-[88vh] sm:max-h-[90vh] flex flex-col transform transition-all duration-300">
        
        <!-- Mobile Pull Drag Handle Indicator -->
        <div class="w-10 h-1 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-2.5 mb-1 sm:hidden shrink-0"></div>

        <!-- Toast Notification inside modal -->
        <div id="modal-toast" class="hidden absolute top-3 left-1/2 -translate-x-1/2 z-30 px-4 py-2 rounded-xl bg-emerald-600 text-white font-semibold text-xs shadow-lg items-center gap-2 animate-pop-in">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span id="modal-toast-text"></span>
        </div>

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/80 dark:bg-slate-900/80 shrink-0 gap-2">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <div id="actions-modal-avatar" class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white flex items-center justify-center font-bold text-xs sm:text-sm shadow-md shadow-sky-500/20 shrink-0">
                    CU
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                        <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base leading-tight truncate max-w-[150px] sm:max-w-none" id="actions-modal-title">Nama Pelanggan</h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border inline-flex items-center gap-1 shrink-0" id="actions-modal-status-badge">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse-glow"></span>
                            <span>ACTIVE</span>
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[11px] sm:text-xs mt-0.5">
                        <span class="font-mono text-sky-600 dark:text-sky-400 font-bold shrink-0" id="actions-modal-code">CID-000</span>
                        <span class="text-slate-400">•</span>
                        <span class="text-slate-500 dark:text-slate-400 truncate max-w-[140px] sm:max-w-none" id="actions-modal-location-text">POP Central</span>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeActionsModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl btn-interactive shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- MODAL TAB NAVIGATION (SCROLLABLE NO-SCROLLBAR) -->
        <div class="px-2 sm:px-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-900/40 flex items-center gap-1 overflow-x-auto shrink-0 no-scrollbar snap-x snap-mandatory" id="modal-tab-header">
            <button type="button" onclick="switchActionTab('finance')" id="tab-btn-finance" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-bold border-b-2 border-sky-600 text-sky-600 dark:text-sky-400 bg-white dark:bg-slate-800 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                {{-- Label dipendekkan di mobile: dengan label panjang, tab ke-4
                     (Profil & Berkas) jatuh di luar layar dan cuma ketemu kalau
                     user kebetulan menggeser header tab. --}}
                <span class="hidden sm:inline">Ringkasan & Tagihan</span>
                <span class="sm:hidden">Tagihan</span>
            </button>
            <button type="button" onclick="switchActionTab('technical')" id="tab-btn-technical" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                <span class="hidden sm:inline">Teknis & Perangkat</span>
                <span class="sm:hidden">Teknis</span>
            </button>
            <button type="button" onclick="switchActionTab('field')" id="tab-btn-field" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="hidden sm:inline">Lokasi & Lapangan</span>
                <span class="sm:hidden">Lokasi</span>
            </button>
            <button type="button" onclick="switchActionTab('profile')" id="tab-btn-profile" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span class="hidden sm:inline">Profil & Berkas</span>
                <span class="sm:hidden">Berkas</span>
            </button>
        </div>

        <!-- Body Scrollable Content -->
        <div class="p-3.5 sm:p-6 overflow-y-auto space-y-4 sm:space-y-6 flex-1 overscroll-y-contain">
            
            <!-- Loading State -->
            <div id="modal-hub-loading" class="py-8 text-center hidden">
                <svg class="animate-spin h-6 w-6 text-sky-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-xs text-slate-500 font-medium mt-2">Memuat data tagihan & sistem...</p>
            </div>

            <!-- TAB 1: RINGKASAN & KEUANGAN -->
            <div id="tab-content-finance" class="tab-pane space-y-4 sm:space-y-6">
                {{-- Quick Action Row — CUMA DESKTOP (>=lg). Di <lg, WA/Isolir/Cetak
                     Struk sudah ada di bar ikon footer (selalu tampil, lepas dari tab
                     mana yang aktif); menampilkan lagi di sini di layar sempit berarti
                     tombol yang sama dobel dan grid 2-kolom timpang karena isinya
                     cuma 2-3 tombol (Catat Bayar & Detail Full sudah dipindah keluar
                     baris ini — sudah ada formnya sendiri di bawah dan di footer). --}}
                <div class="hidden lg:flex lg:flex-wrap gap-2">
                    <!-- Kirim WA Dropdown -->
                    <div class="relative flex-1 min-w-[140px]" id="wa-dropdown-container">
                        {{-- h-11 dikunci di keempat tombol: tanpa itu tombol dengan label
                             panjang (mis. "Aktifkan Layanan") wrap dan tingginya beda
                             sendiri, bikin baris aksi cepat kelihatan miring di mobile. --}}
                        <button type="button" onclick="toggleWaDropdown()" class="w-full h-11 px-2.5 rounded-xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/60 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs font-semibold flex items-center justify-center gap-1.5 hover:bg-emerald-100 transition-all btn-interactive touch-target">
                            <svg class="w-4 h-4 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            <span>Kirim WA</span>
                        </button>
                        <div id="wa-menu-dropdown" class="hidden absolute left-0 right-0 sm:right-auto mt-1 w-full sm:w-60 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl z-30 p-2 space-y-1 text-xs">
                            <span class="text-[10px] font-bold text-slate-400 px-2 uppercase">Pilih Template WA:</span>
                            <a id="btn-wa-reminder" href="#" target="_blank" class="block p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 btn-interactive">
                                <p class="font-bold">Pengingat Tagihan</p>
                                <p class="text-[10px] text-slate-400">Notifikasi invoice & jatuh tempo</p>
                            </a>
                            <a id="btn-wa-confirmation" href="#" target="_blank" class="block p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 btn-interactive">
                                <p class="font-bold">Konfirmasi Pembayaran</p>
                                <p class="text-[10px] text-slate-400">Terima kasih pembayaran lunas</p>
                            </a>
                            <a id="btn-wa-isolir" href="#" target="_blank" class="block p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 btn-interactive">
                                <p class="font-bold">Pemberitahuan Isolir</p>
                                <p class="text-[10px] text-slate-400">Penangguhan sementara</p>
                            </a>
                        </div>
                    </div>

                    <!-- Switch Status Layanan -->
                    <button type="button" onclick="triggerHubToggleConnection()" id="btn-hub-toggle-status" class="flex-1 min-w-[140px] h-11 px-2.5 rounded-xl border border-amber-200 dark:border-amber-900/40 bg-amber-50/60 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 text-xs font-semibold flex items-center justify-center gap-1.5 hover:bg-amber-100 transition-all btn-interactive touch-target">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        <span id="btn-hub-toggle-status-text">Isolir Layanan</span>
                    </button>

                    @if(auth()->user()->hasPermission('payments.view'))
                    {{-- Struk yang dicetak = pembayaran TERAKHIR pelanggan ini. Tombol sengaja di luar <form> (type=button) supaya tidak
                        ikut submit form pembayaran. --}}
                        <button type="button" onclick="printLatestReceipt()" id="btn-print-receipt" disabled
                                class="btn-print-receipt-action flex-1 min-w-[140px] h-11 px-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold flex items-center justify-center gap-1.5 shadow-sm btn-interactive disabled:opacity-50 disabled:cursor-not-allowed"
                                title="Cetak struk pembayaran terakhir">
                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-5a2 2 0 00-2-2H5a2 2 0 00-2 2v5a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4"/></svg>
                            <span>Cetak Struk</span>
                        </button>
                    @endif
                </div>

                <!-- Grid Info Tagihan & Form Pembayaran Cepat -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Left: Tagihan Active Card Summary -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ringkasan Tagihan</h4>
                            <span id="hub-invoice-period-badge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300">Periode</span>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Paket Internet</span>
                                <span id="hub-fin-package" class="font-semibold text-slate-900 dark:text-white">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Harga Bulanan</span>
                                <span id="hub-fin-price" class="font-mono font-bold text-slate-900 dark:text-white">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Jatuh Tempo</span>
                                <span id="hub-fin-due-date" class="font-mono text-slate-800 dark:text-slate-200">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Total Piutang</span>
                                <span id="hub-fin-arrears" class="font-mono font-bold text-rose-600">Rp 0</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                <span class="font-bold text-slate-900 dark:text-white">Total Harus Dibayar</span>
                                <span id="hub-fin-total-pay" class="font-mono font-bold text-base text-emerald-600 dark:text-emerald-400">Rp 0</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Form Input Pembayaran Cepat -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-3" id="payment-form-container">
                        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2">
                            <h4 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Input Pembayaran Instan</span>
                            </h4>
                        </div>

                        <!-- Notice Badge Saat Belum Ada Tagihan -->
                        <div id="payment-form-notice" class="hidden p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-900/60 text-amber-700 dark:text-amber-300 text-[11px] font-medium flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span id="payment-form-notice-text">Pelanggan ini belum memiliki tagihan aktif.</span>
                        </div>

                        <form id="payment-form" method="POST" action="" class="space-y-3 text-xs">
                            @csrf
                            <div>
                                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Nominal Pembayaran (Rp)</label>
                                {{-- data-rupiah: 150000 → 150.000 saat diketik,
                                     dinormalkan lagi saat submit (layouts/app). --}}
                                <input type="text" inputmode="decimal" name="amount" id="payment_amount" data-rupiah class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-mono font-bold text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all" required>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Metode</label>
                                    <select name="payment_method" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all" required>
                                        <option value="cash">Tunai / Kasir</option>
                                        <option value="transfer">Transfer Bank</option>
                                        <option value="qris">QRIS</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Tanggal</label>
                                    <input type="date" name="payment_date" id="payment_date" value="{{ date('Y-m-d') }}" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-mono text-xs focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all" required>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row items-stretch gap-2">
                                <button type="submit" class="w-full h-10 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20 btn-interactive">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Simpan Pembayaran</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Riwayat Pembayaran Terakhir -->
                <div class="space-y-2">
                    <h5 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Riwayat 3 Pembayaran Terakhir</h5>
                    {{-- overflow-x-auto: 4 kolom (Tanggal/Invoice/Metode/Nominal) tidak
                         muat di layar sempit tanpa ini — tabel akan memaksa modal
                         melebar atau teksnya kepencet sampai numpuk. --}}
                    <div class="border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-x-auto">
                        <table class="w-full min-w-[420px] text-left text-xs">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-semibold border-b border-slate-200/80 dark:border-slate-800">
                                    <th class="py-2.5 px-3">TANGGAL</th>
                                    <th class="py-2.5 px-3">INVOICE</th>
                                    <th class="py-2.5 px-3">METODE</th>
                                    <th class="py-2.5 px-3 text-right">NOMINAL</th>
                                    <th class="py-2.5 px-3 text-center">STRUK</th>
                                </tr>
                            </thead>
                            <tbody id="hub-recent-payments-body" class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-400">Belum ada riwayat pembayaran.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: TEKNIS & PERANGKAT -->
            <div id="tab-content-technical" class="tab-pane hidden space-y-4 sm:space-y-5">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-4 text-xs">
                    <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2">
                        <h4 class="font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            <span>Konfigurasi Teknis Jaringan & Perangkat</span>
                        </h4>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" onclick="copyTechInfo()" class="text-sky-600 dark:text-sky-400 font-semibold hover:underline">Copy Teknis</button>
                            @if(auth()->user()->hasPermission('customers.detail.installation.validate'))
                            <button type="button" onclick="triggerNetworkAssignmentFromHub()"
                                    class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-sky-200 dark:border-sky-800 text-sky-600 dark:text-sky-400 font-semibold hover:bg-sky-50 dark:hover:bg-slate-700 transition-colors btn-interactive inline-flex items-center gap-1.5"
                                    title="Atur Mini POP & Distribusi">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                                <span>Atur Jaringan</span>
                            </button>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">Username PPPoE</span>
                            <span id="hub-tech-pppoe" class="font-mono font-bold text-slate-900 dark:text-white">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">IP Address</span>
                            <span id="hub-tech-ip" class="font-mono font-bold text-sky-600 dark:text-sky-400">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">VLAN ID</span>
                            <span id="hub-tech-vlan" class="font-mono font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">Bandwidth</span>
                            <span id="hub-tech-bandwidth" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">SN ONU / Modem</span>
                            <span id="hub-tech-onu" class="font-mono font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">SN Router WiFi</span>
                            <span id="hub-tech-router" class="font-mono font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">ODP / Distribusi</span>
                            <span id="hub-tech-distribution" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">Skema Kontrak</span>
                            <span id="hub-tech-contract" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: LOKASI & LAPANGAN -->
            <div id="tab-content-field" class="tab-pane hidden space-y-4 sm:space-y-5">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-3 text-xs">
                    <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2">
                        <h4 class="font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>Alamat Pemasangan & Navigasi</span>
                        </h4>
                    </div>

                    <div class="space-y-2">
                        <div>
                            <span class="text-slate-400 text-[11px] block">Alamat Pemasangan Lengkap</span>
                            <p id="hub-field-address-full" class="font-semibold text-slate-900 dark:text-white text-sm">-</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200/60 dark:border-slate-700">
                            <div>
                                <span class="text-slate-400 text-[10px] block">Desa / Kelurahan</span>
                                <span id="hub-field-village" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] block">Kecamatan</span>
                                <span id="hub-field-district" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] block">Kota / Kabupaten</span>
                                <span id="hub-field-city" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] block">Kode Pos</span>
                                <span id="hub-field-postal-code" class="font-mono text-slate-800 dark:text-slate-200">-</span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">Koordinat GPS</span>
                            <span id="hub-field-coords" class="font-mono font-bold text-sky-600 dark:text-sky-400 block">-</span>
                        </div>
                    </div>

                    <div class="pt-3 flex justify-end">
                        <a id="btn-field-launch-maps" href="#" target="_blank" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-semibold inline-flex items-center justify-center gap-2 shadow-md shadow-sky-600/20 btn-interactive">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>Buka Google Maps</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- TAB 4: PROFIL & BERKAS -->
            <div id="tab-content-profile" class="tab-pane hidden space-y-4 sm:space-y-5">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-3 text-xs">
                    <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2">
                        <h4 class="font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span>Identitas & Kelengkapan Berkas</span>
                        </h4>
                        <span id="hub-prof-completeness-status" class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-amber-100 text-amber-700">-</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <span class="text-slate-400 text-[10px] block">Nama Lengkap</span>
                            <span id="hub-prof-fullname" class="font-bold text-slate-900 dark:text-white">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">NIK / No. KTP</span>
                            <span id="hub-prof-nik" class="font-mono text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">Kode Pelanggan (CID)</span>
                            <span id="hub-prof-cid" class="font-mono font-bold text-sky-600 dark:text-sky-400">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">No. HP / WA</span>
                            <span id="hub-prof-phone" class="font-mono text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">Email</span>
                            <span id="hub-prof-email" class="text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">Tanggal Registrasi</span>
                            <span id="hub-prof-reg" class="font-mono text-slate-800 dark:text-slate-200">-</span>
                        </div>
                    </div>

                    <!-- Progress Bar Kelengkapan -->
                    <div class="pt-3 border-t border-slate-200/60 dark:border-slate-700 space-y-1.5">
                        <div class="flex justify-between text-xs font-semibold">
                            <span class="text-slate-700 dark:text-slate-200">Kemajuan Kelengkapan Berkas</span>
                            <span id="hub-prof-completeness-bar-text" class="font-mono text-sky-600 dark:text-sky-400">0%</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                            <div id="hub-prof-completeness-bar" class="h-full bg-sky-600 transition-all duration-300" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Kartu Berkas: Foto Rumah -->
                <div class="grid grid-cols-1 gap-3 text-xs">
                    @foreach([
                        ['type' => 'rumah', 'title' => 'Foto Rumah / Lokasi', 'upload_label' => 'Upload Foto Lokasi'],
                    ] as $berkas)
                    <div class="p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-slate-800 dark:text-white">{{ $berkas['title'] }}</span>
                            <span id="hub-doc-{{ $berkas['type'] }}-badge" class="text-[10px] px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 font-semibold shrink-0">-</span>
                        </div>

                        <div class="h-28 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center border border-dashed border-slate-300 dark:border-slate-700 text-slate-400 overflow-hidden">
                            <a id="hub-doc-{{ $berkas['type'] }}-link" href="#" target="_blank" class="hidden w-full h-full items-center justify-center text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                                Lihat Berkas Tersimpan
                            </a>
                            <span id="hub-doc-{{ $berkas['type'] }}-empty" class="text-[10px] px-2 text-center">Belum ada berkas diunggah.</span>
                        </div>

                        @if(auth()->user()->hasPermission('customers.detail.documents.upload'))
                        {{-- Action form di-set dari JS (butuh id pelanggan yang lagi dibuka).
                             Upload akan redirect ke halaman Detail Pelanggan sesuai pola PRG
                             CustomerDocumentController::store(). --}}
                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-1.5 hub-document-form" data-document-type="{{ $berkas['type'] }}">
                            @csrf
                            <input type="hidden" name="document_type" value="{{ $berkas['type'] }}">
                            <input type="file" name="document_file" required accept=".jpg,.jpeg,.png,.webp,.pdf"
                                   class="w-full text-[10px] text-slate-500 dark:text-slate-400 file:mr-2 file:py-1.5 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:font-semibold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-200">
                            <button type="submit" class="w-full py-2 rounded-lg border border-sky-200 dark:border-sky-900/60 text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 text-[11px] font-semibold touch-target btn-interactive">
                                {{ $berkas['upload_label'] }}
                            </button>
                        </form>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>

        </div>

        <!-- Modal Footer -->
        {{-- Footer aksi — DUA versi.

             Mobile & tablet (<lg): satu baris ikon + label pendek, grid 6 kolom —
             Detail, Edit, WA, Cetak Struk, Isolir/Aktifkan, Putus. Semua aksi sama
             pentingnya, jadi tidak ada yang disembunyikan di balik menu. Baris ini
             SATU-SATUNYA tempat WA/Struk/Isolir muncul di layar <lg — quick action
             row di tab Ringkasan (di atas) sengaja disembunyikan pada lebar ini
             (hidden lg:flex) supaya tidak dobel. Bentuk tombol berlabel panjang
             makan dua baris penuh, dan footer ini shrink-0 (tidak ikut scroll) —
             tiap baris tambahan langsung memotong area isi modal sampai form
             pembayaran ketutup. Tombol tutup tidak diulang di sini: sudah ada
             tombol X di header modal.

             Desktop (>=lg): quick action row (WA/Isolir/Struk) tampil di tab
             Ringkasan, sisanya (Detail, Edit, Putus) di layout kiri/kanan berikut
             — ruang vertikal longgar, tidak perlu dipadatkan jadi ikon. --}}
        <div class="lg:hidden px-2 py-2 border-t border-slate-100 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/80 shrink-0">
            <div class="grid grid-cols-6 gap-1">
                <button type="button" onclick="triggerDetail()" title="Detail Full"
                        class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors btn-interactive">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    <span class="text-[9px] font-semibold leading-none">Detail</span>
                </button>

                @if(auth()->user()->hasPermission('customers.update'))
                <button type="button" onclick="triggerEdit()" title="Edit Data Pelanggan"
                        class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors btn-interactive">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span class="text-[9px] font-semibold leading-none">Edit</span>
                </button>
                @endif

                <button type="button" onclick="focusWaTemplates()" data-wa-trigger title="Kirim WhatsApp"
                        class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-slate-800 transition-colors btn-interactive">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    <span class="text-[9px] font-semibold leading-none">WA</span>
                </button>

                @if(auth()->user()->hasPermission('payments.view'))
                {{-- Struk yang dicetak = pembayaran TERAKHIR pelanggan ini. Tombol sengaja di luar <form> (type=button) supaya tidak
                        ikut submit form pembayaran. --}}
                    <button type="button" onclick="printLatestReceipt()" id="btn-hub-footer-print-receipt" disabled
                        class="btn-print-receipt-action flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors btn-interactive disabled:opacity-50 disabled:cursor-not-allowed"
                        title="Cetak struk pembayaran terakhir">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-5a2 2 0 00-2-2H5a2 2 0 00-2 2v5a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4"/></svg>
                        <span class="text-[9px] font-semibold leading-none">PDF</span>
                    </button>
                @endif

                <button type="button" onclick="triggerHubToggleConnection()" title="Isolir / Aktifkan Layanan"
                        class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-slate-800 transition-colors btn-interactive">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    <span id="btn-hub-footer-toggle-text" class="text-[9px] font-semibold leading-none">Isolir</span>
                </button>

                @if(auth()->user()->hasPermission('customers.deactivate'))
                <button type="button" onclick="triggerTerminate()" title="Putus Langganan"
                        class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-slate-800 transition-colors btn-interactive">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.1-1.1m-1.756-4.928a4 4 0 005.656 0l4-4a4 4 0 10-5.656-5.656l-1.1 1.1"/></svg>
                    <span class="text-[9px] font-semibold leading-none">Putus</span>
                </button>
                @endif
            </div>
        </div>

        <div class="hidden lg:flex px-6 py-3.5 border-t border-slate-100 dark:border-slate-800 items-center justify-between gap-2 bg-slate-50/80 dark:bg-slate-900/80 shrink-0 text-xs">
            <div class="flex items-center gap-2">
                <button type="button" onclick="triggerDetail()" class="h-10 px-3.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-semibold transition-colors shadow-sm btn-interactive inline-flex items-center justify-center text-center">
                    Detail Full
                </button>
                @if(auth()->user()->hasPermission('customers.update'))
                <button type="button" onclick="triggerEdit()" class="h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors btn-interactive inline-flex items-center justify-center text-center">
                    Edit Master Data
                </button>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if(auth()->user()->hasPermission('customers.deactivate'))
                <button type="button" onclick="triggerTerminate()" class="h-10 px-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/40 text-rose-700 dark:text-rose-300 font-semibold hover:bg-rose-100 dark:hover:bg-rose-950/70 transition-colors btn-interactive inline-flex items-center justify-center text-center">
                    Putus Langganan
                </button>
                @endif
            </div>
        </div>
    </div>
</div>

