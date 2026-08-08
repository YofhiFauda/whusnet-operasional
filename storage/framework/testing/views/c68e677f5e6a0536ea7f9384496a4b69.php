<?php $__env->startSection('title', 'Riwayat Import Pelanggan - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Riwayat Import Pelanggan'); ?>
<?php $__env->startSection('breadcrumb_parent', 'Pelanggan'); ?>
<?php $__env->startSection('breadcrumb_parent_url', '/customers'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-text-main tracking-tight">Riwayat Batch Import</h1>
            <p class="text-xs text-text-secondary mt-1">Daftar aktivitas import pelanggan massal</p>
        </div>
        <a href="<?php echo e(route('customers.import')); ?>" class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-2 px-4 rounded transition-colors shadow-sm">
            Import Baru
        </a>
    </div>
</div>

<div class="bg-surface border border-border rounded-lg shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse text-text-secondary">
            <thead>
                <tr class="bg-surface-muted border-b border-border text-text-muted font-semibold text-[10px] uppercase">
                    <th class="px-6 py-4">Waktu</th>
                    <th class="px-6 py-4">Nomor Batch</th>
                    <th class="px-6 py-4">Nama File / Sumber</th>
                    <th class="px-6 py-4">Oleh</th>
                    <th class="px-6 py-4 text-center">Total</th>
                    <th class="px-6 py-4 text-center">Valid / Error</th>
                    <th class="px-6 py-4 text-center">Berhasil</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <?php $__empty_1 = true; $__currentLoopData = $batches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $batch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="hover:bg-surface-muted transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-text-muted">
                        <?php echo e($batch->created_at->format('d/m/Y H:i')); ?>

                    </td>
                    <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-text-main">
                        <?php echo e($batch->batch_number); ?>

                    </td>
                    <td class="px-6 py-4 text-text-secondary max-w-[200px] truncate">
                        <?php echo e($batch->file_name); ?>

                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo e($batch->user?->name ?? 'System'); ?>

                    </td>
                    <td class="px-6 py-4 text-center font-mono font-bold text-text-main">
                        <?php echo e($batch->total_rows); ?>

                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-success font-bold font-mono"><?php echo e($batch->valid_rows); ?></span>
                        <span class="text-text-disabled mx-1">/</span>
                        <span class="text-error font-bold font-mono"><?php echo e($batch->invalid_rows); ?></span>
                    </td>
                    <td class="px-6 py-4 text-center font-mono font-bold text-primary">
                        <?php echo e($batch->imported_rows); ?>

                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if($batch->status === 'imported'): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-success-bg text-success border border-success-border">IMPORTED</span>
                        <?php elseif($batch->status === 'failed'): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-error-bg text-error border border-error-border">FAILED</span>
                        <?php elseif($batch->status === 'pending'): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-warning-bg text-warning border border-warning-border">PENDING</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-surface-muted text-text-secondary border border-border uppercase"><?php echo e($batch->status); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <a href="<?php echo e(route('customers.import.batch-detail', $batch->id)); ?>" class="inline-flex items-center justify-center bg-surface-muted hover:bg-surface border border-border text-text-secondary text-[10px] font-bold py-1.5 px-3 rounded transition-colors uppercase tracking-tight">
                            Detail
                        </a>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-text-muted italic">
                        Belum ada riwayat import data.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($batches->hasPages()): ?>
    <div class="px-6 py-4 bg-surface-muted border-t border-border">
        <?php echo e($batches->links()); ?>

    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/import_history.blade.php ENDPATH**/ ?>