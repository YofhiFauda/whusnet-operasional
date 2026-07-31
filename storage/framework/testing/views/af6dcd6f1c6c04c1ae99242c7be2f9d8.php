<?php $__env->startSection('title', 'Detail Tagihan - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Detail Tagihan'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $badgeClass = match($invoice->invoice_status->value) {
        'lunas' => 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400 border-green-100 dark:border-green-500/20',
        'sebagian' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-100 dark:border-amber-500/20',
        'batal' => 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 border-red-100 dark:border-red-500/20',
        default => 'bg-slate-50 dark:bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-100 dark:border-slate-500/20',
    };
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <nav class="flex text-xs font-semibold text-slate-400 dark:text-slate-500 dark:text-slate-400 uppercase tracking-wider gap-2 mb-2">
            <a href="<?php echo e(route('invoices.index')); ?>" class="hover:text-slate-700 dark:hover:text-slate-200 transition-colors">Daftar Tagihan</a>
            <span>/</span>
            <span class="text-slate-600 dark:text-slate-300"><?php echo e($invoice->invoice_number); ?></span>
        </nav>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-200 tracking-tight">Detail Tagihan <?php echo e($invoice->invoice_number); ?></h1>
    </div>
    <div class="flex gap-2">
        <?php if(auth()->user()->hasPermission('create_payments') && !in_array($invoice->invoice_status->value, ['lunas', 'batal'], true)): ?>
            <a href="<?php echo e(route('invoices.payments.create', $invoice->id)); ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
                Input Pembayaran
            </a>
        <?php endif; ?>
        <a href="<?php echo e(route('customers.show', $invoice->customer_id)); ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Detail Pelanggan
        </a>
        <a href="<?php echo e(route('invoices.index')); ?>" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Kembali
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Informasi Tagihan</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Snapshot tagihan berdasarkan layanan pelanggan saat invoice dibuat.</p>
            </div>
            <div class="flex items-center gap-2">
                <?php if($invoice->invoice_type): ?>
                <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-100 dark:border-sky-500/20">
                    <?php echo e($invoice->invoice_type->label()); ?>

                </span>
                <?php endif; ?>
                <span class="px-2.5 py-1 text-xs font-bold rounded-full border <?php echo e($badgeClass); ?>">
                    <?php echo e($invoice->invoice_status->label()); ?>

                </span>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">No. Tagihan</p>
                <p class="font-mono font-bold text-slate-900 dark:text-slate-200 mt-1"><?php echo e($invoice->invoice_number); ?></p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Periode</p>
                <p class="font-mono text-slate-900 dark:text-slate-200 mt-1"><?php echo e($invoice->billing_period); ?></p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tanggal Terbit</p>
                <p class="text-slate-900 dark:text-slate-200 mt-1"><?php echo e(optional($invoice->issue_date)->format('d/m/Y')); ?></p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tanggal Jatuh Tempo</p>
                <p class="text-slate-900 dark:text-slate-200 mt-1"><?php echo e(optional($invoice->due_date)->format('d/m/Y')); ?></p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Dibuat Oleh</p>
                <p class="text-slate-900 dark:text-slate-200 mt-1"><?php echo e($invoice->creator->name ?? 'System'); ?></p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">POP / Cabang</p>
                <p class="text-slate-900 dark:text-slate-200 mt-1"><?php echo e($invoice->pop->name ?? '-'); ?></p>
            </div>
        </div>

        <?php if($invoice->old_invoice_id || $invoice->old_cost_id || $invoice->old_request_id): ?>
        <div class="border-t border-slate-100 dark:border-slate-700 bg-sky-50/50 dark:bg-sky-900/20 p-6">
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-sky-600 text-white text-[10px] font-bold">i</span>
                <h3 class="text-xs font-bold text-sky-900 dark:text-sky-400 uppercase tracking-wider">Audit Visibilitas Data Migrasi</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <?php if($invoice->old_invoice_id): ?>
                <div>
                    <span class="text-slate-500 dark:text-slate-400 block">ID Invoice Lama:</span>
                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($invoice->old_invoice_id); ?></span>
                </div>
                <?php endif; ?>
                <?php if($invoice->old_cost_id): ?>
                <div>
                    <span class="text-slate-500 dark:text-slate-400 block">ID Biaya Lama:</span>
                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($invoice->old_cost_id); ?></span>
                </div>
                <?php endif; ?>
                <?php if($invoice->old_request_id): ?>
                <div>
                    <span class="text-slate-500 dark:text-slate-400 block">ID Permintaan Lama:</span>
                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($invoice->old_request_id); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="border-t border-slate-100 dark:border-slate-700 p-6">
            <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-4">Rincian Biaya</h3>
            <div class="space-y-3 text-sm">
                
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Harga Paket (Subtotal)</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp <?php echo e(number_format((float) $invoice->subtotal, 0, ',', '.')); ?></span>
                </div>

                <?php if((float)$invoice->discount > 0): ?>
                <div class="flex justify-between gap-4 text-green-700 dark:text-green-400">
                    <span>Potongan Diskon</span>
                    <span class="font-mono">- Rp <?php echo e(number_format((float) $invoice->discount, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php
                    $afterDiscount = max(0, (float)$invoice->subtotal - (float)$invoice->discount);
                    $ppnRate = (float)$invoice->ppn;
                    $ppnAmount = round($afterDiscount * ($ppnRate / 100), 2);
                ?>

                <?php if($ppnRate > 0): ?>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">PPN (<?php echo e(number_format($ppnRate, 0)); ?>%)</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp <?php echo e(number_format($ppnAmount, 0, ',', '.')); ?></span>
                </div>
                <?php else: ?>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">PPN</span>
                    <span class="font-mono text-slate-400 dark:text-slate-500">Tidak dikenakan</span>
                </div>
                <?php endif; ?>

                
                <?php if((float)($invoice->prorate_amount ?? 0) > 0): ?>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Tagihan Prorate</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp <?php echo e(number_format((float) $invoice->prorate_amount, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->extra_cable_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Biaya Kabel Tambahan</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp <?php echo e(number_format((float) $invoice->extra_cable_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->other_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Biaya Lain-lain</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp <?php echo e(number_format((float) $invoice->other_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->extra_installation_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Jasa Instalasi Tambahan</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp <?php echo e(number_format((float) $invoice->extra_installation_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->extra_pole_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Tambahan Tiang</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp <?php echo e(number_format((float) $invoice->extra_pole_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                
                <div class="flex justify-between gap-4 pt-3 border-t border-slate-200 dark:border-slate-700">
                    <span class="font-bold text-slate-800 dark:text-slate-200">Total Tagihan</span>
                    <span class="font-mono font-bold text-slate-900 text-base">Rp <?php echo e(number_format((float) $invoice->total_amount, 0, ',', '.')); ?></span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Sudah Terbayar</span>
                    <span class="font-mono text-emerald-700 dark:text-emerald-400">Rp <?php echo e(number_format((float) $invoice->paid_amount, 0, ',', '.')); ?></span>
                </div>
                <div class="flex justify-between gap-4 pt-2 border-t border-slate-100 dark:border-slate-700">
                    <span class="font-bold text-slate-800 dark:text-slate-200">Sisa Tagihan</span>
                    <span class="font-mono font-bold <?php echo e((float)$invoice->remaining_amount > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'); ?>">
                        Rp <?php echo e(number_format((float) $invoice->remaining_amount, 0, ',', '.')); ?>

                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Riwayat Pembayaran</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Pembayaran yang sudah dicatat untuk invoice ini.</p>
            </div>
        </div>

        <?php if($invoice->payments->count() > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider text-[10px]">
                            <th class="px-4 py-3">No. Pembayaran</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Metode</th>
                            <th class="px-4 py-3 text-right">Nominal</th>
                            <th class="px-4 py-3">Penerima</th>
                            <th class="px-4 py-3">Bukti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-slate-700 dark:text-slate-300">
                        <?php $__currentLoopData = $invoice->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/25 transition-colors">
                                <td class="px-4 py-3 font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($payment->payment_number); ?></td>
                                <td class="px-4 py-3"><?php echo e(optional($payment->payment_date)->format('d/m/Y')); ?></td>
                                <td class="px-4 py-3"><?php echo e(strtoupper($payment->payment_method)); ?></td>
                                <td class="px-4 py-3 text-right font-mono font-semibold">Rp <?php echo e(number_format((float) $payment->amount, 2, ',', '.')); ?></td>
                                <td class="px-4 py-3"><?php echo e($payment->receiver->name ?? '-'); ?></td>
                                <td class="px-4 py-3">
                                    <?php if($payment->proof_file): ?>
                                        <a href="<?php echo e(asset('storage/' . $payment->proof_file)); ?>" target="_blank" class="text-sky-700 dark:text-sky-400 hover:text-sky-900 dark:hover:text-sky-300 font-semibold">Lihat bukti</a>
                                    <?php else: ?>
                                        <span class="text-slate-400 dark:text-slate-500">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-6 text-center text-sm text-slate-500 dark:text-slate-400 dark:text-slate-500">
                Belum ada pembayaran untuk invoice ini.
            </div>
        <?php endif; ?>
    </div>

    <div class="space-y-6">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider mb-4">Pelanggan</h2>
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama</p>
                    <p class="font-semibold text-slate-900 dark:text-slate-200 mt-1"><?php echo e($invoice->customer->full_name ?? '-'); ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ID Pelanggan</p>
                    <p class="font-mono text-slate-900 dark:text-slate-200 mt-1"><?php echo e($invoice->customer->cid ?? $invoice->customer->customer_code ?? '-'); ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">No. HP</p>
                    <p class="font-mono text-slate-900 dark:text-slate-200 mt-1"><?php echo e($invoice->customer->primary_phone ?? $invoice->customer->phone ?? '-'); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider mb-4">Paket</h2>
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Paket</p>
                    <p class="font-semibold text-slate-900 dark:text-slate-200 mt-1"><?php echo e($invoice->customerService->package_name_snapshot ?? $invoice->internetPackage->name ?? '-'); ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Download / Upload</p>
                    <p class="text-slate-900 dark:text-slate-200 mt-1">
                        <?php echo e($invoice->customerService->download_speed_snapshot ?? '-'); ?> /
                        <?php echo e($invoice->customerService->upload_speed_snapshot ?? '-'); ?>

                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Harga Bulanan</p>
                    <p class="font-mono text-slate-900 dark:text-slate-200 mt-1">Rp <?php echo e(number_format((float) ($invoice->customerService->monthly_price ?? 0), 2, ',', '.')); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/invoices/show.blade.php ENDPATH**/ ?>