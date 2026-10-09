@extends('layouts.app')

@section('title', 'Lapor Hasil Pemasangan — Whusnet Operasional')
@section('page_title', 'Lapor Hasil Pemasangan')
@section('breadcrumb_parent', 'Antrean Pemasangan')
@section('breadcrumb_parent_url', route('verifications.queue'))

@section('content')
<div class="max-w-6xl mx-auto space-y-4 sm:space-y-6 pb-20 sm:pb-8">

    <!-- LAYER 1: NAKED PAGE HEADER & MOBILE HERO CUSTOMER CARD -->
    <!-- LAYER 1: NAKED PAGE HEADER & CUSTOMER CONTEXT (Card Budget = 1 Principle) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ $returnTo }}" class="p-2 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors inline-flex items-center gap-1.5 shadow-sm bg-white dark:bg-slate-800 shrink-0">
                <x-ui.icon name="arrow-left" class="w-4 h-4" />
                <span class="hidden sm:inline">Kembali</span>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 truncate">{{ $customer->full_name }}</h1>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-mono font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        {{ $customer->display_id }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
                    {{ $customer->address }} &bull; {{ $customer->village->name ?? '-' }}, {{ $customer->district->name ?? '-' }}
                </p>
            </div>
        </div>
    </div>

    <div id="wizard-container" class="space-y-4 sm:space-y-6">

        <!-- TOP PANEL: Dynamic Completeness Progress Bar -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl p-4 sm:p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800/60">
                        <x-ui.icon name="wrench" class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">Kelengkapan Laporan Pemasangan</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Data teknis, perangkat, foto bukti &amp; speedtest</p>
                    </div>
                </div>
                <div class="text-right">
                    <span id="progress-percentage" class="text-base sm:text-lg font-black text-sky-600 dark:text-sky-400 data-text">0%</span>
                    <span class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 block">
                        <span id="filled-fields-count" class="data-text font-bold text-slate-800 dark:text-slate-200">0</span>/<span id="total-fields-count" class="data-text font-bold">12</span> field wajib
                    </span>
                </div>
            </div>

            <!-- Progress Bar Fill Strip -->
            <div class="w-full bg-slate-100 dark:bg-slate-700/60 rounded-full h-2.5 overflow-hidden border border-slate-200/60 dark:border-slate-700">
                <div id="progress-bar-fill" class="bg-gradient-to-r from-sky-500 via-blue-500 to-emerald-500 h-full w-0 transition-all duration-500 ease-out" style="width: 0%;"></div>
            </div>
        </div>

        <!-- MOBILE SEGMENTED STEPPER (Optimized for Touch on Mobile) -->
        <div class="lg:hidden bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl p-2 shadow-sm">
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-1.5 text-center">
                <!-- Step 1 -->
                <button type="button" onclick="goToStep(1)" id="mobile-step-btn-1" class="py-2 px-1 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 shadow-sm">
                    <span class="w-5 h-5 rounded-full bg-sky-600 text-white text-[10px] font-bold flex items-center justify-center shrink-0">1</span>
                    <span class="text-[10px] font-bold tracking-tight truncate max-w-full">Data Diri</span>
                </button>

                <!-- Step 2 -->
                <button type="button" onclick="goToStep(2)" id="mobile-step-btn-2" class="py-2 px-1 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800">
                    <span class="w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0">✓</span>
                    <span class="text-[10px] font-bold tracking-tight truncate max-w-full">Dokumen</span>
                </button>

                <!-- Step 3 -->
                <button type="button" onclick="goToStep(3)" id="mobile-step-btn-3" class="py-2 px-1 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800">
                    <span class="w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0">✓</span>
                    <span class="text-[10px] font-bold tracking-tight truncate max-w-full">Paket</span>
                </button>

                <!-- Step 4 -->
                <button type="button" onclick="goToStep(4)" id="mobile-step-btn-4" class="py-2 px-1 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800">
                    <span class="w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0">✓</span>
                    <span class="text-[10px] font-bold tracking-tight truncate max-w-full">Survey</span>
                </button>

                <!-- Step 5 -->
                <button type="button" onclick="goToStep(5)" id="mobile-step-btn-5" class="py-2 px-1 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40">
                    <span class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold flex items-center justify-center shrink-0">5</span>
                    <span class="text-[10px] font-bold tracking-tight truncate max-w-full">Perangkat</span>
                </button>

                <!-- Step 6 -->
                <button type="button" onclick="goToStep(6)" id="mobile-step-btn-6" class="py-2 px-1 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40">
                    <span class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold flex items-center justify-center shrink-0">6</span>
                    <span class="text-[10px] font-bold tracking-tight truncate max-w-full">Speedtest</span>
                </button>
            </div>
        </div>

        <!-- MAIN GRID LAYOUT -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- LEFT COLUMN: Desktop 6-Step Checklist (Visible on >= lg screens) -->
            <div class="hidden lg:block lg:col-span-4 space-y-4 sticky top-6">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl p-5 shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Tahapan Laporan</h4>
                        <span class="text-[10px] font-semibold text-slate-400">6 Langkah</span>
                    </div>

                    <div class="space-y-2.5">
                        <!-- Step 1 Navigation Card -->
                        <button type="button" onclick="goToStep(1)" id="step-nav-1" class="w-full text-left p-3 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm">
                            <div class="flex items-center gap-3">
                                <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0 shadow-sm">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">1. Data Diri Pelanggan</span>
                                    <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 block uppercase tracking-wider">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 2 Navigation Card -->
                        <button type="button" onclick="goToStep(2)" id="step-nav-2" class="w-full text-left p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 transition-all group focus:outline-none">
                            <div class="flex items-center gap-3">
                                <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0 shadow-sm">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">2. Dokumen Lampiran</span>
                                    <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 block uppercase tracking-wider">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 3 Navigation Card -->
                        <button type="button" onclick="goToStep(3)" id="step-nav-3" class="w-full text-left p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 transition-all group focus:outline-none">
                            <div class="flex items-center gap-3">
                                <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0 shadow-sm">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">3. Layanan &amp; Paket</span>
                                    <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 block uppercase tracking-wider">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 4 Navigation Card -->
                        <button type="button" onclick="goToStep(4)" id="step-nav-4" class="w-full text-left p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 transition-all group focus:outline-none">
                            <div class="flex items-center gap-3">
                                <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0 shadow-sm">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">4. Laporan Survey Lapangan</span>
                                    <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 block uppercase tracking-wider">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 5 Navigation Card -->
                        <button type="button" onclick="goToStep(5)" id="step-nav-5" class="w-full text-left p-3 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-5">
                                    <span class="w-5 h-5 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400">
                                        <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">5. Perangkat &amp; Instalasi</span>
                                    <span id="step-nav-status-5" class="text-[9px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mt-0.5">Belum Lengkap</span>
                                    <span id="step-nav-missing-5" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-1 leading-relaxed"></span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 6 Navigation Card -->
                        <button type="button" onclick="goToStep(6)" id="step-nav-6" class="w-full text-left p-3 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-6">
                                    <span class="w-5 h-5 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400">
                                        <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">6. Laporan Speedtest</span>
                                    <span id="step-nav-status-6" class="text-[9px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mt-0.5">Belum Lengkap</span>
                                    <span id="step-nav-missing-6" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-1 leading-relaxed"></span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Wizard Steps Form Panels -->
            <div class="lg:col-span-8 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between min-h-[540px]">

                <!-- FORM BODY -->
                <div class="p-4 sm:p-7 flex-1">

                    <!-- STEP 1 PANEL: Data Diri Pelanggan -->
                    <div id="step-panel-1" class="step-panel space-y-5" x-data="{ editingIdentity: false }">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 font-bold text-xs flex items-center justify-center">1</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">Identitas &amp; Alamat Pelanggan</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Data registrasi calon pelanggan</p>
                                </div>
                            </div>
                            <button type="button" x-show="!editingIdentity" @click="editingIdentity = true"
                                    class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 hover:bg-sky-100 dark:hover:bg-sky-900/50 border border-sky-200 dark:border-sky-800 transition-colors inline-flex items-center gap-1">
                                <x-ui.icon name="edit" class="w-3 h-3" />
                                <span>Edit Data</span>
                            </button>
                            <button type="button" x-show="editingIdentity" x-cloak @click="editingIdentity = false"
                                    class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                                Batal
                            </button>
                        </div>

                        <!-- EDIT MODE FORM -->
                        <form x-show="editingIdentity" x-cloak action="{{ route('customers.installation.update-identity', $customer->id) }}" method="POST"
                              class="bg-slate-50/90 dark:bg-slate-900/70 rounded-xl p-4 sm:p-5 border border-slate-200 dark:border-slate-700/80 space-y-4 text-xs">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nama Lengkap *</span>
                                    <input type="text" name="full_name" value="{{ old('full_name', $customer->full_name) }}" required maxlength="150"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nomor Identitas (NIK)</span>
                                    <input type="text" name="identity_number" value="{{ old('identity_number', $customer->identity_number) }}" maxlength="16" inputmode="numeric"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs data-text text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nomor HP Utama *</span>
                                    <input type="text" name="primary_phone" value="{{ old('primary_phone', $customer->primary_phone ?? $customer->phone) }}" required maxlength="20" inputmode="tel"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs data-text text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nomor HP Alternatif</span>
                                    <input type="text" name="alternative_phone" value="{{ old('alternative_phone', $customer->alternative_phone) }}" maxlength="20" inputmode="tel"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs data-text text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Alamat Email</span>
                                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" maxlength="100"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Alamat Instalasi Lengkap *</span>
                                    <textarea name="address" required rows="2"
                                              class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">{{ old('address', $customer->address) }}</textarea>
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Kota/Kabupaten</span>
                                    <select id="identity-city_id" name="city_id" data-selected-district="{{ old('district_id', $customer->district_id) }}" data-selected-village="{{ old('village_id', $customer->village_id) }}" onchange="wilayahLoadDistricts(this.value)"
                                            class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                        <option value="">Pilih Kota/Kabupaten</option>
                                        @foreach($cities as $city)
                                            <option value="{{ $city->id }}" {{ old('city_id', $customer->city_id) == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Kecamatan</span>
                                    <select id="identity-district_id" name="district_id" onchange="wilayahLoadVillages(this.value)"
                                            class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                        <option value="">Pilih kota/kabupaten dulu</option>
                                    </select>
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Desa/Kelurahan</span>
                                    <select id="identity-village_id" name="village_id"
                                            class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                        <option value="">Pilih kecamatan dulu</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Latitude</span>
                                    <input type="text" name="latitude" value="{{ old('latitude', $customer->latitude) }}" inputmode="decimal"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs data-text text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Longitude</span>
                                    <input type="text" name="longitude" value="{{ old('longitude', $customer->longitude) }}" inputmode="decimal"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs data-text text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-700">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">Simpan perubahan data diri</span>
                                <button type="submit"
                                        class="px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-sm inline-flex items-center gap-1.5">
                                    <x-ui.icon name="save" class="w-3.5 h-3.5" />
                                    <span>Simpan Data Diri</span>
                                </button>
                            </div>
                        </form>

                        <!-- READ ONLY CARDS (Mobile-friendly Grid) -->
                        <div x-show="!editingIdentity" class="space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3 text-xs">
                                <div class="p-3.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Nama Lengkap</span>
                                    <span class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ $customer->full_name }}</span>
                                    <span class="block text-[11px] data-text text-slate-500 dark:text-slate-400 mt-0.5">NIK: {{ $customer->identity_number ?? '-' }}</span>
                                </div>

                                <div class="p-3.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Kontak Telepon</span>
                                    <span class="block text-sm data-text font-bold text-slate-800 dark:text-slate-100">{{ $customer->primary_phone ?? $customer->phone ?? '-' }}</span>
                                    <span class="block text-[11px] data-text text-slate-500 dark:text-slate-400 mt-0.5">Alt: {{ $customer->alternative_phone ?? '-' }}</span>
                                </div>

                                <div class="p-3.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Alamat Instalasi</span>
                                    <span class="block text-xs font-bold text-slate-900 dark:text-slate-100 leading-snug">{{ $customer->address }}</span>
                                    <span class="block text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                        Kel. {{ $customer->village->name ?? '-' }},
                                        Kec. {{ $customer->district->name ?? '-' }},
                                        {{ $customer->city->name ?? '-' }}
                                    </span>
                                </div>

                                <div class="p-3.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Koordinat GPS</span>
                                    <span class="text-xs data-text font-mono font-semibold text-slate-800 dark:text-slate-200 block mt-0.5">
                                        {{ $customer->latitude ?? '-' }}, {{ $customer->longitude ?? '-' }}
                                    </span>
                                </div>

                                <div class="p-3.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">POP Wilayah</span>
                                    <span class="block text-xs font-bold text-sky-600 dark:text-sky-400">{{ $customer->pop->name ?? '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 PANEL: Dokumen Lampiran -->
                    <div id="step-panel-2" class="step-panel space-y-5 hidden" x-data="{ editingPhotos: false }">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 font-bold text-xs flex items-center justify-center">2</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">Dokumen Lampiran Survey</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Foto rumah &amp; ODP dari laporan survey awal</p>
                                </div>
                            </div>
                            <button type="button" x-show="!editingPhotos" @click="editingPhotos = true"
                                    class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 hover:bg-sky-100 dark:hover:bg-sky-900/50 border border-sky-200 dark:border-sky-800 transition-colors inline-flex items-center gap-1">
                                <x-ui.icon name="camera" class="w-3 h-3" />
                                <span>Ganti Foto</span>
                            </button>
                            <button type="button" x-show="editingPhotos" x-cloak @click="editingPhotos = false"
                                    class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                                Batal
                            </button>
                        </div>

                        <!-- EDIT PHOTOS FORM -->
                        <form x-show="editingPhotos" x-cloak action="{{ route('customers.installation.update-photos', $customer->id) }}" method="POST" enctype="multipart/form-data"
                              class="bg-slate-50/90 dark:bg-slate-900/70 rounded-xl p-4 sm:p-5 border border-slate-200 dark:border-slate-700/80 space-y-4 text-xs">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Foto Rumah</span>
                                    <input type="file" name="house_photo" accept="image/*"
                                           class="w-full text-xs file:mr-3 file:px-3 file:py-2 file:rounded-xl file:border-0 file:bg-sky-50 file:text-sky-700 dark:file:bg-sky-950/40 dark:file:text-sky-300">
                                    @error('house_photo')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Foto ODP Terdekat</span>
                                    <input type="file" name="survey_photo" accept="image/*"
                                           class="w-full text-xs file:mr-3 file:px-3 file:py-2 file:rounded-xl file:border-0 file:bg-sky-50 file:text-sky-700 dark:file:bg-sky-950/40 dark:file:text-sky-300">
                                    @error('survey_photo')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                                </label>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-700">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">Maks. 2 MB per foto</span>
                                <button type="submit" class="px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-sm inline-flex items-center gap-1.5">
                                    <x-ui.icon name="save" class="w-3.5 h-3.5" />
                                    <span>Simpan Foto</span>
                                </button>
                            </div>
                        </form>

                        <!-- READ ONLY PHOTO CARDS -->
                        <div x-show="!editingPhotos" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Foto Rumah -->
                            <div class="border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 rounded-2xl p-4 flex flex-col justify-between shadow-sm text-center">
                                @php $fotoRumahUrl = foto_publik($customer->latestSurvey?->house_photo); @endphp
                                @if($fotoRumahUrl)
                                    <div>
                                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Foto Rumah</span>
                                        <div class="relative rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                                            <img class="max-h-36 max-w-full rounded-xl object-contain mx-auto hover:scale-105 transition-transform cursor-pointer"
                                                 src="{{ $fotoRumahUrl }}"
                                                 alt="Preview Foto Rumah"
                                                 onclick="window.open('{{ $fotoRumahUrl }}', '_blank')">
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center justify-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-3 bg-emerald-50 dark:bg-emerald-950/40 py-1 px-2.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                                        <x-ui.icon name="check" class="w-3 h-3" /> Terlampir
                                    </span>
                                @else
                                    <div class="flex flex-col justify-center py-6">
                                        <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-lg mb-2">
                                            <x-ui.icon name="house" class="w-5 h-5" />
                                        </div>
                                        <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">Foto Rumah</span>
                                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-1">{{ $customer->latestSurvey?->house_photo ? 'File tidak ditemukan' : 'Tidak ada di survey' }}</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Foto ODP -->
                            <div class="border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 rounded-2xl p-4 flex flex-col justify-between shadow-sm text-center">
                                @php $fotoOdpUrl = foto_publik($customer->latestSurvey?->survey_photo); @endphp
                                @if($fotoOdpUrl)
                                    <div>
                                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Foto ODP Terdekat</span>
                                        <div class="relative rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                                            <img class="max-h-36 max-w-full rounded-xl object-contain mx-auto hover:scale-105 transition-transform cursor-pointer"
                                                 src="{{ $fotoOdpUrl }}"
                                                 alt="Preview Foto ODP"
                                                 onclick="window.open('{{ $fotoOdpUrl }}', '_blank')">
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center justify-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-3 bg-emerald-50 dark:bg-emerald-950/40 py-1 px-2.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                                        <x-ui.icon name="check" class="w-3 h-3" /> Terlampir
                                    </span>
                                @else
                                    <div class="flex flex-col justify-center py-6">
                                        <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-lg mb-2">
                                            <x-ui.icon name="network" class="w-5 h-5" />
                                        </div>
                                        <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">Foto ODP</span>
                                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-1">{{ $customer->latestSurvey?->survey_photo ? 'File tidak ditemukan' : 'Tidak ada di survey' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3 PANEL: Layanan & Paket -->
                    <div id="step-panel-3" class="step-panel space-y-5 hidden" x-data="{ editingPackage: false }">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 font-bold text-xs flex items-center justify-center">3</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">Layanan &amp; Paket Internet</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Paket layanan aktif yang dikonfirmasi</p>
                                </div>
                            </div>
                            <button type="button" x-show="!editingPackage" @click="editingPackage = true"
                                    class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 hover:bg-sky-100 dark:hover:bg-sky-900/50 border border-sky-200 dark:border-sky-800 transition-colors inline-flex items-center gap-1">
                                <x-ui.icon name="edit" class="w-3 h-3" />
                                <span>Edit Paket</span>
                            </button>
                            <button type="button" x-show="editingPackage" x-cloak @click="editingPackage = false"
                                    class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                                Batal
                            </button>
                        </div>

                        <!-- EDIT PACKAGE FORM -->
                        <form x-show="editingPackage" x-cloak action="{{ route('customers.installation.update-package', $customer->id) }}" method="POST"
                              class="bg-slate-50/90 dark:bg-slate-900/70 rounded-xl p-4 sm:p-5 border border-slate-200 dark:border-slate-700/80 space-y-4 text-xs">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            <label class="block">
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Pilih Paket Internet *</span>
                                <select name="internet_package_id" required
                                        class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3.5 py-3 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                    <option value="">Pilih Paket Internet</option>
                                    @foreach($internetPackages as $package)
                                        <option value="{{ $package->id }}" {{ (int) old('internet_package_id', $customer->internet_package_id) === $package->id ? 'selected' : '' }}>
                                            {{ $package->package_code }} — {{ $package->name }} (Rp {{ number_format($package->monthly_price, 0, ',', '.') }}/bln)
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-700">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">Diskon &amp; PPN dipertahankan</span>
                                <button type="submit" class="px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-sm inline-flex items-center gap-1.5">
                                    <x-ui.icon name="save" class="w-3.5 h-3.5" />
                                    <span>Simpan Paket</span>
                                </button>
                            </div>
                        </form>

                        <!-- READ ONLY PACKAGE SUMMARY -->
                        <div x-show="!editingPackage" class="bg-slate-50/80 dark:bg-slate-900/50 rounded-2xl p-4 sm:p-5 border border-slate-200/70 dark:border-slate-700/60 space-y-4 text-xs">
                            <div class="p-3.5 rounded-xl bg-sky-50/80 dark:bg-sky-950/40 border border-sky-100 dark:border-sky-900/60 flex items-center justify-between">
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-sky-800 dark:text-sky-300">Paket Internet</span>
                                    <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $customer->internetPackage->package_code ?? '-' }} — {{ $customer->internetPackage->name ?? 'Belum Dipilih' }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-sky-800 dark:text-sky-300">Biaya Bulanan</span>
                                    <span class="text-base font-black text-sky-700 dark:text-sky-400 data-text">Rp {{ number_format($customer->internetPackage->monthly_price ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-200/60 dark:border-slate-700/60 grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-4">
                                <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-0.5">Jenis Kontrak</span>
                                    <span class="block text-xs font-bold uppercase text-slate-800 dark:text-slate-200">{{ $customer->customerService->contract_type ?? 'Sewa' }}</span>
                                </div>
                                <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-0.5">Masa Kontrak</span>
                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $customer->customerService->contract_period_months ?? 12 }} Bulan</span>
                                </div>
                                <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-0.5">Diskon Promosi</span>
                                    <span class="block text-xs data-text font-semibold text-slate-800 dark:text-slate-200">Rp {{ number_format($customer->discount_amount ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4 PANEL: Laporan Survey (Read-Only) -->
                    <div id="step-panel-4" class="step-panel space-y-5 hidden" x-data="{ editingSurvey: false }">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 font-bold text-xs flex items-center justify-center">4</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">Laporan Survey Lapangan</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Hasil pengamatan awal oleh tim survey</p>
                                </div>
                            </div>
                            <button type="button" x-show="!editingSurvey" @click="editingSurvey = true"
                                    class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 hover:bg-sky-100 dark:hover:bg-sky-900/50 border border-sky-200 dark:border-sky-800 transition-colors inline-flex items-center gap-1">
                                <x-ui.icon name="edit" class="w-3 h-3" />
                                <span>Edit Survey</span>
                            </button>
                            <button type="button" x-show="editingSurvey" x-cloak @click="editingSurvey = false"
                                    class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                                Batal
                            </button>
                        </div>

                        @php $surveyForEdit = $customer->latestSurvey; @endphp
                        <form x-show="editingSurvey" x-cloak action="{{ route('customers.installation.update-survey', $customer->id) }}" method="POST"
                              class="bg-slate-50/90 dark:bg-slate-900/70 rounded-xl p-4 sm:p-5 border border-slate-200 dark:border-slate-700/80 space-y-4 text-xs">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">ODP Terdekat</span>
                                    <input type="text" name="nearest_odp" value="{{ old('nearest_odp', $surveyForEdit?->nearest_odp) }}" maxlength="255"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Estimasi Kabel (Meter)</span>
                                    <input type="number" name="cable_estimation_meter" min="0" value="{{ old('cable_estimation_meter', $surveyForEdit?->cable_estimation_meter) }}"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Tanggal Request Pemasangan</span>
                                    <input type="date" name="requested_installation_date" value="{{ old('requested_installation_date', $surveyForEdit?->requested_installation_date?->format('Y-m-d')) }}"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Tingkat Kesulitan</span>
                                    <select name="difficulty_level"
                                            class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                        <option value="">Pilih tingkat kesulitan</option>
                                        @foreach(['MUDAH' => 'Mudah', 'SEDANG' => 'Sedang', 'SULIT' => 'Sulit'] as $value => $label)
                                            <option value="{{ $value }}" {{ old('difficulty_level', $surveyFields['difficulty_level']) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Catatan Teknis Survey</span>
                                    <textarea name="survey_note" rows="2"
                                              class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">{{ old('survey_note', $surveyFields['survey_note']) }}</textarea>
                                </label>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-700">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">Simpan perubahan survey</span>
                                <button type="submit" class="px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-sm inline-flex items-center gap-1.5">
                                    <x-ui.icon name="save" class="w-3.5 h-3.5" />
                                    <span>Simpan Survey</span>
                                </button>
                            </div>
                        </form>

                        <!-- READ ONLY SURVEY SUMMARY -->
                        <div x-show="!editingSurvey" class="bg-slate-50/80 dark:bg-slate-900/50 rounded-2xl p-4 sm:p-5 border border-slate-200/70 dark:border-slate-700/60 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-0.5">ODP Terdekat</span>
                                <span class="block text-sm data-text font-bold text-slate-800 dark:text-slate-100">{{ $customer->latestSurvey->nearest_odp ?? '-' }}</span>
                            </div>

                            <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-0.5">Estimasi Kabel</span>
                                <span class="block text-sm data-text font-bold text-slate-800 dark:text-slate-100">{{ $customer->latestSurvey->cable_estimation_meter ?? '0' }} Meter</span>
                            </div>

                            @if($customer->latestSurvey?->requested_installation_date)
                                <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-0.5">Tanggal Request Pemasangan</span>
                                    <span class="block text-xs data-text font-bold text-slate-800 dark:text-slate-100">{{ \App\Support\IndonesianDate::date($customer->latestSurvey->requested_installation_date) }}</span>
                                </div>
                            @endif

                            <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 sm:col-span-2">
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-0.5">Tingkat Kesulitan</span>
                                <span class="block text-xs font-bold uppercase text-slate-800 dark:text-slate-200">{{ $surveyFields['difficulty_level'] ?? '-' }}</span>
                                @if($surveyFields['survey_note'])
                                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 whitespace-pre-wrap">{{ $surveyFields['survey_note'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- STEP 5 PANEL: Laporan Pemasangan & Perangkat -->
                    <div id="step-panel-5" class="step-panel space-y-5 hidden">
                    <form id="form-pemasangan" action="{{ route('customers.installation.pemasangan', $customer->id) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ $returnTo }}">
                        <input type="hidden" name="started_at" id="hidden_started_at">

                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 font-bold text-xs flex items-center justify-center">5</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">Laporan Pemasangan &amp; Perangkat</h4>
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
                            <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 rounded-2xl p-4 space-y-2 shadow-sm">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">
                                    <x-ui.icon name="triangle-alert" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                    Instruksi Revisi Pemasangan
                                </div>
                                <p class="text-[11px] font-semibold text-slate-600 dark:text-slate-300">Catatan dari Admin Verifikasi:</p>
                                <p class="bg-white dark:bg-slate-900 border border-amber-100 dark:border-amber-900/50 p-3 rounded-xl data-text text-xs text-slate-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed">{{ $failedInstallation->installation_note }}</p>
                            </div>
                        @endif

                        <div class="space-y-5">

                            <!-- Section: Perangkat Aktif -->
                            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 space-y-4">
                                <h5 class="text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="cpu" class="w-4 h-4" /> Informasi Perangkat Aktif
                                </h5>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 text-xs">
                                    <div>
                                        <label for="device_type" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Jenis Perangkat <span class="text-rose-500">*</span></label>
                                        <select name="device_type" id="device_type" class="w-full text-xs font-sans px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm">
                                            <option value="ont" {{ old('device_type', $dev->device_type ?? 'ont') === 'ont' ? 'selected' : '' }}>ONT</option>
                                            <option value="modem" {{ old('device_type', $dev->device_type ?? '') === 'modem' ? 'selected' : '' }}>Modem</option>
                                            <option value="onu" {{ old('device_type', $dev->device_type ?? '') === 'onu' ? 'selected' : '' }}>ONU</option>
                                            <option value="router" {{ old('device_type', $dev->device_type ?? '') === 'router' ? 'selected' : '' }}>Router</option>
                                            <option value="other" {{ old('device_type', $dev->device_type ?? '') === 'other' ? 'selected' : '' }}>Lainnya</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="connection_mode" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Mode Koneksi <span class="text-rose-500">*</span></label>
                                        <select name="connection_mode" id="connection_mode" class="w-full text-xs font-sans px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm">
                                            <option value="pppoe" {{ old('connection_mode', $dev->connection_mode ?? 'pppoe') === 'pppoe' ? 'selected' : '' }}>PPPoE</option>
                                            <option value="bridge" {{ old('connection_mode', $dev->connection_mode ?? '') === 'bridge' ? 'selected' : '' }}>Bridge</option>
                                            <option value="static" {{ old('connection_mode', $dev->connection_mode ?? '') === 'static' ? 'selected' : '' }}>Static IP</option>
                                            <option value="dhcp" {{ old('connection_mode', $dev->connection_mode ?? '') === 'dhcp' ? 'selected' : '' }}>DHCP</option>
                                            <option value="other" {{ old('connection_mode', $dev->connection_mode ?? '') === 'other' ? 'selected' : '' }}>Lainnya</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="brand" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Merk Perangkat</label>
                                        <input type="text" name="brand" id="brand" value="{{ old('brand', $dev->brand ?? '') }}" class="w-full text-xs font-sans px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="ZTE / Huawei / FiberHome">
                                    </div>

                                    <div>
                                        <label for="model" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Tipe Model</label>
                                        <input type="text" name="model" id="model" value="{{ old('model', $dev->model ?? '') }}" class="w-full text-xs font-sans px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="Contoh: F609 / HG8245H">
                                    </div>

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

                                    <div class="sm:col-span-2">
                                        <label for="selected_inventory_serial_id-search" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Serial Number (SN) — Custody Gudang <span class="text-rose-500">*</span></label>
                                        <div class="mb-2"><x-estimasi-patokan :rows="$estimasiPerangkatAktif" title="Estimasi Perangkat Aktif" /></div>
                                        @if($eligibleSerials->isNotEmpty())
                                            <x-combobox id="selected_inventory_serial_id" name="selected_inventory_serial_id" :options-expr="json_encode($serialOptions)" :value-expr="json_encode((string) $oldSelectedSerialId)" placeholder="Ketik SN atau nama perangkat..." />
                                        @else
                                            <select disabled class="w-full text-xs data-text px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed">
                                                <option>Tidak ada SN di custody Anda</option>
                                            </select>
                                            <p class="text-[11px] text-rose-500 mt-1 leading-relaxed">Anda belum mengambil barang dari Gudang — hubungi Admin/FOP.</p>
                                        @endif
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
                                        <label for="selected_inventory_roll_id-search" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Roll Kabel (Opsional) — Sisa Meter Gudang</label>
                                        <div class="mb-2"><x-estimasi-patokan :rows="$estimasiRoll" title="Estimasi Roll Kabel" /></div>
                                        @if($eligibleRolls->isNotEmpty())
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                                <x-combobox id="selected_inventory_roll_id" name="selected_inventory_roll_id" :options-expr="json_encode($rollOptions)" :value-expr="json_encode((string) $oldSelectedRollId)" placeholder="Ketik kode roll atau nama kabel..." />
                                                <input type="number" step="0.01" min="0.01" name="roll_meters_used" id="roll_meters_used" value="{{ old('roll_meters_used', $installation->roll_meters_used ?? '') }}" placeholder="Meter terpakai (cth: 150)" class="w-full text-xs data-text px-3.5 py-2.5 border @error('roll_meters_used') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm">
                                            </div>
                                        @else
                                            <p class="text-[11px] text-slate-400 mt-1">Tidak ada roll kabel di custody Anda — lewati jika kabel belum ditrack per roll.</p>
                                        @endif
                                    </div>

                                    <div>
                                        <label for="mac_address" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">MAC Address</label>
                                        <input type="text" name="mac_address" id="mac_address" value="{{ old('mac_address', $dev->mac_address ?? '') }}" class="w-full text-xs data-text px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="00:11:22:33:44:55">
                                    </div>

                                    <div>
                                        <label for="pppoe_username" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Username PPPoE</label>
                                        <input type="text" name="pppoe_username" id="pppoe_username" value="{{ old('pppoe_username', $dev->pppoe_username ?? '') }}" class="w-full text-xs data-text px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="user_ponorogo_01">
                                    </div>

                                    <div>
                                        <label for="pppoe_password" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Password PPPoE</label>
                                        <input type="text" name="pppoe_password" id="pppoe_password" value="{{ old('pppoe_password', $dev->pppoe_password ?? '') }}" class="w-full text-xs data-text px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="Password PPPOE">
                                    </div>

                                    <div>
                                        <label for="wifi_ssid" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">SSID WiFi <span class="text-rose-500">*</span></label>
                                        <input type="text" name="wifi_ssid" id="wifi_ssid" value="{{ old('wifi_ssid', $dev->wifi_ssid ?? '') }}" class="w-full text-xs font-sans px-3.5 py-2.5 border @error('wifi_ssid') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="SSID Pelanggan">
                                    </div>

                                    <div>
                                        <label for="wifi_password" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Password WiFi <span class="text-rose-500">*</span></label>
                                        <input type="text" name="wifi_password" id="wifi_password" value="{{ old('wifi_password', $dev->wifi_password ?? '') }}" class="w-full text-xs font-sans px-3.5 py-2.5 border @error('wifi_password') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="Password WiFi Pelanggan">
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Distribusi Jaringan (ODP / OLT) -->
                            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 space-y-4">
                                <h5 class="text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="workflow" class="w-4 h-4" /> Distribusi Jaringan (ODP / OLT)
                                </h5>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 text-xs">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label for="odp_number" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Nomor ODP <span class="text-rose-500">*</span></label>
                                            <input type="text" name="odp_number" id="odp_number" value="{{ old('odp_number', $tech5->odp_number ?? $customer->latestSurvey->nearest_odp ?? '') }}" class="w-full text-xs data-text px-3.5 py-2.5 border @error('odp_number') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="ODP-01">
                                        </div>
                                        <div>
                                            <label for="odp_port" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Port ODP <span class="text-rose-500">*</span></label>
                                            <input type="text" name="odp_port" id="odp_port" value="{{ old('odp_port', $tech5->odp_port ?? '') }}" class="w-full text-xs data-text px-3.5 py-2.5 border @error('odp_port') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="Port 4">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <label for="olt_number" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300 truncate">No. OLT</label>
                                            <input type="text" name="olt_number" id="olt_number" value="{{ old('olt_number', $tech5->olt_number ?? '') }}" placeholder="1" class="w-full text-xs data-text px-2.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm">
                                        </div>
                                        <div>
                                            <label for="olt_slot" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300 truncate">Slot OLT</label>
                                            <input type="text" name="olt_slot" id="olt_slot" value="{{ old('olt_slot', $tech5->olt_slot ?? '') }}" placeholder="2" class="w-full text-xs data-text px-2.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm">
                                        </div>
                                        <div>
                                            <label for="olt_port" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300 truncate">Port OLT</label>
                                            <input type="text" name="olt_port" id="olt_port" value="{{ old('olt_port', $tech5->olt_port ?? '') }}" placeholder="3" class="w-full text-xs data-text px-2.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label for="vlan" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">VLAN</label>
                                            <input type="text" name="vlan" id="vlan" value="{{ old('vlan', $tech5->vlan ?? '') }}" class="w-full text-xs data-text px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="100">
                                        </div>
                                        <div>
                                            <label for="router_number" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">No. Router</label>
                                            <input type="text" name="router_number" id="router_number" value="{{ old('router_number', $tech5->router_number ?? '') }}" class="w-full text-xs data-text px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="Distribusi">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="initial_attenuation" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Redaman Awal (dBm)</label>
                                        <input type="text" name="initial_attenuation" id="initial_attenuation" value="{{ old('initial_attenuation', $tech5->initial_attenuation ?? '') }}" class="w-full text-xs data-text px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="-19.5">
                                    </div>
                                </div>
                            </div>

                            <!-- Aktivasi Action Banner -->
                            @unless($pemasanganComplete)
                                <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-sky-50 to-blue-50 dark:from-sky-950/40 dark:to-blue-950/40 border border-sky-200/80 dark:border-sky-800/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <h6 class="text-xs font-bold text-sky-900 dark:text-sky-200">Aktivasi Tahap Speedtest (Fase 6)</h6>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">Tekan setelah Perangkat &amp; ODP terisi untuk membuka akses Speedtest.</p>
                                    </div>
                                    <button type="button" id="btn-aktivasi" onclick="attemptActivate()" class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white rounded-xl transition-all text-xs font-bold cursor-pointer shadow-md shadow-sky-500/20 active:scale-95 inline-flex items-center justify-center gap-1.5 shrink-0 disabled:opacity-50">
                                        <x-ui.icon name="zap" class="w-3.5 h-3.5" />
                                        <span>Aktivasi Laporan Speedtest</span>
                                    </button>
                                </div>
                            @else
                                <div class="p-4 sm:p-5 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/80 dark:border-emerald-800/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 text-xs font-bold border border-emerald-300 dark:border-emerald-800">
                                            <x-ui.icon name="check" class="w-3.5 h-3.5" /> Sudah Diaktivasi — Fase 6 Terbuka
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Gunakan tombol di samping untuk menyimpan koreksi data perangkat.</p>
                                    </div>
                                    <button type="button" id="btn-aktivasi" onclick="attemptActivate()" class="w-full sm:w-auto px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-sm inline-flex items-center justify-center gap-1.5">
                                        <x-ui.icon name="save" class="w-3.5 h-3.5" />
                                        <span>Simpan Perubahan</span>
                                    </button>
                                </div>
                            @endunless

                            <!-- Section: Material Realita Terpakai -->
                            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 space-y-3">
                                <label class="block font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">
                                    Perangkat Pasif / Material Terpakai Realita <span class="text-rose-500">*</span>
                                </label>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Material yang benar-benar dipakai di lokasi dari custody tim Anda.</p>
                                <x-estimasi-patokan :rows="$estimasiPasif" title="Estimasi Perangkat Pasif" />
                                
                                @if($eligiblePassiveCustody->isEmpty())
                                    <p class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold">⚠ Tim ini belum punya custody Perangkat Pasif — ambil barang dari Gudang dulu.</p>
                                @endif
                                @if($droppedFreeformEstimateNames->isNotEmpty())
                                    <p class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold">⚠ Estimasi survey "Lainnya" tidak bisa diprefill: {{ $droppedFreeformEstimateNames->implode(', ') }}.</p>
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
                            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                <x-work-tool-checklist
                                    name="work_tools"
                                    :tools="$workTools"
                                    :rows="$workToolRows"
                                    label="Alat Kerja Opsional"
                                    hint="Peralatan kerja tim yang dibawa dan dibawa pulang kembali."
                                />
                            </div>

                            <!-- Section: Upload Foto Bukti Pemasangan -->
                            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 space-y-4">
                                <h5 class="text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="camera" class="w-4 h-4" /> Foto Bukti Pemasangan &amp; Kontrak
                                </h5>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                                    @foreach ([
                                        ['field' => 'installation_photo', 'icon' => 'wrench', 'label' => 'FOTO PEMASANGAN', 'hint' => 'Router / ONT Terpasang', 'cta' => 'Pilih Foto Pemasangan', 'alt' => 'Preview Foto Pemasangan'],
                                        ['field' => 'contract_photo', 'icon' => 'file-text', 'label' => 'FOTO KONTRAK', 'hint' => 'Form Fisik Bertanda Tangan', 'cta' => 'Pilih Foto Kontrak', 'alt' => 'Preview Foto Kontrak'],
                                        ['field' => 'signature_photo', 'icon' => 'signature', 'label' => 'FOTO TTD PELANGGAN', 'hint' => 'Bukti Serah Terima', 'cta' => 'Pilih Foto TTD', 'alt' => 'Preview Foto TTD'],
                                    ] as $photo)
                                        @php $existingFotoUrl = foto_publik($installation->{$photo['field']} ?? null); @endphp
                                        <div class="border-2 border-dashed @error($photo['field']) border-rose-400 bg-rose-50/20 @else border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 @enderror hover:border-sky-500 dark:hover:border-sky-400 rounded-2xl p-3.5 text-center transition-all shadow-sm flex flex-col justify-between relative">
                                            
                                            <!-- Default Empty Placeholder -->
                                            <div id="default-placeholder-{{ $photo['field'] }}" class="py-3 space-y-2 {{ $existingFotoUrl ? 'hidden' : '' }}">
                                                <div class="w-10 h-10 mx-auto rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-200 dark:border-sky-800">
                                                    <x-ui.icon name="{{ $photo['icon'] }}" class="w-4 h-4" />
                                                </div>
                                                <div>
                                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">{{ $photo['label'] }} <span class="text-rose-500">*</span></span>
                                                    <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">{{ $photo['hint'] }}</span>
                                                </div>
                                            </div>

                                            <!-- Existing Saved Photo -->
                                            @if($existingFotoUrl)
                                                <div id="existing-preview-{{ $photo['field'] }}" class="py-2 flex flex-col items-center justify-center">
                                                    <div class="relative inline-block w-full">
                                                        <img class="max-h-28 max-w-full rounded-xl object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto cursor-pointer" src="{{ $existingFotoUrl }}" alt="{{ $photo['alt'] }}" onclick="window.open('{{ $existingFotoUrl }}', '_blank')">
                                                    </div>
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-1.5 px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
                                                        <x-ui.icon name="check" class="w-3 h-3" /> Sudah Tersimpan
                                                    </span>
                                                </div>
                                            @endif

                                            <!-- Live Picked Preview -->
                                            <div id="preview-container-{{ $photo['field'] }}" style="display: none;" class="py-2 flex flex-col items-center justify-center">
                                                <div class="relative inline-block w-full">
                                                    <img id="preview-img-{{ $photo['field'] }}" class="max-h-28 max-w-full rounded-xl object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="" alt="{{ $photo['alt'] }}">
                                                    <button type="button" onclick="clearFile('{{ $photo['field'] }}')" class="absolute -top-2 -right-2 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md active:scale-95 transition-transform focus:outline-none cursor-pointer" title="Hapus File">
                                                        <x-ui.icon name="x" class="w-3 h-3" />
                                                    </button>
                                                </div>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-1.5 px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
                                                    <x-ui.icon name="check" class="w-3 h-3" /> Terpilih
                                                </span>
                                            </div>

                                            <div class="mt-2">
                                                <input type="file" name="{{ $photo['field'] }}" id="{{ $photo['field'] }}" accept="image/*" class="hidden" data-has-existing="{{ $existingFotoUrl ? 'true' : 'false' }}" onchange="onFileChange('{{ $photo['field'] }}')">
                                                <label for="{{ $photo['field'] }}" class="w-full text-center bg-sky-600 hover:bg-sky-700 active:scale-95 text-white text-xs font-semibold py-2 px-3 rounded-xl cursor-pointer transition-all shadow-sm focus:outline-none inline-flex items-center justify-center gap-1">
                                                    <x-ui.icon name="camera" class="w-3.5 h-3.5" />
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
                                <label for="installation_note" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Catatan Pemasangan Teknisi</label>
                                <textarea name="installation_note" id="installation_note" rows="2" class="w-full text-xs font-sans px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="Tuliskan catatan teknis tambahan selama proses pemasangan...">{{ old('installation_note', $installation->installation_note ?? '') }}</textarea>
                            </div>
                        </div>
                    </form>
                    </div>

                    <!-- STEP 6 PANEL: Laporan Uji Koneksi (Speedtest) -->
                    <div id="step-panel-6" class="step-panel space-y-5 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 font-bold text-xs flex items-center justify-center">6</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">Laporan Uji Koneksi (Speedtest)</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Hasil pengujian kecepatan &amp; stabilitas internet pelanggan</p>
                                </div>
                            </div>
                        </div>

                        @unless($pemasanganComplete)
                            <!-- Locked State View -->
                            <div class="py-12 px-4 text-center bg-slate-50/70 dark:bg-slate-900/40 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-2xl space-y-3">
                                <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-200 dark:border-amber-800/60 shadow-sm">
                                    <x-ui.icon name="lock" class="w-6 h-6" />
                                </div>
                                <div class="max-w-sm mx-auto space-y-1">
                                    <h5 class="text-sm font-bold text-slate-800 dark:text-slate-200">Fase Speedtest Masih Terkunci</h5>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                                        Lengkapi data di <strong>Step 5 (Perangkat &amp; Instalasi)</strong>, lalu tekan tombol <strong>Aktivasi Laporan Speedtest</strong> untuk membuka tahap ini.
                                    </p>
                                </div>
                                <button type="button" onclick="goToStep(5)" class="mt-2 inline-flex items-center gap-1.5 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all active:scale-95">
                                    <x-ui.icon name="chevron-left" class="w-3.5 h-3.5" />
                                    <span>Kembali ke Step 5</span>
                                </button>
                            </div>
                        @else
                        <!-- Form Speedtest Active -->
                        <form id="form-speedtest" action="{{ route('customers.installation.speedtest', $customer->id) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                            @csrf
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            <input type="hidden" name="completed_at" id="hidden_completed_at">

                            <!-- Speed Metrics Hero Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 bg-gradient-to-br from-sky-50 via-blue-50 to-indigo-50 dark:from-sky-950/30 dark:via-blue-950/30 dark:to-indigo-950/30 p-4 sm:p-5 rounded-2xl border border-sky-200/80 dark:border-sky-800/60">
                                <div>
                                    <label for="test_download" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-sky-900 dark:text-sky-200">
                                        Speed Download (Mbps) <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="number" step="0.01" inputmode="decimal" name="test_download" id="test_download" value="{{ old('test_download') }}" class="w-full text-base data-text font-black px-4 py-3 pr-14 border @error('test_download') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="15.5">
                                        <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-sky-600 dark:text-sky-400">Mbps</span>
                                    </div>
                                    @error('test_download')<p class="text-[10px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="test_upload" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-sky-900 dark:text-sky-200">
                                        Speed Upload (Mbps) <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="number" step="0.01" inputmode="decimal" name="test_upload" id="test_upload" value="{{ old('test_upload') }}" class="w-full text-base data-text font-black px-4 py-3 pr-14 border @error('test_upload') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 shadow-sm" placeholder="15.0">
                                        <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-sky-600 dark:text-sky-400">Mbps</span>
                                    </div>
                                    @error('test_upload')<p class="text-[10px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <!-- Secondary Network Quality Metrics -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 text-xs">
                                <div class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <label for="latency_ms" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-500 dark:text-slate-400">Latency (ms)</label>
                                    <input type="number" step="0.1" inputmode="decimal" name="latency_ms" id="latency_ms" value="{{ old('latency_ms') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="4.2">
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <label for="jitter_ms" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-500 dark:text-slate-400">Jitter (ms)</label>
                                    <input type="number" step="0.1" inputmode="decimal" name="jitter_ms" id="jitter_ms" value="{{ old('jitter_ms') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="1.0">
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <label for="packet_loss_percent" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-500 dark:text-slate-400">Packet Loss (%)</label>
                                    <input type="number" step="0.01" inputmode="decimal" name="packet_loss_percent" id="packet_loss_percent" value="{{ old('packet_loss_percent') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="0">
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <label for="actual_attenuation" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-500 dark:text-slate-400">Redaman (dBm)</label>
                                    <input type="text" name="actual_attenuation" id="actual_attenuation" value="{{ old('actual_attenuation') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="-19.8">
                                </div>
                            </div>

                            <!-- Foto Speedtest Upload Dropzone -->
                            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 space-y-3">
                                <h5 class="text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="gauge" class="w-4 h-4" /> Bukti Screenshot Speedtest
                                </h5>

                                <div class="border-2 border-dashed @error('speedtest_photo') border-rose-400 bg-rose-50/20 @else border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 @enderror hover:border-sky-500 dark:hover:border-sky-400 rounded-2xl p-5 text-center transition-all shadow-sm flex flex-col justify-between relative">
                                    <div id="default-placeholder-speedtest_photo" class="py-4 space-y-2">
                                        <div class="w-12 h-12 mx-auto rounded-2xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg border border-sky-200 dark:border-sky-800">
                                            <x-ui.icon name="gauge" class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">FOTO BUKTI SPEEDTEST <span class="text-rose-500">*</span></span>
                                            <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Screenshot Aplikasi Speedtest (Ookla / Fast.com)</span>
                                        </div>
                                    </div>

                                    <div id="preview-container-speedtest_photo" style="display: none;" class="py-2 flex flex-col items-center justify-center">
                                        <div class="relative inline-block w-full max-w-sm">
                                            <img id="preview-img-speedtest_photo" class="max-h-44 max-w-full rounded-xl object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="" alt="Preview Foto Speedtest">
                                            <button type="button" onclick="clearFile('speedtest_photo')" class="absolute -top-2.5 -right-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-7 h-7 flex items-center justify-center shadow-lg active:scale-95 transition-transform focus:outline-none cursor-pointer" title="Hapus File">
                                                <x-ui.icon name="x" class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-2 px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
                                            <x-ui.icon name="check" class="w-3 h-3" /> Foto Speedtest Terpilih
                                        </span>
                                    </div>

                                    <div class="mt-3 max-w-xs mx-auto w-full">
                                        <input type="file" name="speedtest_photo" id="speedtest_photo" accept="image/*" class="hidden" onchange="onFileChange('speedtest_photo')">
                                        <label for="speedtest_photo" class="w-full text-center bg-sky-600 hover:bg-sky-700 active:scale-95 text-white text-xs font-semibold py-2.5 px-3 rounded-xl cursor-pointer transition-all shadow-sm focus:outline-none inline-flex items-center justify-center gap-1.5">
                                            <x-ui.icon name="camera" class="w-3.5 h-3.5" />
                                            <span>Pilih Foto Speedtest</span>
                                        </label>
                                        <span id="file-label-speedtest_photo" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1.5 font-mono truncate">Belum ada file dipilih</span>
                                    </div>

                                    @error('speedtest_photo')
                                        <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-2">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                        </form>
                        @endunless
                    </div>

                </div>

                <!-- DESKTOP & MOBILE NAVIGATION FOOTER (Sticky-safe on mobile) -->
                <div class="px-4 sm:px-7 py-3.5 sm:py-4 bg-slate-50/95 dark:bg-slate-900/80 backdrop-blur border-t border-slate-200 dark:border-slate-700/60 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3 shrink-0 rounded-b-2xl">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button type="button" id="btn-prev" onclick="prevStep()" style="display: none;" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm active:scale-95">
                            <x-ui.icon name="chevron-left" class="w-3.5 h-3.5" />
                            <span>Sebelumnya</span>
                        </button>
                        <a href="{{ route('verifications.queue') }}" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all text-xs font-semibold cursor-pointer focus:outline-none text-center inline-flex items-center justify-center active:scale-95">
                            Batal
                        </a>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button type="button" id="btn-next" onclick="nextStep()" class="w-full sm:w-auto px-6 py-2.5 sm:py-2 bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white rounded-xl transition-all text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-md shadow-sky-500/20 active:scale-95">
                            <span>Lanjut Langkah Berikutnya</span>
                            <x-ui.icon name="chevron-right" class="w-3.5 h-3.5" />
                        </button>

                        <button type="button" onclick="handleSpeedtestSubmit()" id="btn-submit" style="display: none;" class="w-full sm:w-auto px-6 py-2.5 sm:py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl transition-all text-xs font-bold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-md shadow-emerald-500/20 active:scale-95">
                            <x-ui.icon name="check-circle" class="w-4 h-4" />
                            <span>Simpan &amp; Selesaikan Pemasangan</span>
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

    const aktivasiRequiredFields = [
        'device_type', 'connection_mode', 'selected_inventory_serial_id', 'wifi_ssid', 'wifi_password',
        'odp_number', 'odp_port',
    ];

    function attemptActivate() {
        const missing = getMissingFieldsFrom(aktivasiRequiredFields);
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

    function hasMaterialTerpakaiRow() {
        const qtyInputs = document.querySelectorAll('input[name^="materials["][name$="[qty]"]');
        return Array.from(qtyInputs).some(input => parseFloat(input.value) > 0);
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

        if (! hasMaterialTerpakaiRow()) {
            missingParts.push('Material Terpakai: belum ada baris dengan jumlah > 0');
        }

        return missingParts.length > 0 ? missingParts.join('\n') : null;
    }

    function handleSpeedtestSubmit() {
        stopTimerAndGetCompletedAt();
        document.getElementById('form-speedtest').submit();
    }

    const formFields = {
        'pemasangan': {
            required: ['device_type', 'connection_mode', 'selected_inventory_serial_id', 'wifi_ssid', 'wifi_password', 'odp_number', 'odp_port', 'installation_photo', 'contract_photo', 'signature_photo'],
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

        if (!navBtn || !iconDiv || !statusSpan || !missingSpan) return;

        iconDiv.innerHTML = '';
        missingSpan.textContent = '';

        if (requiredMissing.length > 0) {
            statusSpan.textContent = 'Belum Lengkap';
            statusSpan.className = 'text-[9px] font-bold block uppercase tracking-wider text-rose-600 dark:text-rose-400 mt-0.5';
            iconDiv.innerHTML = `<span class="w-5 h-5 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400"><x-ui.icon name="x" class="w-2.5 h-2.5" /></span>`;
            missingSpan.textContent = 'Wajib diisi: ' + requiredMissing.join(', ');

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none";
            }
        } else {
            statusSpan.textContent = 'Lengkap';
            statusSpan.className = 'text-[9px] font-bold block uppercase tracking-wider text-emerald-600 dark:text-emerald-400 mt-0.5';
            iconDiv.innerHTML = `<span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0 shadow-sm"><x-ui.icon name="check" class="w-2.5 h-2.5" /></span>`;
            missingSpan.textContent = 'Semua terisi';

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-all group focus:outline-none";
            }
        }

        if (currentActiveStep === step) {
            navBtn.className = "w-full text-left p-3 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm";
        }
    }

    function updateLockedStep6Nav() {
        const navBtn = document.getElementById('step-nav-6');
        const iconDiv = document.getElementById('step-nav-icon-6');
        const statusSpan = document.getElementById('step-nav-status-6');
        const missingSpan = document.getElementById('step-nav-missing-6');

        if (!navBtn || !iconDiv || !statusSpan || !missingSpan) return;

        statusSpan.textContent = 'Terkunci';
        statusSpan.className = 'text-[9px] font-bold block uppercase tracking-wider text-slate-400 dark:text-slate-500 mt-0.5';
        iconDiv.innerHTML = `<span class="w-5 h-5 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-400"><x-ui.icon name="lock" class="w-2.5 h-2.5" /></span>`;
        missingSpan.textContent = 'Aktivasi dulu di step 5';

        if (currentActiveStep !== 6) {
            navBtn.className = "w-full text-left p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/30 opacity-70 transition-all group focus:outline-none";
        } else {
            navBtn.className = "w-full text-left p-3 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm";
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

            if (missing.length === 0 && ! hasMaterialTerpakaiRow()) {
                missing.push('Material Terpakai (minimal 1 baris, jumlah > 0)');
            }

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

        document.getElementById('step-panel-' + currentActiveStep).classList.add('hidden');
        currentActiveStep = stepNumber;
        document.getElementById('step-panel-' + currentActiveStep).classList.remove('hidden');

        for (let i = 1; i <= totalStepsCount; i++) {
            const mBtn = document.getElementById('mobile-step-btn-' + i);
            if (! mBtn) continue;

            if (i === currentActiveStep) {
                mBtn.className = "py-2 px-1 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 shadow-sm";
            } else if (readOnlySteps.includes(i)) {
                mBtn.className = "py-2 px-1 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800";
            } else {
                mBtn.className = "py-2 px-1 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40";
            }
        }

        updateWizardButtons();
        runLiveProgressUpdates();

        readOnlySteps.forEach(step => {
            const navBtn = document.getElementById('step-nav-' + step);
            if (! navBtn) return;

            if (currentActiveStep === step) {
                navBtn.className = "w-full text-left p-3 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm";
            } else {
                navBtn.className = "w-full text-left p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-all group focus:outline-none";
            }
        });

        window.scrollTo({ top: 0, behavior: 'smooth' });
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
