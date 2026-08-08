<?php $__env->startSection('title', 'Detail Tagihan ' . $invoice->invoice_number . ' - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Detail Tagihan'); ?>
<?php $__env->startSection('breadcrumb_parent', 'Daftar Tagihan'); ?>
<?php $__env->startSection('breadcrumb_parent_url', route('invoices.index')); ?>

<?php $__env->startSection('content'); ?>
<?php
    $badgeClass = match($invoice->invoice_status->value) {
        'lunas' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
        'sebagian' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
        'batal' => 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20',
        default => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
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
                <span class="font-mono <?php echo e((float)$invoice->remaining_amount > 0 ? 'text-red-600' : 'text-emerald-600'); ?>">Rp <?php echo e(number_format((float)$invoice->remaining_amount, 0, ',', '.')); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- SCREEN ONLY ENTERPRISE REDESIGN VIEW -->
<div class="space-y-6 screen-only max-w-full pb-20 md:pb-8">
    
    <!-- HEADER TITLE & QUICK ACTIONS BAR -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-surface p-5 sm:p-6 rounded-2xl border border-border shadow-2xs">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="<?php echo e(route('invoices.index')); ?>" class="p-1.5 text-text-muted hover:text-text-main rounded-lg hover:bg-surface-muted transition-colors" title="Kembali">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-xl sm:text-2xl font-extrabold text-text-main tracking-tight">Detail Tagihan</h1>
                
                <!-- Invoice Technical ID Badge + Copy -->
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-muted border border-border text-xs font-mono font-bold text-text-main">
                    <span><?php echo e($invoice->invoice_number); ?></span>
                    <button onclick="copyToClipboard('<?php echo e($invoice->invoice_number); ?>', 'No. Invoice')" class="text-text-muted hover:text-primary transition-colors cursor-pointer" title="Salin No. Invoice">
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
                    <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800" title="Total uang lebih yang diserahkan pelanggan pada invoice ini">
                        Lebih Bayar Rp <?php echo e(number_format($invoiceTotalOverpay, 0, ',', '.')); ?>

                    </span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-text-muted flex items-center gap-2">
                <span>Periode: <strong class="text-text-main font-mono"><?php echo e($invoice->billing_period); ?></strong></span>
                <span>&bull;</span>
                <span>Diterbitkan <?php echo e(optional($invoice->issue_date)->format('d/m/Y')); ?> oleh <?php echo e($invoice->creator->name ?? 'System'); ?></span>
            </p>
        </div>

        <!-- Desktop Action Buttons Toolbar -->
        <div class="hidden sm:flex items-center gap-2.5 no-print">
            <?php if($customerPhone): ?>
                <a href="<?php echo e($waUrl); ?>" target="_blank" class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-xs font-semibold rounded-xl transition-all shadow-2xs">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.105 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    <span>WhatsApp</span>
                </a>
            <?php endif; ?>

            <?php if(auth()->user()->hasPermission('create_payments') && !in_array($invoice->invoice_status->value, ['lunas', 'batal'], true)): ?>
                <a href="<?php echo e(route('invoices.payments.create', $invoice->id)); ?>" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-md shadow-emerald-600/20 transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span><?php echo e($invoice->invoice_status->value === 'sebagian' ? 'Bayar Cicil' : 'Input Pembayaran'); ?></span>
                </a>
            <?php endif; ?>

            <!-- Print Menu Dropdown -->
            <div class="relative flex-1 sm:flex-none">
                <button onclick="togglePrintDropdown(event)" id="printDropdownBtn" class="w-full inline-flex items-center justify-center gap-2 px-3.5 py-2.5 bg-surface border border-border hover:bg-surface-muted text-text-main text-xs font-semibold rounded-xl shadow-2xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak</span>
                    <svg class="w-3 h-3 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div id="printDropdownMenu" class="hidden absolute right-0 mt-2 w-56 bg-surface border border-border rounded-xl shadow-xl py-1 z-40 text-xs">
                    <button type="button" onclick="window.print(); closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-text-main hover:bg-surface-muted transition-colors text-left cursor-pointer font-medium">
                        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <div>
                            <p class="font-semibold text-text-main leading-tight">Cetak Invoice A4 (PDF)</p>
                            <p class="text-[10px] text-text-muted">Format dokumen faktur resmi</p>
                        </div>
                    </button>
                    <div class="border-t border-border"></div>
                    <?php if($invoice->payments->count() > 0): ?>
                        <a href="<?php echo e(route('payments.receipt', $invoice->payments->first()->id)); ?>" target="_blank" onclick="closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-text-main hover:bg-surface-muted transition-colors text-left font-medium">
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <div>
                                <p class="font-semibold text-text-main leading-tight">Struk Thermal (80mm)</p>
                                <p class="text-[10px] text-text-muted">Struk bukti bayar kasir POP</p>
                            </div>
                        </a>
                    <?php else: ?>
                        <button type="button" onclick="window.Toast.warning('Belum Ada Struk', 'Belum ada riwayat pembayaran terdaftar untuk mencetak struk kasir.'); closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-text-muted hover:bg-surface-muted transition-colors text-left opacity-75">
                            <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <div>
                                <p class="font-semibold leading-tight">Struk Thermal (80mm)</p>
                                <p class="text-[10px] text-text-muted">Perlu pembayaran terdaftar</p>
                            </div>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- HERO METRIC SUMMARY CARDS (4 Grid) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 xl:gap-5">
        <!-- Total Tagihan Card -->
        <div class="bg-surface border border-border rounded-2xl p-5 shadow-2xs transition-all hover:border-primary/40 hover:shadow-md duration-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-text-muted uppercase tracking-wider">Total Tagihan</span>
                <div class="p-2 rounded-xl bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/></svg>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-bold font-mono text-text-main">Rp <?php echo e(number_format($totalAmount, 0, ',', '.')); ?></div>
            <div class="mt-2 text-[11px] text-text-muted flex justify-between">
                <span>Harga Paket Subtotal:</span>
                <span class="font-mono font-medium text-text-main">Rp <?php echo e(number_format((float) $invoice->subtotal, 0, ',', '.')); ?></span>
            </div>
        </div>

        <!-- Sisa Tagihan Card -->
        <div class="bg-surface border border-border rounded-2xl p-5 shadow-2xs transition-all hover:border-amber-500/40 hover:shadow-md duration-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-text-muted uppercase tracking-wider">Sisa Tagihan</span>
                <div class="p-2 rounded-xl <?php echo e($remainingAmount > 0 ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'); ?>">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-bold font-mono <?php echo e($remainingAmount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'); ?>">
                Rp <?php echo e(number_format($remainingAmount, 0, ',', '.')); ?>

            </div>
            <div class="mt-2 text-[11px] text-text-muted flex justify-between">
                <span>Sudah Terbayar:</span>
                <span class="font-mono font-medium text-emerald-600 dark:text-emerald-400">Rp <?php echo e(number_format($paidAmount, 0, ',', '.')); ?></span>
            </div>
        </div>

        <!-- Jatuh Tempo Card -->
        <div class="bg-surface border border-border rounded-2xl p-5 shadow-2xs transition-all hover:border-purple-500/40 hover:shadow-md duration-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-text-muted uppercase tracking-wider">Jatuh Tempo</span>
                <div class="p-2 rounded-xl bg-purple-50 dark:bg-purple-500/10 text-purple-600 dark:text-purple-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-bold font-mono text-text-main"><?php echo e(optional($invoice->due_date)->format('d/m/Y') ?? '-'); ?></div>
            <div class="mt-2 text-[11px] text-text-muted flex justify-between">
                <span>Tanggal Terbit:</span>
                <span class="font-mono font-medium text-text-main"><?php echo e(optional($invoice->issue_date)->format('d/m/Y') ?? '-'); ?></span>
            </div>
        </div>

        <!-- POP / Cabang Card -->
        <div class="bg-surface border border-border rounded-2xl p-5 shadow-2xs transition-all hover:border-emerald-500/40 hover:shadow-md duration-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-text-muted uppercase tracking-wider">POP / Cabang</span>
                <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h5m-5 0V11m0 0h5m-5 0H7"/></svg>
                </div>
            </div>
            <div class="text-lg sm:text-xl font-bold text-text-main truncate"><?php echo e($invoice->pop->name ?? '-'); ?></div>
            <div class="mt-2 text-[11px] text-text-muted truncate">
                Oleh: <?php echo e($invoice->creator->name ?? 'System'); ?>

            </div>
        </div>
    </div>

    <!-- MAIN ENTERPRISE 2-COLUMN GRID (8 cols Left Main + 4 cols Sticky Sidebar on xl:) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
        
        <!-- LEFT MAIN PANEL (7 cols on lg:, 8 cols on xl:) -->
        <div class="lg:col-span-7 xl:col-span-8 space-y-6">
            
            <!-- TABBED CARD PANEL WITH SEGMENTED SWITCHER -->
            <div class="bg-surface border border-border rounded-2xl overflow-hidden shadow-2xs">
                
                <!-- Segmented Navigation Tabs Header -->
                <div class="p-2 bg-surface-muted/60 border-b border-border flex gap-1.5 overflow-x-auto custom-scrollbar no-print sticky top-0 z-20 backdrop-blur-xs">
                    <button onclick="switchTab('items')" id="tab-items" class="px-4 py-2.5 rounded-xl text-xs font-bold bg-surface text-primary shadow-xs flex items-center gap-2 transition-all cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Rincian Kalkulasi Biaya</span>
                    </button>

                    <button onclick="switchTab('payments')" id="tab-payments" class="px-4 py-2.5 rounded-xl text-xs font-medium text-text-muted hover:text-text-main flex items-center gap-2 transition-all cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Riwayat Pembayaran (<?php echo e($invoice->payments->count()); ?>)</span>
                    </button>

                    <?php if($invoice->old_invoice_id || $invoice->old_cost_id || $invoice->old_request_id): ?>
                    <button onclick="switchTab('audit')" id="tab-audit" class="px-4 py-2.5 rounded-xl text-xs font-medium text-text-muted hover:text-text-main flex items-center gap-2 transition-all cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span>Audit Migrasi Legacy</span>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- TAB PANE 1: Rincian Biaya -->
                <div id="pane-items" class="p-6 space-y-6">
                    <div class="flex items-center justify-between border-b border-border pb-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted">Rincian Komponen Invoice</h3>
                        <span class="text-xs font-mono text-text-muted">ID Invoice: <?php echo e($invoice->id); ?></span>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between items-center py-2 border-b border-border">
                            <div class="space-y-0.5">
                                <p class="font-semibold text-text-main">Harga Paket Internet (Subtotal)</p>
                                <p class="text-[11px] text-text-muted"><?php echo e($invoice->customerService->package_name_snapshot ?? $invoice->internetPackage->name ?? 'Paket Internet'); ?> &bull; Periode <?php echo e($invoice->billing_period); ?></p>
                            </div>
                            <span class="font-mono font-bold text-text-main text-sm">Rp <?php echo e(number_format((float) $invoice->subtotal, 0, ',', '.')); ?></span>
                        </div>

                        <?php if((float)$invoice->discount > 0): ?>
                        <div class="flex justify-between items-center py-2 border-b border-border text-emerald-600 dark:text-emerald-400">
                            <span class="font-medium">Potongan Diskon</span>
                            <span class="font-mono font-semibold text-sm">- Rp <?php echo e(number_format((float) $invoice->discount, 0, ',', '.')); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php
                            $afterDiscount = max(0, (float)$invoice->subtotal - (float)$invoice->discount);
                            $ppnRate = (float)$invoice->ppn;
                            $ppnAmount = round($afterDiscount * ($ppnRate / 100), 2);
                        ?>

                        <?php if($ppnRate > 0): ?>
                        <div class="flex justify-between items-center py-2 border-b border-border">
                            <span class="text-text-secondary font-medium">PPN (<?php echo e(number_format($ppnRate, 0)); ?>%)</span>
                            <span class="font-mono font-semibold text-text-main text-sm">Rp <?php echo e(number_format($ppnAmount, 0, ',', '.')); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="flex justify-between items-center py-2 border-border">
                            <span class="text-text-secondary font-medium">PPN (Pajak Pertambahan Nilai)</span>
                            <span class="font-mono text-text-muted">Tidak Dikenakan</span>
                        </div>
                        <?php endif; ?>

                        <?php if((float)($invoice->prorate_amount ?? 0) > 0): ?>
                        <div class="flex justify-between items-center py-2 border-b border-border">
                            <span class="text-text-secondary font-medium">Tagihan Prorate</span>
                            <span class="font-mono font-semibold text-text-main text-sm">Rp <?php echo e(number_format((float) $invoice->prorate_amount, 0, ',', '.')); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if((float)($invoice->extra_cable_fee ?? 0) > 0): ?>
                        <div class="flex justify-between items-center py-2 border-b border-border">
                            <span class="text-text-secondary font-medium">Biaya Kabel Tambahan</span>
                            <span class="font-mono font-semibold text-text-main text-sm">Rp <?php echo e(number_format((float) $invoice->extra_cable_fee, 0, ',', '.')); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if((float)($invoice->other_fee ?? 0) > 0): ?>
                        <div class="flex justify-between items-center py-2 border-b border-border">
                            <span class="text-text-secondary font-medium">Biaya Lain-lain</span>
                            <span class="font-mono font-semibold text-text-main text-sm">Rp <?php echo e(number_format((float) $invoice->other_fee, 0, ',', '.')); ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Summary Footer Breakdown -->
                        <div class="pt-4 space-y-2.5 border-t-2 border-border">
                            <div class="flex justify-between items-center text-sm font-bold">
                                <span class="text-text-main">TOTAL TAGIHAN</span>
                                <span class="font-mono text-lg text-text-main">Rp <?php echo e(number_format($totalAmount, 0, ',', '.')); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                                <span>Sudah Terbayar</span>
                                <span class="font-mono text-sm">- Rp <?php echo e(number_format($paidAmount, 0, ',', '.')); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-sm font-extrabold pt-2.5 border-t border-border">
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
                                    <tr class="bg-surface-muted/60 border-b border-border text-text-muted uppercase tracking-wider text-[10px] font-bold">
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
                                <tbody class="divide-y divide-border text-text-secondary">
                                    <?php $__currentLoopData = $invoice->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php $meta = $installmentMeta[$payment->id] ?? null; ?>
                                        <tr class="hover:bg-surface-muted/50 transition-colors">
                                            <td class="px-5 py-3.5 font-semibold text-text-main">
                                                <?php if($meta): ?>
                                                    Cicilan Ke-<?php echo e($meta['number']); ?>

                                                <?php else: ?>
                                                    &mdash;
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3.5 font-mono font-bold text-primary">
                                                <a href="<?php echo e(route('payments.show', $payment->id)); ?>" class="hover:underline">
                                                    <?php echo e($payment->payment_number); ?>

                                                </a>
                                            </td>
                                            <td class="px-4 py-3.5 font-mono text-text-main whitespace-nowrap"><?php echo e(optional($payment->payment_date)->format('d/m/Y')); ?></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 uppercase">
                                                    <?php echo e(strtoupper($payment->payment_method)); ?>

                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5 text-right font-mono font-bold text-text-main whitespace-nowrap">
                                                Rp <?php echo e(number_format((float) $payment->amount, 0, ',', '.')); ?>

                                                <?php if((float) $payment->overpay_amount > 0): ?>
                                                    <span class="block text-[10px] font-semibold text-amber-600 dark:text-amber-400" title="Uang lebih yang diserahkan pelanggan">
                                                        +<?php echo e(number_format((float) $payment->overpay_amount, 0, ',', '.')); ?> lebih
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                                <?php if(! $meta): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">Ditolak</span>
                                                <?php elseif($meta['settles']): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">Lunas</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">Cicil</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3.5 font-medium text-text-main whitespace-nowrap"><?php echo e($payment->receiver->name ?? '-'); ?></td>
                                            <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <?php if($payment->proof_file): ?>
                                                        <a href="<?php echo e(asset('storage/' . $payment->proof_file)); ?>" target="_blank" class="px-2 py-1 bg-surface-muted text-primary hover:bg-border rounded text-[11px] font-semibold">Bukti</a>
                                                    <?php endif; ?>
                                                    <a href="<?php echo e(route('payments.receipt', $payment->id)); ?>" target="_blank" class="p-1.5 rounded-lg border border-border hover:bg-surface-muted text-text-muted transition-colors" title="Lihat Struk Thermal">
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
                        <div class="p-8 text-center text-xs text-text-muted">
                            Belum ada riwayat pembayaran yang dicatat untuk tagihan ini.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB PANE 3: Audit Migrasi Legacy (Conditional) -->
                <?php if($invoice->old_invoice_id || $invoice->old_cost_id || $invoice->old_request_id): ?>
                <div id="pane-audit" class="hidden p-6 space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted">Audit Visibilitas Data Migrasi Legacy</h3>
                    
                    <div class="p-4 bg-surface-muted/50 rounded-xl border border-border space-y-2.5 text-xs max-w-md">
                        <?php if($invoice->old_invoice_id): ?>
                        <div class="flex justify-between items-center pb-2 border-b border-border">
                            <span class="text-text-muted">ID Invoice Lama:</span>
                            <span class="font-mono font-bold text-text-main"><?php echo e($invoice->old_invoice_id); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($invoice->old_cost_id): ?>
                        <div class="flex justify-between items-center pb-2 border-b border-border">
                            <span class="text-text-muted">ID Biaya Lama:</span>
                            <span class="font-mono font-bold text-text-main"><?php echo e($invoice->old_cost_id); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($invoice->old_request_id): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-text-muted">ID Permintaan Lama:</span>
                            <span class="font-mono font-bold text-text-main"><?php echo e($invoice->old_request_id); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT STICKY SIDEBAR PANEL (5 cols on lg:, 4 cols on xl: — STICKY ON LAPTOP & DESKTOP++) -->
        <div class="lg:col-span-5 xl:col-span-4 lg:sticky lg:top-6 space-y-6 self-start">
            
            <!-- CUSTOMER PROFILE CARD -->
            <div class="bg-surface border border-border rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Identitas Pelanggan</span>
                    <a href="<?php echo e(route('customers.show', $invoice->customer_id)); ?>" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                        <span>Profil Full</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 font-bold text-base flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800">
                        <?php echo e(strtoupper(substr($invoice->customer->full_name ?? 'P', 0, 2))); ?>

                    </div>
                    <div class="space-y-1 min-w-0">
                        <a href="<?php echo e(route('customers.show', $invoice->customer_id)); ?>" class="font-bold text-text-main text-sm hover:text-primary transition-colors block truncate">
                            <?php echo e($invoice->customer->full_name ?? '-'); ?>

                        </a>
                        <div class="flex items-center gap-1 text-xs text-text-muted font-mono">
                            <span>CID: <?php echo e($invoice->customer->cid ?? $invoice->customer->customer_code ?? '-'); ?></span>
                            <?php if($invoice->customer->cid || $invoice->customer->customer_code): ?>
                                <button onclick="copyToClipboard('<?php echo e($invoice->customer->cid ?? $invoice->customer->customer_code); ?>', 'CID')" class="hover:text-primary transition-colors cursor-pointer" title="Salin CID">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="space-y-2.5 text-xs pt-3 border-t border-dashed border-border text-text-secondary">
                    <div class="flex justify-between gap-2">
                        <span class="text-text-muted">No. Telephone:</span>
                        <span class="font-mono font-semibold text-text-main"><?php echo e($invoice->customer->primary_phone ?? $invoice->customer->phone ?? '-'); ?></span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span class="text-text-muted">POP / Cabang:</span>
                        <span class="font-semibold text-text-main"><?php echo e($invoice->pop->name ?? '-'); ?></span>
                    </div>
                    <div>
                        <span class="text-text-muted block mb-1">Alamat Pelanggan:</span>
                        <p class="p-2.5 rounded-xl bg-surface-muted/60 border border-border text-text-main text-[11px] leading-relaxed">
                            <?php echo e($invoice->customer->address ?? '-'); ?>

                        </p>
                    </div>
                </div>
            </div>

            <!-- SERVICE & INTERNET PACKAGE CARD -->
            <div class="bg-surface border border-border rounded-2xl p-5 shadow-2xs space-y-3">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Paket Layanan</span>
                    <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-[10px] font-bold text-text-muted uppercase tracking-wider">Nama Paket</span>
                        <p class="font-bold text-text-main text-sm mt-0.5">
                            <?php echo e($invoice->customerService->package_name_snapshot ?? $invoice->internetPackage->name ?? 'Paket Internet ISP'); ?>

                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <div class="p-2.5 rounded-xl bg-surface-muted/60 border border-border">
                            <span class="text-[10px] text-text-muted block">Speed DL / UL</span>
                            <span class="font-mono font-bold text-text-main text-xs">
                                <?php echo e($invoice->customerService->download_speed_snapshot ?? '-'); ?> / <?php echo e($invoice->customerService->upload_speed_snapshot ?? '-'); ?>

                            </span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-surface-muted/60 border border-border">
                            <span class="text-[10px] text-text-muted block">Harga Langganan</span>
                            <span class="font-mono font-bold text-text-main text-xs">
                                Rp <?php echo e(number_format((float) ($invoice->customerService->monthly_price ?? $invoice->subtotal), 0, ',', '.')); ?>

                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- STICKY MOBILE ACTION BAR (FOR MOBILE USERS) -->
<div class="fixed bottom-0 inset-x-0 z-30 bg-surface/90 backdrop-blur-md border-t border-border p-3 flex items-center gap-2 sm:hidden no-print mobile-action-bar">
    <?php if($customerPhone): ?>
        <a href="<?php echo e($waUrl); ?>" target="_blank" class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 rounded-xl border border-emerald-200 dark:border-emerald-800 flex items-center justify-center shrink-0" title="Kirim WA">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.105 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
        </a>
    <?php endif; ?>

    <?php if(auth()->user()->hasPermission('create_payments') && !in_array($invoice->invoice_status->value, ['lunas', 'batal'], true)): ?>
        <a href="<?php echo e(route('invoices.payments.create', $invoice->id)); ?>" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            <span><?php echo e($invoice->invoice_status->value === 'sebagian' ? 'Bayar Cicil' : 'Bayar Tagihan'); ?></span>
        </a>
    <?php endif; ?>
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
                btn.className = 'px-4 py-2.5 rounded-xl text-xs font-bold bg-surface text-primary shadow-xs flex items-center gap-2 transition-all cursor-pointer shrink-0';
                pane.classList.remove('hidden');
            } else {
                btn.className = 'px-4 py-2.5 rounded-xl text-xs font-medium text-text-muted hover:text-text-main flex items-center gap-2 transition-all cursor-pointer shrink-0';
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
        if (window.Toast) {
            window.Toast.success('Disalin', `${label} (${text}) berhasil disalin!`);
        }
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/invoices/show.blade.php ENDPATH**/ ?>