<?php $__env->startSection('title', 'Ubah POP - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Ubah POP / Cabang'); ?>

<?php $__env->startSection('content'); ?>
<!-- Back link and Title Header -->
<div class="mb-6 flex items-center justify-between">
    <a href="<?php echo e(route('master.pop.index')); ?>" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Daftar POP
    </a>
</div>

<!-- Form Container -->
<form action="<?php echo e(route('master.pop.update', $pop)); ?>" method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <?php echo csrf_field(); ?>
    <?php echo method_field('PUT'); ?>
    
    <!-- Left Panel: Data Utama -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm lg:col-span-2 space-y-5">
        <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Informasi Utama POP</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Ubah kode unik, nama, tipe, parent, dan status keaktifan POP.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Kode POP -->
            <div>
                <label for="code" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Kode POP <span class="text-rose-500">*</span></label>
                <input type="text" name="code" id="code" value="<?php echo e(old('code', $pop->code)); ?>" required placeholder="Contoh: POP-SMN-01"
                       class="w-full px-3 py-2 border <?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                <?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <!-- Nama POP -->
            <div>
                <label for="name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Nama POP <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="name" value="<?php echo e(old('name', $pop->name)); ?>" required placeholder="Contoh: POP Cabang Sleman"
                       class="w-full px-3 py-2 border <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-slate-100 dark:border-slate-700/50 pt-5">
            <!-- Kode Identifier POP -->
            <div>
                <label for="pop_code" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Kode Identifier POP <span class="text-rose-500">*</span></label>
                <input type="text" name="pop_code" id="pop_code" value="<?php echo e(old('pop_code', $pop->pop_code)); ?>" required placeholder="Contoh: SMN"
                       class="w-full px-3 py-2 border <?php $__errorArgs = ['pop_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 uppercase">
                <?php $__errorArgs = ['pop_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <!-- Prefix ID Request -->
            <div>
                <label for="registration_prefix" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Prefix ID Request <span class="text-rose-500">*</span></label>
                <input type="text" name="registration_prefix" id="registration_prefix" value="<?php echo e(old('registration_prefix', $pop->registration_prefix)); ?>" required placeholder="Contoh: C"
                       class="w-full px-3 py-2 border <?php $__errorArgs = ['registration_prefix'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 uppercase">
                <?php $__errorArgs = ['registration_prefix'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <!-- Prefix CID -->
            <div>
                <label for="cid_prefix" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Prefix CID <span class="text-rose-500">*</span></label>
                <input type="text" name="cid_prefix" id="cid_prefix" value="<?php echo e(old('cid_prefix', $pop->cid_prefix)); ?>" required placeholder="Contoh: D"
                       class="w-full px-3 py-2 border <?php $__errorArgs = ['cid_prefix'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 uppercase">
                <?php $__errorArgs = ['cid_prefix'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Tipe POP -->
            <div>
                <label for="type" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Tipe POP <span class="text-rose-500">*</span></label>
                <select name="type" id="type" required 
                        class="w-full px-3 py-2 border <?php $__errorArgs = ['type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 bg-white dark:bg-slate-800">
                    <option value="pusat" <?php echo e(old('type', $pop->type) === 'pusat' ? 'selected' : ''); ?>>Pusat</option>
                    <option value="cabang" <?php echo e(old('type', $pop->type) === 'cabang' ? 'selected' : ''); ?>>Cabang</option>
                    <option value="mini_pop" <?php echo e(old('type', $pop->type) === 'mini_pop' ? 'selected' : ''); ?>>Mini POP</option>
                </select>
                <?php $__errorArgs = ['type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <!-- Parent POP -->
            <div>
                <label for="parent_id" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Parent POP</label>
                <select name="parent_id" id="parent_id" 
                        class="w-full px-3 py-2 border <?php $__errorArgs = ['parent_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 bg-white dark:bg-slate-800">
                    <option value="">-- Tanpa Parent (POP Utama) --</option>
                    <?php $__currentLoopData = $parents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($parent->id); ?>" <?php echo e(old('parent_id', $pop->parent_id) == $parent->id ? 'selected' : ''); ?>>
                            <?php echo e($parent->name); ?> (<?php echo e($parent->code); ?>)
                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <?php $__errorArgs = ['parent_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-slate-100 dark:border-slate-700/50 pt-5">
            <!-- Status Keaktifan -->
            <div>
                <label for="status" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Status POP <span class="text-rose-500">*</span></label>
                <select name="status" id="status" required 
                        class="w-full px-3 py-2 border <?php $__errorArgs = ['status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 bg-white dark:bg-slate-800">
                    <option value="active" <?php echo e(old('status', $pop->status) === 'active' ? 'selected' : ''); ?>>Aktif</option>
                    <option value="inactive" <?php echo e(old('status', $pop->status) === 'inactive' ? 'selected' : ''); ?>>Nonaktif</option>
                </select>
                <?php $__errorArgs = ['status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>

        <div class="border-t border-slate-100 dark:border-slate-700/50 pt-5 space-y-4">
            <div class="pb-1">
                <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Penanggung Jawab (PIC)</h3>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">Kontak person yang bertanggung jawab penuh terhadap POP ini.</p>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Nama PIC -->
                <div>
                    <label for="pic_name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Nama Lengkap PIC</label>
                    <input type="text" name="pic_name" id="pic_name" value="<?php echo e(old('pic_name', $pop->pic_name)); ?>" placeholder="Contoh: Ahmad Subardjo"
                           class="w-full px-3 py-2 border <?php $__errorArgs = ['pic_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                    <?php $__errorArgs = ['pic_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <!-- Kontak PIC -->
                <div>
                    <label for="pic_phone" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">No. HP / WA PIC</label>
                    <input type="text" name="pic_phone" id="pic_phone" value="<?php echo e(old('pic_phone', $pop->pic_phone)); ?>" placeholder="Contoh: 08123456789"
                           class="w-full px-3 py-2 border <?php $__errorArgs = ['pic_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                    <?php $__errorArgs = ['pic_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Panel: Lokasi & Wilayah -->
    <div class="space-y-6 lg:col-span-1">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm space-y-4">
            <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Detail Alamat & Lokasi</h3>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Ubah alamat fisik dan koordinat geografis presisi POP.</p>
            </div>

            <!-- Alamat Lengkap -->
            <div>
                <label for="address" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Alamat Lengkap</label>
                <textarea name="address" id="address" rows="3" placeholder="Jl. Diponegoro No. 23, RT 02/05..."
                          class="w-full px-3 py-2 border <?php $__errorArgs = ['address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1"><?php echo e(old('address', $pop->address)); ?></textarea>
                <?php $__errorArgs = ['address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <!-- Wilayah Administratif -->
            <div class="space-y-3">
                <div>
                    <label for="city" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Kota / Kabupaten</label>
                    <input type="text" name="city" id="city" value="<?php echo e(old('city', $pop->city)); ?>" placeholder="Sleman"
                           class="w-full px-3 py-2 border <?php $__errorArgs = ['city'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                    <?php $__errorArgs = ['city'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label for="district" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Kecamatan</label>
                    <input type="text" name="district" id="district" value="<?php echo e(old('district', $pop->district)); ?>" placeholder="Depok"
                           class="w-full px-3 py-2 border <?php $__errorArgs = ['district'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                    <?php $__errorArgs = ['district'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label for="village" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Desa / Kelurahan</label>
                    <input type="text" name="village" id="village" value="<?php echo e(old('village', $pop->village)); ?>" placeholder="Caturtunggal"
                           class="w-full px-3 py-2 border <?php $__errorArgs = ['village'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                    <?php $__errorArgs = ['village'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
            </div>

            <!-- Koordinat Geografis -->
            <div class="grid grid-cols-2 gap-3 border-t border-slate-100 dark:border-slate-700/50 pt-4">
                <div>
                    <label for="latitude" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Latitude</label>
                    <input type="text" name="latitude" id="latitude" value="<?php echo e(old('latitude', $pop->latitude)); ?>" placeholder="-7.782"
                           class="w-full px-3 py-2 border <?php $__errorArgs = ['latitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                    <?php $__errorArgs = ['latitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label for="longitude" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Longitude</label>
                    <input type="text" name="longitude" id="longitude" value="<?php echo e(old('longitude', $pop->longitude)); ?>" placeholder="110.372"
                           class="w-full px-3 py-2 border <?php $__errorArgs = ['longitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                    <?php $__errorArgs = ['longitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
            </div>
        </div>

        <!-- Submit Actions -->
        <div class="flex items-center gap-3">
            <a href="<?php echo e(route('master.pop.index')); ?>" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 transition-colors focus:outline-none cursor-pointer">
                Batal
            </a>
            <button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 dark:hover:bg-sky-600 transition-colors focus:outline-none cursor-pointer">
                Perbarui POP
            </button>
        </div>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                    submitBtn.innerHTML = `
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Memproses...
                    `;
                }
            });
        }
    });
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/master/pop/edit.blade.php ENDPATH**/ ?>