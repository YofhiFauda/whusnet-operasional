<?php $__env->startSection('title', 'Edit Pelanggan - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Edit Data Pelanggan'); ?>
<?php $__env->startSection('breadcrumb_parent', 'Pelanggan'); ?>
<?php $__env->startSection('breadcrumb_parent_url', '/customers'); ?>

<?php $__env->startSection('content'); ?>

<!-- Form Container -->
<form action="/customers/<?php echo e($customer->id); ?>" method="POST" enctype="multipart/form-data" id="wizard-form" class="space-y-6">
    <?php echo csrf_field(); ?>
    <?php echo method_field('PUT'); ?>

    <!-- TOP PANEL: Progress Bar -->
    <div class="bg-surface border border-border rounded-lg p-6 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Kelengkapan Formulir Registrasi</h3>
                <p class="text-xs text-text-muted mt-0.5">Semua data akan divalidasi sebelum disimpan ke database</p>
            </div>
            <div class="text-right">
                <span id="progress-percentage" class="text-sm font-extrabold text-primary data-text">0%</span>
                <span class="text-xs text-text-muted block mt-0.5"><span id="filled-fields-count" class="data-text">0</span> dari <span class="data-text">25</span> field terisi</span>
            </div>
        </div>
        <!-- Progress bar background -->
        <div class="w-full bg-surface-muted rounded-full h-3.5 overflow-hidden border border-border/50">
            <div id="progress-bar-fill" class="bg-gradient-to-r from-primary to-primary-focus h-full w-0 transition-all duration-500 ease-out"></div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">
        
        <!-- LEFT COLUMN: Stepper & Completeness Checklist -->
        <div class="lg:col-span-1 flex flex-col gap-4">
            <div class="bg-surface border border-border rounded-lg p-5 shadow-sm space-y-5">
                <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider">Tahapan Formulir</h4>
                
                <div class="space-y-4">
                    <!-- Step 1 Trigger -->
                    <button type="button" onclick="goToStep(1)" id="step-nav-1" class="w-full text-left p-3.5 rounded-lg border border-primary bg-primary/10 hover:bg-primary/20 transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-1">
                                <!-- Will be inserted by JS -->
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-text-main">1. Data Diri & Wilayah</span>
                                <span id="step-nav-status-1" class="text-[9px] font-bold block mt-1 uppercase tracking-wider">Mengevaluasi...</span>
                                <span id="step-nav-missing-1" class="text-[9px] text-text-muted block mt-1 leading-relaxed whitespace-pre-wrap"></span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 2 Trigger -->
                    <button type="button" onclick="goToStep(2)" id="step-nav-2" class="w-full text-left p-3.5 rounded-lg border border-border bg-surface hover:bg-surface-muted transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-2">
                                <!-- Will be inserted by JS -->
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-text-secondary group-hover:text-text-main">2. Dokumen Lampiran</span>
                                <span id="step-nav-status-2" class="text-[9px] font-bold block mt-1 uppercase tracking-wider">Mengevaluasi...</span>
                                <span id="step-nav-missing-2" class="text-[9px] text-text-muted block mt-1 leading-relaxed whitespace-pre-wrap"></span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 3 Trigger -->
                    <button type="button" onclick="goToStep(3)" id="step-nav-3" class="w-full text-left p-3.5 rounded-lg border border-border bg-surface hover:bg-surface-muted transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-3">
                                <!-- Will be inserted by JS -->
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-text-secondary group-hover:text-text-main">3. Layanan & Paket</span>
                                <span id="step-nav-status-3" class="text-[9px] font-bold block mt-1 uppercase tracking-wider">Mengevaluasi...</span>
                                <span id="step-nav-missing-3" class="text-[9px] text-text-muted block mt-1 leading-relaxed whitespace-pre-wrap"></span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 4 Trigger -->
                    <button type="button" onclick="goToStep(4)" id="step-nav-4" class="w-full text-left p-3.5 rounded-lg border border-border bg-surface hover:bg-surface-muted transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-4">
                                <!-- Will be inserted by JS -->
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-text-secondary group-hover:text-text-main">4. Info Referral</span>
                                <span id="step-nav-status-4" class="text-[9px] font-bold block mt-1 uppercase tracking-wider">Mengevaluasi...</span>
                                <span id="step-nav-missing-4" class="text-[9px] text-text-muted block mt-1 leading-relaxed whitespace-pre-wrap"></span>
                            </div>
                        </div>
                    </button>

                    <!-- Step 5 Trigger -->
                    <button type="button" onclick="goToStep(5)" id="step-nav-5" class="w-full text-left p-3.5 rounded-lg border border-border bg-surface hover:bg-surface-muted transition-all group focus:outline-none">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0" id="step-nav-icon-5">
                                <!-- Will be inserted by JS -->
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs font-bold text-text-secondary group-hover:text-text-main">5. Operasional & Teknis</span>
                                <span id="step-nav-status-5" class="text-[9px] font-bold block mt-1 uppercase tracking-wider">Mengevaluasi...</span>
                                <span id="step-nav-missing-5" class="text-[9px] text-text-muted block mt-1 leading-relaxed whitespace-pre-wrap"></span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Wizard Steps Form Panels -->
        <div class="lg:col-span-3 bg-surface border border-border rounded-lg shadow-sm overflow-hidden min-h-[480px] flex flex-col justify-between">
            
            <!-- FORM BODY -->
            <div class="p-6 md:p-8 flex-1">
                
                <!-- Errors Block ditangani otomatis oleh global Component Toast (x-toast) -->

                <!-- STEP 1 PANEL: Data Diri & Wilayah -->
                <div id="step-panel-1" class="step-panel space-y-6">
                    <div class="border-b border-border pb-3 mb-6">
                        <h4 class="text-sm font-bold text-text-main uppercase tracking-wider">1. IDENTITAS PELANGGAN & ALAMAT</h4>
                        <p class="text-xs text-text-muted mt-1">Ubah data diri dan wilayah instalasi pelanggan jika diperlukan</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-text-secondary">
                        <div>
                            <label for="full_name" class="block mb-2 uppercase tracking-wide">NAMA LENGKAP <span class="text-red-500">*</span></label>
                            <input type="text" name="full_name" id="full_name" value="<?php echo e(old('full_name', $customer->full_name)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: Budi Santoso">
                        </div>

                        <div>
                            <label for="identity_number" class="block mb-2 uppercase tracking-wide">NOMOR IDENTITAS (NIK) <span class="text-red-500">*</span></label>
                            <input type="text" name="identity_number" id="identity_number" value="<?php echo e(old('identity_number', $customer->identity_number)); ?>" maxlength="16" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 16);" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: 3502182039200001">
                        </div>

                        <div>
                            <label for="gender" class="block mb-2 uppercase tracking-wide">JENIS KELAMIN <span class="text-red-500">*</span></label>
                            <select name="gender" id="gender" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                                <option value="" disabled>Pilih Jenis Kelamin</option>
                                <option value="Laki-laki" <?php echo e(old('gender', $customer->gender?->value) === 'Laki-laki' ? 'selected' : ''); ?>>Laki-laki</option>
                                <option value="Perempuan" <?php echo e(old('gender', $customer->gender?->value) === 'Perempuan' ? 'selected' : ''); ?>>Perempuan</option>
                            </select>
                        </div>

                        <div>
                            <label for="primary_phone" class="block mb-2 uppercase tracking-wide">NOMOR HP UTAMA <span class="text-red-500">*</span></label>
                            <input type="text" name="primary_phone" id="primary_phone" value="<?php echo e(old('primary_phone', $customer->primary_phone ?? $customer->phone)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: 082139xxxxxx">
                        </div>

                        <div>
                            <label for="alternative_phone" class="block mb-2 uppercase tracking-wide">NOMOR HP ALTERNATIF</label>
                            <input type="text" name="alternative_phone" id="alternative_phone" value="<?php echo e(old('alternative_phone', $customer->alternative_phone)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: 082139xxxxxx">
                        </div>

                        <div>
                            <label for="email" class="block mb-2 uppercase tracking-wide">ALAMAT EMAIL</label>
                            <input type="email" name="email" id="email" value="<?php echo e(old('email', $customer->email)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: budi@gmail.com">
                        </div>

                        <div>
                            <label for="registration_date" class="block mb-2 uppercase tracking-wide">TANGGAL REGISTRASI <span class="text-red-500">*</span></label>
                            <input type="date" name="registration_date" id="registration_date" value="<?php echo e(old('registration_date', $customer->registration_date ? $customer->registration_date->format('Y-m-d') : '')); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                        </div>

                        <div>
                            <label for="pop_id" class="block mb-2 uppercase tracking-wide">POP CABANG <span class="text-red-500">*</span></label>
                            <select name="pop_id" id="pop_id" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                                <option value="" disabled selected>Pilih POP Cabang</option>
                                <?php $__currentLoopData = $pops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($pop->id); ?>" <?php echo e(old('pop_id', $customer->pop_id) == $pop->id ? 'selected' : ''); ?>><?php echo e($pop->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <p class="text-[10px] text-text-muted mt-1">Mini POP & Distribusi diatur terpisah lewat modal "Atur Mini POP & Distribusi" di halaman detail pelanggan (pasca pemasangan).</p>
                        </div>

                        <div>
                            <label for="distribution_id" class="block mb-2 uppercase tracking-wide">KODE DISTRIBUSI (ODP)</label>
                            <select name="distribution_id" id="distribution_id" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                                <option value="">Pilih Kode Distribusi</option>
                                <?php $__currentLoopData = $distributions ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dist): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($dist->id); ?>" <?php echo e(old('distribution_id', $customer->distribution_id) == $dist->id ? 'selected' : ''); ?>><?php echo e($dist->code); ?> - <?php echo e($dist->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label for="address" class="block mb-2 uppercase tracking-wide">ALAMAT INSTALASI LENGKAP <span class="text-red-500">*</span></label>
                            <textarea name="address" id="address" rows="2" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Nama Jalan, RT/RW, nomor rumah, detail lainnya..."><?php echo e(old('address', $customer->address)); ?></textarea>
                        </div>

                        <!-- Region Selection -->
                        <div>
                            <label for="city_id" class="block mb-2 uppercase tracking-wide">KOTA <span class="text-red-500">*</span></label>
                            <select name="city_id" id="city_id" onchange="loadDistricts(this.value)" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                                <option value="" disabled selected>Pilih Kota</option>
                                <?php $__currentLoopData = $cities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $city): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($city->id); ?>" <?php echo e(old('city_id', $customer->city_id) == $city->id ? 'selected' : ''); ?>><?php echo e($city->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>

                        <div>
                            <label for="district_id" class="block mb-2 uppercase tracking-wide">KECAMATAN <span class="text-red-500">*</span></label>
                            <select name="district_id" id="district_id" onchange="loadVillages(this.value)" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                                <option value="" disabled>Pilih Kecamatan (Pilih Kota Dulu)</option>
                            </select>
                        </div>

                        <div>
                            <label for="village_id" class="block mb-2 uppercase tracking-wide">DESA / KELURAHAN <span class="text-red-500">*</span></label>
                            <select name="village_id" id="village_id" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                                <option value="" disabled selected>Pilih Desa</option>
                                <!-- Async Populated -->
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4 md:col-span-1">
                            <div>
                                <label for="latitude" class="block mb-2 uppercase tracking-wide">LATITUDE</label>
                                <input type="text" name="latitude" id="latitude" value="<?php echo e(old('latitude', $customer->latitude)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="-7.86940">
                            </div>
                            <div>
                                <label for="longitude" class="block mb-2 uppercase tracking-wide">LONGITUDE</label>
                                <input type="text" name="longitude" id="longitude" value="<?php echo e(old('longitude', $customer->longitude)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="111.46210">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2 PANEL: Dokumen Lampiran -->
                <div id="step-panel-2" class="step-panel space-y-6 hidden">
                    <div class="border-b border-border pb-3 mb-6">
                        <h4 class="text-sm font-bold text-text-main uppercase tracking-wider">2. UPLOAD DOKUMEN LAMPIRAN</h4>
                        <p class="text-xs text-text-muted mt-1">Ubah lampiran dokumen pendukung pelanggan (opsional)</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Foto Rumah -->
                        <div class="border border-border rounded-lg p-5 flex flex-col justify-between hover:border-primary/50 transition-colors shadow-sm relative">
                            <input type="hidden" name="delete_foto_rumah" id="delete_foto_rumah" value="0">
                            <div id="default-placeholder-foto_rumah" class="text-center py-4 <?php if($customer->foto_rumah): ?> hidden <?php endif; ?>">
                                <svg class="mx-auto h-10 w-10 text-border" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                <span class="block text-xs font-bold text-text-main mt-3">FOTO RUMAH</span>
                                <span class="block text-[10px] text-text-muted mt-1">Format: JPG, PNG (Max 2MB)</span>
                            </div>

                            <!-- Preview Container -->
                            <div id="preview-container-foto_rumah" class="<?php if(!$customer->foto_rumah): ?> hidden <?php endif; ?> text-center py-2 flex flex-col items-center justify-center">
                                <div class="relative inline-block">
                                    <img id="preview-img-foto_rumah" class="max-h-28 max-w-full rounded-lg object-contain border border-border shadow-sm" src="<?php echo e($customer->foto_rumah ? asset('storage/' . $customer->foto_rumah) : ''); ?>" alt="Preview Foto Rumah">
                                    <button type="button" onclick="clearFile('foto_rumah')" class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-1 shadow-md hover:scale-105 transition-transform focus:outline-none" title="Hapus File">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <span class="block text-xs font-bold text-text-main mt-2">PREVIEW FOTO RUMAH</span>
                            </div>

                            <div class="mt-4">
                                <input type="file" name="foto_rumah" id="foto_rumah" accept="image/*" capture="environment" class="hidden" onchange="onFileChange('foto_rumah')" <?php if($customer->foto_rumah): ?> data-populated="true" <?php endif; ?>>
                                <label for="foto_rumah" class="block w-full text-center bg-surface-muted border border-border hover:bg-surface-muted/80 text-text-secondary text-xs font-semibold py-2 px-3 rounded cursor-pointer transition-colors">
                                    Pilih File
                                </label>
                                <span id="file-label-foto_rumah" class="block text-[10px] text-text-muted text-center mt-2 font-mono truncate">
                                    <?php if($customer->foto_rumah): ?>
                                        <?php echo e(basename($customer->foto_rumah)); ?>

                                    <?php else: ?>
                                        Belum ada file dipilih
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Foto Kontrak -->
                        <div class="border border-border rounded-lg p-5 flex flex-col justify-between hover:border-primary/50 transition-colors shadow-sm relative">
                            <input type="hidden" name="delete_foto_kontrak" id="delete_foto_kontrak" value="0">
                            <div id="default-placeholder-foto_kontrak" class="text-center py-4 <?php if($customer->foto_kontrak): ?> hidden <?php endif; ?>">
                                <svg class="mx-auto h-10 w-10 text-border" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="block text-xs font-bold text-text-main mt-3">FOTO KONTRAK</span>
                                <span class="block text-[10px] text-text-muted mt-1">Format: JPG, PNG, PDF (Max 2MB)</span>
                            </div>

                            <!-- Preview Container -->
                            <div id="preview-container-foto_kontrak" class="<?php if(!$customer->foto_kontrak): ?> hidden <?php endif; ?> text-center py-2 flex flex-col items-center justify-center">
                                <div class="relative inline-block">
                                    <img id="preview-img-foto_kontrak" class="max-h-28 max-w-full rounded-lg object-contain border border-border shadow-sm <?php if($customer->foto_kontrak && Str::endsWith(strtolower($customer->foto_kontrak), '.pdf')): ?> hidden <?php endif; ?>" src="<?php echo e($customer->foto_kontrak && !Str::endsWith(strtolower($customer->foto_kontrak), '.pdf') ? asset('storage/' . $customer->foto_kontrak) : ''); ?>" alt="Preview Foto Kontrak">
                                    
                                    <!-- PDF Icon Preview -->
                                    <div id="preview-pdf-foto_kontrak" class="h-28 w-28 bg-red-500/10 border border-red-500/20 rounded-lg flex flex-col items-center justify-center text-red-500 shadow-sm <?php if(!$customer->foto_kontrak || !Str::endsWith(strtolower($customer->foto_kontrak), '.pdf')): ?> hidden <?php endif; ?>">
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
                                <span class="block text-xs font-bold text-text-main mt-2">PREVIEW FOTO KONTRAK</span>
                            </div>

                            <div class="mt-4">
                                <input type="file" name="foto_kontrak" id="foto_kontrak" accept="image/*,application/pdf" capture="environment" class="hidden" onchange="onFileChange('foto_kontrak')" <?php if($customer->foto_kontrak): ?> data-populated="true" <?php endif; ?>>
                                <label for="foto_kontrak" class="block w-full text-center bg-surface-muted border border-border hover:bg-surface-muted/80 text-text-secondary text-xs font-semibold py-2 px-3 rounded cursor-pointer transition-colors">
                                    Pilih File
                                </label>
                                <span id="file-label-foto_kontrak" class="block text-[10px] text-text-muted text-center mt-2 font-mono truncate">
                                    <?php if($customer->foto_kontrak): ?>
                                        <?php echo e(basename($customer->foto_kontrak)); ?>

                                    <?php else: ?>
                                        Belum ada file dipilih
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3 PANEL: Layanan & Paket -->
                <div id="step-panel-3" class="step-panel space-y-6 hidden">
                    <div class="border-b border-border pb-3 mb-6">
                        <h4 class="text-sm font-bold text-text-main uppercase tracking-wider">3. LAYANAN & PAKET LAYANAN INTERNET</h4>
                        <p class="text-xs text-text-muted mt-1">Ubah paket internet dan rincian parameter kontrak berlangganan</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-text-secondary">
                        <div>
                            <label class="block mb-2 uppercase tracking-wide" for="internet_package_id">PAKET INTERNET <span class="text-red-500">*</span></label>
                            <select name="internet_package_id" id="internet_package_id" onchange="updateLayananBreakdown()" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                                <option value="" disabled>Pilih Paket Internet</option>
                                <?php $__currentLoopData = $packages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($package->id); ?>" data-price="<?php echo e($package->monthly_price); ?>" <?php echo e(old('internet_package_id', $customer->internet_package_id) == $package->id ? 'selected' : ''); ?>><?php echo e($package->package_code); ?> - <?php echo e($package->name); ?> (Rp <?php echo e(number_format($package->monthly_price, 0, ',', '.')); ?>/bln)</option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>

                        <div>
                            <label class="block mb-2 uppercase tracking-wide" for="contract_period_months">MASA KONTRAK (BULAN) <span class="text-red-500">*</span></label>
                            <input type="number" name="contract_period_months" id="contract_period_months" value="<?php echo e(old('contract_period_months', $customer->contract_period_months)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: 12">
                        </div>

                        <div>
                            <label class="block mb-2 uppercase tracking-wide" for="discount_amount">DISKON PROMOSI (RP) <span class="text-red-500">*</span></label>
                            
                            <input type="text" inputmode="decimal" data-rupiah name="discount_amount" id="discount_amount" oninput="updateLayananBreakdown()" value="<?php echo e(old('discount_amount', \App\Helpers\FormatHelper::rupiahInput($customer->discount_amount))); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: 10000">
                        </div>

                        <div>
                            <label class="block mb-2 uppercase tracking-wide" for="tax_percent">PPN (%) <span class="text-red-500">*</span></label>
                            <input type="number" name="tax_percent" id="tax_percent" oninput="updateLayananBreakdown()" value="<?php echo e(old('tax_percent', $customer->tax_percent)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: 11">
                        </div>

                        <div>
                            <label class="block mb-2 uppercase tracking-wide" for="other_fee">BIAYA LAIN DI LUAR STANDAR (RP)</label>
                            <input type="text" inputmode="decimal" data-rupiah name="other_fee" id="other_fee" oninput="updateLayananBreakdown()" value="<?php echo e(old('other_fee', \App\Helpers\FormatHelper::rupiahInput($customer->customerService?->other_fee ?? 0))); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: 11000">
                        </div>
                    </div>

                    <!-- Dynamic Pricing Calculations Preview -->
                    <div class="border border-border rounded-lg p-5 bg-surface-muted/20 mt-6">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-3">Rincian Estimasi Biaya Bulanan</span>
                        <div class="space-y-2 max-w-sm text-xs font-mono data-text">
                            <div class="flex justify-between">
                                <span class="text-text-muted">Harga Paket Dasar:</span>
                                <span class="text-text-secondary" id="preview-base-price">Rp 0,00</span>
                            </div>
                            <div class="flex justify-between text-green-600">
                                <span>Diskon Promosi:</span>
                                <span id="preview-discount">- Rp 0,00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-text-muted" id="preview-tax-label">PPN (11%):</span>
                                <span class="text-text-secondary" id="preview-tax">Rp 0,00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-text-muted">Biaya Lain di luar Standar:</span>
                                <span class="text-text-secondary" id="preview-other-fee">Rp 0,00</span>
                            </div>
                            <hr class="border-dashed border-border">
                            <div class="flex justify-between text-sm font-bold text-text-main">
                                <span>Total Biaya Bulanan:</span>
                                <span id="preview-total-monthly">Rp 0,00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4 PANEL: Referral -->
                <div id="step-panel-4" class="step-panel space-y-6 hidden">
                    <div class="border-b border-border pb-3 mb-6">
                        <h4 class="text-sm font-bold text-text-main uppercase tracking-wider">4. INFORMASI REFERRAL & AKUISISI</h4>
                        <p class="text-xs text-text-muted mt-1">Ubah kode sales, agen, atau kode pelanggan yang mereferensikan</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs font-semibold text-text-secondary">
                        <div>
                            <label for="sales_code" class="block mb-2 uppercase tracking-wide">KODE / ID SALES</label>
                            <input type="text" name="sales_code" id="sales_code" value="<?php echo e(old('sales_code', $customer->sales_code)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: SLS-043">
                        </div>

                        <div>
                            <label for="agent_code" class="block mb-2 uppercase tracking-wide">KODE / ID AGENT</label>
                            <input type="text" name="agent_code" id="agent_code" value="<?php echo e(old('agent_code', $customer->agent_code)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: AGT-012">
                        </div>

                        <div>
                            <label for="referral_customer_code" class="block mb-2 uppercase tracking-wide">ID REFERRAL PELANGGAN</label>
                            <input type="text" name="referral_customer_code" id="referral_customer_code" value="<?php echo e(old('referral_customer_code', $customer->referral_customer_code)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: CID-2026-0005">
                        </div>
                    </div>
                </div>

                <!-- STEP 5 PANEL: Operasional Awal & Teknis -->
                <div id="step-panel-5" class="step-panel space-y-6 hidden">
                    <div class="border-b border-border pb-3 mb-6">
                        <h4 class="text-sm font-bold text-text-main uppercase tracking-wider">5. PENYETELAN OPERASIONAL & PARAMETER TEKNIS</h4>
                        <p class="text-xs text-text-muted mt-1">Ubah status alur kerja dan konfigurasi data perangkat ONT / Jaringan</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-text-secondary">
                        <div>
                            <label for="status" class="block mb-2 uppercase tracking-wide">STATUS ALUR KERJA <span class="text-red-500">*</span></label>
                            <select name="status" id="status" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                                <option value="registered" <?php echo e(old('status', $customer->status) === 'registered' ? 'selected' : ''); ?>>Registered (Baru Terdaftar)</option>
                                <option value="waiting_survey" <?php echo e(old('status', $customer->status) === 'waiting_survey' ? 'selected' : ''); ?>>Waiting Survey (Menunggu Survey)</option>
                                <option value="surveyed" <?php echo e(old('status', $customer->status) === 'surveyed' ? 'selected' : ''); ?>>Surveyed (Selesai Survey)</option>
                                <option value="waiting_installation" <?php echo e(old('status', $customer->status) === 'waiting_installation' ? 'selected' : ''); ?>>Waiting Installation (Menunggu Pemasangan)</option>
                                <option value="installed" <?php echo e(old('status', $customer->status) === 'installed' ? 'selected' : ''); ?>>Installed (Selesai Pemasangan)</option>
                                <option value="active" <?php echo e(old('status', $customer->status) === 'active' ? 'selected' : ''); ?>>Active (Aktif Berlangganan)</option>
                                <option value="suspended" <?php echo e(old('status', $customer->status) === 'suspended' ? 'selected' : ''); ?>>Suspended (Diisolir Sementara)</option>
                            </select>
                        </div>

                        <div>
                            <label for="ont_sn" class="block mb-2 uppercase tracking-wide">SERIAL NUMBER (SN) ONT</label>
                            <input type="text" name="ont_sn" id="ont_sn" value="<?php echo e(old('ont_sn', $customer->ont_sn)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: ZTEG12345678">
                        </div>

                        <div>
                            <label for="ip_address" class="block mb-2 uppercase tracking-wide">IP ADDRESS DIAL-UP</label>
                            <input type="text" name="ip_address" id="ip_address" value="<?php echo e(old('ip_address', $customer->ip_address)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: 10.200.45.10">
                        </div>

                        <div>
                            <label for="odp_code" class="block mb-2 uppercase tracking-wide">KODE / KOTAK ODP</label>
                            <input type="text" name="odp_code" id="odp_code" value="<?php echo e(old('odp_code', $customer->odp_code)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: ODP-PON-024">
                        </div>

                        <div>
                            <label for="olt_code" class="block mb-2 uppercase tracking-wide">NAMA / KODE PERANGKAT OLT
                                <span class="text-text-muted font-normal normal-case text-[10px] ml-1">(label perangkat, bukan untuk CID)</span>
                            </label>
                            <input type="text" name="olt_code" id="olt_code" value="<?php echo e(old('olt_code', $customer->olt_code)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: OLT-ZTE-C320">
                            <p class="text-[10px] text-text-muted mt-1">Label nama perangkat OLT. <strong class="text-warning">Nomor OLT untuk CID diisi teknisi saat survei/pemasangan.</strong></p>
                        </div>

                        <div>
                            <label for="vlan_id" class="block mb-2 uppercase tracking-wide">VLAN ID</label>
                            <input type="text" name="vlan_id" id="vlan_id" value="<?php echo e(old('vlan_id', $customer->vlan_id)); ?>" class="w-full text-sm font-sans px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" placeholder="Contoh: 1024">
                        </div>
                    </div>
                </div>

            </div>

            <!-- BUTTONS NAVIGATION FOOTER -->
            <div class="px-6 py-4 bg-surface-muted/50 border-t border-border flex items-center justify-between">
                <div>
                    <button type="button" id="btn-prev" onclick="prevStep()" class="px-4 py-2 border border-border rounded-md bg-surface text-text-secondary hover:bg-surface-muted transition-colors text-xs font-semibold cursor-pointer hidden focus:outline-none">
                        Sebelumnya
                    </button>
                </div>
                
                <div class="flex gap-2">
                    <a href="/customers/<?php echo e($customer->id); ?>" class="px-4 py-2 border border-border rounded-md bg-surface text-text-secondary hover:bg-surface-muted transition-colors text-xs font-semibold cursor-pointer focus:outline-none">
                        Batal
                    </a>
                    
                    <button type="button" id="btn-next" onclick="nextStep()" class="px-4 py-2 bg-primary hover:bg-primary-focus text-white rounded-md transition-colors text-xs font-semibold cursor-pointer focus:outline-none">
                        Lanjut
                    </button>

                    <button type="submit" id="btn-submit" class="px-4 py-2 bg-primary hover:bg-primary-focus text-white rounded-md transition-colors text-xs font-semibold cursor-pointer hidden focus:outline-none">
                        Simpan Perubahan
                    </button>
                </div>
            </div>

        </div>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    let currentActiveStep = 1;
    const totalStepsCount = 5;

    // Define wizard fields configurations
    const formFields = {
        'data-diri': {
            required: ['full_name', 'identity_number', 'gender', 'primary_phone', 'registration_date', 'pop_id', 'address', 'city_id', 'district_id', 'village_id'],
            optional: ['email', 'alternative_phone', 'latitude', 'longitude']
        },
        'dokumen': {
            required: [],
            optional: ['foto_rumah', 'foto_kontrak']
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
        updateLayananBreakdown();
    });

    // Populate active city/district
    const activeCityId = "<?php echo e(old('city_id', $customer->city_id)); ?>";
    const activeDistrictId = "<?php echo e(old('district_id', $customer->district_id)); ?>";
    const activeVillageId = "<?php echo e(old('village_id', $customer->village_id)); ?>";
    
    if (activeCityId) {
        loadDistricts(activeCityId, activeDistrictId, activeVillageId);
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

        // Reset the delete flag to 0 if a new file is uploaded
        const deleteInput = document.getElementById('delete_' + fieldId);
        if (deleteInput && input.files && input.files.length > 0) {
            deleteInput.value = '0';
        }

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
            // Only reset label & preview if it's not populated from DB OR delete flag is set to 1
            const isDeleted = deleteInput && deleteInput.value === '1';
            if (isDeleted || !input.hasAttribute('data-populated')) {
                label.textContent = "Belum ada file dipilih";
                input.removeAttribute('data-populated');
                if (defaultPlaceholder) defaultPlaceholder.classList.remove('hidden');
                if (previewContainer) previewContainer.classList.add('hidden');
                if (previewImg) {
                    previewImg.src = '';
                }
            }
        }
        runLiveProgressUpdates();
    }

    // Clear file selection helper
    function clearFile(fieldId) {
        const input = document.getElementById(fieldId);
        if (input) {
            input.value = '';
            // set delete flag to 1
            const deleteInput = document.getElementById('delete_' + fieldId);
            if (deleteInput) {
                deleteInput.value = '1';
            }
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

        // Diskon & biaya lain bermasking ribuan — parseFloat('10.000') = 10.
        // `tax_percent` TIDAK dimasking (persen), jadi tetap parseFloat.
        const angka = (el) => (el && window.Rupiah ? window.Rupiah.angka(el.value) : parseFloat(el ? el.value : 0)) || 0;

        const discount = angka(discountInput);
        const taxPercent = parseFloat(taxInput.value) || 0;
        const otherFeeInput = document.getElementById('other_fee');
        const otherFee = angka(otherFeeInput);

        const taxable = Math.max(0, basePrice - discount);
        const tax = Math.round(taxable * (taxPercent / 100));
        const total = taxable + tax + otherFee;

        // Render preview texts
        document.getElementById('preview-base-price').textContent = formatRupiah(basePrice);
        document.getElementById('preview-discount').textContent = '- ' + formatRupiah(discount);
        document.getElementById('preview-tax-label').textContent = `PPN (${taxPercent}%):`;
        document.getElementById('preview-tax').textContent = formatRupiah(tax);
        const otherFeeEl = document.getElementById('preview-other-fee');
        if (otherFeeEl) {
            otherFeeEl.textContent = formatRupiah(otherFee);
        }
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
            statusSpan.className = 'text-[9px] font-bold block mt-1 uppercase tracking-wider text-red-500';
            
            // Red warning icon (SVG)
            iconDiv.innerHTML = `<span class="h-5 w-5 rounded-full bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-500">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </span>`;

            missingSpan.textContent = 'Wajib diisi: ' + requiredMissing.join(', ');
            
            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-red-500/20 bg-red-500/5 hover:bg-red-500/10 transition-all group focus:outline-none";
            }
        } else if (optionalMissing.length > 0) {
            // State: Kekurangan Data (Amber Warning)
            statusSpan.textContent = 'Kekurangan Data';
            statusSpan.className = 'text-[9px] font-bold block mt-1 uppercase tracking-wider text-amber-500';

            // Amber alert icon (SVG)
            iconDiv.innerHTML = `<span class="h-5 w-5 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </span>`;

            missingSpan.textContent = 'Kurang: ' + optionalMissing.join(', ');

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-amber-500/20 bg-amber-500/5 hover:bg-amber-500/10 transition-all group focus:outline-none";
            }
        } else {
            // State: Lengkap (Green Check)
            statusSpan.textContent = 'Lengkap';
            statusSpan.className = 'text-[9px] font-bold block mt-1 uppercase tracking-wider text-green-600';

            // Green check icon (SVG)
            iconDiv.innerHTML = `<span class="h-5 w-5 rounded-full bg-green-600 flex items-center justify-center text-white">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </span>`;

            missingSpan.textContent = 'Semua data terisi';

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-lg border border-green-500/20 bg-green-500/5 hover:bg-green-500/10 transition-all group focus:outline-none";
            }
        }

        // Highlight active step specifically
        if (currentActiveStep === step) {
            navBtn.className = "w-full text-left p-3.5 rounded-lg border-2 border-primary bg-primary/10 transition-all group focus:outline-none";
        }
    }

    // Field names translator helper
    function getLabelName(field) {
        const labels = {
            full_name: 'Nama Lengkap',
            identity_number: 'NIK',
            gender: 'Jenis Kelamin',
            phone: 'Nomor HP',
            primary_phone: 'Nomor HP Utama',
            alternative_phone: 'Nomor HP Alternatif',
            pop_id: 'POP Cabang',
            email: 'Email',
            registration_date: 'Tgl Registrasi',
            address: 'Alamat',
            city_id: 'Kota',
            district_id: 'Kecamatan',
            village_id: 'Desa',
            latitude: 'Latitude',
            longitude: 'Longitude',
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
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/edit.blade.php ENDPATH**/ ?>