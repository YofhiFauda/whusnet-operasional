{{-- Skrip lipat/buka papan (tabel & matriks): satu sumber, dipasang oleh _table dan _matrix. --}}
@push('scripts')
<script>
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
            return this.openCategories[key] ?? true;
        },
        toggleCategory(key) {
            this.openCategories[key] = !this.isCategoryOpen(key);
        },
        expandAll() {
            this.startOpen = true;
            this.openPops = {};
            this.openCategories = {};
        },
        collapseAll() {
            this.startOpen = false;
            this.openPops = {};
        },
    };
}
</script>
@endpush
