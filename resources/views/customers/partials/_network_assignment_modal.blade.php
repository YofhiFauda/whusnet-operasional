{{-- Modal "Atur Jaringan & Mini POP" — SATU markup dipakai banyak halaman
     (List Pelanggan lewat _quick_hub_modal, Antrean Verifikasi & Pemasangan).
     Isinya di-fetch per klik lewat customers.network-assignment.data, jadi satu
     modal cukup untuk semua baris. Pasangannya WAJIB: skrip
     customers.partials._network_assignment_js (fungsi open/close ada di sana).

     Kalau perlu ubah field, ubah DI SINI saja — dulu markupnya nempel di
     _quick_hub_modal dan halaman lain tidak bisa memakainya tanpa menyalin. --}}
<div id="network-modal-wrapper" class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 md:p-6 hidden">
    <!-- Backdrop Blur -->
    <div onclick="closeNetworkAssignmentModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity"></div>

    <!-- Modal Dialog Sheet -->
    <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden z-10 max-h-[88vh] sm:max-h-[90vh] flex flex-col transform transition-all duration-300">
        <!-- Mobile Pull Drag Handle Indicator -->
        <div class="w-10 h-1 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-2.5 mb-1 sm:hidden shrink-0"></div>

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50 shrink-0">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold shrink-0 shadow-sm">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base leading-tight truncate">Atur Jaringan & Mini POP</h3>
                    <p class="text-[11px] sm:text-xs text-slate-400 truncate">Konfigurasi titik distribusi OLT & pelanggan</p>
                </div>
            </div>
            <button type="button" onclick="closeNetworkAssignmentModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl btn-interactive shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Body Form -->
        <form id="network-assignment-form" method="POST" class="p-4 sm:p-6 space-y-4 overflow-y-auto flex-1 overscroll-y-contain">
            @csrf
            @method('PUT')

            <!-- Customer Target Summary Card -->
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div class="min-w-0 pr-2">
                    <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Pelanggan</span>
                    <p id="na-customer-name" class="font-bold text-slate-800 dark:text-white text-xs sm:text-sm truncate">Memuat...</p>
                </div>
                <div class="text-right shrink-0">
                    <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">CID / Jaringan</span>
                    <p id="na-customer-cid" class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs">—</p>
                </div>
            </div>

            <!-- Cabang Context -->
            <div class="text-xs text-slate-500">
                <span>Cabang Utama: <strong id="na-pop-name" class="text-slate-800 dark:text-slate-200 font-semibold">—</strong></span>
            </div>

            <!-- Blocked Warning Banner -->
            <div id="na-blocked-warning" class="hidden text-xs text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 p-3 rounded-xl border border-rose-200 dark:border-rose-800">
                Mini POP & Distribusi cuma bisa diatur setelah proses pemasangan dimulai.
            </div>

            <!-- Mini POP Select -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Mini POP (OLT Target)</label>
                <select id="na-mini-pop-select" name="mini_pop_id" class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs sm:text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all">
                    <option value="">Memuat...</option>
                </select>
            </div>

            <!-- Jalur Distribusi (ODP) Select -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Titik Distribusi (ODP / Box)</label>
                <select id="na-distribution-select" name="distribution_id" class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs sm:text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all">
                    <option value="">—</option>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Daftar Distribusi otomatis menyesuaikan dengan Mini POP yang dipilih.</p>
            </div>

            <!-- Footer Buttons -->
            <div class="pt-4 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800 shrink-0">
                <button type="button" onclick="closeNetworkAssignmentModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-center btn-interactive">Batal</button>
                <button type="submit" id="na-submit-btn" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-md shadow-sky-600/20 text-center btn-interactive">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
