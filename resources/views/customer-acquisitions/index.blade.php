@extends('layouts.app')

@section('title', 'Busdev - Whusnet Operasional')
@section('page_title', 'Pelanggan Baru Diverifikasi')
@section('breadcrumb_parent', 'Busdev')
@section('breadcrumb_parent_url', route('customer-acquisitions.index'))

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

@php
    $totalCount = $records->count();
    $totalLangganan = $records->sum(fn($r) => (float) ($r->customer?->customerService?->total_monthly_bill ?? 0));
    $totalDpp = $records->sum(fn($r) => (float) ($r->harga_dikurangi_ppn ?? 0));
    $issuedInstallationInvoices = $records->filter(fn($r) => $r->installationFeeInvoice !== null);
    $totalInstalasi = $issuedInstallationInvoices->sum(fn($r) => (float) ($r->installation_fee ?? 0));
    $countInstalasi = $issuedInstallationInvoices->count();
    $pendingValidationCount = $records->filter(fn($r) => $r->needsInstallationFeeValidation() && ! $r->installationFeeInvoice)->count();
@endphp

<div x-data="{
    searchQuery: '',
    showGuide: false,
    matchesQuery(row) {
        if (!this.searchQuery.trim()) return true;
        const q = this.searchQuery.toLowerCase();
        return (row.name && row.name.toLowerCase().includes(q)) ||
               (row.cid && row.cid.toLowerCase().includes(q)) ||
               (row.pop && row.pop.toLowerCase().includes(q)) ||
               (row.address && row.address.toLowerCase().includes(q)) ||
               (row.sales && row.sales.toLowerCase().includes(q));
    }
}">

    {{-- ── LAYER 1: PAGE HEADER (NAKED — NO CARD WRAPPER) ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Pelanggan Baru Diverifikasi
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60 font-mono">
                    {{ $totalCount }} Pelanggan
                </span>
                @if($isCurrentPeriode)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Bulan Berjalan
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                        Arsip
                    </span>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                Monitoring akuisisi pelanggan baru aktif per bulan verifikasi, perhitungan DPP net PPN 11%, dan validasi tagihan instalasi kategori bisnis.
            </p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-center shrink-0">
            <button type="button"
                    @click="showGuide = !showGuide"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors shadow-2xs">
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="showGuide ? 'Tutup Panduan' : 'Panduan Modul'"></span>
            </button>
        </div>
    </div>

    {{-- ── CONTEXTUAL INFO / GUIDE BANNER (COLLAPSIBLE) ── --}}
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <div class="space-y-1.5 flex-1">
                <div class="font-semibold text-slate-900 dark:text-slate-100">Aturan &amp; Ketentuan Modul Busdev</div>
                <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-300">
                    <li><strong class="text-slate-800 dark:text-slate-200">Reset Tanggal 1:</strong> Terisi otomatis saat pelanggan lolos verifikasi admin. Tiap tanggal 1, tabel bulan berjalan mulai kosong kembali. Data bulan lalu tetap tersimpan di filter periode.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Harga Dikurangi PPN:</strong> Dihitung otomatis secara live <code class="font-mono text-sky-700 dark:text-sky-300">Biaya Langganan − PPN 11%</code> (bukan input manual).</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Biaya Instalasi Bisnis:</strong> Hanya berlaku untuk kategori paket yang mewajibkannya. Sekali diterbitkan, sistem membuat invoice resmi (Insidental) dan nominal terkunci dari pengubahan langsung.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── LAYER 2: SUMMARY STRIP (TYPE A — FLAT BAR WITH 1 ENCLOSING CONTAINER) ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-slate-700/60 rounded-lg shadow-2xs mb-5 overflow-hidden">
        {{-- Total Pelanggan --}}
        <div class="p-3.5 sm:p-4 border-b lg:border-b-0 border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Total Pelanggan Baru
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-slate-100">
                    {{ number_format($totalCount) }}
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    terverifikasi
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Periode {{ \Carbon\Carbon::createFromFormat('Y-m', $periode)->locale('id')->translatedFormat('F Y') }}
            </p>
        </div>

        {{-- Total Biaya Langganan --}}
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
                Total tagihan bulanan bruto
            </p>
        </div>

        {{-- Total DPP (Net PPN) --}}
        <div class="p-3.5 sm:p-4 border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                Total DPP (Net PPN 11%)
            </span>
            <div class="mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-sky-600 dark:text-sky-400 truncate block">
                    {{ \App\Helpers\FormatHelper::rupiah($totalDpp) }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Pendapatan sebelum PPN 11%
            </p>
        </div>

        {{-- Biaya Instalasi Terbit / Pending --}}
        <div class="p-3.5 sm:p-4">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Tagihan Instalasi Bisnis
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-slate-900 dark:text-slate-100 truncate">
                    {{ \App\Helpers\FormatHelper::rupiah($totalInstalasi) }}
                </span>
            </div>
            <div class="flex items-center gap-2 mt-0.5 text-[11px]">
                <span class="text-emerald-600 dark:text-emerald-400 font-medium">
                    {{ $countInstalasi }} Terbit
                </span>
                @if($pendingValidationCount > 0)
                    <span class="text-slate-300 dark:text-slate-600">·</span>
                    <span class="text-amber-600 dark:text-amber-400 font-medium">
                        {{ $pendingValidationCount }} Perlu Validasi
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- ── LAYER 2: FILTER BAR (NAKED — NO ENCLOSING CARD) ── --}}
    <div class="mb-5">
        <form action="{{ route('customer-acquisitions.index') }}" method="GET" class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            {{-- Dropdown Filters --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 flex-1 max-w-3xl">
                {{-- Periode Selector --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Periode Verifikasi
                    </label>
                    <select name="periode"
                            onchange="this.form.submit()"
                            class="w-full h-9 px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        @foreach($periodeOptions as $opt)
                            <option value="{{ $opt }}" {{ $periode === $opt ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::createFromFormat('Y-m', $opt)->locale('id')->translatedFormat('F Y') }}{{ $opt === now()->format('Y-m') ? ' (Berjalan)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Role Penginput Selector --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Role Penginput
                    </label>
                    <select name="role_id"
                            onchange="this.form.submit()"
                            class="w-full h-9 px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">Semua Role</option>
                        @foreach($restrictedRoles as $role)
                            <option value="{{ $role->id }}" {{ (string) $roleId === (string) $role->id ? 'selected' : '' }}>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Nama Penginput Selector --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Nama Penginput
                    </label>
                    <select name="sales_user_id"
                            onchange="this.form.submit()"
                            class="w-full h-9 px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">Semua Nama</option>
                        @foreach($nameOptions as $person)
                            <option value="{{ $person->id }}" {{ (string) $salesUserId === (string) $person->id ? 'selected' : '' }}>
                                {{ $person->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Instant Search & Action Bar --}}
            <div class="flex items-center gap-2 self-end lg:self-auto w-full lg:w-auto">
                {{-- Client-side Search Box --}}
                <div class="relative flex-1 lg:w-64">
                    <input type="text"
                           x-model="searchQuery"
                           placeholder="Cari nama, CID, POP, staf..."
                           class="w-full h-9 pl-9 pr-3 text-xs border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
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

                @if($roleId || $salesUserId || $periode !== now()->format('Y-m'))
                    <a href="{{ route('customer-acquisitions.index', ['periode' => $periode]) }}"
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

        {{-- DESKTOP / LAPTOP / TABLET (HORIZONTAL TABLE) --}}
        <div class="hidden md:block overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/75 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-700/80 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                        <th class="px-4 py-3 min-w-[220px]">Pelanggan</th>
                        <th class="px-4 py-3 min-w-[160px]">POP &amp; Alamat</th>
                        <th class="px-4 py-3 min-w-[130px]">Aktivasi</th>
                        <th class="px-4 py-3 min-w-[140px]">Diinput Oleh</th>
                        <th class="px-4 py-3 text-right min-w-[130px]">Biaya Langganan</th>
                        <th class="px-4 py-3 text-right min-w-[140px]">DPP (Net PPN 11%)</th>
                        <th class="px-4 py-3 text-right min-w-[200px]">Biaya Instalasi</th>
                        <th class="px-4 py-3 text-center w-16">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($records as $record)
                        @php
                            $customer = $record->customer;
                            $salesName = $customer->salesUser ? $customer->salesUser->name : ($customer->sales_code ?? '—');
                            $salesRole = $customer->salesUser?->role?->name ?? '—';
                            $rowPayload = [
                                'name' => $customer->full_name,
                                'cid' => $customer->customer_id ?? '',
                                'pop' => $customer->pop?->name ?? '',
                                'address' => $customer->clean_address ?? '',
                                'sales' => $salesName,
                            ];
                        @endphp
                        <tr x-show="matchesQuery({{ json_encode($rowPayload) }})"
                            class="hover:bg-slate-50/75 dark:hover:bg-slate-700/25 transition-colors">

                            {{-- Pelanggan (Avatar + Nama + CID) --}}
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
                                        @if($customer->customer_id)
                                            <span class="font-mono text-[10px] text-slate-400 dark:text-slate-500">
                                                {{ $customer->customer_id }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- POP & Alamat --}}
                            <td class="px-4 py-3">
                                <div class="space-y-0.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                        {{ $customer->pop?->name ?? '—' }}
                                    </span>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate max-w-[200px]" title="{{ $customer->clean_address }}">
                                        {{ $customer->clean_address ?? '—' }}
                                    </p>
                                </div>
                            </td>

                            {{-- Tanggal Aktivasi --}}
                            <td class="px-4 py-3">
                                <span class="font-mono text-slate-600 dark:text-slate-300">
                                    {{ $customer->customerService?->activation_date ? \App\Helpers\FormatHelper::tanggal($customer->customerService->activation_date) : '—' }}
                                </span>
                            </td>

                            {{-- Diinput Oleh --}}
                            <td class="px-4 py-3">
                                <div>
                                    <span class="font-medium text-slate-700 dark:text-slate-200">
                                        {{ $salesName }}
                                    </span>
                                    @if($customer->salesUser?->role)
                                        <span class="block text-[10px] text-slate-400 dark:text-slate-500">
                                            {{ $salesRole }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Biaya Langganan --}}
                            <td class="px-4 py-3 text-right">
                                <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $customer->customerService ? \App\Helpers\FormatHelper::rupiah($customer->customerService->total_monthly_bill) : '—' }}
                                </span>
                            </td>

                            {{-- DPP (Net PPN 11%) --}}
                            <td class="px-4 py-3 text-right">
                                @if($record->harga_dikurangi_ppn !== null)
                                    <span class="font-mono font-medium text-sky-700 dark:text-sky-300">
                                        {{ \App\Helpers\FormatHelper::rupiah($record->harga_dikurangi_ppn) }}
                                    </span>
                                    <span class="block text-[9px] text-slate-400 dark:text-slate-500">DPP Net</span>
                                @else
                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                @endif
                            </td>

                            {{-- Biaya Instalasi --}}
                            <td class="px-4 py-3 text-right">
                                @if(! $record->needsInstallationFeeValidation())
                                    <span class="text-slate-300 dark:text-slate-600 font-mono">—</span>
                                @elseif($record->installationFeeInvoice)
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">
                                            {{ \App\Helpers\FormatHelper::rupiah($record->installation_fee) }}
                                        </span>
                                        <div class="flex items-center gap-1.5">
                                            <a href="{{ route('invoices.show', $record->installationFeeInvoice) }}"
                                               class="font-mono text-[10px] text-sky-600 dark:text-sky-400 hover:underline">
                                                {{ $record->installationFeeInvoice->invoice_number }}
                                            </a>
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase
                                                {{ $record->installationFeeInvoice->invoice_status === \App\Enums\InvoiceStatus::LUNAS
                                                    ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60'
                                                    : 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60' }}">
                                                {{ $record->installationFeeInvoice->invoice_status->label() }}
                                            </span>
                                        </div>
                                    </div>
                                @elseif($record->canBeValidatedBy(auth()->user()))
                                    <form action="{{ route('customer-acquisitions.installation-fee.update', $record) }}"
                                          method="POST"
                                          onsubmit="return confirm('Terbitkan tagihan biaya instalasi sebesar Rp ' + this.installation_fee.value + ' untuk {{ $customer->full_name }}? Tagihan akan langsung terkunci.')"
                                          class="flex items-center justify-end gap-1.5">
                                        @csrf
                                        @method('PUT')
                                        <div class="relative flex items-center">
                                            <span class="absolute left-2 text-[10px] text-slate-400 font-mono pointer-events-none">Rp</span>
                                            <input type="number"
                                                   step="0.01"
                                                   min="0.01"
                                                   name="installation_fee"
                                                   required
                                                   value="{{ $record->installation_fee }}"
                                                   placeholder="0"
                                                   class="w-28 h-7 pl-6 pr-2 py-0.5 text-right font-mono text-xs border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                                        </div>
                                        <button type="submit"
                                                class="h-7 px-2.5 text-[11px] font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white shadow-2xs transition-colors cursor-pointer shrink-0">
                                            Terbitkan
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200/80 dark:border-amber-800/60">
                                        Menunggu Validasi
                                    </span>
                                @endif
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
                            <td colspan="8" class="px-4 py-12 text-center">
                                <div class="max-w-xs mx-auto text-center space-y-2">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Belum Ada Pelanggan</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Belum ada pelanggan diverifikasi pada periode ini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- MOBILE / SMALL SCREEN (RESPONSIVE CARDS STREAM) --}}
        <div class="block md:hidden divide-y divide-slate-100 dark:divide-slate-700/60">
            @forelse($records as $record)
                @php
                    $customer = $record->customer;
                    $salesName = $customer->salesUser ? $customer->salesUser->name : ($customer->sales_code ?? '—');
                    $salesRole = $customer->salesUser?->role?->name ?? '—';
                    $rowPayload = [
                        'name' => $customer->full_name,
                        'cid' => $customer->customer_id ?? '',
                        'pop' => $customer->pop?->name ?? '',
                        'address' => $customer->clean_address ?? '',
                        'sales' => $salesName,
                    ];
                @endphp
                <div x-show="matchesQuery({{ json_encode($rowPayload) }})"
                     class="p-4 space-y-3">
                    {{-- Top row: Avatar + Name + CID + View Action --}}
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
                                <div class="flex items-center gap-1.5 text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                                    <span>{{ $customer->customer_id ?? 'No CID' }}</span>
                                    <span>·</span>
                                    <span>{{ $customer->pop?->name ?? 'No POP' }}</span>
                                </div>
                            </div>
                        </div>

                        <a href="{{ route('customers.show', $customer) }}"
                           class="p-1.5 text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    {{-- Address & Input By Metadata --}}
                    <div class="grid grid-cols-2 gap-2 text-xs text-slate-600 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-900/40 p-2.5 rounded-lg">
                        <div>
                            <span class="block text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500">Aktivasi</span>
                            <span class="font-mono text-slate-700 dark:text-slate-300">
                                {{ $customer->customerService?->activation_date ? \App\Helpers\FormatHelper::tanggal($customer->customerService->activation_date) : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500">Diinput Oleh</span>
                            <span class="truncate block text-slate-700 dark:text-slate-300" title="{{ $salesName }}">
                                {{ $salesName }} ({{ $salesRole }})
                            </span>
                        </div>
                    </div>

                    {{-- Financial Mini Grid --}}
                    <div class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-100 dark:border-slate-700/60">
                        <div>
                            <span class="block text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase">Langganan</span>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-100 text-xs">
                                {{ $customer->customerService ? \App\Helpers\FormatHelper::rupiah($customer->customerService->total_monthly_bill) : '—' }}
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="block text-[10px] font-semibold text-sky-600 dark:text-sky-400 uppercase">DPP (Net PPN)</span>
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs">
                                {{ $record->harga_dikurangi_ppn !== null ? \App\Helpers\FormatHelper::rupiah($record->harga_dikurangi_ppn) : '—' }}
                            </span>
                        </div>
                    </div>

                    {{-- Installation Fee Section (Mobile Actionable) --}}
                    @if($record->needsInstallationFeeValidation())
                        <div class="p-2.5 rounded-lg bg-slate-50/75 dark:bg-slate-900/60 border border-slate-200/60 dark:border-slate-700/60 space-y-2">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="font-semibold text-slate-700 dark:text-slate-300">Biaya Instalasi Bisnis:</span>
                                @if($record->installationFeeInvoice)
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase
                                        {{ $record->installationFeeInvoice->invoice_status === \App\Enums\InvoiceStatus::LUNAS
                                            ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400' }}">
                                        {{ $record->installationFeeInvoice->invoice_status->label() }}
                                    </span>
                                @endif
                            </div>

                            @if($record->installationFeeInvoice)
                                <div class="flex items-center justify-between font-mono text-xs">
                                    <span class="font-bold text-slate-800 dark:text-slate-100">
                                        {{ \App\Helpers\FormatHelper::rupiah($record->installation_fee) }}
                                    </span>
                                    <a href="{{ route('invoices.show', $record->installationFeeInvoice) }}"
                                       class="text-sky-600 dark:text-sky-400 hover:underline text-[11px]">
                                        {{ $record->installationFeeInvoice->invoice_number }}
                                    </a>
                                </div>
                            @elseif($record->canBeValidatedBy(auth()->user()))
                                <form action="{{ route('customer-acquisitions.installation-fee.update', $record) }}"
                                      method="POST"
                                      onsubmit="return confirm('Terbitkan tagihan biaya instalasi sebesar Rp ' + this.installation_fee.value + ' untuk {{ $customer->full_name }}?')"
                                      class="flex items-center gap-1.5">
                                    @csrf
                                    @method('PUT')
                                    <div class="relative flex-1">
                                        <span class="absolute left-2 text-[10px] text-slate-400 font-mono pointer-events-none top-1.5">Rp</span>
                                        <input type="number"
                                               step="0.01"
                                               min="0.01"
                                               name="installation_fee"
                                               required
                                               value="{{ $record->installation_fee }}"
                                               placeholder="0"
                                               class="w-full h-8 pl-6 pr-2 text-right font-mono text-xs border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                    </div>
                                    <button type="submit"
                                            class="h-8 px-3 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white shadow-2xs transition-colors cursor-pointer shrink-0">
                                        Terbitkan
                                    </button>
                                </form>
                            @else
                                <span class="text-[11px] text-amber-600 dark:text-amber-400">
                                    Menunggu Validasi Role Berwenang
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center">
                    <p class="text-xs text-slate-400 dark:text-slate-500">Belum ada pelanggan diverifikasi pada periode ini.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

