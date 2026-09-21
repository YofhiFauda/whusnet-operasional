@extends('layouts.app')

@section('title', 'Scan Barang - Whusnet Operasional')
@section('page_title', 'Scan Barang')

@section('content')

@php
    $user = auth()->user();
    $canReceive = $user->hasPermission('warehouse_transfer.create');
    $canTransfer = $user->hasPermission('warehouse_transfer.create');
    $canIssue = $user->hasPermission('warehouse_issue.create');
@endphp

<x-warehouse.header active="scan" title="Scan Barang" subtitle="Scan dulu, sistem yang nawarin mau diapain — kebalik dari alur biasa (pilih tujuan dulu baru isi form)." />

{{--
    Mode scan-first (rancangan-layout.md solusi #4, 2026-09-07) — SATU-
    SATUNYA halaman gudang yang scan-nya jalan DULUAN sebelum staf mikir
    mau ngapain. Cuma buat barang SERIALIZED (py SN) — QUANTITY/BATCH gak
    punya identitas per-unit yang bisa di-lookup, tetap lewat Kelola Stok.

    onScan() manggil endpoint JSON warehouse.scan.lookup (WarehouseScanController,
    read-only murni) — hasilnya array `actions` isi URL ke halaman MUTASI
    ASLI (Transfer/Issue/Adjustment/Reassign), masing-masing py guard &
    validasi sendiri penuh. Halaman ini sendiri gak nulis apa pun ke DB.

    2026-09-08 (laporan user) — nambah "Mode Batch": Receive/Transfer/Issue
    scan BERUNTUN (bukan satu-satu kayak mode Lookup), digrup jadi 3 sub-tab.
    Beda filosofi dari Lookup ("scan dulu baru nentuin aksi", per-SN) — Batch
    WAJIB "pilih tujuan dulu baru scan" (satu Gudang Tujuan/Teknisi buat
    SEMUA SN dalam satu submit), gak bisa dihindari kalau mau banyak SN
    sekali kirim. Form Batch POST LANGSUNG ke rute store() ASLI yang SAMA
    dipakai Receive/Transfer/Issue create() penuh — bukan endpoint baru,
    bukan logic bisnis baru, cuma UI shortcut biar staf gak pindah halaman.
--}}
<div class="max-w-2xl mx-auto" x-data="{ mode: 'lookup' }">

    {{-- Mode Toggle --}}
    @if($canReceive || $canTransfer || $canIssue)
    <div class="mb-4 w-full sm:w-fit flex items-center gap-1.5 bg-slate-100/90 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/70 rounded-lg p-1.5">
        <button type="button" @click="mode = 'lookup'"
                :class="mode === 'lookup' ? 'bg-white dark:bg-slate-700 text-violet-700 dark:text-violet-300 shadow-xs ring-1 ring-black/5 dark:ring-white/5 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium'"
                class="flex-1 sm:flex-none px-3.5 py-2 rounded-lg text-xs transition-all cursor-pointer">
            Scan Satu-Satu
        </button>
        <button type="button" @click="mode = 'batch'"
                :class="mode === 'batch' ? 'bg-white dark:bg-slate-700 text-violet-700 dark:text-violet-300 shadow-xs ring-1 ring-black/5 dark:ring-white/5 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium'"
                class="flex-1 sm:flex-none px-3.5 py-2 rounded-lg text-xs transition-all cursor-pointer">
            Mode Batch
        </button>
    </div>
    @endif

    {{-- ================= MODE: LOOKUP (SATU-SATU) ================= --}}
    <div x-show="mode === 'lookup'" x-cloak
         x-data="scanFirst(
            @js(route('warehouse.scan.lookup')),
            @js(route('warehouse.transfers.store')),
            @js(route('warehouse.issues.store')),
            @js($cabangPops->map(fn ($pop) => ['id' => $pop->id, 'name' => $pop->name])),
            @js($technicians->map(fn ($tech) => ['id' => $tech->id, 'name' => $tech->name]))
         )"
         @barcode-detected.window="$event.detail.target === 'scan-first' && onScan($event.detail.code)">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-6 shadow-xs">
            <x-warehouse.barcode-scanner target="scan-first" />

            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/60">
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Atau Ketik Manual</label>
                <div class="flex gap-2">
                    <input type="text" x-model="manualSn" @keydown.enter.prevent="lookup(manualSn)"
                           placeholder="Ketik / tempel Serial Number…"
                           class="flex-1 min-h-[44px] text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 transition-all">
                    <button type="button" @click="lookup(manualSn)"
                            class="min-h-[44px] px-4 text-xs font-bold rounded-lg bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white transition-colors cursor-pointer">
                        Cari
                    </button>
                </div>
            </div>
        </div>

        <!-- Hasil Lookup -->
        <template x-if="loading">
            <div class="mt-4 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-6 text-center">
                <svg class="w-5 h-5 mx-auto animate-spin text-violet-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <p class="text-xs text-slate-400 mt-2">Mencari SN…</p>
            </div>
        </template>

        <template x-if="!loading && result">
            <div class="mt-4 bg-white dark:bg-slate-800/90 border rounded-lg p-5 shadow-xs"
                 :class="result.found ? (result.in_scope === false ? 'border-amber-200 dark:border-amber-800' : 'border-emerald-200 dark:border-emerald-800') : 'border-slate-200 dark:border-slate-700'">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-mono text-xs font-bold text-slate-500 dark:text-slate-400" x-text="'SN: ' + result.sn"></span>
                    <button type="button" @click="result = null; manualSn = ''" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs">✕ Tutup</button>
                </div>

                <template x-if="result.found && result.in_scope !== false">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100" x-text="result.item_name"></h3>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300" x-text="result.status_label"></span>
                            <template x-if="result.condition_label">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold"
                                    :class="{
                                        'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400': result.condition_label === 'Baru',
                                        'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400': result.condition_label === 'Bekas — Rusak',
                                        'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400': result.condition_label === 'Bekas — Sudah Dicek',
                                        'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400': result.condition_label === 'Bekas — Belum Dicek',
                                    }"
                                    x-text="result.condition_label"></span>
                            </template>
                            <span class="text-[11px] text-slate-400" x-text="'@ ' + result.location"></span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2" x-text="result.message"></p>
                    </div>
                </template>

                <template x-if="result.found && result.in_scope === false">
                    <p class="text-xs text-amber-700 dark:text-amber-400" x-text="result.message"></p>
                </template>

                <template x-if="!result.found">
                    <p class="text-xs text-slate-500 dark:text-slate-400" x-text="result.message"></p>
                </template>

                <div class="mt-4 flex flex-wrap gap-2" x-show="result.actions && result.actions.length > 0">
                    {{--
                        BUG 2026-09-08 (laporan user: "Scan Tunggal cuma
                        deskripsi, tombol Transfer/Serahkan/Catat Barang Masuk
                        gak nongol sama sekali walau login Owner") — root
                        cause: `<template x-for>` WAJIB cuma py SATU elemen
                        akar per iterasi (aturan Alpine). Versi SEBELUMNYA di
                        sini nyimpen TIGA `<template x-if>` bersebelahan
                        langsung di dalam satu `<template x-for>` — Alpine
                        cuma ngambil child PERTAMA template itu buat di-clone,
                        dua x-if sisanya gak pernah ke-render, dan even
                        aksi non-quick (Lihat Riwayat, dst) gak pernah nongol
                        juga karena children lain di luar clone pertama itu
                        diabaikan. Fix: BUNGKUS ketiga `x-if` dalam SATU
                        elemen (`<span>`) biar jadi satu akar yang valid.
                    --}}
                    <template x-for="action in (result.actions || [])" :key="action.url">
                        <span>
                            <template x-if="action.quick === 'transfer'">
                                <button type="button" @click="showQuickTransfer = !showQuickTransfer"
                                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors bg-violet-600 hover:bg-violet-700 text-white"
                                        x-text="showQuickTransfer ? 'Tutup Form Kirim Cepat' : action.label"></button>
                            </template>
                            <template x-if="action.quick === 'issue'">
                                <button type="button" @click="showQuickIssue = !showQuickIssue"
                                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors bg-violet-600 hover:bg-violet-700 text-white"
                                        x-text="showQuickIssue ? 'Tutup Form Kirim Cepat' : action.label"></button>
                            </template>
                            <template x-if="!action.quick">
                                <a :href="action.url" x-text="action.label"
                                   :class="{
                                       'bg-violet-600 hover:bg-violet-700 text-white': action.style === 'primary',
                                       'bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/50 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800': action.style === 'danger',
                                       'bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300': action.style === 'ghost',
                                   }"
                                   class="px-4 py-2 text-xs font-bold rounded-lg transition-colors"></a>
                            </template>
                        </span>
                    </template>
                </div>
                <p class="mt-3 text-[10px] text-slate-400" x-show="!result.actions || result.actions.length === 0">
                    Gak ada aksi cepat yang bisa dilakukan buat SN ini dari peran Anda saat ini.
                </p>

                {{-- Form "Kirim Cepat" — Transfer ke Cabang. POST LANGSUNG ke
                     `warehouse.transfers.store` (rute aslinya, bukan endpoint
                     baru) — SN/Barang/Gudang Asal udah otomatis dari hasil scan,
                     staf cuma pilih Gudang Tujuan. Sukses/gagal ditangani PRG +
                     flash Toast biasa (redirect ke Bon Transfer kalau sukses,
                     balik ke halaman ini dengan pesan error kalau gagal — sama
                     pola form manapun di aplikasi ini, gak ada jalur baru). --}}
                <template x-if="showQuickTransfer && quickTransferAction">
                    <form :action="transferStoreUrl" method="POST" class="mt-3 p-3.5 rounded-lg bg-violet-50/60 dark:bg-violet-950/20 border border-violet-200 dark:border-violet-900/50 space-y-2.5">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <input type="hidden" name="from_pop_id" :value="quickTransferAction.from_pop_id">
                        <input type="hidden" name="lines[0][item_id]" :value="quickTransferAction.item_id">
                        <input type="hidden" name="lines[0][serial_numbers]" :value="quickTransferAction.serial_number">
                        <label class="block text-[11px] font-bold text-violet-800 dark:text-violet-300 uppercase tracking-wide">Kirim Cepat — Pilih Gudang Tujuan</label>
                        <select name="to_pop_id" required
                                class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-violet-200 dark:border-violet-800 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500">
                            <option value="">— Pilih Gudang Cabang Tujuan —</option>
                            <template x-for="pop in cabangPops" :key="pop.id">
                                <option :value="pop.id" x-text="pop.name"></option>
                            </template>
                        </select>
                        <button type="submit" class="w-full min-h-[42px] px-4 py-2 text-xs font-bold rounded-lg bg-violet-600 hover:bg-violet-700 text-white transition-colors cursor-pointer">
                            Kirim Transfer Sekarang
                        </button>
                    </form>
                </template>

                {{-- Form "Kirim Cepat" — Serah ke Teknisi. Sama pola, POST
                     langsung ke `warehouse.issues.store`. --}}
                <template x-if="showQuickIssue && quickIssueAction">
                    <form :action="issueStoreUrl" method="POST" class="mt-3 p-3.5 rounded-lg bg-violet-50/60 dark:bg-violet-950/20 border border-violet-200 dark:border-violet-900/50 space-y-2.5">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <input type="hidden" name="cabang_pop_id" :value="quickIssueAction.cabang_pop_id">
                        <input type="hidden" name="lines[0][item_id]" :value="quickIssueAction.item_id">
                        <input type="hidden" name="lines[0][serial_numbers]" :value="quickIssueAction.serial_number">
                        <label class="block text-[11px] font-bold text-violet-800 dark:text-violet-300 uppercase tracking-wide">Kirim Cepat — Pilih Teknisi Penerima</label>
                        <select name="technician_id" required
                                class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-violet-200 dark:border-violet-800 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500">
                            <option value="">— Pilih Teknisi —</option>
                            <template x-for="tech in technicians" :key="tech.id">
                                <option :value="tech.id" x-text="tech.name"></option>
                            </template>
                        </select>
                        <button type="submit" class="w-full min-h-[42px] px-4 py-2 text-xs font-bold rounded-lg bg-violet-600 hover:bg-violet-700 text-white transition-colors cursor-pointer">
                            Serahkan ke Teknisi Sekarang
                        </button>
                    </form>
                </template>
            </div>
        </template>
    </div>
    {{-- /MODE: LOOKUP --}}

    {{-- ================= MODE: BATCH (FULL SCAN) ================= --}}
    @if($canReceive || $canTransfer || $canIssue)
    <div x-show="mode === 'batch'" x-cloak
         x-data="scanBatchManager({
            receiveStoreUrl: @js(route('warehouse.receive.store')),
            transferStoreUrl: @js(route('warehouse.transfers.store')),
            issueStoreUrl: @js(route('warehouse.issues.store')),
            transferStockUrl: @js(route('warehouse.transfers.available-stock')),
            issueStockUrl: @js(route('warehouse.issues.available-stock')),
            pusatPops: @js($pusatPops->map(fn ($pop) => ['id' => $pop->id, 'name' => $pop->name])),
            cabangPops: @js($cabangPops->map(fn ($pop) => ['id' => $pop->id, 'name' => $pop->name])),
            technicians: @js($technicians->map(fn ($tech) => ['id' => $tech->id, 'name' => $tech->name])),
            scanCategories: @js($scanCategories->map(fn ($cat) => ['id' => $cat->id, 'name' => $cat->name])),
            scanItems: @js($scanItems->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'code' => $item->code, 'category_id' => $item->item_category_id])),
            initialBatchType: @js($canReceive ? 'receive' : ($canTransfer ? 'transfer' : 'issue')),
         })"
         @barcode-detected.window="
            $event.detail.target === 'scan-batch-receive' && receiveAddSn($event.detail.code);
            $event.detail.target === 'scan-batch-transfer' && transferAddSn($event.detail.code);
            $event.detail.target === 'scan-batch-issue' && issueAddSn($event.detail.code);
         ">

        {{-- Sub-tab switcher --}}
        <div class="mb-4 w-full flex items-center gap-1.5 bg-slate-100/90 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/70 rounded-lg p-1.5 overflow-x-auto no-scrollbar">
            @if($canReceive)
            <button type="button" @click="batchType = 'receive'"
                    :class="batchType === 'receive' ? 'bg-white dark:bg-slate-700 text-emerald-700 dark:text-emerald-300 shadow-xs ring-1 ring-black/5 dark:ring-white/5 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium'"
                    class="shrink-0 px-3.5 py-2 rounded-lg text-xs transition-all cursor-pointer">
                Terima Barang
            </button>
            @endif
            @if($canTransfer)
            <button type="button" @click="batchType = 'transfer'"
                    :class="batchType === 'transfer' ? 'bg-white dark:bg-slate-700 text-sky-700 dark:text-sky-300 shadow-xs ring-1 ring-black/5 dark:ring-white/5 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium'"
                    class="shrink-0 px-3.5 py-2 rounded-lg text-xs transition-all cursor-pointer">
                Transfer ke Cabang
            </button>
            @endif
            @if($canIssue)
            <button type="button" @click="batchType = 'issue'"
                    :class="batchType === 'issue' ? 'bg-white dark:bg-slate-700 text-indigo-700 dark:text-indigo-300 shadow-xs ring-1 ring-black/5 dark:ring-white/5 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium'"
                    class="shrink-0 px-3.5 py-2 rounded-lg text-xs transition-all cursor-pointer">
                Serah ke Teknisi
            </button>
            @endif
        </div>

        {{-- ===== SUB-TAB: TERIMA BARANG (RECEIVE) ===== --}}
        @if($canReceive)
        <div x-show="batchType === 'receive'" x-cloak class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-5 shadow-xs space-y-3.5">
            <p class="text-[11px] text-slate-500 dark:text-slate-400">SN yang di-scan di sini BARU (belum pernah tercatat) — pilih dulu Gudang, Kategori→Barang, dan Harga Satuan, semua SN yang di-scan setelahnya dianggap satu batch barang yang sama.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                    <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Gudang Pusat Penerima</label>
                    <select x-model="receivePopId" class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">
                        <option value="">-- Pilih Gudang --</option>
                        <template x-for="pop in pusatPops" :key="pop.id">
                            <option :value="pop.id" x-text="pop.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Harga Beli Satuan (Rp)</label>
                    <input type="number" x-model.number="receiveUnitPrice" min="1" step="1" placeholder="350000"
                           class="w-full min-h-[42px] text-xs font-mono font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Kategori</label>
                    <select x-model="receiveCategoryId" @change="receiveItemId = ''" class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">
                        <option value="">-- Semua Kategori --</option>
                        <template x-for="cat in scanCategories" :key="cat.id">
                            <option :value="cat.id" x-text="cat.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Barang</label>
                    <select x-model="receiveItemId" class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">
                        <option value="">-- Pilih Barang --</option>
                        <template x-for="opt in receiveFilteredItems" :key="opt.id">
                            <option :value="opt.id" x-text="opt.code + ' — ' + opt.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60" x-show="receiveItemId" x-cloak>
                <x-warehouse.barcode-scanner target="scan-batch-receive" />

                <div class="mt-3">
                    <label class="block mb-1.5 text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">Atau Tempel/Scan Banyak SN Sekaligus</label>
                    <textarea x-model="receiveBulkInput" rows="3" placeholder="Satu SN per baris..."
                              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-[11px] font-mono font-semibold bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"></textarea>
                    <button type="button" @click="receiveProcessBulk()" :disabled="!receiveBulkInput.trim()"
                            class="mt-1.5 w-full min-h-[38px] inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 disabled:cursor-not-allowed dark:disabled:bg-slate-700 text-white rounded-lg text-[11px] font-bold cursor-pointer">
                        Proses Daftar SN
                    </button>
                </div>
            </div>
            <p class="text-[11px] text-amber-600 dark:text-amber-400" x-show="!receiveItemId">Pilih Barang dulu sebelum scan.</p>

            <form :action="receiveStoreUrl" method="POST" class="pt-3 border-t border-slate-100 dark:border-slate-700/60 space-y-2.5">
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="pop_id" :value="receivePopId">
                <input type="hidden" name="lines[0][item_id]" :value="receiveItemId">
                <input type="hidden" name="lines[0][unit_price]" :value="receiveUnitPrice">
                <input type="hidden" name="lines[0][serial_numbers]" :value="receiveScanned.map(r => r.sn).join('\n')">

                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">Daftar SN Siap Terima</span>
                    <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400" x-text="receiveScanned.length + ' unit'"></span>
                </div>
                <div class="max-h-48 overflow-y-auto space-y-1" x-show="receiveScanned.length > 0">
                    <template x-for="(row, index) in receiveScanned" :key="row.sn">
                        <div class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900/50 text-[11px] font-mono">
                            <span x-text="row.sn"></span>
                            <button type="button" @click="receiveScanned.splice(index, 1)" class="text-rose-500 hover:text-rose-600 cursor-pointer">✕</button>
                        </div>
                    </template>
                </div>
                <p class="text-[11px] text-slate-400 italic" x-show="receiveScanned.length === 0">Belum ada SN.</p>

                <button type="submit" :disabled="!receivePopId || !receiveItemId || !receiveUnitPrice || receiveScanned.length === 0"
                        class="w-full min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 dark:disabled:bg-slate-700 text-white rounded-lg text-xs font-bold shadow-xs transition-all disabled:cursor-not-allowed cursor-pointer">
                    Simpan Barang Masuk Sekarang
                </button>
            </form>
        </div>
        @endif

        {{-- ===== SUB-TAB: TRANSFER KE CABANG ===== --}}
        @if($canTransfer)
        <div x-show="batchType === 'transfer'" x-cloak class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-5 shadow-xs space-y-3.5">
            <p class="text-[11px] text-slate-500 dark:text-slate-400">SN yang di-scan divalidasi terhadap stok Gudang Asal — ketemu langsung masuk daftar (barangnya otomatis ketauan), gak ketemu ditandai mismatch.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                    <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Dari Gudang Asal (Pusat)</label>
                    <select x-model="transferFromPopId" @change="loadTransferStock()" class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">
                        <option value="">-- Pilih Gudang Asal --</option>
                        <template x-for="pop in pusatPops" :key="pop.id">
                            <option :value="pop.id" x-text="pop.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Ke Gudang Tujuan (Cabang)</label>
                    <select x-model="transferToPopId" class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">
                        <option value="">-- Pilih Gudang Tujuan --</option>
                        <template x-for="pop in cabangPops" :key="pop.id">
                            <option :value="pop.id" x-text="pop.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60" x-show="transferFromPopId" x-cloak>
                <x-warehouse.barcode-scanner target="scan-batch-transfer" />

                <div class="mt-3">
                    <label class="block mb-1.5 text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">Atau Tempel/Scan Banyak SN Sekaligus</label>
                    <textarea x-model="transferBulkInput" rows="3" placeholder="Satu SN per baris..."
                              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-[11px] font-mono font-semibold bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"></textarea>
                    <button type="button" @click="transferProcessBulk()" :disabled="!transferBulkInput.trim()"
                            class="mt-1.5 w-full min-h-[38px] inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-300 disabled:cursor-not-allowed dark:disabled:bg-slate-700 text-white rounded-lg text-[11px] font-bold cursor-pointer">
                        Proses Daftar SN
                    </button>
                </div>

                <div x-show="transferMismatches.length > 0" x-cloak class="mt-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 rounded-lg p-3">
                    <div class="flex items-center justify-between mb-1.5">
                        <p class="text-[11px] font-bold text-rose-800 dark:text-rose-300">⚠ SN Gak Ada di Stok Gudang Ini</p>
                        <button type="button" @click="transferMismatches = []" class="text-[10px] font-semibold text-rose-500 hover:text-rose-600 cursor-pointer">Tutup</button>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="code in transferMismatches" :key="code">
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300" x-text="code"></span>
                        </template>
                    </div>
                </div>
            </div>
            <p class="text-[11px] text-amber-600 dark:text-amber-400" x-show="!transferFromPopId">Pilih Gudang Asal dulu sebelum scan.</p>

            <form :action="transferStoreUrl" method="POST" class="pt-3 border-t border-slate-100 dark:border-slate-700/60 space-y-2.5">
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="from_pop_id" :value="transferFromPopId">
                <input type="hidden" name="to_pop_id" :value="transferToPopId">
                <template x-for="(row, index) in transferRows" :key="row.item_id">
                    <span>
                        <input type="hidden" :name="`lines[${index}][item_id]`" :value="row.item_id">
                        <input type="hidden" :name="`lines[${index}][serial_numbers]`" :value="row.serials.join('\n')">
                    </span>
                </template>

                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">Daftar Barang Siap Transfer</span>
                    <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-full bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400" x-text="transferTotalSn + ' unit'"></span>
                </div>
                <div class="max-h-56 overflow-y-auto space-y-2" x-show="transferRows.length > 0">
                    <template x-for="row in transferRows" :key="row.item_id">
                        <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/50">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200" x-text="row.name"></span>
                                <span class="text-[10px] font-mono text-slate-400" x-text="row.serials.length + ' unit'"></span>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <template x-for="sn in row.serials" :key="sn">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-mono px-1.5 py-0.5 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                        <span x-text="sn"></span>
                                        <button type="button" @click="transferRemoveSn(row, sn)" class="text-rose-400 hover:text-rose-600 cursor-pointer">✕</button>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <p class="text-[11px] text-slate-400 italic" x-show="transferRows.length === 0">Belum ada barang.</p>

                <button type="submit" :disabled="!transferValid"
                        class="w-full min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-300 dark:disabled:bg-slate-700 text-white rounded-lg text-xs font-bold shadow-xs transition-all disabled:cursor-not-allowed cursor-pointer">
                    Kirim Transfer Sekarang
                </button>
            </form>
        </div>
        @endif

        {{-- ===== SUB-TAB: SERAH KE TEKNISI (ISSUE) ===== --}}
        @if($canIssue)
        <div x-show="batchType === 'issue'" x-cloak class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-5 shadow-xs space-y-3.5">
            <p class="text-[11px] text-slate-500 dark:text-slate-400">SN yang di-scan divalidasi terhadap stok Gudang Cabang — ketemu langsung masuk daftar, gak ketemu ditandai mismatch.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                    <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Gudang Cabang Asal</label>
                    <select x-model="issueCabangPopId" @change="loadIssueStock()" class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">
                        <option value="">-- Pilih Gudang Cabang --</option>
                        <template x-for="pop in cabangPops" :key="pop.id">
                            <option :value="pop.id" x-text="pop.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Teknisi Penerima</label>
                    <select x-model="issueTechnicianId" class="w-full min-h-[42px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">
                        <option value="">-- Pilih Teknisi --</option>
                        <template x-for="tech in technicians" :key="tech.id">
                            <option :value="tech.id" x-text="tech.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60" x-show="issueCabangPopId" x-cloak>
                <x-warehouse.barcode-scanner target="scan-batch-issue" />

                <div class="mt-3">
                    <label class="block mb-1.5 text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">Atau Tempel/Scan Banyak SN Sekaligus</label>
                    <textarea x-model="issueBulkInput" rows="3" placeholder="Satu SN per baris..."
                              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-[11px] font-mono font-semibold bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"></textarea>
                    <button type="button" @click="issueProcessBulk()" :disabled="!issueBulkInput.trim()"
                            class="mt-1.5 w-full min-h-[38px] inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 disabled:cursor-not-allowed dark:disabled:bg-slate-700 text-white rounded-lg text-[11px] font-bold cursor-pointer">
                        Proses Daftar SN
                    </button>
                </div>

                <div x-show="issueMismatches.length > 0" x-cloak class="mt-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 rounded-lg p-3">
                    <div class="flex items-center justify-between mb-1.5">
                        <p class="text-[11px] font-bold text-rose-800 dark:text-rose-300">⚠ SN Gak Ada di Stok Cabang Ini</p>
                        <button type="button" @click="issueMismatches = []" class="text-[10px] font-semibold text-rose-500 hover:text-rose-600 cursor-pointer">Tutup</button>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="code in issueMismatches" :key="code">
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300" x-text="code"></span>
                        </template>
                    </div>
                </div>
            </div>
            <p class="text-[11px] text-amber-600 dark:text-amber-400" x-show="!issueCabangPopId">Pilih Gudang Cabang dulu sebelum scan.</p>

            <form :action="issueStoreUrl" method="POST" class="pt-3 border-t border-slate-100 dark:border-slate-700/60 space-y-2.5">
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="cabang_pop_id" :value="issueCabangPopId">
                <input type="hidden" name="technician_id" :value="issueTechnicianId">
                <template x-for="(row, index) in issueRows" :key="row.item_id">
                    <span>
                        <input type="hidden" :name="`lines[${index}][item_id]`" :value="row.item_id">
                        <input type="hidden" :name="`lines[${index}][serial_numbers]`" :value="row.serials.join('\n')">
                    </span>
                </template>

                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">Daftar Barang Siap Serah</span>
                    <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400" x-text="issueTotalSn + ' unit'"></span>
                </div>
                <div class="max-h-56 overflow-y-auto space-y-2" x-show="issueRows.length > 0">
                    <template x-for="row in issueRows" :key="row.item_id">
                        <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/50">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200" x-text="row.name"></span>
                                <span class="text-[10px] font-mono text-slate-400" x-text="row.serials.length + ' unit'"></span>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <template x-for="sn in row.serials" :key="sn">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-mono px-1.5 py-0.5 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                        <span x-text="sn"></span>
                                        <button type="button" @click="issueRemoveSn(row, sn)" class="text-rose-400 hover:text-rose-600 cursor-pointer">✕</button>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <p class="text-[11px] text-slate-400 italic" x-show="issueRows.length === 0">Belum ada barang.</p>

                <button type="submit" :disabled="!issueValid"
                        class="w-full min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 dark:disabled:bg-slate-700 text-white rounded-lg text-xs font-bold shadow-xs transition-all disabled:cursor-not-allowed cursor-pointer">
                    Serahkan ke Teknisi Sekarang
                </button>
            </form>
        </div>
        @endif
    </div>
    @endif
    {{-- /MODE: BATCH --}}

</div>

@push('scripts')
<script>
function scanFirst(endpoint, transferStoreUrl, issueStoreUrl, cabangPops, technicians) {
    return {
        endpoint: endpoint,
        transferStoreUrl: transferStoreUrl,
        issueStoreUrl: issueStoreUrl,
        cabangPops: cabangPops,
        technicians: technicians,
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        loading: false,
        result: null,
        manualSn: '',
        // "Kirim Cepat" (2026-09-08) — toggle form inline, direset tiap
        // lookup baru biar gak nyangkut kebuka dari SN sebelumnya.
        showQuickTransfer: false,
        showQuickIssue: false,

        get quickTransferAction() {
            return (this.result?.actions || []).find(a => a.quick === 'transfer') ?? null;
        },

        get quickIssueAction() {
            return (this.result?.actions || []).find(a => a.quick === 'issue') ?? null;
        },

        onScan(code) {
            this.lookup(code);
        },

        lookup(sn) {
            sn = (sn || '').trim();
            if (! sn) return;

            this.loading = true;
            this.result = null;
            this.showQuickTransfer = false;
            this.showQuickIssue = false;

            fetch(`${this.endpoint}?sn=${encodeURIComponent(sn)}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => { this.result = data; })
                .catch(() => { this.result = { found: false, sn: sn, message: 'Gagal menghubungi server, coba lagi.', actions: [] }; })
                .finally(() => { this.loading = false; });
        },
    };
}

/**
 * Mode Batch (2026-09-08) — 3 sub-mode (Receive/Transfer/Issue) full-scan,
 * masing-masing POST langsung ke rute store() ASLI (lihat komentar Blade di
 * atas). Transfer/Issue reuse pola grouping-by-item yang SAMA kayak
 * Asisten Dispatch Cepat di transfers/create.blade.php & issues/create.blade.php
 * (satu jalur logic: scan → cocokkan ke available-stock → grup per item_id).
 */
function scanBatchManager(opts) {
    return {
        receiveStoreUrl: opts.receiveStoreUrl,
        transferStoreUrl: opts.transferStoreUrl,
        issueStoreUrl: opts.issueStoreUrl,
        transferStockUrl: opts.transferStockUrl,
        issueStockUrl: opts.issueStockUrl,
        pusatPops: opts.pusatPops,
        cabangPops: opts.cabangPops,
        technicians: opts.technicians,
        scanCategories: opts.scanCategories,
        scanItems: opts.scanItems,
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        batchType: opts.initialBatchType,

        // ---- Receive ----
        receivePopId: '',
        receiveCategoryId: '',
        receiveItemId: '',
        receiveUnitPrice: '',
        receiveScanned: [],
        receiveBulkInput: '',

        get receiveFilteredItems() {
            if (! this.receiveCategoryId) return this.scanItems;
            return this.scanItems.filter(o => String(o.category_id) === String(this.receiveCategoryId));
        },

        receiveAddSn(sn) {
            const value = (sn || '').trim();
            if (! value) return;
            if (! this.receiveItemId) {
                window.Toast?.warning('Pilih Barang Dulu', 'Pilih Kategori & Barang sebelum scan.');
                return;
            }
            if (this.receiveScanned.some(r => r.sn.toLowerCase() === value.toLowerCase())) {
                window.Toast?.info('SN Sudah Ada', `'${value}' sudah ada di daftar.`, 2000);
                return;
            }
            this.receiveScanned.unshift({ sn: value });
            window.Toast?.success('SN Terinput', `'${value}' masuk daftar.`, 2000);
        },

        receiveProcessBulk() {
            if (! this.receiveItemId) {
                window.Toast?.warning('Pilih Barang Dulu', 'Pilih Kategori & Barang sebelum memproses daftar SN.');
                return;
            }
            const lines = this.receiveBulkInput.split(/[\r\n,]+/).map(s => s.trim()).filter(s => s !== '');
            let added = 0, duplicate = 0;
            lines.forEach(sn => {
                if (this.receiveScanned.some(r => r.sn.toLowerCase() === sn.toLowerCase())) { duplicate++; return; }
                this.receiveScanned.push({ sn });
                added++;
            });
            this.receiveBulkInput = '';
            if (added > 0 || duplicate > 0) {
                window.Toast?.success('Batch Diproses', `${added} SN baru ditambahkan` + (duplicate > 0 ? `, ${duplicate} duplikat dilewati.` : '.'));
            }
        },

        // ---- Transfer ----
        transferFromPopId: '',
        transferToPopId: '',
        transferAvailableStock: [],
        transferLoadingStock: false,
        transferRows: [],
        transferBulkInput: '',
        transferMismatches: [],

        loadTransferStock() {
            this.transferRows = [];
            this.transferMismatches = [];

            if (! this.transferFromPopId) {
                this.transferAvailableStock = [];
                return;
            }

            this.transferLoadingStock = true;
            fetch(`${this.transferStockUrl}?pop_id=${this.transferFromPopId}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => { this.transferAvailableStock = data.items || []; })
                .catch(() => { this.transferAvailableStock = []; })
                .finally(() => { this.transferLoadingStock = false; });
        },

        transferMatchCode(code) {
            return this.transferAvailableStock.find(item => Array.isArray(item.serials) && item.serials.includes(code));
        },

        transferAddSn(code) {
            const match = this.transferMatchCode(code);

            if (! match) {
                if (! this.transferMismatches.includes(code)) this.transferMismatches.push(code);
                window.Toast?.warning('SN Tidak Ditemukan', `"${code}" bukan stok Gudang Asal yang dipilih.`);
                return;
            }

            let row = this.transferRows.find(r => r.item_id === match.item_id);
            if (! row) {
                row = { item_id: match.item_id, name: match.name, serials: [] };
                this.transferRows.push(row);
            }
            if (! row.serials.includes(code)) {
                row.serials.push(code);
                window.Toast?.success('SN Terinput', `"${code}" (${match.name}) masuk daftar transfer.`, 2000);
            }
        },

        transferProcessBulk() {
            const lines = this.transferBulkInput.split(/[\r\n,]+/).map(s => s.trim()).filter(s => s !== '');
            let matched = 0, mismatched = 0;
            lines.forEach(code => {
                const before = this.transferMismatches.length;
                const match = this.transferMatchCode(code);
                if (match) {
                    let row = this.transferRows.find(r => r.item_id === match.item_id);
                    if (! row) { row = { item_id: match.item_id, name: match.name, serials: [] }; this.transferRows.push(row); }
                    if (! row.serials.includes(code)) { row.serials.push(code); matched++; }
                } else {
                    if (! this.transferMismatches.includes(code)) this.transferMismatches.push(code);
                    if (this.transferMismatches.length > before) mismatched++;
                }
            });
            this.transferBulkInput = '';
            if (matched > 0 || mismatched > 0) {
                window.Toast?.success('Batch Diproses', `${matched} SN masuk daftar transfer` + (mismatched > 0 ? `, ${mismatched} gak ketemu di stok.` : '.'));
            }
        },

        transferRemoveSn(row, sn) {
            row.serials = row.serials.filter(s => s !== sn);
            if (row.serials.length === 0) {
                this.transferRows = this.transferRows.filter(r => r !== row);
            }
        },

        get transferTotalSn() {
            return this.transferRows.reduce((sum, r) => sum + r.serials.length, 0);
        },

        get transferValid() {
            return !!this.transferFromPopId && !!this.transferToPopId && this.transferFromPopId !== this.transferToPopId && this.transferRows.length > 0;
        },

        // ---- Issue ----
        issueCabangPopId: '',
        issueTechnicianId: '',
        issueAvailableStock: [],
        issueLoadingStock: false,
        issueRows: [],
        issueBulkInput: '',
        issueMismatches: [],

        loadIssueStock() {
            this.issueRows = [];
            this.issueMismatches = [];

            if (! this.issueCabangPopId) {
                this.issueAvailableStock = [];
                return;
            }

            this.issueLoadingStock = true;
            fetch(`${this.issueStockUrl}?pop_id=${this.issueCabangPopId}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => { this.issueAvailableStock = data.items || []; })
                .catch(() => { this.issueAvailableStock = []; })
                .finally(() => { this.issueLoadingStock = false; });
        },

        issueMatchCode(code) {
            return this.issueAvailableStock.find(item => Array.isArray(item.serials) && item.serials.includes(code));
        },

        issueAddSn(code) {
            const match = this.issueMatchCode(code);

            if (! match) {
                if (! this.issueMismatches.includes(code)) this.issueMismatches.push(code);
                window.Toast?.warning('SN Tidak Ditemukan', `"${code}" bukan stok Gudang Cabang ini.`);
                return;
            }

            let row = this.issueRows.find(r => r.item_id === match.item_id);
            if (! row) {
                row = { item_id: match.item_id, name: match.name, serials: [] };
                this.issueRows.push(row);
            }
            if (! row.serials.includes(code)) {
                row.serials.push(code);
                window.Toast?.success('SN Terinput', `"${code}" (${match.name}) masuk daftar serah terima.`, 2000);
            }
        },

        issueProcessBulk() {
            const lines = this.issueBulkInput.split(/[\r\n,]+/).map(s => s.trim()).filter(s => s !== '');
            let matched = 0, mismatched = 0;
            lines.forEach(code => {
                const before = this.issueMismatches.length;
                const match = this.issueMatchCode(code);
                if (match) {
                    let row = this.issueRows.find(r => r.item_id === match.item_id);
                    if (! row) { row = { item_id: match.item_id, name: match.name, serials: [] }; this.issueRows.push(row); }
                    if (! row.serials.includes(code)) { row.serials.push(code); matched++; }
                } else {
                    if (! this.issueMismatches.includes(code)) this.issueMismatches.push(code);
                    if (this.issueMismatches.length > before) mismatched++;
                }
            });
            this.issueBulkInput = '';
            if (matched > 0 || mismatched > 0) {
                window.Toast?.success('Batch Diproses', `${matched} SN masuk daftar serah terima` + (mismatched > 0 ? `, ${mismatched} gak ketemu di stok.` : '.'));
            }
        },

        issueRemoveSn(row, sn) {
            row.serials = row.serials.filter(s => s !== sn);
            if (row.serials.length === 0) {
                this.issueRows = this.issueRows.filter(r => r !== row);
            }
        },

        get issueTotalSn() {
            return this.issueRows.reduce((sum, r) => sum + r.serials.length, 0);
        },

        get issueValid() {
            return !!this.issueCabangPopId && !!this.issueTechnicianId && this.issueRows.length > 0;
        },
    };
}
</script>
@endpush

@vite(['resources/js/barcode-scan.js'])

@endsection
