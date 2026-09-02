<?php
    $pageTitle = 'Daftar Tagihan';
    $formAction = route('invoices.index');
    if (isset($statusGroup) && $statusGroup !== '') {
        if ($statusGroup === 'lunas') {
            $pageTitle = 'Tagihan Lunas';
            $formAction = route('invoices.lunas');
        } elseif ($statusGroup === 'belum_lunas') {
            $pageTitle = 'Tagihan Belum Lunas';
            $formAction = route('invoices.belum-lunas');
        }
    }
?>

<?php $__env->startSection('title', $pageTitle . ' - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', $pageTitle); ?>

<?php $__env->startSection('content'); ?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-slate-800 dark:text-slate-200 text-sm font-semibold uppercase tracking-wider">Daftar dan Filter <?php echo e($pageTitle); ?></h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Tagihan berasal dari pelanggan aktif dan layanan pelanggan yang sudah tersimpan.</p>
    </div>
    <div class="flex items-center gap-2">
        <?php if(auth()->user()->hasPermission('collector_worksheet.view')): ?>
            <a href="<?php echo e(route('collector-worksheet.index')); ?>" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
                Kolektor
            </a>
        <?php endif; ?>
        <a href="/customers" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Buka Data Pelanggan
        </a>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white dark:bg-slate-800 border border-orange-200 dark:border-orange-500/30 rounded-lg p-4 flex items-center justify-between">
        <div>
            <span class="block text-[10px] font-bold text-orange-600 dark:text-orange-500 uppercase tracking-wider mb-1">Total Nunggak — Tagihan Awal</span>
            <span class="block text-lg font-bold text-slate-800 dark:text-slate-200 font-mono">Rp <?php echo e(number_format($unpaidAwalTotal, 0, ',', '.')); ?></span>
        </div>
        <span class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold border uppercase tracking-wide bg-orange-50 dark:bg-orange-500/10 text-orange-700 dark:text-orange-400 border-orange-200 dark:border-orange-500/20">Awal / Reaktivasi</span>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-sky-200 dark:border-sky-500/30 rounded-lg p-4 flex items-center justify-between">
        <div>
            <span class="block text-[10px] font-bold text-sky-600 dark:text-sky-500 uppercase tracking-wider mb-1">Total Nunggak — Tagihan Bulanan</span>
            <span class="block text-lg font-bold text-slate-800 dark:text-slate-200 font-mono">Rp <?php echo e(number_format($unpaidBulananTotal, 0, ',', '.')); ?></span>
        </div>
        <span class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold border uppercase tracking-wide bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-500/20">Bulanan</span>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <form action="<?php echo e($formAction); ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        <div>
            <label for="search" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">CARI TAGIHAN</label>
            <input type="text" name="search" id="search" value="<?php echo e($search); ?>" placeholder="Cari Nama, No. Tagihan, atau ID Invoice Lama..." class="w-full font-sans text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <div>
            <label for="pop_id" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">POP / CABANG</label>
            <select name="pop_id" id="pop_id" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua POP</option>
                <?php $__currentLoopData = $pops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($pop->id); ?>" <?php echo e((string) $popId === (string) $pop->id ? 'selected' : ''); ?>><?php echo e($pop->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div>
            <label for="billing_period" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">PERIODE</label>
            <input type="month" name="billing_period" id="billing_period" value="<?php echo e($billingPeriod); ?>" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <div>
            <label for="invoice_type" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">JENIS TAGIHAN</label>
            <select name="invoice_type" id="invoice_type" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Jenis</option>
                <option value="awal" <?php echo e(($invoiceType ?? '') === 'awal' ? 'selected' : ''); ?>>Tagihan Awal (PSB)</option>
                <option value="bulanan" <?php echo e(($invoiceType ?? '') === 'bulanan' ? 'selected' : ''); ?>>Tagihan Bulanan Rutin</option>
                <option value="reaktivasi" <?php echo e(($invoiceType ?? '') === 'reaktivasi' ? 'selected' : ''); ?>>Tagihan Reaktivasi</option>
            </select>
        </div>

        <div>
            <label for="status" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">STATUS</label>
            <select name="status" id="status" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Status</option>
                <?php $__currentLoopData = $allowedStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoiceStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if(!isset($statusGroup) || $statusGroup === '' || 
                        ($statusGroup === 'lunas' && $invoiceStatus === 'lunas') || 
                        ($statusGroup === 'belum_lunas' && ($invoiceStatus === 'belum_dibayar' || $invoiceStatus === 'sebagian'))): ?>
                        <option value="<?php echo e($invoiceStatus); ?>" <?php echo e($status === $invoiceStatus ? 'selected' : ''); ?>><?php echo e(ucwords(str_replace('_', ' ', $invoiceStatus))); ?></option>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500/25">
                Filter
            </button>
            <a href="<?php echo e($formAction); ?>" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none">
                Reset
            </a>
        </div>
    </form>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-slate-700 dark:text-slate-200">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">NO. TAGIHAN</th>
                    <th class="px-6 py-3.5">PELANGGAN</th>
                    <th class="px-6 py-3.5">POP</th>
                    <th class="px-6 py-3.5">PERIODE</th>
                    <th class="px-6 py-3.5 text-right">TOTAL</th>
                    <th class="px-6 py-3.5 text-right">SISA</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                <?php $__empty_1 = true; $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $badgeClass = match($invoice->invoice_status->value) {
                            'lunas' => 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400 border-green-100 dark:border-green-500/20',
                            'sebagian' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-100 dark:border-amber-500/20',
                            'batal' => 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 border-red-100 dark:border-red-500/20',
                            default => 'bg-slate-50 dark:bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-100 dark:border-slate-500/20',
                        };

                        // Baris induk dapat tombol expand HANYA selama tagihan
                        // masih dicicil. Begitu lunas, riwayat cicilan tak lagi
                        // ditumpuk di list — cukup Total & Sisa (§D-4); rincian
                        // lengkapnya ada di halaman Detail tagihan.
                        $isInstallment = $invoice->invoice_status->value === 'sebagian'
                            && $invoice->payments->isNotEmpty();
                        $columnCount = 9;
                        // Invoice TIDAK punya relasi latestPayment (itu cuma ada di
                        // Customer) — ambil dari koleksi payments yang sudah dimuat
                        // terurut payment_date ASC, jadi tinggal last().
                        $latestInvoicePayment = $invoice->payments->last();
                    ?>
                    <tr class="hover:bg-slate-50/45 dark:hover:bg-slate-700/25 transition-colors" id="invoice-row-<?php echo e($invoice->id); ?>">
                        <td class="px-6 py-3.5 text-center text-slate-400 dark:text-slate-500 data-text"><?php echo e(($invoices->currentPage() - 1) * $invoices->perPage() + $loop->iteration); ?></td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <?php if($isInstallment): ?>
                                    <button type="button"
                                            class="p-0.5 rounded text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors focus:outline-none cursor-pointer"
                                            data-expanded="false"
                                            onclick="toggleInstallments(<?php echo e($invoice->id); ?>, this)"
                                            title="Lihat riwayat cicilan">
                                        <svg class="h-4 w-4 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                <?php else: ?>
                                    <span class="w-5 inline-block"></span>
                                <?php endif; ?>
                                <a href="<?php echo e(route('invoices.show', $invoice->id)); ?>" class="font-mono font-bold text-sky-700 dark:text-sky-400 hover:text-sky-900 dark:hover:text-sky-300"><?php echo e($invoice->invoice_number); ?></a>
                            </div>
                            <div class="flex items-center flex-wrap gap-1.5 mt-1">
                                <?php if($invoice->old_invoice_id || $invoice->old_cost_id): ?>
                                <span title="Data Migrasi (ID Lama: <?php echo e($invoice->old_invoice_id ?: $invoice->old_cost_id); ?>)" class="px-1.5 py-0.5 text-[9px] font-bold rounded border bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-500/20">
                                    Migrasi #<?php echo e($invoice->old_invoice_id ?: $invoice->old_cost_id); ?>

                                </span>
                                <?php endif; ?>
                                <?php if($invoice->invoice_type): ?>
                                <span class="px-1.5 py-0.5 text-[9px] font-bold rounded border bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-100 dark:border-sky-500/20">
                                    <?php echo e($invoice->invoice_type->label()); ?>

                                </span>
                                <?php endif; ?>
                                <span class="text-[10px] text-slate-400 dark:text-slate-500">Tempo <?php echo e(optional($invoice->due_date)->format('d/m/Y')); ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <div class="font-semibold text-slate-900 dark:text-slate-200"><?php echo e($invoice->customer->full_name ?? '-'); ?></div>
                            <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono"><?php echo e($invoice->customer->cid ?? $invoice->customer->customer_code ?? '-'); ?></div>
                            <?php if($invoice->customer && $invoice->customer->collector_id): ?>
                                <span class="inline-flex items-center mt-1 px-1.5 py-0.5 text-[9px] font-bold rounded border bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-100 dark:border-violet-500/20">
                                    Kolektor: <?php echo e($invoice->customer->collector?->name ?? '-'); ?>

                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-medium text-slate-800 dark:text-slate-200"><?php echo e($invoice->pop->name ?? '-'); ?></td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-mono"><?php echo e($invoice->billing_period); ?></td>
                        <td class="px-6 py-3.5 text-right font-mono font-semibold">Rp <?php echo e(number_format((float) $invoice->total_amount, 2, ',', '.')); ?></td>
                        <td class="px-6 py-3.5 text-right font-mono" id="invoice-remaining-<?php echo e($invoice->id); ?>">Rp <?php echo e(number_format((float) $invoice->remaining_amount, 2, ',', '.')); ?></td>
                        <td class="px-6 py-3.5 text-center whitespace-nowrap">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border <?php echo e($badgeClass); ?>" id="invoice-status-badge-<?php echo e($invoice->id); ?>" data-badge-style="pill">
                                <?php echo e($invoice->invoice_status->label()); ?>

                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payments.create')): ?>
                                    <span id="invoice-pay-btn-<?php echo e($invoice->id); ?>" class="<?php echo e(in_array($invoice->invoice_status->value, ['lunas', 'batal'], true) ? 'hidden' : ''); ?>">
                                        <button type="button"
                                                data-invoice-id="<?php echo e($invoice->id); ?>"
                                                data-invoice-number="<?php echo e($invoice->invoice_number); ?>"
                                                data-remaining="<?php echo e((float) $invoice->remaining_amount); ?>"
                                                
                                                data-payment-store-url="<?php echo e(route('invoices.payments.store', $invoice->id)); ?>"
                                                onclick="openQuickPaymentModal(parseInt(this.dataset.invoiceId, 10), this.dataset.invoiceNumber, parseFloat(this.dataset.remaining), this.dataset.paymentStoreUrl)"
                                                class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md transition-colors text-xs font-semibold cursor-pointer">
                                            <?php echo e($invoice->invoice_status->value === 'sebagian' ? 'Bayar Cicil' : 'Bayar'); ?>

                                        </button>
                                    </span>
                                <?php endif; ?>
                                <?php if($invoice->invoice_status->value === 'lunas' && auth()->user()->hasPermission('payments.view') && $latestInvoicePayment): ?>
                                    <a href="<?php echo e(route('payments.receipt', $latestInvoicePayment->id)); ?>" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1.5 border border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 hover:bg-sky-100 dark:hover:bg-sky-900/60 rounded-md transition-colors text-xs font-semibold" title="Cetak Struk Pembayaran Terakhir">
                                        <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-5a2 2 0 00-2-2H5a2 2 0 00-2 2v5a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4"/></svg>
                                        <span>Struk</span>
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo e(route('invoices.show', $invoice->id)); ?>" class="inline-flex items-center px-3 py-1.5 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-md transition-colors text-xs font-semibold">
                                    Detail
                                </a>
                            </div>
                        </td>
                    </tr>

                    <?php if($isInstallment): ?>
                        <?php $runningPaid = 0.0; $invoiceTotal = round((float) $invoice->total_amount, 2); ?>
                        <?php $__currentLoopData = $invoice->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $installment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php $runningPaid = round($runningPaid + (float) $installment->amount, 2); ?>
                            <tr class="hidden bg-slate-50/60 dark:bg-slate-900/40 installment-row-<?php echo e($invoice->id); ?>">
                                <td colspan="<?php echo e($columnCount); ?>" class="px-6 py-2.5">
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 pl-8 text-xs">
                                        <span class="font-semibold text-slate-700 dark:text-slate-200">Cicilan Ke-<?php echo e($loop->iteration); ?></span>
                                        <a href="<?php echo e(route('payments.show', $installment->id)); ?>" class="font-mono text-sky-700 dark:text-sky-400 hover:underline"><?php echo e($installment->payment_number); ?></a>
                                        <span class="text-slate-500 dark:text-slate-400"><?php echo e(optional($installment->payment_date)->format('d/m/Y')); ?></span>
                                        <span class="px-1.5 py-0.5 text-[9px] font-bold rounded border bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-100 dark:border-emerald-500/20">
                                            <?php echo e(strtoupper($installment->payment_method)); ?>

                                        </span>
                                        <span class="px-1.5 py-0.5 text-[9px] font-bold rounded border bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-100 dark:border-violet-500/20">
                                            <?php echo e($installment->collector?->name ?? 'Langsung'); ?>

                                        </span>
                                        <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">Rp <?php echo e(number_format((float) $installment->amount, 2, ',', '.')); ?></span>
                                        <?php if((float) $installment->overpay_amount > 0): ?>
                                            <span class="text-[10px] font-semibold text-amber-600 dark:text-amber-400" title="Uang lebih dari pelanggan — catatan saja, tidak menambah pembayaran tagihan">
                                                +<?php echo e(number_format((float) $installment->overpay_amount, 0, ',', '.')); ?> lebih
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-slate-500 dark:text-slate-400">
                                            Sisa setelah ini: <span class="font-mono">Rp <?php echo e(number_format(max(0, round($invoiceTotal - $runningPaid, 2)), 2, ',', '.')); ?></span>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="9" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada tagihan yang sesuai filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700">
        <?php echo e($invoices->links()); ?>

    </div>
</div>

<?php echo $__env->make('payments.partials.quick-payment-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<?php $__env->startPush('scripts'); ?>
    <script>
        function toggleInstallments(invoiceId, button) {
            const expanded = button.getAttribute('data-expanded') === 'true';
            button.setAttribute('data-expanded', expanded ? 'false' : 'true');
            button.querySelector('svg').classList.toggle('rotate-90', !expanded);

            document.querySelectorAll('.installment-row-' + invoiceId).forEach((row) => {
                row.classList.toggle('hidden', expanded);
            });
        }
    </script>
<?php $__env->stopPush(); ?>


<?php $__env->startPush('scripts'); ?>
    <script>
        const INVOICE_STATUS_LABELS = {
            belum_dibayar: 'Belum Dibayar',
            sebagian: 'Sebagian',
            lunas: 'Lunas',
            batal: 'Batal',
        };

        const INVOICE_STATUS_BADGE_CLASSES = {
            lunas: 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400 border-green-100 dark:border-green-500/20',
            sebagian: 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-100 dark:border-amber-500/20',
            batal: 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 border-red-100 dark:border-red-500/20',
            belum_dibayar: 'bg-slate-50 dark:bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-100 dark:border-slate-500/20',
        };

        function applyInvoiceUpdate(data) {
            const row = document.getElementById('invoice-row-' + data.invoice_id);
            if (!row) {
                return false;
            }

            const remainingCell = document.getElementById('invoice-remaining-' + data.invoice_id);
            if (remainingCell) {
                remainingCell.textContent = 'Rp ' + Number(data.remaining_amount).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            const badge = document.getElementById('invoice-status-badge-' + data.invoice_id);
            if (badge) {
                badge.textContent = data.invoice_status_label || INVOICE_STATUS_LABELS[data.invoice_status] || data.invoice_status;
                badge.className = 'px-2 py-0.5 text-[10px] font-bold rounded-full border ' +
                    (INVOICE_STATUS_BADGE_CLASSES[data.invoice_status] || INVOICE_STATUS_BADGE_CLASSES.belum_dibayar);
            }

            const payBtnWrap = document.getElementById('invoice-pay-btn-' + data.invoice_id);
            if (payBtnWrap) {
                const settled = data.invoice_status === 'lunas' || data.invoice_status === 'batal';
                payBtnWrap.classList.toggle('hidden', settled);

                const btn = payBtnWrap.querySelector('button');
                if (btn && !settled) {
                    btn.dataset.remaining = data.remaining_amount;
                    btn.textContent = data.invoice_status === 'sebagian' ? 'Bayar Cicil' : 'Bayar';
                }
            }

            return true;
        }

        // Aksi sendiri lewat modal Bayar Cepat — lihat quick-payment-modal.blade.php.
        document.addEventListener('payment-recorded', function (e) {
            if (applyInvoiceUpdate(e.detail)) {
                e.preventDefault();
            }
        });

        // Aksi user lain di POP yang sama, lewat Reverb.
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.Echo === 'undefined' || !window.Echo) {
                return;
            }

            const popIds = <?php echo json_encode($invoices->pluck('pop_id')->unique()->values(), 15, 512) ?>;
            popIds.forEach(function (popId) {
                window.Echo.private('invoices.' + popId)
                    .listen('.InvoiceStatusUpdated', applyInvoiceUpdate);
            });
        });
    </script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/invoices/index.blade.php ENDPATH**/ ?>