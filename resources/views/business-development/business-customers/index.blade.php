@extends('layouts.app')

@section('title', 'List Pelanggan Bisnis - Whusnet Operasional')
@section('page_title', 'List Pelanggan Bisnis')
@section('breadcrumb_parent', 'Busdev')
@section('breadcrumb_parent_url', route('business-development.business-customers.index'))

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

@php
    $totalCount = $customers->total();
    // Hitung ringkasan pada halaman ini
    $currentPageCustomers = $customers->getCollection();
    $totalTagihanHalaman = $currentPageCustomers->sum(fn($c) => (float) ($c->customerService?->total_monthly_bill ?? 0));
    $totalPaketHalaman = $currentPageCustomers->sum(function($c) {
        $svc = $c->customerService;
        return $svc ? max(0, (float) $svc->monthly_price - (float) $svc->discount) : 0;
    });
    $totalInstalasiHalaman = $currentPageCustomers->sum(fn($c) => (float) ($c->customerAcquisition?->installation_fee ?? 0));
    $totalAlatHalaman = $currentPageCustomers->sum(fn($c) => $c->inventorySerials->count());
@endphp

<div x-data="{
    showGuide: false,
    copiedText: null,
    copyToClipboard(text) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            this.copiedText = text;
            setTimeout(() => {
                if (this.copiedText === text) this.copiedText = null;
            }, 2000);
        });
    }
}">

    {{-- ── LAYER 1: PAGE HEADER (NAKED — NO CARD WRAPPER, SESUAI DESIGN.MD TYPE A) ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    List Pelanggan Bisnis
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60 font-mono">
                    {{ number_format($totalCount) }} Pelanggan
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                    Paket Bisnis
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                Otomatis dari data pelanggan berkategori paket Bisnis (Master Kategori Paket) — harga bulanan sebelum/sesudah PPN, biaya instalasi, serta perangkat terpasang di lokasi.
            </p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-center shrink-0">
            <button type="button"
                    @click="showGuide = !showGuide"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors shadow-2xs cursor-pointer">
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="showGuide ? 'Tutup Panduan' : 'Panduan Modul'"></span>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <div class="space-y-1.5 flex-1">
                <div class="font-semibold text-slate-900 dark:text-slate-100">Aturan &amp; Ketentuan Pelanggan Bisnis</div>
                <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-300">
                    <li><strong class="text-slate-800 dark:text-slate-200">Kategori Bisnis Otomatis:</strong> Diambil langsung dari kategori paket yang memiliki role validator Biaya Instalasi di Master Kategori Paket — tidak ada input manual terpisah.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Harga Paket:</strong> Harga bulanan setelah diskon, <span class="font-semibold text-sky-700 dark:text-sky-300">sebelum PPN</span>.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Harga Sesudah PPN:</strong> Tagihan bulanan riil pelanggan mengikuti persentase PPN spesifik yang diatur per pelanggan.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Alat yang Ditinggalkan:</strong> Menampilkan unit serial number (SN) dari modul gudang yang berstatus <code class="font-mono text-emerald-600 dark:text-emerald-400">INSTALLED</code> terpasang di lokasi pelanggan.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── LAYER 2: SUMMARY STRIP (TYPE A — FLAT BAR WITH 1 ENCLOSING CONTAINER) ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-slate-700/60 rounded-lg shadow-2xs mb-5 overflow-hidden">
        {{-- Total Pelanggan --}}
        <div class="p-3.5 sm:p-4 border-b lg:border-b-0 border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Total Pelanggan Bisnis
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-slate-100">
                    {{ number_format($totalCount) }}
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    terdata
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Paket Bisnis Aktif / Terpasang
            </p>
        </div>

        {{-- Total Tagihan Bulanan (Sesudah PPN) --}}
        <div class="p-3.5 sm:p-4 border-b lg:border-b-0 lg:border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Tagihan Bulanan (Hal. Ini)
            </span>
            <div class="mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-slate-900 dark:text-slate-100 truncate block">
                    {{ \App\Helpers\FormatHelper::rupiah($totalTagihanHalaman) }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Total bruto sesudah PPN
            </p>
        </div>

        {{-- Total Harga Paket (Sebelum PPN) --}}
        <div class="p-3.5 sm:p-4 border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                Harga Paket (Sebelum PPN)
            </span>
            <div class="mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-sky-600 dark:text-sky-400 truncate block">
                    {{ \App\Helpers\FormatHelper::rupiah($totalPaketHalaman) }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Subtotal harga paket hal. ini
            </p>
        </div>

        {{-- Total Alat Terpasang --}}
        <div class="p-3.5 sm:p-4">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Alat Terpasang (Hal. Ini)
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-slate-900 dark:text-slate-100 truncate">
                    {{ number_format($totalAlatHalaman) }}
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    unit SN
                </span>
            </div>
            <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5 font-medium truncate">
                {{ \App\Helpers\FormatHelper::rupiah($totalInstalasiHalaman) }} instalasi
            </p>
        </div>
    </div>

    {{-- ── LAYER 2: FILTER BAR (NAKED — NO ENCLOSING CARD, SESUAI DESIGN.MD TYPE A) ── --}}
    <div class="mb-5">
        <form action="{{ route('business-development.business-customers.index') }}" method="GET" class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            {{-- Filter Fields Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 flex-1 max-w-4xl">
                {{-- Search Box --}}
                <div class="sm:col-span-5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Cari Pelanggan
                    </label>
                    <div class="relative">
                        <input type="text"
                               name="q"
                               value="{{ $search }}"
                               placeholder="Nama / ID / no HP / nama alat..."
                               class="w-full h-9 pl-9 pr-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>

                {{-- Tipe Paket Selector --}}
                <div class="sm:col-span-4">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Tipe Paket
                    </label>
                    <select name="category"
                            onchange="this.form.submit()"
                            class="w-full h-9 px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all cursor-pointer">
                        <option value="">Semua Tipe</option>
                        @foreach($businessCategories as $name)
                            <option value="{{ $name }}" {{ $category === $name ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status Selector --}}
                <div class="sm:col-span-3">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Status
                    </label>
                    <select name="status"
                            onchange="this.form.submit()"
                            class="w-full h-9 px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all cursor-pointer">
                        <option value="">Semua Status</option>
                        @foreach($statusOptions as $code => $label)
                            <option value="{{ $code }}" {{ $status === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-2 self-end lg:self-auto shrink-0 pt-1 lg:pt-5">
                @if($search !== '' || $category || $status)
                    <a href="{{ route('business-development.business-customers.index') }}"
                       title="Reset Filter"
                       class="h-9 px-3 inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 border border-slate-200 dark:border-slate-700 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Reset</span>
                    </a>
                @endif
                <button type="submit"
                        class="h-9 px-4 inline-flex items-center gap-1.5 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white shadow-2xs transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span>Cari</span>
                </button>
            </div>
        </form>
    </div>

    {{-- ── LAYER 3: PRIMARY CONTENT — TABLE PANEL (CARD BUDGET = 1) ── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg shadow-2xs overflow-hidden">

        {{-- DESKTOP / LAPTOP / LARGE TABLET VIEW (TABLE VIEW) --}}
        <div class="hidden md:block overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/75 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-700/80 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                        <th class="px-4 py-3 w-12 text-center">No.</th>
                        <th class="px-4 py-3 min-w-[220px]">Nama Pelanggan</th>
                        <th class="px-4 py-3 min-w-[150px]">Tipe Paket</th>
                        <th class="px-4 py-3 text-right min-w-[130px]">Harga Paket</th>
                        <th class="px-4 py-3 text-right min-w-[150px]">Harga Sesudah PPN</th>
                        <th class="px-4 py-3 min-w-[110px]">Status</th>
                        <th class="px-4 py-3 min-w-[180px]">Alat yang Ditinggalkan</th>
                        <th class="px-4 py-3 text-right min-w-[130px]">Biaya Instalasi</th>
                        <th class="px-4 py-3 min-w-[120px]">Tanggal Aktivasi</th>
                        <th class="px-4 py-3 text-center w-16">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($customers as $i => $customer)
                        @php
                            $service = $customer->customerService;
                            $hargaPaket = $service ? max(0, (float) $service->monthly_price - (float) $service->discount) : null;
                            // Kelompokkan per nama barang: 3 unit AP jadi "3 AP TpLink Omada AX1800", bukan 3 baris.
                            $alat = $customer->inventorySerials
                                ->groupBy(fn ($serial) => $serial->item?->name ?? 'Barang tanpa nama')
                                ->map(fn ($group, $name) => $group->count().' '.$name)
                                ->values();
                            $biayaInstalasi = $customer->customerAcquisition?->installation_fee;
                        @endphp
                        <tr class="hover:bg-slate-50/75 dark:hover:bg-slate-700/25 transition-colors">
                            {{-- No. --}}
                            <td class="px-4 py-3 text-center font-mono text-slate-400 dark:text-slate-500">
                                {{ $customers->firstItem() + $i }}
                            </td>

                            {{-- Nama Pelanggan & Metadata --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-800/60 font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($customer->full_name, 0, 2)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('customers.show', $customer) }}"
                                           class="font-semibold text-slate-800 dark:text-slate-100 hover:text-sky-600 dark:hover:text-sky-400 transition-colors truncate block">
                                            {{ $customer->full_name }}
                                        </a>
                                        <div class="flex items-center gap-1.5 text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                                            <span class="font-mono text-slate-600 dark:text-slate-400">{{ $customer->display_id }}</span>
                                            @if($customer->pop)
                                                <span>·</span>
                                                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                                    {{ $customer->pop->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Tipe Paket --}}
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60">
                                    {{ $service?->internetPackage?->category ?? '—' }}
                                </span>
                                @if($service?->package_name_snapshot)
                                    <span class="block text-[10px] text-slate-500 dark:text-slate-400 mt-1 truncate max-w-[160px]" title="{{ $service->package_name_snapshot }}">
                                        {{ $service->package_name_snapshot }}
                                    </span>
                                @endif
                            </td>

                            {{-- Harga Paket (Sebelum PPN) --}}
                            <td class="px-4 py-3 text-right">
                                <span class="font-mono text-slate-700 dark:text-slate-300">
                                    {{ $hargaPaket !== null ? \App\Helpers\FormatHelper::rupiah($hargaPaket) : '—' }}
                                </span>
                                <span class="block text-[9px] text-slate-400 dark:text-slate-500">Sebelum PPN</span>
                            </td>

                            {{-- Harga Sesudah PPN --}}
                            <td class="px-4 py-3 text-right">
                                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">
                                    {{ $service ? \App\Helpers\FormatHelper::rupiah($service->total_monthly_bill) : '—' }}
                                </span>
                                @if($service && (float) $service->ppn > 0)
                                    <span class="inline-flex items-center gap-1 text-[9px] font-semibold text-sky-600 dark:text-sky-400 mt-0.5">
                                        PPN {{ rtrim(rtrim(number_format((float) $service->ppn, 2, ',', '.'), '0'), ',') }}%
                                    </span>
                                @endif
                            </td>

                            {{-- Status Layanan --}}
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $customer->subscriptionStatus?->badgeClasses() ?? 'bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700/50' }}">
                                    {{ $customer->subscriptionStatus?->name ?? ucwords(str_replace('_', ' ', $customer->status)) }}
                                </span>
                            </td>

                            {{-- Alat yang Ditinggalkan --}}
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                @if($alat->isEmpty())
                                    <span class="text-slate-300 dark:text-slate-600 font-mono">—</span>
                                @else
                                    <div class="flex flex-col gap-1 max-w-[200px]">
                                        @foreach($alat as $itemStr)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-50 dark:bg-slate-900/40 border border-slate-200/60 dark:border-slate-700/60 text-[11px] text-slate-700 dark:text-slate-300 truncate" title="{{ $itemStr }}">
                                                <svg class="w-3 h-3 text-sky-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                                                </svg>
                                                {{ $itemStr }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            {{-- Biaya Instalasi --}}
                            <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">
                                {{ $biayaInstalasi !== null ? \App\Helpers\FormatHelper::rupiah($biayaInstalasi) : '—' }}
                            </td>

                            {{-- Tanggal Aktivasi --}}
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                <span class="font-mono text-xs">
                                    {{ $service?->activation_date ? \App\Helpers\FormatHelper::tanggal($service->activation_date) : '—' }}
                                </span>
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('customers.show', $customer) }}"
                                   title="Lihat Detail Pelanggan"
                                   class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center">
                                <div class="max-w-xs mx-auto text-center space-y-2">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Belum Ada Pelanggan</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Belum ada pelanggan paket Bisnis{{ $search !== '' || $category || $status ? ' yang cocok dengan filter' : '' }}.
                                    </p>
                                    @if($search !== '' || $category || $status)
                                        <a href="{{ route('business-development.business-customers.index') }}"
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

        {{-- MOBILE & TABLET PORTRAIT VIEW (STREAM OF RESPONSIVE CARDS) --}}
        <div class="block md:hidden divide-y divide-slate-100 dark:divide-slate-700/60">
            @forelse($customers as $i => $customer)
                @php
                    $service = $customer->customerService;
                    $hargaPaket = $service ? max(0, (float) $service->monthly_price - (float) $service->discount) : null;
                    $alat = $customer->inventorySerials
                        ->groupBy(fn ($serial) => $serial->item?->name ?? 'Barang tanpa nama')
                        ->map(fn ($group, $name) => $group->count().' '.$name)
                        ->values();
                    $biayaInstalasi = $customer->customerAcquisition?->installation_fee;
                @endphp
                <div class="p-4 space-y-3">
                    {{-- Customer Row: Avatar + Name + Status + Detail Link --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-800/60 font-bold text-xs flex items-center justify-center shrink-0">
                                {{ strtoupper(substr($customer->full_name, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('customers.show', $customer) }}"
                                   class="font-semibold text-slate-800 dark:text-slate-100 hover:text-sky-600 dark:hover:text-sky-400 transition-colors truncate block text-sm">
                                    {{ $customer->full_name }}
                                </a>
                                <div class="flex items-center gap-1.5 text-[10px] text-slate-400 dark:text-slate-500 font-mono mt-0.5">
                                    <span>{{ $customer->display_id }}</span>
                                    @if($customer->pop)
                                        <span>·</span>
                                        <span>{{ $customer->pop->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $customer->subscriptionStatus?->badgeClasses() ?? 'bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700/50' }}">
                                {{ $customer->subscriptionStatus?->name ?? ucwords(str_replace('_', ' ', $customer->status)) }}
                            </span>
                            <a href="{{ route('customers.show', $customer) }}"
                               class="p-1.5 text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    {{-- Package & Activation Meta Banner --}}
                    <div class="grid grid-cols-2 gap-2 text-xs text-slate-600 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-900/40 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800/60">
                        <div>
                            <span class="block text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500">Tipe Paket</span>
                            <span class="font-semibold text-sky-700 dark:text-sky-300 text-xs">
                                {{ $service?->internetPackage?->category ?? '—' }}
                            </span>
                            @if($service?->package_name_snapshot)
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400 truncate">
                                    {{ $service->package_name_snapshot }}
                                </span>
                            @endif
                        </div>
                        <div>
                            <span class="block text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500">Aktivasi</span>
                            <span class="font-mono text-slate-700 dark:text-slate-300 text-xs block">
                                {{ $service?->activation_date ? \App\Helpers\FormatHelper::tanggal($service->activation_date) : '—' }}
                            </span>
                        </div>
                    </div>

                    {{-- Financial Mini Grid --}}
                    <div class="grid grid-cols-3 gap-2 pt-1 border-t border-slate-100 dark:border-slate-700/60 text-xs">
                        <div>
                            <span class="block text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase">Harga Paket</span>
                            <span class="font-mono font-medium text-slate-700 dark:text-slate-300 text-xs block">
                                {{ $hargaPaket !== null ? \App\Helpers\FormatHelper::rupiah($hargaPaket) : '—' }}
                            </span>
                            <span class="text-[9px] text-slate-400">Sblm PPN</span>
                        </div>

                        <div>
                            <span class="block text-[10px] font-semibold text-sky-600 dark:text-sky-400 uppercase">Sesudah PPN</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-slate-100 text-xs block">
                                {{ $service ? \App\Helpers\FormatHelper::rupiah($service->total_monthly_bill) : '—' }}
                            </span>
                            @if($service && (float) $service->ppn > 0)
                                <span class="text-[9px] font-semibold text-sky-600 dark:text-sky-400">PPN {{ rtrim(rtrim(number_format((float) $service->ppn, 2, ',', '.'), '0'), ',') }}%</span>
                            @endif
                        </div>

                        <div class="text-right">
                            <span class="block text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase">Instalasi</span>
                            <span class="font-mono font-semibold text-slate-700 dark:text-slate-300 text-xs block">
                                {{ $biayaInstalasi !== null ? \App\Helpers\FormatHelper::rupiah($biayaInstalasi) : '—' }}
                            </span>
                        </div>
                    </div>

                    {{-- Installed Equipment Chips (Mobile) --}}
                    @if($alat->isNotEmpty())
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                            <span class="block text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 mb-1">Alat yang Ditinggalkan:</span>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($alat as $itemStr)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700/60 text-[11px] font-medium text-slate-700 dark:text-slate-300">
                                        <svg class="w-3 h-3 text-sky-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                                        </svg>
                                        {{ $itemStr }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center">
                    <p class="text-xs text-slate-400 dark:text-slate-500">Belum ada pelanggan paket Bisnis.</p>
                </div>
            @endforelse
        </div>

        {{-- PAGINATION CONTAINER --}}
        @if($customers->hasPages())
            <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Menampilkan <span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ $customers->firstItem() ?? 0 }}</span>–<span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ $customers->lastItem() ?? 0 }}</span> dari <span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ number_format($customers->total()) }}</span> pelanggan
                </div>
                <div>
                    {{ $customers->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
