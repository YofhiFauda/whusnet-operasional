{{-- Skrip lipat/buka papan (tabel & matriks): satu sumber, dipasang oleh _table dan _matrix. --}}
@push('scripts')
<script>
if (typeof window.calcDropdownPos !== 'function') {
    window.calcDropdownPos = function(el, menuWidth = 224) {
        const bounds = el.getBoundingClientRect();
        const spaceBelow = window.innerHeight - bounds.bottom;
        const spaceAbove = bounds.top;
        const openUp = spaceBelow < 180 && spaceAbove > spaceBelow;
        let left = bounds.right - menuWidth;

        if (left < 8) left = 8;
        if (left + menuWidth > window.innerWidth - 8) {
            left = Math.max(8, window.innerWidth - menuWidth - 8);
        }

        return {
            openUp,
            top: openUp ? null : Math.round(bounds.bottom + 4),
            bottom: openUp ? Math.round(window.innerHeight - bounds.top + 4) : null,
            left: Math.round(left),
        };
    };
}
/**
 * Lipat/buka papan Kelola Stok. Keadaan awal dihitung dari data server
 * (`data-pop-count`, `data-force-open`): semua gudang terbuka bila jumlahnya
 * ≤ collapseGudangOver, user terkunci satu gudang, atau filter/pencarian aktif
 * (gudang yang tidak cocok sudah dibuang server). Kategori terbuka secara
 * default. Pilihan per band disimpan di openPops/openCategories; "Bentang
 * semua"/"Ciutkan" mengganti default lalu membuang pilihan per band.
 */
function stockBoard(config) {
    return {
        startOpen: true,
        categoriesOpen: true,
        openPops: {},
        openCategories: {},
        init() {
            const popCount = Number(this.$el.dataset.popCount || 0);
            this.startOpen = this.$el.dataset.forceOpen === 'true' || popCount <= config.collapseGudangOver;
        },
        isPopOpen(id) {
            return this.openPops[id] ?? this.startOpen;
        },
        togglePop(id) {
            this.openPops[id] = !this.isPopOpen(id);
        },
        isCategoryOpen(key) {
            return this.openCategories[key] ?? this.categoriesOpen;
        },
        toggleCategory(key) {
            this.openCategories[key] = !this.isCategoryOpen(key);
        },
        expandAll() {
            this.startOpen = true;
            this.categoriesOpen = true;
            this.openPops = {};
            this.openCategories = {};
        },
        collapseAll() {
            this.startOpen = false;
            this.categoriesOpen = false;
            this.openPops = {};
            this.openCategories = {};
        },
    };
}
</script>
@endpush
