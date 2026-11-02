{{--
    Pemilih gudang + satu kolom pencarian "barang / SN / kode roll", dipakai
    form Cek Stok & Barang Bermasalah. Server (`warehouse.adjustments.resolve`)
    yang menentukan jenisnya — user gak pernah memilih "qty atau SN atau roll".

    Wajib berada di dalam elemen `x-data="stockCorrectionForm($el.dataset)"`
    yang membawa `data-resolve-url`, `data-prefill-pop`, `data-prefill-q`,
    `data-prefill-lot` (masing-masing old() dulu, lalu $prefill). Input bernama
    `pop_id` & `q` ikut terkirim; endpoint simpan mengabaikan `q`, tapi
    `old('q')` membuat hasil pencarian kembali muncul setelah gagal validasi.

    Variabel: $pops, $prefill (array{pop_id: ?int, q: ?string, lot_no: string})
--}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div>
        <label for="pop_id" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Gudang <span class="text-rose-500">*</span></label>
        <select id="pop_id" name="pop_id" required x-model="popId" @change="search()"
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <option value="">Pilih Gudang</option>
            @foreach($pops as $pop)
            <option value="{{ $pop->id }}" @selected(old('pop_id', $prefill['pop_id']) == $pop->id)>{{ $pop->name }}</option>
            @endforeach
        </select>
        @error('pop_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <label for="q" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Barang, nomor SN, atau kode roll <span class="text-rose-500">*</span></label>
        <input id="q" name="q" type="text" maxlength="100" autocomplete="off" x-model="q" @input="pending()" @input.debounce.400ms="search()" @keydown.enter.prevent="search()"
               value="{{ old('q', $prefill['q']) }}" placeholder="Ketik nama/kode barang, atau scan SN / kode roll"
               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 font-mono text-xs text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
    </div>
</div>

<div class="mt-3 text-xs" aria-live="polite">
    <p x-show="loading" x-cloak class="text-slate-400">Mencari…</p>
    <p x-show="!loading && !popId && q.length >= 2" x-cloak class="text-amber-700 dark:text-amber-400">Pilih gudang dulu.</p>
    <p x-show="!loading && result.type === 'none'" x-cloak class="text-rose-600 dark:text-rose-400">Tidak ditemukan di gudang ini. SN/roll yang sedang dipegang teknisi dikoreksi lewat halaman Custody.</p>
    <p x-show="!loading && result.type === 'need_unit'" x-cloak class="text-amber-700 dark:text-amber-400">
        <span class="font-semibold" x-text="result.label"></span> dicatat per unit — ketik atau scan <span x-text="result.tracking === 'roll' ? 'kode roll' : 'nomor SN'"></span>-nya.
    </p>
    <div x-show="!loading && result.type === 'choose'" x-cloak class="space-y-1">
        <p class="text-slate-500 dark:text-slate-400">Beberapa barang cocok, pilih salah satu:</p>
        <template x-for="option in (result.options || [])" :key="option.item_id">
            <button type="button" @click="q = option.code; search()"
                    class="mr-1 mb-1 inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <span class="font-mono" x-text="option.code"></span>&nbsp;—&nbsp;<span x-text="option.label"></span>
            </button>
        </template>
    </div>
    <div x-show="!loading && ['qty', 'serial', 'roll'].includes(result.type)" x-cloak
         class="flex items-center justify-between rounded-lg border border-sky-200 bg-sky-50 p-3 dark:border-sky-800/60 dark:bg-sky-950/40">
        <span class="font-semibold text-sky-800 dark:text-sky-300" x-text="result.label"></span>
        <span class="font-mono font-bold text-sky-700 dark:text-sky-200" x-text="summary()"></span>
    </div>
</div>

@once
@push('scripts')
<script>
/**
 * State bersama form Koreksi Stok. `result.type` dari endpoint resolve:
 * qty | serial | roll | choose | need_unit | none. Token permintaan membuang
 * respons basi kalau user mengetik lagi sebelum fetch selesai.
 */
function stockCorrectionForm(dataset) {
    return {
        resolveUrl: dataset.resolveUrl,
        popId: dataset.prefillPop || '',
        // Lewat dataset, bukan atribut value — x-model menimpa value awal input.
        q: dataset.prefillQ || '',
        lotNo: dataset.prefillLot || '',
        result: { type: null },
        loading: false,
        token: 0,
        init() {
            if (this.popId && this.q.length >= 2) this.search();
        },
        // Dipanggil SETIAP ketukan, sebelum debounce: hasil lama langsung
        // dibuang. Scanner barcode mengirim Enter tepat setelah SN — kalau
        // hasil lama masih nempel, form bisa POST ke SN sebelumnya.
        pending() {
            this.token++;
            this.result = { type: null };
            this.loading = this.popId !== '' && this.q.trim().length >= 2;
        },
        async search() {
            const token = ++this.token;
            if (!this.popId || this.q.trim().length < 2) { this.result = { type: null }; this.loading = false; return; }
            this.loading = true;
            try {
                const url = new URL(this.resolveUrl, window.location.origin);
                url.searchParams.set('pop_id', this.popId);
                url.searchParams.set('q', this.q.trim());
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const data = response.ok ? await response.json() : { type: 'none' };
                if (token !== this.token) return;
                this.result = data;
                if (data.type === 'qty' && !data.lots.some((lot) => lot.lot_no === this.lotNo)) {
                    this.lotNo = data.lots.length ? data.lots[0].lot_no : '';
                }
            } finally {
                if (token === this.token) this.loading = false;
            }
        },
        currentQty() {
            const lot = (this.result.lots || []).find((l) => l.lot_no === this.lotNo);
            return lot ? lot.qty : 0;
        },
        summary() {
            const r = this.result;
            if (r.type === 'qty') return `Stok sistem: ${this.currentQty()} ${r.unit || ''}`;
            if (r.type === 'serial') return `SN ${r.serial_number} · ${r.status_label}`;
            if (r.type === 'roll') return `Sisa sistem: ${r.length_remaining} m`;
            return '';
        },
    };
}
</script>
@endpush
@endonce
