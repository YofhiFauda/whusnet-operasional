@extends('layouts.app')

@section('title', 'Lapor Data Survey Pelanggan - Whusnet Operasional')
@section('page_title', 'Lapor Data Survey')

@section('content')
<!-- Breadcrumbs -->
<div class="mb-6">
    <nav class="flex text-xs font-semibold text-slate-400 uppercase tracking-wider gap-2">
        <a href="{{ route('surveys.queue') }}" class="hover:text-slate-700 transition-colors">Antrean Survey</a>
        <span>/</span>
        <span class="text-slate-600">Lapor Data Survey</span>
    </nav>
</div>

<!-- Form Container -->
<form action="{{ route('customers.survey.store', $customer->id) }}" method="POST" enctype="multipart/form-data" id="wizard-form" class="space-y-6">
    @csrf
    
    <input type="hidden" name="survey_status" id="survey_status_input" value="completed">

    <!-- TOP PANEL: Progress Bar -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Kelengkapan Laporan Survey</h3>
                <p class="text-xs text-slate-500 mt-0.5">Semua data wajib akan divalidasi sebelum laporan disimpan</p>
            </div>
            <div class="text-right">
                <span id="progress-percentage" class="text-sm font-extrabold text-sky-600 data-text">0%</span>
                <span class="text-xs text-slate-400 block mt-0.5"><span id="filled-fields-count" class="data-text">0</span> dari <span id="total-fields-count" class="data-text">4</span> field terisi</span>
            </div>
        </div>
        <!-- Progress bar background -->
        <div class="w-full bg-slate-100 rounded-full h-3.5 overflow-hidden border border-slate-200/50">
            <div id="progress-bar-fill" class="bg-gradient-to-r from-sky-500 to-sky-600 h-full w-0 transition-all duration-500 ease-out"></div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">
        
        <!-- LEFT COLUMN: Stepper -->
        <div class="lg:col-span-1 flex flex-col gap-4">
            <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm space-y-5">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tahapan Laporan</h4>
                
                <div class="space-y-4">
                    <!-- Step 1 -->
                    <button type="button" onclick="goToStep(1)" id="step-nav-1" class="w-full text-left p-3.5 rounded-lg border-2 border-sky-600 bg-sky-50/30 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-1">
                                <span class="h-5 w-5 rounded-full bg-green-500 flex items-center justify-center text-white">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-800">1. Data Diri Pelanggan</span>
                                <span class="text-[9px] font-bold block mt-1 uppercase tracking-wider text-green-600">Lengkap</span>
                                <span class="text-[9px] text-slate-400 block mt-1">Data Read-only</span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 2 -->
                    <button type="button" onclick="goToStep(2)" id="step-nav-2" class="w-full text-left p-3.5 rounded-lg border border-red-100 bg-red-50/10 hover:bg-red-50/20 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-2">
                                <span class="h-5 w-5 rounded-full bg-red-50 border border-red-200 flex items-center justify-center text-red-600">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 group-hover:text-slate-900">2. Dokumen Lampiran</span>
                                <span id="step-nav-status-2" class="text-[9px] font-bold block mt-1 uppercase tracking-wider text-red-600">Belum Lengkap</span>
                                <span id="step-nav-missing-2" class="text-[9px] text-slate-400 block mt-1 leading-relaxed whitespace-pre-wrap">Wajib diisi: Foto Rumah dan Foto ODP Terdekat.</span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 3 -->
                    <button type="button" onclick="goToStep(3)" id="step-nav-3" class="w-full text-left p-3.5 rounded-lg border border-green-200 bg-green-50/10 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-3">
                                <span class="h-5 w-5 rounded-full bg-green-500 flex items-center justify-center text-white">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 group-hover:text-slate-900">3. Layanan & Paket</span>
                                <span class="text-[9px] font-bold block mt-1 uppercase tracking-wider text-green-600">Lengkap</span>
                                <span class="text-[9px] text-slate-400 block mt-1">Data Read-only</span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 4 -->
                    <button type="button" onclick="goToStep(4)" id="step-nav-4" class="w-full text-left p-3.5 rounded-lg border border-red-100 bg-red-50/10 hover:bg-red-50/20 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-4">
                                <span class="h-5 w-5 rounded-full bg-red-50 border border-red-200 flex items-center justify-center text-red-600">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 group-hover:text-slate-900">4. Laporan Survey</span>
                                <span id="step-nav-status-4" class="text-[9px] font-bold block mt-1 uppercase tracking-wider text-red-600">Belum Lengkap</span>
                                <span id="step-nav-missing-4" class="text-[9px] text-slate-400 block mt-1 leading-relaxed whitespace-pre-wrap"></span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Wizard Steps Form Panels -->
        <div class="lg:col-span-3 bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden min-h-[480px] flex flex-col justify-between">
            
            <!-- FORM BODY -->
            <div class="p-6 md:p-8 flex-1">
                
                <!-- Errors Block ditangani otomatis oleh global Component Toast (x-toast) -->

                <!-- STEP 1 PANEL: Data Diri (Read-Only) -->
                <div id="step-panel-1" class="step-panel space-y-6">
                    <div class="border-b border-slate-100 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">1. IDENTITAS PELANGGAN & ALAMAT</h4>
                        <p class="text-xs text-slate-400 mt-1">Data diri pelanggan yang diisi pada saat registrasi.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-slate-500 bg-slate-50 p-5 rounded-lg border border-slate-100">
                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">NAMA LENGKAP</span>
                            <span class="block text-sm font-bold text-slate-800">{{ $customer->full_name }}</span>
                        </div>

                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">NOMOR IDENTITAS (NIK)</span>
                            <span class="block text-sm font-mono font-medium text-slate-800">{{ $customer->identity_number ?? '-' }}</span>
                        </div>

                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">JENIS KELAMIN</span>
                            <span class="block text-sm text-slate-800">{{ $customer->gender ?? '-' }}</span>
                        </div>

                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">NOMOR HP UTAMA</span>
                            <span class="block text-sm font-mono text-slate-800">{{ $customer->primary_phone ?? $customer->phone ?? '-' }}</span>
                        </div>

                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">NOMOR HP ALTERNATIF</span>
                            <span class="block text-sm font-mono text-slate-800">{{ $customer->alternative_phone ?? '-' }}</span>
                        </div>

                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">ALAMAT EMAIL</span>
                            <span class="block text-sm text-slate-800">{{ $customer->email ?? '-' }}</span>
                        </div>

                        <div class="md:col-span-2 mt-2 pt-4 border-t border-slate-200">
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">ALAMAT INSTALASI LENGKAP</span>
                            <span class="block text-sm font-bold text-slate-800">{{ $customer->address }}</span>
                            <span class="block mt-1 text-slate-600">
                                Kel. {{ $customer->village->name ?? '-' }}, 
                                Kec. {{ $customer->district->name ?? '-' }}, 
                                {{ $customer->city->name ?? '-' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 md:col-span-1 mt-2">
                            <div>
                                <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">LATITUDE</span>
                                <span class="block text-sm font-mono text-slate-800">{{ $customer->latitude ?? '-' }}</span>
                            </div>
                            <div>
                                <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">LONGITUDE</span>
                                <span class="block text-sm font-mono text-slate-800">{{ $customer->longitude ?? '-' }}</span>
                            </div>
                        </div>
                        
                        <div class="mt-2 md:col-span-1">
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">POP CABANG</span>
                            <span class="block text-sm text-slate-800 font-bold">{{ $customer->pop->name ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- STEP 2 PANEL: Dokumen Lampiran -->
                <div id="step-panel-2" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">2. UPLOAD DOKUMEN LAMPIRAN</h4>
                        <p class="text-xs text-slate-400 mt-1">Lihat dokumen KTP yang terlampir dan tambahkan Foto Rumah serta ODP Terdekat.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        
                        <!-- Foto KTP (Read Only dari Registrasi) -->
                        <div class="border border-slate-200 bg-slate-50 rounded-lg p-5 flex flex-col justify-between shadow-sm relative opacity-90">
                            @if($customer->foto_ktp)
                                <div class="text-center py-2 flex flex-col items-center justify-center">
                                    <div class="relative inline-block w-full">
                                        <img class="max-h-32 max-w-full rounded-lg object-contain border border-slate-200 shadow-sm mx-auto cursor-pointer hover:opacity-90"
                                             src="{{ asset('storage/' . $customer->foto_ktp) }}"
                                             alt="Preview Foto KTP"
                                             onclick="window.open('{{ asset('storage/' . $customer->foto_ktp) }}', '_blank')">
                                    </div>
                                    <span class="block text-xs font-bold text-slate-700 mt-3 uppercase">FOTO KTP (Dari Registrasi)</span>
                                    <span class="block text-[10px] text-emerald-600 mt-1 font-medium">✓ Sudah diupload</span>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M21 12h-6m6 4h-6" />
                                    </svg>
                                    <span class="block text-xs font-bold text-slate-700 mt-3 uppercase">FOTO KTP KOSONG</span>
                                    <span class="block text-[10px] text-amber-600 mt-1">Pelanggan belum upload KTP saat registrasi</span>
                                </div>
                            @endif
                        </div>

                        <!-- Foto Rumah (Input Baru) -->
                        <div class="border border-slate-200 rounded-lg p-5 flex flex-col justify-between hover:border-sky-300 transition-colors shadow-sm relative bg-white">
                            <div id="default-placeholder-house_photo" class="text-center py-4">
                                <svg class="mx-auto h-10 w-10 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                <span class="block text-xs font-bold text-slate-700 mt-3">FOTO RUMAH PELANGGAN <span class="text-red-500">*</span></span>
                                <span class="block text-[10px] text-slate-400 mt-1">Wajib Diisi</span>
                            </div>

                            <div id="preview-container-house_photo" class="hidden text-center py-2 flex flex-col items-center justify-center">
                                <div class="relative inline-block w-full">
                                    <img id="preview-img-house_photo" class="max-h-28 max-w-full rounded-lg object-contain border border-slate-200 shadow-sm mx-auto" src="" alt="Preview Foto Rumah">
                                    <button type="button" onclick="clearFile('house_photo')" class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-1 shadow-md hover:scale-105 transition-transform focus:outline-none" title="Hapus File">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <span class="block text-xs font-bold text-slate-700 mt-2 uppercase">PREVIEW FOTO RUMAH</span>
                            </div>

                            <div class="mt-4">
                                <input type="file" name="house_photo" id="house_photo" accept="image/*" capture="environment" class="hidden" onchange="onFileChange('house_photo')">
                                <label for="house_photo" class="block w-full text-center bg-sky-50 border border-sky-200 hover:bg-sky-100 hover:border-sky-300 text-sky-700 text-xs font-bold py-2 px-3 rounded cursor-pointer transition-colors">
                                    Pilih Foto Rumah
                                </label>
                                <span id="file-label-house_photo" class="block text-[10px] text-slate-500 text-center mt-2 font-mono truncate">Belum ada file dipilih</span>
                            </div>
                        </div>

                        <!-- Foto ODP (Input Baru) -->
                        <div class="border border-slate-200 rounded-lg p-5 flex flex-col justify-between hover:border-sky-300 transition-colors shadow-sm relative bg-white">
                            <div id="default-placeholder-survey_photo" class="text-center py-4">
                                <svg class="mx-auto h-10 w-10 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                <span class="block text-xs font-bold text-slate-700 mt-3">FOTO ODP / JALUR <span class="text-red-500">*</span></span>
                                <span class="block text-[10px] text-slate-400 mt-1">Wajib Diisi</span>
                            </div>

                            <div id="preview-container-survey_photo" class="hidden text-center py-2 flex flex-col items-center justify-center">
                                <div class="relative inline-block w-full">
                                    <img id="preview-img-survey_photo" class="max-h-28 max-w-full rounded-lg object-contain border border-slate-200 shadow-sm mx-auto" src="" alt="Preview Foto ODP">
                                    <button type="button" onclick="clearFile('survey_photo')" class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-1 shadow-md hover:scale-105 transition-transform focus:outline-none" title="Hapus File">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <span class="block text-xs font-bold text-slate-700 mt-2 uppercase">PREVIEW FOTO ODP</span>
                            </div>

                            <div class="mt-4">
                                <input type="file" name="survey_photo" id="survey_photo" accept="image/*" capture="environment" class="hidden" onchange="onFileChange('survey_photo')">
                                <label for="survey_photo" class="block w-full text-center bg-sky-50 border border-sky-200 hover:bg-sky-100 hover:border-sky-300 text-sky-700 text-xs font-bold py-2 px-3 rounded cursor-pointer transition-colors">
                                    Pilih Foto ODP
                                </label>
                                <span id="file-label-survey_photo" class="block text-[10px] text-slate-500 text-center mt-2 font-mono truncate">Belum ada file dipilih</span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- STEP 3 PANEL: Layanan & Paket -->
                <div id="step-panel-3" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">3. LAYANAN & PAKET LAYANAN INTERNET</h4>
                        <p class="text-xs text-slate-400 mt-1">Paket yang dipilih pada tahap registrasi.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-slate-500 bg-slate-50 p-5 rounded-lg border border-slate-100">
                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">PAKET INTERNET</span>
                            <span class="block text-sm font-bold text-slate-800">{{ $customer->internetPackage->package_code ?? '-' }} - {{ $customer->internetPackage->name ?? 'Belum Dipilih' }}</span>
                        </div>

                        <div>
                            <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">BIAYA BULANAN DASAR</span>
                            <span class="block text-sm font-mono font-bold text-slate-800">Rp {{ number_format($customer->internetPackage->monthly_price ?? 0, 0, ',', '.') }}</span>
                        </div>

                        <div class="pt-4 border-t border-slate-200 md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">JENIS KONTRAK</span>
                                <span class="block text-sm text-slate-800 uppercase">{{ $customer->customerService->contract_type ?? 'Sewa' }}</span>
                            </div>

                            <div>
                                <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">MASA KONTRAK</span>
                                <span class="block text-sm text-slate-800">{{ $customer->customerService->contract_period_months ?? 12 }} Bulan</span>
                            </div>

                            <div>
                                <span class="block mb-1 uppercase tracking-wide text-[10px] text-slate-400">DISKON PROMOSI</span>
                                <span class="block text-sm font-mono text-slate-800">Rp {{ number_format($customer->discount_amount ?? 0, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4 PANEL: Laporan Survey -->
                <div id="step-panel-4" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">4. LAPORAN SURVEY LAPANGAN</h4>
                        <p class="text-xs text-slate-400 mt-1">Masukkan data teknis hasil pantauan di lapangan.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-slate-500">
                        <!-- ODP Terdekat -->
                        <div>
                            <label for="nearest_odp" class="block mb-2 uppercase tracking-wide">ODP TERDEKAT <span class="text-red-500">*</span></label>
                            <input type="text" name="nearest_odp" id="nearest_odp" value="{{ old('nearest_odp') }}" class="w-full text-sm font-mono px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: ODP-BBD-01">
                        </div>
                        
                        <!-- Estimasi Kabel -->
                        <div>
                            <label for="cable_estimation_meter" class="block mb-2 uppercase tracking-wide">ESTIMASI KABEL (METER) <span class="text-red-500">*</span></label>
                            <input type="number" name="cable_estimation_meter" id="cable_estimation_meter" min="0" value="{{ old('cable_estimation_meter') }}" class="w-full text-sm font-mono px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="0">
                        </div>

                        <!-- Tingkat Kesulitan -->
                        <div>
                            <label for="difficulty_level" class="block mb-2 uppercase tracking-wide">TINGKAT KESULITAN <span class="text-red-500">*</span></label>
                            <select name="difficulty_level" id="difficulty_level" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                                <option value="" disabled selected>Pilih Tingkat Kesulitan</option>
                                <option value="MUDAH" {{ old('difficulty_level') === 'MUDAH' ? 'selected' : '' }}>MUDAH</option>
                                <option value="SEDANG" {{ old('difficulty_level') === 'SEDANG' ? 'selected' : '' }}>SEDANG</option>
                                <option value="SULIT" {{ old('difficulty_level') === 'SULIT' ? 'selected' : '' }}>SULIT</option>
                            </select>
                        </div>

                        <!-- Alat Tambahan -->
                        <div class="md:col-span-2">
                            <label for="required_tools" class="block mb-2 uppercase tracking-wide">ALAT TAMBAHAN / KHUSUS</label>
                            <textarea name="required_tools" id="required_tools" rows="2" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Cth: Tangga ekstra panjang, Bor tembus dinding tebal, dll">{{ old('required_tools') }}</textarea>
                        </div>

                        <!-- Catatan Teknis -->
                        <div class="md:col-span-2">
                            <label for="survey_note" class="block mb-2 uppercase tracking-wide">CATATAN TEKNIS SURVEY</label>
                            <textarea name="survey_note" id="survey_note" rows="3" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Tuliskan kendala teknis atau informasi penting untuk teknisi instalasi...">{{ old('survey_note') }}</textarea>
                        </div>
                    </div>
                </div>

            </div>

            <!-- BUTTONS NAVIGATION FOOTER -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                <div>
                    <button type="button" id="btn-prev" onclick="prevStep()" class="px-4 py-2 border border-slate-200 rounded-md bg-white text-slate-700 hover:bg-slate-50 transition-colors text-xs font-semibold cursor-pointer hidden focus:outline-none">
                        Sebelumnya
                    </button>
                </div>
                
                <div class="flex gap-2">
                    <a href="{{ route('surveys.queue') }}" class="px-4 py-2 border border-slate-200 rounded-md bg-white text-slate-700 hover:bg-slate-50 transition-colors text-xs font-semibold cursor-pointer focus:outline-none">
                        Batal
                    </a>
                    
                    <button type="button" id="btn-next" onclick="nextStep()" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold cursor-pointer focus:outline-none">
                        Lanjut
                    </button>

                    <button type="submit" id="btn-submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md transition-colors text-xs font-semibold cursor-pointer hidden focus:outline-none">
                        Simpan Laporan Survey
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
    const totalStepsCount = 4;

    // Define wizard fields configurations (only valid for input validation steps)
    const formFields = {
        'dokumen': {
            required: ['house_photo', 'survey_photo'],
            optional: []
        },
        'laporan': {
            required: ['nearest_odp', 'cable_estimation_meter', 'difficulty_level'],
            optional: ['required_tools', 'survey_note']
        }
    };

    const stepKeys = {
        2: 'dokumen',
        4: 'laporan'
    };

    document.addEventListener("DOMContentLoaded", function() {
        const inputs = document.querySelectorAll('#wizard-form input, #wizard-form select, #wizard-form textarea');
        inputs.forEach(input => {
            input.addEventListener('input', runLiveProgressUpdates);
            input.addEventListener('change', runLiveProgressUpdates);
        });

        runLiveProgressUpdates();
    });

    // File selection UI update helper
    function onFileChange(fieldId) {
        const input = document.getElementById(fieldId);
        const label = document.getElementById('file-label-' + fieldId);
        const defaultPlaceholder = document.getElementById('default-placeholder-' + fieldId);
        const previewContainer = document.getElementById('preview-container-' + fieldId);
        const previewImg = document.getElementById('preview-img-' + fieldId);

        if (input.files && input.files.length > 0) {
            const file = input.files[0];
            label.textContent = file.name;
            input.setAttribute('data-populated', 'true');

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) {
                        previewImg.src = e.target.result;
                    }
                    if (defaultPlaceholder) defaultPlaceholder.classList.add('hidden');
                    if (previewContainer) previewContainer.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        } else {
            label.textContent = "Belum ada file dipilih";
            input.removeAttribute('data-populated');
            if (defaultPlaceholder) defaultPlaceholder.classList.remove('hidden');
            if (previewContainer) previewContainer.classList.add('hidden');
            if (previewImg) {
                previewImg.src = '';
            }
        }
        runLiveProgressUpdates();
    }

    // Clear file selection helper
    function clearFile(fieldId) {
        const input = document.getElementById(fieldId);
        if (input) {
            input.value = '';
            onFileChange(fieldId);
        }
    }

    function runLiveProgressUpdates() {
        let totalRequiredFieldsCount = 0;
        let filledRequiredFieldsCount = 0;

        // Iterate steps that require input
        [2, 4].forEach(step => {
            const stepKey = stepKeys[step];
            const config = formFields[stepKey];
            let requiredMissing = [];
            let optionalMissing = [];

            // Check required fields
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

            // Check optional fields
            config.optional.forEach(field => {
                const el = document.getElementById(field);
                if (el && el.value.trim() === "") {
                    optionalMissing.push(getLabelName(field));
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
            statusSpan.className = 'text-[9px] font-bold block mt-1 uppercase tracking-wider text-red-600';
            
            iconDiv.innerHTML = `<span class="h-5 w-5 rounded-full bg-red-50 border border-red-200 flex items-center justify-center text-red-600">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </span>`;

            missingSpan.textContent = 'Wajib diisi: ' + requiredMissing.join(', ');
            
            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-red-100 bg-red-50/10 hover:bg-red-50/20 transition-all group focus:outline-none";
            }
        } else {
            statusSpan.textContent = 'Lengkap';
            statusSpan.className = 'text-[9px] font-bold block mt-1 uppercase tracking-wider text-green-600';
            
            iconDiv.innerHTML = `<span class="h-5 w-5 rounded-full bg-green-500 flex items-center justify-center text-white">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </span>`;

            missingSpan.textContent = optionalMissing.length > 0 ? 'Beberapa opsional kosong' : 'Semua terisi';

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-green-200 bg-green-50/10 hover:bg-green-50/20 transition-all group focus:outline-none";
            }
        }

        if (currentActiveStep === step) {
            navBtn.className = "w-full text-left p-3.5 rounded-lg border-2 border-sky-600 bg-sky-50/30 transition-all group focus:outline-none";
        }
    }

    function getLabelName(field) {
        const labels = {
            house_photo: 'Foto Rumah',
            survey_photo: 'Foto ODP Terdekat',
            nearest_odp: 'ODP Terdekat',
            cable_estimation_meter: 'Estimasi Kabel',
            difficulty_level: 'Tingkat Kesulitan',
            required_tools: 'Alat Tambahan',
            survey_note: 'Catatan Teknis'
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
            document.getElementById('btn-failed').classList.remove('hidden');
        } else {
            document.getElementById('btn-next').classList.remove('hidden');
            document.getElementById('btn-submit').classList.add('hidden');
            document.getElementById('btn-failed').classList.add('hidden');
        }

        runLiveProgressUpdates();
        
        // Ensure steps 1 and 3 are correctly highlighted when active
        [1, 3].forEach(step => {
            const navBtn = document.getElementById('step-nav-' + step);
            if (currentActiveStep === step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border-2 border-sky-600 bg-sky-50/30 transition-all group focus:outline-none";
            } else {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-green-200 bg-green-50/10 transition-all group focus:outline-none";
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

