@extends('layouts.app')

@section('title', 'Verifikasi Registrasi - Whusnet Operasional')
@section('page_title', 'Verifikasi Registrasi')
@section('breadcrumb_parent', 'Pelanggan')
@section('breadcrumb_parent_url', route('customers.index'))

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

@php
    $totalWaiting = $customers->total();
    $currentPageCustomers = $customers->getCollection();
    $totalMonthlyPotential = $currentPageCustomers->sum(fn($c) => (float) ($c->customerService?->total_monthly_bill ?? 0));
    $uniquePopsCount = $currentPageCustomers->pluck('pop_id')->filter()->unique()->count();
    $activeSearch = request('search');
    $activePop = request('pop_id');
@endphp

<div x-data="{
    showGuide: false,
    copiedCode: null,
    copyToClipboard(text) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            this.copiedCode = text;
            setTimeout(() => {
                if (this.copiedCode === text) this.copiedCode = null;
            }, 2000);
        });
    }
}">

    {{-- ── LAYER 1: PAGE HEADER (NAKED — NO CARD WRAPPER, SESUAI DESIGN.MD TYPE A) ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Verifikasi Registrasi
                </h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/80 dark:border-amber-800/60 font-mono">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    {{ number_format($totalWaiting) }} Menunggu Verifikasi
                </span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                    Non-Skip Survey
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                Antrean peninjauan calon pelanggan baru hasil registrasi sebelum pembuatan Task &amp; FOP Task Survey.
            </p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-center shrink-0">
            <button type="button"
                    @click="showGuide = !showGuide"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors shadow-2xs cursor-pointer">
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="showGuide ? 'Tutup Panduan' : 'Panduan Alur'"></span>
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
                <div class="font-semibold text-slate-900 dark:text-slate-100">Aturan &amp; Ketentuan Verifikasi Registrasi</div>
                <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-300">
                    <li><strong class="text-slate-800 dark:text-slate-200">Tahap Awal Pendaftaran:</strong> Calon pelanggan baru yang mendaftar (jalur standar non-Skip Survey) tertahan di status <code class="font-mono text-amber-600 dark:text-amber-400">REGISTERED</code> dan belum memiliki Task FOP Survey.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Koreksi Cepat:</strong> Petugas Admin/CS dapat mengoreksi kesalahan typo data diri (nama, NIK, alamat, kontak, koordinat) atau mengganti paket langsung dari halaman tinjau tanpa harus keluar modul.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Persetujuan (Approve):</strong> Menyetujui registrasi akan otomatis menerbitkan Task &amp; FOP Task Survey dan memajukan alur calon pelanggan ke tahap <code class="font-mono text-sky-600 dark:text-sky-400">WAITING_SURVEY</code>.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Penolakan (Reject):</strong> Menolak registrasi wajib menyertakan alasan penolakan dan calon pelanggan beralih ke status <code class="font-mono text-rose-600 dark:text-rose-400">REJECTED</code>.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── LAYER 2: SUMMARY STRIP (TYPE A — FLAT BAR WITH 1 ENCLOSING CONTAINER) ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-slate-700/60 rounded-lg shadow-2xs mb-5 overflow-hidden">
        {{-- Total Menunggu Verifikasi --}}
        <div class="p-3.5 sm:p-4 border-b lg:border-b-0 border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                Antrean Verifikasi
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-amber-600 dark:text-amber-400">
                    {{ number_format($totalWaiting) }}
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    calon
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Menunggu peninjauan Admin / CS
            </p>
        </div>

        {{-- Antrean Halaman Ini --}}
        <div class="p-3.5 sm:p-4 border-b lg:border-b-0 lg:border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Tampil di Halaman Ini
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-slate-900 dark:text-slate-100">
                    {{ number_format($customers->count()) }}
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    dari {{ number_format($totalWaiting) }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Beban antrean saat ini
            </p>
        </div>

        {{-- Potensi Tagihan Bulanan --}}
        <div class="p-3.5 sm:p-4 border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                Potensi Tagihan Bulanan (Hal. Ini)
            </span>
            <div class="mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-sky-600 dark:text-sky-400 truncate block">
                    Rp {{ number_format($totalMonthlyPotential, 0, ',', '.') }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Estimasi recurring revenue
            </p>
        </div>

        {{-- Sebaran POP --}}
        <div class="p-3.5 sm:p-4">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Sebaran POP / Cabang (Hal. Ini)
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-lg sm:text-xl font-bold font-mono text-slate-900 dark:text-slate-100">
                    {{ $uniquePopsCount }}
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    titik POP
                </span>
            </div>
            <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5 font-medium truncate">
                Scope cabang terdata
            </p>
        </div>
    </div>

    {{-- ── LAYER 2: FILTER & SEARCH BAR (NAKED — NO ENCLOSING CARD, SESUAI DESIGN.MD TYPE A) ── --}}
    <div class="mb-5 space-y-2.5">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <form action="{{ route('customer-registration-verifications.index') }}" method="GET" class="flex-1 flex flex-col sm:flex-row items-stretch sm:items-center gap-2 max-w-3xl">
                {{-- Search Input with Integrated Icon & Clear Trigger --}}
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text"
                           name="search"
                           value="{{ $activeSearch }}"
                           placeholder="Cari nama, NIK, ID REG, atau nomor HP..."
                           class="w-full h-9 pl-9 pr-8 text-xs sm:text-sm font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                    @if($activeSearch)
                        <a href="{{ route('customer-registration-verifications.index', array_filter(['pop_id' => $activePop])) }}"
                           class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                           title="Bersihkan kata kunci">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </a>
                    @endif
                </div>

                {{-- POP Dropdown Filter (if available) --}}
                @if(isset($pops) && $pops->count() > 1)
                    <div class="sm:w-48 shrink-0">
                        <select name="pop_id"
                                onchange="this.form.submit()"
                                class="w-full h-9 px-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all cursor-pointer shadow-2xs">
                            <option value="">Semua POP / Cabang</option>
                            @foreach($pops as $pop)
                                <option value="{{ $pop->id }}" {{ $activePop == $pop->id ? 'selected' : '' }}>
                                    {{ $pop->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Action Buttons: Cari & Reset --}}
                <div class="flex items-center gap-2 shrink-0">
                    <button type="submit"
                            class="h-9 px-4 inline-flex items-center justify-center gap-1.5 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white shadow-2xs transition-colors cursor-pointer w-full sm:w-auto">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <span>Cari</span>
                    </button>

                    @if($activeSearch || $activePop)
                        <a href="{{ route('customer-registration-verifications.index') }}"
                           class="h-9 px-3 inline-flex items-center justify-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 transition-colors shadow-2xs"
                           title="Reset seluruh filter">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span>Reset</span>
                        </a>
                    @endif
                </div>
            </form>

            {{-- Result Counter Info (Right Side) --}}
            @if($activeSearch || $activePop)
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 self-start lg:self-center shrink-0">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700">
                        <span>Hasil filter:</span>
                        <strong class="font-mono text-slate-800 dark:text-slate-200">{{ number_format($customers->total()) }}</strong> calon
                    </span>
                </div>
            @endif
        </div>

        {{-- Active Filter Tags (if filtering) --}}
        @if($activeSearch || $activePop)
            <div class="flex items-center gap-2 flex-wrap text-xs pt-1">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Filter Aktif:</span>
                @if($activeSearch)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60">
                        <span>Kata kunci: <strong class="font-mono font-semibold">"{{ $activeSearch }}"</strong></span>
                        <a href="{{ route('customer-registration-verifications.index', array_filter(['pop_id' => $activePop])) }}" class="hover:text-rose-600 dark:hover:text-rose-400 ml-0.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    </span>
                @endif
                @if($activePop && isset($pops))
                    @php $currentPopName = $pops->firstWhere('id', $activePop)?->name ?? 'POP #'.$activePop; @endphp
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60">
                        <span>POP: <strong class="font-semibold">{{ $currentPopName }}</strong></span>
                        <a href="{{ route('customer-registration-verifications.index', array_filter(['search' => $activeSearch])) }}" class="hover:text-rose-600 dark:hover:text-rose-400 ml-0.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- ── LAYER 3: PRIMARY CONTENT — TABLE PANEL (CARD BUDGET = 1) ── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg shadow-2xs overflow-hidden">

        {{-- DESKTOP / LAPTOP / TABLET VIEW (TABLE VIEW) --}}
        <div class="hidden md:block overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/75 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-700/80 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                        <th class="px-4 py-3">ID REG</th>
                        <th class="px-4 py-3">Calon Pelanggan</th>
                        <th class="px-4 py-3">POP &amp; Wilayah</th>
                        <th class="px-4 py-3">Paket &amp; Tagihan</th>
                        <th class="px-4 py-3">Waktu Registrasi</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($customers as $customer)
                        @php
                            $service = $customer->customerService;
                            $monthlyBill = $service?->total_monthly_bill ?? ($customer->internetPackage?->monthly_price ?? 0);
                            $packageName = $service?->internetPackage?->name ?? ($customer->internetPackage?->name ?? ($service?->package_name_snapshot ?? '—'));
                        @endphp
                        <tr class="hover:bg-sky-50/30 dark:hover:bg-sky-950/20 transition-colors">
                            {{-- ID REG --}}
                            <td class="px-4 py-3 align-top whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('customer-registration-verifications.show', $customer) }}"
                                       class="font-mono font-bold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 transition-colors">
                                        {{ $customer->customer_code }}
                                    </a>
                                    <button type="button"
                                            @click="copyToClipboard('{{ $customer->customer_code }}')"
                                            class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                            :title="copiedCode === '{{ $customer->customer_code }}' ? 'Tersalin!' : 'Salin ID'">
                                        <template x-if="copiedCode === '{{ $customer->customer_code }}'">
                                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </template>
                                        <template x-if="copiedCode !== '{{ $customer->customer_code }}'">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        </template>
                                    </button>
                                </div>
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                                    {{ ucfirst($customer->customer_type ?? 'Individu') }}
                                </span>
                            </td>

                            {{-- Calon Pelanggan --}}
                            <td class="px-4 py-3 align-top">
                                <div class="flex items-start gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">
                                        {{ strtoupper(substr($customer->full_name, 0, 2)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('customer-registration-verifications.show', $customer) }}"
                                           class="font-semibold text-slate-800 dark:text-slate-100 hover:text-sky-600 dark:hover:text-sky-400 transition-colors block truncate max-w-[220px]"
                                           title="{{ $customer->full_name }}">
                                            {{ $customer->full_name }}
                                        </a>
                                        <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-500 dark:text-slate-400 flex-wrap font-mono">
                                            @if($customer->primary_phone)
                                                <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $customer->primary_phone)) }}"
                                                   target="_blank"
                                                   class="inline-flex items-center gap-1 text-slate-600 dark:text-slate-300 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">
                                                    <svg class="w-3 h-3 text-emerald-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.699c.971.53 1.771.815 2.796.815 3.181 0 5.767-2.586 5.768-5.766 0-3.18-2.587-5.767-5.768-5.767zm3.391 8.187c-.141.396-.713.729-1.002.775-.289.046-.657.067-1.077-.07-.42-.138-.97-.333-1.666-.636-1.579-.687-2.607-2.313-2.686-2.418-.079-.105-.644-.858-.644-1.636 0-.777.408-1.161.554-1.319.146-.158.32-.198.427-.198.106 0 .213.001.306.006.098.005.23-.037.36.275.136.326.464 1.134.505 1.218.041.084.068.182.014.29-.055.107-.082.174-.163.269-.082.095-.172.213-.246.286-.082.081-.168.17-.072.335.096.165.426.703.914 1.138.628.56 1.157.733 1.322.815.165.082.262.069.359-.043.097-.112.417-.487.528-.654.111-.167.223-.139.375-.083.153.056.969.457 1.136.541.167.084.278.125.32.195.041.069.041.402-.1.798z"/></svg>
                                                    {{ $customer->primary_phone }}
                                                </a>
                                            @endif
                                            @if($customer->identity_number)
                                                <span>·</span>
                                                <span class="text-slate-400 dark:text-slate-500">NIK: {{ $customer->identity_number }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- POP & Wilayah --}}
                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700">
                                    <svg class="w-3 h-3 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    {{ $customer->pop?->name ?? '—' }}
                                </span>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 truncate max-w-[200px]" title="{{ $customer->village ? 'Desa '.$customer->village->name.', Kec. '.($customer->village->district->name ?? '-') : $customer->address }}">
                                    @if($customer->village)
                                        Desa {{ $customer->village->name }}, Kec. {{ $customer->village->district->name ?? '-' }}
                                    @else
                                        {{ $customer->address ?? '—' }}
                                    @endif
                                </p>
                            </td>

                            {{-- Paket & Tagihan --}}
                            <td class="px-4 py-3 align-top">
                                <span class="font-semibold text-slate-800 dark:text-slate-200 block truncate max-w-[180px]" title="{{ $packageName }}">
                                    {{ $packageName }}
                                </span>
                                <span class="font-mono font-semibold text-sky-600 dark:text-sky-400 text-xs block mt-0.5">
                                    Rp {{ number_format($monthlyBill, 0, ',', '.') }}<span class="text-[10px] text-slate-400 dark:text-slate-500 font-normal">/bln</span>
                                </span>
                            </td>

                            {{-- Waktu Registrasi --}}
                            <td class="px-4 py-3 align-top whitespace-nowrap">
                                <span class="font-mono text-slate-700 dark:text-slate-300 block">
                                    {{ \App\Support\IndonesianDate::dateTime($customer->created_at) }}
                                </span>
                                <span class="text-[10px] text-slate-400 dark:text-slate-500 block mt-0.5">
                                    {{ $customer->created_at?->diffForHumans() }}
                                    @if($customer->creator)
                                        · oleh {{ $customer->creator->name }}
                                    @endif
                                </span>
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-3 align-top text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/80 dark:border-amber-800/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    Menunggu Verifikasi
                                </span>
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3 align-top text-right whitespace-nowrap">
                                <a href="{{ route('customer-registration-verifications.show', $customer) }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white shadow-2xs transition-colors">
                                    <span>Tinjau</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="max-w-xs mx-auto text-center space-y-2">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Antrean Bersih</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Tidak ada registrasi calon pelanggan yang menunggu verifikasi saat ini{{ $activeSearch || $activePop ? ' yang cocok dengan filter' : '' }}.
                                    </p>
                                    @if($activeSearch || $activePop)
                                        <a href="{{ route('customer-registration-verifications.index') }}"
                                           class="inline-block mt-2 text-xs text-sky-600 dark:text-sky-400 hover:underline font-medium">
                                            Bersihkan Filter Pencarian
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
            @forelse($customers as $customer)
                @php
                    $service = $customer->customerService;
                    $monthlyBill = $service?->total_monthly_bill ?? ($customer->internetPackage?->monthly_price ?? 0);
                    $packageName = $service?->internetPackage?->name ?? ($customer->internetPackage?->name ?? ($service?->package_name_snapshot ?? '—'));
                @endphp
                <div class="p-4 space-y-3">
                    {{-- Row 1: Header --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-amber-500/10 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-bold text-xs flex items-center justify-center shrink-0">
                                {{ strtoupper(substr($customer->full_name, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('customer-registration-verifications.show', $customer) }}"
                                   class="font-semibold text-slate-800 dark:text-slate-100 hover:text-sky-600 dark:hover:text-sky-400 transition-colors truncate block text-sm">
                                    {{ $customer->full_name }}
                                </a>
                                <div class="flex items-center gap-1.5 text-[10px] text-slate-400 dark:text-slate-500 font-mono mt-0.5">
                                    <span class="text-sky-600 dark:text-sky-400 font-semibold">{{ $customer->customer_code }}</span>
                                    <span>·</span>
                                    <span>{{ $customer->pop?->name ?? 'Tanpa POP' }}</span>
                                </div>
                            </div>
                        </div>

                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/80 dark:border-amber-800/60 shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            Menunggu
                        </span>
                    </div>

                    {{-- Row 2: Details Grid --}}
                    <div class="grid grid-cols-2 gap-2 text-xs pt-1 border-t border-slate-100 dark:border-slate-700/50">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">Paket &amp; Tagihan</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200 block truncate">{{ $packageName }}</span>
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs">
                                Rp {{ number_format($monthlyBill, 0, ',', '.') }}/bln
                            </span>
                        </div>

                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">Kontak &amp; Telepon</span>
                            @if($customer->primary_phone)
                                <a href="tel:{{ $customer->primary_phone }}" class="font-mono text-slate-700 dark:text-slate-300 block truncate font-medium">
                                    {{ $customer->primary_phone }}
                                </a>
                            @else
                                <span class="text-slate-400 dark:text-slate-500">-</span>
                            @endif
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 block truncate">
                                {{ $customer->village ? 'Desa '.$customer->village->name : ($customer->address ?? '-') }}
                            </span>
                        </div>
                    </div>

                    {{-- Row 3: Action --}}
                    <div class="pt-2 flex items-center justify-between border-t border-slate-100 dark:border-slate-700/50">
                        <span class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                            {{ \App\Support\IndonesianDate::dateTime($customer->created_at) }}
                        </span>
                        <a href="{{ route('customer-registration-verifications.show', $customer) }}"
                           class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition-colors">
                            <span>Tinjau Registrasi</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center">
                    <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Antrean Bersih</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        Tidak ada calon pelanggan yang menunggu verifikasi saat ini.
                    </p>
                </div>
            @endforelse
        </div>

        {{-- ── LAYER 4: PAGINATION FOOTER ── --}}
        @if($customers->hasPages())
            <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-900/30">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                    <div>
                        Menampilkan <span class="font-mono font-semibold text-slate-700 dark:text-slate-300">{{ $customers->firstItem() ?? 0 }}</span>
                        sampai <span class="font-mono font-semibold text-slate-700 dark:text-slate-300">{{ $customers->lastItem() ?? 0 }}</span>
                        dari <span class="font-mono font-semibold text-slate-700 dark:text-slate-300">{{ $customers->total() }}</span> calon pelanggan
                    </div>
                    <div>
                        {{ $customers->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
