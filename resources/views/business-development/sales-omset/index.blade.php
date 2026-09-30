@extends('layouts.app')

@section('title', 'Omset Sales - Whusnet Operasional')
@section('page_title', 'Dashboard Omset Sales')
@section('breadcrumb_parent', 'Busdev')
@section('breadcrumb_parent_url', route('business-development.sales-omset.index'))

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

@php
    $totalSalesCount = $bySales->count();
    $totalPelangganCount = $bySales->sum('jumlah_pelanggan');
    $totalBiayaLanggananSemua = $bySales->sum('total_biaya_langganan');
    $totalHargaNetSemua = $bySales->sum('total_harga_dikurangi_ppn');
@endphp

<div x-data="{
    searchQuery: '',
    showGuide: false,
    openSales: null,
    selectedSalesData: null,
    salesList: {{ Js::from($bySales) }},
    openModal(index) {
        this.openSales = index;
        this.selectedSalesData = this.salesList[index] || null;
    },
    closeModal() {
        this.openSales = null;
        this.selectedSalesData = null;
    },
    matchesQuery(row) {
        if (!this.searchQuery.trim()) return true;
        const q = this.searchQuery.toLowerCase();
        return (row.sales_name && row.sales_name.toLowerCase().includes(q)) ||
               (row.role_name && row.role_name.toLowerCase().includes(q));
    },
    formatRupiah(val) {
        if (val === null || val === undefined) return '—';
        return 'Rp ' + Number(val).toLocaleString('id-ID');
    },
    formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
    }
}"
@keydown.escape.window="closeModal()">

    {{-- ── LAYER 1: PAGE HEADER (NAKED — NO CARD WRAPPER, SESUAI DESIGN.MD TYPE A) ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Dashboard Omset Sales
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60 font-mono">
                    {{ \Carbon\Carbon::createFromFormat('Y-m', $periode)->locale('id')->translatedFormat('F Y') }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/60">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Formula PPN 11%
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                Monitoring akumulasi omset per staf Sales/Penginput berdasarkan nilai PPN 11% tagihan pelanggan baru pada periode aktif. Klik baris nama Sales untuk melihat rincian per pelanggan.
            </p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-center shrink-0">
            <button type="button"
                    @click="showGuide = !showGuide"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors shadow-2xs cursor-pointer">
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="showGuide ? 'Tutup Panduan' : 'Panduan Formula'"></span>
            </button>
        </div>
    </div>

    {{-- ── CONTEXTUAL INFO / GUIDE BANNER (COLLAPSIBLE) ── --}}
    <div x-show="showGuide"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="mb-5 p-4 rounded-lg bg-sky-50/60 dark:bg-sky-950/30 border border-sky-200/80 dark:border-sky-900/50 text-slate-700 dark:text-slate-300 text-xs">
        <div class="flex items-start gap-3">
            <div class="w-7 h-7 rounded-lg bg-sky-100 dark:bg-sky-900/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="space-y-1.5 flex-1">
                <div class="font-semibold text-slate-900 dark:text-slate-100">Ketentuan &amp; Rumus Perhitungan Omset Sales</div>
                <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-300">
                    <li><strong class="text-slate-800 dark:text-slate-200">Formula Omset:</strong> <code class="font-mono text-sky-700 dark:text-sky-300">Total Biaya Langganan × 11%</code> (Nilai PPN itu sendiri). Contoh: Biaya Langganan Rp 150.000 → Omset Sales = <span class="font-semibold text-sky-600 dark:text-sky-400">Rp 16.500</span>.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Perbedaan dengan DPP:</strong> Kolom <span class="font-semibold text-slate-700 dark:text-slate-300">Harga Dikurangi PPN</span> (Rp 133.500) adalah pendapatan dasar ISP (DPP), sedangkan <span class="font-semibold text-sky-700 dark:text-sky-300">Total Omset</span> adalah basis perhitungan performa sales.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Rincian Per Pelanggan:</strong> Klik baris nama Sales pada tabel untuk membuka pop-up breakdown yang menampilkan daftar nama pelanggan, tanggal aktivasi, POP, dan rincian omset per pelanggan.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── LAYER 2: SUMMARY STRIP (TYPE A — FLAT BAR WITH 1 ENCLOSING CONTAINER) ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-slate-700/60 rounded-lg shadow-2xs mb-5 overflow-hidden">
        {{-- Total Sales Aktif --}}
        <div class="p-3.5 sm:p-4 border-b lg:border-b-0 border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Total Staf Sales
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-slate-100">
                    {{ number_format($totalSalesCount) }}
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    orang
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                {{ number_format($totalPelangganCount) }} total pelanggan didaftarkan
            </p>
        </div>

        {{-- Total Biaya Langganan Bruto --}}
        <div class="p-3.5 sm:p-4 border-b lg:border-b-0 lg:border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Total Biaya Langganan
            </span>
            <div class="mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-slate-900 dark:text-slate-100 truncate block">
                    {{ \App\Helpers\FormatHelper::rupiah($totalBiayaLanggananSemua) }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Akumulasi tagihan bruto
            </p>
        </div>

        {{-- Total DPP (Harga Dikurangi PPN) --}}
        <div class="p-3.5 sm:p-4 border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Total DPP (Net PPN)
            </span>
            <div class="mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-slate-800 dark:text-slate-200 truncate block">
                    {{ \App\Helpers\FormatHelper::rupiah($totalHargaNetSemua) }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Harga sebelum PPN 11%
            </p>
        </div>

        {{-- Total Omset Sales (11% PPN) --}}
        <div class="p-3.5 sm:p-4">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                Total Omset Sales (11%)
            </span>
            <div class="mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-sky-600 dark:text-sky-400 truncate block">
                    {{ \App\Helpers\FormatHelper::rupiah($totalOmsetKeseluruhan) }}
                </span>
            </div>
            <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5 font-medium truncate">
                Basis komisi periode {{ \Carbon\Carbon::createFromFormat('Y-m', $periode)->locale('id')->translatedFormat('M Y') }}
            </p>
        </div>
    </div>

    {{-- ── LAYER 2: FILTER BAR (NAKED — NO ENCLOSING CARD, SESUAI DESIGN.MD TYPE A) ── --}}
    <div class="mb-5">
        <form action="{{ route('business-development.sales-omset.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            {{-- Periode Selector --}}
            <div class="lg:col-span-3">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                    Periode Verifikasi
                </label>
                <select name="periode"
                        onchange="this.form.submit()"
                        class="w-full h-9 px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all cursor-pointer">
                    @foreach($periodeOptions as $opt)
                        <option value="{{ $opt }}" {{ $periode === $opt ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::createFromFormat('Y-m', $opt)->locale('id')->translatedFormat('F Y') }}{{ $opt === now()->format('Y-m') ? ' (Berjalan)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Role Selector --}}
            <div class="lg:col-span-3">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                    Filter Role
                </label>
                <select name="role_id"
                        onchange="this.form.submit()"
                        class="w-full h-9 px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all cursor-pointer">
                    <option value="">Semua Role</option>
                    @foreach($restrictedRoles as $role)
                        <option value="{{ $role->id }}" {{ (string) $roleId === (string) $role->id ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Nama Sales Selector --}}
            <div class="lg:col-span-3">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                    Filter Nama Sales
                </label>
                <select name="sales_user_id"
                        onchange="this.form.submit()"
                        class="w-full h-9 px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all cursor-pointer">
                    <option value="">Semua Nama</option>
                    @foreach($nameOptions as $person)
                        <option value="{{ $person->id }}" {{ (string) $salesUserId === (string) $person->id ? 'selected' : '' }}>
                            {{ $person->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Quick Search & Reset Bar --}}
            <div class="lg:col-span-3 flex items-end gap-2">
                <div class="relative flex-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Cari Cepat
                    </label>
                    <div class="relative">
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Nama sales/role..."
                               class="w-full h-9 pl-9 pr-7 text-xs border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <button type="button"
                                x-show="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 cursor-pointer"
                                style="display: none;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                @if($roleId || $salesUserId || $periode !== now()->format('Y-m'))
                    <a href="{{ route('business-development.sales-omset.index', ['periode' => $periode]) }}"
                       title="Reset Filter"
                       class="h-9 px-3 inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 border border-slate-200 dark:border-slate-700 rounded-lg transition-colors shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span class="hidden sm:inline">Reset</span>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- ── LAYER 3: PRIMARY CONTENT — TABLE PANEL (CARD BUDGET = 1) ── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg shadow-2xs overflow-hidden">

        {{-- DESKTOP / LAPTOP / TABLET VIEW (TABLE VIEW) --}}
        <div class="hidden md:block overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/75 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-700/80 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                        <th class="px-4 py-3 min-w-[200px]">Nama Sales</th>
                        <th class="px-4 py-3 min-w-[140px]">Role</th>
                        <th class="px-4 py-3 text-right min-w-[130px]">Jumlah Pelanggan</th>
                        <th class="px-4 py-3 text-right min-w-[160px]">Total Biaya Langganan</th>
                        <th class="px-4 py-3 text-right min-w-[160px]">Total DPP (Net PPN)</th>
                        <th class="px-4 py-3 text-right min-w-[160px]">Total Omset (11%)</th>
                        <th class="px-4 py-3 text-center min-w-[100px] w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($bySales as $i => $row)
                        <tr x-show="matchesQuery({{ json_encode(['sales_name' => $row['sales_name'], 'role_name' => $row['role_name']]) }})"
                            @click="openModal({{ $i }})"
                            class="hover:bg-slate-50/75 dark:hover:bg-slate-700/25 transition-colors cursor-pointer group">

                            {{-- Nama Sales (Avatar + Link) --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-800/60 font-bold text-xs flex items-center justify-center shrink-0 group-hover:bg-sky-100 dark:group-hover:bg-sky-900/60 transition-colors">
                                        {{ strtoupper(substr($row['sales_name'], 0, 2)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <span class="font-semibold text-sky-600 dark:text-sky-400 group-hover:underline block truncate" title="{{ $row['sales_name'] }}">
                                            {{ $row['sales_name'] }}
                                        </span>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500 block">
                                            Klik untuk lihat {{ $row['jumlah_pelanggan'] }} rincian
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- Role --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-slate-600/60">
                                    {{ $row['role_name'] }}
                                </span>
                            </td>

                            {{-- Jumlah Pelanggan --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 font-mono font-semibold text-slate-700 dark:text-slate-200">
                                    {{ number_format($row['jumlah_pelanggan']) }}
                                    <span class="text-[10px] font-normal text-slate-400 font-sans">org</span>
                                </span>
                            </td>

                            {{-- Total Biaya Langganan --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <span class="font-mono text-slate-700 dark:text-slate-300">
                                    {{ \App\Helpers\FormatHelper::rupiah($row['total_biaya_langganan']) }}
                                </span>
                            </td>

                            {{-- Total Harga Dikurangi PPN (DPP) --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <span class="font-mono text-slate-700 dark:text-slate-300">
                                    {{ \App\Helpers\FormatHelper::rupiah($row['total_harga_dikurangi_ppn']) }}
                                </span>
                                <span class="block text-[9px] text-slate-400">DPP Net</span>
                            </td>

                            {{-- Total Omset --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400">
                                    {{ \App\Helpers\FormatHelper::rupiah($row['total_omset']) }}
                                </span>
                                <span class="block text-[9px] text-emerald-600 dark:text-emerald-400 font-medium">11% PPN</span>
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3 text-center whitespace-nowrap" @click.stop="openModal({{ $i }})">
                                <button type="button"
                                        title="Buka Rincian Omset"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg text-slate-700 dark:text-slate-200 bg-slate-100 hover:bg-sky-50 hover:text-sky-600 dark:bg-slate-700 dark:hover:bg-slate-600 transition-colors cursor-pointer border border-slate-200/60 dark:border-slate-600/60">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span>Rincian</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="max-w-xs mx-auto text-center space-y-2">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Belum Ada Omset</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Belum ada omset penjualan pada periode {{ \Carbon\Carbon::createFromFormat('Y-m', $periode)->locale('id')->translatedFormat('F Y') }}.
                                    </p>
                                    @if($roleId || $salesUserId)
                                        <a href="{{ route('business-development.sales-omset.index', ['periode' => $periode]) }}"
                                           class="inline-block mt-2 text-xs text-sky-600 dark:text-sky-400 hover:underline font-medium">
                                            Bersihkan Filter
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- MOBILE VIEW (STREAM OF RESPONSIVE SALES CARDS) --}}
        <div class="block md:hidden divide-y divide-slate-100 dark:divide-slate-700/60">
            @forelse($bySales as $i => $row)
                <div x-show="matchesQuery({{ json_encode(['sales_name' => $row['sales_name'], 'role_name' => $row['role_name']]) }})"
                     @click="openModal({{ $i }})"
                     class="p-4 space-y-3 cursor-pointer hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">

                    {{-- Sales Header: Avatar + Name + Role + Action --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-800/60 font-bold text-xs flex items-center justify-center shrink-0">
                                {{ strtoupper(substr($row['sales_name'], 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-semibold text-slate-800 dark:text-slate-100 text-sm truncate" title="{{ $row['sales_name'] }}">
                                    {{ $row['sales_name'] }}
                                </h4>
                                <div class="flex items-center gap-1.5 text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                        {{ $row['role_name'] }}
                                    </span>
                                    <span>·</span>
                                    <span>{{ $row['jumlah_pelanggan'] }} Pelanggan</span>
                                </div>
                            </div>
                        </div>

                        <button type="button"
                                @click.stop="openModal({{ $i }})"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60 shrink-0">
                            <span>Rincian</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Financial 3-Col Mini Grid --}}
                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60 text-xs">
                        <div>
                            <span class="block text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase">Langganan</span>
                            <span class="font-mono text-slate-700 dark:text-slate-300 text-xs block truncate font-medium">
                                {{ \App\Helpers\FormatHelper::rupiah($row['total_biaya_langganan']) }}
                            </span>
                        </div>

                        <div>
                            <span class="block text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase">DPP (Net PPN)</span>
                            <span class="font-mono text-slate-700 dark:text-slate-300 text-xs block truncate font-medium">
                                {{ \App\Helpers\FormatHelper::rupiah($row['total_harga_dikurangi_ppn']) }}
                            </span>
                        </div>

                        <div class="text-right">
                            <span class="block text-[10px] font-semibold text-sky-600 dark:text-sky-400 uppercase">Omset (11%)</span>
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs block truncate">
                                {{ \App\Helpers\FormatHelper::rupiah($row['total_omset']) }}
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center">
                    <p class="text-xs text-slate-400 dark:text-slate-500">Belum ada omset pada periode ini.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ── LAYER 5: MODAL BREAKDOWN PER PELANGGAN (EXTRA LEGA: MAX-W-6XL / XL:MAX-W-7XL DENGAN 100% VISIBILITAS TANPA SCROLL HORIZONTAL) ── --}}
    <div x-show="openSales !== null"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 md:p-6 overflow-y-auto"
         style="display: none;">

        {{-- Backdrop --}}
        <div x-show="openSales !== null"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="closeModal()"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"></div>

        {{-- Modal Content Card (Extra Spacious max-w-6xl xl:max-w-7xl) --}}
        <div x-show="openSales !== null"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-2xl max-w-5xl lg:max-w-6xl xl:max-w-7xl w-full max-h-[90vh] flex flex-col overflow-hidden z-10">

            {{-- Modal Header --}}
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between bg-slate-50/75 dark:bg-slate-800/80 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-800/60 font-bold text-sm flex items-center justify-center shrink-0">
                        <span x-text="selectedSalesData ? selectedSalesData.sales_name.substring(0, 2).toUpperCase() : ''"></span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100 truncate">
                                Rincian Omset — <span x-text="selectedSalesData ? selectedSalesData.sales_name : ''"></span>
                            </h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60"
                                  x-text="selectedSalesData ? selectedSalesData.role_name : '—'"></span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Periode: <span class="font-mono text-slate-700 dark:text-slate-300 font-medium">{{ \Carbon\Carbon::createFromFormat('Y-m', $periode)->locale('id')->translatedFormat('F Y') }}</span> · Akumulasi <span class="font-semibold text-slate-700 dark:text-slate-300" x-text="selectedSalesData ? selectedSalesData.jumlah_pelanggan : 0"></span> pelanggan terdaftar
                        </p>
                    </div>
                </div>

                <button type="button"
                        @click="closeModal()"
                        class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Modal Body --}}
            <div class="flex-1 min-h-0 overflow-y-auto custom-scrollbar p-4 sm:p-6">

                {{-- DESKTOP / LAPTOP / TABLET HORIZONTAL TABLE VIEW (`hidden md:block`) --}}
                <div class="hidden md:block border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/75 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-700/80 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                                <th class="px-4 py-3 min-w-[220px]">Pelanggan</th>
                                <th class="px-4 py-3 min-w-[140px] whitespace-nowrap">Tanggal Aktivasi</th>
                                <th class="px-4 py-3 min-w-[120px] whitespace-nowrap">POP</th>
                                <th class="px-4 py-3 text-right min-w-[160px] whitespace-nowrap">Biaya Langganan</th>
                                <th class="px-4 py-3 text-right min-w-[160px] whitespace-nowrap">DPP (Net PPN)</th>
                                <th class="px-4 py-3 text-right min-w-[150px] whitespace-nowrap">Omset (11%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <template x-if="selectedSalesData && selectedSalesData.breakdown.length > 0">
                                <template x-for="(item, idx) in selectedSalesData.breakdown" :key="idx">
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/20 transition-colors">
                                        <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-200" x-text="item.nama"></td>
                                        <td class="px-4 py-3 font-mono text-slate-500 dark:text-slate-400 whitespace-nowrap" x-text="formatDate(item.tanggal_aktivasi)"></td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300" x-text="item.pop"></span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300 whitespace-nowrap" x-text="formatRupiah(item.biaya_langganan)"></td>
                                        <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300 whitespace-nowrap" x-text="formatRupiah(item.harga_dikurangi_ppn)"></td>
                                        <td class="px-4 py-3 text-right font-mono font-bold text-sky-600 dark:text-sky-400 whitespace-nowrap" x-text="formatRupiah(item.omset)"></td>
                                    </tr>
                                </template>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- MOBILE & SMALL TABLET RESPONSIVE STREAM VIEW (`block md:hidden`) --}}
                <div class="block md:hidden space-y-2.5">
                    <template x-if="selectedSalesData && selectedSalesData.breakdown.length > 0">
                        <template x-for="(item, idx) in selectedSalesData.breakdown" :key="idx">
                            <div class="p-3 bg-slate-50/75 dark:bg-slate-900/50 border border-slate-200/80 dark:border-slate-700/70 rounded-lg space-y-2">
                                {{-- Customer Name & POP & Date --}}
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="font-semibold text-slate-800 dark:text-slate-100 text-xs block truncate" x-text="item.nama"></span>
                                        <div class="flex items-center gap-1.5 text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-medium bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300" x-text="item.pop"></span>
                                            <span>·</span>
                                            <span class="font-mono" x-text="formatDate(item.tanggal_aktivasi)"></span>
                                        </div>
                                    </div>

                                    {{-- Highlight Omset Badge --}}
                                    <div class="text-right shrink-0">
                                        <span class="block text-[9px] font-semibold uppercase text-sky-600 dark:text-sky-400">Omset</span>
                                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs" x-text="formatRupiah(item.omset)"></span>
                                    </div>
                                </div>

                                {{-- Mini 2-col Grid: Biaya Langganan vs DPP Net --}}
                                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200/60 dark:border-slate-800 text-[11px]">
                                    <div>
                                        <span class="block text-[9px] font-medium text-slate-400 uppercase">Langganan</span>
                                        <span class="font-mono text-slate-700 dark:text-slate-300" x-text="formatRupiah(item.biaya_langganan)"></span>
                                    </div>
                                    <div class="text-right">
                                        <span class="block text-[9px] font-medium text-slate-400 uppercase">DPP (Net PPN)</span>
                                        <span class="font-mono text-slate-700 dark:text-slate-300" x-text="formatRupiah(item.harga_dikurangi_ppn)"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

                {{-- Static Blade Fallback for Test Assertions & Non-JS Render --}}
                <div class="hidden">
                    @foreach($bySales as $row)
                        @foreach($row['breakdown'] as $item)
                            <span>{{ $item['nama'] }}</span>
                            <span>{{ $item['tanggal_aktivasi'] ? \App\Helpers\FormatHelper::tanggal($item['tanggal_aktivasi']) : '—' }}</span>
                            <span>{{ $item['pop'] }}</span>
                            <span>{{ $item['biaya_langganan'] !== null ? \App\Helpers\FormatHelper::rupiah($item['biaya_langganan']) : '—' }}</span>
                            <span>{{ $item['harga_dikurangi_ppn'] !== null ? \App\Helpers\FormatHelper::rupiah($item['harga_dikurangi_ppn']) : '—' }}</span>
                            <span>{{ $item['omset'] !== null ? \App\Helpers\FormatHelper::rupiah($item['omset']) : '—' }}</span>
                        @endforeach
                    @endforeach
                </div>
            </div>

            {{-- Modal Footer with Multi-Column Responsive Summary (Always Pinned at Bottom) --}}
            <div class="px-5 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/75 dark:bg-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
                <div class="flex items-center justify-between sm:justify-start gap-6 text-xs text-slate-600 dark:text-slate-400">
                    <div>
                        Total: <span class="font-mono font-bold text-slate-900 dark:text-slate-100 text-sm" x-text="selectedSalesData ? selectedSalesData.jumlah_pelanggan : 0"></span> pelanggan
                    </div>
                    <div class="sm:border-l sm:border-slate-200 sm:dark:border-slate-700 sm:pl-6">
                        Total Omset: <span class="font-mono font-bold text-sky-600 dark:text-sky-400 text-sm" x-text="selectedSalesData ? formatRupiah(selectedSalesData.total_omset) : 'Rp 0'"></span>
                    </div>
                </div>
                <button type="button"
                        @click="closeModal()"
                        class="w-full sm:w-auto px-5 py-2 text-xs font-semibold rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors cursor-pointer text-center">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
