{{-- Modal Atur Mini POP & Distribusi + Customer Quick Hub (5 tab). Dipakai
     bareng partials/_list_table.blade.php; skriptnya di
     partials/_list_scripts.blade.php. --}}

{{-- ────────────────────────────────────────────────────────────
     MODAL 1: ATUR MINI POP & DISTRIBUSI (MOBILE FRIENDLY)
     Markupnya pindah ke partial supaya halaman lain (Antrean Verifikasi &
     Pemasangan) bisa memakai modal yang SAMA, bukan menyalinnya.
──────────────────────────────────────────────────────────── --}}
@include('customers.partials._network_assignment_modal')

{{-- ────────────────────────────────────────────────────────────
     MODAL 1b: GANTI PAKET INTERNET
     Sama alasannya dengan Modal 1 — dipisah dari Quick Hub biar dipakai
     ulang & aksinya jelas kelihatan (bukan link kecil ketimpa di teks).
──────────────────────────────────────────────────────────── --}}
@include('customers.partials._package_change_modal')

{{-- ────────────────────────────────────────────────────────────
     MODAL 2: CUSTOMER QUICK HUB & OPERATIONAL ACTIONS MODAL (5 TABS)
──────────────────────────────────────────────────────────── --}}
<div id="actions-modal" class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 md:p-6 hidden">
    <!-- Backdrop Blur -->
    <div onclick="closeActionsModal()" class="fixed inset-0 bg-slate-950/70 backdrop-blur-md transition-opacity"></div>

    <!-- Modal Dialog Sheet -->
    <div class="relative bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden z-10 max-h-[92vh] sm:max-h-[88vh] flex flex-col transform transition-all duration-300">
        
        <!-- Mobile Pull Drag Handle Indicator -->
        <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-2.5 mb-1 sm:hidden shrink-0"></div>

        <!-- Toast Notification inside modal -->
        <div id="modal-toast" class="hidden absolute top-4 left-1/2 -translate-x-1/2 z-30 px-4 py-2.5 rounded-2xl bg-slate-900/95 dark:bg-emerald-600 text-white font-semibold text-xs shadow-xl items-center gap-2.5 border border-slate-700 dark:border-emerald-500 animate-pop-in">
            <svg class="w-4 h-4 text-emerald-400 dark:text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span id="modal-toast-text"></span>
        </div>

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between bg-slate-50/90 dark:bg-slate-900/90 backdrop-blur shrink-0 gap-3">
            <div class="flex items-center gap-3 sm:gap-3.5 min-w-0">
                <div id="actions-modal-avatar" class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-gradient-to-tr from-sky-500 via-indigo-500 to-indigo-600 text-white flex items-center justify-center font-bold text-xs sm:text-sm shadow-md shadow-sky-500/20 shrink-0 ring-2 ring-white dark:ring-slate-800">
                    CU
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base leading-tight truncate max-w-[170px] sm:max-w-xs md:max-w-md" id="actions-modal-title">Nama Pelanggan</h3>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border inline-flex items-center gap-1.5 shrink-0 shadow-2xs" id="actions-modal-status-badge">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse-glow"></span>
                            <span>ACTIVE</span>
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[11px] sm:text-xs mt-0.5">
                        <span class="font-mono text-sky-600 dark:text-sky-400 font-bold shrink-0 tracking-tight" id="actions-modal-code">CID-000</span>
                        <span class="text-slate-300 dark:text-slate-600">•</span>
                        <span class="text-slate-500 dark:text-slate-400 truncate max-w-[160px] sm:max-w-xs" id="actions-modal-location-text">POP Central</span>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeActionsModal()" class="p-2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shrink-0 btn-interactive" title="Tutup Modal (Esc)">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- MODAL TAB NAVIGATION (SCROLLABLE NO-SCROLLBAR) -->
        <div class="px-2 sm:px-6 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50 flex items-center gap-1 overflow-x-auto shrink-0 no-scrollbar snap-x snap-mandatory" id="modal-tab-header">
            <button type="button" onclick="switchActionTab('profile')" id="tab-btn-profile" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-bold border-b-2 border-sky-600 text-sky-600 dark:text-sky-400 bg-white dark:bg-slate-800 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span class="hidden sm:inline">Profil & Paket</span>
                <span class="sm:hidden">Profil</span>
            </button>
            <button type="button" onclick="switchActionTab('technical')" id="tab-btn-technical" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                <span class="hidden sm:inline">Teknis & Perangkat</span>
                <span class="sm:hidden">Teknis</span>
            </button>
            <button type="button" onclick="switchActionTab('field')" id="tab-btn-field" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="hidden sm:inline">Lokasi & Pemasangan</span>
                <span class="sm:hidden">Lokasi</span>
            </button>
            <button type="button" onclick="switchActionTab('documents')" id="tab-btn-documents" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Berkas</span>
            </button>
            <button type="button" onclick="switchActionTab('finance')" id="tab-btn-finance" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span class="hidden sm:inline">Ringkasan & Tagihan</span>
                <span class="sm:hidden">Tagihan</span>
            </button>
        </div>

        <!-- Body Scrollable Content -->
        <div class="p-3.5 sm:p-6 overflow-y-auto space-y-4 sm:space-y-6 flex-1 overscroll-y-contain">
            
            <!-- Loading State -->
            <div id="modal-hub-loading" class="py-8 text-center hidden">
                <svg class="animate-spin h-7 w-7 text-sky-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-2">Memuat data tagihan & sistem...</p>
            </div>

            <!-- TAB 1: PROFIL & PAKET -->
            <div id="tab-content-profile" class="tab-pane space-y-4 sm:space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Identitas Pelanggan -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 space-y-3.5 text-xs shadow-2xs">
                        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2.5">
                            <h4 class="font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                                <span class="p-1 rounded-lg bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </span>
                                <span>Identitas Pelanggan</span>
                            </h4>
                            <span id="hub-prof-completeness-status" class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-900/40">-</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                            <div class="p-2.5 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Nama Lengkap</span>
                                <span id="hub-prof-fullname" class="font-bold text-slate-900 dark:text-white truncate block text-xs mt-0.5">-</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">NIK / No. KTP</span>
                                <span id="hub-prof-nik" class="font-mono font-medium text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Kode Pelanggan (CID)</span>
                                <span id="hub-prof-cid" class="font-mono font-bold text-sky-600 dark:text-sky-400 truncate block text-xs mt-0.5">-</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">No. HP / WhatsApp</span>
                                <span id="hub-prof-phone" class="font-mono font-semibold text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Email</span>
                                <span id="hub-prof-email" class="text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Tanggal Registrasi</span>
                                <span id="hub-prof-reg" class="font-mono font-medium text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Paket Internet Aktif -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 space-y-3.5 text-xs shadow-2xs flex flex-col justify-between">
                        <div class="space-y-3.5">
                            <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2.5">
                                <h4 class="font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                                    <span class="p-1 rounded-lg bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </span>
                                    <span>Paket Internet</span>
                                </h4>
                                <span class="text-[10px] font-semibold text-slate-400 uppercase">Langganan Aktif</span>
                            </div>
                            
                            <div class="space-y-3 pt-1">
                                <div class="p-3 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750 flex items-center justify-between">
                                    <div>
                                        <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Nama Paket Layanan</span>
                                        <span id="hub-prof-package" class="font-bold text-slate-900 dark:text-white text-sm sm:text-base mt-0.5 block">-</span>
                                    </div>
                                    <span class="p-2 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                                    </span>
                                </div>

                                <div class="p-3 rounded-xl bg-gradient-to-br from-emerald-50 to-teal-50/50 dark:from-emerald-950/30 dark:to-teal-950/20 border border-emerald-200/70 dark:border-emerald-900/40 flex items-center justify-between">
                                    <div>
                                        <span class="text-emerald-700 dark:text-emerald-400 text-[10px] block font-semibold uppercase tracking-wider">Harga Bulanan</span>
                                        <span id="hub-prof-price" class="font-mono font-bold text-emerald-700 dark:text-emerald-300 text-base sm:text-lg mt-0.5 block">-</span>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-bold text-[10px] shadow-sm">
                                        Per Bulan
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p class="text-[11px] text-slate-400 dark:text-slate-500 italic mt-2">
                            *Untuk upgrade atau pergantian paket, gunakan tombol <strong>Paket</strong> pada menu aksi di bawah.
                        </p>
                    </div>
                </div>
            </div>

            <!-- TAB 2: TEKNIS & PERANGKAT -->
            <div id="tab-content-technical" class="tab-pane hidden space-y-4 sm:space-y-5">
                <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 space-y-4 text-xs shadow-2xs">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-3 gap-2.5">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                            <span class="p-1 rounded-lg bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            </span>
                            <span>Konfigurasi Jaringan & Perangkat</span>
                        </h4>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" onclick="copyTechInfo()" class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-100 dark:hover:bg-slate-750 transition-colors btn-interactive inline-flex items-center gap-1.5" title="Salin seluruh info teknis ke clipboard">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                <span>Copy Teknis</span>
                            </button>
                            @if(auth()->user()->hasPermission('customers.detail.installation.validate'))
                            <button type="button" onclick="triggerNetworkAssignmentFromHub()"
                                    class="px-3 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-semibold transition-colors shadow-sm btn-interactive inline-flex items-center gap-1.5"
                                    title="Atur Mini POP & Distribusi">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                                <span>Atur Jaringan</span>
                            </button>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <!-- PPPoE Username -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750 flex flex-col justify-between">
                            <div>
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Username PPPoE</span>
                                <div class="flex items-center justify-between gap-1.5 mt-0.5">
                                    <span id="hub-tech-pppoe" class="font-mono font-bold text-slate-900 dark:text-white truncate block text-xs">-</span>
                                    <button type="button" onclick="copyPppoeUsername()" title="Copy Username PPPoE" class="shrink-0 p-1 rounded-lg text-slate-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-slate-700 transition-colors btn-interactive">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                </div>
                            </div>
                            <span id="hub-tech-pppoe-warning" class="hidden text-[10px] text-amber-600 dark:text-amber-400 block mt-1 leading-snug"></span>
                        </div>

                        <!-- VLAN ID -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                            <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">VLAN ID</span>
                            <span id="hub-tech-vlan" class="font-mono font-bold text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                        </div>

                        <!-- Bandwidth -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                            <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Bandwidth</span>
                            <span id="hub-tech-bandwidth" class="font-bold text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                        </div>

                        <!-- SN Perangkat -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                            <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">SN Perangkat (ONT/Modem)</span>
                            <span id="hub-tech-onu" class="font-mono font-bold text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                        </div>

                        <!-- Merk / Model / MAC -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                            <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Merk / Model / MAC</span>
                            <span id="hub-tech-router" class="font-mono font-semibold text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                        </div>

                        <!-- ODP / Distribusi -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                            <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">ODP / Distribusi</span>
                            <span id="hub-tech-distribution" class="font-semibold text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                        </div>

                        <!-- Skema Kontrak -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                            <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Skema Kontrak</span>
                            <span id="hub-tech-contract" class="font-semibold text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                        </div>

                        <!-- Redaman Optik -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                            <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Redaman (dBm)</span>
                            <span id="hub-tech-attenuation" class="font-mono font-bold text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: LOKASI & LAPANGAN -->
            <div id="tab-content-field" class="tab-pane hidden space-y-4 sm:space-y-5">
                <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 space-y-4 text-xs shadow-2xs">
                    <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2.5">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                            <span class="p-1 rounded-lg bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </span>
                            <span>Alamat Pemasangan & Navigasi</span>
                        </h4>
                    </div>

                    <div class="space-y-3">
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                            <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Alamat Pemasangan Lengkap</span>
                            <p id="hub-field-address-full" class="font-semibold text-slate-900 dark:text-white text-xs sm:text-sm mt-0.5">-</p>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <div class="p-2.5 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Desa / Kelurahan</span>
                                <span id="hub-field-village" class="font-semibold text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Kecamatan</span>
                                <span id="hub-field-district" class="font-semibold text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Kota / Kabupaten</span>
                                <span id="hub-field-city" class="font-semibold text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750">
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Kode Pos</span>
                                <span id="hub-field-postal-code" class="font-mono font-medium text-slate-800 dark:text-slate-200 truncate block text-xs mt-0.5">-</span>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-white dark:bg-slate-850 border border-slate-200/60 dark:border-slate-750 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <span class="text-slate-400 dark:text-slate-500 text-[10px] block font-medium">Koordinat GPS</span>
                                <span id="hub-field-coords" class="font-mono font-bold text-sky-600 dark:text-sky-400 block text-xs sm:text-sm mt-0.5">-</span>
                            </div>
                            <a id="btn-field-launch-maps" href="#" target="_blank" class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-semibold inline-flex items-center justify-center gap-2 shadow-sm shadow-sky-600/20 btn-interactive shrink-0" title="Buka navigasi Google Maps">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Buka Google Maps</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: BERKAS -->
            <div id="tab-content-documents" class="tab-pane hidden space-y-4 sm:space-y-5">
                <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 space-y-2 text-xs shadow-2xs">
                    <div class="flex justify-between items-center font-semibold">
                        <span class="text-slate-700 dark:text-slate-200">Kemajuan Kelengkapan Berkas</span>
                        <span id="hub-prof-completeness-bar-text" class="font-mono font-bold text-sky-600 dark:text-sky-400">0%</span>
                    </div>
                    <div class="w-full h-2.5 rounded-full bg-slate-200 dark:bg-slate-700/80 overflow-hidden">
                        <div id="hub-prof-completeness-bar" class="h-full bg-gradient-to-r from-sky-500 to-indigo-600 transition-all duration-300 rounded-full" style="width: 0%;"></div>
                    </div>
                </div>

                <!-- Kartu Berkas: Foto Rumah -->
                <div class="grid grid-cols-1 gap-3 text-xs">
                    @foreach([
                        ['type' => 'rumah', 'title' => 'Foto Rumah / Lokasi', 'upload_label' => 'Upload Foto Lokasi'],
                    ] as $berkas)
                    <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3 shadow-2xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-slate-800 dark:text-white flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>{{ $berkas['title'] }}</span>
                            </span>
                            <span id="hub-doc-{{ $berkas['type'] }}-badge" class="text-[10px] px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 font-semibold shrink-0 border border-slate-200 dark:border-slate-700">-</span>
                        </div>

                        <div class="h-32 rounded-xl bg-slate-50 dark:bg-slate-850 flex items-center justify-center border border-dashed border-slate-300 dark:border-slate-700 text-slate-400 overflow-hidden">
                            <a id="hub-doc-{{ $berkas['type'] }}-link" href="#" target="_blank" class="hidden w-full h-full items-center justify-center text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline bg-sky-50/50 dark:bg-sky-950/20">
                                <span>Lihat Berkas Tersimpan ↗</span>
                            </a>
                            <span id="hub-doc-{{ $berkas['type'] }}-empty" class="text-[11px] px-3 text-center text-slate-400 dark:text-slate-500">Belum ada berkas diunggah.</span>
                        </div>

                        @if(auth()->user()->hasPermission('customers.detail.documents.upload'))
                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-2 hub-document-form" data-document-type="{{ $berkas['type'] }}">
                            @csrf
                            <input type="hidden" name="document_type" value="{{ $berkas['type'] }}">
                            <input type="file" name="document_file" required accept="image/*,application/pdf"
                                   class="w-full text-[10px] text-slate-500 dark:text-slate-400 file:mr-2 file:py-1.5 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:font-semibold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-200">
                            <button type="submit" class="w-full py-2 rounded-xl border border-sky-200 dark:border-sky-900/60 text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 text-xs font-semibold touch-target btn-interactive transition-colors">
                                {{ $berkas['upload_label'] }}
                            </button>
                        </form>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- TAB 5: RINGKASAN & TAGIHAN -->
            <div id="tab-content-finance" class="tab-pane hidden space-y-4 sm:space-y-6">
                <!-- Ringkasan Billing Banner -->
                <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-br from-slate-50 to-sky-50/30 dark:from-slate-850 dark:to-sky-950/20 border border-slate-200/80 dark:border-slate-800 space-y-3.5 max-w-lg shadow-2xs">
                    <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2.5">
                        <h4 class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                            <span>Ringkasan Tagihan</span>
                        </h4>
                        <span id="hub-invoice-period-badge" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-900/40">Periode</span>
                    </div>
                    <div class="space-y-2.5 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 dark:text-slate-400">Jatuh Tempo</span>
                            <span id="hub-fin-due-date" class="font-mono font-medium text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 dark:text-slate-400">Total Piutang Tertunggak</span>
                            <span id="hub-fin-arrears" class="font-mono font-bold text-rose-600 dark:text-rose-400">Rp 0</span>
                        </div>
                        <div class="flex justify-between items-center pt-2.5 border-t border-slate-200/60 dark:border-slate-700/60">
                            <span class="font-bold text-slate-900 dark:text-white text-xs sm:text-sm">Total Harus Dibayar</span>
                            <span id="hub-fin-total-pay" class="font-mono font-bold text-base sm:text-lg text-emerald-600 dark:text-emerald-400">Rp 0</span>
                        </div>
                    </div>
                </div>

                <!-- Riwayat Pembayaran Terakhir -->
                <div class="space-y-2.5">
                    <h5 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Riwayat 3 Pembayaran Terakhir</span>
                    </h5>
                    <div class="border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-x-auto shadow-2xs">
                        <table class="w-full min-w-[440px] text-left text-xs">
                            <thead>
                                <tr class="bg-slate-50/80 dark:bg-slate-800/60 text-slate-400 font-semibold border-b border-slate-200/80 dark:border-slate-800">
                                    <th class="py-2.5 px-3.5">TANGGAL</th>
                                    <th class="py-2.5 px-3">INVOICE</th>
                                    <th class="py-2.5 px-3">METODE</th>
                                    <th class="py-2.5 px-3 text-right">NOMINAL</th>
                                    <th class="py-2.5 px-3.5 text-center">STRUK</th>
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

        </div>

        <!-- Modal Footer: Single Unified Action Bar (6 Actions) -->
        <div class="px-2 sm:px-4 py-2.5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-900/90 backdrop-blur shrink-0">
            <div class="grid grid-cols-6 gap-1 sm:gap-2">
                <button type="button" onclick="triggerDetail()" title="Detail Full Pelanggan"
                        class="flex flex-col items-center justify-center gap-1 py-1.5 px-1 rounded-xl text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors btn-interactive touch-target">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    <span class="text-[9px] sm:text-[10px] font-semibold leading-none">Detail</span>
                </button>

                @if(auth()->user()->hasPermission('customers.update'))
                <button type="button" onclick="triggerEdit()" title="Edit Data Pelanggan"
                        class="flex flex-col items-center justify-center gap-1 py-1.5 px-1 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors btn-interactive touch-target">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span class="text-[9px] sm:text-[10px] font-semibold leading-none">Edit</span>
                </button>
                @endif

                @if(auth()->user()->hasPermission('customers.detail.packages.change'))
                <button type="button" onclick="triggerPackageChangeFromHub()" title="Upgrade Paket Internet"
                        class="flex flex-col items-center justify-center gap-1 py-1.5 px-1 rounded-xl text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors btn-interactive touch-target">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span class="text-[9px] sm:text-[10px] font-semibold leading-none">Paket</span>
                </button>
                @endif

                @if(auth()->user()->hasPermission('payments.view'))
                <button type="button" onclick="printLatestReceipt()" id="btn-hub-footer-print-receipt" disabled
                        class="btn-print-receipt-action flex flex-col items-center justify-center gap-1 py-1.5 px-1 rounded-xl text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors btn-interactive disabled:opacity-40 disabled:cursor-not-allowed touch-target"
                        title="Cetak struk pembayaran terakhir">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-5a2 2 0 00-2-2H5a2 2 0 00-2 2v5a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4"/></svg>
                    <span class="text-[9px] sm:text-[10px] font-semibold leading-none">Struk</span>
                </button>
                @endif

                <button type="button" onclick="triggerHubToggleConnection()" title="Isolir / Aktifkan Layanan"
                        class="flex flex-col items-center justify-center gap-1 py-1.5 px-1 rounded-xl text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-slate-800 transition-colors btn-interactive touch-target">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    <span id="btn-hub-footer-toggle-text" class="text-[9px] sm:text-[10px] font-semibold leading-none">Isolir</span>
                </button>

                @if(auth()->user()->hasPermission('customers.deactivate'))
                <button type="button" onclick="triggerTerminate()" title="Putus Langganan"
                        class="flex flex-col items-center justify-center gap-1 py-1.5 px-1 rounded-xl text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-slate-800 transition-colors btn-interactive touch-target">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.1-1.1m-1.756-4.928a4 4 0 005.656 0l4-4a4 4 0 10-5.656-5.656l-1.1 1.1"/></svg>
                    <span class="text-[9px] sm:text-[10px] font-semibold leading-none">Putus</span>
                </button>
                @endif
            </div>
        </div>
    </div>
</div>
