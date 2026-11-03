{{-- Modal "Request Putus Langganan" — versi AJAX dari panel inline di
     customers/show.blade.php (id="terminate"). Dipasang di Quick Hub List
     (bukan modal form POST+referer biasa) SENGAJA pakai fetch + render error
     inline di modal sendiri: pola modal form POST biasa dilarang CLAUDE.md
     untuk aksi mutasi data (back()->withErrors() balik ke List yang jadi
     referer, modal ketutup, errornya ilang — bug persis ADHOC-20). Konfirmasi
     dialog + fetch-nya ada di triggerTerminate()/submitTerminationModal() di
     _list_scripts.blade.php, satu pola dengan triggerHubToggleConnection(). --}}
<div id="termination-modal-wrapper" role="dialog" aria-modal="true" aria-labelledby="termination-modal-title" class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 md:p-6 hidden">
    <!-- Backdrop Blur -->
    <div onclick="closeTerminationModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity"></div>

    <!-- Modal Dialog Sheet -->
    <div class="relative bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-900/40 rounded-t-3xl sm:rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden z-10 max-h-[88vh] sm:max-h-[90vh] flex flex-col transform transition-all duration-300">
        <!-- Mobile Pull Drag Handle Indicator -->
        <div class="w-10 h-1 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-2.5 mb-1 sm:hidden shrink-0"></div>

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-3.5 border-b border-rose-100 dark:border-rose-900/30 flex items-center justify-between bg-rose-50/50 dark:bg-rose-950/20 shrink-0">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-rose-100 dark:bg-rose-950 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold shrink-0 shadow-sm" aria-hidden="true">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.1-1.1m-1.756-4.928a4 4 0 005.656 0l4-4a4 4 0 10-5.656-5.656l-1.1 1.1"/></svg>
                </div>
                <div class="min-w-0">
                    <h2 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base leading-tight truncate" id="termination-modal-title">Request Putus Langganan</h2>
                    <p class="text-[11px] sm:text-xs text-slate-600 dark:text-slate-400 truncate" id="terminate-customer-name">Memuat...</p>
                </div>
            </div>
            <button type="button" onclick="closeTerminationModal()" aria-label="Tutup modal putus langganan" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl btn-interactive shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Body Form -->
        <form id="termination-form" method="POST" action="" class="p-4 sm:p-6 space-y-3 overflow-y-auto flex-1 overscroll-y-contain" onsubmit="submitTerminationModal(event)">
            @csrf

            <div id="termination-modal-errors" class="hidden text-[11px] text-rose-600 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/40 rounded-xl p-2.5 space-y-1"></div>

            <div>
                <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Alasan Putus <span class="text-rose-500">*</span></label>
                <select id="terminate-reason-select" name="termination_reason_id" required onchange="onTerminationReasonChange()"
                        class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">
                    <option value="">-- Pilih Alasan --</option>
                    @foreach($terminationReasons as $reason)
                    <option value="{{ $reason->id }}" data-default="{{ (int) $reason->default_penalty_amount }}">{{ $reason->name }}</option>
                    @endforeach
                </select>
            </div>

            <div id="terminate-penalty-wrapper">
                <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Nominal Denda <span class="text-rose-500">*</span></label>
                <input type="text" inputmode="decimal" id="terminate-penalty-input" name="penalty_amount" data-rupiah value="0"
                       class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">Masa langganan pelanggan ini &le;1 tahun — denda wajib diisi (boleh 0). Nilai diprefill dari alasan terpilih, tapi tetap wajib dikonfirmasi/diubah sebelum submit.</p>
            </div>
            <p id="terminate-penalty-free-note" class="hidden text-[10px] text-emerald-600 dark:text-emerald-400">Masa langganan pelanggan ini &gt;1 tahun — tidak ada denda putus langganan.</p>

            <div>
                <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Catatan Tambahan (opsional)</label>
                <textarea name="termination_note" rows="2" maxlength="1000" placeholder="Detail lebih spesifik, opsional..."
                          class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900/60 text-slate-800 dark:text-slate-200"></textarea>
            </div>

            <p class="text-[10px] text-rose-500/80">Tindakan permanen — status pelanggan jadi Terminated/Berhenti. Beda dengan Isolir yang masih bisa diaktifkan kembali.</p>

            <!-- Footer Buttons -->
            <div class="pt-2 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800 shrink-0 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:pb-0">
                <button type="button" onclick="closeTerminationModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-center btn-interactive">Batal</button>
                <button type="submit" id="terminate-submit-btn" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-md shadow-rose-600/20 text-center btn-interactive">Putuskan Langganan</button>
            </div>
        </form>
    </div>
</div>
