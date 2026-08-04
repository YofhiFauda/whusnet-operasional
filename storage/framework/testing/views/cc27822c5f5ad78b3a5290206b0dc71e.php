<?php $__env->startSection('title', 'Lebih Bayar - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Lebih Bayar'); ?>
<?php $__env->startSection('breadcrumb_parent', 'Pembayaran'); ?>
<?php $__env->startSection('breadcrumb_parent_url', route('payments.index')); ?>

<?php $__env->startSection('content'); ?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-text-main text-sm font-semibold uppercase tracking-wider">Daftar Pembayaran dengan Lebih Bayar</h3>
        <p class="text-xs text-text-muted mt-1">
            Uang lebih yang diserahkan pelanggan saat membayar tagihan. <span class="font-semibold">Catatan saja, bukan saldo</span> — tidak otomatis dipakai untuk tagihan berikutnya, diselesaikan manual (refund atau potong tagihan berikut).
        </p>
    </div>
    <a href="<?php echo e(route('payments.index')); ?>" class="inline-flex items-center gap-1.5 px-4 py-2 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
        Kembali ke Daftar Pembayaran
    </a>
</div>

<div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-lg p-4 mb-6">
    <p class="text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider">Total Lebih Bayar (sesuai filter)</p>
    <p class="text-xl font-bold text-amber-800 dark:text-amber-300 font-mono mt-1">Rp <?php echo e(number_format($totalOverpay, 0, ',', '.')); ?></p>
</div>

<div class="bg-surface border border-border rounded-lg p-6 mb-6">
    <form action="<?php echo e(route('payments.overpay')); ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 items-end">
        <div class="sm:col-span-2">
            <label for="search" class="block text-xs font-semibold text-text-secondary mb-2">CARI PELANGGAN</label>
            <input type="text" name="search" id="search" value="<?php echo e($search); ?>" placeholder="Cari Nama, CID, atau Kode Pelanggan..." class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
        </div>

        <div>
            <label for="pop_id" class="block text-xs font-semibold text-text-secondary mb-2">POP / CABANG</label>
            <select name="pop_id" id="pop_id" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                <option value="">Semua POP</option>
                <?php $__currentLoopData = $pops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($pop->id); ?>" <?php echo e((string) $popId === (string) $pop->id ? 'selected' : ''); ?>><?php echo e($pop->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-primary hover:bg-primary-focus text-white text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/25">
                Filter
            </button>
            <a href="<?php echo e(route('payments.overpay')); ?>" class="bg-surface-muted hover:bg-surface-muted/80 text-text-secondary text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none border border-border">
                Reset
            </a>
        </div>
    </form>
</div>

<div class="bg-surface border border-border rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-text-secondary">
            <thead>
                <tr class="bg-surface-muted/50 border-b border-border text-text-secondary font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">NO. PEMBAYARAN</th>
                    <th class="px-6 py-3.5">PELANGGAN</th>
                    <th class="px-6 py-3.5">POP</th>
                    <th class="px-6 py-3.5">TANGGAL</th>
                    <th class="px-6 py-3.5 text-right">NOMINAL BAYAR</th>
                    <th class="px-6 py-3.5 text-right">NOMINAL LEBIH</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <?php $__empty_1 = true; $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="hover:bg-surface-muted/40 transition-colors">
                        <td class="px-6 py-3.5 text-center text-text-muted data-text"><?php echo e(($payments->currentPage() - 1) * $payments->perPage() + $loop->iteration); ?></td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <a href="<?php echo e(route('payments.show', $payment->id)); ?>" class="font-mono font-bold text-primary hover:text-primary-focus"><?php echo e($payment->payment_number); ?></a>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <div class="font-semibold text-text-main"><?php echo e($payment->customer->full_name ?? '-'); ?></div>
                            <div class="text-[10px] text-text-muted font-mono"><?php echo e($payment->customer->cid ?? $payment->customer->customer_code ?? '-'); ?></div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-medium text-text-main"><?php echo e($payment->pop->name ?? '-'); ?></td>
                        <td class="px-6 py-3.5 whitespace-nowrap"><?php echo e(optional($payment->payment_date)->format('d/m/Y')); ?></td>
                        <td class="px-6 py-3.5 text-right font-mono">Rp <?php echo e(number_format((float) $payment->amount, 2, ',', '.')); ?></td>
                        <td class="px-6 py-3.5 text-right font-mono font-bold text-amber-600 dark:text-amber-400">Rp <?php echo e(number_format((float) $payment->overpay_amount, 2, ',', '.')); ?></td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="<?php echo e(route('payments.show', $payment->id)); ?>" class="inline-flex items-center px-3 py-1.5 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-md transition-colors text-xs font-semibold">
                                Detail
                            </a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-10 text-center text-sm text-text-muted">Tidak ada pembayaran dengan lebih bayar sesuai filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-border">
        <?php echo e($payments->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/payments/overpay.blade.php ENDPATH**/ ?>