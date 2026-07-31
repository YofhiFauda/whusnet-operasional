<?php $__env->startSection('title', 'Daftar Pembayaran - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Daftar Pembayaran'); ?>
<?php $__env->startSection('breadcrumb_parent', 'Pembayaran'); ?>
<?php $__env->startSection('breadcrumb_parent_url', route('payments.index')); ?>

<?php $__env->startSection('content'); ?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-text-main text-sm font-semibold uppercase tracking-wider">Daftar dan Filter Pembayaran</h3>
        <p class="text-xs text-text-muted mt-1">Pembayaran selalu terhubung ke invoice, pelanggan, dan POP/Cabang.</p>
    </div>
    <a href="<?php echo e(route('invoices.index')); ?>" class="inline-flex items-center gap-1.5 px-4 py-2 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
        Buka Daftar Tagihan
    </a>
</div>

<div class="bg-surface border border-border rounded-lg p-6 mb-6">
    <form action="<?php echo e(route('payments.index')); ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-7 gap-4 items-end">
        <div class="sm:col-span-2 xl:col-span-2">
            <label for="search" class="block text-xs font-semibold text-text-secondary mb-2">CARI PEMBAYARAN</label>
            <input type="text" name="search" id="search" value="<?php echo e($search); ?>" placeholder="Cari Nama, ID Transaksi, atau ID Pembayaran Lama..." class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
        </div>

        <div>
            <label for="date_from" class="block text-xs font-semibold text-text-secondary mb-2">DARI TANGGAL</label>
            <input type="date" name="date_from" id="date_from" value="<?php echo e($dateFrom); ?>" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
        </div>

        <div>
            <label for="date_to" class="block text-xs font-semibold text-text-secondary mb-2">SAMPAI TANGGAL</label>
            <input type="date" name="date_to" id="date_to" value="<?php echo e($dateTo); ?>" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
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

        <div>
            <label for="method" class="block text-xs font-semibold text-text-secondary mb-2">METODE</label>
            <select name="method" id="method" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                <option value="">Semua Metode</option>
                <?php $__currentLoopData = $allowedMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paymentMethod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($paymentMethod); ?>" <?php echo e($method === $paymentMethod ? 'selected' : ''); ?>><?php echo e(strtoupper($paymentMethod)); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div>
            <label for="invoice_type" class="block text-xs font-semibold text-text-secondary mb-2">JENIS TAGIHAN</label>
            <select name="invoice_type" id="invoice_type" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                <option value="">Semua Jenis</option>
                <option value="awal" <?php echo e(($invoiceType ?? '') === 'awal' ? 'selected' : ''); ?>>Tagihan Awal (PSB)</option>
                <option value="bulanan" <?php echo e(($invoiceType ?? '') === 'bulanan' ? 'selected' : ''); ?>>Tagihan Bulanan Rutin</option>
                <option value="reaktivasi" <?php echo e(($invoiceType ?? '') === 'reaktivasi' ? 'selected' : ''); ?>>Tagihan Reaktivasi</option>
            </select>
        </div>

        <div>
            <label for="status" class="block text-xs font-semibold text-text-secondary mb-2">STATUS</label>
            <select name="status" id="status" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                <option value="">Semua Status</option>
                <?php $__currentLoopData = $allowedStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paymentStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($paymentStatus); ?>" <?php echo e($status === $paymentStatus ? 'selected' : ''); ?>><?php echo e(ucwords(str_replace('_', ' ', $paymentStatus))); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div class="flex gap-2 xl:col-start-6 xl:col-span-2">
            <button type="submit" class="flex-1 bg-primary hover:bg-primary-focus text-white text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/25">
                Filter
            </button>
            <a href="<?php echo e(route('payments.index')); ?>" class="bg-surface-muted hover:bg-surface-muted/80 text-text-secondary text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none border border-border">
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
                    <th class="px-6 py-3.5">INVOICE</th>
                    <th class="px-6 py-3.5">PELANGGAN</th>
                    <th class="px-6 py-3.5">POP</th>
                    <th class="px-6 py-3.5">TANGGAL</th>
                    <th class="px-6 py-3.5">METODE</th>
                    <th class="px-6 py-3.5 text-right">NOMINAL</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <?php $__empty_1 = true; $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $badgeClass = match($payment->payment_status->value) {
                            'valid' => 'bg-green-500/10 text-green-500 border-green-500/20',
                            'ditolak' => 'bg-red-500/10 text-red-500 border-red-500/20',
                            default => 'bg-amber-500/10 text-amber-500 border-amber-500/20',
                        };
                    ?>
                    <tr class="hover:bg-surface-muted/40 transition-colors">
                        <td class="px-6 py-3.5 text-center text-text-muted data-text"><?php echo e(($payments->currentPage() - 1) * $payments->perPage() + $loop->iteration); ?></td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <a href="<?php echo e(route('payments.show', $payment->id)); ?>" class="font-mono font-bold text-primary hover:text-primary-focus"><?php echo e($payment->payment_number); ?></a>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <?php if($payment->old_payment_id): ?>
                                <span title="Data Migrasi (ID Bayar Lama: <?php echo e($payment->old_payment_id); ?>)" class="px-1.5 py-0.5 text-[9px] font-bold rounded border bg-primary/10 text-primary border-primary/20">
                                    Migrasi #<?php echo e($payment->old_payment_id); ?>

                                </span>
                                <?php endif; ?>
                                <span class="text-[10px] text-text-muted"><?php echo e($payment->receiver->name ?? '-'); ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <?php if($payment->invoice): ?>
                                <a href="<?php echo e(route('invoices.show', $payment->invoice_id)); ?>" class="font-mono font-semibold text-text-main hover:text-primary"><?php echo e($payment->invoice->invoice_number); ?></a>
                            <?php else: ?>
                                <span class="text-text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <div class="font-semibold text-text-main"><?php echo e($payment->customer->full_name ?? '-'); ?></div>
                            <div class="text-[10px] text-text-muted font-mono"><?php echo e($payment->customer->cid ?? $payment->customer->customer_code ?? '-'); ?></div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-medium text-text-main"><?php echo e($payment->pop->name ?? '-'); ?></td>
                        <td class="px-6 py-3.5 whitespace-nowrap"><?php echo e(optional($payment->payment_date)->format('d/m/Y')); ?></td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-semibold"><?php echo e(strtoupper($payment->payment_method)); ?></td>
                        <td class="px-6 py-3.5 text-right font-mono font-semibold">Rp <?php echo e(number_format((float) $payment->amount, 2, ',', '.')); ?></td>
                        <td class="px-6 py-3.5 text-center whitespace-nowrap">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border <?php echo e($badgeClass); ?>">
                                <?php echo e($payment->payment_status->label()); ?>

                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="<?php echo e(route('payments.show', $payment->id)); ?>" class="inline-flex items-center px-3 py-1.5 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-md transition-colors text-xs font-semibold">
                                Detail
                            </a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="10" class="px-6 py-10 text-center text-sm text-text-muted">Belum ada pembayaran yang sesuai filter.</td>
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/payments/index.blade.php ENDPATH**/ ?>