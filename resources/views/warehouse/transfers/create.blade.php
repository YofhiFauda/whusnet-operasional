@extends('layouts.app')

@section('title', 'Transfer Antar Gudang Cabang - Whusnet Operasional')
@section('page_title', 'Transfer Antar Gudang')

@section('content')

<x-warehouse.header active="stock" title="Transfer Distribusi Antar Gudang" subtitle="Pengiriman stok material atau perangkat aktif dari Gudang Pusat ke Gudang Cabang (POP)." />

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start max-w-6xl">
    <!-- Left Panel: Form Transfer -->
    <div class="lg:col-span-8 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-6 shadow-xs">
        <div class="mb-6 pb-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-800/60">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Surat Jalan Transfer Logistik</h3>
                    <p class="text-xs text-slate-400">Stok Pusat berkurang saat dibuat, stok Cabang bertambah setelah konfirmasi terima.</p>
                </div>
            </div>
            <a href="{{ route('warehouse.stock.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                ← Kembali
            </a>
        </div>

        <form action="{{ route('warehouse.transfers.store') }}" method="POST" class="space-y-6" x-data="{ fromPopId: '{{ old('from_pop_id') }}' }">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="from_pop_id" class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">
                        Dari Gudang Asal (Pusat) <span class="text-rose-500">*</span>
                    </label>
                    <select name="from_pop_id" id="from_pop_id" x-model="fromPopId" @change="$dispatch('pusat-changed', fromPopId)" required
                            class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">— Pilih Gudang Asal —</option>
                        @foreach($fromPops as $pop)
                        <option value="{{ $pop->id }}" {{ old('from_pop_id') == $pop->id ? 'selected' : '' }}>
                            {{ $pop->name }} ({{ strtoupper($pop->type) }})
                        </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="to_pop_id" class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">
                        Ke Gudang Tujuan (Cabang) <span class="text-rose-500">*</span>
                    </label>
                    <select name="to_pop_id" id="to_pop_id" required
                            class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">— Pilih Gudang Tujuan —</option>
                        @foreach($toPops as $pop)
                        <option value="{{ $pop->id }}" {{ old('to_pop_id') == $pop->id ? 'selected' : '' }}>
                            {{ $pop->name }} ({{ strtoupper($pop->type) }})
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-200">Barang &amp; Nomor Seri yang Ditransfer <span class="text-rose-500">*</span></label>
                    <span class="text-[11px] text-slate-400 font-medium">Klik chip SN / Qty di panel kanan untuk mengisi cepat</span>
                </div>
                <x-inventory-line-rows name="lines" :items="$items" />
            </div>

            <div class="pt-5 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <a href="{{ route('warehouse.stock.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <span>Kirim Transfer Sekarang</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Right Panel: Available Stock Quick Picker -->
    <div class="lg:col-span-4 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-xs sticky top-20"
        x-data="warehouseStockPanel(@js(route('warehouse.transfers.available-stock')))"
        @pusat-changed.window="load($event.detail)"
        @barcode-detected.window="$event.detail.target === 'transfer-dispatch' && onScan($event.detail.code)">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60 mb-3">
            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                <span>Stok Siap Kirim</span>
            </h4>
            <span class="text-[10px] text-slate-400" x-show="popId && !loading" x-text="items.length + ' item'"></span>
        </div>

        {{--
            Scan Kamera — VALIDASI SEKALIGUS PILIH (2026-09-04, klarifikasi
            user). Beda dari kegunaan di halaman lain: di sini scan BUKAN
            cuma mempercepat isi form, tapi jawaban buat "100 barang masuk,
            gimana milih SN yang bener buat 1 cabang tanpa scroll list satu
            per satu". SN yang di-scan dicek TERHADAP `items` yang lagi
            ke-load (stok Gudang Asal yang dipilih) — ketemu → otomatis
            "pick" (sama efeknya klik chip di bawah), gak ketemu → SN itu
            gak ada di gudang ini (kepilih gudang asal salah, atau barang
            emang bukan di sini), langsung ketahuan lewat daftar mismatch,
            bukan ketauan belakangan pas surat jalan gak sesuai fisik.
        --}}
        <div x-show="popId" x-cloak class="mb-3">
            <x-warehouse.barcode-scanner target="transfer-dispatch" />

            <div x-show="mismatches.length > 0" x-cloak class="mt-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 rounded-xl p-3">
                <div class="flex items-center justify-between mb-1.5">
                    <p class="text-[11px] font-bold text-rose-800 dark:text-rose-300">⚠ SN Gak Ada di Stok Gudang Ini</p>
                    <button type="button" @click="mismatches = []" class="text-[10px] font-semibold text-rose-500 hover:text-rose-600 cursor-pointer">Tutup</button>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="code in mismatches" :key="code">
                        <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800" x-text="code"></span>
                    </template>
                </div>
            </div>
        </div>

        <template x-if="!popId">
            <div class="py-10 text-center text-slate-400">
                <svg class="w-8 h-8 mx-auto mb-2 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <p class="text-xs">Pilih Gudang Asal di atas untuk menampilkan stok yang tersedia.</p>
            </div>
        </template>

        <template x-if="popId && loading">
            <div class="py-8 text-center text-slate-400 flex items-center justify-center gap-2">
                <svg class="w-4 h-4 animate-spin text-sky-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span class="text-xs">Memuat data stok...</span>
            </div>
        </template>

        <template x-if="popId && !loading && items.length === 0">
            <p class="py-8 text-center text-xs text-slate-400 italic">Stok kosong di gudang ini.</p>
        </template>

        <div class="space-y-3 max-h-96 overflow-y-auto pr-1" x-show="popId && !loading && items.length > 0">
            <template x-for="item in items" :key="item.item_id">
                <div class="bg-slate-50/70 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/60 rounded-xl p-3">
                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200" x-text="item.name"></p>

                    <template x-if="item.serials">
                        <div class="flex flex-wrap gap-1 mt-2">
                            <template x-for="sn in item.serials" :key="sn">
                                <button type="button" @click="$dispatch('pick-serial', { itemId: item.item_id, serialNumber: sn })"
                                    class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-lg bg-white dark:bg-slate-800 border border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-300 hover:bg-sky-500 hover:text-white transition-colors cursor-pointer"
                                    title="Klik untuk memilih SN ini"
                                    x-text="sn"></button>
                            </template>
                        </div>
                    </template>

                    <template x-if="item.lots">
                        <div class="flex flex-wrap gap-1 mt-2">
                            <template x-for="lot in item.lots" :key="lot.lot_no ?? '-'">
                                <button type="button" @click="$dispatch('pick-qty', { itemId: item.item_id, lotNo: lot.lot_no, qty: lot.qty })"
                                    class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-lg bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300 hover:bg-purple-500 hover:text-white transition-colors cursor-pointer"
                                    title="Klik untuk memilih lot/qty ini">
                                    <span x-show="lot.lot_no" x-text="lot.lot_no + ': '"></span><span x-text="lot.qty"></span> <span x-text="item.unit"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
function warehouseStockPanel(endpoint) {
    return {
        endpoint: endpoint,
        popId: '',
        loading: false,
        items: [],
        mismatches: [],

        load(popId) {
            this.popId = popId;
            this.mismatches = [];

            if (! popId) {
                this.items = [];
                return;
            }

            this.loading = true;
            fetch(`${this.endpoint}?pop_id=${popId}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => { this.items = data.items || []; })
                .catch(() => { this.items = []; })
                .finally(() => { this.loading = false; });
        },

        // SN hasil scan dicek terhadap stok yang LAGI KE-LOAD (`items`,
        // sudah discope ke gudang asal yang dipilih) — ketemu di situ =
        // valid buat gudang ini, langsung "pick" (sama efek klik chip).
        // Gak ketemu = SN itu bukan stok gudang ini, ditampilkan sebagai
        // mismatch (bukan diam-diam diabaikan).
        onScan(code) {
            const match = this.items.find(item => Array.isArray(item.serials) && item.serials.includes(code));

            if (match) {
                this.$dispatch('pick-serial', { itemId: match.item_id, serialNumber: code });
                window.Toast?.success('SN Terinput', `"${code}" (${match.name}) masuk daftar transfer.`, 2000);

                return;
            }

            if (! this.mismatches.includes(code)) {
                this.mismatches.push(code);
            }

            window.Toast?.warning('SN Tidak Ditemukan', `"${code}" bukan stok Gudang Asal yang dipilih — cek lagi nomor serinya atau gudang asalnya.`);
        },
    };
}
</script>
@endpush

@vite(['resources/js/barcode-scan.js'])

@endsection
