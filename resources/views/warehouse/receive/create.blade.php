@extends('layouts.app')

@section('title', 'Penerimaan Barang Masuk (Inbound) - Whusnet Operasional')
@section('page_title', 'Barang Masuk (Inbound)')

@section('content')

<x-warehouse.header active="stock" title="Pencatatan Barang Masuk (Inbound)" subtitle="Pencatatan hasil pengadaan barang baru ke Gudang Pusat sebagai titik acuan last-cost inventori." />

@php
    // Kategori khusus barang SERIALIZED — dipakai dropdown Scan Kamera di
    // atas (langkah pertama yang disentuh staf), beda dari $categoryOptions
    // di dalam <x-inventory-line-rows> yang nyakup SEMUA tracking_type.
    $serializedCategoryOptions = $items
        ->where('tracking_type', \App\Enums\TrackingType::SERIALIZED)
        ->pluck('category')
        ->filter()
        ->unique('id')
        ->sortBy('name')
        ->values();
@endphp

{{--
    Layout mobile-first (2026-09-04, laporan user: scroll kepanjangan di HP,
    nyari barang dari list panjang makan waktu). Tiga perbaikan:
    1. Tombol Simpan `sticky bottom-0` — selalu ke-reach tanpa scroll ke
       paling bawah, penting pas daftar barang udah panjang (banyak baris).
    2. Blok Scan Kamera dapet Kategori→Barang cascading juga (bukan cuma di
       baris bawah) — langkah PERTAMA yang disentuh staf pas mau scan.
    3. Padding/spacing dirapetin di mobile (p-4, bukan p-6/p-8) — kurang
       ruang kosong yang dulu numpuk pas discroll di layar sempit.
--}}
<div class="max-w-4xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-6 lg:p-8 shadow-xs">
    <div class="mb-4 sm:mb-6 pb-4 border-b border-slate-100 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-100 dark:border-emerald-800/60 shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Form Pencatatan Barang Masuk (Pengadaan)</h3>
                <p class="text-xs text-slate-400">Harga satuan otomatis menjadi referensi biaya material sampai ke teknisi &amp; instalasi.</p>
            </div>
        </div>
        <a href="{{ route('warehouse.stock.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 shrink-0">
            ← Kembali
        </a>
    </div>

    <form action="{{ route('warehouse.receive.store') }}" method="POST" class="space-y-5 sm:space-y-6">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="pop_id" class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">
                    Gudang Pusat Penerima <span class="text-rose-500">*</span>
                </label>
                <select name="pop_id" id="pop_id" required
                        class="w-full min-h-[44px] text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                    <option value="">— Pilih Gudang Pusat —</option>
                    @foreach($pusatPops as $pop)
                    <option value="{{ $pop->id }}" {{ old('pop_id') == $pop->id ? 'selected' : '' }}>
                        {{ $pop->name }} (Pusat Logistik)
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="notes" class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">
                    No. Dokumen / Referensi Pengadaan (Opsional)
                </label>
                <input type="text" name="notes" id="notes" value="{{ old('notes') }}" placeholder="mis. REF-PENGADAAN/2026/089"
                       class="w-full min-h-[44px] text-xs font-medium px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
            </div>
        </div>

        {{--
            Scan Kamera — pelengkap textarea SN manual di
            `<x-inventory-line-rows>` di bawah, BUKAN baris/form terpisah.
            Reuse event `pick-serial` yang SUDAH ADA (dipakai juga di
            Transfer/Issue buat "klik chip SN dari stok tersedia") —
            `inventoryLineRows.onPickSerial()` cari-atau-bikin baris yang
            item_id-nya cocok, lalu nyisipin SN ke textarea baris itu. Beda
            dari Transfer/Issue: di sana SN dipilih dari daftar YANG SUDAH
            ADA di sistem (klik chip), di sini SN itu BARU (belum pernah
            tercatat) — makanya sumbernya kamera/ketik manual, bukan daftar.

            Kategori→Barang cascading (sama pola `<x-inventory-line-rows>`)
            — nyari 1 barang dari SEMUA barang aktif itu lama, apalagi di HP.
        --}}
        <div class="bg-emerald-50/40 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 rounded-2xl p-3.5 sm:p-4"
             x-data="{
                scanCategoryId: '',
                scanItemId: '',
                itemOptions: @js($items->where('tracking_type', \App\Enums\TrackingType::SERIALIZED)->values()->map(fn ($item) => ['id' => $item->id, 'label' => $item->code.' — '.$item->name, 'category_id' => $item->item_category_id])),
                get filteredItems() {
                    if (! this.scanCategoryId) return this.itemOptions;
                    return this.itemOptions.filter(o => String(o.category_id) === String(this.scanCategoryId));
                },
                onScan(code) {
                    if (! this.scanItemId) {
                        window.Toast?.warning('Pilih Barang Dulu', 'Pilih barang bernomor seri sebelum scan.');
                        return;
                    }
                    // Cek prefix vendor SN (heuristik ONT/GPON — lihat
                    // docblock `detectSnVendorMismatch` di barcode-scan.js).
                    // Soft warning doang, SN tetap masuk ke baris.
                    const picked = this.itemOptions.find(o => String(o.id) === String(this.scanItemId));
                    const vendorMismatch = window.detectSnVendorMismatch?.(code, picked?.label || '');
                    if (vendorMismatch) {
                        window.Toast?.warning('Cek Lagi Barangnya', `SN '${code}' kelihatannya ${vendorMismatch} — Barang yang dipilih beda merek. Yakin ini barangnya?`, 5000);
                    }
                    this.$dispatch('pick-serial', { itemId: this.scanItemId, serialNumber: code });
                    window.Toast?.success('SN Terinput', `'${code}' masuk ke baris SN.`, 2000);
                },
             }"
             @barcode-detected.window="$event.detail.target === 'receive-manual' && onScan($event.detail.code)">
            <label class="block mb-1.5 text-xs font-bold text-emerald-800 dark:text-emerald-300 uppercase tracking-wide">Scan Kamera — Isi Otomatis ke Baris SN</label>
            <p class="text-[11px] text-emerald-700/80 dark:text-emerald-400 mb-2.5">Pilih barang SN yang mau di-scan, hasilnya otomatis masuk ke baris yang cocok di bawah (atau baris baru dibikin kalau belum ada).</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <select x-model="scanCategoryId" @change="scanItemId = ''"
                        class="w-full min-h-[44px] text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($serializedCategoryOptions as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <select x-model="scanItemId"
                        class="w-full min-h-[44px] text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                    <option value="">-- Pilih Barang --</option>
                    <template x-for="opt in filteredItems" :key="opt.id">
                        <option :value="opt.id" x-text="opt.label"></option>
                    </template>
                </select>
            </div>

            <x-warehouse.barcode-scanner target="receive-manual" />
        </div>

        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="text-xs font-bold text-slate-700 dark:text-slate-200">Daftar Barang &amp; Nomor Seri yang Diterima <span class="text-rose-500">*</span></label>
                <span class="text-[11px] text-slate-400 font-medium hidden sm:inline">Bisa mencampur perangkat SN, roll kabel, dan aksesoris</span>
            </div>
            <x-inventory-line-rows name="lines" :items="$items" :with-price="true" />
        </div>

        {{-- Sticky di mobile — daftar barang bisa panjang, tombol Simpan
             HARUS tetap kereach tanpa scroll balik ke paling bawah. Desktop
             (sm:) balik jadi static biasa, gak perlu sticky di layar lebar. --}}
        <div class="sticky bottom-0 sm:static -mx-4 sm:mx-0 px-4 sm:px-0 py-3 sm:py-0 sm:pt-5 bg-white/95 dark:bg-slate-800/95 backdrop-blur-sm sm:bg-transparent sm:dark:bg-transparent border-t border-slate-100 dark:border-slate-700/60 flex flex-col-reverse sm:flex-row items-center justify-between gap-2.5 sm:gap-0">
            <a href="{{ route('warehouse.stock.index') }}" class="w-full sm:w-auto text-center min-h-[44px] flex items-center justify-center px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit" class="w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-emerald-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <span>Simpan Faktur Barang Masuk</span>
            </button>
        </div>
    </form>
</div>

@vite(['resources/js/barcode-scan.js'])

@endsection
