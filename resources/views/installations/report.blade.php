@extends('layouts.app')

@section('title', 'Lapor Hasil Pemasangan — Whusnet Operasional')
@section('page_title', 'Lapor Hasil Pemasangan')
@section('breadcrumb_parent', 'Antrean Pemasangan')
@section('breadcrumb_parent_url', route('verifications.queue'))

@section('content')
<style>
    @keyframes stepFadeIn {
        from {
            opacity: 0;
            transform: translateY(6px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .step-panel.is-active {
        animation: stepFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @media (prefers-reduced-motion: reduce) {
        .step-panel.is-active {
            animation: none;
            opacity: 1;
            transform: none;
        }
    }
    /* Tab button reset: prevent black outline / focus ring flash when switching tabs */
    button[id^="mobile-step-btn-"],
    button[id^="step-nav-"] {
        outline: none !important;
        -webkit-tap-highlight-color: transparent !important;
    }
    button[id^="mobile-step-btn-"]:focus,
    button[id^="mobile-step-btn-"]:focus-visible,
    button[id^="step-nav-"]:focus,
    button[id^="step-nav-"]:focus-visible {
        outline: none !important;
        box-shadow: none;
    }
</style>
<div class="max-w-6xl mx-auto space-y-4 pb-28 sm:pb-16">

    <!-- LAYER 1: NAKED PAGE HEADER & CUSTOMER CONTEXT (Card Budget = 1 Principle) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ $returnTo }}" class="p-1.5 border border-slate-200 dark:border-slate-700 rounded-md text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors inline-flex items-center gap-1.5 shadow-xs bg-white dark:bg-slate-900 shrink-0">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                <span class="hidden sm:inline">Kembali</span>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-base sm:text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100 truncate">{{ $customer->full_name }}</h1>
                    <span class="px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        {{ $customer->display_id }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
                    {{ $customer->address }} &bull; {{ $customer->village->name ?? '-' }}, {{ $customer->district->name ?? '-' }}
                </p>
            </div>
        </div>
    </div>

    <div id="wizard-container" class="space-y-4">

        <!-- ENTERPRISE METRIC & PROGRESS STRIP (Card Budget = 1 Principle) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg shadow-xs overflow-hidden">
            <div class="px-3.5 py-2.5 sm:px-4 sm:py-2.5 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-2 h-2 rounded-full bg-sky-500 shrink-0 animate-pulse"></span>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 tracking-tight">Kelengkapan Laporan Pemasangan</span>
                    <span class="text-[11px] text-slate-400 dark:text-slate-500 hidden sm:inline">&bull; Data teknis, perangkat, foto bukti &amp; speedtest</span>
                </div>
                <div class="flex items-center gap-2.5 shrink-0">
                    <span class="text-[11px] font-mono text-slate-500 dark:text-slate-400">
                        <strong id="filled-fields-count" class="font-semibold text-slate-800 dark:text-slate-200 data-text">0</strong>/<span id="total-fields-count" class="data-text">12</span> field wajib
                    </span>
                    <span id="progress-percentage" class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 data-text">0%</span>
                </div>
            </div>
            <!-- Hairline Flush Progress Fill Strip -->
            <div class="w-full bg-slate-100 dark:bg-slate-800 h-1 overflow-hidden">
                <div id="progress-bar-fill" class="bg-sky-600 dark:bg-sky-500 h-full w-0 transition-all duration-300 ease-out" style="width: 0%;"></div>
            </div>
        </div>

        <!-- MOBILE SEGMENTED STEPPER (WCAG 2.5.5 Touch Target & High Contrast) -->
        <div class="lg:hidden bg-slate-100 dark:bg-slate-800/80 p-1.5 rounded-lg border border-slate-200 dark:border-slate-700/80 shadow-xs space-y-1.5">
            <!-- Active Step Contextual Banner -->
            <div class="flex items-center justify-between px-1 text-xs">
                <div class="flex items-center gap-1.5 min-w-0">
                    <span id="mobile-step-pill" class="text-[10px] font-mono font-bold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/70 border border-sky-200 dark:border-sky-800 px-1.5 py-0.5 rounded shrink-0">Langkah 1/6</span>
                    <span id="mobile-active-step-title" class="font-semibold text-slate-800 dark:text-slate-100 truncate text-xs">Data Diri Pelanggan</span>
                </div>
                <span id="mobile-active-step-status" class="text-[10px] font-mono font-bold text-emerald-600 dark:text-emerald-400 shrink-0">Lengkap</span>
            </div>

            <!-- 6-Step Touch Grid (min-h-[44px] touch target) -->
            <div class="grid grid-cols-6 gap-1 bg-white/60 dark:bg-slate-900/40 p-1 rounded-md border border-slate-200/60 dark:border-slate-700/60 text-center">
                <!-- Step 1: Diri -->
                <button type="button" onclick="goToStep(1)" id="mobile-step-btn-1" class="min-h-[44px] py-1.5 px-0.5 rounded-md text-center transition-colors duration-150 flex flex-col items-center justify-center gap-1 focus:outline-none bg-white dark:bg-slate-900 text-sky-700 dark:text-sky-300 shadow-xs border border-slate-200 dark:border-slate-700">
                    <span id="mobile-step-badge-1" class="w-5 h-5 rounded-full bg-sky-600 text-white text-[10px] font-mono font-bold flex items-center justify-center shrink-0 shadow-xs">1</span>
                    <span id="mobile-step-label-1" class="text-[11px] font-bold tracking-tight text-sky-700 dark:text-sky-300 leading-none">Diri</span>
                </button>

                <!-- Step 2: Dok -->
                <button type="button" onclick="goToStep(2)" id="mobile-step-btn-2" class="min-h-[44px] py-1.5 px-0.5 rounded-md text-center transition-colors duration-150 flex flex-col items-center justify-center gap-1 focus:outline-none text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-transparent">
                    <span id="mobile-step-badge-2" class="w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0">✓</span>
                    <span id="mobile-step-label-2" class="text-[11px] font-semibold tracking-tight text-slate-700 dark:text-slate-300 leading-none">Dok</span>
                </button>

                <!-- Step 3: Paket -->
                <button type="button" onclick="goToStep(3)" id="mobile-step-btn-3" class="min-h-[44px] py-1.5 px-0.5 rounded-md text-center transition-colors duration-150 flex flex-col items-center justify-center gap-1 focus:outline-none text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-transparent">
                    <span id="mobile-step-badge-3" class="w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0">✓</span>
                    <span id="mobile-step-label-3" class="text-[11px] font-semibold tracking-tight text-slate-700 dark:text-slate-300 leading-none">Paket</span>
                </button>

                <!-- Step 4: Survey -->
                <button type="button" onclick="goToStep(4)" id="mobile-step-btn-4" class="min-h-[44px] py-1.5 px-0.5 rounded-md text-center transition-colors duration-150 flex flex-col items-center justify-center gap-1 focus:outline-none text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-transparent">
                    <span id="mobile-step-badge-4" class="w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0">✓</span>
                    <span id="mobile-step-label-4" class="text-[11px] font-semibold tracking-tight text-slate-700 dark:text-slate-300 leading-none">Survey</span>
                </button>

                <!-- Step 5: Alat -->
                <button type="button" onclick="goToStep(5)" id="mobile-step-btn-5" class="min-h-[44px] py-1.5 px-0.5 rounded-md text-center transition-colors duration-150 flex flex-col items-center justify-center gap-1 focus:outline-none text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-transparent">
                    <span id="mobile-step-badge-5" class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-[10px] font-mono font-bold flex items-center justify-center shrink-0">5</span>
                    <span id="mobile-step-label-5" class="text-[11px] font-semibold tracking-tight text-slate-700 dark:text-slate-300 leading-none">Alat</span>
                </button>

                <!-- Step 6: Uji -->
                <button type="button" onclick="goToStep(6)" id="mobile-step-btn-6" class="min-h-[44px] py-1.5 px-0.5 rounded-md text-center transition-colors duration-150 flex flex-col items-center justify-center gap-1 focus:outline-none text-slate-500 dark:text-slate-400 border border-transparent">
                    <span id="mobile-step-badge-6" class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-[10px] font-mono font-bold flex items-center justify-center shrink-0">
                        <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </span>
                    <span id="mobile-step-label-6" class="text-[11px] font-semibold tracking-tight text-slate-500 dark:text-slate-400 leading-none">Uji</span>
                </button>
            </div>
        </div>

        <!-- MAIN GRID LAYOUT -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

            <!-- LEFT COLUMN: Desktop 6-Step Checklist (Visible on >= lg screens) -->
            <div class="hidden lg:block lg:col-span-4 space-y-3 sticky top-6">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg p-3.5 shadow-xs space-y-2.5">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                        <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Tahapan Laporan</h4>
                        <span class="text-[10px] font-mono text-slate-400">6 Langkah</span>
                    </div>

                    <div class="space-y-1.5">
                        <!-- Step 1 Navigation Card -->
                        <button type="button" onclick="goToStep(1)" id="step-nav-1" class="w-full text-left p-2.5 rounded-md border border-sky-500 bg-sky-50/50 dark:bg-sky-950/30 transition-colors group focus:outline-none">
                            <div class="flex items-center gap-2.5">
                                <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <span class="block text-xs font-semibold text-slate-900 dark:text-slate-100">1. Data Diri Pelanggan</span>
                                    <span class="text-[10px] font-medium text-emerald-600 dark:text-emerald-400 block">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 2 Navigation Card -->
                        <button type="button" onclick="goToStep(2)" id="step-nav-2" class="w-full text-left p-2.5 rounded-md border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group focus:outline-none">
                            <div class="flex items-center gap-2.5">
                                <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200">2. Dokumen Lampiran</span>
                                    <span class="text-[10px] font-medium text-emerald-600 dark:text-emerald-400 block">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 3 Navigation Card -->
                        <button type="button" onclick="goToStep(3)" id="step-nav-3" class="w-full text-left p-2.5 rounded-md border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group focus:outline-none">
                            <div class="flex items-center gap-2.5">
                                <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200">3. Layanan &amp; Paket</span>
                                    <span class="text-[10px] font-medium text-emerald-600 dark:text-emerald-400 block">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 4 Navigation Card -->
                        <button type="button" onclick="goToStep(4)" id="step-nav-4" class="w-full text-left p-2.5 rounded-md border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group focus:outline-none">
                            <div class="flex items-center gap-2.5">
                                <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200">4. Laporan Survey Lapangan</span>
                                    <span class="text-[10px] font-medium text-emerald-600 dark:text-emerald-400 block">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 5 Navigation Card -->
                        <button type="button" onclick="goToStep(5)" id="step-nav-5" class="w-full text-left p-2.5 rounded-md border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group focus:outline-none">
                            <div class="flex items-start gap-2.5">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-5">
                                    <span class="w-4 h-4 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500">
                                        <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200">5. Perangkat &amp; Instalasi</span>
                                    <span id="step-nav-status-5" class="text-[10px] font-medium text-slate-500 dark:text-slate-400 block">Belum Lengkap</span>
                                    <span id="step-nav-missing-5" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-0.5 leading-snug"></span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 6 Navigation Card -->
                        <button type="button" onclick="goToStep(6)" id="step-nav-6" class="w-full text-left p-2.5 rounded-md border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group focus:outline-none">
                            <div class="flex items-start gap-2.5">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-6">
                                    <span class="w-4 h-4 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500">
                                        <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200">6. Laporan Speedtest</span>
                                    <span id="step-nav-status-6" class="text-[10px] font-medium text-slate-500 dark:text-slate-400 block">Belum Lengkap</span>
                                    <span id="step-nav-missing-6" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-0.5 leading-snug"></span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Wizard Steps Form Panels -->
            <div class="lg:col-span-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg shadow-xs overflow-hidden flex flex-col justify-between min-h-[520px]">

                <!-- FORM BODY -->
                <div class="p-4 sm:p-6 flex-1">

                    <!-- STEP 1 PANEL: Data Diri Pelanggan -->
                    <div id="step-panel-1" class="step-panel space-y-4 is-active" x-data="{ editingIdentity: false }">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs flex items-center justify-center">1</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-100">Identitas &amp; Alamat Pelanggan</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Data registrasi calon pelanggan</p>
                                </div>
                            </div>
                            <button type="button" x-show="!editingIdentity" @click="editingIdentity = true"
                                    class="shrink-0 text-xs font-medium px-2.5 py-1 rounded-md bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 transition-colors inline-flex items-center gap-1 shadow-xs">
                                <x-ui.icon name="edit" class="w-3 h-3" />
                                <span>Edit Data</span>
                            </button>
                            <button type="button" x-show="editingIdentity" x-cloak @click="editingIdentity = false"
                                    class="shrink-0 text-xs font-medium px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                Batal
                            </button>
                        </div>

                        <!-- EDIT MODE FORM -->
                        <form x-show="editingIdentity" x-cloak action="{{ route('customers.installation.update-identity', $customer->id) }}" method="POST"
                              class="bg-slate-50/70 dark:bg-slate-950/40 rounded-lg p-4 border border-slate-200 dark:border-slate-800 space-y-3.5 text-xs">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nama Lengkap *</span>
                                    <input type="text" name="full_name" value="{{ old('full_name', $customer->full_name) }}" required maxlength="150"
                                           class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nomor Identitas (NIK)</span>
                                    <input type="text" name="identity_number" value="{{ old('identity_number', $customer->identity_number) }}" maxlength="16" inputmode="numeric"
                                           class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-mono text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nomor HP Utama *</span>
                                    <input type="text" name="primary_phone" value="{{ old('primary_phone', $customer->primary_phone ?? $customer->phone) }}" required maxlength="20" inputmode="tel"
                                           class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-mono text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nomor HP Alternatif</span>
                                    <input type="text" name="alternative_phone" value="{{ old('alternative_phone', $customer->alternative_phone) }}" maxlength="20" inputmode="tel"
                                           class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-mono text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Alamat Email</span>
                                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" maxlength="100"
                                           class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Alamat Instalasi Lengkap *</span>
                                    <textarea name="address" required rows="2"
                                              class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">{{ old('address', $customer->address) }}</textarea>
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Kota/Kabupaten</span>
                                    <select id="identity-city_id" name="city_id" data-selected-district="{{ old('district_id', $customer->district_id) }}" data-selected-village="{{ old('village_id', $customer->village_id) }}" onchange="wilayahLoadDistricts(this.value)"
                                            class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                        <option value="">Pilih Kota/Kabupaten</option>
                                        @foreach($cities as $city)
                                            <option value="{{ $city->id }}" {{ old('city_id', $customer->city_id) == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Kecamatan</span>
                                    <select id="identity-district_id" name="district_id" onchange="wilayahLoadVillages(this.value)"
                                            class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                        <option value="">Pilih kota/kabupaten dulu</option>
                                    </select>
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Desa/Kelurahan</span>
                                    <select id="identity-village_id" name="village_id"
                                            class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                        <option value="">Pilih kecamatan dulu</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Latitude</span>
                                    <input type="text" name="latitude" value="{{ old('latitude', $customer->latitude) }}" inputmode="decimal"
                                           class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-mono text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Longitude</span>
                                    <input type="text" name="longitude" value="{{ old('longitude', $customer->longitude) }}" inputmode="decimal"
                                           class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-mono text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                </label>
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-800">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">Simpan perubahan data diri</span>
                                <button type="submit"
                                        class="px-3.5 py-1.5 rounded-md bg-sky-600 hover:bg-sky-700 text-white text-xs font-medium shadow-xs inline-flex items-center gap-1.5">
                                    <x-ui.icon name="save" class="w-3.5 h-3.5" />
                                    <span>Simpan Data Diri</span>
                                </button>
                            </div>
                        </form>

                        <!-- READ ONLY DEFINITION TABLE (Stripe/Linear style) -->
                        <div x-show="!editingIdentity" class="border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            <div class="grid grid-cols-1 sm:grid-cols-3 p-3 bg-white dark:bg-slate-900 gap-1 sm:gap-2">
                                <span class="text-slate-500 dark:text-slate-400 font-medium">Nama Lengkap &amp; NIK</span>
                                <div class="sm:col-span-2 font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2 flex-wrap">
                                    <span>{{ $customer->full_name }}</span>
                                    <span class="text-[11px] font-mono text-slate-500 dark:text-slate-400 font-normal">NIK: {{ $customer->identity_number ?? '-' }}</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 p-3 bg-white dark:bg-slate-900 gap-1 sm:gap-2">
                                <span class="text-slate-500 dark:text-slate-400 font-medium">Kontak Telepon</span>
                                <div class="sm:col-span-2 font-mono text-slate-800 dark:text-slate-200 flex items-center gap-2 flex-wrap">
                                    <span>{{ $customer->primary_phone ?? $customer->phone ?? '-' }}</span>
                                    @if($customer->alternative_phone)
                                        <span class="text-slate-400 dark:text-slate-500 font-normal text-[11px]">Alt: {{ $customer->alternative_phone }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 p-3 bg-white dark:bg-slate-900 gap-1 sm:gap-2">
                                <span class="text-slate-500 dark:text-slate-400 font-medium">Alamat Instalasi</span>
                                <div class="sm:col-span-2 text-slate-800 dark:text-slate-200 leading-snug">
                                    {{ $customer->address }}
                                    <span class="block text-slate-500 dark:text-slate-400 text-[11px] mt-0.5">
                                        Kel. {{ $customer->village->name ?? '-' }}, Kec. {{ $customer->district->name ?? '-' }}, {{ $customer->city->name ?? '-' }}
                                    </span>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 p-3 bg-white dark:bg-slate-900 gap-1 sm:gap-2">
                                <span class="text-slate-500 dark:text-slate-400 font-medium">Koordinat GPS &amp; POP</span>
                                <div class="sm:col-span-2 text-slate-800 dark:text-slate-200 flex items-center gap-3 flex-wrap">
                                    <span class="font-mono text-xs">{{ $customer->latitude ?? '-' }}, {{ $customer->longitude ?? '-' }}</span>
                                    <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                                    <span class="text-xs font-medium text-sky-600 dark:text-sky-400">{{ $customer->pop->name ?? '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 PANEL: Dokumen Lampiran -->
                    <div id="step-panel-2" class="step-panel space-y-4 hidden" x-data="{ editingPhotos: false }">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs flex items-center justify-center">2</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-100">Dokumen Lampiran Survey</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Foto rumah &amp; ODP dari laporan survey awal</p>
                                </div>
                            </div>
                            <button type="button" x-show="!editingPhotos" @click="editingPhotos = true"
                                    class="shrink-0 text-xs font-medium px-2.5 py-1 rounded-md bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 transition-colors inline-flex items-center gap-1 shadow-xs">
                                <x-ui.icon name="camera" class="w-3 h-3" />
                                <span>Ganti Foto</span>
                            </button>
                            <button type="button" x-show="editingPhotos" x-cloak @click="editingPhotos = false"
                                    class="shrink-0 text-xs font-medium px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                Batal
                            </button>
                        </div>

                        <!-- EDIT PHOTOS FORM -->
                        <form x-show="editingPhotos" x-cloak action="{{ route('customers.installation.update-photos', $customer->id) }}" method="POST" enctype="multipart/form-data"
                              class="bg-slate-50/70 dark:bg-slate-950/40 rounded-lg p-4 border border-slate-200 dark:border-slate-800 space-y-3.5 text-xs">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Foto Rumah</span>
                                    <input type="file" name="house_photo" accept="image/*"
                                           class="w-full text-xs file:mr-2.5 file:px-2.5 file:py-1.5 file:rounded-md file:border-0 file:bg-slate-100 file:text-slate-700 dark:file:bg-slate-800 dark:file:text-slate-300">
                                    @error('house_photo')<p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>@enderror
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Foto ODP Terdekat</span>
                                    <input type="file" name="survey_photo" accept="image/*"
                                           class="w-full text-xs file:mr-2.5 file:px-2.5 file:py-1.5 file:rounded-md file:border-0 file:bg-slate-100 file:text-slate-700 dark:file:bg-slate-800 dark:file:text-slate-300">
                                    @error('survey_photo')<p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>@enderror
                                </label>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-800">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">Maks. 2 MB per foto</span>
                                <button type="submit" class="px-3.5 py-1.5 rounded-md bg-sky-600 hover:bg-sky-700 text-white text-xs font-medium shadow-xs inline-flex items-center gap-1.5">
                                    <x-ui.icon name="save" class="w-3.5 h-3.5" />
                                    <span>Simpan Foto</span>
                                </button>
                            </div>
                        </form>

                        <!-- READ ONLY PHOTO CARDS -->
                        <div x-show="!editingPhotos" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <!-- Foto Rumah -->
                            <div class="border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 rounded-lg p-3.5 flex flex-col justify-between shadow-xs text-center">
                                @php $fotoRumahUrl = foto_publik($customer->latestSurvey?->house_photo); @endphp
                                @if($fotoRumahUrl)
                                    <div>
                                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Foto Rumah</span>
                                        <div class="relative rounded-md overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50">
                                            <img class="max-h-36 max-w-full rounded-md object-contain mx-auto cursor-pointer"
                                                 src="{{ $fotoRumahUrl }}"
                                                 alt="Preview Foto Rumah"
                                                 onclick="window.open('{{ $fotoRumahUrl }}', '_blank')">
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center justify-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400 mt-2.5">
                                        <x-ui.icon name="check" class="w-3 h-3" /> Terlampir
                                    </span>
                                @else
                                    <div class="flex flex-col justify-center py-6">
                                        <div class="w-10 h-10 mx-auto rounded-md bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mb-2">
                                            <x-ui.icon name="house" class="w-4 h-4" />
                                        </div>
                                        <span class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Foto Rumah</span>
                                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-1">Tidak ada di survey</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Foto ODP -->
                            <div class="border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 rounded-lg p-3.5 flex flex-col justify-between shadow-xs text-center">
                                @php $fotoOdpUrl = foto_publik($customer->latestSurvey?->survey_photo); @endphp
                                @if($fotoOdpUrl)
                                    <div>
                                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Foto ODP Terdekat</span>
                                        <div class="relative rounded-md overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50">
                                            <img class="max-h-36 max-w-full rounded-md object-contain mx-auto cursor-pointer"
                                                 src="{{ $fotoOdpUrl }}"
                                                 alt="Preview Foto ODP"
                                                 onclick="window.open('{{ $fotoOdpUrl }}', '_blank')">
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center justify-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400 mt-2.5">
                                        <x-ui.icon name="check" class="w-3 h-3" /> Terlampir
                                    </span>
                                @else
                                    <div class="flex flex-col justify-center py-6">
                                        <div class="w-10 h-10 mx-auto rounded-md bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mb-2">
                                            <x-ui.icon name="network" class="w-4 h-4" />
                                        </div>
                                        <span class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Foto ODP</span>
                                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-1">Tidak ada di survey</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3 PANEL: Layanan & Paket -->
                    <div id="step-panel-3" class="step-panel space-y-4 hidden" x-data="{ editingPackage: false }">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs flex items-center justify-center">3</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-100">Layanan &amp; Paket Internet</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Paket layanan aktif yang dikonfirmasi</p>
                                </div>
                            </div>
                            <button type="button" x-show="!editingPackage" @click="editingPackage = true"
                                    class="shrink-0 text-xs font-medium px-2.5 py-1 rounded-md bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 transition-colors inline-flex items-center gap-1 shadow-xs">
                                <x-ui.icon name="edit" class="w-3 h-3" />
                                <span>Edit Paket</span>
                            </button>
                            <button type="button" x-show="editingPackage" x-cloak @click="editingPackage = false"
                                    class="shrink-0 text-xs font-medium px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                Batal
                            </button>
                        </div>

                        <!-- EDIT PACKAGE FORM -->
                        <form x-show="editingPackage" x-cloak action="{{ route('customers.installation.update-package', $customer->id) }}" method="POST"
                              class="bg-slate-50/70 dark:bg-slate-950/40 rounded-lg p-4 border border-slate-200 dark:border-slate-800 space-y-3.5 text-xs">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            <label class="block">
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Pilih Paket Internet *</span>
                                <select name="internet_package_id" required
                                        class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                    <option value="">Pilih Paket Internet</option>
                                    @foreach($internetPackages as $package)
                                        <option value="{{ $package->id }}" {{ (int) old('internet_package_id', $customer->internet_package_id) === $package->id ? 'selected' : '' }}>
                                            {{ $package->package_code }} — {{ $package->name }} (Rp {{ number_format($package->monthly_price, 0, ',', '.') }}/bln)
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-800">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">Diskon &amp; PPN dipertahankan</span>
                                <button type="submit" class="px-3.5 py-1.5 rounded-md bg-sky-600 hover:bg-sky-700 text-white text-xs font-medium shadow-xs inline-flex items-center gap-1.5">
                                    <x-ui.icon name="save" class="w-3.5 h-3.5" />
                                    <span>Simpan Paket</span>
                                </button>
                            </div>
                        </form>

                        <!-- READ ONLY PACKAGE SUMMARY -->
                        <div x-show="!editingPackage" class="border border-slate-200 dark:border-slate-800 rounded-lg p-3.5 space-y-3 text-xs bg-slate-50/50 dark:bg-slate-950/40">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-0.5">Paket Layanan</span>
                                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $customer->internetPackage->package_code ?? '-' }} &mdash; {{ $customer->internetPackage->name ?? 'Belum Dipilih' }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-0.5">Biaya Bulanan</span>
                                    <span class="text-sm font-semibold font-mono text-sky-600 dark:text-sky-400">Rp {{ number_format($customer->internetPackage->monthly_price ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2.5 pt-0.5">
                                <div>
                                    <span class="text-[10px] text-slate-500 dark:text-slate-400 block mb-0.5">Jenis Kontrak</span>
                                    <span class="font-medium text-slate-800 dark:text-slate-200 uppercase text-xs">{{ $customer->customerService->contract_type ?? 'Sewa' }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-500 dark:text-slate-400 block mb-0.5">Masa Kontrak</span>
                                    <span class="font-medium text-slate-800 dark:text-slate-200 text-xs">{{ $customer->customerService->contract_period_months ?? 12 }} Bulan</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-500 dark:text-slate-400 block mb-0.5">Diskon Promosi</span>
                                    <span class="font-medium font-mono text-slate-800 dark:text-slate-200 text-xs">Rp {{ number_format($customer->discount_amount ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4 PANEL: Laporan Survey (Read-Only) -->
                    <div id="step-panel-4" class="step-panel space-y-4 hidden" x-data="{ editingSurvey: false }">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs flex items-center justify-center">4</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-100">Laporan Survey Lapangan</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Hasil pengamatan awal oleh tim survey</p>
                                </div>
                            </div>
                            <button type="button" x-show="!editingSurvey" @click="editingSurvey = true"
                                    class="shrink-0 text-xs font-medium px-2.5 py-1 rounded-md bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 transition-colors inline-flex items-center gap-1 shadow-xs">
                                <x-ui.icon name="edit" class="w-3 h-3" />
                                <span>Edit Survey</span>
                            </button>
                            <button type="button" x-show="editingSurvey" x-cloak @click="editingSurvey = false"
                                    class="shrink-0 text-xs font-medium px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                Batal
                            </button>
                        </div>

                        @php $surveyForEdit = $customer->latestSurvey; @endphp
                        <form x-show="editingSurvey" x-cloak action="{{ route('customers.installation.update-survey', $customer->id) }}" method="POST"
                              class="bg-slate-50/70 dark:bg-slate-950/40 rounded-lg p-4 border border-slate-200 dark:border-slate-800 space-y-3.5 text-xs">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">ODP Terdekat</span>
                                    <input type="text" name="nearest_odp" value="{{ old('nearest_odp', $surveyForEdit?->nearest_odp) }}" maxlength="255"
                                           class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Estimasi Kabel (Meter)</span>
                                    <input type="number" name="cable_estimation_meter" min="0" value="{{ old('cable_estimation_meter', $surveyForEdit?->cable_estimation_meter) }}"
                                           class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Tanggal Request Pemasangan</span>
                                    <input type="date" name="requested_installation_date" value="{{ old('requested_installation_date', $surveyForEdit?->requested_installation_date?->format('Y-m-d')) }}"
                                           class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Tingkat Kesulitan</span>
                                    <select name="difficulty_level"
                                            class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                        <option value="">Pilih tingkat kesulitan</option>
                                        @foreach(['MUDAH' => 'Mudah', 'SEDANG' => 'Sedang', 'SULIT' => 'Sulit'] as $value => $label)
                                            <option value="{{ $value }}" {{ old('difficulty_level', $surveyFields['difficulty_level']) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Catatan Teknis Survey</span>
                                    <textarea name="survey_note" rows="2"
                                              class="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">{{ old('survey_note', $surveyFields['survey_note']) }}</textarea>
                                </label>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-800">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">Simpan perubahan survey</span>
                                <button type="submit" class="px-3.5 py-1.5 rounded-md bg-sky-600 hover:bg-sky-700 text-white text-xs font-medium shadow-xs inline-flex items-center gap-1.5">
                                    <x-ui.icon name="save" class="w-3.5 h-3.5" />
                                    <span>Simpan Survey</span>
                                </button>
                            </div>
                        </form>

                        <!-- READ ONLY SURVEY SUMMARY -->
                        <div x-show="!editingSurvey" class="border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            <div class="grid grid-cols-2 p-3 bg-white dark:bg-slate-900 gap-3">
                                <div>
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider block font-bold mb-0.5">ODP Terdekat</span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-200 font-mono">{{ $customer->latestSurvey->nearest_odp ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider block font-bold mb-0.5">Estimasi Kabel</span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-200 font-mono">{{ $customer->latestSurvey->cable_estimation_meter ?? '0' }} Meter</span>
                                </div>
                            </div>
                            @if($customer->latestSurvey?->requested_installation_date)
                                <div class="p-3 bg-white dark:bg-slate-900">
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider block font-bold mb-0.5">Tanggal Request Pemasangan</span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ \App\Support\IndonesianDate::date($customer->latestSurvey->requested_installation_date) }}</span>
                                </div>
                            @endif
                            <div class="p-3 bg-white dark:bg-slate-900">
                                <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider block font-bold mb-0.5">Tingkat Kesulitan</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200 uppercase">{{ $surveyFields['difficulty_level'] ?? '-' }}</span>
                                @if($surveyFields['survey_note'])
                                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-1.5 whitespace-pre-wrap">{{ $surveyFields['survey_note'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- STEP 5 PANEL: Laporan Pemasangan & Perangkat -->
                    <div id="step-panel-5" class="step-panel space-y-4 hidden">
                    <form id="form-pemasangan" action="{{ route('customers.installation.pemasangan', $customer->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4"
                          x-data="{ modemAcquisition: @js(old('modem_acquisition_choice', $installation->modem_acquisition_choice ?? '')) }">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ $returnTo }}">
                        <input type="hidden" name="started_at" id="hidden_started_at">

                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs flex items-center justify-center">5</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-100">Laporan Pemasangan &amp; Perangkat</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Data teknis perangkat aktif, ODP/OLT, dan bukti foto pemasangan</p>
                                </div>
                            </div>
                        </div>

                        @php
                            $failedInstallation = $customer->installations()->where('installation_status', 'failed')->latest()->first();
                            $dev = $customer->customerDevice;
                            $tech5 = $customer->customerTechnicalDetail;
                        @endphp
                        @if($failedInstallation)
                            <div class="bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 rounded-lg p-3 space-y-1.5 shadow-xs">
                                <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-800 dark:text-amber-300">
                                    <x-ui.icon name="triangle-alert" class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                                    Instruksi Revisi Pemasangan
                                </div>
                                <p class="text-[11px] text-slate-600 dark:text-slate-300">Catatan dari Admin Verifikasi:</p>
                                <p class="bg-white dark:bg-slate-900 border border-amber-200/80 dark:border-amber-900/50 p-2.5 rounded-md text-xs text-slate-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed">{{ $failedInstallation->installation_note }}</p>
                            </div>
                        @endif

                        <div class="space-y-4">

                            <!-- Section: Perangkat Aktif -->
                            <div class="p-3.5 sm:p-4 rounded-lg bg-slate-50/50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800 space-y-3">
                                <h5 class="text-xs font-semibold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="cpu" class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" /> Informasi Perangkat Aktif
                                </h5>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <label for="device_type" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Jenis Perangkat <span class="text-rose-500">*</span></label>
                                        <select name="device_type" id="device_type" class="w-full text-xs font-sans px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs">
                                            <option value="ont" {{ old('device_type', $dev->device_type ?? 'ont') === 'ont' ? 'selected' : '' }}>ONT</option>
                                            <option value="modem" {{ old('device_type', $dev->device_type ?? '') === 'modem' ? 'selected' : '' }}>Modem</option>
                                            <option value="onu" {{ old('device_type', $dev->device_type ?? '') === 'onu' ? 'selected' : '' }}>ONU</option>
                                            <option value="router" {{ old('device_type', $dev->device_type ?? '') === 'router' ? 'selected' : '' }}>Router</option>
                                            <option value="other" {{ old('device_type', $dev->device_type ?? '') === 'other' ? 'selected' : '' }}>Lainnya</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="connection_mode" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Mode Koneksi <span class="text-rose-500">*</span></label>
                                        <select name="connection_mode" id="connection_mode" class="w-full text-xs font-sans px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs">
                                            <option value="pppoe" {{ old('connection_mode', $dev->connection_mode ?? 'pppoe') === 'pppoe' ? 'selected' : '' }}>PPPoE</option>
                                            <option value="bridge" {{ old('connection_mode', $dev->connection_mode ?? '') === 'bridge' ? 'selected' : '' }}>Bridge</option>
                                            <option value="static" {{ old('connection_mode', $dev->connection_mode ?? '') === 'static' ? 'selected' : '' }}>Static IP</option>
                                            <option value="dhcp" {{ old('connection_mode', $dev->connection_mode ?? '') === 'dhcp' ? 'selected' : '' }}>DHCP</option>
                                            <option value="other" {{ old('connection_mode', $dev->connection_mode ?? '') === 'other' ? 'selected' : '' }}>Lainnya</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="brand" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Merk Perangkat</label>
                                        <input type="text" name="brand" id="brand" value="{{ old('brand', $dev->brand ?? '') }}" class="w-full text-xs font-sans px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="ZTE / Huawei / FiberHome">
                                    </div>

                                    <div>
                                        <label for="model" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Tipe Model</label>
                                        <input type="text" name="model" id="model" value="{{ old('model', $dev->model ?? '') }}" class="w-full text-xs font-sans px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="Contoh: F609 / HG8245H">
                                    </div>

                                    @if(($customer->customerService?->contract_type ?? 'sewa') === 'beli')
                                    <div class="sm:col-span-2">
                                        <label class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Cara Pemilikan Modem <span class="text-rose-500">*</span></label>
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach([
                                                'beli_sendiri' => 'Beli Sendiri',
                                                'beli_gudang' => 'Beli dari Gudang',
                                            ] as $value => $label)
                                            <label class="flex items-center gap-2 rounded-md px-3 py-2 cursor-pointer transition-colors border text-xs"
                                                   :class="modemAcquisition === '{{ $value }}' ? 'border-sky-500 bg-sky-50/50 dark:bg-sky-950/30' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900'">
                                                <input type="radio" name="modem_acquisition_choice" value="{{ $value }}" x-model="modemAcquisition"
                                                       @checked(old('modem_acquisition_choice', $installation->modem_acquisition_choice ?? '') === $value)
                                                       class="h-3.5 w-3.5 text-sky-600">
                                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ $label }}</span>
                                            </label>
                                            @endforeach
                                        </div>
                                        <p class="text-[10px] mt-1 text-slate-500 dark:text-slate-400">Pelanggan kontrak Beli — pilih apakah modem dibeli sendiri atau dibeli dari Gudang (SN custody dijual).</p>
                                    </div>
                                    @endif

                                    @php
                                        $oldSelectedSerialId = old('selected_inventory_serial_id', $installation->selected_inventory_serial_id ?? null);
                                        $serialCountsByItem = $eligibleSerials->groupBy(fn ($serial) => $serial->item->name)->map->count();
                                        $serialOptions = $eligibleSerials->map(fn ($serial) => [
                                            'value' => $serial->id,
                                            'label' => 'SN ' . $serial->serial_number . ' — ' . $serial->item->name,
                                            'search' => $serial->serial_number . ' ' . $serial->item->name,
                                            'hint' => 'Sisa custody tim: ' . $serialCountsByItem[$serial->item->name] . ' unit',
                                        ])->values();
                                    @endphp

                                    <div class="sm:col-span-2" x-show="modemAcquisition !== 'beli_sendiri'" x-cloak>
                                        <fieldset class="min-w-0" :disabled="modemAcquisition === 'beli_sendiri'">
                                        <label for="selected_inventory_serial_id-search" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Serial Number (SN) — Custody Gudang <span class="text-rose-500">*</span></label>
                                        <div class="mb-2"><x-estimasi-patokan :rows="$estimasiPerangkatAktif" title="Estimasi Perangkat Aktif" /></div>
                                        @if($eligibleSerials->isNotEmpty())
                                            <x-combobox id="selected_inventory_serial_id" name="selected_inventory_serial_id" :options-expr="json_encode($serialOptions)" :value-expr="json_encode((string) $oldSelectedSerialId)" placeholder="Ketik SN atau nama perangkat..." />
                                        @else
                                            <select disabled class="w-full text-xs font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed">
                                                <option>Tidak ada SN di custody Anda</option>
                                            </select>
                                            <p class="text-[11px] text-rose-500 mt-1 leading-relaxed">Anda belum mengambil barang dari Gudang — hubungi Admin/FOP.</p>
                                        @endif

                                        <div x-show="modemAcquisition === 'beli_gudang'" x-cloak class="mt-2.5">
                                            <label for="modem_sale_price" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Harga Jual Modem <span class="text-rose-500">*</span></label>
                                            <input type="number" step="0.01" min="0.01" name="modem_sale_price" id="modem_sale_price" value="{{ old('modem_sale_price', $installation->modem_sale_price ?? '') }}" placeholder="Contoh: 150000" class="w-full text-xs font-mono px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs">
                                            <p class="text-[10px] mt-1 text-slate-500 dark:text-slate-400">Modem ini dijual ke pelanggan — dicatat sebagai penjualan SN, ditagihkan di tagihan AWAL.</p>
                                        </div>
                                        </fieldset>
                                    </div>

                                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-2.5" x-show="modemAcquisition === 'beli_sendiri'" x-cloak>
                                        <div>
                                            <label for="manual_device_brand" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Brand <span class="text-rose-500">*</span></label>
                                            <input type="text" name="manual_device_brand" id="manual_device_brand" value="{{ old('manual_device_brand', $installation->manual_device_brand ?? '') }}" maxlength="100" placeholder="mis. TP-Link" class="w-full text-xs px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs">
                                        </div>
                                        <div>
                                            <label for="manual_device_model" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Model <span class="text-rose-500">*</span></label>
                                            <input type="text" name="manual_device_model" id="manual_device_model" value="{{ old('manual_device_model', $installation->manual_device_model ?? '') }}" maxlength="100" placeholder="mis. Archer C6" class="w-full text-xs px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs">
                                        </div>
                                        <div>
                                            <label for="manual_device_serial_number" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Serial Number <span class="text-rose-500">*</span></label>
                                            <input type="text" name="manual_device_serial_number" id="manual_device_serial_number" value="{{ old('manual_device_serial_number', $installation->manual_device_serial_number ?? '') }}" maxlength="100" placeholder="SN stiker perangkat" class="w-full text-xs font-mono px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs">
                                        </div>
                                        <p class="col-span-1 sm:col-span-3 text-[10px] text-slate-500 dark:text-slate-400">Modem milik pelanggan sendiri — tidak lewat Gudang, tidak ada SN custody yang terpakai.</p>
                                    </div>

                                    @php
                                        $oldSelectedRollId = old('selected_inventory_roll_id', $installation->selected_inventory_roll_id ?? null);
                                        $rollOptions = collect([['value' => '', 'label' => '— Tidak Pakai Roll Kabel —', 'search' => 'tidak pakai roll']])->concat($eligibleRolls->map(fn ($roll) => [
                                            'value' => $roll->id,
                                            'label' => ($roll->item->name ?? '(barang dihapus)') . ' — ' . $roll->roll_code,
                                            'search' => $roll->roll_code . ' ' . ($roll->item->name ?? ''),
                                            'hint' => 'Sisa ' . rtrim(rtrim(number_format((float) $roll->length_remaining, 2, ',', '.'), '0'), ',') . ' m',
                                        ]))->values();
                                    @endphp

                                    <div class="sm:col-span-2">
                                        <label for="selected_inventory_roll_id-search" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Roll Kabel (Opsional) — Sisa Meter Gudang</label>
                                        <div class="mb-2"><x-estimasi-patokan :rows="$estimasiRoll" title="Estimasi Roll Kabel" /></div>
                                        @if($eligibleRolls->isNotEmpty())
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                                <x-combobox id="selected_inventory_roll_id" name="selected_inventory_roll_id" :options-expr="json_encode($rollOptions)" :value-expr="json_encode((string) $oldSelectedRollId)" placeholder="Ketik kode roll atau nama kabel..." />
                                                <input type="number" step="0.01" min="0.01" name="roll_meters_used" id="roll_meters_used" value="{{ old('roll_meters_used', $installation->roll_meters_used ?? '') }}" placeholder="Meter terpakai (cth: 150)" class="w-full text-xs font-mono px-3 py-2 border @error('roll_meters_used') border-rose-500 @else border-slate-300 dark:border-slate-700 @enderror rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs">
                                            </div>
                                        @else
                                            <p class="text-[11px] text-slate-400 mt-1">Tidak ada roll kabel di custody Anda — lewati jika kabel belum ditrack per roll.</p>
                                        @endif
                                    </div>

                                    {{-- Kabel Melintang Jalan (ADHOC-205). Rute dicatat di sini (draft, disinkron
                                         tiap simpan); Deact wajib menyelesaikannya. Lokasi = titik awal → rumah pelanggan. --}}
                                    @php
                                        $hasCrossingOld = session()->hasOldInput();
                                        $crossingOn = $hasCrossingOld ? (bool) old('cable_crossing') : ! empty($cableCrossingRows);
                                        $crossingRows = $hasCrossingOld ? array_values((array) old('cable_crossings', [])) : $cableCrossingRows;
                                        $crossingErrors = collect($errors->messages())->filter(fn ($messages, $key) => str_starts_with($key, 'cable_crossings'))->flatten()->unique();
                                    @endphp
                                    <div class="sm:col-span-2 rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50/40 dark:bg-amber-950/20 p-3 space-y-3"
                                         x-data="{
                                             on: @js($crossingOn),
                                             rows: @js($crossingRows),
                                             addRow() { this.rows.push({ id: '', nama_jalan: '', jenis_jalan: '', meter_terpakai: '', points: [{ label: '', latitude: '', longitude: '' }, { label: '', latitude: '', longitude: '' }] }) },
                                             removeRow(r) { this.rows.splice(r, 1) },
                                             addPoint(r) { this.rows[r].points.push({ label: '', latitude: '', longitude: '' }) },
                                             removePoint(r, p) { this.rows[r].points.splice(p, 1) },
                                             useGps(r, p) {
                                                 if (! navigator.geolocation) { alert('GPS tidak tersedia di perangkat ini. Isi koordinat manual.'); return }
                                                 navigator.geolocation.getCurrentPosition(
                                                     (pos) => { this.rows[r].points[p].latitude = pos.coords.latitude.toFixed(7); this.rows[r].points[p].longitude = pos.coords.longitude.toFixed(7) },
                                                     () => alert('Lokasi GPS tidak terbaca. Isi koordinat manual.'),
                                                 )
                                             },
                                         }">
                                        <label class="flex items-center gap-2 text-xs font-semibold text-amber-900 dark:text-amber-200 cursor-pointer">
                                            <input type="checkbox" name="cable_crossing" value="1" x-model="on" class="h-4 w-4 rounded border-slate-300 accent-amber-600">
                                            Kabel melintang jalan?
                                        </label>
                                        <p class="text-[10px] text-slate-600 dark:text-slate-400">Centang kalau kabel drop dari tiang/ODP ke rumah pelanggan dipasang melintang jalan. Rute akan ditarik saat Deact.</p>

                                        @if($crossingErrors->isNotEmpty())
                                            <div class="rounded-md border border-rose-200 bg-rose-50 dark:bg-rose-950/40 dark:border-rose-900 p-2 text-[11px] text-rose-700 dark:text-rose-300 space-y-0.5">
                                                @foreach($crossingErrors as $message)<p>{{ $message }}</p>@endforeach
                                            </div>
                                        @endif

                                        <div x-show="on" x-cloak class="space-y-3">
                                            <template x-for="(row, r) in rows" :key="r">
                                                <div class="rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3 space-y-2.5">
                                                    <input type="hidden" :name="`cable_crossings[${r}][id]`" :value="row.id">
                                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                                        <div>
                                                            <label class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Nama Jalan <span class="text-rose-500">*</span></label>
                                                            <input type="text" :name="`cable_crossings[${r}][nama_jalan]`" x-model="row.nama_jalan" maxlength="191" placeholder="mis. Jl. Raya Ponorogo" class="w-full text-xs px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 shadow-xs">
                                                        </div>
                                                        <div>
                                                            <label class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Jenis Jalan</label>
                                                            <input type="text" :name="`cable_crossings[${r}][jenis_jalan]`" x-model="row.jenis_jalan" maxlength="100" placeholder="mis. Jalan raya / gang" class="w-full text-xs px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 shadow-xs">
                                                        </div>
                                                        <div>
                                                            <label class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Meter Terpakai</label>
                                                            <input type="number" step="0.01" min="0.01" :name="`cable_crossings[${r}][meter_terpakai]`" x-model="row.meter_terpakai" placeholder="mis. 35" class="w-full text-xs font-mono px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 shadow-xs">
                                                        </div>
                                                    </div>

                                                    <div class="space-y-1.5">
                                                        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-600 dark:text-slate-400">Lokasi Rute (titik awal → rumah pelanggan) <span class="text-rose-500">*</span></p>
                                                        <template x-for="(point, p) in row.points" :key="p">
                                                            <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_1fr_auto_auto] gap-2 items-end">
                                                                <div>
                                                                    <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-0.5" x-text="p === 0 ? 'Titik awal' : (p === row.points.length - 1 ? 'Rumah pelanggan' : 'Titik ' + (p + 1))"></label>
                                                                    <input type="text" :name="`cable_crossings[${r}][points][${p}][label]`" x-model="point.label" maxlength="150" placeholder="Label (opsional)" class="w-full text-xs px-2.5 py-1.5 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-xs">
                                                                </div>
                                                                <div>
                                                                    <input type="text" inputmode="decimal" :name="`cable_crossings[${r}][points][${p}][latitude]`" x-model="point.latitude" placeholder="Latitude" class="w-full text-xs font-mono px-2.5 py-1.5 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-xs">
                                                                </div>
                                                                <div>
                                                                    <input type="text" inputmode="decimal" :name="`cable_crossings[${r}][points][${p}][longitude]`" x-model="point.longitude" placeholder="Longitude" class="w-full text-xs font-mono px-2.5 py-1.5 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-xs">
                                                                </div>
                                                                <button type="button" @click="useGps(r, p)" class="px-2.5 py-1.5 text-[11px] font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/60 rounded-md hover:bg-sky-100">GPS</button>
                                                                <button type="button" @click="removePoint(r, p)" x-show="row.points.length > 2" class="px-2.5 py-1.5 text-[11px] font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/50 rounded-md">Hapus</button>
                                                            </div>
                                                        </template>
                                                        <button type="button" @click="addPoint(r)" class="text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:underline">+ Tambah titik perantara</button>
                                                    </div>

                                                    <div class="space-y-1">
                                                        <label class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Foto Bukti <span x-show="!row.id" class="text-rose-500">*</span></label>
                                                        <input type="file" :name="`cable_crossings[${r}][foto]`" accept="image/*" class="block w-full text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-sky-50 file:text-sky-700 dark:file:bg-sky-950/60 dark:file:text-sky-300 cursor-pointer">
                                                        <p x-show="row.id" class="text-[10px] text-slate-500">Foto sebelumnya tersimpan — unggah lagi kalau ingin menggantinya.</p>
                                                    </div>

                                                    <div class="flex justify-end">
                                                        <button type="button" @click="removeRow(r)" class="text-[11px] font-semibold text-red-600 dark:text-red-400 hover:underline">Hapus rute ini</button>
                                                    </div>
                                                </div>
                                            </template>

                                            <button type="button" @click="addRow()" class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">+ Tambah rute kabel melintang</button>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="mac_address" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">MAC Address</label>
                                        <input type="text" name="mac_address" id="mac_address" value="{{ old('mac_address', $dev->mac_address ?? '') }}" class="w-full text-xs font-mono px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="00:11:22:33:44:55">
                                    </div>

                                    <div>
                                        <label for="pppoe_username" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Username PPPoE</label>
                                        <input type="text" name="pppoe_username" id="pppoe_username" value="{{ old('pppoe_username', $dev->pppoe_username ?? '') }}" class="w-full text-xs font-mono px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="user_ponorogo_01">
                                    </div>

                                    <div>
                                        <label for="pppoe_password" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Password PPPoE</label>
                                        <input type="text" name="pppoe_password" id="pppoe_password" value="{{ old('pppoe_password', $dev->pppoe_password ?? '') }}" class="w-full text-xs font-mono px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="Password PPPOE">
                                    </div>

                                    <div>
                                        <label for="wifi_ssid" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">SSID WiFi <span class="text-rose-500">*</span></label>
                                        <input type="text" name="wifi_ssid" id="wifi_ssid" value="{{ old('wifi_ssid', $dev->wifi_ssid ?? '') }}" class="w-full text-xs font-sans px-3 py-2 border @error('wifi_ssid') border-rose-500 @else border-slate-300 dark:border-slate-700 @enderror rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="SSID Pelanggan">
                                    </div>

                                    <div>
                                        <label for="wifi_password" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Password WiFi <span class="text-rose-500">*</span></label>
                                        <input type="text" name="wifi_password" id="wifi_password" value="{{ old('wifi_password', $dev->wifi_password ?? '') }}" class="w-full text-xs font-sans px-3 py-2 border @error('wifi_password') border-rose-500 @else border-slate-300 dark:border-slate-700 @enderror rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="Password WiFi Pelanggan">
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Distribusi Jaringan (ODP / OLT) -->
                            <div class="p-3.5 sm:p-4 rounded-lg bg-slate-50/50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800 space-y-3">
                                <h5 class="text-xs font-semibold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="workflow" class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" /> Distribusi Jaringan (ODP / OLT)
                                </h5>

                                @if($hasManagedOdp)
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">Pilih ODP & Port Tersedia <span class="text-rose-500">*</span>
                                    <select name="network_node_port_id" required
                                        x-on:change="document.getElementById('odp_number').value = $event.target.selectedOptions[0]?.dataset.code || ''; document.getElementById('odp_port').value = $event.target.selectedOptions[0]?.dataset.port || ''"
                                        class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500">
                                        <option value="">Pilih titik dan port</option>
                                        @foreach($odpPortOptions as $option)
                                        <option value="{{ $option->id }}" data-code="{{ $option->node->code }}" data-port="{{ $option->number }}"
                                            @selected(old('network_node_port_id', $currentOdpPortId) == $option->id)>
                                            {{ $option->node->code }} · Port {{ $option->number }}
                                            {{ $option->activeAssignment->isNotEmpty() ? '(terpasang untuk pelanggan ini)' : '(kosong)' }}
                                        </option>
                                        @endforeach
                                    </select>
                                </label>
                                @endif

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label for="odp_number" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Nomor ODP <span class="text-rose-500">*</span></label>
                                            <input type="text" name="odp_number" id="odp_number" value="{{ old('odp_number', $tech5->odp_number ?? $customer->latestSurvey->nearest_odp ?? '') }}" class="w-full text-xs font-mono px-3 py-2 border @error('odp_number') border-rose-500 @else border-slate-300 dark:border-slate-700 @enderror rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="ODP-01">
                                        </div>
                                        <div>
                                            <label for="odp_port" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Port ODP <span class="text-rose-500">*</span></label>
                                            <input type="text" name="odp_port" id="odp_port" value="{{ old('odp_port', $tech5->odp_port ?? '') }}" class="w-full text-xs font-mono px-3 py-2 border @error('odp_port') border-rose-500 @else border-slate-300 dark:border-slate-700 @enderror rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="Port 4">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <label for="olt_number" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400 truncate">No. OLT</label>
                                            <input type="text" name="olt_number" id="olt_number" value="{{ old('olt_number', $tech5->olt_number ?? '') }}" placeholder="1" class="w-full text-xs font-mono px-2.5 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs">
                                        </div>
                                        <div>
                                            <label for="olt_slot" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400 truncate">Slot OLT</label>
                                            <input type="text" name="olt_slot" id="olt_slot" value="{{ old('olt_slot', $tech5->olt_slot ?? '') }}" placeholder="2" class="w-full text-xs font-mono px-2.5 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs">
                                        </div>
                                        <div>
                                            <label for="olt_port" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400 truncate">Port OLT</label>
                                            <input type="text" name="olt_port" id="olt_port" value="{{ old('olt_port', $tech5->olt_port ?? '') }}" placeholder="3" class="w-full text-xs font-mono px-2.5 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label for="vlan" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">VLAN</label>
                                            <input type="text" name="vlan" id="vlan" value="{{ old('vlan', $tech5->vlan ?? '') }}" class="w-full text-xs font-mono px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="100">
                                        </div>
                                        <div>
                                            <label for="router_number" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">No. Router</label>
                                            <input type="text" name="router_number" id="router_number" value="{{ old('router_number', $tech5->router_number ?? '') }}" class="w-full text-xs font-mono px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="Distribusi">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="initial_attenuation" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Redaman Awal (dBm)</label>
                                        <input type="text" name="initial_attenuation" id="initial_attenuation" value="{{ old('initial_attenuation', $tech5->initial_attenuation ?? '') }}" class="w-full text-xs font-mono px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="-19.5">
                                    </div>
                                </div>

                            </div>

                            {{-- Aktivasi Action Banner — tepat di atas Section Perangkat Tambahan --}}
                            @unless($pemasanganComplete)
                                <div class="p-3.5 sm:p-4 rounded-lg bg-sky-50/50 dark:bg-sky-950/20 border border-sky-200/80 dark:border-sky-800/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <h6 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Aktivasi Tahap Speedtest (Fase 6)</h6>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Simpan perangkat utama dan perangkat tambahan sesuai paket, lalu buka akses Speedtest.</p>
                                    </div>
                                    <button type="button" id="btn-aktivasi" onclick="attemptActivate()" class="w-full sm:w-auto px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-medium cursor-pointer shadow-xs inline-flex items-center justify-center gap-1.5 shrink-0 disabled:opacity-50">
                                        <x-ui.icon name="zap" class="w-3.5 h-3.5" />
                                        <span>Aktivasi Laporan Speedtest</span>
                                    </button>
                                </div>
                            @else
                                <div class="p-3.5 sm:p-4 rounded-lg bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <div class="inline-flex items-center gap-1.5 text-emerald-800 dark:text-emerald-300 text-xs font-medium">
                                            <x-ui.icon name="check" class="w-3.5 h-3.5 text-emerald-600" /> Sudah Diaktivasi — Fase 6 Terbuka
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Gunakan tombol di samping untuk menyimpan koreksi data perangkat.</p>
                                    </div>
                                    <button type="button" id="btn-aktivasi" onclick="attemptActivate()" class="w-full sm:w-auto px-3.5 py-1.5 rounded-md bg-sky-600 hover:bg-sky-700 text-white text-xs font-medium shadow-xs inline-flex items-center justify-center gap-1.5">
                                        <x-ui.icon name="save" class="w-3.5 h-3.5" />
                                        <span>Simpan Perubahan</span>
                                    </button>
                                </div>
                            @endunless

                            {{-- Perangkat Tambahan (ADHOC-190) — di bawah Aktivasi; submit-nya lewat form shell di luar form-pemasangan --}}
                            @include('installations.partials._additional_devices_step')

                            <!-- Section: Material Realita Terpakai -->
                            <div class="p-3.5 sm:p-4 rounded-lg bg-slate-50/50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800 space-y-2.5">
                                <label class="block font-semibold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">
                                    Perangkat Pasif / Material Terpakai Realita (Opsional)
                                </label>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Material yang benar-benar dipakai di lokasi dari custody tim Anda.</p>
                                <x-estimasi-patokan :rows="$estimasiPasif" title="Estimasi Perangkat Pasif" />
                                
                                @if($eligiblePassiveCustody->isEmpty())
                                    <p class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">⚠ Tim ini belum punya custody Perangkat Pasif — ambil barang dari Gudang dulu.</p>
                                @endif
                                @if($droppedFreeformEstimateNames->isNotEmpty())
                                    <p class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">⚠ Estimasi survey "Lainnya" tidak bisa diprefill: {{ $droppedFreeformEstimateNames->implode(', ') }}.</p>
                                @endif
                                
                                <x-material-rows
                                    name="materials"
                                    :categories="$itemCategories"
                                    :rows="$materialRows"
                                    :restrict-to-custody="true"
                                    :custody-options="$eligiblePassiveCustody"
                                    empty-label="Belum ada material dicatat. Klik Tambah Barang."
                                />
                            </div>

                            <!-- Section: Alat Kerja Terpakai -->
                            <div class="p-3.5 sm:p-4 rounded-lg bg-slate-50/50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800">
                                <x-work-tool-checklist
                                    name="work_tools"
                                    :tools="$workTools"
                                    :rows="$workToolRows"
                                    label="Alat Kerja Opsional"
                                    hint="Peralatan kerja tim yang dibawa dan dibawa pulang kembali."
                                />
                            </div>

                            <!-- Section: Upload Foto Bukti Pemasangan -->
                            <div class="p-3.5 sm:p-4 rounded-lg bg-slate-50/50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800 space-y-3">
                                <h5 class="text-xs font-semibold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="camera" class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" /> Foto Bukti Pemasangan &amp; Kontrak
                                </h5>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    @foreach ([
                                        ['field' => 'installation_photo', 'icon' => 'wrench', 'label' => 'FOTO PEMASANGAN', 'hint' => 'Router / ONT Terpasang', 'cta' => 'Pilih Foto Pemasangan', 'alt' => 'Preview Foto Pemasangan'],
                                        ['field' => 'contract_photo', 'icon' => 'file-text', 'label' => 'FOTO KONTRAK', 'hint' => 'Form Fisik Bertanda Tangan', 'cta' => 'Pilih Foto Kontrak', 'alt' => 'Preview Foto Kontrak'],
                                        ['field' => 'signature_photo', 'icon' => 'signature', 'label' => 'FOTO TTD PELANGGAN', 'hint' => 'Bukti Serah Terima', 'cta' => 'Pilih Foto TTD', 'alt' => 'Preview Foto TTD'],
                                    ] as $photo)
                                        @php $existingFotoUrl = foto_publik($installation->{$photo['field']} ?? null); @endphp
                                        <div class="border border-dashed @error($photo['field']) border-rose-400 bg-rose-50/20 @else border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 @enderror rounded-lg p-3 text-center transition-all shadow-xs flex flex-col justify-between relative">
                                            
                                            <!-- Default Empty Placeholder -->
                                            <div id="default-placeholder-{{ $photo['field'] }}" class="py-2.5 space-y-1.5 {{ $existingFotoUrl ? 'hidden' : '' }}">
                                                <div class="w-8 h-8 mx-auto rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center">
                                                    <x-ui.icon name="{{ $photo['icon'] }}" class="w-4 h-4" />
                                                </div>
                                                <div>
                                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $photo['label'] }} <span class="text-rose-500">*</span></span>
                                                    <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">{{ $photo['hint'] }}</span>
                                                </div>
                                            </div>

                                            <!-- Existing Saved Photo -->
                                            @if($existingFotoUrl)
                                                <div id="existing-preview-{{ $photo['field'] }}" class="py-1.5 flex flex-col items-center justify-center">
                                                    <div class="relative inline-block w-full">
                                                        <img class="max-h-24 max-w-full rounded-md object-contain border border-slate-200 dark:border-slate-700 mx-auto cursor-pointer" src="{{ $existingFotoUrl }}" alt="{{ $photo['alt'] }}" onclick="window.open('{{ $existingFotoUrl }}', '_blank')">
                                                    </div>
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400 mt-1">
                                                        <x-ui.icon name="check" class="w-3 h-3" /> Sudah Tersimpan
                                                    </span>
                                                </div>
                                            @endif

                                            <!-- Live Picked Preview -->
                                            <div id="preview-container-{{ $photo['field'] }}" style="display: none;" class="py-1.5 flex flex-col items-center justify-center">
                                                <div class="relative inline-block w-full">
                                                    <img id="preview-img-{{ $photo['field'] }}" class="max-h-24 max-w-full rounded-md object-contain border border-slate-200 dark:border-slate-700 mx-auto" src="" alt="{{ $photo['alt'] }}">
                                                    <button type="button" onclick="clearFile('{{ $photo['field'] }}')" class="absolute -top-2 -right-2 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-5 h-5 flex items-center justify-center shadow-xs transition-transform focus:outline-none cursor-pointer" title="Hapus File">
                                                        <x-ui.icon name="x" class="w-3 h-3" />
                                                    </button>
                                                </div>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400 mt-1">
                                                    <x-ui.icon name="check" class="w-3 h-3" /> Terpilih
                                                </span>
                                            </div>

                                            <div class="mt-2">
                                                <input type="file" name="{{ $photo['field'] }}" id="{{ $photo['field'] }}" accept="image/*" class="hidden" data-has-existing="{{ $existingFotoUrl ? 'true' : 'false' }}" onchange="onFileChange('{{ $photo['field'] }}')">
                                                <label for="{{ $photo['field'] }}" class="w-full text-center bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-xs font-medium py-1.5 px-2.5 rounded-md cursor-pointer transition-colors shadow-xs inline-flex items-center justify-center gap-1">
                                                    <x-ui.icon name="camera" class="w-3 h-3" />
                                                    <span>{{ $existingFotoUrl ? 'Ganti Foto' : $photo['cta'] }}</span>
                                                </label>
                                                <span id="file-label-{{ $photo['field'] }}" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1 font-mono truncate">{{ $existingFotoUrl ? 'Pakai foto tersimpan' : 'Belum ada file' }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Catatan Pemasangan -->
                            <div>
                                <label for="installation_note" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Catatan Pemasangan Teknisi</label>
                                <textarea name="installation_note" id="installation_note" rows="2" class="w-full text-xs font-sans px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="Tuliskan catatan teknis tambahan selama proses pemasangan...">{{ old('installation_note', $installation->installation_note ?? '') }}</textarea>
                            </div>
                        </div>
                    </form>

                    {{-- Form shell Perangkat Tambahan (ADHOC-190) — sengaja di luar form-pemasangan (tidak boleh nested). Field-nya ditarik ke sini lewat atribut form=, lihat partial di Section Distribusi. --}}
                    @if($requiredExtraDevices > 0)
                        <form id="form-additional-devices" action="{{ route('customers.installation.additional-devices.store', $customer->id) }}" method="POST" class="hidden">
                            @csrf
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                        </form>
                    @endif

                    </div>

                    <!-- STEP 6 PANEL: Laporan Uji Koneksi (Speedtest) -->
                    <div id="step-panel-6" class="step-panel space-y-4 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs flex items-center justify-center">6</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-100">Laporan Uji Koneksi (Speedtest)</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Hasil pengujian kecepatan &amp; stabilitas internet pelanggan</p>
                                </div>
                            </div>
                        </div>

                        @unless($pemasanganComplete)
                            <!-- Locked State View -->
                            <div class="py-10 px-4 text-center bg-slate-50/50 dark:bg-slate-950/40 border border-dashed border-slate-300 dark:border-slate-700 rounded-lg space-y-2.5">
                                <div class="w-10 h-10 mx-auto rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center border border-slate-200 dark:border-slate-700">
                                    <x-ui.icon name="lock" class="w-4 h-4" />
                                </div>
                                <div class="max-w-sm mx-auto space-y-1">
                                    <h5 class="text-xs font-semibold text-slate-800 dark:text-slate-200">Fase Speedtest Masih Terkunci</h5>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                                        Lengkapi data di <strong>Step 5 (Perangkat &amp; Instalasi)</strong>, lalu tekan tombol <strong>Aktivasi Laporan Speedtest</strong> untuk membuka tahap ini.
                                    </p>
                                </div>
                                <button type="button" onclick="goToStep(5)" class="mt-1 inline-flex items-center gap-1 px-3.5 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-md text-xs font-medium shadow-xs transition-colors">
                                    <x-ui.icon name="chevron-left" class="w-3.5 h-3.5" />
                                    <span>Kembali ke Step 5</span>
                                </button>
                            </div>
                        @else
                        <!-- Form Speedtest Active -->
                        @error('extra_devices')
                            <p class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold">{{ $message }}</p>
                        @enderror
                        <form id="form-speedtest" action="{{ route('customers.installation.speedtest', $customer->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            <input type="hidden" name="completed_at" id="hidden_completed_at">

                            <!-- Speed Metrics Hero Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40">
                                <div>
                                    <label for="test_download" class="block mb-1 font-semibold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">
                                        Speed Download (Mbps) <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="number" step="0.01" inputmode="decimal" name="test_download" id="test_download" value="{{ old('test_download') }}" class="w-full text-base font-mono font-bold px-3 py-2 pr-14 border @error('test_download') border-rose-500 @else border-slate-300 dark:border-slate-700 @enderror rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="15.5">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-500">Mbps</span>
                                    </div>
                                    @error('test_download')<p class="text-[10px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="test_upload" class="block mb-1 font-semibold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">
                                        Speed Upload (Mbps) <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="number" step="0.01" inputmode="decimal" name="test_upload" id="test_upload" value="{{ old('test_upload') }}" class="w-full text-base font-mono font-bold px-3 py-2 pr-14 border @error('test_upload') border-rose-500 @else border-slate-300 dark:border-slate-700 @enderror rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 shadow-xs" placeholder="15.0">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-500">Mbps</span>
                                    </div>
                                    @error('test_upload')<p class="text-[10px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <!-- Secondary Network Quality Metrics -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                                <div class="p-2.5 rounded-lg bg-slate-50/50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800">
                                    <label for="latency_ms" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-500 dark:text-slate-400">Latency (ms)</label>
                                    <input type="number" step="0.1" inputmode="decimal" name="latency_ms" id="latency_ms" value="{{ old('latency_ms') }}" class="w-full text-xs font-mono px-2.5 py-1.5 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500" placeholder="4.2">
                                </div>

                                <div class="p-2.5 rounded-lg bg-slate-50/50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800">
                                    <label for="jitter_ms" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-500 dark:text-slate-400">Jitter (ms)</label>
                                    <input type="number" step="0.1" inputmode="decimal" name="jitter_ms" id="jitter_ms" value="{{ old('jitter_ms') }}" class="w-full text-xs font-mono px-2.5 py-1.5 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500" placeholder="1.0">
                                </div>

                                <div class="p-2.5 rounded-lg bg-slate-50/50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800">
                                    <label for="packet_loss_percent" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-500 dark:text-slate-400">Packet Loss (%)</label>
                                    <input type="number" step="0.01" inputmode="decimal" name="packet_loss_percent" id="packet_loss_percent" value="{{ old('packet_loss_percent') }}" class="w-full text-xs font-mono px-2.5 py-1.5 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500" placeholder="0">
                                </div>

                                <div class="p-2.5 rounded-lg bg-slate-50/50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800">
                                    <label for="actual_attenuation" class="block mb-1 font-medium uppercase text-[10px] tracking-wide text-slate-500 dark:text-slate-400">Redaman (dBm)</label>
                                    <input type="text" name="actual_attenuation" id="actual_attenuation" value="{{ old('actual_attenuation') }}" class="w-full text-xs font-mono px-2.5 py-1.5 border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-500" placeholder="-19.8">
                                </div>
                            </div>

                            <!-- Foto Speedtest Upload Dropzone -->
                            <div class="p-3.5 sm:p-4 rounded-lg bg-slate-50/50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800 space-y-3">
                                <h5 class="text-xs font-semibold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="gauge" class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" /> Bukti Screenshot Speedtest
                                </h5>

                                <div class="border border-dashed @error('speedtest_photo') border-rose-400 bg-rose-50/20 @else border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 @enderror rounded-lg p-4 text-center transition-all shadow-xs flex flex-col justify-between relative">
                                    <div id="default-placeholder-speedtest_photo" class="py-3 space-y-1.5">
                                        <div class="w-10 h-10 mx-auto rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center">
                                            <x-ui.icon name="gauge" class="w-4 h-4" />
                                        </div>
                                        <div>
                                            <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200">FOTO BUKTI SPEEDTEST <span class="text-rose-500">*</span></span>
                                            <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Screenshot Ookla / Fast.com</span>
                                        </div>
                                    </div>

                                    <div id="preview-container-speedtest_photo" style="display: none;" class="py-2 flex flex-col items-center justify-center">
                                        <div class="relative inline-block w-full max-w-sm">
                                            <img id="preview-img-speedtest_photo" class="max-h-36 max-w-full rounded-md object-contain border border-slate-200 dark:border-slate-700 mx-auto" src="" alt="Preview Foto Speedtest">
                                            <button type="button" onclick="clearFile('speedtest_photo')" class="absolute -top-2 -right-2 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-5 h-5 flex items-center justify-center shadow-xs transition-transform focus:outline-none cursor-pointer" title="Hapus File">
                                                <x-ui.icon name="x" class="w-3 h-3" />
                                            </button>
                                        </div>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400 mt-1.5">
                                            <x-ui.icon name="check" class="w-3 h-3" /> Foto Speedtest Terpilih
                                        </span>
                                    </div>

                                    <div class="mt-2 max-w-xs mx-auto w-full">
                                        <input type="file" name="speedtest_photo" id="speedtest_photo" accept="image/*" class="hidden" onchange="onFileChange('speedtest_photo')">
                                        <label for="speedtest_photo" class="w-full text-center bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-xs font-medium py-1.5 px-3 rounded-md cursor-pointer transition-colors shadow-xs inline-flex items-center justify-center gap-1.5">
                                            <x-ui.icon name="camera" class="w-3 h-3" />
                                            <span>Pilih Foto Speedtest</span>
                                        </label>
                                        <span id="file-label-speedtest_photo" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1 font-mono truncate">Belum ada file dipilih</span>
                                    </div>

                                    @error('speedtest_photo')
                                        <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-1.5">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                        </form>
                        @endunless
                    </div>

                </div>

                <!-- DESKTOP & MOBILE NAVIGATION FOOTER (Fixed on mobile, static on desktop) -->
                <div class="fixed sm:static bottom-0 inset-x-0 z-30 px-4 sm:px-6 py-2.5 sm:py-3 bg-white dark:bg-slate-900 sm:bg-slate-50/90 sm:dark:bg-slate-900/90 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2.5 shrink-0 sm:rounded-b-lg shadow-[0_-4px_16px_rgba(0,0,0,0.06)] dark:shadow-[0_-4px_16px_rgba(0,0,0,0.3)] sm:shadow-none pb-[calc(env(safe-area-inset-bottom)+0.625rem)] sm:pb-3">
                    <!-- Left Actions (Batal on desktop, Sebelumnya on both) -->
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button type="button" id="btn-prev" onclick="prevStep()" style="display: none;" class="w-1/3 sm:w-auto min-h-[44px] sm:min-h-0 py-2.5 sm:py-1.5 px-3 sm:px-3.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors duration-150 text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-xs shrink-0">
                            <x-ui.icon name="chevron-left" class="w-3.5 h-3.5" />
                            <span>Sebelumnya</span>
                        </button>
                        <a href="{{ route('verifications.queue') }}" class="hidden sm:inline-flex px-3.5 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors duration-150 text-xs font-medium cursor-pointer focus:outline-none text-center items-center justify-center">
                            Batal
                        </a>
                    </div>

                    <!-- Right Primary Actions -->
                    <div class="flex items-center gap-2 flex-1 sm:flex-initial justify-end">
                        <button type="button" id="btn-next" onclick="nextStep()" class="flex-1 sm:w-auto min-h-[44px] sm:min-h-0 py-2.5 sm:py-2 px-4 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors duration-150 text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-xs whitespace-nowrap">
                            <span>Lanjut</span><span class="hidden sm:inline">&nbsp;Langkah Berikutnya</span>
                            <x-ui.icon name="chevron-right" class="w-3.5 h-3.5" />
                        </button>

                        <button type="button" onclick="handleSpeedtestSubmit()" id="btn-submit" style="display: none;" class="flex-1 sm:w-auto min-h-[44px] sm:min-h-0 py-2.5 sm:py-2 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md transition-colors duration-150 text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-xs whitespace-nowrap">
                            <x-ui.icon name="check-circle" class="w-3.5 h-3.5" />
                            <span>Simpan</span><span class="hidden sm:inline">&nbsp;&amp; Selesaikan Pemasangan</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@include('partials.wilayah-cascade-script')
<script>
    /* ── Wizard Form Stepper & Live Timer Logic ── */
    let currentActiveStep = 1;
    const totalStepsCount = 6;

    const inputSteps = [5, 6];
    const readOnlySteps = [1, 2, 3, 4];

    const pemasanganComplete = @json($pemasanganComplete);

    // ═══════════════════════════════════════════════════════════
    // TIMER LOGIC
    // ═══════════════════════════════════════════════════════════
    let timerInterval = null;
    let timerSeconds = 0;

    @if($installation && $installation->started_at)
        const timerStartedAt = new Date("{{ $installation->started_at->toIso8601String() }}");

        const hiddenStartedAtInit = document.getElementById('hidden_started_at');
        if (hiddenStartedAtInit) {
            hiddenStartedAtInit.value = "{{ $installation->started_at->format('Y-m-d H:i:s') }}";
        }

        timerSeconds = Math.max(0, Math.floor((new Date() - timerStartedAt) / 1000));

        timerInterval = setInterval(function() {
            timerSeconds++;
            const h = String(Math.floor(timerSeconds / 3600)).padStart(2, '0');
            const m = String(Math.floor((timerSeconds % 3600) / 60)).padStart(2, '0');
            const s = String(timerSeconds % 60).padStart(2, '0');
            const displayEl = document.getElementById('timer-display');
            if (displayEl) {
                displayEl.textContent = `${h}:${m}:${s}`;
            }
        }, 1000);
    @endif

    function stopTimerAndGetCompletedAt() {
        if (timerInterval) clearInterval(timerInterval);
        const completedAt = new Date();
        const hiddenCompletedAt = document.getElementById('hidden_completed_at');
        if (hiddenCompletedAt) {
            hiddenCompletedAt.value = formatDatetimeLocal(completedAt);
        }
        return completedAt;
    }

    function formatDatetimeLocal(date) {
        const pad = n => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    }

    function handlePemasanganSubmit() {
        document.getElementById('form-pemasangan').submit();
    }

    // Ownership modem (rancangan ownership modem sewa/beli, 2026-10-09, task 4)
    // — SN Perangkat Aktif TIDAK wajib kalau radio 'Beli Sendiri' dipilih
    // (modem gak lewat Gudang sama sekali). Radio-nya cuma dirender kalau
    // contract_type pelanggan 'beli' (lihat blade di atas) — buat pelanggan
    // 'sewa' querySelector di bawah selalu null, jadi daftar wajibnya PERSIS
    // sama seperti sebelum task ini (regresi byte-identik).
    function modemAcquisitionIsBeliSendiri() {
        return document.querySelector('input[name="modem_acquisition_choice"]:checked')?.value === 'beli_sendiri';
    }

    function getAktivasiRequiredFields() {
        const base = ['device_type', 'connection_mode', 'selected_inventory_serial_id', 'wifi_ssid', 'wifi_password', 'odp_number', 'odp_port'];
        return modemAcquisitionIsBeliSendiri() ? base.filter(field => field !== 'selected_inventory_serial_id') : base;
    }

    function getPemasanganRequiredFields() {
        const base = ['device_type', 'connection_mode', 'selected_inventory_serial_id', 'wifi_ssid', 'wifi_password', 'odp_number', 'odp_port', 'installation_photo', 'contract_photo', 'signature_photo'];
        return modemAcquisitionIsBeliSendiri() ? base.filter(field => field !== 'selected_inventory_serial_id') : base;
    }

    function attemptActivate() {
        const missing = getMissingFieldsFrom(getAktivasiRequiredFields());
        if (missing.length > 0) {
            if (window.Toast) {
                window.Toast.warning('Data Aktivasi Belum Lengkap', 'Wajib diisi dulu: ' + missing.join(', '));
            }
            return;
        }

        const btn = document.getElementById('btn-aktivasi');
        if (btn) btn.disabled = true;

        const incompleteWarning = buildFase6IncompleteWarning();
        if (incompleteWarning) {
            window.Confirm(
                'Fase 6 Belum Bisa Dibuka',
                incompleteWarning
                    + '\n\nData di atas (device + ODP) tetap akan tersimpan kalau lanjut, tapi Laporan Speedtest (Fase 6) TIDAK akan terbuka sampai ini dilengkapi.'
                    + '\n\nLanjutkan Aktivasi sekarang?',
                'warning',
                () => handlePemasanganSubmit(),
                () => enableAktivasiButton()
            );
            return;
        }

        handlePemasanganSubmit();
    }

    function enableAktivasiButton() {
        const btn = document.getElementById('btn-aktivasi');
        if (btn) btn.disabled = false;
    }

    function buildFase6IncompleteWarning() {
        const missingParts = [];

        const photoFields = [
            ['installation_photo', 'Foto Pemasangan'],
            ['contract_photo', 'Foto Kontrak'],
            ['signature_photo', 'Foto TTD Pelanggan'],
        ];
        const missingPhotos = photoFields.filter(([field]) => {
            const el = document.getElementById(field);
            if (! el) return true;
            return ! ((el.files && el.files.length > 0) || el.dataset.hasExisting === 'true');
        }).map(([, label]) => label);

        if (missingPhotos.length > 0) {
            missingParts.push('Foto belum lengkap: ' + missingPhotos.join(', '));
        }

        // Material Terpakai SENGAJA TIDAK ikut pengecekan ini (koreksi
        // 2026-10-12, permintaan eksplisit user) — opsional, sama seperti
        // laporan lain.

        return missingParts.length > 0 ? missingParts.join('\n') : null;
    }

    function handleSpeedtestSubmit() {
        stopTimerAndGetCompletedAt();
        document.getElementById('form-speedtest').submit();
    }

    const formFields = {
        'pemasangan': {
            get required() { return getPemasanganRequiredFields(); },
            optional: ['brand', 'model', 'mac_address', 'pppoe_username', 'pppoe_password', 'olt_number', 'olt_slot', 'olt_port', 'vlan', 'router_number', 'initial_attenuation', 'installation_note']
        },
        'uji': {
            required: ['test_download', 'test_upload', 'speedtest_photo'],
            optional: ['latency_ms', 'jitter_ms', 'packet_loss_percent', 'actual_attenuation']
        }
    };

    const stepKeys = {
        5: 'pemasangan',
        6: 'uji'
    };

    document.addEventListener("DOMContentLoaded", function() {
        const inputs = document.querySelectorAll('#form-pemasangan input, #form-pemasangan select, #form-pemasangan textarea, #form-speedtest input, #form-speedtest select, #form-speedtest textarea');
        inputs.forEach(input => {
            input.addEventListener('input', runLiveProgressUpdates);
            input.addEventListener('change', runLiveProgressUpdates);
        });

        const formPemasangan = document.getElementById('form-pemasangan');
        if (formPemasangan) {
            formPemasangan.addEventListener('input', enableAktivasiButton);
            formPemasangan.addEventListener('change', enableAktivasiButton);
        }

        updateWizardButtons();
        runLiveProgressUpdates();

        const params = new URLSearchParams(window.location.search);
        if (params.get('activated') === '1') {
            goToStep(pemasanganComplete ? 6 : 5);
        }
    });

    function setElementVisible(el, visible) {
        if (! el) return;
        el.style.display = visible ? '' : 'none';
    }

    function updateWizardButtons() {
        const isFirstStep = currentActiveStep === 1;
        const isStep6 = currentActiveStep === 6;

        setElementVisible(document.getElementById('btn-prev'), ! isFirstStep);
        setElementVisible(document.getElementById('btn-next'), ! isStep6);
        setElementVisible(document.getElementById('btn-submit'), isStep6 && pemasanganComplete);
    }

    function onFileChange(fieldId) {
        const input = document.getElementById(fieldId);
        const label = document.getElementById('file-label-' + fieldId);
        const defaultPlaceholder = document.getElementById('default-placeholder-' + fieldId);
        const previewContainer = document.getElementById('preview-container-' + fieldId);
        const previewImg = document.getElementById('preview-img-' + fieldId);
        const existingPreview = document.getElementById('existing-preview-' + fieldId);
        const hasExisting = input.dataset.hasExisting === 'true';

        if (input.files && input.files.length > 0) {
            const file = input.files[0];
            label.textContent = file.name;
            input.setAttribute('data-populated', 'true');
            if (existingPreview) existingPreview.classList.add('hidden');

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) previewImg.src = e.target.result;
                    if (defaultPlaceholder) defaultPlaceholder.classList.add('hidden');
                    setElementVisible(previewContainer, true);
                };
                reader.readAsDataURL(file);
            }
        } else {
            label.textContent = hasExisting ? 'Pakai foto tersimpan' : 'Belum ada file dipilih';
            input.removeAttribute('data-populated');
            if (defaultPlaceholder) defaultPlaceholder.classList.toggle('hidden', hasExisting);
            if (existingPreview) existingPreview.classList.remove('hidden');
            setElementVisible(previewContainer, false);
            if (previewImg) previewImg.src = '';
        }
        runLiveProgressUpdates();
    }

    function clearFile(fieldId) {
        const input = document.getElementById(fieldId);
        if (input) {
            input.value = '';
            onFileChange(fieldId);
        }
    }

    function getMissingFieldsFrom(fieldNames) {
        const missing = [];

        fieldNames.forEach(field => {
            const el = document.getElementById(field);
            if (! el) {
                missing.push(getLabelName(field));
                return;
            }
            const isFilePopulated = el.type === 'file' && (
                (el.files && el.files.length > 0) || el.dataset.hasExisting === 'true'
            );
            if (! ((el.value && el.value.trim() !== "") || isFilePopulated)) {
                missing.push(getLabelName(field));
            }
        });

        return missing;
    }

    function getMissingRequiredFields(step) {
        return getMissingFieldsFrom(formFields[stepKeys[step]].required);
    }

    function runLiveProgressUpdates() {
        let totalRequiredFieldsCount = 0;
        let filledRequiredFieldsCount = 0;

        inputSteps.forEach(step => {
            if (step === 6 && ! pemasanganComplete) {
                return;
            }

            const config = formFields[stepKeys[step]];
            const requiredMissing = getMissingRequiredFields(step);
            totalRequiredFieldsCount += config.required.length;
            filledRequiredFieldsCount += config.required.length - requiredMissing.length;

            updateStepNavStatus(step, requiredMissing);
        });

        if (! pemasanganComplete) {
            updateLockedStep6Nav();
        }

        const progressPercentage = totalRequiredFieldsCount > 0 ? Math.round((filledRequiredFieldsCount / totalRequiredFieldsCount) * 100) : 0;
        const pctEl = document.getElementById('progress-percentage');
        const filledEl = document.getElementById('filled-fields-count');
        const totalEl = document.getElementById('total-fields-count');
        const fillEl = document.getElementById('progress-bar-fill');

        if (pctEl) pctEl.textContent = progressPercentage + '%';
        if (filledEl) filledEl.textContent = filledRequiredFieldsCount;
        if (totalEl) totalEl.textContent = totalRequiredFieldsCount;
        if (fillEl) fillEl.style.width = progressPercentage + '%';
    }

    function updateStepNavStatus(step, requiredMissing) {
        const navBtn = document.getElementById('step-nav-' + step);
        const iconDiv = document.getElementById('step-nav-icon-' + step);
        const statusSpan = document.getElementById('step-nav-status-' + step);
        const missingSpan = document.getElementById('step-nav-missing-' + step);
        const mBadge = document.getElementById('mobile-step-badge-' + step);

        if (!navBtn || !iconDiv || !statusSpan || !missingSpan) return;

        iconDiv.innerHTML = '';
        missingSpan.textContent = '';

        if (requiredMissing.length > 0) {
            statusSpan.textContent = 'Belum Lengkap';
            statusSpan.className = 'text-[10px] font-medium block text-rose-600 dark:text-rose-400';
            iconDiv.innerHTML = `<span class="w-4 h-4 rounded-full bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 flex items-center justify-center text-rose-600 dark:text-rose-400"><x-ui.icon name="x" class="w-2.5 h-2.5" /></span>`;
            missingSpan.textContent = 'Wajib: ' + requiredMissing.join(', ');

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-2.5 rounded-md border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group focus:outline-none";
            }
            if (mBadge && currentActiveStep !== step) {
                mBadge.className = "w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-[10px] font-mono font-bold flex items-center justify-center shrink-0";
                mBadge.textContent = step;
            }
        } else {
            statusSpan.textContent = 'Lengkap';
            statusSpan.className = 'text-[10px] font-medium block text-emerald-600 dark:text-emerald-400';
            iconDiv.innerHTML = `<span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] shrink-0"><x-ui.icon name="check" class="w-2.5 h-2.5" /></span>`;
            missingSpan.textContent = 'Semua terisi';

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-2.5 rounded-md border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group focus:outline-none";
            }
            if (mBadge && currentActiveStep !== step) {
                mBadge.className = "w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0";
                mBadge.textContent = "✓";
            }
        }

        if (currentActiveStep === step) {
            navBtn.className = "w-full text-left p-2.5 rounded-md border border-sky-500 bg-sky-50/50 dark:bg-sky-950/30 transition-colors group focus:outline-none";
            if (mBadge) {
                mBadge.className = "w-5 h-5 rounded-full bg-sky-600 text-white text-[10px] font-mono font-bold flex items-center justify-center shrink-0 shadow-xs";
                mBadge.textContent = step;
            }
            const activeStatus = document.getElementById('mobile-active-step-status');
            if (activeStatus) {
                if (requiredMissing.length === 0) {
                    activeStatus.textContent = 'Lengkap';
                    activeStatus.className = 'text-[10px] font-mono font-bold text-emerald-600 dark:text-emerald-400 shrink-0';
                } else {
                    activeStatus.textContent = 'Belum Lengkap';
                    activeStatus.className = 'text-[10px] font-mono font-bold text-rose-600 dark:text-rose-400 shrink-0';
                }
            }
        }
    }

    function updateLockedStep6Nav() {
        const navBtn = document.getElementById('step-nav-6');
        const iconDiv = document.getElementById('step-nav-icon-6');
        const statusSpan = document.getElementById('step-nav-status-6');
        const missingSpan = document.getElementById('step-nav-missing-6');
        const mBadge6 = document.getElementById('mobile-step-badge-6');

        if (!navBtn || !iconDiv || !statusSpan || !missingSpan) return;

        statusSpan.textContent = 'Terkunci';
        statusSpan.className = 'text-[10px] font-medium block text-slate-400 dark:text-slate-500';
        iconDiv.innerHTML = `<span class="w-4 h-4 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-400"><x-ui.icon name="lock" class="w-2.5 h-2.5" /></span>`;
        missingSpan.textContent = 'Aktivasi di step 5';

        if (mBadge6 && currentActiveStep !== 6) {
            mBadge6.className = "w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-[10px] font-mono font-bold flex items-center justify-center shrink-0";
            mBadge6.innerHTML = `<svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>`;
        }

        if (currentActiveStep !== 6) {
            navBtn.className = "w-full text-left p-2.5 rounded-md border border-slate-200 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-900/20 opacity-70 transition-colors group focus:outline-none";
        } else {
            navBtn.className = "w-full text-left p-2.5 rounded-md border border-sky-500 bg-sky-50/50 dark:bg-sky-950/30 transition-colors group focus:outline-none";
            const activeStatus = document.getElementById('mobile-active-step-status');
            if (activeStatus) {
                activeStatus.textContent = 'Terkunci';
                activeStatus.className = 'text-[10px] font-mono font-bold text-slate-400 dark:text-slate-500 shrink-0';
            }
        }
    }

    function getLabelName(field) {
        const labels = {
            device_type: 'Jenis Perangkat',
            connection_mode: 'Mode Koneksi',
            selected_inventory_serial_id: 'Serial Number (SN)',
            wifi_ssid: 'SSID WiFi',
            wifi_password: 'Password WiFi',
            odp_number: 'Nomor ODP',
            odp_port: 'Port ODP',
            olt_number: 'Nomor OLT',
            olt_slot: 'Slot OLT',
            olt_port: 'Port OLT',
            installation_photo: 'Foto Pemasangan',
            contract_photo: 'Foto Kontrak',
            signature_photo: 'Foto TTD Pelanggan',
            test_download: 'Speed Download',
            test_upload: 'Speed Upload',
            speedtest_photo: 'Foto Speedtest'
        };
        return labels[field] || field;
    }

    function goToStep(stepNumber) {
        if (stepNumber === 6 && ! pemasanganComplete) {
            const missing = getMissingRequiredFields(5);

            // Material Terpakai SENGAJA TIDAK ikut syarat ini (koreksi
            // 2026-10-12, permintaan eksplisit user) — opsional, sama
            // seperti laporan lain.

            if (missing.length === 0) {
                attemptActivate();
                return;
            }

            if (window.Toast) {
                window.Toast.warning(
                    'Fase 6 Masih Terkunci',
                    'Isi dulu Laporan Pemasangan & Perangkat (step 5): ' + missing.join(', ')
                );
            }
            return;
        }

        if (document.activeElement && typeof document.activeElement.blur === 'function') {
            document.activeElement.blur();
        }

        const prevPanel = document.getElementById('step-panel-' + currentActiveStep);
        if (prevPanel) {
            prevPanel.classList.add('hidden');
            prevPanel.classList.remove('is-active');
        }
        currentActiveStep = stepNumber;
        const nextPanel = document.getElementById('step-panel-' + currentActiveStep);
        if (nextPanel) {
            nextPanel.classList.remove('hidden');
            void nextPanel.offsetWidth;
            nextPanel.classList.add('is-active');
        }

        const stepTitles = {
            1: 'Data Diri Pelanggan',
            2: 'Dokumen Lampiran',
            3: 'Paket & Layanan',
            4: 'Laporan Survey',
            5: 'Laporan Pemasangan & Perangkat',
            6: 'Uji Kecepatan (Speedtest)'
        };

        const activePill = document.getElementById('mobile-step-pill');
        const activeTitle = document.getElementById('mobile-active-step-title');
        const activeStatus = document.getElementById('mobile-active-step-status');

        if (activePill) activePill.textContent = 'Langkah ' + stepNumber + '/6';
        if (activeTitle) activeTitle.textContent = stepTitles[stepNumber] || '';

        if (activeStatus) {
            if (stepNumber <= 4) {
                activeStatus.textContent = 'Lengkap';
                activeStatus.className = 'text-[10px] font-mono font-bold text-emerald-600 dark:text-emerald-400 shrink-0';
            } else if (stepNumber === 5) {
                const missing = getMissingRequiredFields(5);
                if (missing.length === 0) {
                    activeStatus.textContent = 'Lengkap';
                    activeStatus.className = 'text-[10px] font-mono font-bold text-emerald-600 dark:text-emerald-400 shrink-0';
                } else {
                    activeStatus.textContent = 'Belum Lengkap';
                    activeStatus.className = 'text-[10px] font-mono font-bold text-rose-600 dark:text-rose-400 shrink-0';
                }
            } else if (stepNumber === 6) {
                if (! pemasanganComplete) {
                    activeStatus.textContent = 'Terkunci';
                    activeStatus.className = 'text-[10px] font-mono font-bold text-slate-400 dark:text-slate-500 shrink-0';
                } else {
                    const missing = getMissingRequiredFields(6);
                    if (missing.length === 0) {
                        activeStatus.textContent = 'Lengkap';
                        activeStatus.className = 'text-[10px] font-mono font-bold text-emerald-600 dark:text-emerald-400 shrink-0';
                    } else {
                        activeStatus.textContent = 'Belum Lengkap';
                        activeStatus.className = 'text-[10px] font-mono font-bold text-rose-600 dark:text-rose-400 shrink-0';
                    }
                }
            }
        }

        for (let i = 1; i <= totalStepsCount; i++) {
            const mBtn = document.getElementById('mobile-step-btn-' + i);
            const mBadge = document.getElementById('mobile-step-badge-' + i);
            const mLabel = document.getElementById('mobile-step-label-' + i);
            if (! mBtn) continue;

            if (i === currentActiveStep) {
                mBtn.className = "min-h-[44px] py-1.5 px-0.5 rounded-md text-center transition-colors duration-150 flex flex-col items-center justify-center gap-1 focus:outline-none bg-white dark:bg-slate-900 text-sky-700 dark:text-sky-300 shadow-xs border border-slate-200 dark:border-slate-700";
                if (mBadge) {
                    mBadge.className = "w-5 h-5 rounded-full bg-sky-600 text-white text-[10px] font-mono font-bold flex items-center justify-center shrink-0 shadow-xs";
                }
                if (mLabel) {
                    mLabel.className = "text-[11px] font-bold tracking-tight text-sky-700 dark:text-sky-300 leading-none";
                }
            } else if (readOnlySteps.includes(i)) {
                mBtn.className = "min-h-[44px] py-1.5 px-0.5 rounded-md text-center transition-colors duration-150 flex flex-col items-center justify-center gap-1 focus:outline-none text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-transparent";
                if (mBadge) {
                    mBadge.className = "w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0";
                    mBadge.textContent = "✓";
                }
                if (mLabel) {
                    mLabel.className = "text-[11px] font-semibold tracking-tight text-slate-700 dark:text-slate-300 leading-none";
                }
            } else if (i === 6 && ! pemasanganComplete) {
                mBtn.className = "min-h-[44px] py-1.5 px-0.5 rounded-md text-center transition-colors duration-150 flex flex-col items-center justify-center gap-1 focus:outline-none text-slate-500 dark:text-slate-400 border border-transparent";
                if (mBadge) {
                    mBadge.className = "w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-[10px] font-mono font-bold flex items-center justify-center shrink-0";
                    mBadge.innerHTML = `<svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>`;
                }
                if (mLabel) {
                    mLabel.className = "text-[11px] font-semibold tracking-tight text-slate-500 dark:text-slate-400 leading-none";
                }
            } else {
                const missing = getMissingRequiredFields(i);
                mBtn.className = "min-h-[44px] py-1.5 px-0.5 rounded-md text-center transition-colors duration-150 flex flex-col items-center justify-center gap-1 focus:outline-none text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-transparent";
                if (mBadge) {
                    if (missing.length === 0) {
                        mBadge.className = "w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0";
                        mBadge.textContent = "✓";
                    } else {
                        mBadge.className = "w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-[10px] font-mono font-bold flex items-center justify-center shrink-0";
                        mBadge.textContent = i;
                    }
                }
                if (mLabel) {
                    mLabel.className = "text-[11px] font-semibold tracking-tight text-slate-700 dark:text-slate-300 leading-none";
                }
            }
        }

        updateWizardButtons();
        runLiveProgressUpdates();

        // Update desktop sidebar navigation uniformly for all 6 steps
        for (let step = 1; step <= totalStepsCount; step++) {
            const navBtn = document.getElementById('step-nav-' + step);
            if (! navBtn) continue;

            if (currentActiveStep === step) {
                navBtn.className = "w-full text-left p-2.5 rounded-md border border-sky-500 bg-sky-50/50 dark:bg-sky-950/30 transition-colors duration-150 group focus:outline-none";
            } else if (step === 6 && ! pemasanganComplete) {
                navBtn.className = "w-full text-left p-2.5 rounded-md border border-slate-200 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-900/20 opacity-70 transition-colors duration-150 group focus:outline-none";
            } else {
                navBtn.className = "w-full text-left p-2.5 rounded-md border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors duration-150 group focus:outline-none";
            }
        }

        const wizardContainer = document.getElementById('wizard-container');
        if (wizardContainer && window.scrollY > wizardContainer.offsetTop) {
            window.scrollTo({ top: Math.max(0, wizardContainer.offsetTop - 16), behavior: 'smooth' });
        }
    }

    function nextStep() {
        if (currentActiveStep < totalStepsCount) {
            goToStep(currentActiveStep + 1);
        }
    }

    function prevStep() {
        if (currentActiveStep > 1) {
            goToStep(currentActiveStep - 1);
        }
    }
</script>
@endsection
