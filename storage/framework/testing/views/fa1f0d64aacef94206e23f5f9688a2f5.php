<?php $__env->startSection('title', 'Detail Log Import - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Detail Log Import'); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6">
    <!-- Breadcrumb / Header Actions -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <nav class="flex text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider gap-2">
            <a href="<?php echo e(route('reports.imports.index')); ?>" class="hover:text-slate-700 dark:hover:text-slate-200 dark:hover:text-slate-300 transition-colors">Laporan Import</a>
            <span>/</span>
            <span class="text-slate-600 dark:text-slate-400">Detail Batch</span>
        </nav>
        <div class="flex gap-2">
            <a href="<?php echo e(route('reports.imports.index')); ?>" class="inline-flex items-center justify-center gap-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 text-xs font-semibold py-2 px-4 rounded transition-colors shadow-sm">
                Kembali
            </a>
            <?php if($batch->errors->count() > 0): ?>
                <a href="<?php echo e(route('reports.imports.export', $batch->id)); ?>" class="inline-flex items-center justify-center gap-2 bg-emerald-600 dark:bg-emerald-500 hover:bg-emerald-700 text-white text-xs font-semibold py-2 px-4 rounded transition-colors shadow-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export Log Error (CSV)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Layout 2 Columns -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Summary Info Card (Col span 1) -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm space-y-4">
                <h4 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Ringkasan Batch</h4>
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Nomor Batch:</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($batch->batch_number); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Status:</span>
                        <?php if($batch->status === 'imported'): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-100 dark:border-green-800/30 uppercase"><?php echo e($batch->status); ?></span>
                        <?php elseif($batch->status === 'failed'): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-100 dark:border-red-800/30 uppercase"><?php echo e($batch->status); ?></span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border border-amber-100 dark:border-amber-800/30 uppercase"><?php echo e($batch->status); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Waktu Upload:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-300"><?php echo e($batch->created_at->format('d M Y H:i')); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Uploader:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-300"><?php echo e($batch->user?->name ?? 'System'); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Nama File:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-300 truncate max-w-[150px]" title="<?php echo e($batch->file_name); ?>"><?php echo e($batch->file_name); ?></span>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-700/50">
                        <div class="flex justify-between items-center text-xs mb-2">
                            <span class="text-slate-500 dark:text-slate-400 font-medium">Total Baris:</span>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e(number_format($batch->total_rows)); ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs mb-2">
                            <span class="text-green-600 dark:text-green-400 font-medium">Data Valid:</span>
                            <span class="font-mono font-bold text-green-700 dark:text-green-400"><?php echo e(number_format($batch->valid_rows)); ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs mb-2">
                            <span class="text-red-600 dark:text-red-400 font-medium">Data Invalid:</span>
                            <span class="font-mono font-bold text-red-700 dark:text-red-400"><?php echo e(number_format($batch->invalid_rows)); ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs pt-3 border-t border-slate-100 dark:border-slate-700/50">
                            <span class="text-sky-600 font-bold uppercase">Berhasil Masuk:</span>
                            <span class="text-lg font-mono font-extrabold text-sky-700 dark:text-sky-400"><?php echo e(number_format($batch->imported_rows)); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Table Error (Col span 2) -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Log Detail Error Import</h3>
                    <span class="inline-flex items-center rounded-md bg-red-100 dark:bg-red-900/40 px-2.5 py-0.5 text-xs font-semibold text-red-800">
                        Total: <?php echo e($batch->errors->count()); ?> Error
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                <th class="px-6 py-3 text-center w-24">Baris</th>
                                <th class="px-6 py-3 w-44">Kolom / Field</th>
                                <th class="px-6 py-3">Pesan Kesalahan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 bg-white dark:bg-slate-800">
                            <?php $__empty_1 = true; $__currentLoopData = $batch->errors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr class="hover:bg-red-50/10 transition-colors">
                                    <td class="px-6 py-4 text-center font-mono font-bold text-slate-400 dark:text-slate-500">
                                        <?php echo e($error->row_number ?? '-'); ?>

                                    </td>
                                    <td class="px-6 py-4 font-mono font-bold text-red-600 dark:text-red-400">
                                        <?php echo e($error->field_name ?? 'Global/DB Check'); ?>

                                    </td>
                                    <td class="px-6 py-4 text-red-700 dark:text-red-400 font-medium leading-relaxed">
                                        <?php echo e($error->error_message); ?>

                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-10 text-center text-slate-500 dark:text-slate-400 italic">
                                        Tidak ada catatan error untuk batch import ini. Semua baris data berhasil diproses.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/reports/imports/show.blade.php ENDPATH**/ ?>