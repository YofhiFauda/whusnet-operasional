<?php $__env->startSection('title', 'Ubah Kategori Issue - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Ubah Kategori Issue'); ?>

<?php $__env->startSection('content'); ?>
<!-- Back link and Title Header -->
<div class="mb-6 flex items-center justify-between">
    <a href="<?php echo e(route('master.ticket-issue-categories.index')); ?>" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Daftar Kategori Issue
    </a>
</div>

<!-- Form Container -->
<form action="<?php echo e(route('master.ticket-issue-categories.update', $category)); ?>" method="POST" class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-4xl mx-auto">
    <?php echo csrf_field(); ?>
    <?php echo method_field('PUT'); ?>

    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm col-span-1 lg:col-span-2 space-y-5">
        <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Informasi Kategori Issue</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Ubah data master kategori keluhan.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Nama Kategori -->
            <div>
                <label for="name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Nama Kategori <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="name" value="<?php echo e(old('name', $category->name)); ?>" required
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

            <!-- Prioritas Default -->
            <div>
                <label for="default_priority" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Prioritas Default <span class="text-rose-500">*</span></label>
                <select name="default_priority" id="default_priority" required
                        class="w-full px-3 py-2 border <?php $__errorArgs = ['default_priority'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 bg-white dark:bg-slate-800">
                    <option value="">-- Pilih Prioritas --</option>
                    <?php $__currentLoopData = \App\Enums\FopTaskPriority::cases(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $priority): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($priority->value); ?>" <?php echo e(old('default_priority', $category->default_priority?->value) === $priority->value ? 'selected' : ''); ?>><?php echo e($priority->value); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <?php $__errorArgs = ['default_priority'];
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

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
            <!-- Sumber SLA -->
            <div>
                <label for="sla_source" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Sumber SLA <span class="text-rose-500">*</span></label>
                <select name="sla_source" id="sla_source" required
                        class="w-full px-3 py-2 border <?php $__errorArgs = ['sla_source'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 bg-white dark:bg-slate-800">
                    <option value="prioritas" <?php echo e(old('sla_source', $category->sla_source) === 'prioritas' ? 'selected' : ''); ?>>Prioritas</option>
                    <option value="paket" <?php echo e(old('sla_source', $category->sla_source) === 'paket' ? 'selected' : ''); ?>>Paket</option>
                </select>
                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Cuma flag klasifikasi/pelaporan — tidak mengubah mesin SLA Pengerjaan/Handling SLA.</p>
                <?php $__errorArgs = ['sla_source'];
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

            <!-- Status -->
            <div>
                <label for="is_active" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Status <span class="text-rose-500">*</span></label>
                <select name="is_active" id="is_active" required
                        class="w-full px-3 py-2 border <?php $__errorArgs = ['is_active'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 focus:ring-rose-500 <?php else: ?> border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 bg-white dark:bg-slate-800">
                    <option value="1" <?php echo e(old('is_active', $category->is_active ? '1' : '0') === '1' ? 'selected' : ''); ?>>Aktif</option>
                    <option value="0" <?php echo e(old('is_active', $category->is_active ? '1' : '0') === '0' ? 'selected' : ''); ?>>Nonaktif</option>
                </select>
                <?php $__errorArgs = ['is_active'];
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

        <!-- Submit Actions -->
        <div class="flex items-center gap-3 justify-end pt-5 border-t border-slate-100 dark:border-slate-700/50 mt-5">
            <a href="<?php echo e(route('master.ticket-issue-categories.index')); ?>" class="inline-flex items-center justify-center px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 transition-colors focus:outline-none cursor-pointer">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                Simpan Perubahan
            </button>
        </div>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/master/ticket-issue-categories/edit.blade.php ENDPATH**/ ?>