<?php $__env->startSection('title', 'Worklist Saya - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Worklist Saya'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-6">
    <h3 class="text-slate-800 dark:text-slate-200 text-sm font-semibold uppercase tracking-wider">Pelanggan yang Perlu Didatangi</h3>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Daftar ini read-only — serahkan uang yang sudah dikumpulkan ke admin kantor untuk diproses.</p>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-slate-700 dark:text-slate-200">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-semibold text-xs">
                    <th class="px-6 py-3.5">PELANGGAN</th>
                    <th class="px-6 py-3.5">ALAMAT</th>
                    <th class="px-6 py-3.5">TELEPON</th>
                    <th class="px-6 py-3.5 text-right">TOTAL TUNGGAKAN</th>
                    <th class="px-6 py-3.5 text-center">JML TAGIHAN</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                <?php $__empty_1 = true; $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php $totalTunggakan = $customer->invoices->sum('remaining_amount'); ?>
                    <tr class="hover:bg-slate-50/45 dark:hover:bg-slate-700/25 transition-colors">
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <div class="font-semibold text-slate-900 dark:text-slate-200"><?php echo e($customer->full_name); ?></div>
                            <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono"><?php echo e($customer->cid ?? $customer->customer_code); ?></div>
                        </td>
                        <td class="px-6 py-3.5 text-xs text-slate-600 dark:text-slate-300"><?php echo e($customer->address ?? '-'); ?></td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-mono text-xs"><?php echo e($customer->primary_phone ?? '-'); ?></td>
                        <td class="px-6 py-3.5 text-right font-mono font-semibold text-amber-700 dark:text-amber-400">Rp <?php echo e(number_format((float) $totalTunggakan, 0, ',', '.')); ?></td>
                        <td class="px-6 py-3.5 text-center"><?php echo e($customer->invoices->count()); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada pelanggan dengan tunggakan saat ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700">
        <?php echo e($customers->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/collector-worklist/index.blade.php ENDPATH**/ ?>