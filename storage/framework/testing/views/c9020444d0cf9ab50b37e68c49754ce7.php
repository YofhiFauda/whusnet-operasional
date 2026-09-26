<?php $__env->startSection('title', 'Riwayat Transaksi Pembayaran - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Riwayat Transaksi Pembayaran'); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6" x-data="paymentManager()">
    <?php echo $__env->make('payments.partials.riwayat-banner', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <!-- Naked Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-text-main tracking-tight">Riwayat Transaksi Pembayaran</h1>
            <p class="text-xs text-text-muted mt-1">
                Seluruh riwayat transaksi pembayaran tagihan pelanggan ISP terhubung dengan Invoice & POP.
            </p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <a href="<?php echo e(route('payments.overpay')); ?>" class="inline-flex items-center gap-2 px-3.5 py-2 border border-amber-200 dark:border-amber-500/30 bg-amber-50/80 dark:bg-amber-500/10 hover:bg-amber-100 dark:hover:bg-amber-500/20 text-amber-700 dark:text-amber-300 rounded-lg transition-colors text-xs font-semibold shadow-xs">
                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Lebih Bayar</span>
            </a>
            <a href="<?php echo e(route('invoices.index')); ?>" class="inline-flex items-center gap-2 px-3.5 py-2 border border-border bg-surface hover:bg-surface-muted text-text-main rounded-lg transition-colors text-xs font-semibold shadow-xs">
                <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Daftar Tagihan</span>
            </a>
        </div>
    </div>

    <!-- Stat Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Payments Count -->
        <div class="bg-surface border border-border rounded-xl p-4 transition-all hover:border-primary/40 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">Total Transaksi</span>
                <span class="p-2 rounded-lg bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold font-mono text-text-main"><?php echo e(number_format($payments->total())); ?></span>
                <span class="text-[11px] text-text-muted">transaksi</span>
            </div>
        </div>

        <!-- Total Page Amount -->
        <div class="bg-surface border border-border rounded-xl p-4 transition-all hover:border-emerald-500/40 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">Nominal Hal. Ini</span>
                <span class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2">
                <span class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                    Rp <?php echo e(number_format((float) $payments->sum('amount'), 0, ',', '.')); ?>

                </span>
            </div>
        </div>

        <!-- Payment Methods Breakdown (Current Page) -->
        <div class="bg-surface border border-border rounded-xl p-4 transition-all hover:border-violet-500/40 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">Metode Bayar (Hal. Ini)</span>
                <span class="p-2 rounded-lg bg-violet-50 dark:bg-violet-500/10 text-violet-600 dark:text-violet-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-center gap-3 text-xs flex-wrap">
                <?php
                    $cashCount = $payments->filter(fn($p) => strtolower($p->payment_method) === 'cash')->count();
                    $transferCount = $payments->filter(fn($p) => strtolower($p->payment_method) === 'transfer')->count();
                    $saldoCount = $payments->filter(fn($p) => strtolower($p->payment_method) === 'saldo' || (float)$p->balance_used_amount > 0)->count();
                ?>
                <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold font-mono text-[11px]">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Cash: <?php echo e($cashCount); ?>

                </span>
                <span class="inline-flex items-center gap-1 text-sky-600 dark:text-sky-400 font-semibold font-mono text-[11px]">
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span> Transfer: <?php echo e($transferCount); ?>

                </span>
                <span class="inline-flex items-center gap-1 text-purple-600 dark:text-purple-400 font-semibold font-mono text-[11px]">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span> Saldo: <?php echo e($saldoCount); ?>

                </span>
            </div>
        </div>

        <!-- Overpay Highlight -->
        <div class="bg-surface border border-border rounded-xl p-4 transition-all hover:border-amber-500/40 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">Lebih Bayar (Hal. Ini)</span>
                <span class="p-2 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2">
                <span class="text-xl font-bold font-mono text-amber-600 dark:text-amber-400">
                    Rp <?php echo e(number_format((float) $payments->sum('overpay_amount'), 0, ',', '.')); ?>

                </span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Control Card (Kelola Gudang Style) -->
    <div class="bg-surface border border-border rounded-xl p-4 sm:p-5 shadow-xs">
        <form action="<?php echo e(route('payments.index')); ?>" method="GET" class="space-y-4">
            <!-- Row 1: Search and Primary Inputs Unified -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                <!-- Search Input: Positioned next to inputs -->
                <div class="sm:col-span-2 lg:col-span-2">
                    <label for="search" class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">
                        Cari Pembayaran
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" name="search" id="search" value="<?php echo e($search); ?>" 
                               placeholder="Cari No. Bayar, Invoice, Nama, CID, HP, Rekening..." 
                               class="w-full pl-9 pr-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 placeholder:text-text-muted/60 transition-colors">
                    </div>
                </div>

                <!-- Dari Tanggal -->
                <div>
                    <label for="date_from" class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo e($dateFrom); ?>" 
                           class="w-full px-3 py-2 text-xs font-mono border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 transition-colors">
                </div>

                <!-- Sampai Tanggal -->
                <div>
                    <label for="date_to" class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo e($dateTo); ?>" 
                           class="w-full px-3 py-2 text-xs font-mono border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 transition-colors">
                </div>

                <!-- POP / Cabang -->
                <div>
                    <label for="pop_id" class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">POP / Cabang</label>
                    <select name="pop_id" id="pop_id" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 transition-colors">
                        <option value="">— Semua Cabang POP —</option>
                        <?php $__currentLoopData = $pops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($pop->id); ?>" <?php echo e((string) $popId === (string) $pop->id ? 'selected' : ''); ?>>
                                <?php echo e($pop->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <!-- Metode Bayar -->
                <div>
                    <label for="method" class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Metode Bayar</label>
                    <select name="method" id="method" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 transition-colors">
                        <option value="">— Semua Metode —</option>
                        <?php $__currentLoopData = $allowedMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paymentMethod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($paymentMethod); ?>" <?php echo e($method === $paymentMethod ? 'selected' : ''); ?>>
                                <?php echo e(strtoupper($paymentMethod)); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
            </div>

            <!-- Row 2: Secondary Filters & Action Buttons -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-border/70">
                <div class="flex items-center gap-2 flex-wrap text-xs text-text-muted">
                    <span>Menampilkan <strong class="text-text-main"><?php echo e($payments->firstItem() ?? 0); ?></strong> - <strong class="text-text-main"><?php echo e($payments->lastItem() ?? 0); ?></strong> dari <strong class="text-text-main"><?php echo e($payments->total()); ?></strong> pembayaran</span>
                    <?php if($search !== '' || $popId !== '' || $dateFrom !== '' || $dateTo !== '' || $method !== '' || $status !== '' || $invoiceType !== ''): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary border border-primary/20">
                            Filter Aktif
                        </span>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2">
                    <?php if($search !== '' || $popId !== '' || $dateFrom !== '' || $dateTo !== '' || $method !== '' || $status !== '' || $invoiceType !== ''): ?>
                        <a href="<?php echo e(route('payments.index')); ?>" 
                           class="inline-flex items-center gap-1 px-3.5 py-1.5 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-lg transition-colors text-xs font-semibold">
                            <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span>Reset Filter</span>
                        </a>
                    <?php endif; ?>
                    <button type="submit" 
                            class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-primary hover:bg-primary-focus text-white text-xs font-semibold rounded-lg shadow-2xs transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/25">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                        <span>Terapkan Filter</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Payments Table View -->
    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs text-text-secondary">
                <thead>
                    <tr class="bg-surface-muted/60 border-b border-border text-text-muted font-bold text-[10px] uppercase tracking-wider">
                        <th class="px-4 py-3.5 w-10 text-center">No</th>
                        <th class="px-4 py-3.5">No. Transaksi</th>
                        <th class="px-4 py-3.5">No. Invoice</th>
                        <th class="px-4 py-3.5">Pelanggan</th>
                        <th class="px-4 py-3.5">POP / Cabang</th>
                        <th class="px-4 py-3.5">Tanggal</th>
                        <th class="px-4 py-3.5">Metode</th>
                        <th class="px-4 py-3.5">Penerima / Kolektor</th>
                        <th class="px-4 py-3.5 text-right">Nominal (Rp)</th>
                        <th class="px-4 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php $__empty_1 = true; $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $statusVal = is_object($payment->payment_status) ? $payment->payment_status->value : $payment->payment_status;
                            $statusLabel = is_object($payment->payment_status) ? $payment->payment_status->label() : ucwords(str_replace('_', ' ', $payment->payment_status));

                            $badgeClass = match($statusVal) {
                                'valid' => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                                'ditolak' => 'bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800',
                                default => 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
                            };

                            $isSaldoSettled = strtolower($payment->payment_method) === 'saldo' || ((float)$payment->balance_used_amount > 0 && (float)$payment->balance_used_amount >= (float)$payment->amount);
                        ?>
                        <tr class="hover:bg-surface-muted/50 transition-colors">
                            <td class="px-4 py-3.5 text-center text-text-muted font-mono"><?php echo e(($payments->currentPage() - 1) * $payments->perPage() + $loop->iteration); ?></td>
                            
                            <!-- Payment Number -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <a href="<?php echo e(route('payments.show', $payment->id)); ?>" class="font-mono font-bold text-primary hover:text-primary-focus transition-colors">
                                    <?php echo e($payment->payment_number); ?>

                                </a>
                                <?php if($payment->old_payment_id): ?>
                                    <div class="mt-0.5">
                                        <span title="Data Migrasi (ID Bayar Lama: <?php echo e($payment->old_payment_id); ?>)" class="inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold rounded border bg-primary/10 text-primary border-primary/20">
                                            Migrasi #<?php echo e($payment->old_payment_id); ?>

                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Invoice Number -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <?php if($payment->invoice): ?>
                                    <a href="<?php echo e(route('invoices.show', $payment->invoice_id)); ?>" class="font-mono font-semibold text-text-main hover:text-primary transition-colors flex items-center gap-1">
                                        <svg class="w-3 h-3 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        <span><?php echo e($payment->invoice->invoice_number); ?></span>
                                    </a>
                                <?php else: ?>
                                    <span class="text-text-muted italic">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Customer -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <div class="font-semibold text-text-main"><?php echo e($payment->customer->full_name ?? '-'); ?></div>
                                <div class="text-[10px] text-text-muted font-mono flex items-center gap-1">
                                    <span>CID: <?php echo e($payment->customer->cid ?? $payment->customer->customer_code ?? '-'); ?></span>
                                </div>
                            </td>

                            <!-- POP -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted text-text-secondary border border-border">
                                    <?php echo e($payment->pop->name ?? '-'); ?>

                                </span>
                            </td>

                            <!-- Payment Date -->
                            <td class="px-4 py-3.5 whitespace-nowrap font-mono text-text-secondary">
                                <?php echo e(optional($payment->payment_date)->format('d/m/Y')); ?>

                            </td>

                            <!-- Payment Method (Label for Transfer) -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <?php if(strtolower($payment->payment_method) === 'transfer'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] font-bold rounded-md border bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-500/20 w-fit">
                                        <svg class="w-3 h-3 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <span><?php echo e($payment->bankAccount?->label ?? ($payment->bank_name ? $payment->bank_name.($payment->account_number ? ' - '.$payment->account_number : '') : 'Transfer Bank')); ?></span>
                                    </span>
                                <?php elseif(strtolower($payment->payment_method) === 'cash'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-md border uppercase tracking-wider bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        CASH
                                    </span>
                                <?php elseif(strtolower($payment->payment_method) === 'kolektor'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-md border uppercase tracking-wider bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>
                                        KOLEKTOR
                                    </span>
                                <?php elseif(strtolower($payment->payment_method) === 'saldo'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] font-bold rounded-md border bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800/60">
                                        <svg class="w-3 h-3 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Lunas dari Saldo</span>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-md border uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700">
                                        <?php echo e(strtoupper($payment->payment_method)); ?>

                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Receiver / Collector -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <?php if($payment->collector): ?>
                                    <div class="flex flex-col">
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-500/20 w-fit">
                                            Kolektor: <?php echo e($payment->collector->name); ?>

                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-text-muted text-[11px]">Bayar Langsung</span>
                                <?php endif; ?>
                            </td>

                            <!-- Amount & Overpay -->
                            <td class="px-4 py-3.5 text-right font-mono whitespace-nowrap">
                                <div class="font-bold text-text-main text-xs">
                                    Rp <?php echo e(number_format((float) $payment->amount, 0, ',', '.')); ?>

                                </div>
                                <?php if((float) $payment->overpay_amount > 0): ?>
                                    <span class="inline-block text-[10px] font-bold text-amber-600 dark:text-amber-400" title="Uang lebih yang diserahkan pelanggan">
                                        +<?php echo e(number_format((float) $payment->overpay_amount, 0, ',', '.')); ?> lebih
                                    </span>
                                <?php endif; ?>
                                <?php if((float) $payment->balance_used_amount > 0 && strtolower($payment->payment_method) !== 'saldo'): ?>
                                    <div class="text-[10px] font-semibold text-purple-600 dark:text-purple-400">
                                        (Pakai Saldo: Rp <?php echo e(number_format((float) $payment->balance_used_amount, 0, ',', '.')); ?>)
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Action Buttons -->
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Button Edit (Trigger Modal Kelola Gudang Style) -->
                                    <button type="button" 
                                            @click="openEditModal(<?php echo \Illuminate\Support\Js::from([
                                                'id' => $payment->id,
                                                'payment_number' => $payment->payment_number,
                                                'invoice_number' => $payment->invoice?->invoice_number ?? '-',
                                                'customer_name' => $payment->customer?->full_name ?? '-',
                                                'amount' => (float) $payment->amount,
                                                'payment_date' => optional($payment->payment_date)->format('Y-m-d'),
                                                'payment_method' => $payment->payment_method,
                                                'bank_account_id' => $payment->bank_account_id,
                                                'bank_name' => $payment->bank_name,
                                                'account_number' => $payment->account_number,
                                                'sender_name' => $payment->sender_name,
                                                'collected_by' => $payment->collected_by,
                                                'note' => $payment->note,
                                                'update_url' => route('payments.update', $payment->id),
                                                'is_ditolak' => $statusVal === 'ditolak',
                                            ])->toHtml() ?>)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 border border-sky-200 dark:border-sky-500/30 bg-sky-50/80 dark:bg-sky-500/10 hover:bg-sky-100 dark:hover:bg-sky-500/20 text-sky-700 dark:text-sky-300 rounded-md transition-colors text-xs font-semibold cursor-pointer shadow-2xs"
                                            title="Edit Pembayaran">
                                        <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                        <span>Edit</span>
                                    </button>

                                    <!-- Button Detail -->
                                    <a href="<?php echo e(route('payments.show', $payment->id)); ?>" class="inline-flex items-center gap-1 px-2.5 py-1 border border-border bg-surface hover:bg-surface-muted text-text-main rounded-md transition-colors text-xs font-semibold shadow-2xs">
                                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>Detail</span>
                                    </a>

                                    <!-- Button Cetak Kwitansi -->
                                    <a href="<?php echo e(route('payments.receipt', $payment->id)); ?>" target="_blank" class="p-1 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-md transition-colors text-xs" title="Cetak Kwitansi">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-text-muted">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <svg class="w-10 h-10 mx-auto text-text-muted/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" />
                                    </svg>
                                    <p class="text-xs font-semibold text-text-main">Tidak ada pembayaran ditemukan</p>
                                    <p class="text-[11px] text-text-muted">Coba ubah kata kunci pencarian atau tanggal filter di atas.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-border bg-surface-muted/30">
            <?php echo e($payments->links()); ?>

        </div>
    </div>

    <!-- Modal: Edit Pembayaran (Kelola Gudang Modal Style) -->
    <div x-show="editModalOpen" 
         x-cloak
         @keydown.escape.window="closeEditModal()"
         class="fixed inset-0 z-[80] overflow-y-auto" 
         aria-labelledby="modal-edit-payment-title" 
         role="dialog" 
         aria-modal="true"
         style="display: none;">
        
        <!-- Backdrop Overlay -->
        <div x-show="editModalOpen" 
             x-transition.opacity 
             @click="closeEditModal()" 
             class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs transition-opacity"></div>

        <!-- Modal Center Dialog -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="editModalOpen"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 @click.stop
                 class="relative transform overflow-hidden rounded-xl bg-surface text-left shadow-xl transition-all w-full sm:max-w-lg sm:my-8 border border-border">
                
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-border bg-surface-muted/50 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-text-main flex items-center gap-2" id="modal-edit-payment-title">
                            <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-500/20">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                            </span>
                            <span>Edit Pembayaran</span>
                        </h3>
                        <p class="text-[11px] text-text-muted mt-0.5">Koreksi detail metode pembayaran, bank, atau catatan transaksi.</p>
                    </div>
                    <button type="button" @click="closeEditModal()" class="text-text-muted hover:text-text-main p-1 rounded-lg hover:bg-surface-muted transition-colors">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>

                <!-- Form Section -->
                <form :action="editData.update_url" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>

                    <!-- Read-Only Payment Info Card -->
                    <div class="bg-surface-muted/60 border border-border/80 rounded-lg p-3 space-y-1.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-text-muted">No. Transaksi</span>
                            <span class="font-mono font-bold text-primary" x-text="editData.payment_number"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-text-muted">Invoice / Pelanggan</span>
                            <span class="font-semibold text-text-main" x-text="editData.invoice_number + ' • ' + editData.customer_name"></span>
                        </div>
                        <div class="flex items-center justify-between pt-1 border-t border-border/60">
                            <span class="text-[11px] text-text-muted">Nominal Pembayaran</span>
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400" x-text="'Rp ' + Number(editData.amount || 0).toLocaleString('id-ID')"></span>
                        </div>
                    </div>

                    <!-- Tanggal Pembayaran -->
                    <div>
                        <label for="modal_payment_date" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Tanggal Bayar</label>
                        <input type="date" name="payment_date" id="modal_payment_date" x-model="editData.payment_date" required
                               max="<?php echo e(now()->format('Y-m-d')); ?>"
                               class="w-full px-3 py-2 text-xs font-mono border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary transition-colors">
                    </div>

                    <!-- Metode Pembayaran -->
                    <div>
                        <label for="modal_payment_method" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Metode Bayar</label>
                        <select name="payment_method" id="modal_payment_method" x-model="editData.payment_method" required
                                class="w-full px-3 py-2 text-xs font-semibold border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary transition-colors">
                            <option value="cash">CASH (Tunai)</option>
                            <option value="transfer">TRANSFER (Bank)</option>
                            <option value="kolektor">KOLEKTOR</option>
                            <option value="saldo">SALDO PELANGGAN</option>
                            <option value="lainnya">LAINNYA</option>
                        </select>
                    </div>

                    <!-- Dynamic Section: Transfer Bank Details -->
                    <div x-show="editData.payment_method === 'transfer'" x-cloak class="space-y-3 p-3 rounded-lg bg-sky-50/50 dark:bg-sky-950/30 border border-sky-200/80 dark:border-sky-800/60">
                        <div>
                            <label for="modal_bank_account_id" class="block text-[10px] font-bold text-sky-800 dark:text-sky-300 uppercase tracking-wider mb-1">Rekening Tujuan (Master Bank)</label>
                            <select name="bank_account_id" id="modal_bank_account_id" x-model="editData.bank_account_id"
                                    :required="editData.payment_method === 'transfer'"
                                    class="w-full px-3 py-2 text-xs font-semibold border border-sky-300 dark:border-sky-700 rounded-lg bg-surface text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/25 transition-colors">
                                <option value="">— Pilih Rekening Bank Tujuan —</option>
                                <?php $__currentLoopData = $bankAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bankAccount): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($bankAccount->id); ?>"><?php echo e($bankAccount->displayName()); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div>
                            <label for="modal_sender_name" class="block text-[10px] font-bold text-sky-800 dark:text-sky-300 uppercase tracking-wider mb-1">Nama Pengirim (Opsional)</label>
                            <input type="text" name="sender_name" id="modal_sender_name" x-model="editData.sender_name" maxlength="150"
                                   placeholder="Nama pemilik rekening pengirim transfer..."
                                   class="w-full px-3 py-2 text-xs border border-sky-300 dark:border-sky-700 rounded-lg bg-surface text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/25 transition-colors">
                        </div>
                    </div>

                    <!-- Dynamic Section: Kolektor Selection -->
                    <div x-show="editData.payment_method === 'kolektor'" x-cloak class="space-y-2 p-3 rounded-lg bg-violet-50/50 dark:bg-violet-950/30 border border-violet-200/80 dark:border-violet-800/60">
                        <label for="modal_collected_by" class="block text-[10px] font-bold text-violet-800 dark:text-violet-300 uppercase tracking-wider mb-1">Kolektor Penagih</label>
                        <select name="collected_by" id="modal_collected_by" x-model="editData.collected_by"
                                :required="editData.payment_method === 'kolektor'"
                                class="w-full px-3 py-2 text-xs font-semibold border border-violet-300 dark:border-violet-700 rounded-lg bg-surface text-text-main focus:outline-none focus:ring-2 focus:ring-violet-500/25 transition-colors">
                            <option value="">— Pilih Petugas Kolektor —</option>
                            <?php $__currentLoopData = $collectors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $collector): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($collector->id); ?>"><?php echo e($collector->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <!-- Bukti Pembayaran -->
                    <div>
                        <label for="modal_proof_file" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Bukti Pembayaran (Opsional)</label>
                        <input type="file" name="proof_file" id="modal_proof_file" accept=".jpg,.jpeg,.png,.pdf"
                               class="w-full px-3 py-1.5 border border-border rounded-lg text-xs bg-surface text-text-main file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-surface-muted file:text-text-main hover:file:bg-border transition-colors">
                        <p class="text-[10px] text-text-muted mt-1">Unggah untuk memperbarui berkas bukti (JPG, PNG, PDF maks 2MB).</p>
                    </div>

                    <!-- Catatan -->
                    <div>
                        <label for="modal_note" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Catatan Pembayaran</label>
                        <textarea name="note" id="modal_note" x-model="editData.note" rows="2"
                                  :required="editData.payment_method === 'lainnya'"
                                  placeholder="Catatan transaksi atau keterangan tambahan..."
                                  class="w-full px-3 py-2 border border-border rounded-lg text-xs bg-surface text-text-main focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary transition-colors"></textarea>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-border">
                        <button type="button" @click="closeEditModal()"
                                class="px-4 py-2 border border-border text-text-secondary bg-surface hover:bg-surface-muted font-semibold rounded-lg shadow-2xs transition-colors text-xs cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-primary hover:bg-primary-focus text-white font-semibold rounded-lg shadow-2xs transition-colors text-xs flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function paymentManager() {
        return {
            editModalOpen: false,
            editData: {
                id: null,
                payment_number: '',
                invoice_number: '',
                customer_name: '',
                amount: 0,
                payment_date: '',
                payment_method: 'cash',
                bank_account_id: '',
                bank_name: '',
                account_number: '',
                sender_name: '',
                collected_by: '',
                note: '',
                update_url: '',
            },
            openEditModal(data) {
                this.editData = Object.assign({}, data);
                this.editModalOpen = true;
                document.body.classList.add('overflow-hidden');
            },
            closeEditModal() {
                this.editModalOpen = false;
                document.body.classList.remove('overflow-hidden');
            }
        };
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/payments/index.blade.php ENDPATH**/ ?>