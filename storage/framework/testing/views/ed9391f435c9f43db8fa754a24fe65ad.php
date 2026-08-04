<?php $__env->startSection('title', 'Kolektor - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Kolektor'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-slate-800 dark:text-slate-200 text-sm font-semibold uppercase tracking-wider">Daftar Kolektor</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Klik salah satu kolektor untuk lihat worklist, bayar 1-by-1/massal, dan atur pelanggannya.</p>
    </div>
    <a href="<?php echo e(route('invoices.index')); ?>" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
        Kembali ke Tagihan
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php $__empty_1 = true; $__currentLoopData = $collectors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $collector): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <a href="<?php echo e(route('collectors.show', $collector->id)); ?>" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 hover:border-sky-300 dark:hover:border-sky-700 hover:shadow-md transition-all">
            <div class="flex items-center gap-3 mb-3">
                <div class="h-10 w-10 rounded-full bg-violet-50 dark:bg-violet-500/10 flex items-center justify-center text-violet-600 dark:text-violet-400 font-bold text-sm shrink-0">
                    <?php echo e(strtoupper(substr($collector->name, 0, 1))); ?>

                </div>
                <div>
                    <div class="font-semibold text-slate-900 dark:text-slate-200"><?php echo e($collector->name); ?></div>
                    <div class="text-[10px] text-slate-400 dark:text-slate-500"><?php echo e($collector->status === 'active' ? 'Aktif' : 'Nonaktif'); ?></div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-md p-2.5">
                    <div class="text-slate-400 dark:text-slate-500 text-[10px] uppercase font-bold tracking-wide">Pelanggan</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5"><?php echo e($collector->customer_count); ?></div>
                </div>
                <div class="bg-amber-50 dark:bg-amber-500/10 rounded-md p-2.5">
                    <div class="text-amber-600 dark:text-amber-500 text-[10px] uppercase font-bold tracking-wide">Tunggakan</div>
                    <div class="font-bold text-amber-700 dark:text-amber-400 mt-0.5">Rp <?php echo e(number_format($collector->unpaid_total, 0, ',', '.')); ?></div>
                </div>
            </div>
        </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="col-span-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-10 text-center text-sm text-slate-500 dark:text-slate-400">
            Belum ada user ber-role Kolektor. Tambah lewat User Management.
        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/collectors/index.blade.php ENDPATH**/ ?>