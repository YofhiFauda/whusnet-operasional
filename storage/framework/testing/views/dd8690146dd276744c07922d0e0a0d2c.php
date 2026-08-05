<?php $__env->startSection('title', 'Detail Pembayaran ' . $payment->payment_number . ' - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Detail Pembayaran'); ?>
<?php $__env->startSection('breadcrumb_parent', 'Pembayaran'); ?>
<?php $__env->startSection('breadcrumb_parent_url', route('payments.index')); ?>

<?php $__env->startSection('content'); ?>
<?php
    $badgeClass = match($payment->payment_status->value) {
        'valid' => 'bg-emerald-50 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        'ditolak' => 'bg-red-50 dark:bg-red-950/80 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800',
        default => 'bg-amber-50 dark:bg-amber-950/80 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
    };
?>

<style>
    @media print {
        .no-print, header, sidebar, footer, nav, #toast, .modal { display: none !important; }
        .print-only { display: block !important; }
        .screen-only { display: none !important; }
        body { background: white !important; color: black !important; padding: 0 !important; }
    }
    @media screen {
        .print-only { display: none !important; }
    }
</style>

<!-- PRINT ONLY A4 KWITANSI PEMBAYARAN SHEET -->
<div class="print-only p-8 bg-white text-slate-900 font-sans text-xs leading-normal">
    <!-- Header -->
    <div class="flex justify-between items-start border-b pb-4 mb-4 border-slate-300">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">WHUSNET</h1>
            <p class="text-xs text-slate-600 font-medium"><?php echo e($payment->pop->name ?? 'Kantor Pusat'); ?> &bull; ISP Service</p>
            <p class="text-[10px] text-slate-500 mt-0.5">Website Billing ISP Internal</p>
        </div>
        <div class="text-right">
            <h2 class="text-base font-bold text-slate-900 uppercase tracking-wide">STRUK / KWITANSI PEMBAYARAN</h2>
            <p class="font-mono text-xs font-semibold text-slate-700">No. Transaksi: <?php echo e($payment->payment_number); ?></p>
            <p class="text-xs text-slate-600 mt-1">Status: <span class="font-bold uppercase text-emerald-700">● <?php echo e($payment->payment_status->label()); ?></span></p>
            <?php if($installmentContext): ?>
                <p class="text-xs text-slate-600"><?php echo e($installmentContext['settles'] ? 'Melunasi Tagihan' : 'Cicilan Ke-'.$installmentContext['number']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Grid -->
    <div class="grid grid-cols-2 gap-6 mb-6 text-xs">
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">DITERIMA DARI PELANGGAN</p>
            <p class="font-bold text-sm text-slate-900"><?php echo e($payment->customer->full_name ?? '-'); ?></p>
            <p class="font-mono text-xs text-slate-700">CID: <?php echo e($payment->customer->cid ?? $payment->customer->customer_code ?? '-'); ?></p>
            <p class="text-slate-600 font-mono">No. HP: <?php echo e($payment->customer->primary_phone ?? $payment->customer->phone ?? '-'); ?></p>
            <p class="text-slate-600 mt-0.5">Alamat: <?php echo e($payment->customer->address ?? '-'); ?></p>
        </div>
        <div class="text-right space-y-1">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">DETAIL TRANSAKSI</p>
            <p><span class="text-slate-500">Tanggal Bayar:</span> <span class="font-semibold"><?php echo e(optional($payment->payment_date)->format('d/m/Y')); ?></span></p>
            <p><span class="text-slate-500">Metode Bayar:</span> <span class="font-semibold uppercase font-mono"><?php echo e(strtoupper($payment->payment_method)); ?></span></p>
            <p><span class="text-slate-500">Kolektor:</span> <span class="font-semibold"><?php echo e($payment->collector ? $payment->collector->name : 'Langsung (Kasir POP)'); ?></span></p>
            <p><span class="text-slate-500">No. Tagihan:</span> <span class="font-mono font-semibold"><?php echo e($payment->invoice->invoice_number ?? '-'); ?></span></p>
            <p><span class="text-slate-500">Periode Tagihan:</span> <span class="font-mono font-semibold"><?php echo e($payment->invoice->billing_period ?? '-'); ?></span></p>
            <p><span class="text-slate-500">POP / Cabang:</span> <span class="font-semibold"><?php echo e($payment->pop->name ?? '-'); ?></span></p>
        </div>
    </div>

    <!-- Table Rincian Transaksi -->
    <table class="w-full text-left border-collapse text-xs mb-6">
        <thead>
            <tr class="border-y border-slate-300 bg-slate-100 text-slate-700 uppercase font-semibold text-[10px]">
                <th class="py-2.5 px-3">Deskripsi Pembayaran</th>
                <th class="py-2.5 px-3 text-center">Metode</th>
                <th class="py-2.5 px-3 text-right">Nominal Diterima (Rp)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            <tr>
                <td class="py-3 px-3">
                    <p class="font-bold text-slate-900">Pembayaran Internet <?php echo e($payment->invoice->internetPackage->name ?? 'Layanan ISP'); ?></p>
                    <p class="text-[11px] text-slate-500">Invoice: <?php echo e($payment->invoice->invoice_number ?? '-'); ?> &bull; Periode <?php echo e($payment->invoice->billing_period ?? '-'); ?></p>
                </td>
                <td class="py-3 px-3 text-center font-mono uppercase"><?php echo e($payment->payment_method); ?></td>
                <td class="py-3 px-3 text-right font-mono font-bold">Rp <?php echo e(number_format((float) $payment->amount, 0, ',', '.')); ?></td>
            </tr>
            <?php if((float) $payment->overpay_amount > 0): ?>
            <tr>
                <td class="py-3 px-3" colspan="2">
                    <p class="text-slate-600">Lebih Bayar (catatan, di luar pembayaran tagihan)</p>
                </td>
                <td class="py-3 px-3 text-right font-mono font-bold text-sky-700">Rp <?php echo e(number_format((float) $payment->overpay_amount, 0, ',', '.')); ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Calculation Breakdown & Signatures -->
    <div class="flex justify-between items-start gap-6 text-xs">
        <div class="space-y-2 max-w-xs">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">KETERANGAN & CATATAN</p>
            <p class="text-[11px] text-slate-600">Catatan: <?php echo e($payment->note ?: 'Tidak ada catatan.'); ?>. Struk pembayaran ini diterbitkan secara resmi oleh WHUSNET Operasional.</p>
            <p class="text-[10px] text-slate-400 italic mt-4">Struk sah tanpa tanda tangan &bull; Dicetak <?php echo e(now()->format('d/m/Y H:i')); ?></p>
        </div>

        <div class="w-64 space-y-1.5 text-xs">
            <?php if($payment->invoice): ?>
            <div class="flex justify-between text-slate-600">
                <span>Total Tagihan Invoice</span>
                <span class="font-mono font-semibold">Rp <?php echo e(number_format((float) $payment->invoice->total_amount, 0, ',', '.')); ?></span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between text-emerald-600 font-bold text-sm pt-1 border-t border-slate-300">
                <span>JUMLAH DIBAYAR</span>
                <span class="font-mono">Rp <?php echo e(number_format((float) $payment->amount, 0, ',', '.')); ?></span>
            </div>
            <?php if($payment->invoice): ?>
            <div class="flex justify-between text-slate-600">
                <span>Sisa Tagihan</span>
                <span class="font-mono font-semibold <?php echo e((float)$payment->invoice->remaining_amount > 0 ? 'text-red-600' : 'text-emerald-600'); ?>">
                    Rp <?php echo e(number_format((float) $payment->invoice->remaining_amount, 0, ',', '.')); ?>

                </span>
            </div>
            <?php endif; ?>
            <div class="pt-3 text-right">
                <span class="text-[10px] text-slate-500 block">Diterima oleh:</span>
                <span class="font-semibold text-slate-900 block"><?php echo e($payment->receiver->name ?? '-'); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="space-y-5 screen-only">
    <?php echo $__env->make('payments.partials.riwayat-banner', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <!-- LAYER 1: NAKED PAGE HEADER (§1.5 & §1.7 — STRICTLY NO CARD WRAPPER) -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        
        <!-- Left Title Block -->
        <div class="space-y-1">
            <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                <a href="<?php echo e(route('payments.index')); ?>" class="hover:text-slate-900 dark:hover:text-slate-200 transition-colors">Pembayaran</a>
                <svg class="h-3 w-3 text-slate-400 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100"><?php echo e($payment->payment_number); ?></span>
            </nav>

            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="<?php echo e(route('payments.index')); ?>" class="p-1.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 rounded-lg hover:bg-slate-200/60 dark:hover:bg-slate-800 transition-colors" title="Kembali">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Detail Pembayaran</h1>
                
                <!-- Payment Technical ID Badge -->
                <span class="font-mono text-xs px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 font-semibold inline-flex items-center gap-1.5">
                    <?php echo e($payment->payment_number); ?>

                    <button onclick="copyToClipboard('<?php echo e($payment->payment_number); ?>', 'No. Pembayaran')" title="Salin No. Pembayaran" class="text-slate-400 hover:text-sky-600 transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </span>

                <!-- Status Badge -->
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border <?php echo e($badgeClass); ?>">
                    <span class="w-2 h-2 rounded-full bg-current"></span>
                    <?php echo e($payment->payment_status->label()); ?>

                </span>

                <?php if($installmentContext): ?>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border <?php echo e($installmentContext['settles'] ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800'); ?>">
                        <?php echo e($installmentContext['settles'] ? 'Melunasi Tagihan' : 'Cicilan Ke-'.$installmentContext['number']); ?>

                    </span>
                <?php endif; ?>

                <?php if((float) $payment->overpay_amount > 0): ?>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800" title="Uang lebih yang diserahkan pelanggan — catatan saja, tidak menambah pembayaran tagihan">
                        Lebih Bayar Rp <?php echo e(number_format((float) $payment->overpay_amount, 0, ',', '.')); ?>

                    </span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Tercatat pada <?php echo e(optional($payment->payment_date)->format('d/m/Y')); ?> &bull; Diterima oleh <?php echo e($payment->receiver->name ?? 'System'); ?> &bull; POP <?php echo e($payment->pop->name ?? '-'); ?></p>
        </div>

        <!-- Right Action Toolbar (Naked Buttons) -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap no-print">
            <?php if($payment->invoice): ?>
                <a href="<?php echo e(route('invoices.show', $payment->invoice_id)); ?>" class="inline-flex items-center justify-center gap-2 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Detail Tagihan</span>
                </a>
            <?php endif; ?>

            <!-- Print Options Dropdown -->
            <div class="relative flex-1 sm:flex-none">
                <button onclick="togglePrintDropdown(event)" id="printDropdownBtn" class="w-full inline-flex items-center justify-center gap-2 px-3.5 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak</span>
                    <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div id="printDropdownMenu" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg shadow-xl py-1 z-40 text-xs">
                    <button type="button" onclick="window.print(); closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left">
                        <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100 leading-tight">Cetak Kwitansi A4 (PDF)</p>
                            <p class="text-[10px] text-slate-400">Format kuitansi pembayaran resmi</p>
                        </div>
                    </button>
                    <div class="border-t border-slate-100 dark:border-slate-800"></div>
                    <a href="<?php echo e(route('payments.receipt', $payment->id)); ?>" target="_blank" onclick="closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left">
                        <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100 leading-tight">Cetak Struk Thermal (80mm)</p>
                            <p class="text-[10px] text-slate-400">Struk bukti bayar kasir POP</p>
                        </div>
                    </a>
                </div>
            </div>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payments.reject')): ?>
                <?php if($payment->payment_status->value === 'valid'): ?>
                    <button type="button" onclick="openRejectDialog()" class="inline-flex items-center justify-center gap-2 px-3.5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        <span>Tolak Pembayaran</span>
                    </button>
                <?php endif; ?>
            <?php endif; ?>

            <a href="<?php echo e(route('payments.index')); ?>" class="inline-flex items-center justify-center gap-2 px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-sm transition-colors">
                <span>Kembali</span>
            </a>
        </div>
    </div>

    <?php if($payment->payment_status->value === 'ditolak'): ?>
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-lg p-4 text-xs text-red-700 dark:text-red-400">
            <p class="font-semibold">Pembayaran ini sudah ditolak/dibatalkan.</p>
            <?php if($payment->reject_reason): ?>
                <p class="mt-1">Alasan: <?php echo e($payment->reject_reason); ?></p>
            <?php endif; ?>
            <p class="mt-1 text-red-600/80 dark:text-red-400/70">
                Oleh <?php echo e($payment->rejecter->name ?? '-'); ?> pada <?php echo e(optional($payment->rejected_at)->format('d/m/Y H:i')); ?>

            </p>
        </div>
    <?php endif; ?>

    <!-- LAYER 3: SINGLE PRIMARY DETAIL PANEL (CARD BUDGET = 1 STRICT COMPLIANCE) -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden shadow-sm">
        
        <!-- SECTION 1: FINANCIAL METRIC STRIP (Flat Row with Vertical Dividers) -->
        <div class="p-5 sm:p-6 grid grid-cols-2 md:grid-cols-4 gap-6 bg-slate-50/50 dark:bg-slate-800/20 border-b border-slate-200 dark:border-slate-800">
            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">NOMINAL BAYAR</span>
                <span class="font-mono text-2xl font-bold text-emerald-600 dark:text-emerald-400 block mt-0.5">Rp <?php echo e(number_format((float) $payment->amount, 0, ',', '.')); ?></span>
                <?php if((float) $payment->overpay_amount > 0): ?>
                    <span class="text-[11px] font-semibold text-sky-600 dark:text-sky-400 block">+ Rp <?php echo e(number_format((float) $payment->overpay_amount, 0, ',', '.')); ?> lebih bayar</span>
                <?php else: ?>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">Pembayaran Terverifikasi</span>
                <?php endif; ?>
            </div>

            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">TANGGAL & METODE</span>
                <span class="font-bold text-slate-800 dark:text-slate-200 block text-base mt-1"><?php echo e(optional($payment->payment_date)->format('d/m/Y')); ?></span>
                <span class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 uppercase">
                    <?php echo e(strtoupper($payment->payment_method)); ?>

                </span>
            </div>

            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">DITERIMA OLEH</span>
                <span class="font-semibold text-slate-800 dark:text-slate-200 block text-base mt-1 truncate"><?php echo e($payment->receiver->name ?? '-'); ?></span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate block">POP: <?php echo e($payment->pop->name ?? '-'); ?></span>
            </div>

            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">KOLEKTOR LAPANGAN</span>
                <span class="font-semibold text-slate-800 dark:text-slate-200 block text-base mt-1 truncate">
                    <?php echo e($payment->collector ? $payment->collector->name : 'Langsung (Kasir POP)'); ?>

                </span>
                <span class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                    <?php echo e($payment->collector ? 'Kolektor Lapangan' : 'Bukan via kolektor'); ?>

                </span>
            </div>
        </div>

        <!-- SECTION 2: UNIFIED CUSTOMER & INVOICE STRIP (Flat Row — No Cards inside) -->
        <div class="p-5 sm:p-6 grid grid-cols-1 md:grid-cols-2 gap-6 border-b border-slate-200 dark:border-slate-800">
            
            <!-- Customer Information Block -->
            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">INFORMASI PELANGGAN</span>
                </div>

                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-400 font-bold text-sm flex items-center justify-center shrink-0">
                        <?php echo e(strtoupper(substr($payment->customer->full_name ?? 'P', 0, 2))); ?>

                    </div>
                    <div class="space-y-1 text-xs">
                        <div class="flex items-center gap-2">
                            <a href="<?php echo e(route('customers.show', $payment->customer_id)); ?>" class="font-bold text-slate-900 dark:text-slate-100 text-sm hover:text-sky-600 dark:hover:text-sky-400 transition-colors">
                                <?php echo e($payment->customer->full_name ?? '-'); ?>

                            </a>
                        </div>
                        <div class="flex items-center gap-3 text-slate-500 dark:text-slate-400 flex-wrap">
                            <span class="font-mono font-medium flex items-center gap-1">
                                CID: <?php echo e($payment->customer->cid ?? $payment->customer->customer_code ?? '-'); ?>

                                <?php if($payment->customer->cid || $payment->customer->customer_code): ?>
                                    <button onclick="copyToClipboard('<?php echo e($payment->customer->cid ?? $payment->customer->customer_code); ?>', 'CID')" class="text-slate-400 hover:text-sky-600"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button>
                                <?php endif; ?>
                            </span>
                            <span>&bull;</span>
                            <span class="flex items-center gap-1">
                                HP: <span class="font-mono"><?php echo e($payment->customer->primary_phone ?? $payment->customer->phone ?? '-'); ?></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Context Block -->
            <div class="space-y-3 md:border-l md:border-slate-200 md:dark:border-slate-800 md:pl-6">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">TAGIHAN / INVOICE TERKAIT</span>
                </div>

                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-950/80 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <div class="space-y-0.5 text-xs">
                        <?php if($payment->invoice): ?>
                            <a href="<?php echo e(route('invoices.show', $payment->invoice_id)); ?>" class="font-mono font-bold text-sky-600 dark:text-sky-400 hover:underline text-sm block">
                                <?php echo e($payment->invoice->invoice_number); ?>

                            </a>
                            <p class="text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                                Periode: <?php echo e($payment->invoice->billing_period ?? '-'); ?> &bull; Total Invoice: Rp <?php echo e(number_format((float) ($payment->invoice->total_amount ?? 0), 0, ',', '.')); ?>

                            </p>
                            <p class="text-slate-600 dark:text-slate-400 text-[11px] font-semibold">
                                Sisa Tagihan Saat Ini: Rp <?php echo e(number_format((float) ($payment->invoice->remaining_amount ?? 0), 0, ',', '.')); ?>

                            </p>
                        <?php else: ?>
                            <p class="text-slate-400 italic">Tidak terhubung ke invoice tertentu</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- SECTION 3: TAB NAVIGATION BAR (Internal Border Bottom) -->
        <div class="border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 flex items-center justify-between gap-4 overflow-x-auto custom-scrollbar no-print bg-slate-50/30 dark:bg-slate-800/10">
            <div class="flex items-center gap-2 sm:gap-6 text-xs shrink-0">
                <button onclick="switchTab('info')" id="tab-info" class="py-3.5 border-b-2 border-sky-600 text-sky-600 dark:border-sky-400 dark:text-sky-400 font-semibold flex items-center gap-2 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Informasi & Catatan</span>
                </button>
                <button onclick="switchTab('proof')" id="tab-proof" class="py-3.5 border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium flex items-center gap-2 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Bukti Pembayaran</span>
                </button>
                <?php if(auth()->user()->hasPermission('audit_logs.view')): ?>
                <button onclick="switchTab('audit')" id="tab-audit" class="py-3.5 border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium flex items-center gap-2 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Timeline & Audit Log</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- SECTION 4: TAB CONTENT PANES -->

        <!-- TAB PANE 1: Informasi & Catatan -->
        <div id="pane-info" class="p-5 sm:p-6 space-y-6">
            <div class="space-y-3">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Catatan Pembayaran</h3>
                <div class="p-4 bg-slate-50/80 dark:bg-slate-800/40 rounded-lg border border-slate-200 dark:border-slate-800 text-xs text-slate-700 dark:text-slate-300">
                    <?php echo e($payment->note ?: 'Tidak ada catatan khusus untuk transaksi ini.'); ?>

                </div>
            </div>

            <?php if($payment->old_payment_id || $payment->old_transaction_id || $payment->old_request_id): ?>
            <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Audit Visibilitas Data Migrasi Legacy</h3>
                <div class="p-4 bg-slate-50/80 dark:bg-slate-800/40 rounded-lg border border-slate-200 dark:border-slate-800 space-y-2 text-xs max-w-lg">
                    <?php if($payment->old_payment_id): ?>
                    <div class="flex justify-between items-center pb-2 border-b border-slate-200/60 dark:border-slate-800/60">
                        <span class="text-slate-500 dark:text-slate-400">ID Bayar Lama:</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($payment->old_payment_id); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if($payment->old_transaction_id): ?>
                    <div class="flex justify-between items-center pb-2 border-b border-slate-200/60 dark:border-slate-800/60">
                        <span class="text-slate-500 dark:text-slate-400">ID Transaksi Lama:</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($payment->old_transaction_id); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if($payment->old_request_id): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 dark:text-slate-400">ID Permintaan Lama:</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($payment->old_request_id); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB PANE 2: Bukti Pembayaran -->
        <div id="pane-proof" class="hidden p-5 sm:p-6 space-y-4">
            <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Bukti Pembayaran</h3>
            
            <?php if($payment->proof_file): ?>
                <div class="p-6 bg-slate-50/80 dark:bg-slate-800/40 rounded-lg border border-dashed border-slate-300 dark:border-slate-700 text-center space-y-3 max-w-md">
                    <svg class="h-10 w-10 text-sky-600 dark:text-sky-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <div>
                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Lampiran Ter-upload</p>
                        <p class="text-[10px] text-slate-400 font-mono mt-0.5 truncate"><?php echo e($payment->proof_file); ?></p>
                    </div>
                    <a href="<?php echo e(asset('storage/' . $payment->proof_file)); ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors">
                        Lihat Bukti Pembayaran
                    </a>
                </div>
            <?php else: ?>
                <div class="p-6 bg-slate-50/80 dark:bg-slate-800/40 rounded-lg border border-slate-200 dark:border-slate-800 text-center text-xs text-slate-500 dark:text-slate-400">
                    Bukti pembayaran belum diupload.
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB PANE 3: Timeline & Audit Log -->
        <?php if(auth()->user()->hasPermission('audit_logs.view')): ?>
        <div id="pane-audit" class="hidden p-5 sm:p-6 space-y-6">
            <div class="space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Riwayat Audit Pembayaran</h3>
                
                <?php if($payment->relationLoaded('auditLogs') && $payment->auditLogs->count() > 0): ?>
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px]">
                                    <th class="px-4 py-3 font-semibold">Waktu</th>
                                    <th class="px-4 py-3 font-semibold">Aksi</th>
                                    <th class="px-4 py-3 font-semibold">User</th>
                                    <th class="px-4 py-3 font-semibold">Data Sebelum</th>
                                    <th class="px-4 py-3 font-semibold">Data Sesudah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                                <?php $__currentLoopData = $payment->auditLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $auditLog): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors">
                                        <td class="px-4 py-3 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                            <?php echo e(optional($auditLog->created_at)->format('d/m/Y H:i')); ?>

                                        </td>
                                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">
                                            <?php echo e(ucwords(str_replace('_', ' ', $auditLog->action))); ?>

                                        </td>
                                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200 whitespace-nowrap">
                                            <?php echo e($auditLog->user->name ?? '-'); ?>

                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <pre class="max-w-xs whitespace-pre-wrap break-words text-[10px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 rounded p-2"><?php echo e($auditLog->old_values ? json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '-'); ?></pre>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <pre class="max-w-xs whitespace-pre-wrap break-words text-[10px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 rounded p-2"><?php echo e($auditLog->new_values ? json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '-'); ?></pre>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Belum ada riwayat audit pembayaran.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payments.reject')): ?>
    <?php if($payment->payment_status->value === 'valid'): ?>
        
        <form id="rejectForm" method="POST" action="<?php echo e(route('payments.reject', $payment->id)); ?>" class="hidden no-print">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="reject_reason" id="reject_reason_hidden" value="<?php echo e(old('reject_reason')); ?>">
        </form>
    <?php endif; ?>
<?php endif; ?>

<script>
    function openRejectDialog() {
        window.Dialog.show({
            title: 'Tolak Pembayaran <?php echo e($payment->payment_number); ?>',
            icon: 'error',
            contentHtml: `
                <p class="mb-3 text-xs text-text-secondary">Tagihan akan dihitung ulang setelah pembayaran ini ditolak. Tindakan ini butuh alasan.</p>
                <label for="reject-reason-input" class="block text-xs font-semibold text-text-secondary mb-1.5">Alasan Penolakan *</label>
                <textarea id="reject-reason-input" rows="4" maxlength="1000" class="w-full text-sm rounded-lg border border-border bg-background p-2.5 text-text-main focus:outline-none focus:ring-2 focus:ring-red-500/30 focus:border-red-500" placeholder="Contoh: bukti transfer tidak valid / duplikat input."><?php echo e(old('reject_reason')); ?></textarea>
                <p id="reject-reason-error" class="hidden text-xs text-red-600 mt-1.5">Alasan wajib diisi.</p>
            `,
            buttons: [
                { text: 'Batal', type: 'secondary', onClick: () => window.Dialog.close() },
                {
                    text: 'Tolak Pembayaran', type: 'danger', onClick: (e) => {
                        const input = document.getElementById('reject-reason-input');
                        const reason = (input?.value || '').trim();

                        if (reason === '') {
                            document.getElementById('reject-reason-error')?.classList.remove('hidden');
                            const btn = e.currentTarget;
                            btn.disabled = false;
                            btn.classList.remove('opacity-50', 'cursor-not-allowed');
                            input?.focus();
                            return;
                        }

                        document.getElementById('reject_reason_hidden').value = reason;
                        document.getElementById('rejectForm').submit();
                    },
                },
            ],
        });

        setTimeout(() => document.getElementById('reject-reason-input')?.focus(), 350);
    }

    <?php if($errors->has('reject_reason')): ?>
        document.addEventListener('DOMContentLoaded', openRejectDialog);
    <?php endif; ?>

    function togglePrintDropdown(e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('printDropdownMenu');
        if (menu) menu.classList.toggle('hidden');
    }

    function closePrintDropdown() {
        const menu = document.getElementById('printDropdownMenu');
        if (menu) menu.classList.add('hidden');
    }

    document.addEventListener('click', function(e) {
        const btn = document.getElementById('printDropdownBtn');
        const menu = document.getElementById('printDropdownMenu');
        if (menu && !menu.classList.contains('hidden') && !menu.contains(e.target) && !btn?.contains(e.target)) {
            closePrintDropdown();
        }
    });

    function switchTab(tabKey) {
        const tabs = ['info', 'proof', 'audit'];
        tabs.forEach(key => {
            const btn = document.getElementById(`tab-${key}`);
            const pane = document.getElementById(`pane-${key}`);
            if (!btn || !pane) return;
            
            if (key === tabKey) {
                btn.className = 'py-3.5 border-b-2 border-sky-600 text-sky-600 dark:border-sky-400 dark:text-sky-400 font-semibold flex items-center gap-2 transition-all';
                pane.classList.remove('hidden');
            } else {
                btn.className = 'py-3.5 border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium flex items-center gap-2 transition-all';
                pane.classList.add('hidden');
            }
        });
    }

    function copyToClipboard(text, label) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            const input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }
        window.Toast.success('Disalin', `${label} (${text}) berhasil disalin`);
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/payments/show.blade.php ENDPATH**/ ?>