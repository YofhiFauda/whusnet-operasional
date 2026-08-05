<?php $__env->startSection('title', 'Detail Tagihan ' . $invoice->invoice_number . ' - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Detail Tagihan'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $badgeClass = match($invoice->invoice_status->value) {
        'lunas' => 'bg-emerald-50 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        'sebagian' => 'bg-amber-50 dark:bg-amber-950/80 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        'batal' => 'bg-red-50 dark:bg-red-950/80 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800',
        default => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
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

<!-- PRINT ONLY A4 FAKTUR INVOICE / KWITANSI SHEET -->
<div class="print-only p-8 bg-white text-slate-900 font-sans text-xs leading-normal">
    <!-- Header -->
    <div class="flex justify-between items-start border-b pb-4 mb-4 border-slate-300">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">WHUSNET</h1>
            <p class="text-xs text-slate-600 font-medium"><?php echo e($invoice->pop->name ?? 'Kantor Pusat'); ?> &bull; ISP Service</p>
            <p class="text-[10px] text-slate-500 mt-0.5">Website Billing ISP Internal</p>
        </div>
        <div class="text-right">
            <h2 class="text-base font-bold text-slate-900 uppercase tracking-wide">FAKTUR INVOICE / KWITANSI</h2>
            <p class="font-mono text-xs font-semibold text-slate-700">No: <?php echo e($invoice->invoice_number); ?></p>
            <p class="text-xs text-slate-600 mt-1">Status: <span class="font-bold uppercase text-emerald-700">● <?php echo e($invoice->invoice_status->label()); ?></span></p>
        </div>
    </div>

    <!-- Info Grid -->
    <div class="grid grid-cols-2 gap-6 mb-6 text-xs">
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">DITERBITKAN KEPADA</p>
            <p class="font-bold text-sm text-slate-900"><?php echo e($invoice->customer->full_name ?? '-'); ?></p>
            <p class="font-mono text-xs text-slate-700">CID: <?php echo e($invoice->customer->cid ?? $invoice->customer->customer_code ?? '-'); ?></p>
            <p class="text-slate-600 font-mono">No. HP: <?php echo e($invoice->customer->primary_phone ?? $invoice->customer->phone ?? '-'); ?></p>
            <p class="text-slate-600 mt-0.5">Alamat: <?php echo e($invoice->customer->address ?? '-'); ?></p>
        </div>
        <div class="text-right space-y-1">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">DETAIL TAGIHAN</p>
            <p><span class="text-slate-500">Tanggal Terbit:</span> <span class="font-semibold"><?php echo e(optional($invoice->issue_date)->format('d/m/Y')); ?></span></p>
            <p><span class="text-slate-500">Jatuh Tempo:</span> <span class="font-semibold"><?php echo e(optional($invoice->due_date)->format('d/m/Y')); ?></span></p>
            <p><span class="text-slate-500">Periode Tagihan:</span> <span class="font-mono font-semibold"><?php echo e($invoice->billing_period); ?></span></p>
            <p><span class="text-slate-500">POP / Cabang:</span> <span class="font-semibold"><?php echo e($invoice->pop->name ?? '-'); ?></span></p>
        </div>
    </div>

    <!-- Table Rincian Biaya -->
    <table class="w-full text-left border-collapse text-xs mb-6">
        <thead>
            <tr class="border-y border-slate-300 bg-slate-100 text-slate-700 uppercase font-semibold text-[10px]">
                <th class="py-2.5 px-3">Deskripsi Layanan</th>
                <th class="py-2.5 px-3 text-center">Periode</th>
                <th class="py-2.5 px-3 text-right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            <tr>
                <td class="py-3 px-3">
                    <p class="font-bold text-slate-900"><?php echo e($invoice->customerService->package_name_snapshot ?? $invoice->internetPackage->name ?? 'Paket Internet'); ?></p>
                    <p class="text-[11px] text-slate-500">Langganan Internet Dedicated — DL/UL: <?php echo e($invoice->customerService->download_speed_snapshot ?? '-'); ?> / <?php echo e($invoice->customerService->upload_speed_snapshot ?? '-'); ?></p>
                </td>
                <td class="py-3 px-3 text-center font-mono"><?php echo e($invoice->billing_period); ?></td>
                <td class="py-3 px-3 text-right font-mono font-bold">Rp <?php echo e(number_format((float) $invoice->subtotal, 0, ',', '.')); ?></td>
            </tr>
            <?php if((float)$invoice->prorate_amount > 0): ?>
            <tr>
                <td class="py-2 px-3 text-slate-700">Tagihan Prorate Layanan</td>
                <td class="py-2 px-3 text-center font-mono">-</td>
                <td class="py-2 px-3 text-right font-mono font-semibold">Rp <?php echo e(number_format((float) $invoice->prorate_amount, 0, ',', '.')); ?></td>
            </tr>
            <?php endif; ?>
            <?php if((float)$invoice->extra_cable_fee > 0): ?>
            <tr>
                <td class="py-2 px-3 text-slate-700">Biaya Kabel Tambahan</td>
                <td class="py-2 px-3 text-center font-mono">-</td>
                <td class="py-2 px-3 text-right font-mono font-semibold">Rp <?php echo e(number_format((float) $invoice->extra_cable_fee, 0, ',', '.')); ?></td>
            </tr>
            <?php endif; ?>
            <?php if((float)$invoice->other_fee > 0): ?>
            <tr>
                <td class="py-2 px-3 text-slate-700">Biaya Lain-lain</td>
                <td class="py-2 px-3 text-center font-mono">-</td>
                <td class="py-2 px-3 text-right font-mono font-semibold">Rp <?php echo e(number_format((float) $invoice->other_fee, 0, ',', '.')); ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Calculation Breakdown & Signatures -->
    <div class="flex justify-between items-start gap-6 text-xs">
        <div class="space-y-2 max-w-xs">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">KETERANGAN & CATATAN</p>
            <p class="text-[11px] text-slate-600">Dokumen faktur/kwitansi ini diterbitkan secara resmi oleh sistem WHUSNET Operasional. Pembayaran melalui Kasir POP / Transfer Bank resmi.</p>
            <p class="text-[10px] text-slate-400 italic mt-4">Struk / Kwitansi sah tanpa tanda tangan &bull; Dicetak <?php echo e(now()->format('d/m/Y H:i')); ?></p>
        </div>

        <div class="w-64 space-y-1.5 text-xs">
            <div class="flex justify-between text-slate-600">
                <span>Harga Paket (Subtotal)</span>
                <span class="font-mono font-semibold">Rp <?php echo e(number_format((float)$invoice->subtotal, 0, ',', '.')); ?></span>
            </div>
            <?php if((float)$invoice->discount > 0): ?>
            <div class="flex justify-between text-emerald-600 font-semibold">
                <span>Diskon</span>
                <span class="font-mono">- Rp <?php echo e(number_format((float)$invoice->discount, 0, ',', '.')); ?></span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between text-slate-600">
                <span>PPN</span>
                <span class="font-mono"><?php echo e((float)$invoice->ppn > 0 ? 'Rp ' . number_format(((float)$invoice->subtotal - (float)$invoice->discount) * ((float)$invoice->ppn / 100), 0, ',', '.') : 'Tidak dikenakan'); ?></span>
            </div>
            <div class="flex justify-between pt-2 border-t border-slate-300 font-bold text-slate-900 text-sm">
                <span>Total Tagihan</span>
                <span class="font-mono">Rp <?php echo e(number_format((float)$invoice->total_amount, 0, ',', '.')); ?></span>
            </div>
            <div class="flex justify-between text-emerald-600 font-semibold">
                <span>Sudah Terbayar</span>
                <span class="font-mono">Rp <?php echo e(number_format((float)$invoice->paid_amount, 0, ',', '.')); ?></span>
            </div>
            <div class="flex justify-between pt-1 border-t border-slate-200 font-bold text-slate-900">
                <span>Sisa Tagihan</span>
                <span class="font-mono <?php echo e((float)$invoice->remaining_amount > 0 ? 'text-red-600' : 'text-emerald-600'); ?>">Rp <?php echo e(number_format((float)$invoice->remaining_amount, 0, ',', '.')); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="space-y-5 screen-only">
    <!-- LAYER 1: NAKED PAGE HEADER (§1.5 & §1.7 — STRICTLY NO CARD WRAPPER) -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        
        <!-- Left Title Block -->
        <div class="space-y-1">
            <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                <a href="<?php echo e(route('invoices.index')); ?>" class="hover:text-slate-900 dark:hover:text-slate-200 transition-colors">Daftar Tagihan</a>
                <svg class="h-3 w-3 text-slate-400 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100"><?php echo e($invoice->invoice_number); ?></span>
            </nav>

            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="<?php echo e(route('invoices.index')); ?>" class="p-1.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 rounded-lg hover:bg-slate-200/60 dark:hover:bg-slate-800 transition-colors" title="Kembali">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Detail Tagihan</h1>
                
                <!-- Invoice Technical ID Badge -->
                <span class="font-mono text-xs px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 font-semibold inline-flex items-center gap-1.5">
                    <?php echo e($invoice->invoice_number); ?>

                    <button onclick="copyToClipboard('<?php echo e($invoice->invoice_number); ?>', 'No. Invoice')" title="Salin No. Invoice" class="text-slate-400 hover:text-sky-600 transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </span>

                <!-- Status Badge -->
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border <?php echo e($badgeClass); ?>">
                    <span class="w-2 h-2 rounded-full bg-current"></span>
                    <?php echo e($invoice->invoice_status->label()); ?>

                </span>

                <?php if($invoice->invoice_type): ?>
                <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800">
                    <?php echo e($invoice->invoice_type->label()); ?>

                </span>
                <?php endif; ?>

                <?php
                    // Total lebih bayar dari SELURUH payment valid invoice ini —
                    // relevan ditampilkan di header terutama saat tagihan sudah
                    // Lunas, biar jelas kalau pelunasannya menyisakan kelebihan.
                    $invoiceTotalOverpay = $invoice->payments
                        ->filter(fn ($p) => $p->payment_status === \App\Enums\PaymentStatus::VALID)
                        ->sum(fn ($p) => (float) $p->overpay_amount);
                ?>
                <?php if($invoiceTotalOverpay > 0): ?>
                    <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800" title="Total uang lebih yang diserahkan pelanggan pada invoice ini — catatan saja, tidak menjadi saldo">
                        Lebih Bayar Rp <?php echo e(number_format($invoiceTotalOverpay, 0, ',', '.')); ?>

                    </span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Periode <?php echo e($invoice->billing_period); ?> &bull; Diterbitkan <?php echo e(optional($invoice->issue_date)->format('d/m/Y')); ?> oleh <?php echo e($invoice->creator->name ?? 'System'); ?></p>
        </div>

        <!-- Right Action Toolbar (Naked Buttons) -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap no-print">
            <?php if(auth()->user()->hasPermission('create_payments') && !in_array($invoice->invoice_status->value, ['lunas', 'batal'], true)): ?>
                <a href="<?php echo e(route('invoices.payments.create', $invoice->id)); ?>" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span><?php echo e($invoice->invoice_status->value === 'sebagian' ? 'Bayar Cicil' : 'Input Pembayaran'); ?></span>
                </a>
            <?php endif; ?>

            <a href="<?php echo e(route('customers.show', $invoice->customer_id)); ?>" class="inline-flex items-center justify-center gap-2 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>Detail Pelanggan</span>
            </a>

            <!-- Print Options Dropdown -->
            <div class="relative flex-1 sm:flex-none" x-data="{ open: false }">
                <button onclick="togglePrintDropdown(event)" id="printDropdownBtn" class="w-full inline-flex items-center justify-center gap-2 px-3.5 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak</span>
                    <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div id="printDropdownMenu" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg shadow-xl py-1 z-40 text-xs">
                    <button type="button" onclick="window.print(); closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left">
                        <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100 leading-tight">Cetak Invoice A4 (PDF)</p>
                            <p class="text-[10px] text-slate-400">Format dokumen faktur resmi</p>
                        </div>
                    </button>
                    <div class="border-t border-slate-100 dark:border-slate-800"></div>
                    <?php if($invoice->payments->count() > 0): ?>
                        <a href="<?php echo e(route('payments.receipt', $invoice->payments->first()->id)); ?>" target="_blank" onclick="closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left">
                            <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-slate-100 leading-tight">Cetak Struk Thermal (80mm)</p>
                                <p class="text-[10px] text-slate-400">Struk bukti bayar kasir POP</p>
                            </div>
                        </a>
                    <?php else: ?>
                        <button type="button" onclick="window.Toast.warning('Belum Ada Struk', 'Belum ada riwayat pembayaran terdaftar untuk mencetak struk kasir.'); closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-400 dark:text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left opacity-75">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <div>
                                <p class="font-semibold leading-tight">Cetak Struk Thermal (80mm)</p>
                                <p class="text-[10px] text-slate-400">Perlu pembayaran terdaftar</p>
                            </div>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- LAYER 3: SINGLE PRIMARY DETAIL PANEL (CARD BUDGET = 1 STRICT COMPLIANCE) -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden shadow-sm">
        
        <!-- SECTION 1: FINANCIAL METRIC STRIP (Flat Row with Vertical Dividers) -->
        <div class="p-5 sm:p-6 grid grid-cols-2 md:grid-cols-4 gap-6 bg-slate-50/50 dark:bg-slate-800/20 border-b border-slate-200 dark:border-slate-800">
            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">TOTAL TAGIHAN</span>
                <span class="font-mono text-2xl font-bold text-slate-900 dark:text-slate-100 block mt-0.5">Rp <?php echo e(number_format((float) $invoice->total_amount, 0, ',', '.')); ?></span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400">Periode: <?php echo e($invoice->billing_period); ?></span>
            </div>

            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">SISA PEMBAYARAN</span>
                <span class="font-mono text-2xl font-bold <?php echo e((float)$invoice->remaining_amount > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'); ?> block mt-0.5">
                    Rp <?php echo e(number_format((float) $invoice->remaining_amount, 0, ',', '.')); ?>

                </span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400">Sudah Dibayar: Rp <?php echo e(number_format((float) $invoice->paid_amount, 0, ',', '.')); ?></span>
            </div>

            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">JATUH TEMPO</span>
                <span class="font-bold text-slate-800 dark:text-slate-200 block text-base mt-1"><?php echo e(optional($invoice->due_date)->format('d/m/Y') ?? '-'); ?></span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400">Terbit: <?php echo e(optional($invoice->issue_date)->format('d/m/Y') ?? '-'); ?></span>
            </div>

            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">POP / PEMBUAT</span>
                <span class="font-semibold text-slate-800 dark:text-slate-200 block text-base mt-1 truncate"><?php echo e($invoice->pop->name ?? '-'); ?></span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate block"><?php echo e($invoice->creator->name ?? 'System'); ?></span>
            </div>
        </div>

        <!-- SECTION 2: UNIFIED CUSTOMER & PACKAGE STRIP (Flat Row — No Cards inside) -->
        <div class="p-5 sm:p-6 grid grid-cols-1 md:grid-cols-2 gap-6 border-b border-slate-200 dark:border-slate-800">
            
            <!-- Customer Information Block -->
            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">INFORMASI PELANGGAN</span>
                </div>

                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-400 font-bold text-sm flex items-center justify-center shrink-0">
                        <?php echo e(strtoupper(substr($invoice->customer->full_name ?? 'P', 0, 2))); ?>

                    </div>
                    <div class="space-y-1 text-xs">
                        <div class="flex items-center gap-2">
                            <a href="<?php echo e(route('customers.show', $invoice->customer_id)); ?>" class="font-bold text-slate-900 dark:text-slate-100 text-sm hover:text-sky-600 dark:hover:text-sky-400 transition-colors">
                                <?php echo e($invoice->customer->full_name ?? '-'); ?>

                            </a>
                        </div>
                        <div class="flex items-center gap-3 text-slate-500 dark:text-slate-400 flex-wrap">
                            <span class="font-mono font-medium flex items-center gap-1">
                                CID: <?php echo e($invoice->customer->cid ?? $invoice->customer->customer_code ?? '-'); ?>

                                <?php if($invoice->customer->cid || $invoice->customer->customer_code): ?>
                                    <button onclick="copyToClipboard('<?php echo e($invoice->customer->cid ?? $invoice->customer->customer_code); ?>', 'CID')" class="text-slate-400 hover:text-sky-600"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button>
                                <?php endif; ?>
                            </span>
                            <span>&bull;</span>
                            <span class="flex items-center gap-1">
                                HP: <span class="font-mono"><?php echo e($invoice->customer->primary_phone ?? $invoice->customer->phone ?? '-'); ?></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Internet Package Block -->
            <div class="space-y-3 md:border-l md:border-slate-200 md:dark:border-slate-800 md:pl-6">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">PAKET INTERNET</span>
                </div>

                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-950/80 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071a10 10 0 0114.142 0M1.414 8.586a15 15 0 0121.172 0"/></svg>
                    </div>
                    <div class="space-y-0.5 text-xs">
                        <p class="font-bold text-slate-900 dark:text-slate-100 text-sm"><?php echo e($invoice->customerService->package_name_snapshot ?? $invoice->internetPackage->name ?? '-'); ?></p>
                        <p class="text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                            DL/UL: <?php echo e($invoice->customerService->download_speed_snapshot ?? '-'); ?> / <?php echo e($invoice->customerService->upload_speed_snapshot ?? '-'); ?>

                            &bull; Harga Paket: Rp <?php echo e(number_format((float) ($invoice->customerService->monthly_price ?? $invoice->subtotal), 0, ',', '.')); ?>

                        </p>
                    </div>
                </div>
            </div>

        </div>

        <!-- SECTION 3: TAB NAVIGATION BAR (Internal Border Bottom) -->
        <div class="border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 flex items-center justify-between gap-4 overflow-x-auto custom-scrollbar no-print bg-slate-50/30 dark:bg-slate-800/10">
            <div class="flex items-center gap-2 sm:gap-6 text-xs shrink-0">
                <button onclick="switchTab('items')" id="tab-items" class="py-3.5 border-b-2 border-sky-600 text-sky-600 dark:border-sky-400 dark:text-sky-400 font-semibold flex items-center gap-2 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Rincian Biaya</span>
                </button>
                <button onclick="switchTab('payments')" id="tab-payments" class="py-3.5 border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium flex items-center gap-2 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>Riwayat Pembayaran (<?php echo e($invoice->payments->count()); ?>)</span>
                </button>
                <?php if($invoice->old_invoice_id || $invoice->old_cost_id || $invoice->old_request_id): ?>
                <button onclick="switchTab('audit')" id="tab-audit" class="py-3.5 border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium flex items-center gap-2 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>Audit Migrasi Legacy</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- SECTION 4: TAB CONTENT PANES -->

        <!-- TAB PANE 1: Rincian Biaya -->
        <div id="pane-items" class="p-5 sm:p-6 space-y-6">
            <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Kalkulasi Tagihan</h3>
            
            <div class="space-y-3 text-xs max-w-xl">
                
                <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">Harga Paket (Subtotal)</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">Rp <?php echo e(number_format((float) $invoice->subtotal, 0, ',', '.')); ?></span>
                </div>

                <?php if((float)$invoice->discount > 0): ?>
                <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100 dark:border-slate-800 text-emerald-600 dark:text-emerald-400">
                    <span>Potongan Diskon</span>
                    <span class="font-mono font-semibold">- Rp <?php echo e(number_format((float) $invoice->discount, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php
                    $afterDiscount = max(0, (float)$invoice->subtotal - (float)$invoice->discount);
                    $ppnRate = (float)$invoice->ppn;
                    $ppnAmount = round($afterDiscount * ($ppnRate / 100), 2);
                ?>

                <?php if($ppnRate > 0): ?>
                <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">PPN (<?php echo e(number_format($ppnRate, 0)); ?>%)</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">Rp <?php echo e(number_format($ppnAmount, 0, ',', '.')); ?></span>
                </div>
                <?php else: ?>
                <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">PPN</span>
                    <span class="font-mono text-slate-400 dark:text-slate-500">Tidak dikenakan</span>
                </div>
                <?php endif; ?>

                
                <?php if((float)($invoice->prorate_amount ?? 0) > 0): ?>
                <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">Tagihan Prorate</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">Rp <?php echo e(number_format((float) $invoice->prorate_amount, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->extra_cable_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">Biaya Kabel Tambahan</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">Rp <?php echo e(number_format((float) $invoice->extra_cable_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->other_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">Biaya Lain-lain</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">Rp <?php echo e(number_format((float) $invoice->other_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->extra_installation_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">Jasa Instalasi Tambahan</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">Rp <?php echo e(number_format((float) $invoice->extra_installation_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->extra_pole_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">Tambahan Tiang</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">Rp <?php echo e(number_format((float) $invoice->extra_pole_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                
                <div class="flex justify-between gap-4 pt-3 border-t border-slate-200 dark:border-slate-800 text-sm font-bold">
                    <span class="text-slate-900 dark:text-slate-100">Total Tagihan</span>
                    <span class="font-mono text-base text-slate-900 dark:text-slate-100">Rp <?php echo e(number_format((float) $invoice->total_amount, 0, ',', '.')); ?></span>
                </div>
                <div class="flex justify-between gap-4 py-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                    <span>Sudah Terbayar</span>
                    <span class="font-mono">Rp <?php echo e(number_format((float) $invoice->paid_amount, 0, ',', '.')); ?></span>
                </div>
                <div class="flex justify-between gap-4 pt-2 border-t border-slate-200 dark:border-slate-800 text-sm font-bold">
                    <span class="text-slate-900 dark:text-slate-100">Sisa Tagihan</span>
                    <span class="font-mono text-base <?php echo e((float)$invoice->remaining_amount > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'); ?>">
                        Rp <?php echo e(number_format((float) $invoice->remaining_amount, 0, ',', '.')); ?>

                    </span>
                </div>
            </div>
        </div>

        <!-- TAB PANE 2: Riwayat Pembayaran -->
        <div id="pane-payments" class="hidden">
            <?php if($invoice->payments->count() > 0): ?>
                <?php
                    // Nomor cicilan dihitung dari pembayaran VALID saja, urut
                    // menaik (Cicilan Ke-1 = paling awal). Tabel ini ditampilkan
                    // menurun (terbaru di atas), jadi nomor TIDAK boleh diambil
                    // dari $loop->iteration. Pembayaran ditolak sengaja tak
                    // diberi nomor supaya urutan cicilan tetap rapat.
                    $validPayments = $invoice->payments
                        ->filter(fn ($p) => $p->payment_status === \App\Enums\PaymentStatus::VALID)
                        ->sortBy([['payment_date', 'asc'], ['id', 'asc']])
                        ->values();

                    $invoiceTotal = round((float) $invoice->total_amount, 2);
                    $installmentMeta = [];
                    $runningPaid = 0.0;

                    foreach ($validPayments as $index => $validPayment) {
                        $runningPaid = round($runningPaid + (float) $validPayment->amount, 2);
                        $installmentMeta[$validPayment->id] = [
                            'number' => $index + 1,
                            'settles' => $runningPaid >= $invoiceTotal,
                        ];
                    }
                ?>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px]">
                                <th class="px-6 py-3 font-semibold">Cicilan</th>
                                <th class="px-4 py-3 font-semibold">No. Pembayaran</th>
                                <th class="px-4 py-3 font-semibold">Tanggal</th>
                                <th class="px-4 py-3 font-semibold">Metode</th>
                                <th class="px-4 py-3 font-semibold">Kolektor</th>
                                <th class="px-4 py-3 font-semibold text-right">Nominal</th>
                                <th class="px-4 py-3 font-semibold text-center">Status</th>
                                <th class="px-4 py-3 font-semibold">Penerima</th>
                                <th class="px-6 py-3 font-semibold text-center">Bukti</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <?php $__currentLoopData = $invoice->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php $meta = $installmentMeta[$payment->id] ?? null; ?>
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if($meta): ?>
                                            <span class="font-semibold text-slate-700 dark:text-slate-200">Cicilan Ke-<?php echo e($meta['number']); ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-400 dark:text-slate-500">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 font-mono font-bold text-sky-600 dark:text-sky-400"><?php echo e($payment->payment_number); ?></td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300"><?php echo e(optional($payment->payment_date)->format('d/m/Y')); ?></td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/40">
                                            <?php echo e(strtoupper($payment->payment_method)); ?>

                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php if($payment->collector): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-violet-50 dark:bg-violet-950/60 text-violet-700 dark:text-violet-400 border border-violet-200/60 dark:border-violet-800/40">
                                                <?php echo e($payment->collector->name); ?>

                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400 dark:text-slate-500 text-[11px]">Langsung</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono font-bold text-slate-900 dark:text-slate-100">
                                        Rp <?php echo e(number_format((float) $payment->amount, 0, ',', '.')); ?>

                                        <?php if((float) $payment->overpay_amount > 0): ?>
                                            <span class="block text-[10px] font-semibold text-amber-600 dark:text-amber-400" title="Uang lebih yang diserahkan pelanggan — catatan saja, tidak menambah pembayaran tagihan">
                                                +<?php echo e(number_format((float) $payment->overpay_amount, 0, ',', '.')); ?> lebih
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-center whitespace-nowrap">
                                        <?php if(! $meta): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-400 border border-red-200/60 dark:border-red-800/40">Ditolak</span>
                                        <?php elseif($meta['settles']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/40">Lunas</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/40">Cicil</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 font-medium text-slate-800 dark:text-slate-200"><?php echo e($payment->receiver->name ?? '-'); ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if($payment->proof_file): ?>
                                            <a href="<?php echo e(asset('storage/' . $payment->proof_file)); ?>" target="_blank" class="text-sky-600 dark:text-sky-400 hover:underline font-semibold">Lihat Bukti</a>
                                        <?php else: ?>
                                            <span class="text-slate-400 dark:text-slate-500 italic text-[11px]">Tanpa lampiran</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-8 text-center text-xs text-slate-500 dark:text-slate-400">
                    Belum ada riwayat pembayaran yang dicatat untuk tagihan ini.
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB PANE 3: Audit Migrasi Legacy (Conditional) -->
        <?php if($invoice->old_invoice_id || $invoice->old_cost_id || $invoice->old_request_id): ?>
        <div id="pane-audit" class="hidden p-6 space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Audit Visibilitas Data Migrasi Legacy</h3>
            
            <div class="p-4 bg-slate-50/80 dark:bg-slate-800/40 rounded-lg border border-slate-200 dark:border-slate-800 space-y-2.5 text-xs max-w-lg">
                <?php if($invoice->old_invoice_id): ?>
                <div class="flex justify-between items-center pb-2 border-b border-slate-200/60 dark:border-slate-800/60">
                    <span class="text-slate-500 dark:text-slate-400">ID Invoice Lama</span>
                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($invoice->old_invoice_id); ?></span>
                </div>
                <?php endif; ?>
                <?php if($invoice->old_cost_id): ?>
                <div class="flex justify-between items-center pb-2 border-b border-slate-200/60 dark:border-slate-800/60">
                    <span class="text-slate-500 dark:text-slate-400">ID Biaya Lama</span>
                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($invoice->old_cost_id); ?></span>
                </div>
                <?php endif; ?>
                <?php if($invoice->old_request_id): ?>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500 dark:text-slate-400">ID Permintaan Lama</span>
                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($invoice->old_request_id); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
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
        const tabs = ['items', 'payments', 'audit'];
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/invoices/show.blade.php ENDPATH**/ ?>