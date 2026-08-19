@extends('layouts.app')

@section('title', 'Lapor Hasil Pemasangan — Whusnet Operasional')
@section('page_title', 'Lapor Hasil Pemasangan')
@section('breadcrumb_parent', 'Antrean Pemasangan')
@section('breadcrumb_parent_url', route('verifications.queue'))

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    <!-- LAYER 1: NAKED PAGE HEADER WITH TIMER WIDGET (Strict Design System Rule: No card wrapper) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">Laporan Hasil Pemasangan</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700/60">
                    <x-ui.icon name="user-check" class="w-2.5 h-2.5 mr-1.5" /> {{ $customer->full_name }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                Lapor spesifikasi teknis perangkat (ONT/Router), konfigurasi koneksi, foto instalasi, dan hasil uji speedtest.
            </p>
        </div>

        <div class="flex items-center gap-3 flex-wrap sm:flex-nowrap">
            @if($installation && $installation->started_at)
                <!-- Real-time Timer Counter Widget — hanya relevan kalau task sudah dimulai -->
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-700 dark:text-amber-300 shadow-sm" title="Durasi Pengerjaan Pemasangan">
                    <x-ui.icon name="timer" class="w-3 h-3 animate-pulse text-amber-600 dark:text-amber-400" />
                    <span class="text-xs font-semibold">Durasi:</span>
                    <span id="timer-display" class="font-mono text-sm font-extrabold tracking-wider">00:00:00</span>
                </div>
            @endif

            <a href="{{ $returnTo }}" class="px-3.5 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors inline-flex items-center gap-2">
                <x-ui.icon name="arrow-left" class="w-3 h-3" />
                <span>Kembali</span>
            </a>
        </div>
    </div>

    {{--
        DUA FORM TERPISAH (bukan satu wizard-form): #form-pemasangan
        membungkus step-panel-5, #form-speedtest membungkus step-panel-6.
        Tombol submit-nya ada di footer BERSAMA di luar kedua form (supaya
        satu footer dipakai semua step) — makanya trigger-nya JS
        (handlePemasanganSubmit/handleSpeedtestSubmit) yang manggil
        document.getElementById('form-...').submit(), bukan type="submit"
        biasa. Step 5 submit ke customers.installation.pemasangan TANPA
        menyelesaikan task/workflow; Step 6 baru terbuka setelah itu
        (gerbang $pemasanganComplete) & submit ke customers.installation.speedtest
        — SATU-SATUNYA titik penyelesaian pemasangan di alur wizard ini.
        Lihat CustomerInstallationController::storePemasangan()/storeSpeedtest().
    --}}
    <div id="wizard-container" class="space-y-6">
        <!-- TOP PANEL: Dynamic Completeness Progress Bar -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-4 sm:p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800/50">
                        <x-ui.icon name="wrench" class="w-3 h-3" />
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Kelengkapan Laporan Pemasangan</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Data teknis &amp; foto bukti speedtest divalidasi sebelum disimpan</p>
                    </div>
                </div>
                <div class="text-right">
                    <span id="progress-percentage" class="text-sm sm:text-base font-extrabold text-sky-600 dark:text-sky-400 data-text">0%</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 block"><span id="filled-fields-count" class="data-text font-semibold">0</span> dari <span id="total-fields-count" class="data-text font-semibold">12</span> field terisi</span>
                </div>
            </div>

            <!-- Progress Bar Fill Strip -->
            <div class="w-full bg-slate-100 dark:bg-slate-700/60 rounded-full h-2.5 overflow-hidden border border-slate-200/60 dark:border-slate-700">
                <div id="progress-bar-fill" class="bg-gradient-to-r from-sky-500 to-sky-600 h-full w-0 transition-all duration-500 ease-out" style="width: 0%;"></div>
            </div>
        </div>

        <!-- MOBILE RESPONSIVE STEPPER (Visible on < lg screens: 2 rows x 3 items) -->
        <div class="lg:hidden bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-3 shadow-sm">
            <div class="grid grid-cols-3 gap-2 text-center">
                <button type="button" onclick="goToStep(1)" id="mobile-step-btn-1" class="py-2.5 px-2 rounded-lg text-xs font-bold bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 transition-all flex items-center justify-center gap-1.5 shadow-sm">
                    <span class="w-4 h-4 rounded-full bg-sky-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">1</span>
                    <span class="truncate text-[11px] font-semibold">1. Data Diri</span>
                </button>
                <button type="button" onclick="goToStep(2)" id="mobile-step-btn-2" class="py-2.5 px-2 rounded-lg text-xs font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 transition-all flex items-center justify-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-emerald-500 text-white text-[9px] font-bold flex items-center justify-center shrink-0">✓</span>
                    <span class="truncate text-[11px] font-semibold">2. Dokumen</span>
                </button>
                <button type="button" onclick="goToStep(3)" id="mobile-step-btn-3" class="py-2.5 px-2 rounded-lg text-xs font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 transition-all flex items-center justify-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-emerald-500 text-white text-[9px] font-bold flex items-center justify-center shrink-0">✓</span>
                    <span class="truncate text-[11px] font-semibold">3. Paket</span>
                </button>
                <button type="button" onclick="goToStep(4)" id="mobile-step-btn-4" class="py-2.5 px-2 rounded-lg text-xs font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 transition-all flex items-center justify-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-emerald-500 text-white text-[9px] font-bold flex items-center justify-center shrink-0">✓</span>
                    <span class="truncate text-[11px] font-semibold">4. Survey</span>
                </button>
                <button type="button" onclick="goToStep(5)" id="mobile-step-btn-5" class="py-2.5 px-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all flex items-center justify-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[9px] font-bold flex items-center justify-center shrink-0">5</span>
                    <span class="truncate text-[11px] font-semibold">5. Pasang</span>
                </button>
                <button type="button" onclick="goToStep(6)" id="mobile-step-btn-6" class="py-2.5 px-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all flex items-center justify-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[9px] font-bold flex items-center justify-center shrink-0">6</span>
                    <span class="truncate text-[11px] font-semibold">6. Speedtest</span>
                </button>
            </div>
        </div>

        <!-- MAIN GRID LAYOUT -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- LEFT COLUMN: Desktop 6-Step Checklist (Visible on >= lg screens) -->
            <div class="hidden lg:block lg:col-span-4 space-y-4">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-5 shadow-sm space-y-3">
                    <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-3">Tahapan Laporan</h4>

                    <div class="space-y-2.5">
                        <!-- Step 1 Navigation Card (Read-only) -->
                        <button type="button" onclick="goToStep(1)" id="step-nav-1" class="w-full text-left p-3 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm">
                            <div class="flex items-center gap-3">
                                <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">1. Data Diri Pelanggan</span>
                                    <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 block uppercase tracking-wider">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 2 Navigation Card (Read-only) -->
                        <button type="button" onclick="goToStep(2)" id="step-nav-2" class="w-full text-left p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 transition-all group focus:outline-none">
                            <div class="flex items-center gap-3">
                                <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">2. Dokumen Lampiran</span>
                                    <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 block uppercase tracking-wider">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 3 Navigation Card (Read-only) -->
                        <button type="button" onclick="goToStep(3)" id="step-nav-3" class="w-full text-left p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 transition-all group focus:outline-none">
                            <div class="flex items-center gap-3">
                                <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">3. Layanan &amp; Paket</span>
                                    <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 block uppercase tracking-wider">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 4 Navigation Card (Read-only) -->
                        <button type="button" onclick="goToStep(4)" id="step-nav-4" class="w-full text-left p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 transition-all group focus:outline-none">
                            <div class="flex items-center gap-3">
                                <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0">
                                    <x-ui.icon name="check" class="w-2.5 h-2.5" />
                                </span>
                                <div class="min-w-0">
                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">4. Laporan Survey Lapangan</span>
                                    <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 block uppercase tracking-wider">Lengkap</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 5 Navigation Card (Input) -->
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

                        <!-- Step 6 Navigation Card (Input) -->
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

            <!-- RIGHT COLUMN: Wizard Form Content Panels -->
            <div class="lg:col-span-8 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl shadow-sm overflow-hidden flex flex-col justify-between min-h-[540px]">

                <!-- FORM BODY -->
                <div class="p-5 sm:p-7 flex-1">

                    <!-- STEP 1 PANEL: Data Diri Pelanggan (Read-Only) -->
                    <div id="step-panel-1" class="step-panel space-y-6">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">1. IDENTITAS PELANGGAN &amp; ALAMAT</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Data registrasi calon pelanggan.</p>
                        </div>

                        <div class="bg-slate-50/70 dark:bg-slate-900/50 rounded-xl p-5 border border-slate-200/60 dark:border-slate-700/60 grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4 text-xs">
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Nama Lengkap</span>
                                <span class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ $customer->full_name }}</span>
                            </div>

                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Nomor Identitas (NIK)</span>
                                <span class="block text-sm data-text font-semibold text-slate-800 dark:text-slate-100">{{ $customer->identity_number ?? '-' }}</span>
                            </div>

                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Nomor HP Utama</span>
                                <span class="block text-xs data-text font-semibold text-slate-800 dark:text-slate-200">{{ $customer->primary_phone ?? $customer->phone ?? '-' }}</span>
                            </div>

                            <div class="md:col-span-2 pt-3 border-t border-slate-200/60 dark:border-slate-700/60">
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Alamat Instalasi Lengkap</span>
                                <span class="block text-xs font-bold text-slate-900 dark:text-slate-100">{{ $customer->address }}</span>
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                    Kel. {{ $customer->village->name ?? '-' }},
                                    Kec. {{ $customer->district->name ?? '-' }},
                                    {{ $customer->city->name ?? '-' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 PANEL: Dokumen Lampiran (Read-Only) -->
                    <div id="step-panel-2" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">2. DOKUMEN LAMPIRAN</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Dokumen foto yang diunggah pada tahap registrasi &amp; survey.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Foto Rumah -->
                            <div class="border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 rounded-xl p-4 flex flex-col justify-between shadow-sm text-center">
                                @if($customer->latestSurvey?->house_photo)
                                    <div>
                                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Foto Rumah</span>
                                        <div class="relative rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                                            <img class="max-h-32 max-w-full rounded-lg object-contain mx-auto hover:scale-105 transition-transform cursor-pointer"
                                                 src="{{ asset('storage/' . $customer->latestSurvey->house_photo) }}"
                                                 alt="Preview Foto Rumah"
                                                 onclick="window.open('{{ asset('storage/' . $customer->latestSurvey->house_photo) }}', '_blank')">
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center justify-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-3 bg-emerald-50 dark:bg-emerald-950/40 py-1 px-2 rounded-full border border-emerald-200 dark:border-emerald-800">
                                        <x-ui.icon name="circle-check" class="w-3 h-3" /> Terlampir
                                    </span>
                                @else
                                    <div class="flex flex-col justify-center py-4">
                                        <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-base mb-2">
                                            <x-ui.icon name="house" class="w-4 h-4" />
                                        </div>
                                        <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">Foto Rumah</span>
                                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-1">Tidak ada di survey</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Foto ODP -->
                            <div class="border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 rounded-xl p-4 flex flex-col justify-between shadow-sm text-center">
                                @if($customer->latestSurvey?->survey_photo)
                                    <div>
                                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Foto ODP Terdekat</span>
                                        <div class="relative rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                                            <img class="max-h-32 max-w-full rounded-lg object-contain mx-auto hover:scale-105 transition-transform cursor-pointer"
                                                 src="{{ asset('storage/' . $customer->latestSurvey->survey_photo) }}"
                                                 alt="Preview Foto ODP"
                                                 onclick="window.open('{{ asset('storage/' . $customer->latestSurvey->survey_photo) }}', '_blank')">
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center justify-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-3 bg-emerald-50 dark:bg-emerald-950/40 py-1 px-2 rounded-full border border-emerald-200 dark:border-emerald-800">
                                        <x-ui.icon name="circle-check" class="w-3 h-3" /> Terlampir
                                    </span>
                                @else
                                    <div class="flex flex-col justify-center py-4">
                                        <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-base mb-2">
                                            <x-ui.icon name="network" class="w-4 h-4" />
                                        </div>
                                        <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">Foto ODP</span>
                                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-1">Tidak ada di survey</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3 PANEL: Layanan & Paket (Read-Only) -->
                    <div id="step-panel-3" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">3. LAYANAN &amp; PAKET INTERNET</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Paket layanan yang dikonfirmasi pada saat pendaftaran.</p>
                        </div>

                        <div class="bg-slate-50/70 dark:bg-slate-900/50 rounded-xl p-5 border border-slate-200/60 dark:border-slate-700/60 grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4 text-xs">
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Paket Internet</span>
                                <span class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ $customer->internetPackage->package_code ?? '-' }} - {{ $customer->internetPackage->name ?? 'Belum Dipilih' }}</span>
                            </div>

                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Biaya Bulanan Dasar</span>
                                <span class="block text-sm data-text font-bold text-slate-800 dark:text-slate-100">Rp {{ number_format($customer->internetPackage->monthly_price ?? 0, 0, ',', '.') }}</span>
                            </div>

                            <div class="pt-3 border-t border-slate-200/60 dark:border-slate-700/60 md:col-span-2 grid grid-cols-3 gap-4">
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Jenis Kontrak</span>
                                    <span class="block text-xs font-bold uppercase text-slate-800 dark:text-slate-200">{{ $customer->customerService->contract_type ?? 'Sewa' }}</span>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Masa Kontrak</span>
                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $customer->customerService->contract_period_months ?? 12 }} Bulan</span>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Diskon Promosi</span>
                                    <span class="block text-xs data-text font-semibold text-slate-800 dark:text-slate-200">Rp {{ number_format($customer->discount_amount ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4 PANEL: Laporan Survey (Read-Only) -->
                    <div id="step-panel-4" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">4. LAPORAN SURVEY LAPANGAN</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Hasil pengamatan teknis awal oleh tim survey.</p>
                        </div>

                        <div class="bg-slate-50/70 dark:bg-slate-900/50 rounded-xl p-5 border border-slate-200/60 dark:border-slate-700/60 grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4 text-xs">
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">ODP Terdekat</span>
                                <span class="block text-sm data-text font-bold text-slate-800 dark:text-slate-100">{{ $customer->latestSurvey->nearest_odp ?? '-' }}</span>
                            </div>

                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Estimasi Kabel</span>
                                <span class="block text-sm data-text font-bold text-slate-800 dark:text-slate-100">{{ $customer->latestSurvey->cable_estimation_meter ?? '0' }} Meter</span>
                            </div>

                            @if($customer->latestSurvey?->requested_installation_date)
                                <div class="md:col-span-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Tanggal Request Pemasangan Pelanggan</span>
                                    <span class="block text-sm data-text font-bold text-slate-800 dark:text-slate-100">{{ \App\Support\IndonesianDate::date($customer->latestSurvey->requested_installation_date) }}</span>
                                </div>
                            @endif

                            <div class="md:col-span-2 pt-3 border-t border-slate-200/60 dark:border-slate-700/60">
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Tingkat Kesulitan</span>
                                <span class="block text-xs font-bold uppercase text-slate-800 dark:text-slate-200">{{ $customer->latestSurvey->difficulty_level ?? '-' }}</span>
                            </div>

                            <div class="md:col-span-2">
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Catatan Teknis Survey</span>
                                <span class="block text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap">{{ $customer->latestSurvey->survey_note ?? '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 5 PANEL: Laporan Pemasangan & Perangkat -->
                    <div id="step-panel-5" class="step-panel space-y-6 hidden">
                    {{-- Form sendiri — submit lewat tombol footer "Aktivasi Laporan Speedtest"
                         (form="form-pemasangan"). Lihat catatan gerbang di kepala file. --}}
                    <form id="form-pemasangan" action="{{ route('customers.installation.pemasangan', $customer->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ $returnTo }}">
                        <input type="hidden" name="started_at" id="hidden_started_at">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">5. LAPORAN PEMASANGAN &amp; PERANGKAT</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Masukkan data spesifikasi perangkat aktif, distribusi jaringan, dan foto bukti pemasangan.</p>
                        </div>

                        @php
                            $failedInstallation = $customer->installations()->where('installation_status', 'failed')->latest()->first();

                            // Prefill step 5 dari data yang SUDAH TERSIMPAN (bukan cuma old()) —
                            // "Aktivasi" me-redirect balik ke halaman ini, kalau field cuma baca
                            // old() maka begitu redirect (bukan validation-failure) semua field
                            // ini keliatan kosong lagi padahal sudah kesimpen di DB. Lihat catatan
                            // gerbang $pemasanganComplete di kepala file.
                            $dev = $customer->customerDevice;
                            $tech5 = $customer->customerTechnicalDetail;
                        @endphp
                        @if($failedInstallation)
                            <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 rounded-xl p-4 space-y-2 shadow-sm">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">
                                    <x-ui.icon name="triangle-alert" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                    Instruksi Revisi Pemasangan
                                </div>
                                <p class="text-[11px] font-semibold text-slate-600 dark:text-slate-300">Catatan dari Admin Verifikasi:</p>
                                <p class="bg-white dark:bg-slate-900 border border-amber-100 dark:border-amber-900/50 p-3 rounded-lg data-text text-xs text-slate-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed">{{ $failedInstallation->installation_note }}</p>
                            </div>
                        @endif

                        <div class="space-y-5">

                            <!-- Sub-section: Perangkat Aktif -->
                            <div class="space-y-3">
                                <h5 class="text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="cpu" class="w-4 h-4" /> Informasi Perangkat Aktif
                                </h5>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                    <div>
                                        <label for="device_type" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Jenis Perangkat <span class="text-rose-500">*</span></label>
                                        <select name="device_type" id="device_type" class="w-full text-xs font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                            <option value="ont" {{ old('device_type', $dev->device_type ?? 'ont') === 'ont' ? 'selected' : '' }}>ONT</option>
                                            <option value="modem" {{ old('device_type', $dev->device_type ?? '') === 'modem' ? 'selected' : '' }}>Modem</option>
                                            <option value="onu" {{ old('device_type', $dev->device_type ?? '') === 'onu' ? 'selected' : '' }}>ONU</option>
                                            <option value="router" {{ old('device_type', $dev->device_type ?? '') === 'router' ? 'selected' : '' }}>Router</option>
                                            <option value="other" {{ old('device_type', $dev->device_type ?? '') === 'other' ? 'selected' : '' }}>Lainnya</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="connection_mode" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Mode Koneksi <span class="text-rose-500">*</span></label>
                                        <select name="connection_mode" id="connection_mode" class="w-full text-xs font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                            <option value="pppoe" {{ old('connection_mode', $dev->connection_mode ?? 'pppoe') === 'pppoe' ? 'selected' : '' }}>PPPoE</option>
                                            <option value="bridge" {{ old('connection_mode', $dev->connection_mode ?? '') === 'bridge' ? 'selected' : '' }}>Bridge</option>
                                            <option value="static" {{ old('connection_mode', $dev->connection_mode ?? '') === 'static' ? 'selected' : '' }}>Static IP</option>
                                            <option value="dhcp" {{ old('connection_mode', $dev->connection_mode ?? '') === 'dhcp' ? 'selected' : '' }}>DHCP</option>
                                            <option value="other" {{ old('connection_mode', $dev->connection_mode ?? '') === 'other' ? 'selected' : '' }}>Lainnya</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="brand" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Merk Perangkat</label>
                                        <input type="text" name="brand" id="brand" value="{{ old('brand', $dev->brand ?? '') }}" class="w-full text-xs font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="ZTE / Huawei / FiberHome">
                                    </div>

                                    <div>
                                        <label for="model" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Tipe Model</label>
                                        <input type="text" name="model" id="model" value="{{ old('model', $dev->model ?? '') }}" class="w-full text-xs font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="Contoh: F609 / HG8245H">
                                    </div>

                                    <div>
                                        <label for="serial_number" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Serial Number (SN) <span class="text-rose-500">*</span></label>
                                        <input type="text" name="serial_number" id="serial_number" value="{{ old('serial_number', $dev->serial_number ?? '') }}" class="w-full text-xs data-text px-3 py-2 border @error('serial_number') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="ZTEGC1234567">
                                    </div>

                                    <div>
                                        <label for="mac_address" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">MAC Address</label>
                                        <input type="text" name="mac_address" id="mac_address" value="{{ old('mac_address', $dev->mac_address ?? '') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="00:11:22:33:44:55">
                                    </div>

                                    <div>
                                        <label for="pppoe_username" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Username PPPoE</label>
                                        <input type="text" name="pppoe_username" id="pppoe_username" value="{{ old('pppoe_username', $dev->pppoe_username ?? '') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="user_ponorogo_01">
                                    </div>

                                    <div>
                                        <label for="pppoe_password" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Password PPPoE</label>
                                        <input type="text" name="pppoe_password" id="pppoe_password" value="{{ old('pppoe_password', $dev->pppoe_password ?? '') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                    </div>

                                    <div class="md:col-span-2">
                                        <label for="ip_address" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">IP Address (Statik)</label>
                                        <input type="text" name="ip_address" id="ip_address" value="{{ old('ip_address', $dev->ip_address ?? '') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="192.168.x.x">
                                    </div>

                                    <div>
                                        <label for="wifi_ssid" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">SSID WiFi <span class="text-rose-500">*</span></label>
                                        <input type="text" name="wifi_ssid" id="wifi_ssid" value="{{ old('wifi_ssid', $dev->wifi_ssid ?? '') }}" class="w-full text-xs font-sans px-3 py-2 border @error('wifi_ssid') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="WHUSNET_Pelanggan">
                                    </div>

                                    <div>
                                        <label for="wifi_password" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Password WiFi <span class="text-rose-500">*</span></label>
                                        <input type="text" name="wifi_password" id="wifi_password" value="{{ old('wifi_password', $dev->wifi_password ?? '') }}" class="w-full text-xs font-sans px-3 py-2 border @error('wifi_password') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                    </div>
                                </div>
                            </div>

                            <!-- Sub-section: Distribusi Jaringan -->
                            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 space-y-3">
                                <h5 class="text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="workflow" class="w-4 h-4" /> Distribusi Jaringan (ODP / OLT)
                                </h5>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label for="odp_number" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Nomor ODP</label>
                                            <input type="text" name="odp_number" id="odp_number" value="{{ old('odp_number', $tech5->odp_number ?? $customer->latestSurvey->nearest_odp ?? '') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="ODP-01">
                                        </div>
                                        <div>
                                            <label for="odp_port" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Port ODP</label>
                                            <input type="text" name="odp_port" id="odp_port" value="{{ old('odp_port', $tech5->odp_port ?? '') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="Port 4">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <label for="olt_number" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Nomor OLT</label>
                                            <input type="text" name="olt_number" id="olt_number" value="{{ old('olt_number', $tech5->olt_number ?? '') }}" class="w-full text-xs data-text px-2.5 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                        </div>
                                        <div>
                                            <label for="olt_slot" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Slot OLT</label>
                                            <input type="text" name="olt_slot" id="olt_slot" value="{{ old('olt_slot', $tech5->olt_slot ?? '') }}" class="w-full text-xs data-text px-2.5 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                        </div>
                                        <div>
                                            <label for="olt_port" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Port OLT</label>
                                            <input type="text" name="olt_port" id="olt_port" value="{{ old('olt_port', $tech5->olt_port ?? '') }}" class="w-full text-xs data-text px-2.5 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label for="vlan" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">VLAN (Jaringan)</label>
                                            <input type="text" name="vlan" id="vlan" value="{{ old('vlan', $tech5->vlan ?? '') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="100">
                                        </div>
                                        <div>
                                            <label for="router_number" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Nomor Router</label>
                                            <input type="text" name="router_number" id="router_number" value="{{ old('router_number', $tech5->router_number ?? '') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="Distribusi">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="initial_attenuation" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Redaman Awal Pemasangan (dBm)</label>
                                        <input type="text" name="initial_attenuation" id="initial_attenuation" value="{{ old('initial_attenuation', $tech5->initial_attenuation ?? '') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="-19.5">
                                    </div>
                                </div>
                            </div>

                            <!-- Sub-section: Material Realita Terpakai -->
                            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60">
                                <label class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Perangkat Pasif / Material Terpakai Realita <span class="text-rose-500">*</span></label>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-3 leading-relaxed">Material yang <b>benar-benar dipakai</b> pada pemasangan ini — bukan estimasi. Daftar sudah diisi dari estimasi survey; ubah jumlahnya sesuai realita, tambah atau hapus baris bila perlu.</p>
                                <x-material-rows
                                    name="materials"
                                    :items="$items"
                                    :categories="$itemCategories"
                                    :rows="$materialRows"
                                    empty-label="Belum ada material dicatat. Klik Tambah Barang."
                                />
                            </div>

                            <!-- Sub-section: Alat kerja terpakai -->
                            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60">
                                <x-work-tool-checklist
                                    name="work_tools"
                                    :tools="$workTools"
                                    :rows="$workToolRows"
                                    label="Alat Kerja Yang Dipakai"
                                    hint="Sudah dicentang sesuai penilaian surveyor; sesuaikan dengan yang benar-benar dibawa. Ini peralatan kerja yang dibawa pulang — material yang ditinggal di pelanggan dicatat di daftar di atas."
                                />
                            </div>

                            <!-- Sub-section: Upload Bukti Pemasangan -->
                            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 space-y-3">
                                <h5 class="text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="camera" class="w-4 h-4" /> Foto Bukti Pemasangan &amp; Kontrak
                                </h5>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    @foreach ([
                                        ['field' => 'installation_photo', 'icon' => 'wrench', 'label' => 'FOTO PEMASANGAN', 'hint' => 'Lokasi/Router Terpasang', 'cta' => 'Pilih Foto Pemasangan', 'alt' => 'Preview Foto Pemasangan'],
                                        ['field' => 'contract_photo', 'icon' => 'file-text', 'label' => 'FOTO KONTRAK', 'hint' => 'Form Fisik Bertanda Tangan', 'cta' => 'Pilih Foto Kontrak', 'alt' => 'Preview Foto Kontrak'],
                                        ['field' => 'signature_photo', 'icon' => 'signature', 'label' => 'FOTO TTD PELANGGAN', 'hint' => 'Bukti Serah Terima', 'cta' => 'Pilih Foto TTD', 'alt' => 'Preview Foto TTD'],
                                    ] as $photo)
                                        @php $existingPath = $installation->{$photo['field']} ?? null; @endphp
                                        <div class="border-2 border-dashed @error($photo['field']) border-rose-400 bg-rose-50/20 @else border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 @enderror hover:border-sky-500 dark:hover:border-sky-400 rounded-xl p-4 text-center transition-all shadow-sm flex flex-col justify-between relative">
                                            {{-- Placeholder kosong — cuma tampil kalau belum ada foto tersimpan SAMA
                                                 SEKALI (belum pernah upload). Sekali sudah tersimpan, default view-nya
                                                 jadi thumbnail-dari-server (blok "sudah tersimpan" di bawah), bukan
                                                 placeholder kosong ini — supaya "Aktivasi" gagal karena field lain
                                                 lalu redirect balik gak bikin technician kira foto yang sudah
                                                 keupload hilang (file input emang gak bisa direfill browser, tapi
                                                 foto yang SUDAH TERSIMPAN tetap harus keliatan). --}}
                                            <div id="default-placeholder-{{ $photo['field'] }}" class="py-3 space-y-2 {{ $existingPath ? 'hidden' : '' }}">
                                                <div class="w-9 h-9 mx-auto rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center text-base border border-sky-200 dark:border-sky-800">
                                                    <x-ui.icon name="{{ $photo['icon'] }}" class="w-4 h-4" />
                                                </div>
                                                <div>
                                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">{{ $photo['label'] }} <span class="text-rose-500">*</span></span>
                                                    <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">{{ $photo['hint'] }}</span>
                                                </div>
                                            </div>

                                            {{-- Foto yang sudah tersimpan di server (upload sebelumnya) — beda dari
                                                 preview-container di bawah (itu preview file yang BARU dipilih di
                                                 browser, belum tentu tersubmit). --}}
                                            @if($existingPath)
                                                <div id="existing-preview-{{ $photo['field'] }}" class="py-2 flex flex-col items-center justify-center">
                                                    <div class="relative inline-block w-full">
                                                        <img class="max-h-28 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="{{ asset('storage/'.$existingPath) }}" alt="{{ $photo['alt'] }} (tersimpan)">
                                                    </div>
                                                    <span class="block text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-1.5">✓ Sudah Tersimpan</span>
                                                </div>
                                            @endif

                                            <div id="preview-container-{{ $photo['field'] }}" style="display: none;" class="py-2 flex flex-col items-center justify-center">
                                                <div class="relative inline-block w-full">
                                                    <img id="preview-img-{{ $photo['field'] }}" class="max-h-28 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="" alt="{{ $photo['alt'] }}">
                                                    <button type="button" onclick="clearFile('{{ $photo['field'] }}')" class="absolute -top-2 -right-2 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-5 h-5 flex items-center justify-center shadow-md focus:outline-none cursor-pointer" title="Hapus File">
                                                        <x-ui.icon name="x" class="w-2.5 h-2.5" />
                                                    </button>
                                                </div>
                                                <span class="block text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-1.5">✓ Terpilih</span>
                                            </div>

                                            <div class="mt-2">
                                                <input type="file" name="{{ $photo['field'] }}" id="{{ $photo['field'] }}" accept="image/*" capture="environment" class="hidden" data-has-existing="{{ $existingPath ? 'true' : 'false' }}" onchange="onFileChange('{{ $photo['field'] }}')">
                                                <label for="{{ $photo['field'] }}" class="block w-full text-center bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-semibold py-1.5 px-3 rounded-lg cursor-pointer transition-colors shadow-sm focus:outline-none">
                                                    {{ $existingPath ? 'Ganti Foto' : $photo['cta'] }}
                                                </label>
                                                <span id="file-label-{{ $photo['field'] }}" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1 font-mono truncate">{{ $existingPath ? 'Pakai foto tersimpan' : 'Belum ada file' }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Catatan Pemasangan -->
                            <div class="pt-2">
                                <label for="installation_note" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Catatan Pemasangan Teknisi</label>
                                <textarea name="installation_note" id="installation_note" rows="2" class="w-full text-xs font-sans px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="Tuliskan catatan teknis tambahan selama proses pemasangan...">{{ old('installation_note', $installation->installation_note ?? '') }}</textarea>
                            </div>
                        </div>
                    </form>
                    </div>

                    <!-- STEP 6 PANEL: Laporan Uji Koneksi (Speedtest) -->
                    <div id="step-panel-6" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">6. LAPORAN UJI KONEKSI (SPEEDTEST)</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Masukkan hasil pengujian kecepatan internet untuk memastikan koneksi pelanggan aktif &amp; stabil.</p>
                        </div>

                        @unless($pemasanganComplete)
                            {{-- Default step 6: title+deskripsi (di atas) + satu tombol Aktivasi.
                                 Tombol ini yang men-submit form-pemasangan (data step 5) — bukan
                                 link balik ke step 5. Validasi required step 5 dicek di JS
                                 (attemptActivate()); belum lengkap → Toast, gak submit. Server
                                 (storePemasangan/storeSpeedtest) tetap jadi penegak gerbang yang
                                 sesungguhnya. --}}
                            <div class="py-10 text-center bg-slate-50/70 dark:bg-slate-900/40 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                                <div class="w-12 h-12 mx-auto rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center mb-3">
                                    <x-ui.icon name="zap" class="w-5 h-5" />
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 max-w-xs mx-auto leading-relaxed">
                                    Lengkapi dulu data wajib di <strong>Laporan Pemasangan &amp; Perangkat</strong> (step 5), lalu tekan tombol di bawah ini.
                                </p>
                                <button type="button" onclick="attemptActivate()" class="mt-4 inline-flex items-center gap-1.5 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors text-xs font-semibold cursor-pointer shadow-sm">
                                    <x-ui.icon name="zap" class="w-3 h-3" /> Aktivasi Laporan Speedtest
                                </button>
                            </div>
                        @else
                        {{-- Form sendiri — submit lewat tombol footer "Simpan & Selesaikan
                             Pemasangan" (form="form-speedtest"). Ini titik penyelesaian
                             pemasangan (task complete + transisi workflow). --}}
                        <form id="form-speedtest" action="{{ route('customers.installation.speedtest', $customer->id) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                            @csrf
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            <input type="hidden" name="completed_at" id="hidden_completed_at">

                            <!-- Primary Speed Metrics Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-sky-50/40 dark:bg-sky-950/20 p-4 rounded-xl border border-sky-100 dark:border-sky-900/40">
                                <div>
                                    <label for="test_download" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-200">Speed Download (Mbps) <span class="text-rose-500">*</span></label>
                                    <div class="relative">
                                        <input type="number" step="0.01" name="test_download" id="test_download" value="{{ old('test_download') }}" class="w-full text-xs data-text font-bold px-3 py-2.5 pr-14 border @error('test_download') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="15.5">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400">Mbps</span>
                                    </div>
                                </div>

                                <div>
                                    <label for="test_upload" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-200">Speed Upload (Mbps) <span class="text-rose-500">*</span></label>
                                    <div class="relative">
                                        <input type="number" step="0.01" name="test_upload" id="test_upload" value="{{ old('test_upload') }}" class="w-full text-xs data-text font-bold px-3 py-2.5 pr-14 border @error('test_upload') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="15.0">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400">Mbps</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Secondary Metrics Grid -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                <div>
                                    <label for="latency_ms" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Latency (ms)</label>
                                    <input type="number" step="0.1" name="latency_ms" id="latency_ms" value="{{ old('latency_ms') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="4.2">
                                </div>

                                <div>
                                    <label for="jitter_ms" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Jitter (ms)</label>
                                    <input type="number" step="0.1" name="jitter_ms" id="jitter_ms" value="{{ old('jitter_ms') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="1.0">
                                </div>

                                <div>
                                    <label for="packet_loss_percent" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Packet Loss (%)</label>
                                    <input type="number" step="0.01" name="packet_loss_percent" id="packet_loss_percent" value="{{ old('packet_loss_percent') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="0">
                                </div>

                                <div>
                                    <label for="actual_attenuation" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Redaman Aktual (dBm)</label>
                                    <input type="text" name="actual_attenuation" id="actual_attenuation" value="{{ old('actual_attenuation') }}" class="w-full text-xs data-text px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="-19.8">
                                </div>
                            </div>

                            <!-- Foto Speedtest Upload Dropzone -->
                            <div class="pt-2">
                                <div class="border-2 border-dashed @error('speedtest_photo') border-rose-400 bg-rose-50/20 @else border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 @enderror hover:border-sky-500 dark:hover:border-sky-400 rounded-xl p-5 text-center transition-all shadow-sm flex flex-col justify-between relative">
                                    <div id="default-placeholder-speedtest_photo" class="py-3 space-y-2">
                                        <div class="w-10 h-10 mx-auto rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg border border-sky-200 dark:border-sky-800">
                                            <x-ui.icon name="gauge" class="w-4 h-4" />
                                        </div>
                                        <div>
                                            <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">FOTO BUKTI SPEEDTEST <span class="text-rose-500">*</span></span>
                                            <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Screenshot Aplikasi Speedtest (Ookla/Fast.com)</span>
                                        </div>
                                    </div>

                                    <div id="preview-container-speedtest_photo" style="display: none;" class="py-2 flex flex-col items-center justify-center">
                                        <div class="relative inline-block w-full">
                                            <img id="preview-img-speedtest_photo" class="max-h-36 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="" alt="Preview Foto Speedtest">
                                            <button type="button" onclick="clearFile('speedtest_photo')" class="absolute -top-2.5 -right-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md focus:outline-none cursor-pointer" title="Hapus File">
                                                <x-ui.icon name="x" class="w-3 h-3" />
                                            </button>
                                        </div>
                                        <span class="block text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-2">✓ Foto Speedtest Terpilih</span>
                                    </div>

                                    <div class="mt-2 max-w-xs mx-auto w-full">
                                        <input type="file" name="speedtest_photo" id="speedtest_photo" accept="image/*" capture="environment" class="hidden" onchange="onFileChange('speedtest_photo')">
                                        <label for="speedtest_photo" class="block w-full text-center bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-semibold py-2 px-3 rounded-lg cursor-pointer transition-colors shadow-sm focus:outline-none">
                                            Pilih Foto Speedtest
                                        </label>
                                        <span id="file-label-speedtest_photo" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1.5 font-mono truncate">Belum ada file</span>
                                    </div>
                                </div>
                            </div>

                        </form>
                        @endunless
                    </div>

                </div>

                <!-- BUTTONS NAVIGATION FOOTER -->
                <div class="px-4 sm:px-7 py-3.5 sm:py-4 bg-slate-50/90 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-700/60 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3 shrink-0">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button type="button" id="btn-prev" onclick="prevStep()" style="display: none;" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm">
                            <x-ui.icon name="chevron-left" class="w-2.5 h-2.5" /> Sebelumnya
                        </button>
                        <a href="{{ route('verifications.queue') }}" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-xs font-semibold cursor-pointer focus:outline-none text-center inline-flex items-center justify-center">
                            Batal
                        </a>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button type="button" id="btn-next" onclick="nextStep()" class="w-full sm:w-auto px-5 py-2.5 sm:py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm">
                            Lanjut <x-ui.icon name="chevron-right" class="w-2.5 h-2.5" />
                        </button>

                        {{-- Step 6: submit form-speedtest — SATU-SATUNYA titik penyelesaian pemasangan.
                             Tombol "Aktivasi" (submit form-pemasangan) ada DI DALAM panel step 6
                             sendiri (attemptActivate()), bukan di footer — lihat step-panel-6. --}}
                        <button type="button" onclick="handleSpeedtestSubmit()" id="btn-submit" style="display: none;" class="w-full sm:w-auto px-6 py-2.5 sm:py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm">
                            <x-ui.icon name="send" class="w-3 h-3" /> Simpan &amp; Selesaikan Pemasangan
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    /* ── Wizard Form Stepper & Live Timer Logic ── */
    let currentActiveStep = 1;
    const totalStepsCount = 6;

    // Step 1-4 read-only (data registrasi/survey); hanya 5 & 6 yang punya input.
    const inputSteps = [5, 6];
    const readOnlySteps = [1, 2, 3, 4];

    // Gerbang server-side (CustomerInstallationController::report()) — step 6
    // baru punya form kalau ini true. JS di sini cuma ikut menyesuaikan
    // tombol/nav; penegakan sesungguhnya ada di storeSpeedtest().
    const pemasanganComplete = @json($pemasanganComplete);

    // ═══════════════════════════════════════════════════════════
    // TIMER LOGIC
    // ═══════════════════════════════════════════════════════════
    let timerInterval = null;
    let timerSeconds = 0;

    @if($installation && $installation->started_at)
        const timerStartedAt = new Date("{{ $installation->started_at->toIso8601String() }}");

        // started_at dikirim balik apa adanya — sumber kebenarannya DB, bukan jam browser.
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
        // Format Laravel datetime: YYYY-MM-DD HH:MM:SS
        const pad = n => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    }

    function handlePemasanganSubmit() {
        // Belum menyelesaikan pemasangan — timer TIDAK berhenti di sini,
        // baru berhenti saat Laporan Speedtest disubmit (handleSpeedtestSubmit).
        document.getElementById('form-pemasangan').submit();
    }

    // Tombol Aktivasi di panel step 6 (default/terkunci) manggil ini.
    // Belum lengkap → Toast + gak submit apa-apa. Server (storePemasangan)
    // tetap menegakkan gerbang yang sama kalau ada yang lolos validasi klien.
    function attemptActivate() {
        const missing = getMissingRequiredFields(5);
        if (! hasAtLeastOneMaterialRow()) {
            missing.push('Perangkat Pasif / Material Terpakai');
        }
        if (missing.length > 0) {
            if (window.Toast) {
                window.Toast.warning('Laporan Pemasangan Belum Lengkap', 'Wajib diisi dulu: ' + missing.join(', '));
            }
            return;
        }
        handlePemasanganSubmit();
    }

    // storePemasangan() server wajib minimal satu baris material terpakai
    // (qty > 0). Dicek di sini juga — kalau lolos di browser tapi gagal di
    // server, redirect-back bikin komponen material-rows Alpine re-init dari
    // $materialRows ASLI (bukan input user), baris yang barusan diedit teknisi
    // hilang. Gerbang klien ini nyegah technician sampai ke titik itu.
    function hasAtLeastOneMaterialRow() {
        const qtyInputs = document.querySelectorAll('#form-pemasangan [name^="materials"][name$="[qty]"]');
        return Array.from(qtyInputs).some(el => parseFloat(el.value) > 0);
    }

    function handleSpeedtestSubmit() {
        // Timer berhenti tepat saat submit — completed_at harus mencerminkan itu.
        stopTimerAndGetCompletedAt();
        document.getElementById('form-speedtest').submit();
    }

    const formFields = {
        'pemasangan': {
            required: ['device_type', 'connection_mode', 'serial_number', 'wifi_ssid', 'wifi_password', 'installation_photo', 'contract_photo', 'signature_photo'],
            optional: ['brand', 'model', 'mac_address', 'pppoe_username', 'pppoe_password', 'ip_address', 'odp_number', 'odp_port', 'olt_number', 'olt_slot', 'olt_port', 'vlan', 'router_number', 'initial_attenuation', 'installation_note']
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
        // Dua form terpisah sekarang (form-pemasangan, form-speedtest) — step 6
        // gak selalu ada di DOM (terkunci = cuma placeholder, querySelectorAll
        // aman dapat NodeList kosong).
        const inputs = document.querySelectorAll('#form-pemasangan input, #form-pemasangan select, #form-pemasangan textarea, #form-speedtest input, #form-speedtest select, #form-speedtest textarea');
        inputs.forEach(input => {
            input.addEventListener('input', runLiveProgressUpdates);
            input.addEventListener('change', runLiveProgressUpdates);
        });

        updateWizardButtons();
        runLiveProgressUpdates();

        // Baru saja "Aktivasi" ditekan (redirect balik dengan ?activated=1) —
        // langsung arahkan ke step 6 supaya teknisi gak perlu klik manual.
        const params = new URLSearchParams(window.location.search);
        if (params.get('activated') === '1' && pemasanganComplete) {
            goToStep(6);
        }
    });

    /*
     * Sengaja pakai inline style, BUKAN class 'hidden': elemen-elemen ini juga
     * memakai utility display ('inline-flex' / 'flex'), dan di CSS Tailwind keduanya
     * utility display dengan specificity sama — yang belakangan menang, sehingga
     * 'hidden' tidak berefek. Inline style selalu menang.
     */
    function setElementVisible(el, visible) {
        if (! el) {
            return;
        }
        // String kosong = balik ke display dari class, bukan dipaksa 'block'.
        el.style.display = visible ? '' : 'none';
    }

    /*
     * Aturan tombol wizard — satu-satunya tempat visibilitas tombol ditentukan.
     * Step 1-5      : Sebelumnya (kecuali step 1) + Batal + Lanjut
     * Step 6        : Sebelumnya + Batal + Simpan & Selesaikan Pemasangan
     *                 (submit form-speedtest) — cuma kalau pemasanganComplete.
     *                 Kalau masih terkunci, gak ada tombol footer sama sekali:
     *                 tombol "Aktivasi" ada DI DALAM panel step 6 sendiri.
     * "Batal" selalu tampil, jadi tidak ikut diatur di sini.
     */
    function updateWizardButtons() {
        const isFirstStep = currentActiveStep === 1;
        const isStep6 = currentActiveStep === 6;

        setElementVisible(document.getElementById('btn-prev'), ! isFirstStep);
        setElementVisible(document.getElementById('btn-next'), ! isStep6);
        setElementVisible(document.getElementById('btn-submit'), isStep6 && pemasanganComplete);
    }

    /* File Change & Preview Helper */
    function onFileChange(fieldId) {
        const input = document.getElementById(fieldId);
        const label = document.getElementById('file-label-' + fieldId);
        const defaultPlaceholder = document.getElementById('default-placeholder-' + fieldId);
        const previewContainer = document.getElementById('preview-container-' + fieldId);
        const previewImg = document.getElementById('preview-img-' + fieldId);
        // Thumbnail foto yang SUDAH tersimpan di server (redirect pasca-Aktivasi)
        // — cuma ada di DOM kalau instalasi ini memang sudah punya foto itu.
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
            // Batal pilih file baru — balik ke thumbnail tersimpan kalau ada,
            // placeholder kosong kalau belum pernah upload sama sekali.
            label.textContent = hasExisting ? 'Pakai foto tersimpan' : 'Belum ada file';
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

    // Dipakai runLiveProgressUpdates (indikator nav) DAN attemptActivate
    // (gerbang klik tombol Aktivasi) — satu sumber kebenaran field wajib
    // yang "sudah terisi di browser", biar dua tempat itu gak bisa beda jawaban.
    function getMissingRequiredFields(step) {
        const config = formFields[stepKeys[step]];
        const missing = [];

        config.required.forEach(field => {
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

    /* Live Stepper Auditor & Progress Calculator */
    function runLiveProgressUpdates() {
        let totalRequiredFieldsCount = 0;
        let filledRequiredFieldsCount = 0;

        inputSteps.forEach(step => {
            // Step 6 terkunci = panelnya cuma placeholder, field-nya gak ada
            // di DOM sama sekali — lewati, updateStepNavStatus(6, ...) diurus
            // terpisah lewat updateLockedStep6Nav() di bawah.
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
            iconDiv.innerHTML = `<span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0"><x-ui.icon name="check" class="w-2.5 h-2.5" /></span>`;
            missingSpan.textContent = 'Semua terisi';

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-all group focus:outline-none";
            }
        }

        if (currentActiveStep === step) {
            navBtn.className = "w-full text-left p-3 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm";
        }
    }

    // Step 6 belum ada field-nya sama sekali di DOM selama terkunci — nav
    // kartunya ditandai "Terkunci" (bukan "Belum Lengkap") supaya jelas ini
    // gerbang berurutan, bukan sekadar field kosong yang lupa diisi.
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
            serial_number: 'Serial Number',
            wifi_ssid: 'SSID WiFi',
            wifi_password: 'Password WiFi',
            installation_photo: 'Foto Pemasangan',
            contract_photo: 'Foto Kontrak',
            signature_photo: 'Foto TTD Pelanggan',
            test_download: 'Speed Download',
            test_upload: 'Speed Upload',
            speedtest_photo: 'Foto Speedtest'
        };
        return labels[field] || field;
    }

    /* Stepper Page Switcher */
    function goToStep(stepNumber) {
        // Lompat langsung ke step 6 (nav sidebar / mobile stepper) tanpa step 5
        // lengkap → Toast, TETAP di step sekarang. Begitu step 5 lengkap, boleh
        // masuk step 6 (nampilin title+deskripsi+tombol Aktivasi) meski belum
        // disubmit — attemptActivate() yang benar-benar mengaktivasi.
        if (stepNumber === 6 && ! pemasanganComplete && currentActiveStep !== 6) {
            const missing = getMissingRequiredFields(5);
            if (missing.length > 0) {
                if (window.Toast) {
                    window.Toast.warning('Laporan Pemasangan Belum Lengkap', 'Isi dulu Laporan Pemasangan & Perangkat (step 5): ' + missing.join(', '));
                }
                return;
            }
        }

        document.getElementById('step-panel-' + currentActiveStep).classList.add('hidden');
        currentActiveStep = stepNumber;
        document.getElementById('step-panel-' + currentActiveStep).classList.remove('hidden');

        // Mobile stepper: step read-only tetap hijau, step input netral.
        for (let i = 1; i <= totalStepsCount; i++) {
            const mBtn = document.getElementById('mobile-step-btn-' + i);
            if (! mBtn) continue;

            if (i === currentActiveStep) {
                mBtn.className = "py-2.5 px-2 rounded-lg text-xs font-bold bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 transition-all flex items-center justify-center gap-1.5 shadow-sm";
            } else if (readOnlySteps.includes(i)) {
                mBtn.className = "py-2.5 px-2 rounded-lg text-xs font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 transition-all flex items-center justify-center gap-1.5";
            } else {
                mBtn.className = "py-2.5 px-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all flex items-center justify-center gap-1.5";
            }
        }

        updateWizardButtons();
        runLiveProgressUpdates();

        // Step read-only tidak lewat updateStepNavStatus(), jadi highlight-nya diurus di sini.
        readOnlySteps.forEach(step => {
            const navBtn = document.getElementById('step-nav-' + step);
            if (! navBtn) return;

            if (currentActiveStep === step) {
                navBtn.className = "w-full text-left p-3 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm";
            } else {
                navBtn.className = "w-full text-left p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-all group focus:outline-none";
            }
        });
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
