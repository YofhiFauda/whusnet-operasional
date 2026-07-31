@extends('layouts.app')

@section('title', 'Lapor Hasil Pemasangan - Whusnet Operasional')
@section('page_title', 'Lapor Hasil Pemasangan')

@section('content')
<!-- Breadcrumbs -->
<div class="mb-6">
    <nav class="flex text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider gap-2">
        <a href="{{ route('verifications.queue') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Antrean Pemasangan</a>
        <span>/</span>
        <span class="text-slate-600 dark:text-slate-300">Lapor Pemasangan</span>
    </nav>
</div>

<!-- Form Container -->
<form action="{{ route('customers.installation.store', $customer->id) }}" method="POST" enctype="multipart/form-data" id="wizard-form" class="space-y-6">
    @csrf
    <input type="hidden" name="started_at" id="hidden_started_at">
    <input type="hidden" name="completed_at" id="hidden_completed_at">

    <!-- TOP PANEL: Progress Bar -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Kelengkapan Laporan Pemasangan</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Semua data wajib akan divalidasi sebelum laporan disimpan</p>
            </div>
            <div class="text-right">
                <span id="progress-percentage" class="text-sm font-extrabold text-sky-600 dark:text-sky-400 data-text">0%</span>
                <span class="text-xs text-slate-400 dark:text-slate-500 block mt-0.5"><span id="filled-fields-count" class="data-text">0</span> dari <span id="total-fields-count" class="data-text">6</span> field terisi</span>
            </div>
        </div>
        <!-- Progress bar background -->
        <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-3.5 overflow-hidden border border-slate-200/50 dark:border-slate-600/50">
            <div id="progress-bar-fill" class="bg-gradient-to-r from-sky-500 to-sky-600 dark:from-sky-600 dark:to-sky-500 h-full w-0 transition-all duration-500 ease-out"></div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">
        
        <!-- LEFT COLUMN: Stepper -->
        <div class="lg:col-span-1 flex flex-col gap-4">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 shadow-sm space-y-5">
                <h4 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Tahapan Laporan</h4>
                
                <div class="space-y-4">
                    <!-- Step 1 -->
                    <button type="button" onclick="goToStep(1)" id="step-nav-1" class="w-full text-left p-3.5 rounded-lg border-2 border-sky-600 dark:border-sky-500 bg-sky-50/30 dark:bg-sky-950/30 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-1">
                                <span class="h-5 w-5 rounded-full bg-green-500 flex items-center justify-center text-white">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">1. Data Diri Pelanggan</span>
                                <span class="text-[9px] font-bold block mt-1 uppercase tracking-wider text-green-600 dark:text-green-400">Lengkap</span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 2 -->
                    <button type="button" onclick="goToStep(2)" id="step-nav-2" class="w-full text-left p-3.5 rounded-lg border border-green-200 dark:border-green-800/50 bg-green-50/10 dark:bg-green-950/10 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-2">
                                <span class="h-5 w-5 rounded-full bg-green-500 flex items-center justify-center text-white">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">2. Dokumen Lampiran</span>
                                <span class="text-[9px] font-bold block mt-1 uppercase tracking-wider text-green-600 dark:text-green-400">Lengkap</span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 3 -->
                    <button type="button" onclick="goToStep(3)" id="step-nav-3" class="w-full text-left p-3.5 rounded-lg border border-green-200 dark:border-green-800/50 bg-green-50/10 dark:bg-green-950/10 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-3">
                                <span class="h-5 w-5 rounded-full bg-green-500 flex items-center justify-center text-white">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">3. Layanan & Paket</span>
                                <span class="text-[9px] font-bold block mt-1 uppercase tracking-wider text-green-600 dark:text-green-400">Lengkap</span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 4 -->
                    <button type="button" onclick="goToStep(4)" id="step-nav-4" class="w-full text-left p-3.5 rounded-lg border border-green-200 dark:border-green-800/50 bg-green-50/10 dark:bg-green-950/10 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-4">
                                <span class="h-5 w-5 rounded-full bg-green-500 flex items-center justify-center text-white">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">4. Laporan Survey</span>
                                <span class="text-[9px] font-bold block mt-1 uppercase tracking-wider text-green-600 dark:text-green-400">Lengkap</span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 5 -->
                    <button type="button" onclick="goToStep(5)" id="step-nav-5" class="w-full text-left p-3.5 rounded-lg border border-red-100 dark:border-red-900/30 bg-red-50/10 dark:bg-red-950/10 hover:bg-red-50/20 dark:hover:bg-red-950/20 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-5">
                                <span class="h-5 w-5 rounded-full bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 flex items-center justify-center text-red-600 dark:text-red-400">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">5. Laporan Pemasangan</span>
                                <span id="step-nav-status-5" class="text-[9px] font-bold block mt-1 uppercase tracking-wider text-red-600 dark:text-red-400">Belum Lengkap</span>
                                <span id="step-nav-missing-5" class="text-[9px] text-slate-400 dark:text-slate-500 block mt-1 leading-relaxed whitespace-pre-wrap">Wajib diisi</span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 6 -->
                    <button type="button" onclick="goToStep(6)" id="step-nav-6" class="w-full text-left p-3.5 rounded-lg border border-red-100 dark:border-red-900/30 bg-red-50/10 dark:bg-red-950/10 hover:bg-red-50/20 dark:hover:bg-red-950/20 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-6">
                                <span class="h-5 w-5 rounded-full bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 flex items-center justify-center text-red-600 dark:text-red-400">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">6. Laporan Uji (Speedtest)</span>
                                <span id="step-nav-status-6" class="text-[9px] font-bold block mt-1 uppercase tracking-wider text-red-600 dark:text-red-400">Belum Lengkap</span>
                                <span id="step-nav-missing-6" class="text-[9px] text-slate-400 dark:text-slate-500 block mt-1 leading-relaxed whitespace-pre-wrap"></span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Wizard Steps Form Panels -->
        <div class="lg:col-span-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm overflow-hidden min-h-[480px] flex flex-col justify-between">
            
            <!-- FORM BODY -->
            <div class="p-6 md:p-8 flex-1">
                
                <!-- Errors Block ditangani otomatis oleh global Component Toast (x-toast) -->

                <!-- STEP 1 PANEL: Data Diri (Read-Only) -->
                <div id="step-panel-1" class="step-panel space-y-6">
                    <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">1. IDENTITAS PELANGGAN & ALAMAT</h4>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Data diri pelanggan yang diisi pada saat registrasi.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 p-5 rounded-lg border border-slate-100 dark:border-slate-700/50">
                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">NAMA LENGKAP</span>
                            <span class="block text-sm font-bold text-slate-800 dark:text-slate-200">{{ $customer->full_name }}</span>
                        </div>

                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">NOMOR IDENTITAS (NIK)</span>
                            <span class="block text-sm font-mono font-medium text-slate-800 dark:text-slate-200">{{ $customer->identity_number ?? '-' }}</span>
                        </div>

                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">NOMOR HP UTAMA</span>
                            <span class="block text-sm font-mono text-slate-800 dark:text-slate-200">{{ $customer->primary_phone ?? $customer->phone ?? '-' }}</span>
                        </div>

                        <div class="md:col-span-2 mt-2 pt-4 border-t border-slate-200 dark:border-slate-700">
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">ALAMAT INSTALASI LENGKAP</span>
                            <span class="block text-sm font-bold text-slate-800 dark:text-slate-200">{{ $customer->address }}</span>
                            <span class="block mt-1 text-slate-600 dark:text-slate-400">
                                Kel. {{ $customer->village->name ?? '-' }}, 
                                Kec. {{ $customer->district->name ?? '-' }}, 
                                {{ $customer->city->name ?? '-' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- STEP 2 PANEL: Dokumen Lampiran -->
                <div id="step-panel-2" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">2. DOKUMEN LAMPIRAN</h4>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Dokumen dan foto lokasi yang dilampirkan sebelumnya.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- Foto KTP (dari registrasi) -->
                        <div class="border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 rounded-lg p-5 flex flex-col justify-between shadow-sm relative opacity-90">
                            @if($customer->foto_ktp)
                                <div class="text-center py-2 flex flex-col items-center justify-center">
                                    <div class="relative inline-block w-full">
                                        <img class="max-h-32 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto cursor-pointer hover:opacity-90" src="{{ asset('storage/' . $customer->foto_ktp) }}" alt="Preview Foto KTP" onclick="window.open('{{ asset('storage/' . $customer->foto_ktp) }}', '_blank')">
                                    </div>
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 mt-3 uppercase">FOTO KTP (Dari Registrasi)</span>
                                    <span class="block text-[10px] text-emerald-600 dark:text-emerald-400 mt-1 font-medium">✓ Sudah diupload</span>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <span class="block text-xs font-bold text-amber-600 dark:text-amber-400 uppercase">FOTO KTP KOSONG</span>
                                    <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-1">Belum diupload saat registrasi</span>
                                </div>
                            @endif
                        </div>

                        <!-- Foto Rumah -->
                        <div class="border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 rounded-lg p-5 flex flex-col justify-between shadow-sm relative opacity-90">
                            @if($customer->latestSurvey && $customer->latestSurvey->house_photo)
                                <div class="text-center py-2 flex flex-col items-center justify-center">
                                    <div class="relative inline-block w-full">
                                        <img class="max-h-32 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="{{ asset('storage/' . $customer->latestSurvey->house_photo) }}" alt="Preview Foto Rumah">
                                    </div>
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 mt-3 uppercase">FOTO RUMAH</span>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">FOTO RUMAH KOSONG</span>
                                </div>
                            @endif
                        </div>

                        <!-- Foto ODP -->
                        <div class="border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 rounded-lg p-5 flex flex-col justify-between shadow-sm relative opacity-90">
                            @if($customer->latestSurvey && $customer->latestSurvey->survey_photo)
                                <div class="text-center py-2 flex flex-col items-center justify-center">
                                    <div class="relative inline-block w-full">
                                        <img class="max-h-32 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="{{ asset('storage/' . $customer->latestSurvey->survey_photo) }}" alt="Preview Foto ODP">
                                    </div>
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 mt-3 uppercase">FOTO ODP TERDEKAT</span>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">FOTO ODP KOSONG</span>
                                </div>
                            @endif
                        </div>

                    </div>
                </div>

                <!-- STEP 3 PANEL: Layanan & Paket -->
                <div id="step-panel-3" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">3. LAYANAN & PAKET LAYANAN INTERNET</h4>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Paket yang dipilih pada tahap registrasi.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 p-5 rounded-lg border border-slate-100 dark:border-slate-700/50">
                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">PAKET INTERNET</span>
                            <span class="block text-sm font-bold text-slate-800 dark:text-slate-200">{{ $customer->internetPackage->package_code ?? '-' }} - {{ $customer->internetPackage->name ?? 'Belum Dipilih' }}</span>
                        </div>

                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">BIAYA BULANAN DASAR</span>
                            <span class="block text-sm font-mono font-bold text-slate-800 dark:text-slate-200">Rp {{ number_format($customer->internetPackage->monthly_price ?? 0, 0, ',', '.') }}</span>
                        </div>

                        <div class="pt-4 border-t border-slate-200 dark:border-slate-700 md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">JENIS KONTRAK</span>
                                <span class="block text-sm text-slate-800 dark:text-slate-200 uppercase">{{ $customer->customerService->contract_type ?? 'Sewa' }}</span>
                            </div>

                            <div>
                                <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">MASA KONTRAK</span>
                                <span class="block text-sm text-slate-800 dark:text-slate-200">{{ $customer->customerService->contract_period_months ?? 12 }} Bulan</span>
                            </div>

                            <div>
                                <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">DISKON PROMOSI</span>
                                <span class="block text-sm font-mono text-slate-800 dark:text-slate-200">Rp {{ number_format($customer->discount_amount ?? 0, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4 PANEL: Laporan Survey -->
                <div id="step-panel-4" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">4. LAPORAN SURVEY LAPANGAN</h4>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Data teknis hasil pantauan di lapangan saat survey.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 p-5 rounded-lg border border-slate-100 dark:border-slate-700/50">
                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">ODP TERDEKAT</span>
                            <span class="block text-sm font-mono text-slate-800 dark:text-slate-200">{{ $customer->latestSurvey->nearest_odp ?? '-' }}</span>
                        </div>
                        
                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">ESTIMASI KABEL (METER)</span>
                            <span class="block text-sm font-mono text-slate-800 dark:text-slate-200">{{ $customer->latestSurvey->cable_estimation_meter ?? '0' }} Meter</span>
                        </div>

                        @if($customer->latestSurvey?->requested_installation_date)
                        <div class="md:col-span-2">
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">TANGGAL REQUEST PEMASANGAN PELANGGAN</span>
                            <span class="block text-sm font-mono font-bold text-slate-800 dark:text-slate-200">{{ \App\Support\IndonesianDate::date($customer->latestSurvey->requested_installation_date) }}</span>
                        </div>
                        @endif

                        <div class="md:col-span-2 pt-4 border-t border-slate-200 dark:border-slate-700">
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400 dark:text-slate-500">CATATAN SURVEY / TINGKAT KESULITAN</span>
                            <span class="block text-sm text-slate-800 dark:text-slate-200 whitespace-pre-wrap">{{ $customer->latestSurvey->survey_note ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- STEP 5 PANEL: Laporan Pemasangan -->
                <div id="step-panel-5" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">5. LAPORAN PEMASANGAN & PERANGKAT</h4>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Masukkan data perangkat dan detail teknis instalasi jaringan.</p>
                    </div>

                    @php
                        $failedInstallation = $customer->installations()->where('installation_status', 'failed')->latest()->first();
                    @endphp
                    @if($failedInstallation)
                    <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 text-amber-800 dark:text-amber-200 text-xs rounded-lg p-4 mb-4 flex flex-col gap-1.5 shadow-sm">
                        <div class="flex items-center gap-2 font-bold uppercase tracking-wider text-amber-900 dark:text-amber-300 text-sm">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            INSTRUKSI REVISI PEMASANGAN
                        </div>
                        <p class="font-semibold text-slate-700 dark:text-slate-300 mt-1">Catatan dari Admin Verifikasi:</p>
                        <p class="bg-white dark:bg-slate-900 border border-amber-100 dark:border-amber-900/50 p-3 rounded-md font-mono text-slate-700 dark:text-slate-300 whitespace-pre-wrap mt-1 leading-relaxed">{{ $failedInstallation->installation_note }}</p>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
                        <!-- Status Pemasangan -->
                        <!-- <div class="md:col-span-2">
                            <label for="installation_status" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">STATUS PEMASANGAN <span class="text-red-500">*</span></label>
                            <select name="installation_status" id="installation_status" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                                <option value="completed" selected>Selesai</option>
                                <option value="failed">Gagal / Revisi</option>
                            </select>
                        </div> -->

                        <div class="md:col-span-2 pt-4 mt-2">
                            <h5 class="font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-4">Informasi Perangkat</h5>
                        </div>

                        <!-- Jenis Perangkat -->
                        <div>
                            <label for="device_type" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">JENIS PERANGKAT <span class="text-red-500">*</span></label>
                            <select name="device_type" id="device_type" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                                <option value="ont" selected>ONT</option>
                                <option value="modem">Modem</option>
                                <option value="onu">ONU</option>
                                <option value="router">Router</option>
                                <option value="other">Lainnya</option>
                            </select>
                        </div>

                        <!-- Mode Koneksi -->
                        <div>
                            <label for="connection_mode" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">MODE KONEKSI <span class="text-red-500">*</span></label>
                            <select name="connection_mode" id="connection_mode" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                                <option value="pppoe" selected>PPPoE</option>
                                <option value="bridge">Bridge</option>
                                <option value="static">Static IP</option>
                                <option value="dhcp">DHCP</option>
                                <option value="other">Lainnya</option>
                            </select>
                        </div>

                        <!-- Merk & Tipe -->
                        <div>
                            <label for="brand" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">MERK</label>
                            <input type="text" name="brand" id="brand" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25" placeholder="ZTE / Huawei / Dll">
                        </div>
                        <div>
                            <label for="model" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">TIPE</label>
                            <input type="text" name="model" id="model" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: F609">
                        </div>

                        <!-- Serial & MAC -->
                        <div>
                            <label for="serial_number" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">SERIAL NUMBER <span class="text-red-500">*</span></label>
                            <input type="text" name="serial_number" id="serial_number" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                        </div>
                        <div>
                            <label for="mac_address" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">MAC ADDRESS</label>
                            <input type="text" name="mac_address" id="mac_address" class="w-full text-sm font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25" placeholder="00:11:22:33:44:55">
                        </div>

                        <!-- PPPoE & IP -->
                        <div>
                            <label for="pppoe_username" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">USERNAME PPPOE</label>
                            <input type="text" name="pppoe_username" id="pppoe_username" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                        </div>
                        <div>
                            <label for="pppoe_password" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">PASSWORD PPPOE</label>
                            <input type="text" name="pppoe_password" id="pppoe_password" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                        </div>
                        <div class="md:col-span-2">
                            <label for="ip_address" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">IP ADDRESS (STATIK)</label>
                            <input type="text" name="ip_address" id="ip_address" class="w-full text-sm font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25" placeholder="192.168.x.x">
                        </div>

                        <!-- WiFi -->
                        <div>
                            <label for="wifi_ssid" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">SSID WIFI <span class="text-red-500">*</span></label>
                            <input type="text" name="wifi_ssid" id="wifi_ssid" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                        </div>
                        <div>
                            <label for="wifi_password" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">PASSWORD WIFI <span class="text-red-500">*</span></label>
                            <input type="text" name="wifi_password" id="wifi_password" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                        </div>

                        <div class="md:col-span-2 border-t border-slate-100 dark:border-slate-700/50 pt-4 mt-2">
                            <h5 class="font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-4">Informasi Distribusi Jaringan (ODP/OLT)</h5>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="odp_number" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">NOMOR ODP</label>
                                <input type="text" name="odp_number" id="odp_number" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25" value="{{ $customer->latestSurvey->nearest_odp ?? '' }}">
                            </div>
                            <div>
                                <label for="odp_port" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">NOMOR PORT ODP</label>
                                <input type="text" name="odp_port" id="odp_port" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label for="olt_number" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">NOMOR OLT</label>
                                <input type="text" name="olt_number" id="olt_number" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                            </div>
                            <div>
                                <label for="olt_slot" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">SLOT OLT</label>
                                <input type="text" name="olt_slot" id="olt_slot" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                            </div>
                            <div>
                                <label for="olt_port" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">PORT OLT</label>
                                <input type="text" name="olt_port" id="olt_port" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="vlan" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">VLAN (JARINGAN)</label>
                                <input type="text" name="vlan" id="vlan" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                            </div>
                            <div>
                                <label for="router_number" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">NOMOR ROUTER</label>
                                <input type="text" name="router_number" id="router_number" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25" placeholder="Diisi manual untuk Distribusi">
                            </div>
                        </div>

                        <div>
                            <label for="initial_attenuation" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">REDAMAN AWAL PEMASANGAN (dBm)</label>
                            <input type="text" name="initial_attenuation" id="initial_attenuation" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                        </div>

                        <div class="md:col-span-2 border-t border-slate-100 dark:border-slate-700/50 pt-4 mt-2">
                            <h5 class="font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Perangkat Pasif Terpakai <span class="text-red-500">*</span></h5>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-3 leading-relaxed font-normal">Material yang <b>benar-benar dipakai</b> pada pemasangan ini — bukan estimasi. Daftar di bawah sudah diisi dari estimasi survey; ubah jumlahnya sesuai realita, tambah atau hapus baris bila perlu.</p>
                            <x-material-rows
                                name="materials"
                                :items="$items"
                                :rows="$materialRows"
                                empty-label="Belum ada material dicatat. Klik Tambah Barang."
                            />
                        </div>

                        <div class="md:col-span-2 mt-4 border-t border-slate-100 dark:border-slate-700/50 pt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="installation_photo" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">FOTO PEMASANGAN LAPANGAN <span class="text-red-500">*</span></label>
                                <input type="file" name="installation_photo" id="installation_photo" accept="image/*" capture="environment" class="w-full text-sm text-slate-700 dark:text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-sky-50 dark:file:bg-sky-950/40 file:text-sky-700 dark:file:text-sky-300 hover:file:bg-sky-100 dark:hover:file:bg-sky-900/50 cursor-pointer">
                            </div>
                            <div>
                                <label for="contract_photo" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">FOTO KONTRAK <span class="text-red-500">*</span></label>
                                <input type="file" name="contract_photo" id="contract_photo" accept="image/*" capture="environment" class="w-full text-sm text-slate-700 dark:text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-sky-50 dark:file:bg-sky-950/40 file:text-sky-700 dark:file:text-sky-300 hover:file:bg-sky-100 dark:hover:file:bg-sky-900/50 cursor-pointer">
                            </div>
                            <div>
                                <label for="signature_photo" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">FOTO TTD PELANGGAN <span class="text-red-500">*</span></label>
                                <input type="file" name="signature_photo" id="signature_photo" accept="image/*" capture="environment" class="w-full text-sm text-slate-700 dark:text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-sky-50 dark:file:bg-sky-950/40 file:text-sky-700 dark:file:text-sky-300 hover:file:bg-sky-100 dark:hover:file:bg-sky-900/50 cursor-pointer">
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label for="installation_note" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">CATATAN PEMASANGAN</label>
                            <textarea name="installation_note" id="installation_note" rows="2" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25"></textarea>
                        </div>
                    </div>
                </div>

                <!-- STEP 6 PANEL: Laporan Uji (Speedtest) -->
                <div id="step-panel-6" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">6. LAPORAN UJI KONEKSI (SPEEDTEST)</h4>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Masukkan hasil pengujian kecepatan untuk memastikan koneksi stabil.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6 text-xs font-semibold text-slate-500 dark:text-slate-400">
                        
                        <div class="grid grid-cols-2 gap-4 md:col-span-2 bg-slate-50 dark:bg-slate-900/50 p-4 rounded-lg border border-slate-100 dark:border-slate-700/50">
                            <div>
                                <label for="test_download" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">SPEED DOWNLOAD (Mbps) <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" name="test_download" id="test_download" class="w-full text-sm font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                            </div>
                            <div>
                                <label for="test_upload" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">SPEED UPLOAD (Mbps) <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" name="test_upload" id="test_upload" class="w-full text-sm font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                            </div>
                        </div>

                        <div>
                            <label for="latency_ms" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">LATENCY (ms)</label>
                            <input type="number" step="0.1" name="latency_ms" id="latency_ms" class="w-full text-sm font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                        </div>
                        
                        <div>
                            <label for="jitter_ms" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">JITTER (ms)</label>
                            <input type="number" step="0.1" name="jitter_ms" id="jitter_ms" class="w-full text-sm font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                        </div>

                        <div>
                            <label for="packet_loss_percent" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">PACKET LOSS (%)</label>
                            <input type="number" step="0.01" name="packet_loss_percent" id="packet_loss_percent" class="w-full text-sm font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                        </div>
                        
                        <div>
                            <label for="actual_attenuation" class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">REDAMAN AKTUAL (dBm)</label>
                            <input type="text" name="actual_attenuation" id="actual_attenuation" class="w-full text-sm font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25">
                        </div>

                        <div class="md:col-span-2 mt-2">
                            <label class="block mb-2 uppercase tracking-wide text-slate-700 dark:text-slate-300">FOTO HASIL SPEEDTEST <span class="text-red-500">*</span></label>
                            <input type="file" name="speedtest_photo" id="speedtest_photo" accept="image/*" capture="environment" class="w-full text-sm text-slate-700 dark:text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-sky-50 dark:file:bg-sky-950/40 file:text-sky-700 dark:file:text-sky-300 hover:file:bg-sky-100 dark:hover:file:bg-sky-900/50 cursor-pointer" required>
                        </div>
                    </div>
                </div>

            </div>

            <!-- BUTTONS NAVIGATION FOOTER -->
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <button type="button" id="btn-prev" onclick="prevStep()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-xs font-semibold cursor-pointer hidden focus:outline-none">
                        Sebelumnya
                    </button>
                </div>
                
                <div class="flex gap-2">
                    <a href="{{ route('verifications.queue') }}" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-xs font-semibold cursor-pointer focus:outline-none">
                        Batal
                    </a>
                    
                    <button type="button" id="btn-next" onclick="nextStep()" class="px-4 py-2 bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 dark:hover:bg-sky-600 text-white rounded-md transition-colors text-xs font-semibold cursor-pointer focus:outline-none">
                        Lanjut
                    </button>

                    <button type="button" onclick="handleSubmit()" id="btn-submit" class="px-4 py-2 bg-emerald-600 dark:bg-emerald-500 hover:bg-emerald-700 dark:hover:bg-emerald-600 text-white rounded-md transition-colors text-xs font-semibold cursor-pointer hidden focus:outline-none">
                        Simpan & Laporkan Pemasangan
                    </button>
                </div>
            </div>

        </div>
    </div>
</form>

@endsection

@section('scripts')
<script>
    let currentActiveStep = 1;
    const totalStepsCount = 6;

    // ═══════════════════════════════════════════════════════════
    // TIMER LOGIC
    // ═══════════════════════════════════════════════════════════
    let timerInterval = null;
    let timerStartedAt = null;  // JS Date object
    let timerSeconds = 0;
    let timerIsRunning = false;

    // Initialize timer if already started in database
    @if($installation && $installation->started_at)
        timerIsRunning = true;
        timerStartedAt = new Date("{{ $installation->started_at->toIso8601String() }}");
        
        // Fill hidden input started_at
        const hiddenStartedAtInit = document.getElementById('hidden_started_at');
        if (hiddenStartedAtInit) {
            hiddenStartedAtInit.value = "{{ $installation->started_at->format('Y-m-d H:i:s') }}";
        }
        
        const nowInit = new Date();
        timerSeconds = Math.max(0, Math.floor((nowInit - timerStartedAt) / 1000));
        
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
        // Format: YYYY-MM-DD HH:MM:SS (Laravel datetime format)
        const pad = n => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    }

    function formatTimeDisplay(date) {
        const pad = n => String(n).padStart(2, '0');
        return `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    }

    function handleSubmit() {
        // Hentikan timer dan catat waktu selesai, lalu submit
        stopTimerAndGetCompletedAt();
        document.getElementById('wizard-form').submit();
    }

    const formFields = {
        'pemasangan': {
            required: ['installation_status', 'device_type', 'connection_mode', 'serial_number', 'wifi_ssid', 'wifi_password', 'installation_photo', 'contract_photo', 'signature_photo'],
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

    function updateFieldRequirements() {
        const statusEl = document.getElementById('installation_status');
        if (!statusEl) return;
        
        const status = statusEl.value;
        const isCompleted = status === 'completed';

        const requiredFields = [
            'device_type', 'connection_mode', 'serial_number', 'wifi_ssid', 'wifi_password',
            'installation_photo', 'contract_photo', 'signature_photo',
            'test_download', 'test_upload', 'speedtest_photo'
        ];

        requiredFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                if (isCompleted) {
                    el.setAttribute('required', 'required');
                    const label = el.closest('div')?.querySelector('label');
                    if (label && !label.innerHTML.includes('*')) {
                        label.innerHTML += ' <span class="text-red-500">*</span>';
                    }
                } else {
                    el.removeAttribute('required');
                    const label = el.closest('div')?.querySelector('label');
                    if (label) {
                        label.innerHTML = label.innerHTML.replace(/\s*<span class="text-red-500">\*<\/span>/g, '');
                    }
                }
            }
        });

        // Update JS requirements dynamically
        if (isCompleted) {
            formFields.pemasangan.required = ['installation_status', 'device_type', 'connection_mode', 'serial_number', 'wifi_ssid', 'wifi_password', 'installation_photo', 'contract_photo', 'signature_photo'];
            formFields.uji.required = ['test_download', 'test_upload', 'speedtest_photo'];
        } else {
            // If failed/revisi, only installation_status is strictly required to proceed
            formFields.pemasangan.required = ['installation_status'];
            formFields.uji.required = [];
        }

        runLiveProgressUpdates();
    }

    document.addEventListener("DOMContentLoaded", function() {
        const inputs = document.querySelectorAll('#wizard-form input, #wizard-form select, #wizard-form textarea');
        inputs.forEach(input => {
            input.addEventListener('input', runLiveProgressUpdates);
            input.addEventListener('change', runLiveProgressUpdates);
        });

        const statusEl = document.getElementById('installation_status');
        if (statusEl) {
            statusEl.addEventListener('change', updateFieldRequirements);
        }

        updateFieldRequirements();
    });

    function runLiveProgressUpdates() {
        let totalRequiredFieldsCount = 0;
        let filledRequiredFieldsCount = 0;

        // Iterate steps that require input
        [5, 6].forEach(step => {
            const stepKey = stepKeys[step];
            const config = formFields[stepKey];
            let requiredMissing = [];
            let optionalMissing = [];

            // Check required fields
            config.required.forEach(field => {
                totalRequiredFieldsCount++;
                const el = document.getElementById(field);
                if (el) {
                    if (el.type === 'file') {
                        if (el.files && el.files.length > 0) {
                            filledRequiredFieldsCount++;
                        } else {
                            requiredMissing.push(getLabelName(field));
                        }
                    } else if (el.value && el.value.trim() !== "") {
                        filledRequiredFieldsCount++;
                    } else {
                        requiredMissing.push(getLabelName(field));
                    }
                }
            });

            updateStepNavStatus(step, requiredMissing, optionalMissing);
        });

        // Update overall progress bar
        const progressPercentage = totalRequiredFieldsCount > 0 ? Math.round((filledRequiredFieldsCount / totalRequiredFieldsCount) * 100) : 0;
        document.getElementById('progress-percentage').textContent = progressPercentage + '%';
        document.getElementById('filled-fields-count').textContent = filledRequiredFieldsCount;
        document.getElementById('total-fields-count').textContent = totalRequiredFieldsCount;
        document.getElementById('progress-bar-fill').style.width = progressPercentage + '%';
    }

    function updateStepNavStatus(step, requiredMissing, optionalMissing) {
        const navBtn = document.getElementById('step-nav-' + step);
        const iconDiv = document.getElementById('step-nav-icon-' + step);
        const statusSpan = document.getElementById('step-nav-status-' + step);
        const missingSpan = document.getElementById('step-nav-missing-' + step);

        if (!navBtn || !iconDiv || !statusSpan || !missingSpan) return;

        iconDiv.innerHTML = '';
        missingSpan.textContent = '';

        if (requiredMissing.length > 0) {
            statusSpan.textContent = 'Belum Lengkap';
            statusSpan.className = 'text-[9px] font-bold block mt-1 uppercase tracking-wider text-red-600 dark:text-red-400';
            
            iconDiv.innerHTML = `<span class="h-5 w-5 rounded-full bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 flex items-center justify-center text-red-600 dark:text-red-400">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </span>`;

            missingSpan.textContent = 'Wajib diisi: ' + requiredMissing.join(', ');
            
            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-red-100 dark:border-red-900/30 bg-red-50/10 dark:bg-red-950/10 hover:bg-red-50/20 dark:hover:bg-red-950/20 transition-all group focus:outline-none";
            }
        } else {
            statusSpan.textContent = 'Lengkap';
            statusSpan.className = 'text-[9px] font-bold block mt-1 uppercase tracking-wider text-green-600 dark:text-green-400';
            
            iconDiv.innerHTML = `<span class="h-5 w-5 rounded-full bg-green-500 flex items-center justify-center text-white">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </span>`;

            missingSpan.textContent = 'Semua terisi';

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-green-200 dark:border-green-800/50 bg-green-50/10 dark:bg-green-950/10 hover:bg-green-50/20 dark:hover:bg-green-950/20 transition-all group focus:outline-none";
            }
        }

        if (currentActiveStep === step) {
            navBtn.className = "w-full text-left p-3.5 rounded-lg border-2 border-sky-600 dark:border-sky-500 bg-sky-50/30 dark:bg-sky-950/30 transition-all group focus:outline-none";
        }
    }

    function getLabelName(field) {
        const labels = {
            installation_status: 'Status Pemasangan',
            device_type: 'Jenis Perangkat',
            connection_mode: 'Mode Koneksi',
            serial_number: 'Serial Number',
            wifi_ssid: 'SSID WiFi',
            wifi_password: 'Password WiFi',
            test_download: 'Speed Download',
            test_upload: 'Speed Upload',
            speedtest_photo: 'Foto Speedtest'
        };
        return labels[field] || field;
    }

    function goToStep(stepNumber) {
        document.getElementById('step-panel-' + currentActiveStep).classList.add('hidden');
        currentActiveStep = stepNumber;
        document.getElementById('step-panel-' + currentActiveStep).classList.remove('hidden');

        if (currentActiveStep === 1) {
            document.getElementById('btn-prev').classList.add('hidden');
        } else {
            document.getElementById('btn-prev').classList.remove('hidden');
        }

        if (currentActiveStep === totalStepsCount) {
            document.getElementById('btn-next').classList.add('hidden');
            document.getElementById('btn-submit').classList.remove('hidden');
        } else {
            document.getElementById('btn-next').classList.remove('hidden');
            document.getElementById('btn-submit').classList.add('hidden');
        }

        runLiveProgressUpdates();
        
        // Update styling of steps
        [1, 2, 3, 4, 5, 6].forEach(step => {
            const navBtn = document.getElementById('step-nav-' + step);
            if (currentActiveStep === step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border-2 border-sky-600 dark:border-sky-500 bg-sky-50/30 dark:bg-sky-950/30 transition-all group focus:outline-none";
            } else if ([1, 2, 3, 4].includes(step)) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-green-200 dark:border-green-800/50 bg-green-50/10 dark:bg-green-950/10 transition-all group focus:outline-none";
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
