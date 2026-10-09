@extends('layouts.app')

@section('title', 'Lapor Data Survey Pelanggan — Whusnet Operasional')
@section('page_title', 'Lapor Data Survey')
@section('breadcrumb_parent', 'Antrean Survey')
@section('breadcrumb_parent_url', route('surveys.queue'))

@section('content')
<div class="max-w-6xl mx-auto space-y-4 sm:space-y-6 pb-20 sm:pb-8">

    <!-- LAYER 1: NAKED PAGE HEADER & MOBILE QUICK ACTIONS -->
    <!-- LAYER 1: NAKED PAGE HEADER & CUSTOMER CONTEXT -->
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

    <!-- MAIN FORM CONTAINER -->
    <form action="{{ route('customers.survey.store', $customer->id) }}" method="POST" enctype="multipart/form-data" id="wizard-form" class="space-y-4 sm:space-y-6">
        @csrf
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
        <input type="hidden" name="survey_status" id="survey_status_input" value="completed">

        <!-- PROGRESS & STEPPER BAR (Sleek Mobile & Desktop Hybrid) -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl p-4 sm:p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800/60">
                        <x-ui.icon name="clipboard-check" class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">Kelengkapan Laporan Survey</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Validasi kelengkapan teknis lapangan</p>
                    </div>
                </div>
                <div class="text-right">
                    <span id="progress-percentage" class="text-base sm:text-lg font-black text-sky-600 dark:text-sky-400 data-text">0%</span>
                    <span class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 block">
                        <span id="filled-fields-count" class="data-text font-bold text-slate-800 dark:text-slate-200">0</span>/<span id="total-fields-count" class="data-text font-bold">5</span> wajib
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
            <div class="grid grid-cols-4 gap-1 sm:gap-2">
                <!-- Step 1 Button -->
                <button type="button" onclick="goToStep(1)" id="mobile-step-btn-1" class="py-2.5 px-1 sm:px-2 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 shadow-sm">
                    <span id="mobile-step-badge-1" class="w-5 h-5 rounded-full bg-sky-600 text-white text-[10px] font-bold flex items-center justify-center shrink-0">1</span>
                    <span class="text-[10px] sm:text-[11px] font-bold tracking-tight truncate max-w-full">Data Diri</span>
                </button>

                <!-- Step 2 Button -->
                <button type="button" onclick="goToStep(2)" id="mobile-step-btn-2" class="py-2.5 px-1 sm:px-2 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40">
                    <span id="mobile-step-badge-2" class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold flex items-center justify-center shrink-0">2</span>
                    <span class="text-[10px] sm:text-[11px] font-bold tracking-tight truncate max-w-full">Dokumen</span>
                </button>

                <!-- Step 3 Button -->
                <button type="button" onclick="goToStep(3)" id="mobile-step-btn-3" class="py-2.5 px-1 sm:px-2 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800">
                    <span id="mobile-step-badge-3" class="w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0">✓</span>
                    <span class="text-[10px] sm:text-[11px] font-bold tracking-tight truncate max-w-full">Layanan</span>
                </button>

                <!-- Step 4 Button -->
                <button type="button" onclick="goToStep(4)" id="mobile-step-btn-4" class="py-2.5 px-1 sm:px-2 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40">
                    <span id="mobile-step-badge-4" class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold flex items-center justify-center shrink-0">4</span>
                    <span class="text-[10px] sm:text-[11px] font-bold tracking-tight truncate max-w-full">Laporan</span>
                </button>
            </div>
        </div>

        <!-- MAIN CONTENT GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- LEFT COLUMN: Desktop Stepper Checklist (Visible on >= lg screens) -->
            <div class="hidden lg:block lg:col-span-4 space-y-4 sticky top-6">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Tahapan Laporan</h4>
                        <span class="text-[10px] font-semibold text-slate-400">4 Langkah</span>
                    </div>

                    <div class="space-y-3">
                        <!-- Step 1 Navigation Card -->
                        <button type="button" onclick="goToStep(1)" id="step-nav-1" class="w-full text-left p-3.5 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-1">
                                    <span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-sm">
                                        <x-ui.icon name="check" class="w-3 h-3" />
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-900 dark:text-slate-100">1. Data Diri Pelanggan</span>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block mt-0.5">Lengkap</span>
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 block mt-1">Data identitas &amp; lokasi</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 2 Navigation Card -->
                        <button type="button" onclick="goToStep(2)" id="step-nav-2" class="w-full text-left p-3.5 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-2">
                                    <span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400">
                                        <x-ui.icon name="x" class="w-3 h-3" />
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">2. Dokumen Lampiran</span>
                                    <span id="step-nav-status-2" class="text-[9px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mt-0.5">Belum Lengkap</span>
                                    <span id="step-nav-missing-2" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-1 leading-relaxed">Wajib diisi: Foto Rumah, Foto ODP Terdekat</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 3 Navigation Card -->
                        <button type="button" onclick="goToStep(3)" id="step-nav-3" class="w-full text-left p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-3">
                                    <span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-sm">
                                        <x-ui.icon name="check" class="w-3 h-3" />
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">3. Layanan &amp; Paket</span>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block mt-0.5">Lengkap</span>
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 block mt-1">Paket layanan internet</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 4 Navigation Card -->
                        <button type="button" onclick="goToStep(4)" id="step-nav-4" class="w-full text-left p-3.5 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-4">
                                    <span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400">
                                        <x-ui.icon name="x" class="w-3 h-3" />
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">4. Laporan Survey</span>
                                    <span id="step-nav-status-4" class="text-[9px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mt-0.5">Belum Lengkap</span>
                                    <span id="step-nav-missing-4" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-1 leading-relaxed">Wajib diisi: ODP Terdekat, Estimasi Kabel, Tingkat Kesulitan</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Wizard Steps Form Panels -->
            <div class="lg:col-span-8 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between min-h-[520px]">

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
                        <div x-show="editingIdentity" x-cloak class="bg-slate-50/90 dark:bg-slate-900/70 rounded-xl p-4 sm:p-5 border border-slate-200 dark:border-slate-700/80 space-y-4 text-xs">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nama Lengkap *</span>
                                    <input type="text" name="full_name" form="identity-form" value="{{ old('full_name', $customer->full_name) }}" required maxlength="150"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nomor Identitas (NIK)</span>
                                    <input type="text" name="identity_number" form="identity-form" value="{{ old('identity_number', $customer->identity_number) }}" maxlength="16" inputmode="numeric"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs data-text text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nomor HP Utama *</span>
                                    <input type="text" name="primary_phone" form="identity-form" value="{{ old('primary_phone', $customer->primary_phone ?? $customer->phone) }}" required maxlength="20" inputmode="tel"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs data-text text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Nomor HP Alternatif</span>
                                    <input type="text" name="alternative_phone" form="identity-form" value="{{ old('alternative_phone', $customer->alternative_phone) }}" maxlength="20" inputmode="tel"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs data-text text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Alamat Email</span>
                                    <input type="email" name="email" form="identity-form" value="{{ old('email', $customer->email) }}" maxlength="100"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Alamat Instalasi Lengkap *</span>
                                    <textarea name="address" form="identity-form" required rows="2"
                                              class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">{{ old('address', $customer->address) }}</textarea>
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Kota/Kabupaten</span>
                                    <select id="identity-city_id" name="city_id" data-selected-district="{{ old('district_id', $customer->district_id) }}" data-selected-village="{{ old('village_id', $customer->village_id) }}" form="identity-form" onchange="wilayahLoadDistricts(this.value)"
                                            class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                        <option value="">Pilih Kota/Kabupaten</option>
                                        @foreach($cities as $city)
                                            <option value="{{ $city->id }}" {{ old('city_id', $customer->city_id) == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Kecamatan</span>
                                    <select id="identity-district_id" name="district_id" form="identity-form" onchange="wilayahLoadVillages(this.value)"
                                            class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                        <option value="">Pilih kota/kabupaten dulu</option>
                                    </select>
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Desa/Kelurahan</span>
                                    <select id="identity-village_id" name="village_id" form="identity-form"
                                            class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                        <option value="">Pilih kecamatan dulu</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Latitude</span>
                                    <input type="text" name="latitude" form="identity-form" value="{{ old('latitude', $customer->latitude) }}" inputmode="decimal"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs data-text text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                                <label class="block">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Longitude</span>
                                    <input type="text" name="longitude" form="identity-form" value="{{ old('longitude', $customer->longitude) }}" inputmode="decimal"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2.5 text-xs data-text text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </label>
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-700">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">Simpan perubahan data diri sebelum lanjut</span>
                                <button type="submit" form="identity-form"
                                        class="px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-sm inline-flex items-center gap-1.5">
                                    <x-ui.icon name="save" class="w-3.5 h-3.5" />
                                    <span>Simpan Data Diri</span>
                                </button>
                            </div>
                        </div>

                        <!-- READ ONLY CARDS (Mobile-friendly Grid) -->
                        <div x-show="!editingIdentity" class="space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3 text-xs">
                                <!-- Card: Nama & NIK -->
                                <div class="p-3.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Nama Lengkap</span>
                                    <span class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ $customer->full_name }}</span>
                                    <span class="block text-[11px] data-text text-slate-500 dark:text-slate-400 mt-0.5">NIK: {{ $customer->identity_number ?? '-' }}</span>
                                </div>

                                <!-- Card: Kontak Telepon -->
                                <div class="p-3.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Kontak Telepon</span>
                                    <span class="block text-sm data-text font-bold text-slate-800 dark:text-slate-100">{{ $customer->primary_phone ?? $customer->phone ?? '-' }}</span>
                                    <span class="block text-[11px] data-text text-slate-500 dark:text-slate-400 mt-0.5">Alt: {{ $customer->alternative_phone ?? '-' }}</span>
                                </div>

                                <!-- Card: Alamat Lengkap -->
                                <div class="p-3.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 sm:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Alamat Instalasi</span>
                                    <span class="block text-xs font-bold text-slate-900 dark:text-slate-100 leading-snug">{{ $customer->address }}</span>
                                    <span class="block text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                        Kel. {{ $customer->village->name ?? '-' }},
                                        Kec. {{ $customer->district->name ?? '-' }},
                                        {{ $customer->city->name ?? '-' }}
                                    </span>
                                </div>

                                <!-- Card: Titik Koordinat -->
                                <div class="p-3.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Koordinat GPS</span>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs data-text font-mono font-semibold text-slate-800 dark:text-slate-200">
                                            {{ $customer->latitude ?? '-' }}, {{ $customer->longitude ?? '-' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Card: POP Cabang -->
                                <div class="p-3.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">POP Wilayah</span>
                                    <span class="block text-xs font-bold text-sky-600 dark:text-sky-400">{{ $customer->pop->name ?? '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 PANEL: Upload Dokumen Lampiran -->
                    <div id="step-panel-2" class="step-panel space-y-5 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 font-bold text-xs flex items-center justify-center">2</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">Upload Dokumen Lampiran</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Unggah foto rumah pelanggan dan foto ODP/jalur terdekat</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                            <!-- Foto Rumah Card -->
                            <div class="border-2 border-dashed @error('house_photo') border-rose-400 bg-rose-50/20 @else border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 @enderror hover:border-sky-500 dark:hover:border-sky-400 rounded-2xl p-4 text-center transition-all shadow-sm flex flex-col justify-between relative group">
                                <div id="default-placeholder-house_photo" class="py-5 space-y-2.5 {{ $existingHousePhoto ? 'hidden' : '' }}">
                                    <div class="w-12 h-12 mx-auto rounded-2xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-200 dark:border-sky-800 shadow-sm">
                                        <x-ui.icon name="house" class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">FOTO RUMAH PELANGGAN <span class="text-rose-500">*</span></span>
                                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Ambil foto tampak depan rumah</span>
                                    </div>
                                </div>

                                <div id="preview-container-house_photo" style="display: {{ $existingHousePhoto ? '' : 'none' }};" class="py-2 flex flex-col items-center justify-center relative">
                                    <div class="relative inline-block w-full">
                                        <img id="preview-img-house_photo" class="max-h-40 max-w-full rounded-xl object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="{{ $existingHousePhoto }}" alt="Preview Foto Rumah">
                                        <button type="button" onclick="clearFile('house_photo')" class="absolute -top-2.5 -right-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-7 h-7 flex items-center justify-center shadow-lg hover:scale-110 active:scale-95 transition-transform focus:outline-none cursor-pointer" title="Hapus File">
                                            <x-ui.icon name="x" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-2 px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
                                        <x-ui.icon name="check" class="w-3 h-3" /> Foto Rumah {{ $existingHousePhoto ? 'Tersimpan' : 'Terpilih' }}
                                    </span>
                                </div>

                                <div class="mt-3">
                                    <input type="file" name="house_photo" id="house_photo" accept="image/*" class="hidden" onchange="onFileChange('house_photo')" {{ $existingHousePhoto ? 'data-populated=true' : '' }}>
                                    <input type="hidden" name="house_photo_removed" id="house_photo_removed_flag" value="0">
                                    <label for="house_photo" class="w-full text-center bg-sky-600 hover:bg-sky-700 active:scale-95 text-white text-xs font-semibold py-2.5 px-3 rounded-xl cursor-pointer transition-all shadow-sm focus:outline-none inline-flex items-center justify-center gap-1.5">
                                        <x-ui.icon name="camera" class="w-3.5 h-3.5" />
                                        <span>{{ $existingHousePhoto ? 'Ganti Foto Rumah' : 'Pilih / Ambil Foto Rumah' }}</span>
                                    </label>
                                    <span id="file-label-house_photo" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1.5 font-mono truncate">{{ $existingHousePhoto ? 'Sudah diunggah — klik untuk ganti' : 'Belum ada file dipilih' }}</span>
                                </div>

                                @error('house_photo')
                                    <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-2">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Foto ODP Card -->
                            <div class="border-2 border-dashed @error('survey_photo') border-rose-400 bg-rose-50/20 @else border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 @enderror hover:border-sky-500 dark:hover:border-sky-400 rounded-2xl p-4 text-center transition-all shadow-sm flex flex-col justify-between relative group">
                                <div id="default-placeholder-survey_photo" class="py-5 space-y-2.5 {{ $existingSurveyPhoto ? 'hidden' : '' }}">
                                    <div class="w-12 h-12 mx-auto rounded-2xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-200 dark:border-sky-800 shadow-sm">
                                        <x-ui.icon name="network" class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">FOTO ODP / JALUR KABEL <span class="text-rose-500">*</span></span>
                                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Foto tiang ODP atau jalur tarikan kabel</span>
                                    </div>
                                </div>

                                <div id="preview-container-survey_photo" style="display: {{ $existingSurveyPhoto ? '' : 'none' }};" class="py-2 flex flex-col items-center justify-center relative">
                                    <div class="relative inline-block w-full">
                                        <img id="preview-img-survey_photo" class="max-h-40 max-w-full rounded-xl object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="{{ $existingSurveyPhoto }}" alt="Preview Foto ODP">
                                        <button type="button" onclick="clearFile('survey_photo')" class="absolute -top-2.5 -right-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-7 h-7 flex items-center justify-center shadow-lg hover:scale-110 active:scale-95 transition-transform focus:outline-none cursor-pointer" title="Hapus File">
                                            <x-ui.icon name="x" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-2 px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
                                        <x-ui.icon name="check" class="w-3 h-3" /> Foto ODP {{ $existingSurveyPhoto ? 'Tersimpan' : 'Terpilih' }}
                                    </span>
                                </div>

                                <div class="mt-3">
                                    <input type="file" name="survey_photo" id="survey_photo" accept="image/*" class="hidden" onchange="onFileChange('survey_photo')" {{ $existingSurveyPhoto ? 'data-populated=true' : '' }}>
                                    <input type="hidden" name="survey_photo_removed" id="survey_photo_removed_flag" value="0">
                                    <label for="survey_photo" class="w-full text-center bg-sky-600 hover:bg-sky-700 active:scale-95 text-white text-xs font-semibold py-2.5 px-3 rounded-xl cursor-pointer transition-all shadow-sm focus:outline-none inline-flex items-center justify-center gap-1.5">
                                        <x-ui.icon name="camera" class="w-3.5 h-3.5" />
                                        <span>{{ $existingSurveyPhoto ? 'Ganti Foto ODP' : 'Pilih / Ambil Foto ODP' }}</span>
                                    </label>
                                    <span id="file-label-survey_photo" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1.5 font-mono truncate">{{ $existingSurveyPhoto ? 'Sudah diunggah — klik untuk ganti' : 'Belum ada file dipilih' }}</span>
                                </div>

                                @error('survey_photo')
                                    <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-2">{{ $message }}</p>
                                @enderror
                            </div>

                        </div>
                    </div>

                    <!-- STEP 3 PANEL: Layanan & Paket -->
                    <div id="step-panel-3" class="step-panel space-y-5 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 font-bold text-xs flex items-center justify-center">3</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">Layanan &amp; Paket Layanan Internet</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Verifikasi atau koreksi paket layanan internet yang dipilih</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-slate-50/80 dark:bg-slate-900/50 rounded-2xl p-4 sm:p-5 border border-slate-200/70 dark:border-slate-700/60 space-y-4 text-xs">
                            <div>
                                <label for="internet_package_id" class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Pilih Paket Internet</label>
                                <select name="internet_package_id" id="internet_package_id" onchange="updateSurveyPackagePreview()" class="w-full text-xs data-text px-3.5 py-3 border @error('internet_package_id') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all shadow-sm">
                                    <option value="">Belum Dipilih</option>
                                    @foreach($internetPackages as $package)
                                        <option value="{{ $package->id }}" data-price="{{ $package->monthly_price }}" {{ old('internet_package_id', $customer->internet_package_id) == $package->id ? 'selected' : '' }}>
                                            {{ $package->package_code }} — {{ $package->name }} (Rp {{ number_format($package->monthly_price, 0, ',', '.') }}/bln)
                                        </option>
                                    @endforeach
                                </select>
                                @error('internet_package_id')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Live Price Banner -->
                            <div class="p-3.5 rounded-xl bg-sky-50/80 dark:bg-sky-950/40 border border-sky-100 dark:border-sky-900/60 flex items-center justify-between">
                                <span class="text-xs font-semibold text-sky-800 dark:text-sky-300">Biaya Bulanan Dasar:</span>
                                <span id="survey_package_price_preview" class="text-base font-black text-sky-700 dark:text-sky-400 data-text">Rp {{ number_format($customer->internetPackage->monthly_price ?? 0, 0, ',', '.') }}</span>
                            </div>

                            <div class="pt-3 border-t border-slate-200/60 dark:border-slate-700/60 grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-4">
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

                    <!-- STEP 4 PANEL: Laporan Survey Lapangan -->
                    <div id="step-panel-4" class="step-panel space-y-5 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 font-bold text-xs flex items-center justify-center">4</span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">Laporan Survey Lapangan</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Data teknis hasil pengamatan dan estimasi kebutuhan di lokasi</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <!-- ODP Terdekat -->
                            <div>
                                <label for="nearest_odp" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">
                                    ODP Terdekat <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" name="nearest_odp" id="nearest_odp" value="{{ old('nearest_odp') }}" class="w-full text-xs data-text px-3.5 py-2.5 sm:py-2.5 border @error('nearest_odp') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all shadow-sm" placeholder="Contoh: ODP-BBD-01">
                                </div>
                                @error('nearest_odp')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Estimasi Kabel -->
                            <div>
                                <label for="cable_estimation_meter" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">
                                    Estimasi Kabel (Meter) <span class="text-rose-500">*</span>
                                </label>
                                <div class="space-y-1.5">
                                    <div class="relative">
                                        <input type="number" name="cable_estimation_meter" id="cable_estimation_meter" min="0" inputmode="numeric" value="{{ old('cable_estimation_meter') }}" class="w-full text-xs data-text px-3.5 py-2.5 border @error('cable_estimation_meter') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all shadow-sm" placeholder="Contoh: 150">
                                        <span class="absolute right-3 top-2.5 text-xs text-slate-400 font-semibold">Meter</span>
                                    </div>
                                    <!-- Quick Cable Preset Chips for Mobile -->
                                    <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
                                        <span class="text-[10px] text-slate-400">Preset:</span>
                                        <button type="button" onclick="setCableEstimate(50)" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-sky-100 dark:hover:bg-sky-900/40 hover:text-sky-700 transition-colors">50m</button>
                                        <button type="button" onclick="setCableEstimate(100)" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-sky-100 dark:hover:bg-sky-900/40 hover:text-sky-700 transition-colors">100m</button>
                                        <button type="button" onclick="setCableEstimate(150)" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-sky-100 dark:hover:bg-sky-900/40 hover:text-sky-700 transition-colors">150m</button>
                                        <button type="button" onclick="setCableEstimate(200)" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-sky-100 dark:hover:bg-sky-900/40 hover:text-sky-700 transition-colors">200m</button>
                                    </div>
                                </div>
                                @error('cable_estimation_meter')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tingkat Kesulitan (Interactive Segmented Cards for Mobile) -->
                            <div class="sm:col-span-2">
                                <label class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">
                                    Tingkat Kesulitan Jalur <span class="text-rose-500">*</span>
                                </label>
                                <!-- Hidden standard select element synced with cards -->
                                <select name="difficulty_level" id="difficulty_level" class="hidden">
                                    <option value="" disabled {{ old('difficulty_level') ? '' : 'selected' }}>Pilih Tingkat Kesulitan</option>
                                    <option value="MUDAH" {{ old('difficulty_level') === 'MUDAH' ? 'selected' : '' }}>MUDAH</option>
                                    <option value="SEDANG" {{ old('difficulty_level') === 'SEDANG' ? 'selected' : '' }}>SEDANG</option>
                                    <option value="SULIT" {{ old('difficulty_level') === 'SULIT' ? 'selected' : '' }}>SULIT</option>
                                </select>

                                <div class="grid grid-cols-3 gap-2 sm:gap-3">
                                    <button type="button" onclick="selectDifficulty('MUDAH')" id="diff-btn-MUDAH" class="difficulty-card p-3 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none {{ old('difficulty_level') === 'MUDAH' ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 shadow-sm ring-2 ring-emerald-500/20' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                        <span class="text-sm">🟢</span>
                                        <span class="text-xs font-bold">MUDAH</span>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500 hidden sm:block">Jalur aman &amp; lurus</span>
                                    </button>

                                    <button type="button" onclick="selectDifficulty('SEDANG')" id="diff-btn-SEDANG" class="difficulty-card p-3 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none {{ old('difficulty_level') === 'SEDANG' ? 'border-amber-500 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 shadow-sm ring-2 ring-amber-500/20' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                        <span class="text-sm">🟡</span>
                                        <span class="text-xs font-bold">SEDANG</span>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500 hidden sm:block">Pohon / belokan</span>
                                    </button>

                                    <button type="button" onclick="selectDifficulty('SULIT')" id="diff-btn-SULIT" class="difficulty-card p-3 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none {{ old('difficulty_level') === 'SULIT' ? 'border-rose-500 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 shadow-sm ring-2 ring-rose-500/20' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                        <span class="text-sm">🔴</span>
                                        <span class="text-xs font-bold">SULIT</span>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500 hidden sm:block">Seberang jalan/sungai</span>
                                    </button>
                                </div>
                                @error('difficulty_level')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tanggal Request Pemasangan (opsional) -->
                            <div class="sm:col-span-2">
                                <label for="requested_installation_date" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Tanggal Request Pemasangan (Opsional)</label>
                                <input type="date" name="requested_installation_date" id="requested_installation_date" min="{{ now()->toDateString() }}" value="{{ old('requested_installation_date') }}" class="w-full text-xs font-sans px-3.5 py-2.5 border @error('requested_installation_date') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all shadow-sm">
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-1 leading-relaxed">Kosongkan jika pelanggan tidak meminta tanggal tertentu.</span>
                                @error('requested_installation_date')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Estimasi Kebutuhan Alat (material terstruktur) -->
                            <div class="sm:col-span-2 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                                <label class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Estimasi Kebutuhan Alat &amp; Material</label>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-3 leading-relaxed">Perkiraan jenis &amp; jumlah material yang akan dipakai saat pemasangan. Cukup pilih kategori; nama model diisi saat pemasangan dari custody teknisi.</p>
                                <x-material-rows
                                    name="materials"
                                    mode="kategori"
                                    :categories="$itemCategories"
                                    :rows="$materialRows"
                                    empty-label="Belum ada estimasi. Klik Tambah Barang bila perlu."
                                />
                            </div>

                            <!-- Alat kerja terstruktur -->
                            <div class="sm:col-span-2 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                                <x-work-tool-checklist
                                    name="work_tools"
                                    :tools="$workTools"
                                    :rows="$workToolRows"
                                    label="Alat Kerja Opsional"
                                    hint="Centang alat yang boleh dibawa tim pemasangan bila dibutuhkan di lokasi. Tidak wajib dibawa."
                                />
                            </div>

                            <!-- Kendala peralatan -->
                            <div class="sm:col-span-2">
                                <label for="required_tools" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Catatan Kendala Peralatan</label>
                                <textarea name="required_tools" id="required_tools" rows="2" class="w-full text-xs font-sans px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all shadow-sm" placeholder="Cth: Tangga harus ekstra panjang, lokasi masuk gang sempit...">{{ old('required_tools') }}</textarea>
                            </div>

                            <!-- Catatan Teknis Survey with Quick Preset Chips -->
                            <div class="sm:col-span-2">
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="survey_note" class="font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Catatan Teknis Survey</label>
                                </div>
                                <textarea name="survey_note" id="survey_note" rows="3" class="w-full text-xs font-sans px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all shadow-sm" placeholder="Tuliskan kendala teknis atau informasi penting untuk tim instalasi...">{{ old('survey_note') }}</textarea>

                                <!-- Field Quick Chips -->
                                <div class="flex items-center gap-1.5 flex-wrap mt-2">
                                    <span class="text-[10px] text-slate-400">Template Cepat:</span>
                                    <button type="button" onclick="appendSurveyNote('Jalur kabel aman &amp; siap pasang.')" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-sky-100 hover:text-sky-700 transition-colors">+ Jalur Aman</button>
                                    <button type="button" onclick="appendSurveyNote('Tiang menyeberang jalan utama, butuh klem khusus.')" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-sky-100 hover:text-sky-700 transition-colors">+ Seberang Jalan</button>
                                    <button type="button" onclick="appendSurveyNote('ODP penuh, perlu tambah splitter.')" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-sky-100 hover:text-sky-700 transition-colors">+ Butuh Splitter</button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- DESKTOP & MOBILE NAVIGATION FOOTER (Sticky safe on mobile) -->
                <div class="px-4 sm:px-7 py-3.5 sm:py-4 bg-slate-50/95 dark:bg-slate-900/80 backdrop-blur border-t border-slate-200 dark:border-slate-700/60 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3 shrink-0 rounded-b-2xl">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button type="button" id="btn-prev" onclick="prevStep()" style="display: none;" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm active:scale-95">
                            <x-ui.icon name="chevron-left" class="w-3.5 h-3.5" />
                            <span>Sebelumnya</span>
                        </button>
                        <a href="{{ route('surveys.queue') }}" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all text-xs font-semibold cursor-pointer focus:outline-none text-center inline-flex items-center justify-center active:scale-95">
                            Batal
                        </a>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button type="button" id="btn-next" onclick="nextStep()" class="w-full sm:w-auto px-6 py-2.5 sm:py-2 bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white rounded-xl transition-all text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-md shadow-sky-500/20 active:scale-95">
                            <span>Lanjut Langkah Berikutnya</span>
                            <x-ui.icon name="chevron-right" class="w-3.5 h-3.5" />
                        </button>

                        <button type="submit" id="btn-submit" style="display: none;" class="w-full sm:w-auto px-6 py-2.5 sm:py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl transition-all text-xs font-bold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-md shadow-emerald-500/20 active:scale-95">
                            <x-ui.icon name="check-circle" class="w-4 h-4" />
                            <span>Simpan Laporan Survey</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </form>

    {{-- Form Data Diri Step 1 (Hidden form for inline update) --}}
    <form id="identity-form" action="{{ route('customers.survey.update-identity', $customer) }}" method="POST" class="hidden">
        @csrf
        @method('PUT')
    </form>
</div>
@endsection

@section('scripts')
<script>
    // Helper: Preset Cable & Survey Notes
    function setCableEstimate(meters) {
        const input = document.getElementById('cable_estimation_meter');
        if (input) {
            input.value = meters;
            input.dispatchEvent(new Event('input'));
        }
    }

    function selectDifficulty(level) {
        const select = document.getElementById('difficulty_level');
        if (select) {
            select.value = level;
            select.dispatchEvent(new Event('change'));
        }

        document.querySelectorAll('.difficulty-card').forEach(btn => {
            btn.className = "difficulty-card p-3 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800";
        });

        const activeBtn = document.getElementById('diff-btn-' + level);
        if (activeBtn) {
            if (level === 'MUDAH') {
                activeBtn.className = "difficulty-card p-3 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 shadow-sm ring-2 ring-emerald-500/20";
            } else if (level === 'SEDANG') {
                activeBtn.className = "difficulty-card p-3 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none border-amber-500 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 shadow-sm ring-2 ring-amber-500/20";
            } else if (level === 'SULIT') {
                activeBtn.className = "difficulty-card p-3 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none border-rose-500 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 shadow-sm ring-2 ring-rose-500/20";
            }
        }
    }

    function appendSurveyNote(text) {
        const textarea = document.getElementById('survey_note');
        if (textarea) {
            if (textarea.value.trim() === '') {
                textarea.value = text;
            } else {
                textarea.value = textarea.value.trim() + ' ' + text;
            }
            textarea.dispatchEvent(new Event('input'));
        }
    }

</script>
@include('partials.wilayah-cascade-script')
<script>

    function updateSurveyPackagePreview() {
        const select = document.getElementById('internet_package_id');
        const preview = document.getElementById('survey_package_price_preview');
        const selected = select.options[select.selectedIndex];
        const price = selected ? parseFloat(selected.dataset.price || '0') : 0;
        preview.textContent = 'Rp ' + Math.round(price).toLocaleString('id-ID');
    }

    /* ── Wizard Form Stepper & Live Validation Logic ── */
    let currentActiveStep = 1;
    const totalStepsCount = 4;

    const formFields = {
        'dokumen': {
            required: ['house_photo', 'survey_photo'],
            optional: []
        },
        'laporan': {
            required: ['nearest_odp', 'cable_estimation_meter', 'difficulty_level'],
            optional: ['required_tools', 'survey_note', 'requested_installation_date']
        }
    };

    const stepKeys = {
        2: 'dokumen',
        4: 'laporan'
    };

    const inputSteps = [2, 4];
    const readOnlySteps = [1, 3];

    document.addEventListener("DOMContentLoaded", function() {
        const inputs = document.querySelectorAll('#wizard-form input, #wizard-form select, #wizard-form textarea');
        inputs.forEach(input => {
            input.addEventListener('input', runLiveProgressUpdates);
            input.addEventListener('change', runLiveProgressUpdates);
        });

        updateWizardButtons();
        runLiveProgressUpdates();
    });

    function setElementVisible(el, visible) {
        if (!el) return;
        el.style.display = visible ? '' : 'none';
    }

    function updateWizardButtons() {
        const isFirstStep = currentActiveStep === 1;
        const isLastStep = currentActiveStep === totalStepsCount;

        setElementVisible(document.getElementById('btn-prev'), !isFirstStep);
        setElementVisible(document.getElementById('btn-next'), !isLastStep);
        setElementVisible(document.getElementById('btn-submit'), isLastStep);
    }

    function onFileChange(fieldId) {
        const input = document.getElementById(fieldId);
        const label = document.getElementById('file-label-' + fieldId);
        const defaultPlaceholder = document.getElementById('default-placeholder-' + fieldId);
        const previewContainer = document.getElementById('preview-container-' + fieldId);
        const previewImg = document.getElementById('preview-img-' + fieldId);
        const removedFlag = document.getElementById(fieldId + '_removed_flag');

        if (input.files && input.files.length > 0) {
            const file = input.files[0];
            label.textContent = file.name;
            input.setAttribute('data-populated', 'true');
            if (removedFlag) removedFlag.value = '0';

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
            label.textContent = "Belum ada file dipilih";
            input.removeAttribute('data-populated');
            if (defaultPlaceholder) defaultPlaceholder.classList.remove('hidden');
            setElementVisible(previewContainer, false);
            if (previewImg) previewImg.src = '';
        }
        runLiveProgressUpdates();
    }

    function clearFile(fieldId) {
        const input = document.getElementById(fieldId);
        const removedFlag = document.getElementById(fieldId + '_removed_flag');
        if (removedFlag) removedFlag.value = '1';
        if (input) {
            input.value = '';
            onFileChange(fieldId);
        }
    }

    function runLiveProgressUpdates() {
        let totalRequiredFieldsCount = 0;
        let filledRequiredFieldsCount = 0;

        inputSteps.forEach(step => {
            const config = formFields[stepKeys[step]];
            let requiredMissing = [];
            let optionalMissing = [];

            config.required.forEach(field => {
                totalRequiredFieldsCount++;
                const el = document.getElementById(field);
                if (el) {
                    const isFilePopulated = el.type === 'file' && el.getAttribute('data-populated') === 'true';
                    if (el.value.trim() !== "" || isFilePopulated) {
                        filledRequiredFieldsCount++;
                    } else {
                        requiredMissing.push(getLabelName(field));
                    }
                }
            });

            config.optional.forEach(field => {
                const el = document.getElementById(field);
                if (el && el.value.trim() === "") {
                    optionalMissing.push(getLabelName(field));
                }
            });

            updateStepNavStatus(step, requiredMissing, optionalMissing);
        });

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

    function updateStepNavStatus(step, requiredMissing, optionalMissing) {
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
            statusSpan.className = 'text-[9px] font-bold block uppercase tracking-wider text-rose-600 dark:text-rose-400 mt-0.5';
            iconDiv.innerHTML = `<span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400"><x-ui.icon name="x" class="w-3 h-3" /></span>`;
            missingSpan.textContent = 'Wajib diisi: ' + requiredMissing.join(', ');

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none";
            }
            if (mBadge && currentActiveStep !== step) {
                mBadge.className = "w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold flex items-center justify-center shrink-0";
                mBadge.textContent = step;
            }
        } else {
            statusSpan.textContent = 'Lengkap';
            statusSpan.className = 'text-[9px] font-bold block uppercase tracking-wider text-emerald-600 dark:text-emerald-400 mt-0.5';
            iconDiv.innerHTML = `<span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-sm"><x-ui.icon name="check" class="w-3 h-3" /></span>`;
            missingSpan.textContent = optionalMissing.length > 0 ? 'Beberapa opsional kosong' : 'Semua terisi';

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-all group focus:outline-none";
            }
            if (mBadge && currentActiveStep !== step) {
                mBadge.className = "w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0 shadow-sm";
                mBadge.textContent = "✓";
            }
        }

        if (currentActiveStep === step) {
            navBtn.className = "w-full text-left p-3.5 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm";
            if (mBadge) {
                mBadge.className = "w-5 h-5 rounded-full bg-sky-600 text-white text-[10px] font-bold flex items-center justify-center shrink-0 shadow-sm";
                mBadge.textContent = step;
            }
        }
    }

    function getLabelName(field) {
        const labels = {
            house_photo: 'Foto Rumah',
            survey_photo: 'Foto ODP Terdekat',
            nearest_odp: 'ODP Terdekat',
            cable_estimation_meter: 'Estimasi Kabel',
            difficulty_level: 'Tingkat Kesulitan',
            required_tools: 'Alat Khusus',
            survey_note: 'Catatan Teknis',
            requested_installation_date: 'Tanggal Request Pemasangan'
        };
        return labels[field] || field;
    }

    function goToStep(stepNumber) {
        document.getElementById('step-panel-' + currentActiveStep).classList.add('hidden');
        currentActiveStep = stepNumber;
        document.getElementById('step-panel-' + currentActiveStep).classList.remove('hidden');

        for (let i = 1; i <= totalStepsCount; i++) {
            const mBtn = document.getElementById('mobile-step-btn-' + i);
            if (!mBtn) continue;

            if (i === currentActiveStep) {
                mBtn.className = "py-2.5 px-1 sm:px-2 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 shadow-sm";
            } else if (readOnlySteps.includes(i)) {
                mBtn.className = "py-2.5 px-1 sm:px-2 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800";
            } else {
                mBtn.className = "py-2.5 px-1 sm:px-2 rounded-xl text-center transition-all flex flex-col items-center justify-center gap-1 focus:outline-none relative text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40";
            }
        }

        updateWizardButtons();
        runLiveProgressUpdates();

        readOnlySteps.forEach(step => {
            const navBtn = document.getElementById('step-nav-' + step);
            if (!navBtn) return;

            if (currentActiveStep === step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm";
            } else {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-all group focus:outline-none";
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
