@extends('layouts.app')

@section('title', 'Transfer Distribusi Antar Gudang - Whusnet Operasional')
@section('page_title', 'Transfer Antar Gudang')

@section('content')

@php
    $user = auth()->user();
    $canViewWarehouse = $user?->hasPermission('warehouse.view') ?? false;
    $canViewCustody = $user?->hasPermission('warehouse_custody.view') ?? false;
    $canViewTraceability = $user?->hasPermission('warehouse_traceability.view') ?? false;
    $canViewReport = $user?->hasPermission('warehouse_report.view') ?? false;
    $canViewStockRequest = $user?->hasPermission('warehouse_stock_request.view') ?? false;
    $canReceive = $user?->hasPermission('warehouse_transfer.create') ?? false;
    $canTransfer = $user?->hasPermission('warehouse_transfer.create') ?? false;
    $canIssue = $user?->hasPermission('warehouse_issue.create') ?? false;
    $canAdjust = $user?->hasPermission('warehouse_adjustment.create') ?? false;

    $serializedItems = $items->where('tracking_type', \App\Enums\TrackingType::SERIALIZED)->values();
    $serializedCategoryOptions = $serializedItems
        ->pluck('category')
        ->filter()
        ->unique('id')
        ->sortBy('name')
        ->values();

    $itemOptions = collect($items)->map(fn ($item) => [
        'id' => $item->id,
        'code' => $item->code,
        'name' => $item->name,
        'label' => "{$item->code} — {$item->name}",
        'tracking_type' => $item->tracking_type->value,
        'unit' => $item->unit,
        'category_id' => $item->item_category_id,
        'category_name' => $item->category?->name ?? 'Tanpa Kategori',
    ])->values();

    $categoryOptions = collect($items)
        ->pluck('category')
        ->filter()
        ->unique('id')
        ->sortBy('name')
        ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])
        ->values();
@endphp

<div x-data="warehouseTransferManager(
        @js($itemOptions),
        @js($categoryOptions),
        @js(old('lines', [])),
        @js(route('warehouse.transfers.available-stock')),
        '{{ old('from_pop_id', request()->query('pop_id', '')) }}',
        '{{ old('to_pop_id', request()->query('to_pop_id', '')) }}'
     )"
     @pick-serial.window="onPickSerial($event.detail)"
     @pick-qty.window="onPickQty($event.detail)"
     @barcode-detected.window="$event.detail.target === 'transfer-dispatch' && onScan($event.detail.code)"
     class="space-y-4 pb-0 sm:pb-0 lg:pb-8">

    {{-- Header terpadu Gudang (Desktop & Mobile Responsive) --}}
    <x-warehouse.header
        active="stock"
        title="Transfer Antar Gudang"
        subtitle="Distribusi stok material & perangkat Pusat $\rightarrow$ Cabang"
        backUrl="{{ route('warehouse.stock.index') }}"
    />

    <form id="transfer-form" action="{{ route('warehouse.transfers.store') }}" method="POST" class="space-y-4">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
            
            {{-- ========================================================
                 KOLOM UTAMA (KIRI - LG: 8 KOLOM)
                 Rute Logistik, Asisten Dispatch, & Daftar Rincian Barang
                 ======================================================== --}}
            <div class="lg:col-span-8 space-y-4">
                
                {{-- 1. KARTU RUTE & SURAT JALAN TRANSFER LOGISTIK --}}
                <div class="bg-white dark:bg-slate-800/95 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-5 shadow-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 sm:gap-4 items-center">
                        {{-- Asal --}}
                        <div class="sm:col-span-5">
                            <label for="from_pop_id" class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center justify-between">
                                <span>Dari Gudang Asal (Pengirim) <span class="text-rose-500">*</span></span>
                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300">ORIGIN</span>
                            </label>
                            <div class="relative">
                                <select name="from_pop_id" id="from_pop_id" x-model="fromPopId" @change="onFromPopChange()" required
                                        class="w-full min-h-[44px] text-xs font-semibold pl-9 pr-8 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/60 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all cursor-pointer">
                                    <option value="">— Pilih Gudang Asal —</option>
                                    @foreach($fromPops as $pop)
                                    <option value="{{ $pop->id }}">
                                        {{ $pop->name }} ({{ strtoupper($pop->type) }})
                                    </option>
                                    @endforeach
                                </select>
                                <svg class="w-4 h-4 text-sky-600 dark:text-sky-400 absolute left-3 top-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                                </svg>
                            </div>
                        </div>

                        {{-- Directional Connector Icon --}}
                        <div class="sm:col-span-2 flex justify-center py-1 sm:py-0">
                            <div class="w-5 h-5 rounded-full bg-slate-100 dark:bg-slate-700/60 border border-slate-200 dark:border-slate-600 flex items-center justify-center text-slate-500 dark:text-slate-300 shadow-xs">
                                <svg class="w-4 h-4 rotate-90 sm:rotate-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                                </svg>
                            </div>
                        </div>

                        {{-- Tujuan --}}
                        <div class="sm:col-span-5">
                            <label for="to_pop_id" class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center justify-between">
                                <span>Ke Gudang Tujuan (Penerima) <span class="text-rose-500">*</span></span>
                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300">DESTINATION</span>
                            </label>
                            <div class="relative">
                                <select name="to_pop_id" id="to_pop_id" x-model="toPopId" required
                                        class="w-full min-h-[44px] text-xs font-semibold pl-9 pr-8 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/60 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all cursor-pointer">
                                    <option value="">— Pilih Gudang Tujuan —</option>
                                    @foreach($toPops as $pop)
                                    <option value="{{ $pop->id }}">
                                        {{ $pop->name }} ({{ strtoupper($pop->type) }})
                                    </option>
                                    @endforeach
                                </select>
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400 absolute left-3 top-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Warning jika gudang asal == tujuan --}}
                    <div x-show="fromPopId && toPopId && fromPopId === toPopId" x-cloak class="mt-3.5 p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-lg text-xs text-rose-700 dark:text-rose-300 flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        <span>Gudang asal dan tujuan tidak boleh sama. Silakan pilih cabang penerima yang berbeda.</span>
                    </div>
                </div>

                {{-- 2. ASISTEN CERDAS DISPATCH (COMPACT & COLLAPSIBLE DRAWER) --}}
                <div class="bg-white dark:bg-slate-800/95 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-xs"
                     x-data="{
                        assistantMode: '',
                        bulkInput: '',
                        bulkParsedCount: 0,
                        
                        updateBulkCount() {
                            const codes = (this.bulkInput || '').split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0);
                            this.bulkParsedCount = codes.length;
                        },
                        submitBulk() {
                            if (! fromPopId) {
                                window.Toast?.warning('Pilih Gudang Asal', 'Pilih Gudang Asal terlebih dahulu sebelum memproses nomor seri.');
                                return;
                            }
                            processBulk(this.bulkInput);
                            this.bulkInput = '';
                            this.bulkParsedCount = 0;
                        }
                     }">
                    
                    {{-- Compact Mode Switcher Bar --}}
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                            <span>Asisten Dispatch Cepat</span>
                        </span>

                        <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-900/80 rounded-lg border border-slate-200/60 dark:border-slate-700/60">
                            <button type="button" @click="assistantMode = assistantMode === 'camera' ? '' : 'camera'"
                                    :class="assistantMode === 'camera' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 font-semibold hover:text-slate-900 dark:hover:text-slate-200'"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg transition-colors duration-150 cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.174C3.244 7.54 2.5 8.352 2.5 9.318v9.132a2.25 2.25 0 002.25 2.25h14.5a2.25 2.25 0 002.25-2.25V9.318c0-.966-.744-1.778-1.552-1.914a48.11 48.11 0 00-1.134-.174 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                                <span>Scan Kamera</span>
                            </button>
                            <button type="button" @click="assistantMode = assistantMode === 'bulk' ? '' : 'bulk'"
                                    :class="assistantMode === 'bulk' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 font-semibold hover:text-slate-900 dark:hover:text-slate-200'"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg transition-colors duration-150 cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Bulk Paste</span>
                            </button>
                        </div>
                    </div>

                    {{-- Warning jika belum pilih Gudang Asal --}}
                    <div x-show="!fromPopId" class="mt-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        <span>Pilih <strong>Gudang Asal</strong> di atas untuk memuat data stok dan mengaktifkan scanner.</span>
                    </div>

                    {{-- Collapsible Assistant Drawer Container --}}
                    <div x-show="fromPopId && assistantMode !== ''" x-cloak x-collapse.duration.200ms class="mt-3.5 pt-3.5 border-t border-slate-100 dark:border-slate-700/60">
                        {{-- Drawer Mode 1: Scan Kamera Barcode --}}
                        <div x-show="assistantMode === 'camera'"
                             x-transition:enter="transition-opacity ease-out duration-150"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             class="space-y-3">
                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span>Arahkan kamera ke barcode SN modem / perangkat yang siap dikirim.</span>
                                <span class="text-[11px] font-mono text-sky-600 dark:text-sky-400 font-bold" x-text="availableStockItems.length + ' Jenis Item Tersedia'"></span>
                            </div>

                            <x-warehouse.barcode-scanner target="transfer-dispatch" />
                        </div>

                        {{-- Drawer Mode 2: Bulk Paste Banyak SN --}}
                        <div x-show="assistantMode === 'bulk'"
                             x-transition:enter="transition-opacity ease-out duration-150"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             class="space-y-3">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Tempel Daftar Nomor Seri (1 per baris atau dipisahkan koma)
                                    </label>
                                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full"
                                          :class="bulkParsedCount > 0 ? 'bg-sky-100 dark:bg-sky-900/60 text-sky-700 dark:text-sky-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-400'"
                                          x-text="bulkParsedCount + ' SN Terdeteksi'"></span>
                                </div>
                                <textarea x-model="bulkInput" @input="updateBulkCount()" rows="3"
                                          placeholder="Tempel nomor seri di sini...&#10;FHTT0019284&#10;ZTEG0092811"
                                          class="w-full font-mono text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500"></textarea>
                                
                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <p class="text-[10px] text-slate-400">Sistem otomatis mencocokkan SN dengan stok Gudang Asal.</p>
                                    <button type="button" @click="submitBulk()" :disabled="bulkParsedCount === 0"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 dark:disabled:bg-slate-800 disabled:text-slate-400 text-white rounded-lg text-xs font-bold transition-all shadow-xs shrink-0 cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                        <span>Proses Batch SN</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Mismatch Alert Banner --}}
                    <div x-show="mismatches.length > 0" x-cloak class="mt-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 rounded-lg p-3">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                                <p class="text-xs font-bold text-rose-800 dark:text-rose-300">SN Tidak Ditemukan di Stok Asal</p>
                            </div>
                            <button type="button" @click="mismatches = []" class="text-[10px] font-semibold text-rose-500 hover:text-rose-600 cursor-pointer">Tutup</button>
                        </div>
                        <p class="text-[11px] text-rose-700/90 dark:text-rose-400 mb-2">
                            Nomor seri berikut tidak berstatus <em>AVAILABLE</em> di Gudang Asal:
                        </p>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="code in mismatches" :key="code">
                                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-md bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800" x-text="code"></span>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- 3. DAFTAR BARIS BARANG YANG DITRANSFER --}}
                <div class="bg-white dark:bg-slate-800/95 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">
                                Daftar Barang
                            </h3>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700"
                                  x-text="rows.length + ' Item'"></span>
                        </div>

                        <button type="button" @click="addRow()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800/80 rounded-lg transition-all shadow-xs shrink-0 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            <span>Tambah Item</span>
                        </button>
                    </div>

                    {{-- Empty State --}}
                    <template x-if="rows.length === 0">
                        <div class="py-10 px-4 text-center border-2 border-dashed border-slate-200 dark:border-slate-700/80 rounded-lg bg-slate-50/50 dark:bg-slate-900/30">
                            <h4 class="text-xs font-bold text-slate-700 dark:text-slate-200">Belum ada barang pada transfer ini</h4>
                            <p class="text-[11px] text-slate-400 mt-0.5">Gunakan asisten di atas, klik chip stok siap kirim, atau klik tambah item.</p>
                            <button type="button" @click="addRow()" class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-lg transition-all shadow-xs cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                <span>Tambah Item Pertama</span>
                            </button>
                        </div>
                    </template>

                    {{-- Dynamic Repeatable Rows --}}
                    <div class="space-y-3.5">
                        <template x-for="(row, index) in rows" :key="index">
                            <div class="bg-slate-50/80 dark:bg-slate-900/60 border border-slate-200/90 dark:border-slate-700/80 rounded-lg p-3.5 sm:p-4 space-y-3 transition-all">
                                
                                {{-- Baris Header --}}
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-5 h-5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-[11px] font-bold flex items-center justify-center border border-slate-200 dark:border-slate-700 shadow-xs" x-text="index + 1"></span>
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Barang #<span x-text="index + 1"></span></span>

                                        <template x-if="row.tracking_type === 'serialized'">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-bold bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-400 border border-sky-200 dark:border-sky-800">
                                                SERIAL NUMBER
                                            </span>
                                        </template>
                                        <template x-if="row.tracking_type === 'batch'">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-bold bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-800">
                                                LOT KABEL
                                            </span>
                                        </template>
                                        <template x-if="row.tracking_type === 'quantity'">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                                REGULER
                                            </span>
                                        </template>
                                    </div>

                                    <button type="button" @click="removeRow(index)" class="p-1.5 text-rose-500 hover:text-rose-700 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition-colors cursor-pointer" title="Hapus baris">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- Dropdown Kategori & Barang --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                    <div>
                                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Kategori</label>
                                        <select x-model="row.category_id" @change="row.item_id = ''; row.tracking_type = ''; row.unit = '';"
                                                class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                            <option value="">-- Semua Kategori --</option>
                                            <template x-for="cat in categoryOptions" :key="cat.id">
                                                <option :value="cat.id" x-text="cat.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Nama Barang <span class="text-rose-500">*</span></label>
                                        <select :name="`lines[${index}][item_id]`" x-model="row.item_id" @change="onItemChange(index)" required
                                                class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                            <option value="">— Pilih Barang —</option>
                                            <template x-for="opt in filteredItemOptions(row)" :key="opt.id">
                                                <option :value="opt.id" x-text="opt.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>

                                {{-- Mode 1: Serialized Item --}}
                                <template x-if="row.tracking_type === 'serialized'">
                                    <div class="bg-sky-50/40 dark:bg-sky-950/20 border border-sky-100 dark:border-sky-900/40 rounded-lg p-3 space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <label class="text-[10px] font-bold uppercase tracking-wider text-sky-900 dark:text-sky-300">
                                                Daftar Nomor Seri yang Ditransfer (1 Baris per Unit)
                                            </label>
                                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full"
                                                  :class="serialCount(row) > 0 ? 'bg-sky-100 dark:bg-sky-900/60 text-sky-700 dark:text-sky-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-500'"
                                                  x-text="serialCount(row) + ' Unit SN'"></span>
                                        </div>

                                        <textarea :name="`lines[${index}][serial_numbers]`" x-model="row.serial_numbers" rows="2" required
                                                  placeholder="ZTEG00123&#10;ZTEG00124"
                                                  class="w-full text-xs font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500"></textarea>
                                        <p class="text-[10px] text-slate-400">Tips: Klik chip nomor seri di panel Stok Siap Kirim untuk memasukkan SN secara instan.</p>
                                    </div>
                                </template>

                                {{-- Mode 2: Quantity & Batch Item --}}
                                <template x-if="row.tracking_type !== 'serialized' && row.tracking_type !== ''">
                                    <div class="bg-slate-100/70 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/60 rounded-lg p-3 space-y-2.5">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 items-end">
                                            <div>
                                                <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                                                    <span>Jumlah (</span><span class="text-sky-600 dark:text-sky-400" x-text="row.unit || 'Unit'"></span><span>)</span> <span class="text-rose-500">*</span>
                                                </label>
                                                <input type="number" step="0.01" min="0.01" :name="`lines[${index}][qty]`" x-model="row.qty" required placeholder="0.00"
                                                       class="w-full min-h-[42px] px-3 py-2 text-xs font-mono font-bold border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                            </div>

                                            <template x-if="row.tracking_type === 'batch'">
                                                <div>
                                                    <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">No. Lot / Drum</label>
                                                    <input type="text" :name="`lines[${index}][lot_no]`" x-model="row.lot_no" placeholder="mis. LOT-001"
                                                           class="w-full min-h-[42px] px-3 py-2 text-xs font-mono border border-purple-200 dark:border-purple-800 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Mobile Bottom Clearance Spacer to prevent floating bar overlap --}}
                <div class="h-12 sm:h-22 lg:hidden" aria-hidden="true"></div>
            </div>

            {{-- ========================================================
                 KOLOM SIDEBAR (DESKTOP STICKY - LG: 4 KOLOM)
                 ======================================================== --}}
            <div class="hidden lg:block lg:col-span-4 space-y-4 sticky top-20">
                
                {{-- 1. Ringkasan Dispatch Desktop --}}
                <div class="bg-white dark:bg-slate-800/95 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                        <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                            <span>Ringkasan Dispatch</span>
                        </h4>
                        <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded-md bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400">
                            LIVE
                        </span>
                    </div>

                    {{-- Breakdown Metrik --}}
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700/60">
                            <span class="block text-[10px] font-semibold text-slate-400">Jenis Barang</span>
                            <span class="text-sm font-bold font-mono text-slate-800 dark:text-slate-100" x-text="validItemCount + ' Item'">0 Item</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700/60">
                            <span class="block text-[10px] font-semibold text-slate-400">Total SN</span>
                            <span class="text-sm font-bold font-mono text-sky-600 dark:text-sky-400" x-text="totalSnCount + ' Unit'">0 Unit</span>
                        </div>
                        <div class="col-span-2 p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                            <span class="text-[10px] font-semibold text-slate-400">Total Qty Reguler / Lot:</span>
                            <span class="text-xs font-bold font-mono text-slate-700 dark:text-slate-200" x-text="totalQtyCount + ' Qty'">0 Qty</span>
                        </div>
                    </div>

                    {{-- Checklist Status --}}
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 space-y-1.5 text-xs">
                        <div class="flex items-center gap-2">
                            <span :class="fromPopId ? 'text-emerald-500 font-bold' : 'text-slate-300'">✓</span>
                            <span :class="fromPopId ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400'">Gudang Asal Terpilih</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span :class="toPopId && toPopId !== fromPopId ? 'text-emerald-500 font-bold' : 'text-slate-300'">✓</span>
                            <span :class="toPopId && toPopId !== fromPopId ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400'">Gudang Tujuan Valid</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span :class="validItemCount > 0 ? 'text-emerald-500 font-bold' : 'text-slate-300'">✓</span>
                            <span :class="validItemCount > 0 ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400'">Minimal 1 Baris Barang</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span :class="isDetailsComplete ? 'text-emerald-500 font-bold' : 'text-slate-300'">✓</span>
                            <span :class="isDetailsComplete ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400'">Nomor Seri / Qty Lengkap</span>
                        </div>
                    </div>

                    {{-- CTA --}}
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 space-y-2">
                        <button type="submit" form="transfer-form"
                                :disabled="!isFormValid"
                                class="w-full min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 dark:disabled:bg-slate-700 disabled:text-slate-400 text-white rounded-lg text-xs font-bold shadow-xs transition-all cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <span>Kirim Transfer Sekarang</span>
                        </button>
                    </div>
                </div>

                {{-- 2. PANEL STOK SIAP KIRIM (QUICK PICKER DARI GUDANG ASAL) --}}
                <div class="bg-white dark:bg-slate-800/95 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-xs space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-700/60">
                        <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Stok Siap Kirim</span>
                        </h4>
                        <span class="text-[10px] text-slate-400" x-show="fromPopId && !loadingStock" x-text="filteredAvailableStock.length + ' item'"></span>
                    </div>

                    {{-- Search Filter Input for Available Stock --}}
                    <div x-show="fromPopId && availableStockItems.length > 0" class="relative">
                        <input type="text" x-model="stockSearchQuery" placeholder="Cari nama barang atau SN..."
                               class="w-full text-xs font-medium pl-8 pr-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                    </div>

                    {{-- Prompt Belum Pilih Gudang --}}
                    <template x-if="!fromPopId">
                        <p class="py-4 text-center text-[11px] text-slate-400">Pilih Gudang Asal untuk memuat stok fisik siap kirim.</p>
                    </template>

                    {{-- Loading State --}}
                    <template x-if="fromPopId && loadingStock">
                        <div class="py-4 text-center text-slate-400 flex items-center justify-center gap-2">
                            <svg class="w-4 h-4 animate-spin text-sky-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="text-xs">Memuat data stok...</span>
                        </div>
                    </template>

                    {{-- Empty State --}}
                    <template x-if="fromPopId && !loadingStock && availableStockItems.length === 0">
                        <p class="py-4 text-center text-xs text-slate-400 italic">Stok kosong di gudang asal ini.</p>
                    </template>

                    {{-- Available Stock Item List & Chip Quick Pickers --}}
                    <div class="space-y-2.5 max-h-72 overflow-y-auto scroll-smooth pr-1" x-show="fromPopId && !loadingStock && availableStockItems.length > 0">
                        <template x-for="item in filteredAvailableStock" :key="item.item_id">
                            <div class="bg-slate-50/70 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/60 rounded-lg p-2.5">
                                <div class="flex items-center justify-between gap-1">
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200" x-text="item.name"></p>
                                    <span class="text-[10px] font-mono text-slate-400" x-text="item.unit"></span>
                                </div>

                                {{-- Serialized Item: Chip SN --}}
                                <template x-if="item.serials && item.serials.length > 0">
                                    <div class="mt-1.5">
                                        <div class="flex flex-wrap gap-1">
                                            <template x-for="sn in item.serials" :key="sn">
                                                <button type="button" @click="$dispatch('pick-serial', { itemId: item.item_id, serialNumber: sn })"
                                                    class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded-md bg-white dark:bg-slate-800 border border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-300 hover:bg-sky-600 hover:text-white transition-colors cursor-pointer"
                                                    title="Klik untuk memasukkan SN ini"
                                                    x-text="sn"></button>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- Batch / Lot Item: Chip Lot & Qty --}}
                                <template x-if="item.lots && item.lots.length > 0">
                                    <div class="mt-1.5">
                                        <div class="flex flex-wrap gap-1">
                                            <template x-for="lot in item.lots" :key="lot.lot_no ?? '-'">
                                                <button type="button" @click="$dispatch('pick-qty', { itemId: item.item_id, lotNo: lot.lot_no, qty: lot.qty })"
                                                    class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded-md bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300 hover:bg-purple-600 hover:text-white transition-colors cursor-pointer"
                                                    title="Klik untuk memilih lot/qty ini">
                                                    <span x-show="lot.lot_no" x-text="lot.lot_no + ': '"></span><span x-text="lot.qty"></span> <span x-text="item.unit"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- 3. SOP Card --}}
                <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-xs space-y-2 text-[11px] text-slate-500 dark:text-slate-400">
                    <h5 class="text-xs font-bold text-slate-800 dark:text-slate-200">📌 Panduan Mutasi</h5>
                    <p>&bull; <strong>Status In-Transit:</strong> Stok asal berkurang saat transfer diterbitkan.</p>
                    <p>&bull; <strong>Verifikasi Cabang:</strong> Admin tujuan wajib konfirmasi fisik saat tiba.</p>
                </div>
            </div>
        </div>

        {{-- ========================================================
             MOBILE & TABLET FIXED BOTTOM COMMAND BAR & BOTTOM SHEET
             Offset pada tablet (md:left-64) agar tidak menutupi sidebar
             ======================================================== --}}
        <div class="lg:hidden" x-data="{ openMobileSheet: false }">
            
            {{-- Mobile / Tablet Bottom Sheet Backdrop --}}
            <div x-show="openMobileSheet" x-cloak
                 @click="openMobileSheet = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 md:left-64 bg-slate-900/60 backdrop-blur-xs z-50"></div>

            {{-- Mobile / Tablet Bottom Sheet Drawer --}}
            <div x-show="openMobileSheet" x-cloak
                 x-transition:enter="transition ease-out duration-250 transform"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 class="fixed inset-x-0 md:left-64 md:right-0 bottom-0 max-h-[85vh] bg-white dark:bg-slate-900 rounded-t-3xl border-t border-slate-200 dark:border-slate-700 p-5 z-50 overflow-y-auto scroll-smooth space-y-4 shadow-2xl">
                
                <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full mx-auto -mt-1 mb-2"></div>
                
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">
                        Rincian Pengiriman &amp; Validasi
                    </h4>
                    <button type="button" @click="openMobileSheet = false" class="p-1 text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Metric Box Mobile --}}
                <div class="grid grid-cols-2 gap-2 text-center text-xs">
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                        <span class="block text-[10px] text-slate-400">Total Item</span>
                        <span class="font-bold font-mono text-slate-800 dark:text-slate-100" x-text="validItemCount + ' Item'"></span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                        <span class="block text-[10px] text-slate-400">Total SN</span>
                        <span class="font-bold font-mono text-sky-600 dark:text-sky-400" x-text="totalSnCount + ' Unit'"></span>
                    </div>
                </div>

                {{-- Checklist Mobile / Tablet --}}
                <div class="space-y-2 text-xs pt-1">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Status Kelengkapan:</span>
                    <div class="flex items-center gap-2">
                        <span :class="fromPopId ? 'text-emerald-500 font-bold' : 'text-slate-300'">✓</span>
                        <span :class="fromPopId ? 'text-slate-700 dark:text-slate-200 font-semibold' : 'text-slate-400'">Gudang Asal Terpilih</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="toPopId && toPopId !== fromPopId ? 'text-emerald-500 font-bold' : 'text-slate-300'">✓</span>
                        <span :class="toPopId && toPopId !== fromPopId ? 'text-slate-700 dark:text-slate-200 font-semibold' : 'text-slate-400'">Gudang Tujuan Valid (Berbeda)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="validItemCount > 0 ? 'text-emerald-500 font-bold' : 'text-slate-300'">✓</span>
                        <span :class="validItemCount > 0 ? 'text-slate-700 dark:text-slate-200 font-semibold' : 'text-slate-400'">Minimal 1 Baris Barang</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="isDetailsComplete ? 'text-emerald-500 font-bold' : 'text-slate-300'">✓</span>
                        <span :class="isDetailsComplete ? 'text-slate-700 dark:text-slate-200 font-semibold' : 'text-slate-400'">Nomor Seri / Qty Lengkap</span>
                    </div>
                </div>

                {{-- Mobile Available Stock Quick Tap --}}
                <div x-show="fromPopId && availableStockItems.length > 0" class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pilih Cepat dari Stok Asal:</span>
                    </div>
                    <div class="space-y-2 max-h-48 overflow-y-auto scroll-smooth">
                        <template x-for="item in availableStockItems" :key="item.item_id">
                            <div class="p-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 text-xs">
                                <p class="font-bold text-slate-800 dark:text-slate-200 mb-1" x-text="item.name"></p>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="sn in (item.serials || [])" :key="sn">
                                        <button type="button" @click="$dispatch('pick-serial', { itemId: item.item_id, serialNumber: sn }); openMobileSheet = false;"
                                                class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-md bg-white dark:bg-slate-900 border border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-300"
                                                x-text="sn"></button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="submit" form="transfer-form"
                            :disabled="!isFormValid"
                            @click="openMobileSheet = false"
                            class="w-full min-h-[44px] inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 dark:disabled:bg-slate-800 disabled:text-slate-400 text-white rounded-lg text-xs font-bold transition-all shadow-xs cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <span>Kirim Transfer Sekarang</span>
                    </button>
                </div>
            </div>

            {{-- Floating Bottom Bar (Simple, Sleek, Glassmorphism - Offset on Tablet md:left-64) --}}
            <div class="fixed bottom-3 inset-x-3 md:left-64 md:right-0 md:px-6 z-40 pointer-events-none">
                <div class="bg-slate-900/95 dark:bg-slate-900/95 backdrop-blur-xl border border-white/10 text-white rounded-lg shadow-2xl p-2.5 flex items-center justify-between gap-3 max-w-lg mx-auto pointer-events-auto">
                    
                    {{-- Tapable Summary Area --}}
                    <div class="flex-1 min-w-0 pl-2 cursor-pointer select-none" @click="openMobileSheet = true">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-sm font-extrabold text-sky-400 truncate" x-text="totalSnCount + ' SN • ' + validItemCount + ' Item'">0 Item</span>
                            <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                        </div>
                        <p class="text-[10px] text-slate-400 truncate">
                            <span x-text="fromPopId ? 'Asal siap' : 'Pilih Asal'"></span> &bull; <span x-text="toPopId ? 'Tujuan siap' : 'Pilih Tujuan'"></span>
                        </p>
                    </div>

                    {{-- Submit CTA Button --}}
                    <button type="submit" form="transfer-form"
                            :disabled="!isFormValid"
                            class="min-h-[42px] px-4 py-2 bg-sky-500 hover:bg-sky-600 disabled:bg-slate-800 disabled:text-slate-500 text-slate-950 font-extrabold rounded-lg text-xs flex items-center justify-center gap-1.5 transition-all shadow-md active:scale-95 shrink-0 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <span>Kirim</span>
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>

@push('scripts')
<script>
function warehouseTransferManager(itemOptions, categoryOptions, oldRows, stockEndpoint, initialFromPopId, initialToPopId) {
    return {
        itemOptions: itemOptions,
        categoryOptions: categoryOptions,
        stockEndpoint: stockEndpoint,
        fromPopId: initialFromPopId || '',
        toPopId: initialToPopId || '',
        loadingStock: false,
        availableStockItems: [],
        stockSearchQuery: '',
        mismatches: [],
        rows: [],

        init() {
            // 1. Rehidrasi data old() jika form gagal validasi
            if (oldRows && Object.keys(oldRows).length > 0) {
                Object.values(oldRows).forEach((old, i) => {
                    this.rows.push({
                        item_id: old.item_id || '',
                        category_id: '',
                        tracking_type: '',
                        unit: '',
                        qty: old.qty || '',
                        lot_no: old.lot_no || '',
                        serial_numbers: old.serial_numbers || '',
                    });
                    this.onItemChange(i);
                });
            } else {
                // 2. Baca query string shortcut
                const params = new URLSearchParams(window.location.search);
                const itemId = params.get('item_id');

                this.addRow();

                if (itemId) {
                    this.rows[0].item_id = itemId;
                    this.rows[0].lot_no = params.get('lot_no') || '';
                    this.onItemChange(0);

                    const serial = params.get('serial');
                    if (serial && this.rows[0].tracking_type === 'serialized') {
                        this.rows[0].serial_numbers = serial;
                    }
                }
            }

            // 3. Load stok awal jika gudang asal sudah terisi
            if (this.fromPopId) {
                this.loadAvailableStock(this.fromPopId);
            }
        },

        onFromPopChange() {
            this.loadAvailableStock(this.fromPopId);
        },

        loadAvailableStock(popId) {
            this.mismatches = [];

            if (! popId) {
                this.availableStockItems = [];
                return;
            }

            this.loadingStock = true;
            fetch(`${this.stockEndpoint}?pop_id=${popId}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => { this.availableStockItems = data.items || []; })
                .catch(() => { this.availableStockItems = []; })
                .finally(() => { this.loadingStock = false; });
        },

        get filteredAvailableStock() {
            if (! this.stockSearchQuery.trim()) {
                return this.availableStockItems;
            }
            const q = this.stockSearchQuery.toLowerCase().trim();
            return this.availableStockItems.filter(item => {
                if (item.name.toLowerCase().includes(q)) return true;
                if (Array.isArray(item.serials) && item.serials.some(sn => sn.toLowerCase().includes(q))) return true;
                if (Array.isArray(item.lots) && item.lots.some(l => (l.lot_no || '').toLowerCase().includes(q))) return true;
                return false;
            });
        },

        addRow() {
            this.rows.push({
                item_id: '',
                category_id: '',
                tracking_type: '',
                unit: '',
                qty: '',
                lot_no: '',
                serial_numbers: '',
            });
        },

        removeRow(index) {
            this.rows.splice(index, 1);
        },

        filteredItemOptions(row) {
            if (! row.category_id) return this.itemOptions;
            return this.itemOptions.filter(o => String(o.category_id) === String(row.category_id));
        },

        onItemChange(index) {
            const row = this.rows[index];
            const opt = this.itemOptions.find(o => String(o.id) === String(row.item_id));

            if (opt) {
                row.tracking_type = opt.tracking_type;
                row.unit = opt.unit;
                row.category_id = opt.category_id ?? '';
            }
        },

        serialCount(row) {
            return (row.serial_numbers || '')
                .split(/[\r\n,]+/)
                .map(s => s.trim())
                .filter(s => s.length > 0)
                .length;
        },

        get validItemCount() {
            return this.rows.filter(r => r.item_id).length;
        },

        get totalSnCount() {
            return this.rows
                .filter(r => r.tracking_type === 'serialized')
                .reduce((sum, r) => sum + this.serialCount(r), 0);
        },

        get totalQtyCount() {
            return this.rows
                .filter(r => r.tracking_type !== 'serialized')
                .reduce((sum, r) => sum + (parseFloat(r.qty) || 0), 0);
        },

        get isDetailsComplete() {
            if (this.validItemCount === 0) return false;
            return this.rows.every(r => {
                if (!r.item_id) return true;
                if (r.tracking_type === 'serialized' && this.serialCount(r) === 0) return false;
                if (r.tracking_type !== 'serialized' && (!r.qty || parseFloat(r.qty) <= 0)) return false;
                return true;
            });
        },

        get isFormValid() {
            if (!this.fromPopId) return false;
            if (!this.toPopId || this.toPopId === this.fromPopId) return false;
            if (this.validItemCount === 0) return false;
            return this.isDetailsComplete;
        },

        matchStockCode(code) {
            return this.availableStockItems.find(item => Array.isArray(item.serials) && item.serials.includes(code));
        },

        // Cari baris kosong (belum dipilih barangnya sama sekali) buat
        // DIPAKAI ULANG, bukan langsung nambah baris baru di ujung (2026-09-08,
        // laporan user: scan/klik chip pertama malah ngisi baris #2, baris
        // #1 awal dari `init()` dibiarin kosong nganggur). `addRow()` cuma
        // jalan kalau BENERAN gak ada slot kosong tersisa (semua baris udah
        // kepakai barang lain).
        findEmptyRow() {
            return this.rows.find(r => !r.item_id);
        },

        onPickSerial(detail) {
            let row = this.rows.find(r => String(r.item_id) === String(detail.itemId));

            if (! row) {
                row = this.findEmptyRow();
                if (! row) {
                    this.addRow();
                    row = this.rows[this.rows.length - 1];
                }
                row.item_id = detail.itemId;
                this.onItemChange(this.rows.indexOf(row));
            }

            const existing = (row.serial_numbers || '')
                .split(/[\r\n,]+/)
                .map(s => s.trim())
                .filter(s => s.length > 0);

            if (! existing.includes(detail.serialNumber)) {
                existing.push(detail.serialNumber);
                row.serial_numbers = existing.join('\n');
            }
        },

        onPickQty(detail) {
            let row = this.rows.find(r => String(r.item_id) === String(detail.itemId) && (r.lot_no || '') === (detail.lotNo || ''));

            if (! row) {
                row = this.findEmptyRow();
                if (! row) {
                    this.addRow();
                    row = this.rows[this.rows.length - 1];
                }
                row.item_id = detail.itemId;
                this.onItemChange(this.rows.indexOf(row));
            }

            row.lot_no = detail.lotNo || '';
            row.qty = detail.qty;
        },

        onScan(code) {
            const match = this.matchStockCode(code);

            if (match) {
                this.onPickSerial({ itemId: match.item_id, serialNumber: code });
                window.Toast?.success('SN Terinput', `"${code}" (${match.name}) masuk daftar transfer.`, 2000);
                return;
            }

            if (! this.mismatches.includes(code)) {
                this.mismatches.push(code);
            }

            window.Toast?.warning('SN Tidak Ditemukan', `"${code}" bukan stok Gudang Asal yang dipilih.`);
        },

        processBulk(input) {
            const lines = (input || '').split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0);
            let matched = 0;
            let mismatched = 0;

            lines.forEach(code => {
                const match = this.matchStockCode(code);

                if (match) {
                    this.onPickSerial({ itemId: match.item_id, serialNumber: code });
                    matched++;
                    return;
                }

                if (! this.mismatches.includes(code)) {
                    this.mismatches.push(code);
                }

                mismatched++;
            });

            if (matched > 0 || mismatched > 0) {
                window.Toast?.success('Batch Diproses', `${matched} SN masuk daftar transfer` + (mismatched > 0 ? `, ${mismatched} tidak ditemukan di stok.` : '.'));
            }
        }
    };
}
</script>
@endpush

@vite(['resources/js/barcode-scan.js'])

@endsection
