@extends('layouts.app')

@section('title', 'Serah Terima Barang ke Teknisi - Whusnet Operasional')
@section('page_title', 'Serah Terima ke Teknisi')

@section('content')

<x-warehouse.header active="custody" title="Serah Terima Material ke Teknisi" subtitle="Penyerahan material instalasi atau perangkat modem ONT/Router dari Gudang Cabang ke tangan teknisi." />

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start max-w-6xl">
    <!-- Left Panel: Form Issue -->
    <div class="lg:col-span-8 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-6 shadow-xs">
        <div class="mb-6 pb-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-800/60">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Form Serah Terima Operasional</h3>
                    <p class="text-xs text-slate-400">Stok cabang langsung berkurang dan tercatat ke tangan teknisi.</p>
                </div>
            </div>
            <a href="{{ route('warehouse.custody.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                ← Kembali
            </a>
        </div>

        <form action="{{ route('warehouse.issues.store') }}" method="POST" class="space-y-6" x-data="{ cabangPopId: '{{ old('cabang_pop_id') }}' }">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="cabang_pop_id" class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">
                        Gudang Cabang Asal <span class="text-rose-500">*</span>
                    </label>
                    <select name="cabang_pop_id" id="cabang_pop_id" x-model="cabangPopId" @change="$dispatch('cabang-changed', cabangPopId)" required
                            class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                        <option value="">— Pilih Gudang Cabang —</option>
                        @foreach($cabangPops as $pop)
                        <option value="{{ $pop->id }}" {{ old('cabang_pop_id') == $pop->id ? 'selected' : '' }}>
                            {{ $pop->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="technician_id" class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">
                        Teknisi Lapangan Penerima <span class="text-rose-500">*</span>
                    </label>
                    <select name="technician_id" id="technician_id" required
                            class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                        <option value="">— Pilih Teknisi —</option>
                        @foreach($technicians as $technician)
                        <option value="{{ $technician->id }}" {{ old('technician_id') == $technician->id ? 'selected' : '' }}>
                            {{ $technician->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-200">Barang / SN yang Diserahkan <span class="text-rose-500">*</span></label>
                    <span class="text-[11px] text-slate-400 font-medium">Klik chip SN / Qty di panel kanan untuk mengisi otomatis</span>
                </div>
                <x-inventory-line-rows name="lines" :items="$items" />
            </div>

            <div class="pt-5 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <a href="{{ route('warehouse.custody.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-indigo-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>Simpan Serah Terima Barang</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Right Panel: Available Stock Quick Picker -->
    <div class="lg:col-span-4 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-xs sticky top-20"
        x-data="warehouseStockPanel(@js(route('warehouse.issues.available-stock')))"
        @cabang-changed.window="load($event.detail)"
        @barcode-detected.window="$event.detail.target === 'issue-dispatch' && onScan($event.detail.code)">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60 mb-3">
            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                <span>Stok di Cabang Ini</span>
            </h4>
            <span class="text-[10px] text-slate-400" x-show="popId && !loading" x-text="items.length + ' item'"></span>
        </div>

        {{--
            Scan Kamera — validasi barang beneran ada di stok Cabang ini
            SEBELUM diserahkan ke teknisi (sama alasan Transfer Dispatch di
            `warehouse/transfers/create.blade.php`, lihat komentar di sana).
        --}}
        <div x-show="popId" x-cloak class="mb-3">
            <x-warehouse.barcode-scanner target="issue-dispatch" />

            <div x-show="mismatches.length > 0" x-cloak class="mt-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 rounded-xl p-3">
                <div class="flex items-center justify-between mb-1.5">
                    <p class="text-[11px] font-bold text-rose-800 dark:text-rose-300">⚠ SN Gak Ada di Stok Cabang Ini</p>
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
                <p class="text-xs">Pilih Gudang Cabang di sebelah kiri untuk melihat stok siap ambil.</p>
            </div>
        </template>

        <template x-if="popId && loading">
            <div class="py-8 text-center text-slate-400 flex items-center justify-center gap-2">
                <svg class="w-4 h-4 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span class="text-xs">Memuat data stok cabang...</span>
            </div>
        </template>

        <template x-if="popId && !loading && items.length === 0">
            <p class="py-8 text-center text-xs text-slate-400 italic">Stok kosong di cabang ini.</p>
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

        // Lihat komentar sama di transfers/create.blade.php — SN hasil scan
        // dicek terhadap stok cabang yang lagi ke-load, valid → auto-pick,
        // gak ketemu → mismatch (bukan diabaikan diam-diam).
        onScan(code) {
            const match = this.items.find(item => Array.isArray(item.serials) && item.serials.includes(code));

            if (match) {
                this.$dispatch('pick-serial', { itemId: match.item_id, serialNumber: code });
                window.Toast?.success('SN Terinput', `"${code}" (${match.name}) masuk daftar serah terima.`, 2000);

                return;
            }

            if (! this.mismatches.includes(code)) {
                this.mismatches.push(code);
            }

            window.Toast?.warning('SN Tidak Ditemukan', `"${code}" bukan stok Gudang Cabang ini — cek lagi nomor serinya.`);
        },
    };
}
</script>
@endpush

@vite(['resources/js/barcode-scan.js'])

@endsection
