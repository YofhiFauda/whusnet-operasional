@extends('layouts.app')

@section('title', 'Tambah Pelanggan Baru — WHUSNET Operasional')
@section('page_title', 'Tambah Pelanggan Baru')
@section('breadcrumb_parent', 'Pelanggan')
@section('breadcrumb_parent_url', route('customers.index'))

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    <!-- LAYER 1: NAKED PAGE HEADER (Strict Design System Rule: No card wrapper) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">Registrasi Pelanggan Baru</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700/60">
                    <x-ui.icon name="sparkles" class="w-2.5 h-2.5 mr-1.5" /> Form Wizard
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                Masukkan data identitas calon pelanggan, wilayah pemasangan, foto dokumen KTP, dan paket layanan internet.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('customers.index') }}" class="px-3.5 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors inline-flex items-center gap-2">
                <x-ui.icon name="arrow-left" class="w-3 h-3" />
                <span>Kembali ke List</span>
            </a>
        </div>
    </div>

    <!-- MAIN FORM CONTAINER -->
    <form action="{{ route('customers.store') }}" method="POST" enctype="multipart/form-data" id="wizard-form" class="space-y-6">
        @csrf

        <!-- TOP PANEL: Dynamic Completeness Progress Bar -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-4 sm:p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800/50">
                        <x-ui.icon name="list-checks" class="w-3 h-3" />
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Kelengkapan Formulir Registrasi</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Semua data akan divalidasi sebelum disimpan ke master pelanggan</p>
                    </div>
                </div>
                <div class="text-right">
                    <span id="progress-percentage" class="text-sm sm:text-base font-extrabold text-sky-600 dark:text-sky-400 data-text">0%</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 block"><span id="filled-fields-count" class="data-text font-semibold">0</span> dari <span class="data-text font-semibold">25</span> field terisi</span>
                </div>
            </div>

            <!-- Progress Bar Fill Strip -->
            <div class="w-full bg-slate-100 dark:bg-slate-700/60 rounded-full h-2.5 overflow-hidden border border-slate-200/60 dark:border-slate-700">
                <div id="progress-bar-fill" class="bg-gradient-to-r from-sky-500 to-sky-600 h-full w-0 transition-all duration-500 ease-out" style="width: 0%;"></div>
            </div>
        </div>

        <!-- MOBILE RESPONSIVE STEPPER (Visible on < lg screens: 1 row x 3 items) -->
        <div class="lg:hidden bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-3 shadow-sm">
            <div class="grid grid-cols-3 gap-2 text-center">
                <button type="button" onclick="goToStep(1)" id="mobile-step-btn-1" class="py-2.5 px-2 rounded-lg text-xs font-bold bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 transition-all flex items-center justify-center gap-1.5 shadow-sm">
                    <span class="w-4 h-4 rounded-full bg-sky-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">1</span>
                    <span class="truncate text-[11px] font-semibold">1. Data Diri</span>
                </button>
                <button type="button" onclick="goToStep(2)" id="mobile-step-btn-2" class="py-2.5 px-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all flex items-center justify-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[9px] font-bold flex items-center justify-center shrink-0">2</span>
                    <span class="truncate text-[11px] font-semibold">2. KTP</span>
                </button>
                <button type="button" onclick="goToStep(3)" id="mobile-step-btn-3" class="py-2.5 px-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all flex items-center justify-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[9px] font-bold flex items-center justify-center shrink-0">3</span>
                    <span class="truncate text-[11px] font-semibold">3. Paket</span>
                </button>
            </div>
        </div>

        <!-- MAIN GRID LAYOUT -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- LEFT COLUMN: Desktop Stepper Checklist (Visible on >= lg screens) -->
            <div class="hidden lg:block lg:col-span-4 space-y-4">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-5 shadow-sm space-y-4">
                    <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Tahapan Formulir</h4>

                    <div class="space-y-3">
                        <!-- Step 1 Navigation Card -->
                        <button type="button" onclick="goToStep(1)" id="step-nav-1" class="w-full text-left p-3.5 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-1">
                                    <span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400">
                                        <x-ui.icon name="x" class="w-3 h-3" />
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-900 dark:text-slate-100">1. Data Diri &amp; Wilayah</span>
                                    <span id="step-nav-status-1" class="text-[9px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mt-0.5">Belum Lengkap</span>
                                    <span id="step-nav-missing-1" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-1 leading-relaxed">Wajib diisi: Nama Lengkap, NIK, Jenis Kelamin, HP, POP, Alamat, Kecamatan, Desa</span>
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
                                    <span class="block text-xs font-bold text-slate-900 dark:text-slate-100">2. Dokumen Lampiran</span>
                                    <span id="step-nav-status-2" class="text-[9px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mt-0.5">Belum Lengkap</span>
                                    <span id="step-nav-missing-2" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-1 leading-relaxed">Wajib diisi: Foto KTP</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 3 Navigation Card -->
                        <button type="button" onclick="goToStep(3)" id="step-nav-3" class="w-full text-left p-3.5 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-3">
                                    <span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400">
                                        <x-ui.icon name="x" class="w-3 h-3" />
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-900 dark:text-slate-100">3. Layanan &amp; Paket</span>
                                    <span id="step-nav-status-3" class="text-[9px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mt-0.5">Belum Lengkap</span>
                                    <span id="step-nav-missing-3" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-1 leading-relaxed">Wajib diisi: Paket Internet, Jenis Kontrak, Masa Kontrak, Diskon</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Wizard Steps Form Panels (SINGLE MAIN CARD BUDGET) -->
            <div class="lg:col-span-8 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl shadow-sm overflow-hidden min-h-[520px] flex flex-col justify-between">
                
                <!-- FORM BODY -->
                <div class="p-5 sm:p-7 flex-1">

                    <!-- STEP 1 PANEL: Data Diri & Wilayah -->
                    <div id="step-panel-1" class="step-panel space-y-6">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">1. IDENTITAS PELANGGAN &amp; ALAMAT</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Masukkan data diri lengkap dan wilayah instalasi pelanggan</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <label for="full_name" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Nama Lengkap <span class="text-rose-500">*</span></label>
                                <input type="text" name="full_name" id="full_name" value="{{ old('full_name') }}" class="w-full text-xs font-sans px-3 py-2.5 border @error('full_name') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="Cth: Ihda Ainin Nadhira">
                                @error('full_name')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="identity_number" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Nomor Identitas (NIK KTP) <span class="text-rose-500">*</span></label>
                                <input type="text" name="identity_number" id="identity_number" value="{{ old('identity_number') }}" maxlength="16" class="w-full text-xs font-mono px-3 py-2.5 border @error('identity_number') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="3502182039200001">
                                @error('identity_number')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="gender" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Jenis Kelamin <span class="text-rose-500">*</span></label>
                                <select name="gender" id="gender" class="w-full text-xs font-sans px-3 py-2.5 border @error('gender') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled {{ old('gender') ? '' : 'selected' }}>Pilih Jenis Kelamin</option>
                                    <option value="Laki-laki" {{ old('gender') === 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                                    <option value="Perempuan" {{ old('gender') === 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                                </select>
                                @error('gender')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="primary_phone" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Nomor HP Utama (WhatsApp) <span class="text-rose-500">*</span></label>
                                <input type="text" name="primary_phone" id="primary_phone" value="{{ old('primary_phone') }}" class="w-full text-xs font-mono px-3 py-2.5 border @error('primary_phone') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="082139xxxxxx">
                                @error('primary_phone')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="alternative_phone" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Nomor HP Alternatif (Opsional)</label>
                                <input type="text" name="alternative_phone" id="alternative_phone" value="{{ old('alternative_phone') }}" class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="082139xxxxxx">
                            </div>

                            <div>
                                <label for="npwp" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">NPWP (Opsional)</label>
                                <input type="text" name="npwp" id="npwp" value="{{ old('npwp') }}" class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="12.345.678.9-012.000">
                            </div>

                            <div>
                                <label for="email" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Alamat Email (Opsional)</label>
                                <input type="email" name="email" id="email" value="{{ old('email') }}" class="w-full text-xs font-sans px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="ihda@gmail.com">
                            </div>

                            <div>
                                <label for="registration_date" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Tanggal Registrasi <span class="text-rose-500">*</span></label>
                                <input type="date" name="registration_date" id="registration_date" value="{{ old('registration_date', now()->format('Y-m-d')) }}" class="w-full text-xs font-sans px-3 py-2.5 border @error('registration_date') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                @error('registration_date')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pop_id" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">POP Cabang <span class="text-rose-500">*</span></label>
                                <select name="pop_id" id="pop_id" class="w-full text-xs font-sans px-3 py-2.5 border @error('pop_id') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled {{ old('pop_id') ? '' : 'selected' }}>Pilih POP Cabang</option>
                                    @foreach($pops as $pop)
                                        <option value="{{ $pop->id }}" {{ old('pop_id') == $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                                    @endforeach
                                </select>
                                @error('pop_id')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="address" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Alamat Instalasi Lengkap <span class="text-rose-500">*</span></label>
                                <textarea name="address" id="address" rows="2" class="w-full text-xs font-sans px-3 py-2.5 border @error('address') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="Jln. Raya Siman No. 42, RT 02/RW 01, Gang Melati...">{{ old('address') }}</textarea>
                                @error('address')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Region Selection -->
                            <div>
                                <label for="city_id" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Kota / Kabupaten <span class="text-rose-500">*</span></label>
                                <select name="city_id" id="city_id" onchange="loadDistricts(this.value)" class="w-full text-xs font-sans px-3 py-2.5 border @error('city_id') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled {{ old('city_id') ? '' : 'selected' }}>Pilih Kota</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->id }}" {{ old('city_id', \App\Models\City::where('name', 'Ponorogo')->first()->id ?? '') == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                                    @endforeach
                                </select>
                                @error('city_id')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="district_id" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Kecamatan <span class="text-rose-500">*</span></label>
                                <select name="district_id" id="district_id" onchange="loadVillages(this.value)" class="w-full text-xs font-sans px-3 py-2.5 border @error('district_id') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled selected>Pilih Kecamatan (Pilih Kota Dulu)</option>
                                </select>
                                @error('district_id')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="village_id" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Desa / Kelurahan <span class="text-rose-500">*</span></label>
                                <select name="village_id" id="village_id" class="w-full text-xs font-sans px-3 py-2.5 border @error('village_id') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled selected>Pilih Desa (Pilih Kecamatan Dulu)</option>
                                </select>
                                @error('village_id')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-3 md:col-span-1">
                                <div>
                                    <label for="latitude" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Latitude</label>
                                    <input type="text" name="latitude" id="latitude" value="{{ old('latitude') }}" class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="-7.86940">
                                </div>
                                <div>
                                    <label for="longitude" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Longitude</label>
                                    <input type="text" name="longitude" id="longitude" value="{{ old('longitude') }}" class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="111.46210">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 PANEL: Dokumen Lampiran KTP -->
                    <div id="step-panel-2" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">2. UPLOAD DOKUMEN LAMPIRAN</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Upload file foto KTP asli calon pelanggan untuk verifikasi identitas</p>
                        </div>

                        <div class="max-w-xl mx-auto">
                            <!-- Foto KTP Dropzone -->
                            <div class="border-2 border-dashed @error('foto_ktp') border-rose-400 bg-rose-50/20 @else border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 @enderror hover:border-sky-500 dark:hover:border-sky-400 rounded-2xl p-6 text-center transition-all shadow-sm relative group">
                                <div id="default-placeholder-foto_ktp" class="py-6 space-y-3">
                                    <div class="w-14 h-14 mx-auto rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center text-2xl border border-sky-200 dark:border-sky-800">
                                        <x-ui.icon name="id-card" class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">UPLOAD FOTO KTP <span class="text-rose-500">*</span></span>
                                        <span class="block text-[11px] text-slate-400 dark:text-slate-500 mt-1">Format gambar JPG, PNG (Maksimal 2MB)</span>
                                    </div>
                                </div>

                                <!-- Preview Container -->
                                <div id="preview-container-foto_ktp" style="display: none;" class="py-3 flex flex-col items-center justify-center">
                                    <div class="relative inline-block">
                                        <img id="preview-img-foto_ktp" class="max-h-48 max-w-full rounded-xl object-contain border border-slate-200 dark:border-slate-700 shadow-md" src="" alt="Preview Foto KTP">
                                        <button type="button" onclick="clearFile('foto_ktp')" class="absolute -top-3 -right-3 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-7 h-7 flex items-center justify-center shadow-lg hover:scale-110 transition-transform focus:outline-none cursor-pointer" title="Hapus Foto KTP">
                                            <x-ui.icon name="x" class="w-3 h-3" />
                                        </button>
                                    </div>
                                    <span class="block text-xs font-bold text-emerald-600 dark:text-emerald-400 mt-3 flex items-center gap-1.5">
                                        <x-ui.icon name="circle-check" class="w-3 h-3" /> File KTP Siap Diunggah
                                    </span>
                                </div>

                                <div class="mt-4">
                                    <input type="file" name="foto_ktp" id="foto_ktp" accept="image/*" capture="environment" class="hidden" onchange="onFileChange('foto_ktp')">
                                    <label for="foto_ktp" class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-2.5 px-5 rounded-lg cursor-pointer transition-colors shadow-sm focus:outline-none">
                                        <x-ui.icon name="upload" class="w-3 h-3" />
                                        <span>Pilih File Gambar</span>
                                    </label>
                                    <span id="file-label-foto_ktp" class="block text-[11px] text-slate-400 dark:text-slate-500 text-center mt-2 font-mono truncate">Belum ada file dipilih</span>
                                </div>

                                @error('foto_ktp')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-2">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3 PANEL: Layanan & Paket Internet -->
                    <div id="step-panel-3" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">3. LAYANAN &amp; PAKET LAYANAN INTERNET</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pilih paket internet dan rincian parameter kontrak berlangganan</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div class="md:col-span-2">
                                <label for="internet_package_id" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Paket Internet <span class="text-rose-500">*</span></label>
                                <select name="internet_package_id" id="internet_package_id" onchange="updateLayananBreakdown()" class="w-full text-xs font-sans px-3 py-2.5 border @error('internet_package_id') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled {{ old('internet_package_id') ? '' : 'selected' }}>Pilih Paket Internet</option>
                                    @foreach($packages as $package)
                                        <option value="{{ $package->id }}" data-price="{{ $package->monthly_price }}" {{ old('internet_package_id') == $package->id ? 'selected' : '' }}>
                                            {{ $package->package_code }} — {{ $package->name }} (Rp {{ number_format($package->monthly_price, 0, ',', '.') }}/bln)
                                        </option>
                                    @endforeach
                                </select>
                                @error('internet_package_id')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="jenis_kontrak" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Jenis Kontrak <span class="text-rose-500">*</span></label>
                                <select name="jenis_kontrak" id="jenis_kontrak" class="w-full text-xs font-sans px-3 py-2.5 border @error('jenis_kontrak') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled {{ old('jenis_kontrak') ? '' : 'selected' }}>Pilih Jenis Kontrak</option>
                                    <option value="sewa" {{ old('jenis_kontrak', 'sewa') === 'sewa' ? 'selected' : '' }}>Sewa (Modem Dipinjamkan ISP)</option>
                                    <option value="beli" {{ old('jenis_kontrak') === 'beli' ? 'selected' : '' }}>Beli Putus (Modem Milik Pelanggan)</option>
                                </select>
                                @error('jenis_kontrak')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="contract_period_months" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Masa Kontrak (Bulan) <span class="text-rose-500">*</span></label>
                                <input type="number" name="contract_period_months" id="contract_period_months" value="{{ old('contract_period_months', 12) }}" min="1" class="w-full text-xs font-mono px-3 py-2.5 border @error('contract_period_months') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="12">
                                @error('contract_period_months')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="discount_amount" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Diskon Promosi (Rp) <span class="text-rose-500">*</span></label>
                                <input type="number" name="discount_amount" id="discount_amount" oninput="updateLayananBreakdown()" value="{{ old('discount_amount', 0) }}" min="0" class="w-full text-xs font-mono px-3 py-2.5 border @error('discount_amount') border-rose-500 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="0">
                                @error('discount_amount')
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                        </div>

                        <!-- Live Price Breakdown Card -->
                        <div id="layanan-breakdown-card" class="bg-sky-50/70 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800/60 rounded-xl p-4 sm:p-5 space-y-3">
                            <div class="flex items-center justify-between border-b border-sky-200/60 dark:border-sky-800/50 pb-2">
                                <span class="text-xs font-bold text-sky-900 dark:text-sky-200 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-ui.icon name="calculator" class="w-4 h-4 text-sky-600 dark:text-sky-400" /> Ringkasan Biaya Bulanan
                                </span>
                                <span class="text-[10px] text-sky-700 dark:text-sky-300 bg-sky-100 dark:bg-sky-900/60 px-2 py-0.5 rounded font-semibold">Live Preview</span>
                            </div>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between text-slate-600 dark:text-slate-300">
                                    <span>Harga Master Paket:</span>
                                    <span id="breakdown-base-price" class="data-text font-semibold">Rp 0</span>
                                </div>
                                <div class="flex justify-between text-slate-600 dark:text-slate-300">
                                    <span>Diskon Promosi:</span>
                                    <span id="breakdown-discount" class="data-text font-semibold text-rose-600 dark:text-rose-400">- Rp 0</span>
                                </div>
                                <div class="flex justify-between pt-2 border-t border-sky-200/60 dark:border-sky-800/50 text-slate-900 dark:text-slate-100 font-bold text-sm">
                                    <span>Estimasi Net / Bulan:</span>
                                    <span id="breakdown-total" class="data-text text-sky-600 dark:text-sky-400">Rp 0</span>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- BUTTONS NAVIGATION FOOTER -->
                <div class="px-4 sm:px-7 py-3.5 sm:py-4 bg-slate-50/90 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-700/60 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3 shrink-0">
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <button type="button" id="btn-prev" onclick="prevStep()" style="display: none;" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm">
                                <x-ui.icon name="chevron-left" class="w-2.5 h-2.5" /> Sebelumnya
                            </button>
                            <a href="{{ route('customers.index') }}" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-xs font-semibold cursor-pointer focus:outline-none text-center inline-flex items-center justify-center">
                                Batal
                            </a>
                        </div>
                        
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <button type="button" id="btn-next" onclick="nextStep()" class="w-full sm:w-auto px-5 py-2.5 sm:py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm">
                                Lanjut <x-ui.icon name="chevron-right" class="w-2.5 h-2.5" />
                            </button>

                            <button type="submit" id="btn-submit" style="display: none;" class="w-full sm:w-auto px-6 py-2.5 sm:py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm">
                                <x-ui.icon name="save" class="w-3 h-3" /> Simpan Registrasi
                            </button>
                        </div>
                </div>

            </div>

        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    /* ── Wizard Form Stepper & Live Validation Logic ── */
    let currentActiveStep = 1;
    const totalStepsCount = 3;

    const formFields = {
        'data-diri': {
            required: ['full_name', 'identity_number', 'gender', 'primary_phone', 'registration_date', 'pop_id', 'address', 'city_id', 'district_id', 'village_id'],
            optional: ['email', 'alternative_phone', 'npwp', 'latitude', 'longitude']
        },
        'dokumen': {
            required: ['foto_ktp'],
            optional: []
        },
        'layanan': {
            required: ['internet_package_id', 'jenis_kontrak', 'contract_period_months', 'discount_amount'],
            optional: []
        }
    };

    const stepKeys = {
        1: 'data-diri',
        2: 'dokumen',
        3: 'layanan'
    };

    document.addEventListener("DOMContentLoaded", function() {
        const inputs = document.querySelectorAll('#wizard-form input, #wizard-form select, #wizard-form textarea');
        inputs.forEach(input => {
            input.addEventListener('input', runLiveProgressUpdates);
            input.addEventListener('change', runLiveProgressUpdates);
        });

        // Initialize districts for city
        const oldCityId = "{{ old('city_id', \App\Models\City::where('name', 'Ponorogo')->first()->id ?? '') }}";
        const oldDistrictId = "{{ old('district_id') }}";
        const oldVillageId = "{{ old('village_id') }}";

        if (oldCityId) {
            loadDistricts(oldCityId, oldDistrictId, oldVillageId);
        }

        updateWizardButtons();
        runLiveProgressUpdates();
        updateLayananBreakdown();
    });

    /* Dynamic Districts AJAX */
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

    /* Dynamic Villages AJAX */
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
                runLiveProgressUpdates();
            })
            .catch(err => {
                console.error("Gagal memuat desa:", err);
                villageSelect.innerHTML = '<option value="" disabled selected>Gagal memuat desa</option>';
            });
    }

    /* File Change & Preview Helper */
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
                    if (previewImg) previewImg.src = e.target.result;
                    if (defaultPlaceholder) defaultPlaceholder.classList.add('hidden');
                    // Preview pakai inline style karena kontainernya juga ber-class 'flex'
                    // — alasan sama seperti di setElementVisible().
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
        if (input) {
            input.value = '';
            onFileChange(fieldId);
        }
    }

    /* Live Layanan Billing Calculation */
    function updateLayananBreakdown() {
        const select = document.getElementById('internet_package_id');
        const discountInput = document.getElementById('discount_amount');
        
        let basePrice = 0;
        let discount = parseFloat(discountInput ? discountInput.value : 0) || 0;

        if (select && select.selectedIndex >= 0) {
            const selectedOpt = select.options[select.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.price) {
                basePrice = parseFloat(selectedOpt.dataset.price) || 0;
            }
        }

        const netTotal = Math.max(0, basePrice - discount);

        const formatRupiah = (val) => 'Rp ' + new Intl.NumberFormat('id-ID').format(val);

        const baseEl = document.getElementById('breakdown-base-price');
        const discEl = document.getElementById('breakdown-discount');
        const totalEl = document.getElementById('breakdown-total');

        if (baseEl) baseEl.textContent = formatRupiah(basePrice);
        if (discEl) discEl.textContent = '- ' + formatRupiah(discount);
        if (totalEl) totalEl.textContent = formatRupiah(netTotal);
    }

    /* Live Stepper Auditor & Progress Calculator */
    function runLiveProgressUpdates() {
        let totalFieldsCount = 0;
        let filledFieldsCount = 0;

        for (let step = 1; step <= totalStepsCount; step++) {
            const stepKey = stepKeys[step];
            const config = formFields[stepKey];
            let requiredMissing = [];
            let optionalMissing = [];

            config.required.forEach(field => {
                totalFieldsCount++;
                const el = document.getElementById(field);
                if (el) {
                    const isFilePopulated = el.type === 'file' && el.getAttribute('data-populated') === 'true';
                    if (el.value.trim() !== "" || isFilePopulated) {
                        filledFieldsCount++;
                    } else {
                        requiredMissing.push(getLabelName(field));
                    }
                }
            });

            config.optional.forEach(field => {
                totalFieldsCount++;
                const el = document.getElementById(field);
                if (el) {
                    const isFilePopulated = el.type === 'file' && el.getAttribute('data-populated') === 'true';
                    if (el.value.trim() !== "" || isFilePopulated) {
                        filledFieldsCount++;
                    } else {
                        optionalMissing.push(getLabelName(field));
                    }
                }
            });

            updateStepNavStatus(step, requiredMissing, optionalMissing);
        }

        const progressPercentage = totalFieldsCount > 0 ? Math.round((filledFieldsCount / totalFieldsCount) * 100) : 0;
        const pctEl = document.getElementById('progress-percentage');
        const countEl = document.getElementById('filled-fields-count');
        const fillEl = document.getElementById('progress-bar-fill');

        if (pctEl) pctEl.textContent = progressPercentage + '%';
        if (countEl) countEl.textContent = filledFieldsCount;
        if (fillEl) fillEl.style.width = progressPercentage + '%';
    }

    function updateStepNavStatus(step, requiredMissing, optionalMissing) {
        const navBtn = document.getElementById('step-nav-' + step);
        const iconDiv = document.getElementById('step-nav-icon-' + step);
        const statusSpan = document.getElementById('step-nav-status-' + step);
        const missingSpan = document.getElementById('step-nav-missing-' + step);

        if (!navBtn || !iconDiv || !statusSpan || !missingSpan) return;

        if (requiredMissing.length > 0) {
            statusSpan.textContent = 'Belum Lengkap';
            statusSpan.className = 'text-[9px] font-bold block uppercase tracking-wider text-rose-600 dark:text-rose-400 mt-0.5';
            iconDiv.innerHTML = `<span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400"><x-ui.icon name="x" class="w-3 h-3" /></span>`;
            missingSpan.textContent = 'Wajib diisi: ' + requiredMissing.join(', ');

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none";
            }
        } else if (optionalMissing.length > 0) {
            statusSpan.textContent = 'Kekurangan Data';
            statusSpan.className = 'text-[9px] font-bold block uppercase tracking-wider text-amber-600 dark:text-amber-400 mt-0.5';
            iconDiv.innerHTML = `<span class="w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/40 border border-amber-200 dark:border-amber-700 flex items-center justify-center text-amber-600 dark:text-amber-400"><x-ui.icon name="circle-alert" class="w-3 h-3" /></span>`;
            missingSpan.textContent = 'Kurang: ' + optionalMissing.join(', ');

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/40 dark:bg-amber-950/20 hover:bg-amber-50 dark:hover:bg-amber-900/30 transition-all group focus:outline-none";
            }
        } else {
            statusSpan.textContent = 'Lengkap';
            statusSpan.className = 'text-[9px] font-bold block uppercase tracking-wider text-emerald-600 dark:text-emerald-400 mt-0.5';
            iconDiv.innerHTML = `<span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center"><x-ui.icon name="check" class="w-3 h-3" /></span>`;
            missingSpan.textContent = 'Semua data terisi';

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-all group focus:outline-none";
            }
        }

        if (currentActiveStep === step) {
            navBtn.className = "w-full text-left p-3.5 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm";
        }
    }

    function getLabelName(field) {
        const labels = {
            full_name: 'Nama Lengkap',
            identity_number: 'NIK',
            gender: 'Jenis Kelamin',
            primary_phone: 'HP Utama',
            pop_id: 'POP Cabang',
            registration_date: 'Tgl Registrasi',
            address: 'Alamat',
            city_id: 'Kota',
            district_id: 'Kecamatan',
            village_id: 'Desa',
            foto_ktp: 'Foto KTP',
            internet_package_id: 'Paket Internet',
            jenis_kontrak: 'Jenis Kontrak',
            contract_period_months: 'Masa Kontrak',
            discount_amount: 'Diskon'
        };
        return labels[field] || field;
    }

    /* Stepper Page Switcher */
    function goToStep(stepNumber) {
        document.getElementById('step-panel-' + currentActiveStep).classList.add('hidden');
        currentActiveStep = stepNumber;
        document.getElementById('step-panel-' + currentActiveStep).classList.remove('hidden');

        // Update mobile stepper buttons UI
        for (let i = 1; i <= 3; i++) {
            const mBtn = document.getElementById('mobile-step-btn-' + i);
            if (mBtn) {
                if (i === currentActiveStep) {
                    mBtn.className = "py-2.5 px-2 rounded-lg text-xs font-bold bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 transition-all flex items-center justify-center gap-1.5 shadow-sm";
                } else {
                    mBtn.className = "py-2.5 px-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all flex items-center justify-center gap-1.5";
                }
            }
        }

        updateWizardButtons();
        runLiveProgressUpdates();
    }

    /*
     * Aturan tombol wizard — satu-satunya tempat visibilitas tombol ditentukan.
     * Step pertama  : Batal + Lanjut
     * Step tengah   : Sebelumnya + Batal + Lanjut
     * Step terakhir : Sebelumnya + Batal + Simpan Registrasi
     * "Batal" selalu tampil, jadi tidak ikut diatur di sini.
     * Dipanggil juga saat load supaya markup awal tidak jadi sumber kebenaran kedua.
     *
     * Sengaja pakai inline style, BUKAN class 'hidden': tombol-tombol ini sudah
     * memakai utility display 'inline-flex', dan di CSS Tailwind keduanya utility
     * display dengan specificity sama — 'inline-flex' menang, jadi 'hidden' tidak
     * pernah berefek dan semua tombol tampil bersamaan. Inline style selalu menang.
     */
    function updateWizardButtons() {
        const isFirstStep = currentActiveStep === 1;
        const isLastStep = currentActiveStep === totalStepsCount;

        setElementVisible(document.getElementById('btn-prev'), ! isFirstStep);
        setElementVisible(document.getElementById('btn-next'), ! isLastStep);
        setElementVisible(document.getElementById('btn-submit'), isLastStep);
    }

    function setElementVisible(el, visible) {
        if (! el) {
            return;
        }
        // String kosong = balik ke display dari class (inline-flex), bukan 'block'.
        el.style.display = visible ? '' : 'none';
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
