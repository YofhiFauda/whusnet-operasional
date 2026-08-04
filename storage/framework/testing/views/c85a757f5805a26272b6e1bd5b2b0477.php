<?php $__env->startSection('title', 'Input Pembayaran - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Input Pembayaran'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <nav class="flex text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider gap-2 mb-2">
            <a href="<?php echo e(route('invoices.index')); ?>" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Daftar Tagihan</a>
            <span>/</span>
            <a href="<?php echo e(route('invoices.show', $invoice->id)); ?>" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors"><?php echo e($invoice->invoice_number); ?></a>
            <span>/</span>
            <span class="text-slate-600 dark:text-slate-400">Input Pembayaran</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">Input Pembayaran <?php echo e($invoice->invoice_number); ?></h1>
    </div>
    <a href="<?php echo e(route('invoices.show', $invoice->id)); ?>" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
        Kembali
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">Form Pembayaran</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Pembayaran otomatis memperbarui total terbayar, sisa tagihan, dan status invoice.</p>
        </div>

        <form action="<?php echo e(route('invoices.payments.store', $invoice->id)); ?>" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
            <?php echo csrf_field(); ?>

            

            <div>
                <label for="payment_date" class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Tanggal Bayar</label>
                <input type="date" name="payment_date" id="payment_date" value="<?php echo e(old('payment_date', now()->format('Y-m-d'))); ?>" required
                       class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
            </div>

            <div>
                <label for="payment_method" class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Metode Bayar</label>
                <select name="payment_method" id="payment_method" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                    <?php $__currentLoopData = ['cash' => 'Cash', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'lainnya' => 'Lainnya']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($value); ?>" <?php if(old('payment_method') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>

            <div>
                <label for="amount" class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Nominal Diterima dari Pelanggan</label>
                <input type="number" name="amount" id="amount" value="<?php echo e(old('amount', (float) $invoice->remaining_amount)); ?>" min="1" step="0.01" required
                       class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">
                    Sisa tagihan: Rp <?php echo e(number_format((float) $invoice->remaining_amount, 2, ',', '.')); ?>. Boleh diisi lebih besar — kelebihannya otomatis tercatat sebagai lebih bayar, tidak perlu dihitung manual.
                </p>

                
                <p id="installment-hint" class="hidden text-[10px] font-semibold text-amber-700 dark:text-amber-400 mt-1.5 px-2 py-1.5 rounded bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20"></p>
                <p id="settle-hint" class="hidden text-[10px] font-semibold text-emerald-700 dark:text-emerald-400 mt-1.5 px-2 py-1.5 rounded bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20"></p>
                <p id="overpay-hint" class="hidden text-[10px] font-semibold text-sky-700 dark:text-sky-400 mt-1.5 px-2 py-1.5 rounded bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20"></p>
            </div>

            <div>
                <label for="proof_file" class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Bukti Pembayaran</label>
                <input type="file" name="proof_file" id="proof_file" accept=".jpg,.jpeg,.png,.pdf" capture="environment"
                       class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-slate-100 dark:file:bg-slate-700 file:text-slate-700 dark:file:text-slate-200 hover:file:bg-slate-200 dark:hover:file:bg-slate-600">
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">Opsional. Format: JPG, PNG, atau PDF maksimal 2 MB.</p>
            </div>

            <div>
                <label for="note" class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Catatan</label>
                <textarea name="note" id="note" rows="3" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"><?php echo e(old('note')); ?></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100 dark:border-slate-800">
                <a href="<?php echo e(route('invoices.show', $invoice->id)); ?>" class="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 font-semibold rounded-md shadow-sm transition-colors text-xs">
                    Batal
                </a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-md shadow-sm transition-colors text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/25">
                    Simpan Pembayaran
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg p-6 h-fit">
        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider mb-4">Ringkasan Tagihan</h2>
        <div class="space-y-3 text-sm">
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pelanggan</p>
                <p class="font-semibold text-slate-900 dark:text-slate-100 mt-1"><?php echo e($invoice->customer->full_name ?? '-'); ?></p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">POP / Cabang</p>
                <p class="text-slate-900 dark:text-slate-200 mt-1"><?php echo e($invoice->pop->name ?? '-'); ?></p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">No. Tagihan</p>
                <p class="font-mono text-slate-900 dark:text-slate-200 mt-1"><?php echo e($invoice->invoice_number); ?></p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Periode</p>
                <p class="font-mono text-slate-900 dark:text-slate-200 mt-1"><?php echo e($invoice->billing_period); ?></p>
            </div>

            
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">Rincian Biaya</p>

                <div class="flex justify-between gap-2 text-slate-600 dark:text-slate-400">
                    <span>Harga Paket</span>
                    <span class="font-mono">Rp <?php echo e(number_format((float)$invoice->subtotal, 0, ',', '.')); ?></span>
                </div>

                <?php if((float)$invoice->discount > 0): ?>
                <div class="flex justify-between gap-2 text-green-600 dark:text-emerald-400">
                    <span>Diskon</span>
                    <span class="font-mono">- Rp <?php echo e(number_format((float)$invoice->discount, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php
                    $ppnRate   = (float)$invoice->ppn;
                    $ppnBase   = max(0, (float)$invoice->subtotal - (float)$invoice->discount);
                    $ppnAmount = round($ppnBase * ($ppnRate / 100), 2);
                ?>
                <?php if($ppnRate > 0): ?>
                <div class="flex justify-between gap-2 text-slate-600 dark:text-slate-400">
                    <span>PPN (<?php echo e(number_format($ppnRate, 0)); ?>%)</span>
                    <span class="font-mono">Rp <?php echo e(number_format($ppnAmount, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->prorate_amount ?? 0) > 0): ?>
                <div class="flex justify-between gap-2 text-slate-600 dark:text-slate-400">
                    <span>Prorate</span>
                    <span class="font-mono">Rp <?php echo e(number_format((float)$invoice->prorate_amount, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->extra_cable_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-2 text-slate-600 dark:text-slate-400">
                    <span>Kabel Tambahan</span>
                    <span class="font-mono">Rp <?php echo e(number_format((float)$invoice->extra_cable_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->other_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-2 text-slate-600 dark:text-slate-400">
                    <span>Biaya Lain-lain</span>
                    <span class="font-mono">Rp <?php echo e(number_format((float)$invoice->other_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->extra_installation_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-2 text-slate-600 dark:text-slate-400">
                    <span>Jasa Instalasi</span>
                    <span class="font-mono">Rp <?php echo e(number_format((float)$invoice->extra_installation_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <?php if((float)($invoice->extra_pole_fee ?? 0) > 0): ?>
                <div class="flex justify-between gap-2 text-slate-600 dark:text-slate-400">
                    <span>Tambahan Tiang</span>
                    <span class="font-mono">Rp <?php echo e(number_format((float)$invoice->extra_pole_fee, 0, ',', '.')); ?></span>
                </div>
                <?php endif; ?>

                <div class="flex justify-between gap-2 pt-2 border-t border-slate-200 dark:border-slate-800 font-bold text-slate-900 dark:text-slate-100">
                    <span>Total</span>
                    <span class="font-mono">Rp <?php echo e(number_format((float) $invoice->total_amount, 0, ',', '.')); ?></span>
                </div>
                <div class="flex justify-between gap-2 text-emerald-600 dark:text-emerald-400">
                    <span>Terbayar</span>
                    <span class="font-mono">Rp <?php echo e(number_format((float) $invoice->paid_amount, 0, ',', '.')); ?></span>
                </div>
                <div class="flex justify-between gap-2 font-bold <?php echo e((float)$invoice->remaining_amount > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'); ?>">
                    <span>Sisa</span>
                    <span class="font-mono">Rp <?php echo e(number_format((float) $invoice->remaining_amount, 0, ',', '.')); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const remaining = <?php echo e((float) $invoice->remaining_amount); ?>;
        const nextInstallment = <?php echo e((int) $nextInstallmentNumber); ?>;
        const amountInput = document.getElementById('amount');
        const installmentHint = document.getElementById('installment-hint');
        const settleHint = document.getElementById('settle-hint');
        const overpayHint = document.getElementById('overpay-hint');

        function formatRupiah(value) {
            return 'Rp ' + value.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }

        function refreshHint() {
            const amount = parseFloat(amountInput.value);
            installmentHint.classList.add('hidden');
            settleHint.classList.add('hidden');
            overpayHint.classList.add('hidden');

            if (isNaN(amount) || amount <= 0) {
                return;
            }

            if (amount > remaining) {
                const overpay = Math.round((amount - remaining) * 100) / 100;
                overpayHint.textContent =
                    formatRupiah(remaining) + ' diterapkan ke tagihan (Lunas), ' +
                    formatRupiah(overpay) + ' tercatat sebagai lebih bayar.';
                overpayHint.classList.remove('hidden');
                return;
            }

            const leftover = Math.round((remaining - amount) * 100) / 100;

            if (leftover > 0) {
                installmentHint.textContent =
                    'Tercatat sebagai Cicilan Ke-' + nextInstallment +
                    '. Tagihan jadi berstatus Sebagian, sisa setelah ini: ' + formatRupiah(leftover) + '.';
                installmentHint.classList.remove('hidden');
            } else {
                settleHint.textContent = 'Pembayaran ini melunasi tagihan. Status jadi Lunas.';
                settleHint.classList.remove('hidden');
            }
        }

        amountInput.addEventListener('input', refreshHint);
        refreshHint();
    })();
</script>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/payments/create.blade.php ENDPATH**/ ?>