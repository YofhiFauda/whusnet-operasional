<?php $__env->startSection('title', 'Laporan Tagihan - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Laporan Tagihan'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $statusBadges = [
        'belum_dibayar' => 'bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800/50',
        'sebagian' => 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800/50',
        'lunas' => 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border-green-200 dark:border-green-800/50',
        'batal' => 'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700',
    ];
    $statusLabels = [
        'belum_dibayar' => 'Belum Dibayar',
        'sebagian' => 'Sebagian',
        'lunas' => 'Lunas',
        'batal' => 'Batal',
    ];
?>

<div class="space-y-6">
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Card Total Tagihan -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center text-sky-600 shrink-0">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14h6m-6 4h6M5 3h14a2 2 0 012 2v16l-3-2-3 2-3-2-3 2-3-2-3 2V5a2 2 0 012-2z" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Tagihan</p>
                <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">Rp <?php echo e(number_format($totalAmountSum, 2, ',', '.')); ?></h3>
            </div>
        </div>

        <!-- Card Total Terbayar -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center text-green-600 dark:text-green-400 shrink-0">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Terbayar</p>
                <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">Rp <?php echo e(number_format($totalPaidSum, 2, ',', '.')); ?></h3>
            </div>
        </div>

        <!-- Card Total Tunggakan -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-rose-50 dark:bg-rose-900/20 flex items-center justify-center text-rose-600 dark:text-rose-400 shrink-0">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Tunggakan</p>
                <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">Rp <?php echo e(number_format($totalTunggakanSum, 2, ',', '.')); ?></h3>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 shadow-sm">
        <form method="GET" action="<?php echo e(route('reports.invoices.index')); ?>" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Filter POP -->
                <div>
                    <label for="pop_id" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">POP / Cabang</label>
                    <select id="pop_id" name="pop_id" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua POP Akses</option>
                        <?php $__currentLoopData = $pops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($pop->id); ?>" <?php if((string)$popId === (string)$pop->id): echo 'selected'; endif; ?>>
                                <?php echo e($pop->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <!-- Filter Periode Tagihan -->
                <div>
                    <label for="billing_period" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Periode Tagihan</label>
                    <input id="billing_period" type="month" name="billing_period" value="<?php echo e($billingPeriod); ?>" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <!-- Filter Status Tagihan -->
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Status Tagihan</label>
                    <select id="status" name="status" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua Status</option>
                        <?php $__currentLoopData = $allowedStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($item); ?>" <?php if($status === $item): echo 'selected'; endif; ?>>
                                <?php echo e($statusLabels[$item]); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <!-- Filter Tanggal Terbit Dari -->
                <div>
                    <label for="start_date" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Terbit Dari</label>
                    <input id="start_date" type="date" name="start_date" value="<?php echo e($startDate); ?>" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <!-- Filter Tanggal Terbit Sampai -->
                <div>
                    <label for="end_date" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Terbit Sampai</label>
                    <input id="end_date" type="date" name="end_date" value="<?php echo e($endDate); ?>" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>
            </div>

            <!-- Additional Filters & Action Buttons -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pt-4 border-t border-slate-100 dark:border-slate-700/50 gap-4">
                <!-- Toggle Tunggakan -->
                <div class="flex items-center">
                    <input id="show_tunggakan" type="checkbox" name="show_tunggakan" value="1" <?php if($showTunggakan): echo 'checked'; endif; ?> class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 text-sky-600 focus:ring-sky-500">
                    <label for="show_tunggakan" class="ml-2 text-sm font-medium text-slate-700 dark:text-slate-300">Hanya Tampilkan Tunggakan (Sisa > 0)</label>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md bg-sky-600 dark:bg-sky-500 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filter Laporan
                    </button>
                    <a href="<?php echo e(route('reports.invoices.index')); ?>" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 transition-colors shadow-sm focus:outline-none">
                        Reset
                    </a>
                    <a href="<?php echo e(route('reports.invoices.export', request()->query())); ?>" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md bg-emerald-600 dark:bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export CSV
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm overflow-hidden">
        <!-- Table Header Info -->
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Rincian Laporan Tagihan</h3>
            <span class="inline-flex items-center rounded-md bg-sky-100 dark:bg-sky-900/40 px-2.5 py-0.5 text-xs font-semibold text-sky-800 dark:text-sky-300">
                Total: <?php echo e($invoices->total()); ?> Tagihan
            </span>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                <thead>
                    <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                        <th class="px-6 py-3">No. Invoice</th>
                        <th class="px-6 py-3">Pelanggan</th>
                        <th class="px-6 py-3">POP / Cabang</th>
                        <th class="px-6 py-3">Periode</th>
                        <th class="px-6 py-3 text-center">Tanggal</th>
                        <th class="px-6 py-3 text-right">Tagihan</th>
                        <th class="px-6 py-3 text-right">Terbayar</th>
                        <th class="px-6 py-3 text-right">Tunggakan</th>
                        <th class="px-6 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 bg-white dark:bg-slate-800">
                    <?php $__empty_1 = true; $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap">
                                <a href="<?php echo e(route('invoices.show', $invoice->id)); ?>" class="text-sky-600 hover:text-sky-800 dark:hover:text-sky-300 hover:underline">
                                    <?php echo e($invoice->invoice_number); ?>

                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if($invoice->customer): ?>
                                    <div class="font-bold text-slate-900 dark:text-slate-100"><?php echo e($invoice->customer->full_name); ?></div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400"><?php echo e($invoice->customer->customer_code); ?></div>
                                <?php else: ?>
                                    <span class="text-slate-400 dark:text-slate-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                <?php echo e($invoice->pop->name ?? '-'); ?>

                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                <?php echo e($invoice->billing_period); ?>

                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400 whitespace-nowrap text-center text-xs">
                                <div>Terbit: <?php echo e($invoice->issue_date ? $invoice->issue_date->format('d/m/Y') : '-'); ?></div>
                                <div class="text-slate-400 dark:text-slate-500 mt-0.5">Tempo: <?php echo e($invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-'); ?></div>
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-slate-900 dark:text-slate-100 whitespace-nowrap">
                                Rp <?php echo e(number_format($invoice->total_amount, 2, ',', '.')); ?>

                            </td>
                            <td class="px-6 py-4 text-right font-medium text-green-600 dark:text-green-400 whitespace-nowrap">
                                Rp <?php echo e(number_format($invoice->paid_amount, 2, ',', '.')); ?>

                            </td>
                            <td class="px-6 py-4 text-right font-bold <?php echo e($invoice->remaining_amount > 0 && $invoice->invoice_status->value !== 'batal' ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'); ?> whitespace-nowrap">
                                Rp <?php echo e(number_format($invoice->remaining_amount, 2, ',', '.')); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?php echo e($statusBadges[$invoice->invoice_status->value] ?? 'bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700'); ?>">
                                    <?php echo e($statusLabels[$invoice->invoice_status->value] ?? $invoice->invoice_status->label()); ?>

                                </span>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-10 text-center text-slate-500 dark:text-slate-400">
                                <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                Tidak ada data tagihan yang cocok dengan kriteria filter.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <?php if($invoices->hasPages()): ?>
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                <?php echo e($invoices->links()); ?>

            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/reports/invoices/index.blade.php ENDPATH**/ ?>