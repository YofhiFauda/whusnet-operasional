@extends('layouts.app')

@section('title', 'Verifikasi BD - Whusnet Operasional')
@section('page_title', 'Menunggu Verifikasi BD')
@section('breadcrumb_parent', 'Busdev')
@section('breadcrumb_parent_url', route('business-development-verifications.index'))

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

@php
    $totalCount = $customers->count();
    $totalLangganan = $customers->sum(fn($c) => (float) ($c->customerService?->total_monthly_bill ?? 0));
    $totalDefaultInstalasi = $customers->sum(fn($c) => (float) ($c->customerService?->internetPackage?->installation_fee ?? 0));
    $popCount = $customers->pluck('pop_id')->filter()->unique()->count();
@endphp

<div x-data="{
    searchQuery: '',
    showGuide: false,
    matchesQuery(row) {
        if (!this.searchQuery.trim()) return true;
        const q = this.searchQuery.toLowerCase();
        return (row.name && row.name.toLowerCase().includes(q)) ||
               (row.code && row.code.toLowerCase().includes(q)) ||
               (row.pop && row.pop.toLowerCase().includes(q)) ||
               (row.package && row.package.toLowerCase().includes(q)) ||
               (row.address && row.address.toLowerCase().includes(q));
    }
}">

    {{-- ── LAYER 1: PAGE HEADER (NAKED — NO CARD WRAPPER) ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Menunggu Verifikasi BD
                </h1>
                @if($totalCount > 0)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/60 font-mono">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        {{ $totalCount }} Antrean Menunggu
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Antrean Bersih
                    </span>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                Pelanggan kategori bisnis yang telah lolos verifikasi teknis CS dan menunggu penentuan Biaya Instalasi oleh tim Business Development sebelum resmi diaktifkan.
            </p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-center shrink-0">
            <a href="{{ route('customer-acquisitions.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors shadow-2xs">
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <span>Riwayat Akuisisi</span>
            </a>

            <button type="button"
                    @click="showGuide = !showGuide"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors shadow-2xs">
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="showGuide ? 'Tutup Panduan' : 'Panduan Alur'"></span>
            </button>
        </div>
    </div>

    {{-- ── CONTEXTUAL GUIDE BANNER (COLLAPSIBLE) ── --}}
    <div x-show="showGuide"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="mb-5 p-4 rounded-lg bg-sky-50/60 dark:bg-sky-950/30 border border-sky-200/80 dark:border-sky-900/50 text-slate-700 dark:text-slate-300 text-xs"
         style="display: none;">
        <div class="flex items-start gap-3">
            <div class="w-7 h-7 rounded-lg bg-sky-100 dark:bg-sky-900/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div class="space-y-1.5 flex-1">
                <div class="font-semibold text-slate-900 dark:text-slate-100">Alur Verifikasi Business Development</div>
                <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-300">
                    <li><strong class="text-slate-800 dark:text-slate-200">Kategori Bisnis:</strong> Pelanggan kategori Bisnis ditahan sementara setelah verifikasi CS agar tim BD dapat meninjau kebutuhan &amp; menetapkan Biaya Instalasi khusus.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Satu Invoice Awal Terpadu:</strong> Saat Anda mengklik "Verifikasi &amp; Aktifkan", sistem menggabungkan hitungan tagihan awal CS + Biaya Instalasi BD ke dalam <span class="font-semibold text-sky-700 dark:text-sky-300">SATU invoice resmi</span>.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Aktivasi Langsung:</strong> Begitu diverifikasi, status pelanggan otomatis menjadi <em>Active</em> dan langsung tercatat di modul Akuisisi Pelanggan.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── LAYER 2: SUMMARY STRIP (FLAT BAR WITH 1 ENCLOSING CONTAINER) ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-slate-700/60 rounded-lg shadow-2xs mb-5 overflow-hidden">
        {{-- Antrean Verifikasi --}}
        <div class="p-3.5 sm:p-4 border-b lg:border-b-0 border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Antrean Menunggu
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono {{ $totalCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-slate-100' }}">
                    {{ number_format($totalCount) }}
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    pelanggan
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Perlu ditinjau &amp; diaktifkan
            </p>
        </div>

        {{-- Total Potensi Biaya Bulanan --}}
        <div class="p-3.5 sm:p-4 border-b lg:border-b-0 lg:border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Total Biaya Langganan
            </span>
            <div class="mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-slate-900 dark:text-slate-100 truncate block">
                    {{ \App\Helpers\FormatHelper::rupiah($totalLangganan) }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Potensi pendapatan bulanan
            </p>
        </div>

        {{-- Estimasi Bawaan Instalasi --}}
        <div class="p-3.5 sm:p-4 border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                Estimasi Instalasi Bawaan
            </span>
            <div class="mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-sky-600 dark:text-sky-400 truncate block">
                    {{ \App\Helpers\FormatHelper::rupiah($totalDefaultInstalasi) }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Berdasarkan master paket
            </p>
        </div>

        {{-- Cakupan POP --}}
        <div class="p-3.5 sm:p-4">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Sebaran Cabang / POP
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-slate-900 dark:text-slate-100 truncate">
                    {{ $popCount }}
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    POP terlibat
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Wilayah pelanggan antrean
            </p>
        </div>
    </div>

    {{-- ── LAYER 2: NAKED SEARCH BAR & TOOLBAR ── --}}
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 mb-5">
        {{-- Instant Search Box --}}
        <div class="relative flex-1 max-w-md">
            <input type="text"
                   x-model="searchQuery"
                   placeholder="Cari nama pelanggan, ID/Kode, POP, paket..."
                   class="w-full h-9 pl-9 pr-8 text-xs border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
            <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <button type="button"
                    x-show="searchQuery"
                    @click="searchQuery = ''"
                    class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                    style="display: none;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2 self-end sm:self-center">
            <span>Menampilkan <strong>{{ $totalCount }}</strong> antrean</span>
        </div>
    </div>

    {{-- ── LAYER 3: PRIMARY CONTENT — TABLE PANEL (CARD BUDGET = 1) ── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg shadow-2xs overflow-hidden">

        {{-- DESKTOP / LAPTOP / TABLET (HORIZONTAL TABLE VIEW) --}}
        <div class="hidden md:block overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/75 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-700/80 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                        <th class="px-4 py-3 min-w-[220px]">Pelanggan</th>
                        <th class="px-4 py-3 min-w-[160px]">POP &amp; Lokasi</th>
                        <th class="px-4 py-3 min-w-[180px]">Paket Layanan</th>
                        <th class="px-4 py-3 text-right min-w-[130px]">Biaya Langganan</th>
                        <th class="px-4 py-3 text-right min-w-[130px]">Instalasi Bawaan</th>
                        <th class="px-4 py-3 min-w-[150px]">Menunggu Sejak</th>
                        <th class="px-4 py-3 text-right min-w-[140px]">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($customers as $customer)
                        @php
                            $packageName = $customer->customerService?->internetPackage?->name ?? $customer->customerService?->package_name_snapshot ?? '—';
                            $packageCategory = $customer->customerService?->internetPackage?->category ?? 'Bisnis';
                            $defaultInstalasi = $customer->customerService?->internetPackage?->installation_fee ?? 0;
                            $rowPayload = [
                                'name' => $customer->full_name,
                                'code' => $customer->customer_code ?? $customer->customer_id ?? '',
                                'pop' => $customer->pop?->name ?? '',
                                'package' => $packageName,
                                'address' => $customer->clean_address ?? $customer->address ?? '',
                            ];
                        @endphp
                        <tr x-show="matchesQuery({{ json_encode($rowPayload) }})"
                            class="hover:bg-slate-50/75 dark:hover:bg-slate-700/25 transition-colors">

                            {{-- Pelanggan (Avatar + Full Name + Code) --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-800/60 font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($customer->full_name, 0, 2)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('business-development-verifications.show', $customer) }}"
                                           class="font-semibold text-slate-800 dark:text-slate-100 hover:text-sky-600 dark:hover:text-sky-400 transition-colors truncate block">
                                            {{ $customer->full_name }}
                                        </a>
                                        @if($customer->customer_code || $customer->customer_id)
                                            <span class="font-mono text-[10px] text-slate-400 dark:text-slate-500">
                                                {{ $customer->customer_code ?? $customer->customer_id }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- POP & Lokasi --}}
                            <td class="px-4 py-3">
                                <div class="space-y-0.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                        {{ $customer->pop?->name ?? '—' }}
                                    </span>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate max-w-[200px]" title="{{ $customer->clean_address ?? $customer->address }}">
                                        {{ $customer->clean_address ?? $customer->address ?? '—' }}
                                    </p>
                                </div>
                            </td>

                            {{-- Paket Layanan --}}
                            <td class="px-4 py-3">
                                <div class="space-y-0.5">
                                    <span class="font-medium text-slate-800 dark:text-slate-200 block truncate" title="{{ $packageName }}">
                                        {{ $packageName }}
                                    </span>
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60">
                                        {{ $packageCategory }}
                                    </span>
                                </div>
                            </td>

                            {{-- Biaya Langganan --}}
                            <td class="px-4 py-3 text-right">
                                <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $customer->customerService ? \App\Helpers\FormatHelper::rupiah($customer->customerService->total_monthly_bill) : '—' }}
                                </span>
                            </td>

                            {{-- Instalasi Bawaan --}}
                            <td class="px-4 py-3 text-right">
                                <span class="font-mono text-slate-600 dark:text-slate-300">
                                    {{ $defaultInstalasi > 0 ? \App\Helpers\FormatHelper::rupiah($defaultInstalasi) : '—' }}
                                </span>
                                @if($defaultInstalasi > 0)
                                    <span class="block text-[9px] text-slate-400 dark:text-slate-500">Bawaan Paket</span>
                                @endif
                            </td>

                            {{-- Menunggu Sejak --}}
                            <td class="px-4 py-3">
                                <div class="space-y-0.5">
                                    <span class="font-mono text-slate-700 dark:text-slate-300 block">
                                        {{ \App\Support\IndonesianDate::dateTime($customer->updated_at) }}
                                    </span>
                                    <span class="text-[10px] text-amber-600 dark:text-amber-400 font-medium">
                                        {{ $customer->updated_at->diffForHumans() }}
                                    </span>
                                </div>
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('business-development-verifications.show', $customer) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white shadow-2xs transition-colors">
                                    <span>Verifikasi &amp; Aktifkan</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="max-w-xs mx-auto text-center space-y-2">
                                    <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto border border-emerald-200/80 dark:border-emerald-800/60">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Antrean Bersih</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Tidak ada pelanggan bisnis yang menunggu verifikasi BD saat ini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- MOBILE / SMALL SCREEN (RESPONSIVE CARDS STREAM) --}}
        <div class="block md:hidden divide-y divide-slate-100 dark:divide-slate-700/60">
            @forelse($customers as $customer)
                @php
                    $packageName = $customer->customerService?->internetPackage?->name ?? $customer->customerService?->package_name_snapshot ?? '—';
                    $packageCategory = $customer->customerService?->internetPackage?->category ?? 'Bisnis';
                    $defaultInstalasi = $customer->customerService?->internetPackage?->installation_fee ?? 0;
                    $rowPayload = [
                        'name' => $customer->full_name,
                        'code' => $customer->customer_code ?? $customer->customer_id ?? '',
                        'pop' => $customer->pop?->name ?? '',
                        'package' => $packageName,
                        'address' => $customer->clean_address ?? $customer->address ?? '',
                    ];
                @endphp
                <div x-show="matchesQuery({{ json_encode($rowPayload) }})"
                     class="p-4 space-y-3">
                    {{-- Header Row: Avatar + Name + Code + POP Chip --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-800/60 font-bold text-xs flex items-center justify-center shrink-0">
                                {{ strtoupper(substr($customer->full_name, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('business-development-verifications.show', $customer) }}"
                                   class="font-semibold text-slate-800 dark:text-slate-100 hover:text-sky-600 dark:hover:text-sky-400 transition-colors truncate block text-sm">
                                    {{ $customer->full_name }}
                                </a>
                                <div class="flex items-center gap-1.5 text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                                    <span>{{ $customer->customer_code ?? $customer->customer_id ?? 'No CID' }}</span>
                                    <span>·</span>
                                    <span>{{ $customer->pop?->name ?? 'No POP' }}</span>
                                </div>
                            </div>
                        </div>

                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/60 shrink-0">
                            {{ $customer->updated_at->diffForHumans() }}
                        </span>
                    </div>

                    {{-- Package & Metadata Box --}}
                    <div class="p-2.5 rounded-lg bg-slate-50/75 dark:bg-slate-900/60 border border-slate-200/60 dark:border-slate-700/60 space-y-1.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Paket:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $packageName }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Alamat:</span>
                            <span class="text-slate-700 dark:text-slate-300 truncate max-w-[180px]" title="{{ $customer->clean_address ?? $customer->address }}">
                                {{ $customer->clean_address ?? $customer->address ?? '—' }}
                            </span>
                        </div>
                    </div>

                    {{-- Financial Mini Grid --}}
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <div>
                            <span class="block text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase">Langganan</span>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-100 text-xs">
                                {{ $customer->customerService ? \App\Helpers\FormatHelper::rupiah($customer->customerService->total_monthly_bill) : '—' }}
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="block text-[10px] font-semibold text-sky-600 dark:text-sky-400 uppercase">Instalasi Bawaan</span>
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs">
                                {{ $defaultInstalasi > 0 ? \App\Helpers\FormatHelper::rupiah($defaultInstalasi) : '—' }}
                            </span>
                        </div>
                    </div>

                    {{-- Action Button --}}
                    <div>
                        <a href="{{ route('business-development-verifications.show', $customer) }}"
                           class="w-full flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white shadow-2xs transition-colors">
                            <span>Verifikasi &amp; Tentukan Biaya Instalasi</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center space-y-2">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Antrean Bersih</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Tidak ada pelanggan bisnis yang menunggu verifikasi BD saat ini.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

