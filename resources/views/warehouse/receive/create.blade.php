@extends('layouts.app')

@section('title', 'Pencatatan Barang Masuk (Inbound) - Whusnet Operasional')
@section('page_title', 'Pencatatan Barang Masuk')

@section('content')

@php
    $user = auth()->user();
    $canViewWarehouse = $user?->hasPermission('warehouse.view') ?? false;
    $canViewCustody = $user?->hasPermission('warehouse_custody.view') ?? false;
    $canViewTraceability = $user?->hasPermission('warehouse_traceability.view') ?? false;
    $canViewReport = $user?->hasPermission('warehouse_report.view') ?? false;
    $canViewStockRequest = $user?->hasPermission('warehouse_stock_request.view') ?? false;
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
        'auto_generate_serial' => (bool) $item->auto_generate_serial,
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

<div x-data="warehouseReceiveManager(@js($itemOptions), @js($categoryOptions), @js(old('lines', [])))"
     @pick-serial.window="onPickSerial($event.detail)"
     @pick-qty.window="onPickQty($event.detail)"
     @barcode-detected.window="$event.detail.target === 'receive-manual' && onScan($event.detail.code)"
     class="space-y-4 pb-0 sm:pb-0 lg:pb-8">

    {{-- Header terpadu Gudang (Desktop & Mobile Responsive - Naked Header) --}}
    <x-warehouse.header
        active="stock"
        title="Barang Masuk"
        subtitle="Penerimaan pengadaan barang & registrasi stok baru"
        backUrl="{{ route('warehouse.stock.index') }}"
    />

    <form id="receive-form" action="{{ route('warehouse.receive.store') }}" method="POST">
        @csrf

        {{-- ========================================================
             SINGLE CONTAINER PANEL (Card Budget = 1)
             Sesuai Design.md §1.3, §10.1 & §10.3: Seluruh form
             transaksi gudang berada dalam SATU panel dengan divider 1px.
             ======================================================== --}}
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xs overflow-hidden">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 items-stretch">
                
                {{-- ========================================================
                     KOLOM UTAMA (KIRI - LG: 8 KOLOM)
                     Identitas, Asisten Input Cepat & Daftar Rincian Barang
                     ======================================================== --}}
                <div class="lg:col-span-8 flex flex-col justify-between">
                    <div>
                        
                        {{-- 1. IDENTITAS PENERIMAAN --}}
                        <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-700">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-sky-600"></span>
                                    <span>Identitas Transaksi Inbound</span>
                                </h3>
                                <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ now()->format('d M Y') }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label for="pop_id" class="block mb-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        Gudang Pusat Penerima <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <select name="pop_id" id="pop_id" x-model="popId" required
                                                class="w-full min-h-[42px] text-xs font-semibold pl-9 pr-8 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/60 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-colors cursor-pointer">
                                            <option value="">— Pilih Gudang Pusat —</option>
                                            @foreach($pusatPops as $pop)
                                            <option value="{{ $pop->id }}" {{ (string) old('pop_id', request('pop_id', '')) === (string) $pop->id ? 'selected' : '' }}>
                                                {{ $pop->name }} (Pusat Logistik)
                                            </option>
                                            @endforeach
                                        </select>
                                        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400 absolute left-3 top-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                                        </svg>
                                    </div>
                                </div>

                                <div>
                                    <label for="notes" class="block mb-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        No. Surat Jalan / Faktur Vendor <span class="text-slate-400 font-normal">(Opsional)</span>
                                    </label>
                                    <div class="relative">
                                        <input type="text" name="notes" id="notes" value="{{ old('notes') }}" placeholder="mis. DO/2026/09/VENDOR-012"
                                               class="w-full min-h-[42px] text-xs font-mono pl-9 pr-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/60 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-colors">
                                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 004.5 9.75v7.5a2.25 2.25 0 002.25 2.25h7.5a2.25 2.25 0 002.25-2.25v-7.5a2.25 2.25 0 00-2.25-2.25h-.75m-6 3.75l3 3m0 0l3-3m-3 3V1.5m6 9h.75a2.25 2.25 0 012.25 2.25v7.5a2.25 2.25 0 01-2.25 2.25h-7.5a2.25 2.25 0 01-2.25-2.25v-.75"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 2. ASISTEN SCAN & BULK (COLLAPSIBLE SECTION) --}}
                        <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-900/30"
                             x-data="{
                                assistantMode: new URLSearchParams(window.location.search).get('sn') ? 'camera' : '',
                                scanCategoryId: '',
                                scanItemId: '',
                                bulkCategoryId: '',
                                bulkItemId: '',
                                bulkInput: '',
                                bulkParsedCount: 0,
                                itemOptions: @js($serializedItems->values()->map(fn ($item) => ['id' => $item->id, 'label' => $item->code.' — '.$item->name, 'category_id' => $item->item_category_id])),
                                pendingScanSn: new URLSearchParams(window.location.search).get('sn') || '',
                                
                                get filteredScanItems() {
                                    if (! this.scanCategoryId) return this.itemOptions;
                                    return this.itemOptions.filter(o => String(o.category_id) === String(this.scanCategoryId));
                                },
                                get filteredBulkItems() {
                                    if (! this.bulkCategoryId) return this.itemOptions;
                                    return this.itemOptions.filter(o => String(o.category_id) === String(this.bulkCategoryId));
                                },
                                updateBulkCount() {
                                    const codes = (this.bulkInput || '').split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0);
                                    this.bulkParsedCount = codes.length;
                                },
                                onScanCamera(code) {
                                    if (! this.scanItemId) {
                                        window.Toast?.warning('Pilih Barang Dulu', 'Pilih barang bernomor seri sebelum scan.');
                                        return;
                                    }
                                    const picked = this.itemOptions.find(o => String(o.id) === String(this.scanItemId));
                                    const vendorMismatch = window.detectSnVendorMismatch?.(code, picked?.label || '');
                                    if (vendorMismatch) {
                                        window.Toast?.warning('Merek Berbeda Terdeteksi', `SN '${code}' teridentifikasi sebagai ${vendorMismatch}, sedangkan barang yang dipilih adalah '${picked?.label}'.`);
                                    }
                                    $dispatch('pick-serial', { itemId: this.scanItemId, serialNumber: code });
                                    window.Toast?.success('SN Terinput', `'${code}' masuk ke baris barang.`, 2000);
                                },
                                submitBulk() {
                                    if (! this.bulkItemId) {
                                        window.Toast?.warning('Pilih Barang', 'Pilih jenis barang sebelum memproses daftar SN.');
                                        return;
                                    }
                                    const codes = (this.bulkInput || '').split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0);
                                    if (codes.length === 0) {
                                        window.Toast?.warning('Daftar Kosong', 'Tempel minimal satu nomor seri pada kolom teks.');
                                        return;
                                    }
                                    const picked = this.itemOptions.find(o => String(o.id) === String(this.bulkItemId));
                                    let countAdded = 0;
                                    let mismatchCount = 0;

                                    codes.forEach(code => {
                                        const vendorMismatch = window.detectSnVendorMismatch?.(code, picked?.label || '');
                                        if (vendorMismatch) mismatchCount++;
                                        $dispatch('pick-serial', { itemId: this.bulkItemId, serialNumber: code });
                                        countAdded++;
                                    });

                                    this.bulkInput = '';
                                    this.bulkParsedCount = 0;

                                    if (mismatchCount > 0) {
                                        window.Toast?.warning('Peringatan Vendor', `${countAdded} SN dimasukkan (${mismatchCount} SN berprefix vendor lain).`);
                                    } else {
                                        window.Toast?.success('Batch Berhasil Ditambahkan', `${countAdded} nomor seri dimasukkan ke baris barang.`);
                                    }
                                }
                             }"
                             x-init="$watch('scanItemId', value => {
                                if (value && pendingScanSn) {
                                    onScanCamera(pendingScanSn);
                                    pendingScanSn = '';
                                }
                             })"
                             @pick-serial-scan.window="onScanCamera($event.detail.code)">
                            
                            {{-- Mode Switcher Bar --}}
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-sky-600"></span>
                                    <span>Asisten Input Cepat</span>
                                </span>

                                <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-900/80 rounded-lg border border-slate-200 dark:border-slate-700">
                                    <button type="button" @click="assistantMode = assistantMode === 'camera' ? '' : 'camera'"
                                            :class="assistantMode === 'camera' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-xs font-semibold' : 'text-slate-600 dark:text-slate-400 font-medium hover:text-slate-900 dark:hover:text-slate-200'"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.174C3.244 7.54 2.5 8.352 2.5 9.318v9.132a2.25 2.25 0 002.25 2.25h14.5a2.25 2.25 0 002.25-2.25V9.318c0-.966-.744-1.778-1.552-1.914a48.11 48.11 0 00-1.134-.174 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                                        <span>Scan Kamera</span>
                                    </button>
                                    <button type="button" @click="assistantMode = assistantMode === 'bulk' ? '' : 'bulk'"
                                            :class="assistantMode === 'bulk' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-xs font-semibold' : 'text-slate-600 dark:text-slate-400 font-medium hover:text-slate-900 dark:hover:text-slate-200'"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span>Bulk Paste</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Pending SN Box --}}
                            <div x-show="pendingScanSn" x-cloak class="mt-3 flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 text-xs">
                                <span class="text-sky-900 dark:text-sky-200 text-[11px]">SN: <strong class="font-mono font-bold" x-text="pendingScanSn"></strong> (Pilih barang untuk memasukkan)</span>
                                <button type="button" @click="pendingScanSn = ''" class="text-sky-700 dark:text-sky-400 text-[10px] font-bold">Tutup</button>
                            </div>

                            {{-- Collapsible Assistant Drawer Container --}}
                            <div x-show="assistantMode !== ''" x-cloak x-collapse.duration.200ms class="mt-3.5 pt-3.5 border-t border-slate-200 dark:border-slate-700">
                                {{-- Drawer Mode 1: Scan Kamera --}}
                                <div x-show="assistantMode === 'camera'"
                                     x-transition:enter="transition-opacity ease-out duration-150"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     class="space-y-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                        <div>
                                            <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">1. Kategori</label>
                                            <select x-model="scanCategoryId" @change="scanItemId = ''"
                                                    class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                <option value="">-- Semua Kategori --</option>
                                                @foreach($serializedCategoryOptions as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">2. Pilih Barang Target</label>
                                            <select x-model="scanItemId"
                                                    class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                <option value="">-- Pilih Barang Target Scan --</option>
                                                <template x-for="opt in filteredScanItems" :key="opt.id">
                                                    <option :value="opt.id" x-text="opt.label"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>

                                    <x-warehouse.barcode-scanner target="receive-manual" />
                                </div>

                                {{-- Drawer Mode 2: Bulk Paste --}}
                                <div x-show="assistantMode === 'bulk'"
                                     x-transition:enter="transition-opacity ease-out duration-150"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     class="space-y-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                        <div>
                                            <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">1. Kategori</label>
                                            <select x-model="bulkCategoryId" @change="bulkItemId = ''"
                                                    class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                <option value="">-- Semua Kategori --</option>
                                                @foreach($serializedCategoryOptions as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">2. Pilih Barang Target</label>
                                            <select x-model="bulkItemId"
                                                    class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                <option value="">-- Pilih Barang Target --</option>
                                                <template x-for="opt in filteredBulkItems" :key="opt.id">
                                                    <option :value="opt.id" x-text="opt.label"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Tempel Daftar SN (1 per baris)</label>
                                            <span class="text-[10px] font-mono font-bold text-sky-600 dark:text-sky-400" x-text="bulkParsedCount + ' SN Terdeteksi'"></span>
                                        </div>
                                        <textarea x-model="bulkInput" @input="updateBulkCount()" rows="3"
                                                  placeholder="Tempel nomor seri di sini..."
                                                  class="w-full font-mono text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500"></textarea>
                                        
                                        <div class="mt-2 flex justify-end">
                                            <button type="button" @click="submitBulk()" :disabled="!bulkItemId || bulkParsedCount === 0"
                                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 dark:disabled:bg-slate-800 disabled:text-slate-400 text-white rounded-lg text-xs font-semibold transition-colors cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                                <span>Masukkan ke Baris</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 3. DAFTAR BARIS BARANG MASUK --}}
                        <div class="p-4 sm:p-5 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-600"></span>
                                        <span>Rincian Barang Pengadaan</span>
                                    </h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700"
                                          x-text="rows.length + ' Item'"></span>
                                </div>

                                <button type="button" @click="addRow()"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/60 dark:hover:bg-sky-900/60 border border-sky-200 dark:border-sky-800 rounded-lg transition-colors shadow-xs shrink-0 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                    <span>Tambah Item</span>
                                </button>
                            </div>

                            {{-- Empty State --}}
                            <template x-if="rows.length === 0">
                                <div class="py-10 px-4 text-center border-2 border-dashed border-slate-200 dark:border-slate-700/80 rounded-lg bg-slate-50/50 dark:bg-slate-900/30">
                                    <h4 class="text-xs font-semibold text-slate-700 dark:text-slate-200">Belum ada barang pada faktur</h4>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Gunakan asisten di atas atau klik tombol tambah item.</p>
                                    <button type="button" @click="addRow()" class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-xs cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                        <span>Tambah Item Pertama</span>
                                    </button>
                                </div>
                            </template>

                            {{-- Repeatable Rows --}}
                            <div class="space-y-3.5">
                                <template x-for="(row, index) in rows" :key="index">
                                    <div class="bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/80 rounded-lg p-3.5 sm:p-4 space-y-3 transition-colors">
                                        
                                        {{-- Baris Header --}}
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="w-6 h-6 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-xs font-semibold flex items-center justify-center border border-slate-200 dark:border-slate-700 shadow-xs" x-text="index + 1"></span>
                                                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Baris #<span x-text="index + 1"></span></span>

                                                <template x-if="row.tracking_type === 'serialized'">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-mono font-semibold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                                                        SERIALIZED (SN)
                                                    </span>
                                                </template>
                                                <template x-if="row.tracking_type === 'quantity'">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-mono font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                        REGULER
                                                    </span>
                                                </template>
                                                <template x-if="row.tracking_type === 'roll'">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-mono font-semibold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                                        ROLL KABEL
                                                    </span>
                                                </template>
                                            </div>

                                            <button type="button" @click="removeRow(index)" class="p-1.5 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-colors cursor-pointer" title="Hapus baris">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                                                </svg>
                                            </button>
                                        </div>

                                        {{-- Dropdown Kategori & Barang --}}
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                            <div>
                                                <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Kategori</label>
                                                <select x-model="row.category_id" @change="row.item_id = ''; row.tracking_type = ''; row.unit = ''; row.unit_price = '';"
                                                        class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                    <option value="">-- Semua Kategori --</option>
                                                    <template x-for="cat in categoryOptions" :key="cat.id">
                                                        <option :value="cat.id" x-text="cat.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Nama Barang <span class="text-rose-500">*</span></label>
                                                <select :name="`lines[${index}][item_id]`" x-model="row.item_id" @change="onItemChange(index)" required
                                                        class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                    <option value="">— Pilih Barang —</option>
                                                    <template x-for="opt in filteredItemOptions(row)" :key="opt.id">
                                                        <option :value="opt.id" x-text="opt.label"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>

                                        {{-- Form Serialized manual (barang punya SN vendor asli) --}}
                                        <template x-if="row.tracking_type === 'serialized' && !row.auto_generate_serial">
                                            <div class="bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 rounded-lg p-3 space-y-2.5">
                                                <div class="flex items-center justify-between">
                                                    <label class="text-[10px] font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                                        Daftar Nomor Seri (1 Baris per Unit)
                                                    </label>
                                                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full"
                                                          :class="serialCount(row) > 0 ? 'bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800' : 'bg-slate-200 dark:bg-slate-700 text-slate-500'"
                                                          x-text="serialCount(row) + ' Unit SN'"></span>
                                                </div>

                                                <textarea :name="`lines[${index}][serial_numbers]`" x-model="row.serial_numbers" rows="2" required
                                                          placeholder="ZTEG00123&#10;ZTEG00124"
                                                          class="w-full text-xs font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500"></textarea>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 items-end">
                                                    <div>
                                                        <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Harga Beli Satuan (Rp) <span class="text-rose-500">*</span></label>
                                                        <div class="relative">
                                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-mono font-semibold text-slate-400">Rp</span>
                                                            <input type="text" inputmode="decimal" data-rupiah :name="`lines[${index}][unit_price]`" x-model="row.unit_price" required placeholder="250.000"
                                                                   class="w-full min-h-[42px] pl-9 pr-3 py-2 text-xs font-mono font-semibold border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                        </div>
                                                    </div>

                                                    <div class="bg-slate-50 dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-between min-h-[42px]">
                                                        <span class="text-[10px] font-semibold text-slate-500">Subtotal:</span>
                                                        <span class="font-mono text-xs font-bold text-sky-700 dark:text-sky-300" x-text="'Rp ' + formatRupiah(lineSubtotal(row))"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Form Serialized auto-generate (ODP, Splitter — gak punya SN vendor).
                                             Staf cukup isi jumlah unit, SN + label barcode dibuatkan sistem
                                             setelah Receive tersimpan. --}}
                                        <template x-if="row.tracking_type === 'serialized' && row.auto_generate_serial">
                                            <div class="bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 rounded-lg p-3 space-y-2.5">
                                                <p class="text-[11px] text-sky-700 dark:text-sky-300">SN barang ini digenerate sistem — cukup isi jumlah unit, label barcode siap dicetak setelah tersimpan.</p>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 items-end">
                                                    <div>
                                                        <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Jumlah Unit <span class="text-rose-500">*</span></label>
                                                        <input type="number" step="1" min="1" :name="`lines[${index}][serial_count]`" x-model="row.serial_count" required placeholder="10"
                                                               class="w-full min-h-[42px] px-3 py-2 text-xs font-mono font-semibold border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                    </div>
                                                    <div>
                                                        <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Harga Beli Satuan (Rp) <span class="text-rose-500">*</span></label>
                                                        <div class="relative">
                                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-mono font-semibold text-slate-400">Rp</span>
                                                            <input type="text" inputmode="decimal" data-rupiah :name="`lines[${index}][unit_price]`" x-model="row.unit_price" required placeholder="25.000"
                                                                   class="w-full min-h-[42px] pl-9 pr-3 py-2 text-xs font-mono font-semibold border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="bg-slate-50 dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-between min-h-[42px]">
                                                    <span class="text-[10px] font-semibold text-slate-500">Subtotal:</span>
                                                    <span class="font-mono text-xs font-bold text-sky-700 dark:text-sky-300" x-text="'Rp ' + formatRupiah(lineSubtotal(row))"></span>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Form Roll Kabel --}}
                                        <template x-if="row.tracking_type === 'roll'">
                                            <div class="bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 rounded-lg p-3 space-y-2.5">
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                                    <div>
                                                        <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Jumlah Roll <span class="text-rose-500">*</span></label>
                                                        <input type="number" step="1" min="1" :name="`lines[${index}][roll_count]`" x-model="row.roll_count" required placeholder="3"
                                                               class="w-full min-h-[42px] px-3 py-2 text-xs font-mono font-semibold border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                    </div>
                                                    <div>
                                                        <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Vendor</label>
                                                        <input type="text" :name="`lines[${index}][vendor]`" x-model="row.vendor" placeholder="mis. PT Fiber Nusantara"
                                                               class="w-full min-h-[42px] px-3 py-2 text-xs font-mono border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 items-end">
                                                    <div>
                                                        <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Harga Beli per Roll (Rp) <span class="text-rose-500">*</span></label>
                                                        <div class="relative">
                                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-mono font-semibold text-slate-400">Rp</span>
                                                            <input type="text" inputmode="decimal" data-rupiah :name="`lines[${index}][unit_price]`" x-model="row.unit_price" required placeholder="120.000"
                                                                   class="w-full min-h-[42px] pl-9 pr-3 py-2 text-xs font-mono font-semibold border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                        </div>
                                                    </div>

                                                    <div class="bg-slate-50 dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-between min-h-[42px]">
                                                        <span class="text-[10px] font-semibold text-slate-500">Subtotal:</span>
                                                        <span class="font-mono text-xs font-bold text-sky-700 dark:text-sky-300" x-text="'Rp ' + formatRupiah(lineSubtotal(row))"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Form Quantity & Batch --}}
                                        <template x-if="row.tracking_type !== 'serialized' && row.tracking_type !== 'roll' && row.tracking_type !== ''">
                                            <div class="bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 rounded-lg p-3 space-y-2.5">
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 items-end">
                                                    <div>
                                                        <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                                                            <span>Jumlah (</span><span class="text-sky-600 dark:text-sky-400" x-text="row.unit || 'Unit'"></span><span>)</span> <span class="text-rose-500">*</span>
                                                        </label>
                                                        <input type="number" step="0.01" min="0.01" :name="`lines[${index}][qty]`" x-model="row.qty" required placeholder="0.00"
                                                               class="w-full min-h-[42px] px-3 py-2 text-xs font-mono font-semibold border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                    </div>

                                                    <div>
                                                        <label class="block mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Harga Satuan (Rp) <span class="text-rose-500">*</span></label>
                                                        <div class="relative">
                                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-mono font-semibold text-slate-400">Rp</span>
                                                            <input type="text" inputmode="decimal" data-rupiah :name="`lines[${index}][unit_price]`" x-model="row.unit_price" required placeholder="5.000"
                                                                   class="w-full min-h-[42px] pl-9 pr-3 py-2 text-xs font-mono font-semibold border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="bg-slate-50 dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-between min-h-[42px]">
                                                    <span class="text-[10px] font-semibold text-slate-500">Subtotal:</span>
                                                    <span class="font-mono text-xs font-bold text-sky-700 dark:text-sky-300" x-text="'Rp ' + formatRupiah(lineSubtotal(row))"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                    </div>

                    {{-- Action Footer Bar Desktop (Left bottom) --}}
                    <div class="p-4 sm:p-5 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 flex items-center justify-between gap-3 flex-wrap">
                        <a href="{{ route('warehouse.stock.index') }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                            <span>Kembali ke Stok</span>
                        </a>

                        <div class="flex items-center gap-2">
                            <button type="submit" form="receive-form"
                                    :disabled="!isFormValid"
                                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 dark:disabled:bg-slate-800 disabled:text-slate-400 dark:disabled:text-slate-600 text-white rounded-lg text-xs font-semibold shadow-xs transition-colors cursor-pointer disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                <span>Simpan Faktur Barang Masuk</span>
                            </button>
                        </div>
                    </div>

                </div>

                {{-- ========================================================
                     KOLOM SIDEBAR (DESKTOP - LG: 4 KOLOM)
                     Divided with 1px border (lg:border-l), NOT a separate card!
                     ======================================================== --}}
                <div class="lg:col-span-4 lg:border-l border-slate-200 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-900/20 p-5 sm:p-6 space-y-5">
                    
                    {{-- Header Ringkasan --}}
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700">
                        <h4 class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-sky-600"></span>
                            <span>Ringkasan Penerimaan</span>
                        </h4>
                        <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                            LIVE
                        </span>
                    </div>

                    {{-- Hero Metric (Clean Flat Box - No Gradient sesuai Design.md) --}}
                    <div class="bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 rounded-lg p-4">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Total Nilai Pengadaan
                        </span>
                        <div class="mt-1.5 flex items-baseline gap-1 text-slate-900 dark:text-slate-100">
                            <span class="text-sm font-semibold font-mono text-slate-500">Rp</span>
                            <span class="text-2xl font-bold font-mono tracking-tight" x-text="formatRupiah(grandTotal)">0</span>
                        </div>
                    </div>

                    {{-- Metric Breakdown Strip --}}
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div class="p-3 rounded-lg bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">Jenis Barang</span>
                            <span class="mt-1 block text-sm font-bold font-mono text-slate-800 dark:text-slate-100" x-text="validItemCount + ' Item'">0 Item</span>
                        </div>
                        <div class="p-3 rounded-lg bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total SN</span>
                            <span class="mt-1 block text-sm font-bold font-mono text-sky-600 dark:text-sky-400" x-text="totalSnCount + ' Unit'">0 Unit</span>
                        </div>
                    </div>

                    {{-- Status Checklist Kelengkapan Form --}}
                    <div class="p-4 bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700 rounded-lg space-y-2">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status Validasi Form</span>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center gap-2">
                                <span :class="popId ? 'text-emerald-500 font-bold' : 'text-slate-300 dark:text-slate-600'">✓</span>
                                <span :class="popId ? 'text-slate-700 dark:text-slate-200 font-medium' : 'text-slate-400'">Gudang Pusat Terpilih</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="validItemCount > 0 ? 'text-emerald-500 font-bold' : 'text-slate-300 dark:text-slate-600'">✓</span>
                                <span :class="validItemCount > 0 ? 'text-slate-700 dark:text-slate-200 font-medium' : 'text-slate-400'">Minimal 1 Baris Barang</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="isPriceComplete ? 'text-emerald-500 font-bold' : 'text-slate-300 dark:text-slate-600'">✓</span>
                                <span :class="isPriceComplete ? 'text-slate-700 dark:text-slate-200 font-medium' : 'text-slate-400'">Harga Beli Satuan Valid</span>
                            </div>
                        </div>
                    </div>

                    {{-- CTA Button Desktop --}}
                    <div>
                        <button type="submit" form="receive-form"
                                :disabled="!isFormValid"
                                class="w-full min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 dark:disabled:bg-slate-800 disabled:text-slate-400 dark:disabled:text-slate-600 text-white rounded-lg text-xs font-semibold shadow-xs transition-colors cursor-pointer disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            <span>Simpan Faktur Barang Masuk</span>
                        </button>
                    </div>

                    {{-- SOP Box Singkat --}}
                    <div class="p-3.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white/60 dark:bg-slate-900/50 space-y-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                        <span class="block font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 text-[10px]">Ketentuan Transaksi:</span>
                        <p>&bull; <strong>SN Perangkat:</strong> Otomatis berstatus <em>AVAILABLE</em> di Gudang Pusat.</p>
                        <p>&bull; <strong>Harga Satuan:</strong> Menjadi acuan HPP Last-Cost pengeluaran material.</p>
                    </div>

                </div>

            </div>

        </div>

        {{-- Mobile Bottom Clearance Spacer --}}
        <div class="h-16 lg:hidden" aria-hidden="true"></div>

        {{-- ========================================================
             MOBILE & TABLET FIXED BOTTOM COMMAND BAR & BOTTOM SHEET
             Tampilan Simpel, Ringkas, dan Konsisten
             ======================================================== --}}
        <div class="lg:hidden" x-data="{ openMobileSheet: false }">
            
            {{-- Mobile Backdrop --}}
            <div x-show="openMobileSheet" x-cloak
                 @click="openMobileSheet = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50"></div>

            {{-- Mobile Bottom Sheet Drawer --}}
            <div x-show="openMobileSheet" x-cloak
                 x-transition:enter="transition ease-out duration-250 transform"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 class="fixed inset-x-0 bottom-0 w-full max-h-[85vh] bg-white dark:bg-slate-900 rounded-t-2xl border-t border-slate-200 dark:border-slate-700 p-5 z-50 overflow-y-auto space-y-4 shadow-xl">
                
                <div class="w-10 h-1 bg-slate-200 dark:bg-slate-700 rounded-full mx-auto -mt-1 mb-2"></div>
                
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                    <h4 class="text-xs font-semibold text-slate-900 dark:text-slate-100 uppercase tracking-wider">
                        Rincian Penerimaan &amp; Validasi
                    </h4>
                    <button type="button" @click="openMobileSheet = false" class="p-1 text-slate-400 hover:text-slate-600 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Metric Box --}}
                <div class="bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-lg p-4">
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Nilai Pengadaan</span>
                    <div class="mt-1 flex items-baseline gap-1 text-slate-900 dark:text-slate-100">
                        <span class="text-sm font-semibold font-mono text-slate-500">Rp</span>
                        <span class="text-2xl font-bold font-mono tracking-tight" x-text="formatRupiah(grandTotal)">0</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-center text-xs">
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80">
                        <span class="block text-[10px] font-semibold text-slate-400 uppercase">Total Item</span>
                        <span class="font-bold font-mono text-slate-800 dark:text-slate-100" x-text="validItemCount + ' Item'"></span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80">
                        <span class="block text-[10px] font-semibold text-slate-400 uppercase">Total SN</span>
                        <span class="font-bold font-mono text-sky-600 dark:text-sky-400" x-text="totalSnCount + ' Unit'"></span>
                    </div>
                </div>

                {{-- Checklist Mobile --}}
                <div class="space-y-2 text-xs pt-1">
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">Status Kelengkapan:</span>
                    <div class="flex items-center gap-2">
                        <span :class="popId ? 'text-emerald-500 font-bold' : 'text-slate-300'">✓</span>
                        <span :class="popId ? 'text-slate-700 dark:text-slate-200 font-medium' : 'text-slate-400'">Gudang Pusat Terpilih</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="validItemCount > 0 ? 'text-emerald-500 font-bold' : 'text-slate-300'">✓</span>
                        <span :class="validItemCount > 0 ? 'text-slate-700 dark:text-slate-200 font-medium' : 'text-slate-400'">Minimal 1 Baris Barang</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="isPriceComplete ? 'text-emerald-500 font-bold' : 'text-slate-300'">✓</span>
                        <span :class="isPriceComplete ? 'text-slate-700 dark:text-slate-200 font-medium' : 'text-slate-400'">Harga Beli Satuan Valid</span>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="submit" form="receive-form"
                            :disabled="!isFormValid"
                            @click="openMobileSheet = false"
                            class="w-full min-h-[44px] inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 dark:disabled:bg-slate-800 disabled:text-slate-400 text-white rounded-lg text-xs font-semibold transition-colors shadow-xs cursor-pointer disabled:cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        <span>Simpan Faktur Sekarang</span>
                    </button>
                </div>
            </div>

            {{-- Floating Bottom Bar (Simple & Sleek) --}}
            <div class="fixed bottom-3 inset-x-3 z-40 pointer-events-none">
                <div class="bg-slate-900/95 dark:bg-slate-900/95 backdrop-blur-md border border-slate-800 text-white rounded-lg shadow-xl p-2.5 flex items-center justify-between gap-3 max-w-lg mx-auto pointer-events-auto">
                    
                    {{-- Tapable Summary Area --}}
                    <div class="flex-1 min-w-0 pl-2 cursor-pointer select-none" @click="openMobileSheet = true">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-sm font-bold text-sky-400 truncate" x-text="'Rp ' + formatRupiah(grandTotal)">Rp 0</span>
                            <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                        </div>
                        <p class="text-[10px] text-slate-400 truncate">
                            <span x-text="validItemCount + ' Item'"></span> &bull; <span x-text="totalSnCount + ' SN'"></span>
                        </p>
                    </div>

                    {{-- Submit CTA Button --}}
                    <button type="submit" form="receive-form"
                            :disabled="!isFormValid"
                            class="min-h-[42px] px-4 py-2 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-800 disabled:text-slate-500 text-white font-semibold rounded-lg text-xs flex items-center justify-center gap-1.5 transition-colors shadow-md active:scale-95 shrink-0 cursor-pointer disabled:cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        <span>Simpan</span>
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>

@push('scripts')
<script>
function warehouseReceiveManager(itemOptions, categoryOptions, oldRows) {
    return {
        itemOptions: itemOptions,
        categoryOptions: categoryOptions,
        popId: '{{ old('pop_id', request('pop_id', '')) }}',
        rows: [],

        init() {
            if (this.rows.length > 0) return;

            // 1. Rehidrasi data old() jika form gagal validasi
            if (oldRows && Object.keys(oldRows).length > 0) {
                Object.values(oldRows).forEach((old, i) => {
                    this.rows.push({
                        item_id: old.item_id || '',
                        category_id: '',
                        tracking_type: '',
                        auto_generate_serial: false,
                        unit: '',
                        qty: old.qty || '',
                        lot_no: old.lot_no || '',
                        serial_numbers: old.serial_numbers || '',
                        serial_count: old.serial_count || '',
                        roll_count: old.roll_count || '',
                        vendor: old.vendor || '',
                        unit_price: old.unit_price ? (window.Rupiah ? window.Rupiah.formatDariServer(old.unit_price) : old.unit_price) : '',
                    });
                    this.onItemChange(i);
                });
                return;
            }

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
        },

        addRow() {
            this.rows.push({
                item_id: '',
                category_id: '',
                tracking_type: '',
                auto_generate_serial: false,
                unit: '',
                qty: '',
                lot_no: '',
                serial_numbers: '',
                serial_count: '',
                roll_count: '',
                vendor: '',
                unit_price: ''
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
                row.auto_generate_serial = !!opt.auto_generate_serial;
                row.unit = opt.unit;
                row.category_id = opt.category_id ?? '';
            }
        },

        serialCount(row) {
            if (row.auto_generate_serial) {
                return parseInt(row.serial_count, 10) || 0;
            }
            return (row.serial_numbers || '')
                .split(/[\r\n,]+/)
                .map(s => s.trim())
                .filter(s => s.length > 0)
                .length;
        },

        parsePrice(val) {
            if (window.Rupiah && typeof window.Rupiah.angka === 'function') {
                const num = window.Rupiah.angka(val);
                return isNaN(num) ? 0 : num;
            }
            const clean = String(val || '').replace(/[^\d]/g, '');
            return parseFloat(clean) || 0;
        },

        lineSubtotal(row) {
            const price = this.parsePrice(row.unit_price);
            if (row.tracking_type === 'serialized') {
                return this.serialCount(row) * price;
            }
            if (row.tracking_type === 'roll') {
                return (parseFloat(row.roll_count) || 0) * price;
            }
            const qty = parseFloat(row.qty) || 0;
            return qty * price;
        },

        get grandTotal() {
            return this.rows.reduce((sum, row) => sum + this.lineSubtotal(row), 0);
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

        get isPriceComplete() {
            if (this.validItemCount === 0) return false;
            return this.rows.every(r => !r.item_id || (this.parsePrice(r.unit_price) > 0));
        },

        get isFormValid() {
            if (!this.popId) return false;
            if (this.validItemCount === 0) return false;
            return this.rows.every(r => {
                if (!r.item_id) return true;
                if (!r.unit_price || this.parsePrice(r.unit_price) <= 0) return false;
                if (r.tracking_type === 'serialized' && this.serialCount(r) === 0) return false;
                if (r.tracking_type === 'roll' && (!r.roll_count || parseFloat(r.roll_count) <= 0)) return false;
                if (r.tracking_type !== 'serialized' && r.tracking_type !== 'roll' && (!r.qty || parseFloat(r.qty) <= 0)) return false;
                return true;
            });
        },

        formatRupiah(val) {
            const num = Math.round(Number(val) || 0);
            return num.toLocaleString('id-ID');
        },

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
            const event = new CustomEvent('pick-serial-scan', { detail: { code } });
            window.dispatchEvent(event);
        }
    };
}
</script>
@endpush

@vite(['resources/js/barcode-scan.js'])

@endsection
