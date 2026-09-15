{{-- Modal "Ganti Paket Internet" — dipakai bareng Quick Hub (_quick_hub_modal)
     & halaman Detail Pelanggan bisa pakai pola sama. Terpisah dari Quick Hub
     modal supaya aksinya kelihatan jelas (tombol dedicated + modal sendiri),
     bukan link kecil ketimpa di antara baris teks — modal digabung di 4 tab
     bikin aksi mutasi harga gampang kelewat. Data (nama pelanggan, paket
     aktif) diisi lewat JS dari `selectedCustomerData` yang sama dengan Quick
     Hub, jadi TIDAK fetch ulang. Pasangannya WAJIB: fungsi
     open/closePackageChangeModal di _list_scripts.blade.php. --}}
<div id="package-change-modal-wrapper" class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 md:p-6 hidden">
    <!-- Backdrop Blur -->
    <div onclick="closePackageChangeModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity"></div>

    <!-- Modal Dialog Sheet -->
    <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden z-10 max-h-[88vh] sm:max-h-[90vh] flex flex-col transform transition-all duration-300">
        <!-- Mobile Pull Drag Handle Indicator -->
        <div class="w-10 h-1 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-2.5 mb-1 sm:hidden shrink-0"></div>

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50 shrink-0">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold shrink-0 shadow-sm">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base leading-tight truncate">Ganti Paket Internet</h3>
                    <p class="text-[11px] sm:text-xs text-slate-400 truncate" id="pkg-customer-name">Memuat...</p>
                </div>
            </div>
            <button type="button" onclick="closePackageChangeModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl btn-interactive shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Body Form -->
        <form id="package-change-form" method="POST" action="" class="p-4 sm:p-6 space-y-4 overflow-y-auto flex-1 overscroll-y-contain">
            @csrf
            @method('PUT')

            <!-- Paket Aktif Sekarang -->
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div class="min-w-0 pr-2">
                    <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Paket Aktif Sekarang</span>
                    <p id="pkg-current-package" class="font-bold text-slate-800 dark:text-white text-xs sm:text-sm truncate">—</p>
                </div>
                <div class="text-right shrink-0">
                    <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Harga Bulanan</span>
                    <p id="pkg-current-price" class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs">—</p>
                </div>
            </div>

            <!-- Pilih Paket Baru -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Paket Baru</label>
                <select id="pkg-select" name="internet_package_id" required class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs sm:text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all">
                    <option value="">Pilih paket baru...</option>
                    @foreach($packages as $package)
                    <option value="{{ $package->id }}">{{ $package->name }} — Rp {{ number_format((float) $package->monthly_price, 0, ',', '.') }}</option>
                    @endforeach
                </select>
            </div>

            <p class="text-[11px] text-slate-400 bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                Harga baru berlaku mulai tagihan periode berikutnya — tagihan bulan berjalan yang sudah terbit tidak berubah.
            </p>

            <!-- Footer Buttons -->
            <div class="pt-4 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800 shrink-0">
                <button type="button" onclick="closePackageChangeModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-center btn-interactive">Batal</button>
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-md shadow-sky-600/20 text-center btn-interactive">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
