<?php $__env->startSection('title', 'Detail Tagihan ' . $invoice->invoice_number . ' - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Detail Tagihan'); ?>
<?php $__env->startSection('breadcrumb_parent', 'Daftar Tagihan'); ?>
<?php $__env->startSection('breadcrumb_parent_url', route('invoices.index')); ?>

<?php $__env->startSection('content'); ?>
<?php
    $badgeClass = match($invoice->invoice_status->value) {
        'lunas' => 'bg-emerald-50 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60',
        'sebagian' => 'bg-amber-50 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60',
        'batal' => 'bg-rose-50 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60',
        default => 'bg-slate-50 dark:bg-slate-900/80 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700',
    };

    $totalAmount = (float) $invoice->total_amount;
    $paidAmount = (float) $invoice->paid_amount;
    $remainingAmount = (float) $invoice->remaining_amount;
    $paidPercentage = $totalAmount > 0 ? min(100, round(($paidAmount / $totalAmount) * 100, 1)) : 0;
    $remainingPercentage = max(0, round(100 - $paidPercentage, 1));
    
    $customerPhone = $invoice->customer->primary_phone ?? $invoice->customer->phone ?? '';
    if ($customerPhone && !str_starts_with($customerPhone, '62')) {
        if (str_starts_with($customerPhone, '0')) {
            $customerPhone = '62' . substr($customerPhone, 1);
        }
    }
    
    $waText = rawurlencode("Halo Sdr/i " . ($invoice->customer->full_name ?? '') . ", mengingatkan tagihan internet WHUSNET periode " . $invoice->billing_period . " (" . $invoice->invoice_number . ") memiliki sisa pembayaran sebesar Rp " . number_format($remainingAmount, 0, ',', '.') . ". Terima kasih.");
    $waUrl = $customerPhone ? "https://wa.me/{$customerPhone}?text={$waText}" : '#';
?>

<style>
    @media print {
        .no-print, header, sidebar, footer, nav, #toast, .modal, .mobile-action-bar { display: none !important; }
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
                <span class="font-mono <?php echo e((float)$invoice->remaining_amount > 0 ? 'text-rose-600' : 'text-emerald-600'); ?>">Rp <?php echo e(number_format((float)$invoice->remaining_amount, 0, ',', '.')); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- SCREEN ONLY ENTERPRISE VIEW (RECORD DETAIL TYPE B) -->
<div class="screen-only space-y-5">
    
    <!-- NAKED PAGE HEADER (UNIVERSAL RULE: PAGE HEADER ALWAYS NAKED) -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 py-1">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="<?php echo e(route('invoices.index')); ?>" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition-colors" title="Kembali">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Detail Tagihan</h1>
                
                <!-- Invoice Technical ID Badge + Copy -->
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-slate-800 dark:text-slate-200">
                    <span><?php echo e($invoice->invoice_number); ?></span>
                    <button onclick="copyToClipboard('<?php echo e($invoice->invoice_number); ?>', 'No. Invoice')" class="text-slate-400 hover:text-sky-600 transition-colors cursor-pointer" title="Salin No. Invoice">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </div>

                <!-- Status Badge -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border <?php echo e($badgeClass); ?>">
                    <span class="w-1.5 h-1.5 rounded-full bg-current <?php echo e($invoice->invoice_status->value === 'sebagian' ? 'animate-pulse' : ''); ?>"></span>
                    <?php echo e($invoice->invoice_status->label()); ?>

                </span>

                <?php if($invoice->invoice_type): ?>
                <span class="px-2.5 py-1 text-xs font-medium rounded-full border bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800">
                    <?php echo e($invoice->invoice_type->label()); ?>

                </span>
                <?php endif; ?>

                <?php
                    $invoiceTotalOverpay = $invoice->payments
                        ->filter(fn ($p) => $p->payment_status === \App\Enums\PaymentStatus::VALID)
                        ->sum(fn ($p) => (float) $p->overpay_amount);
                ?>
                <?php if($invoiceTotalOverpay > 0): ?>
                    <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800" title="Total uang lebih yang diserahkan pelanggan pada invoice ini">
                        Lebih Bayar Rp <?php echo e(number_format($invoiceTotalOverpay, 0, ',', '.')); ?>

                    </span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
                <span>Periode: <strong class="text-slate-800 dark:text-slate-200 font-mono"><?php echo e($invoice->billing_period); ?></strong></span>
                <span>&bull;</span>
                <span>Diterbitkan <?php echo e(optional($invoice->issue_date)->format('d/m/Y')); ?> oleh <?php echo e($invoice->creator->name ?? 'System'); ?></span>
            </p>
        </div>

        <!-- Naked Action Buttons Toolbar -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap no-print">
            <?php if($customerPhone): ?>
                <a href="<?php echo e($waUrl); ?>" target="_blank" class="inline-flex items-center justify-center gap-2 px-3.5 py-2 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-xs font-semibold rounded-lg transition-all shadow-2xs">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.105 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    <span>WhatsApp</span>
                </a>
            <?php endif; ?>

            <?php if(auth()->user()->hasPermission('create_payments') && !in_array($invoice->invoice_status->value, ['lunas', 'batal'], true)): ?>
                <a href="<?php echo e(route('invoices.payments.create', $invoice->id)); ?>" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-2xs transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span><?php echo e($invoice->invoice_status->value === 'sebagian' ? 'Bayar Cicil' : 'Input Pembayaran'); ?></span>
                </a>
            <?php endif; ?>

            <!-- Print Menu Dropdown -->
            <div class="relative flex-1 sm:flex-none">
                <button onclick="togglePrintDropdown(event)" id="printDropdownBtn" class="w-full inline-flex items-center justify-center gap-2 px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-2xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak</span>
                    <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div id="printDropdownMenu" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl py-1 z-40 text-xs">
                    <button type="button" onclick="window.print(); closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-left cursor-pointer font-medium">
                        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <div>
                            <p class="font-bold text-slate-900 dark:text-white leading-tight">Cetak Invoice A4 (PDF)</p>
                            <p class="text-[10px] text-slate-400">Format dokumen faktur resmi</p>
                        </div>
                    </button>
                    <div class="border-t border-slate-100 dark:border-slate-700/60"></div>
                    <?php if($invoice->payments->count() > 0): ?>
                        <a href="<?php echo e(route('payments.receipt', $invoice->payments->first()->id)); ?>" target="_blank" onclick="closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-left font-medium">
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <div>
                                <p class="font-bold text-slate-900 dark:text-white leading-tight">Struk Thermal (80mm)</p>
                                <p class="text-[10px] text-slate-400">Struk bukti bayar kasir POP</p>
                            </div>
                        </a>
                    <?php else: ?>
                        <button type="button" onclick="window.Toast.warning('Belum Ada Struk', 'Belum ada riwayat pembayaran terdaftar untuk mencetak struk kasir.'); closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-left opacity-75">
                            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <div>
                                <p class="font-semibold leading-tight">Struk Thermal (80mm)</p>
                                <p class="text-[10px] text-slate-400">Perlu pembayaran terdaftar</p>
                            </div>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- HERO METRIC SUMMARY CARDS (4 Grid, UNIFIED ROUNDED-LG 8PX CARD RADIUS) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Tagihan Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-5 shadow-2xs transition-all hover:border-sky-500/40 duration-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Total Tagihan</span>
                <div class="p-2 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-900/50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/></svg>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-white">Rp <?php echo e(number_format($totalAmount, 0, ',', '.')); ?></div>
            <div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400 flex justify-between">
                <span>Harga Paket Subtotal:</span>
                <span class="font-mono font-medium text-slate-800 dark:text-slate-200">Rp <?php echo e(number_format((float) $invoice->subtotal, 0, ',', '.')); ?></span>
            </div>
        </div>

        <!-- Sisa Tagihan Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-5 shadow-2xs transition-all hover:border-amber-500/40 duration-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Sisa Tagihan</span>
                <div class="p-2 rounded-lg <?php echo e($remainingAmount > 0 ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-900/50' : 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50'); ?>">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-bold font-mono <?php echo e($remainingAmount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'); ?>">
                Rp <?php echo e(number_format($remainingAmount, 0, ',', '.')); ?>

            </div>
            <div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400 flex justify-between">
                <span>Sudah Terbayar:</span>
                <span class="font-mono font-medium text-emerald-600 dark:text-emerald-400">Rp <?php echo e(number_format($paidAmount, 0, ',', '.')); ?></span>
            </div>
        </div>

        <!-- Jatuh Tempo Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-5 shadow-2xs transition-all hover:border-purple-500/40 duration-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Jatuh Tempo</span>
                <div class="p-2 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 border border-purple-100 dark:border-purple-900/50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-white"><?php echo e(optional($invoice->due_date)->format('d/m/Y') ?? '-'); ?></div>
            <div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400 flex justify-between">
                <span>Tanggal Terbit:</span>
                <span class="font-mono font-medium text-slate-800 dark:text-slate-200"><?php echo e(optional($invoice->issue_date)->format('d/m/Y') ?? '-'); ?></span>
            </div>
        </div>

        <!-- POP / Cabang Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-5 shadow-2xs transition-all hover:border-emerald-500/40 duration-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">POP / Cabang</span>
                <div class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h5m-5 0V11m0 0h5m-5 0H7"/></svg>
                </div>
            </div>
            <div class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white truncate"><?php echo e($invoice->pop->name ?? '-'); ?></div>
            <div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400 truncate">
                Oleh: <?php echo e($invoice->creator->name ?? 'System'); ?>

            </div>
        </div>
    </div>

    <!-- MAIN ENTERPRISE 2-COLUMN GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
        
        <!-- LEFT MAIN PANEL (8 cols on lg/xl) -->
        <div class="lg:col-span-8 space-y-5">
            
            <!-- TABBED CARD PANEL WITH ROUNDED-LG RADIUS -->
            <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-2xs">
                
                <!-- Navigation Tabs Header -->
                <div class="p-2 bg-slate-50/70 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 flex gap-1.5 overflow-x-auto custom-scrollbar no-print sticky top-0 z-20 backdrop-blur-xs">
                    <button onclick="switchTab('items')" id="tab-items" class="px-4 py-2.5 rounded-lg text-xs font-bold bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-2xs border border-slate-200 dark:border-slate-700 flex items-center gap-2 transition-all cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Rincian Kalkulasi Biaya</span>
                    </button>

                    <button onclick="switchTab('payments')" id="tab-payments" class="px-4 py-2.5 rounded-lg text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 flex items-center gap-2 transition-all cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Riwayat Pembayaran (<?php echo e($invoice->payments->count()); ?>)</span>
                    </button>

                    <?php if($invoice->old_invoice_id || $invoice->old_cost_id || $invoice->old_request_id): ?>
                    <button onclick="switchTab('audit')" id="tab-audit" class="px-4 py-2.5 rounded-lg text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 flex items-center gap-2 transition-all cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2h2a2 2 0 012-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span>Audit Migrasi Legacy</span>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- TAB PANE 1: Rincian Biaya -->
                <div id="pane-items" class="p-5 sm:p-6 space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-3">
                        <h3 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Rincian Komponen Invoice</h3>
                        <span class="text-xs font-mono text-slate-400 dark:text-slate-500">ID Invoice: <?php echo e($invoice->id); ?></span>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700/60">
                            <div class="space-y-0.5">
                                <p class="font-bold text-slate-900 dark:text-white">Harga Paket Internet (Subtotal)</p>
                                <p class="text-[11px] text-slate-400"><?php echo e($invoice->customerService->package_name_snapshot ?? $invoice->internetPackage->name ?? 'Paket Internet'); ?> &bull; Periode <?php echo e($invoice->billing_period); ?></p>
                            </div>
                            <span class="font-mono font-bold text-slate-900 dark:text-white text-sm">Rp <?php echo e(number_format((float) $invoice->subtotal, 0, ',', '.')); ?></span>
                        </div>

                        <?php if((float)$invoice->discount > 0): ?>
                        <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700/60 text-emerald-600 dark:text-emerald-400">
                            <span class="font-semibold">Potongan Diskon</span>
                            <span class="font-mono font-bold text-sm">- Rp <?php echo e(number_format((float) $invoice->discount, 0, ',', '.')); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php
                            $afterDiscount = max(0, (float)$invoice->subtotal - (float)$invoice->discount);
                            $ppnRate = (float)$invoice->ppn;
                            $ppnAmount = round($afterDiscount * ($ppnRate / 100), 2);
                        ?>

                        <?php if($ppnRate > 0): ?>
                        <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700/60">
                            <span class="text-slate-600 dark:text-slate-400 font-medium">PPN (<?php echo e(number_format($ppnRate, 0)); ?>%)</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white text-sm">Rp <?php echo e(number_format($ppnAmount, 0, ',', '.')); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700/60">
                            <span class="text-slate-600 dark:text-slate-400 font-medium">PPN (Pajak Pertambahan Nilai)</span>
                            <span class="font-mono text-slate-400 dark:text-slate-500">Tidak Dikenakan</span>
                        </div>
                        <?php endif; ?>

                        <?php if((float)($invoice->prorate_amount ?? 0) > 0): ?>
                        <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700/60">
                            <span class="text-slate-600 dark:text-slate-400 font-medium">Tagihan Prorate</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white text-sm">Rp <?php echo e(number_format((float) $invoice->prorate_amount, 0, ',', '.')); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if((float)($invoice->extra_cable_fee ?? 0) > 0): ?>
                        <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700/60">
                            <span class="text-slate-600 dark:text-slate-400 font-medium">Biaya Kabel Tambahan</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white text-sm">Rp <?php echo e(number_format((float) $invoice->extra_cable_fee, 0, ',', '.')); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if((float)($invoice->other_fee ?? 0) > 0): ?>
                        <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700/60">
                            <span class="text-slate-600 dark:text-slate-400 font-medium">Biaya Lain-lain</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white text-sm">Rp <?php echo e(number_format((float) $invoice->other_fee, 0, ',', '.')); ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Summary Footer Breakdown -->
                        <div class="pt-4 space-y-2.5 border-t-2 border-slate-200 dark:border-slate-700">
                            <div class="flex justify-between items-center text-sm font-bold">
                                <span class="text-slate-900 dark:text-white">TOTAL TAGIHAN</span>
                                <span class="font-mono text-lg text-slate-900 dark:text-white">Rp <?php echo e(number_format($totalAmount, 0, ',', '.')); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                                <span>Sudah Terbayar</span>
                                <span class="font-mono text-sm">- Rp <?php echo e(number_format($paidAmount, 0, ',', '.')); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-sm font-extrabold pt-2.5 border-t border-slate-100 dark:border-slate-700/60">
                                <span class="<?php echo e($remainingAmount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'); ?>">
                                    <?php echo e($remainingAmount > 0 ? 'SISA YANG HARUS DIBAYAR' : 'STATUS TAGIHAN LUNAS'); ?>

                                </span>
                                <span class="font-mono text-xl <?php echo e($remainingAmount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'); ?>">
                                    Rp <?php echo e(number_format($remainingAmount, 0, ',', '.')); ?>

                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB PANE 2: Riwayat Pembayaran -->
                <div id="pane-payments" class="hidden">
                    <?php if($invoice->payments->count() > 0): ?>
                        <?php
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
                                    <tr class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-slate-400 uppercase tracking-wider text-[10px] font-bold">
                                        <th class="px-5 py-3.5">Cicilan</th>
                                        <th class="px-4 py-3.5">No. Pembayaran</th>
                                        <th class="px-4 py-3.5">Tanggal</th>
                                        <th class="px-4 py-3.5">Metode</th>
                                        <th class="px-4 py-3.5 text-right">Nominal</th>
                                        <th class="px-4 py-3.5 text-center">Status</th>
                                        <th class="px-4 py-3.5">Penerima</th>
                                        <th class="px-5 py-3.5 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-slate-700 dark:text-slate-300 font-medium">
                                    <?php $__currentLoopData = $invoice->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php $meta = $installmentMeta[$payment->id] ?? null; ?>
                                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/40 transition-colors">
                                            <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                                                <?php if($meta): ?>
                                                    Cicilan Ke-<?php echo e($meta['number']); ?>

                                                <?php else: ?>
                                                    &mdash;
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3.5 font-mono font-bold text-sky-600 dark:text-sky-400">
                                                <a href="<?php echo e(route('payments.show', $payment->id)); ?>" class="hover:underline">
                                                    <?php echo e($payment->payment_number); ?>

                                                </a>
                                            </td>
                                            <td class="px-4 py-3.5 font-mono text-slate-800 dark:text-slate-200 whitespace-nowrap"><?php echo e(optional($payment->payment_date)->format('d/m/Y')); ?></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 uppercase">
                                                    <?php echo e(strtoupper($payment->payment_method)); ?>

                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                                Rp <?php echo e(number_format((float) $payment->amount, 0, ',', '.')); ?>

                                                <?php if((float) $payment->overpay_amount > 0): ?>
                                                    <span class="block text-[10px] font-semibold text-amber-600 dark:text-amber-400" title="Uang lebih yang diserahkan pelanggan">
                                                        +<?php echo e(number_format((float) $payment->overpay_amount, 0, ',', '.')); ?> lebih
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                                <?php if(! $meta): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800">Ditolak</span>
                                                <?php elseif($meta['settles']): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">Lunas</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800">Cicil</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3.5 font-medium text-slate-800 dark:text-slate-200 whitespace-nowrap"><?php echo e($payment->receiver->name ?? '-'); ?></td>
                                            <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <?php if($payment->proof_file): ?>
                                                        <a href="<?php echo e(asset('storage/' . $payment->proof_file)); ?>" target="_blank" class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-sky-600 dark:text-sky-400 hover:bg-slate-200 rounded text-[11px] font-bold">Bukti</a>
                                                    <?php endif; ?>
                                                    <a href="<?php echo e(route('payments.receipt', $payment->id)); ?>" target="_blank" class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 transition-colors" title="Lihat Struk Thermal">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center text-xs text-slate-400">
                            Belum ada riwayat pembayaran yang dicatat untuk tagihan ini.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB PANE 3: Audit Migrasi Legacy (Conditional) -->
                <?php if($invoice->old_invoice_id || $invoice->old_cost_id || $invoice->old_request_id): ?>
                <div id="pane-audit" class="hidden p-5 sm:p-6 space-y-4">
                    <h3 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Audit Visibilitas Data Migrasi Legacy</h3>
                    
                    <div class="p-4 bg-slate-50/70 dark:bg-slate-900/60 rounded-lg border border-slate-200 dark:border-slate-700 space-y-2.5 text-xs max-w-md">
                        <?php if($invoice->old_invoice_id): ?>
                        <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-700/60">
                            <span class="text-slate-500">ID Invoice Lama:</span>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($invoice->old_invoice_id); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($invoice->old_cost_id): ?>
                        <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-700/60">
                            <span class="text-slate-500">ID Biaya Lama:</span>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($invoice->old_cost_id); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($invoice->old_request_id): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">ID Permintaan Lama:</span>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo e($invoice->old_request_id); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT STICKY SIDEBAR PANEL (4 cols on lg/xl) -->
        <div class="lg:col-span-4 space-y-5 lg:sticky lg:top-20">
            
            <!-- CUSTOMER PROFILE CARD -->
            <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Identitas Pelanggan</span>
                    <?php if($invoice->customer_id): ?>
                    <a href="<?php echo e(route('customers.show', $invoice->customer_id)); ?>" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1">
                        <span>Profil Full</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                    <?php endif; ?>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300 font-bold text-sm flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800">
                        <?php echo e(strtoupper(substr($invoice->customer->full_name ?? 'P', 0, 2))); ?>

                    </div>
                    <div class="space-y-0.5 text-xs min-w-0">
                        <?php if($invoice->customer_id): ?>
                        <a href="<?php echo e(route('customers.show', $invoice->customer_id)); ?>" class="font-bold text-slate-900 dark:text-white text-sm hover:text-sky-600 transition-colors block truncate">
                            <?php echo e($invoice->customer->full_name ?? '-'); ?>

                        </a>
                        <?php else: ?>
                        <span class="font-bold text-slate-900 dark:text-white text-sm block truncate"><?php echo e($invoice->customer->full_name ?? '-'); ?></span>
                        <?php endif; ?>
                        <div class="font-mono text-[11px] text-slate-400 flex items-center gap-1">
                            <span>CID: <?php echo e($invoice->customer->cid ?? $invoice->customer->customer_code ?? '-'); ?></span>
                            <?php if($invoice->customer && ($invoice->customer->cid || $invoice->customer->customer_code)): ?>
                                <button onclick="copyToClipboard('<?php echo e($invoice->customer->cid ?? $invoice->customer->customer_code); ?>', 'CID')" class="text-slate-400 hover:text-sky-600 cursor-pointer" title="Salin CID">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="space-y-2.5 text-xs pt-3 border-t border-slate-100 dark:border-slate-700/60">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 dark:text-slate-400">No. HP / WA:</span>
                        <div class="flex items-center gap-1.5 font-mono font-semibold text-slate-800 dark:text-slate-200">
                            <span><?php echo e($invoice->customer->primary_phone ?? $invoice->customer->phone ?? '-'); ?></span>
                            <?php if($customerPhone): ?>
                            <a href="<?php echo e($waUrl); ?>" target="_blank" class="p-1 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 rounded" title="Chat WA">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 dark:text-slate-400">POP / Cabang:</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200"><?php echo e($invoice->pop->name ?? '-'); ?></span>
                    </div>
                    <div class="pt-1">
                        <span class="text-slate-500 dark:text-slate-400 block mb-1">Alamat Pemasangan:</span>
                        <p class="text-slate-700 dark:text-slate-300 text-[11px] leading-relaxed bg-slate-50/70 dark:bg-slate-900/60 p-2.5 rounded-lg border border-slate-200/60 dark:border-slate-700/60">
                            <?php echo e($invoice->customer->address ?? '-'); ?>

                        </p>
                    </div>
                </div>
            </div>

            <!-- QUICK ACTIONS CARD -->
            <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-5 shadow-2xs space-y-3">
                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block pb-2 border-b border-slate-100 dark:border-slate-700/60">Aksi & Informasi Pembayaran</span>
                
                <div class="space-y-2 text-xs">
                    <?php if(auth()->user()->hasPermission('create_payments') && !in_array($invoice->invoice_status->value, ['lunas', 'batal'], true)): ?>
                        <a href="<?php echo e(route('invoices.payments.create', $invoice->id)); ?>" class="w-full flex items-center justify-between px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition-all cursor-pointer shadow-2xs">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                <span><?php echo e($invoice->invoice_status->value === 'sebagian' ? 'Input Pembayaran Cicil' : 'Input Pembayaran Kasir'); ?></span>
                            </div>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    <?php endif; ?>

                    <button type="button" onclick="window.print()" class="w-full flex items-center justify-between px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 hover:bg-slate-100 dark:hover:bg-slate-700/60 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 rounded-lg text-xs font-semibold transition-all cursor-pointer">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <span>Cetak Faktur Invoice A4</span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </button>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- MOBILE BOTTOM FIXED ACTION BAR -->
<div class="fixed bottom-0 left-0 right-0 p-3 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-t border-slate-200 dark:border-slate-700 sm:hidden z-30 flex items-center gap-2 no-print">
    <?php if($customerPhone): ?>
        <a href="<?php echo e($waUrl); ?>" target="_blank" class="p-2.5 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 rounded-lg shrink-0">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.105 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
        </a>
    <?php endif; ?>

    <button onclick="window.print()" class="p-2.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-lg shrink-0">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
    </button>

    <?php if(auth()->user()->hasPermission('create_payments') && !in_array($invoice->invoice_status->value, ['lunas', 'batal'], true)): ?>
        <a href="<?php echo e(route('invoices.payments.create', $invoice->id)); ?>" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 text-white text-xs font-bold rounded-lg shadow-2xs">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            <span><?php echo e($invoice->invoice_status->value === 'sebagian' ? 'Bayar Cicil' : 'Input Bayar'); ?></span>
        </a>
    <?php endif; ?>
</div>

<script>
    function switchTab(tabKey) {
        const tabs = ['items', 'payments', 'audit'];
        tabs.forEach(key => {
            const btn = document.getElementById(`tab-${key}`);
            const pane = document.getElementById(`pane-${key}`);
            if (!btn || !pane) return;

            if (key === tabKey) {
                btn.className = 'px-4 py-2.5 rounded-lg text-xs font-bold bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-2xs border border-slate-200 dark:border-slate-700 flex items-center gap-2 transition-all cursor-pointer shrink-0';
                pane.classList.remove('hidden');
            } else {
                btn.className = 'px-4 py-2.5 rounded-lg text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 flex items-center gap-2 transition-all cursor-pointer shrink-0';
                pane.classList.add('hidden');
            }
        });
    }

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
        if (window.Toast && window.Toast.success) {
            window.Toast.success('Disalin', `${label || 'Teks'} (${text}) berhasil disalin.`);
        } else {
            alert(`${label || 'Teks'} (${text}) berhasil disalin.`);
        }
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/invoices/show.blade.php ENDPATH**/ ?>