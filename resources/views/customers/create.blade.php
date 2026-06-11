@extends('layouts.app')

@section('title', 'Tambah Pelanggan Baru - Whusnet Operasional')
@section('page_title', 'Tambah Pelanggan Baru')

@section('content')
<!-- Breadcrumbs -->
<div class="mb-6">
    <nav class="flex text-xs font-semibold text-slate-400 uppercase tracking-wider gap-2">
        <a href="/customers" class="hover:text-slate-700 transition-colors">Daftar Pelanggan</a>
        <span>/</span>
        <span class="text-slate-600">Registrasi Pelanggan Baru</span>
    </nav>
</div>

<!-- Form Container -->
<form action="/customers" method="POST" enctype="multipart/form-data" id="wizard-form" class="space-y-6">
    @csrf

    <!-- TOP PANEL: Progress Bar -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Kelengkapan Formulir Registrasi</h3>
                <p class="text-xs text-slate-500 mt-0.5">Semua data akan divalidasi sebelum disimpan ke database</p>
            </div>
            <div class="text-right">
                <span id="progress-percentage" class="text-sm font-extrabold text-sky-600 data-text">0%</span>
                <span class="text-xs text-slate-400 block mt-0.5"><span id="filled-fields-count" class="data-text">0</span> dari <span class="data-text">25</span> field terisi</span>
            </div>
        </div>
        <!-- Progress bar background -->
        <div class="w-full bg-slate-100 rounded-full h-3.5 overflow-hidden border border-slate-200/50">
            <div id="progress-bar-fill" class="bg-gradient-to-r from-sky-500 to-sky-600 h-full w-0 transition-all duration-500 ease-out"></div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">
        
        <!-- LEFT COLUMN: Stepper & Completeness Checklist -->
        <div class="lg:col-span-1 flex flex-col gap-4">
            <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm space-y-5">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tahapan Formulir</h4>
                
                <div class="space-y-4">
                    <!-- Step 1 Trigger -->
                    <button type="button" onclick="goToStep(1)" id="step-nav-1" class="w-full text-left p-3.5 rounded-lg border border-sky-600 bg-sky-50/20 hover:bg-sky-50/40 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-1">
                                <!-- Will be inserted by JS -->
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-800">1. Data Diri & Wilayah</span>
                                <span id="step-nav-status-1" class="text-[9px] font-bold block mt-1 uppercase tracking-wider">Mengevaluasi...</span>
                                <span id="step-nav-missing-1" class="text-[9px] text-slate-400 block mt-1 leading-relaxed whitespace-pre-wrap"></span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 2 Trigger -->
                    <button type="button" onclick="goToStep(2)" id="step-nav-2" class="w-full text-left p-3.5 rounded-lg border border-slate-100 bg-white hover:bg-slate-50 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-2">
                                <!-- Will be inserted by JS -->
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 group-hover:text-slate-900">2. Dokumen Lampiran</span>
                                <span id="step-nav-status-2" class="text-[9px] font-bold block mt-1 uppercase tracking-wider">Mengevaluasi...</span>
                                <span id="step-nav-missing-2" class="text-[9px] text-slate-400 block mt-1 leading-relaxed whitespace-pre-wrap"></span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 3 Trigger -->
                    <button type="button" onclick="goToStep(3)" id="step-nav-3" class="w-full text-left p-3.5 rounded-lg border border-slate-100 bg-white hover:bg-slate-50 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-3">
                                <!-- Will be inserted by JS -->
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 group-hover:text-slate-900">3. Layanan & Paket</span>
                                <span id="step-nav-status-3" class="text-[9px] font-bold block mt-1 uppercase tracking-wider">Mengevaluasi...</span>
                                <span id="step-nav-missing-3" class="text-[9px] text-slate-400 block mt-1 leading-relaxed whitespace-pre-wrap"></span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 4 Trigger -->
                    <button type="button" onclick="goToStep(4)" id="step-nav-4" class="w-full text-left p-3.5 rounded-lg border border-slate-100 bg-white hover:bg-slate-50 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-4">
                                <!-- Will be inserted by JS -->
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 group-hover:text-slate-900">4. Info Referral</span>
                                <span id="step-nav-status-4" class="text-[9px] font-bold block mt-1 uppercase tracking-wider">Mengevaluasi...</span>
                                <span id="step-nav-missing-4" class="text-[9px] text-slate-400 block mt-1 leading-relaxed whitespace-pre-wrap"></span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 5 Trigger -->
                    <button type="button" onclick="goToStep(5)" id="step-nav-5" class="w-full text-left p-3.5 rounded-lg border border-slate-100 bg-white hover:bg-slate-50 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-5">
                                <!-- Will be inserted by JS -->
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-slate-700 group-hover:text-slate-900">5. Operasional & Teknis</span>
                                <span id="step-nav-status-5" class="text-[9px] font-bold block mt-1 uppercase tracking-wider">Mengevaluasi...</span>
                                <span id="step-nav-missing-5" class="text-[9px] text-slate-400 block mt-1 leading-relaxed whitespace-pre-wrap"></span>
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
                
                <!-- Errors Block -->
                @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-xs rounded-md p-4 space-y-1">
                    <span class="font-bold block">Registrasi Gagal! Mohon koreksi kesalahan berikut:</span>
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <!-- STEP 1 PANEL: Data Diri & Wilayah -->
                <div id="step-panel-1" class="step-panel space-y-6">
                    <div class="border-b border-slate-100 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">1. IDENTITAS PELANGGAN & ALAMAT</h4>
                        <p class="text-xs text-slate-400 mt-1">Masukkan data diri lengkap dan wilayah instalasi pelanggan</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-slate-500">
                        <div>
                            <label for="full_name" class="block mb-2 uppercase tracking-wide">NAMA LENGKAP <span class="text-red-500">*</span></label>
                            <input type="text" name="full_name" id="full_name" value="{{ old('full_name') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: Budi Santoso">
                        </div>

                        <div>
                            <label for="identity_number" class="block mb-2 uppercase tracking-wide">NOMOR IDENTITAS (NIK) <span class="text-red-500">*</span></label>
                            <input type="text" name="identity_number" id="identity_number" value="{{ old('identity_number') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: 3502182039200001">
                        </div>

                        <div>
                            <label for="gender" class="block mb-2 uppercase tracking-wide">JENIS KELAMIN <span class="text-red-500">*</span></label>
                            <select name="gender" id="gender" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                                <option value="" disabled selected>Pilih Jenis Kelamin</option>
                                <option value="Laki-laki" {{ old('gender') === 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                                <option value="Perempuan" {{ old('gender') === 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                            </select>
                        </div>

                        <div>
                            <label for="phone" class="block mb-2 uppercase tracking-wide">NOMOR HP / TELEPON <span class="text-red-500">*</span></label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: 082139xxxxxx">
                        </div>

                        <div>
                            <label for="email" class="block mb-2 uppercase tracking-wide">ALAMAT EMAIL</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: budi@gmail.com">
                        </div>

                        <div>
                            <label for="registration_date" class="block mb-2 uppercase tracking-wide">TANGGAL REGISTRASI <span class="text-red-500">*</span></label>
                            <input type="date" name="registration_date" id="registration_date" value="{{ old('registration_date', now()->format('Y-m-d')) }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                        </div>

                        <div class="md:col-span-2">
                            <label for="address" class="block mb-2 uppercase tracking-wide">ALAMAT INSTALASI LENGKAP <span class="text-red-500">*</span></label>
                            <textarea name="address" id="address" rows="2" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Nama Jalan, RT/RW, nomor rumah, detail lainnya...">{{ old('address') }}</textarea>
                        </div>

                        <!-- Region Selection -->
                        <div>
                            <label for="city_id" class="block mb-2 uppercase tracking-wide">KOTA <span class="text-red-500">*</span></label>
                            <select name="city_id" id="city_id" onchange="loadDistricts(this.value)" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                                <option value="" disabled selected>Pilih Kota</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->id }}" {{ old('city_id', \App\Models\City::where('name', 'Ponorogo')->first()->id ?? '') == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="district_id" class="block mb-2 uppercase tracking-wide">KECAMATAN <span class="text-red-500">*</span></label>
                            <select name="district_id" id="district_id" onchange="loadVillages(this.value)" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                                <option value="" disabled selected>Pilih Kecamatan (Pilih Kota Dulu)</option>
                            </select>
                        </div>

                        <div>
                            <label for="village_id" class="block mb-2 uppercase tracking-wide">DESA / KELURAHAN <span class="text-red-500">*</span></label>
                            <select name="village_id" id="village_id" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                                <option value="" disabled selected>Pilih Desa (Pilih Kecamatan Dulu)</option>
                                <!-- Async Populated -->
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4 md:col-span-1">
                            <div>
                                <label for="latitude" class="block mb-2 uppercase tracking-wide">LATITUDE</label>
                                <input type="text" name="latitude" id="latitude" value="{{ old('latitude') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="-7.86940">
                            </div>
                            <div>
                                <label for="longitude" class="block mb-2 uppercase tracking-wide">LONGITUDE</label>
                                <input type="text" name="longitude" id="longitude" value="{{ old('longitude') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="111.46210">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2 PANEL: Dokumen Lampiran -->
                <div id="step-panel-2" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">2. UPLOAD DOKUMEN LAMPIRAN</h4>
                        <p class="text-xs text-slate-400 mt-1">Upload lampiran dokumen pendukung pelanggan (opsional)</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Foto KTP -->
                        <div class="border border-slate-200 rounded-lg p-5 flex flex-col justify-between hover:border-sky-300 transition-colors shadow-sm relative">
                            <div id="default-placeholder-foto_ktp" class="text-center py-4">
                                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M21 12h-6m6 4h-6" />
                                </svg>
                                <span class="block text-xs font-bold text-slate-700 mt-3">FOTO KTP</span>
                                <span class="block text-[10px] text-slate-400 mt-1">Format: JPG, PNG (Max 2MB)</span>
                            </div>

                            <!-- Preview Container -->
                            <div id="preview-container-foto_ktp" class="hidden text-center py-2 flex flex-col items-center justify-center">
                                <div class="relative inline-block">
                                    <img id="preview-img-foto_ktp" class="max-h-28 max-w-full rounded-lg object-contain border border-slate-200 shadow-sm" src="" alt="Preview Foto KTP">
                                    <button type="button" onclick="clearFile('foto_ktp')" class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-1 shadow-md hover:scale-105 transition-transform focus:outline-none" title="Hapus File">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <span class="block text-xs font-bold text-slate-700 mt-2">PREVIEW FOTO KTP</span>
                            </div>

                            <div class="mt-4">
                                <input type="file" name="foto_ktp" id="foto_ktp" accept="image/*" class="hidden" onchange="onFileChange('foto_ktp')">
                                <label for="foto_ktp" class="block w-full text-center bg-slate-50 border border-slate-200 hover:bg-slate-100 hover:border-slate-300 text-slate-700 text-xs font-semibold py-2 px-3 rounded cursor-pointer transition-colors">
                                    Pilih File
                                </label>
                                <span id="file-label-foto_ktp" class="block text-[10px] text-slate-500 text-center mt-2 font-mono truncate">Belum ada file dipilih</span>
                            </div>
                        </div>

                        <!-- Foto Rumah -->
                        <div class="border border-slate-200 rounded-lg p-5 flex flex-col justify-between hover:border-sky-300 transition-colors shadow-sm relative">
                            <div id="default-placeholder-foto_rumah" class="text-center py-4">
                                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                <span class="block text-xs font-bold text-slate-700 mt-3">FOTO RUMAH</span>
                                <span class="block text-[10px] text-slate-400 mt-1">Format: JPG, PNG (Max 2MB)</span>
                            </div>

                            <!-- Preview Container -->
                            <div id="preview-container-foto_rumah" class="hidden text-center py-2 flex flex-col items-center justify-center">
                                <div class="relative inline-block">
                                    <img id="preview-img-foto_rumah" class="max-h-28 max-w-full rounded-lg object-contain border border-slate-200 shadow-sm" src="" alt="Preview Foto Rumah">
                                    <button type="button" onclick="clearFile('foto_rumah')" class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-1 shadow-md hover:scale-105 transition-transform focus:outline-none" title="Hapus File">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <span class="block text-xs font-bold text-slate-700 mt-2">PREVIEW FOTO RUMAH</span>
                            </div>

                            <div class="mt-4">
                                <input type="file" name="foto_rumah" id="foto_rumah" accept="image/*" class="hidden" onchange="onFileChange('foto_rumah')">
                                <label for="foto_rumah" class="block w-full text-center bg-slate-50 border border-slate-200 hover:bg-slate-100 hover:border-slate-300 text-slate-700 text-xs font-semibold py-2 px-3 rounded cursor-pointer transition-colors">
                                    Pilih File
                                </label>
                                <span id="file-label-foto_rumah" class="block text-[10px] text-slate-500 text-center mt-2 font-mono truncate">Belum ada file dipilih</span>
                            </div>
                        </div>

                        <!-- Foto Kontrak -->
                        <div class="border border-slate-200 rounded-lg p-5 flex flex-col justify-between hover:border-sky-300 transition-colors shadow-sm relative">
                            <div id="default-placeholder-foto_kontrak" class="text-center py-4">
                                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="block text-xs font-bold text-slate-700 mt-3">FOTO KONTRAK</span>
                                <span class="block text-[10px] text-slate-400 mt-1">Format: JPG, PNG, PDF (Max 2MB)</span>
                            </div>

                            <!-- Preview Container -->
                            <div id="preview-container-foto_kontrak" class="hidden text-center py-2 flex flex-col items-center justify-center">
                                <div class="relative inline-block">
                                    <img id="preview-img-foto_kontrak" class="max-h-28 max-w-full rounded-lg object-contain border border-slate-200 shadow-sm hidden" src="" alt="Preview Foto Kontrak">
                                    
                                    <!-- PDF Icon Preview -->
                                    <div id="preview-pdf-foto_kontrak" class="h-28 w-28 bg-red-50 border border-red-200 rounded-lg flex flex-col items-center justify-center text-red-600 shadow-sm hidden">
                                        <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span class="text-[10px] font-bold mt-2">DOKUMEN PDF</span>
                                    </div>

                                    <button type="button" onclick="clearFile('foto_kontrak')" class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-1 shadow-md hover:scale-105 transition-transform focus:outline-none" title="Hapus File">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <span class="block text-xs font-bold text-slate-700 mt-2">PREVIEW FOTO KONTRAK</span>
                            </div>

                            <div class="mt-4">
                                <input type="file" name="foto_kontrak" id="foto_kontrak" accept="image/*,application/pdf" class="hidden" onchange="onFileChange('foto_kontrak')">
                                <label for="foto_kontrak" class="block w-full text-center bg-slate-50 border border-slate-200 hover:bg-slate-100 hover:border-slate-300 text-slate-700 text-xs font-semibold py-2 px-3 rounded cursor-pointer transition-colors">
                                    Pilih File
                                </label>
                                <span id="file-label-foto_kontrak" class="block text-[10px] text-slate-500 text-center mt-2 font-mono truncate">Belum ada file dipilih</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3 PANEL: Layanan & Paket -->
                <div id="step-panel-3" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">3. LAYANAN & PAKET LAYANAN INTERNET</h4>
                        <p class="text-xs text-slate-400 mt-1">Pilih paket internet dan rincian parameter kontrak berlangganan</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-slate-500">
                        <div>
                            <label class="block mb-2 uppercase tracking-wide" for="internet_package_id">PAKET INTERNET <span class="text-red-500">*</span></label>
                            <select name="internet_package_id" id="internet_package_id" onchange="updateLayananBreakdown()" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                                <option value="" disabled selected>Pilih Paket Internet</option>
                                @foreach($packages as $package)
                                    <option value="{{ $package->id }}" data-price="{{ $package->monthly_price }}" {{ old('internet_package_id') == $package->id ? 'selected' : '' }}>{{ $package->package_code }} - {{ $package->category }} (Rp {{ number_format($package->monthly_price, 0, ',', '.') }}/bln)</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block mb-2 uppercase tracking-wide" for="contract_period_months">MASA KONTRAK (BULAN) <span class="text-red-500">*</span></label>
                            <input type="number" name="contract_period_months" id="contract_period_months" value="{{ old('contract_period_months', 12) }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: 12">
                        </div>

                        <div>
                            <label class="block mb-2 uppercase tracking-wide" for="discount_amount">DISKON PROMOSI (RP) <span class="text-red-500">*</span></label>
                            <input type="number" name="discount_amount" id="discount_amount" oninput="updateLayananBreakdown()" value="{{ old('discount_amount', 0) }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: 10000">
                        </div>

                        <div>
                            <label class="block mb-2 uppercase tracking-wide" for="tax_percent">PPN (%) <span class="text-red-500">*</span></label>
                            <input type="number" name="tax_percent" id="tax_percent" oninput="updateLayananBreakdown()" value="{{ old('tax_percent', 11) }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: 11">
                        </div>
                    </div>

                    <!-- Dynamic Pricing Calculations Preview -->
                    <div class="border border-slate-200 rounded-lg p-5 bg-slate-50/50 mt-6">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3">Rincian Estimasi Biaya Bulanan</span>
                        <div class="space-y-2 max-w-sm text-xs font-mono data-text">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Harga Paket Dasar:</span>
                                <span class="text-slate-700" id="preview-base-price">Rp 0,00</span>
                            </div>
                            <div class="flex justify-between text-green-600">
                                <span>Diskon Promosi:</span>
                                <span id="preview-discount">- Rp 0,00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500" id="preview-tax-label">PPN (11%):</span>
                                <span class="text-slate-700" id="preview-tax">Rp 0,00</span>
                            </div>
                            <hr class="border-dashed border-slate-200">
                            <div class="flex justify-between text-sm font-bold text-slate-900">
                                <span>Total Biaya Bulanan:</span>
                                <span id="preview-total-monthly">Rp 0,00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4 PANEL: Referral -->
                <div id="step-panel-4" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">4. INFORMASI REFERRAL & AKUISISI</h4>
                        <p class="text-xs text-slate-400 mt-1">Masukkan kode sales, agen, atau kode pelanggan yang mereferensikan</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs font-semibold text-slate-500">
                        <div>
                            <label for="sales_code" class="block mb-2 uppercase tracking-wide">KODE / ID SALES</label>
                            <input type="text" name="sales_code" id="sales_code" value="{{ old('sales_code') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: SLS-043">
                        </div>

                        <div>
                            <label for="agent_code" class="block mb-2 uppercase tracking-wide">KODE / ID AGENT</label>
                            <input type="text" name="agent_code" id="agent_code" value="{{ old('agent_code') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: AGT-012">
                        </div>

                        <div>
                            <label for="referral_customer_code" class="block mb-2 uppercase tracking-wide">ID REFERRAL PELANGGAN</label>
                            <input type="text" name="referral_customer_code" id="referral_customer_code" value="{{ old('referral_customer_code') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: CID-2026-0005">
                        </div>
                    </div>
                </div>

                <!-- STEP 5 PANEL: Operasional Awal & Teknis -->
                <div id="step-panel-5" class="step-panel space-y-6 hidden">
                    <div class="border-b border-slate-100 pb-3 mb-6">
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">5. PENYETELAN OPERASIONAL & PARAMETER TEKNIS</h4>
                        <p class="text-xs text-slate-400 mt-1">Tentukan status alur kerja dan konfigurasi data perangkat ONT / Jaringan awal</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-slate-500">
                        <div>
                            <label for="status" class="block mb-2 uppercase tracking-wide">STATUS AWAL ALUR KERJA <span class="text-red-500">*</span></label>
                            <select name="status" id="status" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                                <option value="registered" {{ old('status') === 'registered' ? 'selected' : '' }}>Registered (Baru Terdaftar)</option>
                                <option value="waiting_survey" {{ old('status') === 'waiting_survey' ? 'selected' : '' }}>Waiting Survey (Menunggu Survey)</option>
                                <option value="surveyed" {{ old('status') === 'surveyed' ? 'selected' : '' }}>Surveyed (Selesai Survey)</option>
                                <option value="waiting_installation" {{ old('status') === 'waiting_installation' ? 'selected' : '' }}>Waiting Installation (Menunggu Pemasangan)</option>
                                <option value="installed" {{ old('status') === 'installed' ? 'selected' : '' }}>Installed (Selesai Pemasangan)</option>
                                <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active (Aktif Berlangganan)</option>
                                <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>Suspended (Diisolir Sementara)</option>
                            </select>
                        </div>

                        <div>
                            <label for="ont_sn" class="block mb-2 uppercase tracking-wide">SERIAL NUMBER (SN) ONT</label>
                            <input type="text" name="ont_sn" id="ont_sn" value="{{ old('ont_sn') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: ZTEG12345678">
                        </div>

                        <div>
                            <label for="ip_address" class="block mb-2 uppercase tracking-wide">IP ADDRESS DIAL-UP</label>
                            <input type="text" name="ip_address" id="ip_address" value="{{ old('ip_address') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: 10.200.45.10">
                        </div>

                        <div>
                            <label for="odp_code" class="block mb-2 uppercase tracking-wide">KODE / KOTAK ODP</label>
                            <input type="text" name="odp_code" id="odp_code" value="{{ old('odp_code') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: ODP-PON-024">
                        </div>

                        <div>
                            <label for="olt_code" class="block mb-2 uppercase tracking-wide">KODE OLT</label>
                            <input type="text" name="olt_code" id="olt_code" value="{{ old('olt_code') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: OLT-ZTE-C320">
                        </div>

                        <div>
                            <label for="vlan_id" class="block mb-2 uppercase tracking-wide">VLAN ID</label>
                            <input type="text" name="vlan_id" id="vlan_id" value="{{ old('vlan_id') }}" class="w-full text-sm font-sans px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Contoh: 1024">
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
                    <a href="/customers" class="px-4 py-2 border border-slate-200 rounded-md bg-white text-slate-700 hover:bg-slate-50 transition-colors text-xs font-semibold cursor-pointer focus:outline-none">
                        Batal
                    </a>
                    
                    <button type="button" id="btn-next" onclick="nextStep()" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold cursor-pointer focus:outline-none">
                        Lanjut
                    </button>

                    <button type="submit" id="btn-submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold cursor-pointer hidden focus:outline-none">
                        Simpan Registrasi
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
    const totalStepsCount = 5;

    // Define wizard fields configurations
    const formFields = {
        'data-diri': {
            required: ['full_name', 'identity_number', 'gender', 'phone', 'registration_date', 'address', 'city_id', 'district_id', 'village_id'],
            optional: ['email', 'latitude', 'longitude']
        },
        'dokumen': {
            required: [],
            optional: ['foto_ktp', 'foto_rumah', 'foto_kontrak']
        },
        'layanan': {
            required: ['internet_package_id', 'contract_period_months', 'discount_amount', 'tax_percent'],
            optional: []
        },
        'referral': {
            required: [],
            optional: ['sales_code', 'agent_code', 'referral_customer_code']
        },
        'operasional': {
            required: ['status'],
            optional: ['ont_sn', 'ip_address', 'odp_code', 'olt_code', 'vlan_id']
        }
    };

    // Mapping steps to keys
    const stepKeys = {
        1: 'data-diri',
        2: 'dokumen',
        3: 'layanan',
        4: 'referral',
        5: 'operasional'
    };

    document.addEventListener("DOMContentLoaded", function() {
        // Dynamic checks on input change
        const inputs = document.querySelectorAll('#wizard-form input, #wizard-form select, #wizard-form textarea');
        inputs.forEach(input => {
            input.addEventListener('input', runLiveProgressUpdates);
            input.addEventListener('change', runLiveProgressUpdates);
        });

        // Run validation immediately on load to set defaults
        runLiveProgressUpdates();
    });

    // Check if City had old value
    const oldCityId = "{{ old('city_id', \App\Models\City::where('name', 'Ponorogo')->first()->id ?? '') }}";
    const oldDistrictId = "{{ old('district_id') }}";
    const oldVillageId = "{{ old('village_id') }}";
    if (oldCityId) {
        loadDistricts(oldCityId, oldDistrictId, oldVillageId);
    }

    // Dynamic dropdown for Districts
    function loadDistricts(cityId, selectedDistrictId = null, selectedVillageId = null) {
        const districtSelect = document.getElementById('district_id');
        const villageSelect = document.getElementById('village_id');
        
        districtSelect.innerHTML = '<option value="" disabled selected>Memuat kecamatan...</option>';
        villageSelect.innerHTML = '<option value="" disabled selected>Pilih Desa (Pilih Kecamatan Dulu)</option>';

        fetch(`/api/cities/${cityId}/districts`)
            .then(res => res.json())
            .then(districts => {
                districtSelect.innerHTML = '<option value="" disabled selected>Pilih Kecamatan</option>';
                districts.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name;
                    if (selectedDistrictId && selectedDistrictId == d.id) {
                        opt.selected = true;
                    }
                    districtSelect.appendChild(opt);
                });
                
                if (selectedDistrictId) {
                    loadVillages(selectedDistrictId, selectedVillageId);
                }
                
                runLiveProgressUpdates();
            })
            .catch(err => {
                console.error("Gagal memuat kecamatan:", err);
                districtSelect.innerHTML = '<option value="" disabled selected>Gagal memuat kecamatan</option>';
            });
    }

    // Dynamic dropdown for Villages
    function loadVillages(districtId, selectedVillageId = null) {
        const villageSelect = document.getElementById('village_id');
        villageSelect.innerHTML = '<option value="" disabled selected>Memuat desa...</option>';

        fetch(`/api/districts/${districtId}/villages`)
            .then(res => res.json())
            .then(villages => {
                villageSelect.innerHTML = '<option value="" disabled selected>Pilih Desa</option>';
                villages.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.id;
                    opt.textContent = v.name + (v.postal_code ? ` (${v.postal_code})` : '');
                    if (selectedVillageId && selectedVillageId == v.id) {
                        opt.selected = true;
                    }
                    villageSelect.appendChild(opt);
                });
                // Update live calculations once loaded
                runLiveProgressUpdates();
            })
            .catch(err => {
                console.error("Gagal memuat desa:", err);
                villageSelect.innerHTML = '<option value="" disabled selected>Gagal memuat desa</option>';
            });
    }

    // File selection UI update helper
    function onFileChange(fieldId) {
        const input = document.getElementById(fieldId);
        const label = document.getElementById('file-label-' + fieldId);
        const defaultPlaceholder = document.getElementById('default-placeholder-' + fieldId);
        const previewContainer = document.getElementById('preview-container-' + fieldId);
        const previewImg = document.getElementById('preview-img-' + fieldId);
        const previewPdf = document.getElementById('preview-pdf-' + fieldId);

        if (input.files && input.files.length > 0) {
            const file = input.files[0];
            label.textContent = file.name;
            // Mark file input as populated
            input.setAttribute('data-populated', 'true');

            // Handle preview generating
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) {
                        previewImg.src = e.target.result;
                        previewImg.classList.remove('hidden');
                    }
                    if (previewPdf) {
                        previewPdf.classList.add('hidden');
                    }
                    if (defaultPlaceholder) defaultPlaceholder.classList.add('hidden');
                    if (previewContainer) previewContainer.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                if (previewImg) {
                    previewImg.classList.add('hidden');
                    previewImg.src = '';
                }
                if (previewPdf) {
                    previewPdf.classList.remove('hidden');
                }
                if (defaultPlaceholder) defaultPlaceholder.classList.add('hidden');
                if (previewContainer) previewContainer.classList.remove('hidden');
            } else {
                // Unknown file type, show fallback but still populate
                if (previewImg) {
                    previewImg.classList.add('hidden');
                    previewImg.src = '';
                }
                if (previewPdf) {
                    previewPdf.classList.add('hidden');
                }
                if (defaultPlaceholder) defaultPlaceholder.classList.remove('hidden');
                if (previewContainer) previewContainer.classList.add('hidden');
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

    // Estimate monthly total preview
    function updateLayananBreakdown() {
        const packageSelect = document.getElementById('internet_package_id');
        const discountInput = document.getElementById('discount_amount');
        const taxInput = document.getElementById('tax_percent');

        const selectedOption = packageSelect.options[packageSelect.selectedIndex];
        let basePrice = 0;
        if (selectedOption && selectedOption.value) {
            basePrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        }

        const discount = parseFloat(discountInput.value) || 0;
        const taxPercent = parseFloat(taxInput.value) || 0;

        const taxable = Math.max(0, basePrice - discount);
        const tax = Math.round(taxable * (taxPercent / 100));
        const total = taxable + tax;

        // Render preview texts
        document.getElementById('preview-base-price').textContent = formatRupiah(basePrice);
        document.getElementById('preview-discount').textContent = '- ' + formatRupiah(discount);
        document.getElementById('preview-tax-label').textContent = `PPN (${taxPercent}%):`;
        document.getElementById('preview-tax').textContent = formatRupiah(tax);
        document.getElementById('preview-total-monthly').textContent = formatRupiah(total);
    }

    function formatRupiah(number) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number).replace(/,/g, '.');
    }

    // Central live validation validator
    function runLiveProgressUpdates() {
        let totalFieldsCount = 0;
        let filledFieldsCount = 0;

        // Loop each step definition
        for (let step = 1; step <= totalStepsCount; step++) {
            const stepKey = stepKeys[step];
            const config = formFields[stepKey];
            let requiredMissing = [];
            let optionalMissing = [];

            // 1. Required fields audit
            config.required.forEach(field => {
                totalFieldsCount++;
                const el = document.getElementById(field);
                if (el) {
                    if (el.value.trim() !== "") {
                        filledFieldsCount++;
                    } else {
                        requiredMissing.push(el.getAttribute('placeholder') || getLabelName(field));
                    }
                }
            });

            // 2. Optional fields audit
            config.optional.forEach(field => {
                totalFieldsCount++;
                const el = document.getElementById(field);
                if (el) {
                    // Check if file input is populated
                    const isFilePopulated = el.type === 'file' && el.getAttribute('data-populated') === 'true';
                    if (el.value.trim() !== "" || isFilePopulated) {
                        filledFieldsCount++;
                    } else {
                        optionalMissing.push(getLabelName(field));
                    }
                }
            });

            // Render status on Left Stepper
            updateStepNavStatus(step, requiredMissing, optionalMissing);
        }

        // Calculate and update the overall progress bar
        const progressPercentage = totalFieldsCount > 0 ? Math.round((filledFieldsCount / totalFieldsCount) * 100) : 0;
        document.getElementById('progress-percentage').textContent = progressPercentage + '%';
        document.getElementById('filled-fields-count').textContent = filledFieldsCount;
        document.getElementById('progress-bar-fill').style.width = progressPercentage + '%';
    }

    // Set step nav status classes and content dynamically
    function updateStepNavStatus(step, requiredMissing, optionalMissing) {
        const navBtn = document.getElementById('step-nav-' + step);
        const iconDiv = document.getElementById('step-nav-icon-' + step);
        const statusSpan = document.getElementById('step-nav-status-' + step);
        const missingSpan = document.getElementById('step-nav-missing-' + step);

        iconDiv.innerHTML = '';
        missingSpan.textContent = '';

        if (requiredMissing.length > 0) {
            // State: Belum Lengkap (Red Warning)
            statusSpan.textContent = 'Belum Lengkap';
            statusSpan.className = 'text-[9px] font-bold block mt-1 uppercase tracking-wider text-red-600';
            
            // Red warning icon (SVG)
            iconDiv.innerHTML = `<span class="h-5 w-5 rounded-full bg-red-50 border border-red-200 flex items-center justify-center text-red-600">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </span>`;

            missingSpan.textContent = 'Wajib diisi: ' + requiredMissing.join(', ');
            
            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-red-100 bg-red-50/10 hover:bg-red-50/20 transition-all group focus:outline-none";
            }
        } else if (optionalMissing.length > 0) {
            // State: Kekurangan Data (Amber Warning)
            statusSpan.textContent = 'Kekurangan Data';
            statusSpan.className = 'text-[9px] font-bold block mt-1 uppercase tracking-wider text-amber-600';

            // Amber alert icon (SVG)
            iconDiv.innerHTML = `<span class="h-5 w-5 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </span>`;

            missingSpan.textContent = 'Kurang: ' + optionalMissing.join(', ');

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-amber-100 bg-amber-50/10 hover:bg-amber-50/20 transition-all group focus:outline-none";
            }
        } else {
            // State: Lengkap (Green Check)
            statusSpan.textContent = 'Lengkap';
            statusSpan.className = 'text-[9px] font-bold block mt-1 uppercase tracking-wider text-green-600';

            // Green check icon (SVG)
            iconDiv.innerHTML = `<span class="h-5 w-5 rounded-full bg-green-500 flex items-center justify-center text-white">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </span>`;

            missingSpan.textContent = 'Semua data terisi';

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-green-200 bg-green-50/10 hover:bg-green-50/20 transition-all group focus:outline-none";
            }
        }

        // Highlight active step specifically
        if (currentActiveStep === step) {
            navBtn.className = "w-full text-left p-3.5 rounded-lg border-2 border-sky-600 bg-sky-50/30 transition-all group focus:outline-none";
        }
    }

    // Field names translator helper
    function getLabelName(field) {
        const labels = {
            full_name: 'Nama Lengkap',
            identity_number: 'NIK',
            gender: 'Jenis Kelamin',
            phone: 'Nomor HP',
            email: 'Email',
            registration_date: 'Tgl Registrasi',
            address: 'Alamat',
            city_id: 'Kota',
            district_id: 'Kecamatan',
            village_id: 'Desa',
            latitude: 'Latitude',
            longitude: 'Longitude',
            foto_ktp: 'Foto KTP',
            foto_rumah: 'Foto Rumah',
            foto_kontrak: 'Foto Kontrak',
            internet_package_id: 'Paket Internet',
            contract_period_months: 'Masa Kontrak',
            discount_amount: 'Diskon',
            tax_percent: 'PPN',
            sales_code: 'Kode Sales',
            agent_code: 'Kode Agent',
            referral_customer_code: 'Ref Pelanggan',
            status: 'Status Awal',
            ont_sn: 'ONT SN',
            ip_address: 'IP Dialup',
            odp_code: 'Kode ODP',
            olt_code: 'Kode OLT',
            vlan_id: 'VLAN ID'
        };
        return labels[field] || field;
    }

    // Step switching workflow
    function goToStep(stepNumber) {
        // Hide active panel
        document.getElementById('step-panel-' + currentActiveStep).classList.add('hidden');
        
        // Update current step index
        currentActiveStep = stepNumber;

        // Show new panel
        document.getElementById('step-panel-' + currentActiveStep).classList.remove('hidden');

        // Adjust navigation button visibility
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

        // Re-run status colorizer to update border highlight
        runLiveProgressUpdates();
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
