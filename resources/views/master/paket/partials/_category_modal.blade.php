{{-- Modal Tambah Kategori Paket. Dipakai di master/paket/create & edit —
     kategori sebelumnya hardcode di `InternetPackage::CATEGORIES`, sekarang
     master `package_categories` dan admin bisa nambah sendiri tanpa keluar
     dari form paket yang sedang diisi.

     SENGAJA submit lewat fetch (JSON), bukan form POST biasa: form paket di
     baliknya bisa sudah setengah diisi, dan `back()->withErrors()` normal
     akan balik ke referer lalu menampilkan form paket itu KOSONG kalau modal
     ini dibikin form terpisah yang navigasi halaman (ADHOC-20 lesson). Fetch
     tidak pernah pindah halaman, jadi masalah itu tidak berlaku di sini. --}}
<div id="package-category-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-[2px] flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200/80 dark:border-slate-800 w-full max-w-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Tambah Kategori Paket</h3>
            <button type="button" onclick="closePackageCategoryModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="p-5 space-y-3">
            <div id="pc-error" class="hidden text-xs text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-950/80 border border-red-200 dark:border-red-800 rounded-md px-3 py-2"></div>

            <div>
                <label for="pc-name" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Nama Kategori <span class="text-rose-500">*</span></label>
                <input type="text" id="pc-name" maxlength="100" placeholder="mis. Paket Home Fiber"
                       class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm bg-white dark:bg-slate-800">
            </div>
        </div>

        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
            <button type="button" onclick="closePackageCategoryModal()"
                    class="px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer">
                Batal
            </button>
            <button type="button" id="pc-submit-btn" onclick="submitPackageCategoryModal()"
                    class="px-4 py-2 text-xs font-semibold text-white bg-sky-600 dark:bg-sky-500 rounded-md hover:bg-sky-700 cursor-pointer">
                Simpan Kategori
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // URL POST-nya dibaca dari tombol pembuka (data-package-category-store-url),
    // bukan dirakit di sini — konsisten dengan aturan "target aksi dirender
    // server-side" (ADHOC-20): kalau atributnya gagal terisi, tombol Simpan
    // gagal jelas lewat pcError, bukan diam-diam POST ke URL halaman lain.
    let pcStoreUrl = null;

    function openPackageCategoryModal(storeUrl) {
        pcStoreUrl = storeUrl || null;
        document.getElementById('package-category-modal').classList.remove('hidden');
        document.getElementById('pc-error').classList.add('hidden');
        document.getElementById('pc-name').value = '';
        document.getElementById('pc-name').focus();
    }

    function closePackageCategoryModal() {
        document.getElementById('package-category-modal').classList.add('hidden');
    }

    function submitPackageCategoryModal() {
        const name = document.getElementById('pc-name').value.trim();
        const errorBox = document.getElementById('pc-error');
        const submitBtn = document.getElementById('pc-submit-btn');
        errorBox.classList.add('hidden');

        if (!name) {
            errorBox.textContent = 'Nama kategori wajib diisi.';
            errorBox.classList.remove('hidden');
            return;
        }

        if (!pcStoreUrl) {
            errorBox.textContent = 'URL simpan kategori tidak tersedia. Muat ulang halaman.';
            errorBox.classList.remove('hidden');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Menyimpan...';

        fetch(pcStoreUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ name }),
        })
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const message = data.errors && data.errors.name
                    ? data.errors.name[0]
                    : (data.message || 'Gagal menyimpan kategori.');
                throw new Error(message);
            }
            return data;
        })
        .then((category) => {
            // Halaman pemanggil (create/edit paket) yang tahu cara menyisipkan
            // opsi baru ke <select> kategorinya sendiri.
            document.dispatchEvent(new CustomEvent('package-category-created', { detail: category }));
            closePackageCategoryModal();
        })
        .catch((err) => {
            errorBox.textContent = err.message;
            errorBox.classList.remove('hidden');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Simpan Kategori';
        });
    }
</script>
@endpush
