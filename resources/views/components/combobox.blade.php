{{--
    Komponen: Combobox (dropdown bisa diketik & dicari)
    ===================================================
    Pengganti <select> untuk daftar panjang (SN perangkat aktif, roll kabel,
    kategori & nama barang pasif). <select> biasa cuma bisa di-scroll; begitu
    isinya ratusan baris, teknisi kehilangan waktu dan salah pilih.

    Props:
      $id           (string)  — id input hidden yang membawa NILAI. Sengaja
                                di hidden: getElementById($id).value dipakai
                                validasi wajib isi (getMissingFieldsFrom()).
      $name         (string)  — name statis untuk form, mis. "device_type".
      $nameExpr     (string)  — ekspresi Alpine untuk name dinamis (dipakai di
                                baris x-for), mis. "`materials[${index}][item_id]`".
                                Isi salah satu: $name ATAU $nameExpr.
      $optionsExpr  (string)  — ekspresi JS yang menghasilkan array opsi
                                {value, label, search?, hint?}. Bisa fungsi
                                Alpine (dipanggil ulang tiap render) supaya
                                opsi bisa difilter reaktif.
      $valueExpr    (string)  — ekspresi nilai terpilih (string/number atau
                                fungsi). Default kosong.
      $onSelectExpr (string)  — ekspresi JS dipanggil dengan nilai baru saat
                                opsi dipilih. Dipakai kalau pemilihan punya
                                efek samping (mis. isi tipe & satuan barang).
      $placeholder  (string)  — teks saat belum ada yang dipilih.

    Event: setiap pilihan memicu event `change` (bubbles) di input hidden,
    jadi listener form yang sudah ada (runLiveProgressUpdates, enableAktivasi)
    tetap jalan tanpa diubah.

    Pencarian: setiap kata yang diketik harus cocok (AND, tanpa peduli huruf
    besar) dengan field `search` (fallback `label`). Hasil dibatasi 50 baris
    supaya dropdown tetap ringan walau master barang ribuan.
--}}

@props([
    'id' => null,
    'name' => null,
    'nameExpr' => null,
    'optionsExpr' => '[]',
    'valueExpr' => "''",
    'onSelectExpr' => null,
    'placeholder' => '— Pilih —',
])

<div
    x-data="comboBox({
        options: {{ $optionsExpr }},
        value: {{ $valueExpr }},
        onSelect: {{ $onSelectExpr ? '(value) => { ' . $onSelectExpr . ' }' : 'null' }}
    })"
    class="relative"
    @click.outside="close()"
    @keydown.escape.stop="close()"
>
    {{-- id hanya dipasang untuk combobox statis (SN, roll). Baris material
         dinamis tidak punya id — mereka dibaca lewat name[] saja. --}}
    @if($nameExpr)
        <input type="hidden" :name="{{ $nameExpr }}" :value="current" x-ref="hidden">
    @else
        <input type="hidden" id="{{ $id }}" name="{{ $name }}" :value="current" x-ref="hidden">
    @endif

    <div class="relative">
        <input
            type="text"
            @if($id) id="{{ $id }}-search" @endif
            role="combobox"
            autocomplete="off"
            placeholder="{{ $placeholder }}"
            :value="open ? query : label"
            @focus="openList()"
            @click="openList()"
            @input="onType($event)"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="pick()"
            @keydown.tab="close()"
            @blur="close()"
            class="w-full text-xs font-sans px-3.5 py-2.5 pr-8 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm"
        >
        <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </div>

    <div
        x-show="open"
        x-cloak
        class="absolute z-30 mt-1 w-full max-h-64 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg py-1"
    >
        <template x-for="(opt, index) in matches" :key="String(opt.value)">
            <button
                type="button"
                @mousedown.prevent="choose(opt)"
                @mouseenter="active = index"
                :class="index === active ? 'bg-sky-50 dark:bg-sky-900/40' : ''"
                class="block w-full text-left px-3.5 py-2 text-xs text-slate-800 dark:text-slate-100"
            >
                <span class="block font-semibold" x-text="opt.label"></span>
                <span x-show="opt.hint" class="block text-[10px] text-slate-500 dark:text-slate-400" x-text="opt.hint"></span>
            </button>
        </template>

        <p x-show="matches.length === 0" class="px-3.5 py-2 text-[11px] text-slate-400 dark:text-slate-500">Tidak ada hasil untuk pencarian ini.</p>
        <p x-show="truncated" class="px-3.5 py-2 text-[10px] text-slate-400 dark:text-slate-500 border-t border-slate-100 dark:border-slate-800">
            Menampilkan 50 teratas — ketik lebih banyak untuk mempersempit.
        </p>
    </div>
</div>

@once
@push('scripts')
<script>
function comboBox(config) {
    // Nilai & opsi boleh berupa konstanta atau fungsi. Fungsi dipakai di baris
    // material: opsi "Nama Barang" difilter menurut Kategori yang lagi dipilih
    // di baris yang sama, jadi harus dibaca ulang tiap kali dibutuhkan.
    const read = (source) => (typeof source === 'function' ? source() : source);

    return {
        open: false,
        query: '',
        active: 0,
        optionsSource: config.options,
        valueSource: config.value,
        onSelect: config.onSelect,
        limit: 50,

        get current() {
            return read(this.valueSource) ?? '';
        },

        list() {
            return read(this.optionsSource) ?? [];
        },

        get label() {
            const selected = this.list().find(opt => String(opt.value) === String(this.current));

            return selected ? selected.label : '';
        },

        get filtered() {
            const tokens = this.query.toLowerCase().trim().split(/\s+/).filter(Boolean);

            return this.list().filter(opt => {
                const haystack = String(opt.search ?? opt.label).toLowerCase();

                return tokens.every(token => haystack.includes(token));
            });
        },

        get matches() {
            return this.filtered.slice(0, this.limit);
        },

        get truncated() {
            return this.filtered.length > this.limit;
        },

        // Dipanggil dari focus DAN click. Setelah memilih, input tetap fokus
        // (mousedown.prevent di opsi), jadi cuma klik yang bisa membuka lagi.
        // Kalau daftar sudah terbuka, jangan reset ketikan user.
        openList() {
            if (this.open) {
                return;
            }

            this.open = true;
            this.query = '';
            this.active = 0;
        },

        onType(event) {
            this.query = event.target.value;
            this.open = true;
            this.active = 0;
        },

        close() {
            this.open = false;
            this.query = '';
        },

        move(step) {
            this.open = true;

            if (this.matches.length === 0) {
                return;
            }

            this.active = (this.active + step + this.matches.length) % this.matches.length;
        },

        pick() {
            if (this.open && this.matches[this.active]) {
                this.choose(this.matches[this.active]);
            }
        },

        choose(opt) {
            // Nilai statis disimpan di sini; nilai fungsi (mis. item_id di baris
            // material) diurus pemanggil lewat onSelect() supaya tetap jadi
            // sumber kebenaran tunggal milik baris itu.
            if (typeof this.valueSource !== 'function') {
                this.valueSource = opt.value;
            }

            if (this.onSelect) {
                this.onSelect(opt.value);
            }

            this.close();

            // Tunggu Alpine menulis ulang :value input hidden dulu, baru kirim
            // event change — kalau tidak, listener form membaca nilai lama.
            this.$nextTick(() => this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true })));
        },
    };
}
</script>
@endpush
@endonce
