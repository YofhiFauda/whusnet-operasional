<?php $__env->startSection('title', 'Laporan Pembayaran - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Laporan Pembayaran'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $statusBadges = [
        'valid' => 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border-green-200 dark:border-green-800/50',
        'ditolak' => 'bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800/50',
    ];
    $statusLabels = [
        'valid' => 'Valid / Disetujui',
        'ditolak' => 'Ditolak',
    ];
    $methodLabels = [
        'cash' => 'Cash',
        'transfer' => 'Transfer Bank',
        'qris' => 'QRIS',
        'lainnya' => 'Lainnya',
    ];
?>

<div class="space-y-6">
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Card Total Pembayaran -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center text-sky-600 shrink-0">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Pembayaran (Semua)</p>
                <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">Rp <?php echo e(number_format($totalAmountSum, 2, ',', '.')); ?></h3>
            </div>
        </div>

        <!-- Card Total Valid -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center text-green-600 dark:text-green-400 shrink-0">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pembayaran Valid (Lunas/Diterima)</p>
                <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">Rp <?php echo e(number_format($totalValidSum, 2, ',', '.')); ?></h3>
            </div>
        </div>

        <!-- Card Total Ditolak -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-rose-50 dark:bg-rose-900/20 flex items-center justify-center text-rose-600 dark:text-rose-400 shrink-0">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pembayaran Ditolak</p>
                <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">Rp <?php echo e(number_format($totalDitolakSum, 2, ',', '.')); ?></h3>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 shadow-sm">
        <form method="GET" action="<?php echo e(route('reports.payments.index')); ?>" id="report-filter-form" class="space-y-4">
            <!-- Preset Periode -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">PERIODE CEPAT</label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="applyDatePreset('today')" class="px-3 py-1.5 text-xs font-semibold rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Hari Ini</button>
                    <button type="button" onclick="applyDatePreset('7days')" class="px-3 py-1.5 text-xs font-semibold rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">7 Hari Terakhir</button>
                    <button type="button" onclick="applyDatePreset('this_month')" class="px-3 py-1.5 text-xs font-semibold rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Bulan Ini</button>
                    <button type="button" onclick="applyDatePreset('last_month')" class="px-3 py-1.5 text-xs font-semibold rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Bulan Lalu</button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
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

                <!-- Filter Metode Pembayaran -->
                <div>
                    <label for="payment_method" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Metode Pembayaran</label>
                    <select id="payment_method" name="payment_method" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua Metode</option>
                        <?php $__currentLoopData = $allowedMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($item); ?>" <?php if($paymentMethod === $item): echo 'selected'; endif; ?>>
                                <?php echo e($methodLabels[$item]); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <!-- Filter Status Pembayaran -->
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Status Pembayaran</label>
                    <select id="status" name="status" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua Status</option>
                        <?php $__currentLoopData = $allowedStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($item); ?>" <?php if($status === $item): echo 'selected'; endif; ?>>
                                <?php echo e($statusLabels[$item]); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <!-- Filter Kolektor -->
                <div>
                    <label for="collector_id" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Kolektor</label>
                    <select id="collector_id" name="collector_id" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua (Kolektor &amp; Langsung)</option>
                        <?php $__currentLoopData = $collectors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $collector): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($collector->id); ?>" <?php if((string)$collectorId === (string)$collector->id): echo 'selected'; endif; ?>>
                                <?php echo e($collector->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <!-- Filter Tanggal Bayar Dari -->
                <div>
                    <label for="start_date" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Tanggal Dari</label>
                    <input id="start_date" type="date" name="start_date" value="<?php echo e($startDate); ?>" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <!-- Filter Tanggal Bayar Sampai -->
                <div>
                    <label for="end_date" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Tanggal Sampai</label>
                    <input id="end_date" type="date" name="end_date" value="<?php echo e($endDate); ?>" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end items-center pt-4 border-t border-slate-100 dark:border-slate-700/50 gap-2">
                <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md bg-sky-600 dark:bg-sky-500 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filter Laporan
                </button>
                <a href="<?php echo e(route('reports.payments.index')); ?>" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 transition-colors shadow-sm focus:outline-none">
                    Reset
                </a>
                <a href="<?php echo e(route('reports.payments.export', request()->query())); ?>" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md bg-emerald-600 dark:bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export CSV
                </a>
                <a href="<?php echo e(route('reports.payments.export-xlsx', request()->query())); ?>" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md bg-teal-600 dark:bg-teal-500 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export XLSX
                </a>
            </div>
        </form>
    </div>

    <?php $__env->startPush('scripts'); ?>
    <script>
        function applyDatePreset(preset) {
            const startInput = document.getElementById('start_date');
            const endInput = document.getElementById('end_date');
            const today = new Date();
            const fmt = (d) => d.toISOString().slice(0, 10);

            let start, end;
            switch (preset) {
                case 'today':
                    start = end = today;
                    break;
                case '7days':
                    start = new Date(today);
                    start.setDate(start.getDate() - 6);
                    end = today;
                    break;
                case 'this_month':
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    break;
                case 'last_month':
                    start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    end = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
                default:
                    return;
            }

            startInput.value = fmt(start);
            endInput.value = fmt(end);
            document.getElementById('report-filter-form').submit();
        }
    </script>
    <?php $__env->stopPush(); ?>

    <!-- Data Table Card -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm overflow-hidden">
        <!-- Table Header Info -->
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Rincian Laporan Pembayaran</h3>
            <span class="inline-flex items-center rounded-md bg-sky-100 dark:bg-sky-900/40 px-2.5 py-0.5 text-xs font-semibold text-sky-800 dark:text-sky-300">
                Total: <?php echo e($payments->total()); ?> Transaksi
            </span>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                <thead>
                    <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                        <th class="px-6 py-3">No. Kwitansi</th>
                        <th class="px-6 py-3">No. Invoice</th>
                        <th class="px-6 py-3">Pelanggan</th>
                        <th class="px-6 py-3">POP / Cabang</th>
                        <th class="px-6 py-3 text-center">Tanggal Bayar</th>
                        <th class="px-6 py-3 text-center">Metode</th>
                        <th class="px-6 py-3">Kolektor</th>
                        <th class="px-6 py-3 text-right">Nominal</th>
                        <th class="px-6 py-3">Penerima</th>
                        <th class="px-6 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 bg-white dark:bg-slate-800">
                    <?php $__empty_1 = true; $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap">
                                <a href="<?php echo e(route('payments.show', $payment->id)); ?>" class="text-sky-600 hover:text-sky-800 dark:hover:text-sky-300 hover:underline">
                                    <?php echo e($payment->payment_number); ?>

                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if($payment->invoice): ?>
                                    <a href="<?php echo e(route('invoices.show', $payment->invoice_id)); ?>" class="text-slate-700 dark:text-slate-300 hover:text-sky-600 hover:underline font-medium">
                                        <?php echo e($payment->invoice->invoice_number); ?>

                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-400 dark:text-slate-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if($payment->customer): ?>
                                    <div class="font-bold text-slate-900 dark:text-slate-100"><?php echo e($payment->customer->full_name); ?></div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400"><?php echo e($payment->customer->customer_code); ?></div>
                                <?php else: ?>
                                    <span class="text-slate-400 dark:text-slate-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                <?php echo e($payment->pop->name ?? '-'); ?>

                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400 whitespace-nowrap text-center">
                                <?php echo e($payment->payment_date ? $payment->payment_date->format('d/m/Y') : '-'); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-slate-100 dark:bg-slate-700/50 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                                    <?php echo e($methodLabels[$payment->payment_method] ?? strtoupper($payment->payment_method)); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if($payment->collector): ?>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-800/50">
                                        <?php echo e($payment->collector->name); ?>

                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 dark:text-slate-500 text-xs">Langsung</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right font-bold text-slate-900 dark:text-slate-100 whitespace-nowrap">
                                Rp <?php echo e(number_format($payment->amount, 2, ',', '.')); ?>

                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                <?php echo e($payment->receiver->name ?? '-'); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?php echo e($statusBadges[$payment->payment_status->value] ?? 'bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700'); ?>">
                                    <?php echo e($statusLabels[$payment->payment_status->value] ?? $payment->payment_status->label()); ?>

                                </span>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="10" class="px-6 py-10 text-center text-slate-500 dark:text-slate-400">
                                <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Tidak ada data pembayaran yang cocok dengan kriteria filter.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <?php if($payments->hasPages()): ?>
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                <?php echo e($payments->links()); ?>

            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/reports/payments/index.blade.php ENDPATH**/ ?>