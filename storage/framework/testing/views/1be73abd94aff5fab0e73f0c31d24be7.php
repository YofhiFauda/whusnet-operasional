
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk Pembayaran <?php echo e($payment->payment_number); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            background: #f1f5f9;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            color: #0f172a;
        }
        .struk {
            width: 80mm;
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 14px 16px 18px;
            border: 1px solid #e2e8f0;
        }
        .center { text-align: center; }
        .brand { font-size: 15px; font-weight: 700; letter-spacing: .05em; }
        .muted { color: #64748b; }
        .sep { border-top: 1px dashed #94a3b8; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        td.val { text-align: right; font-weight: 600; }
        .total td { padding-top: 6px; font-size: 14px; font-weight: 700; }
        .actions { width: 80mm; max-width: 100%; margin: 12px auto 0; text-align: center; }
        .actions button, .actions a {
            display: inline-block;
            padding: 8px 14px;
            font: inherit;
            font-weight: 700;
            border-radius: 6px;
            border: 1px solid #0284c7;
            background: #0284c7;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
        }
        .actions a { background: #fff; color: #0f172a; border-color: #cbd5e1; margin-left: 6px; }
        @media print {
            body { background: #fff; padding: 0; }
            .struk { border: 0; padding: 0; width: auto; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="struk">
        <div class="center">
            <div class="brand">WHUSNET</div>
            <div class="muted"><?php echo e($payment->pop->name ?? 'Kantor Pusat'); ?></div>
            <div class="muted">STRUK PEMBAYARAN</div>
        </div>

        <div class="sep"></div>

        <table>
            <tr>
                <td class="muted">No. Struk</td>
                <td class="val"><?php echo e($payment->payment_number); ?></td>
            </tr>
            <tr>
                <td class="muted">Tanggal</td>
                <td class="val"><?php echo e(optional($payment->payment_date)->format('d/m/Y')); ?></td>
            </tr>
            <tr>
                <td class="muted">Metode</td>
                <td class="val"><?php echo e(strtoupper($payment->payment_method)); ?></td>
            </tr>
            <tr>
                <td class="muted">Status</td>
                <td class="val"><?php echo e($payment->payment_status->label()); ?></td>
            </tr>
        </table>

        <div class="sep"></div>

        <table>
            <tr>
                <td class="muted">Pelanggan</td>
                <td class="val"><?php echo e($payment->customer->full_name ?? '-'); ?></td>
            </tr>
            <tr>
                <td class="muted">CID</td>
                <td class="val"><?php echo e($payment->customer?->cid ?: ($payment->customer?->customer_code ?: '-')); ?></td>
            </tr>
            <tr>
                <td class="muted">No. Tagihan</td>
                <td class="val"><?php echo e($payment->invoice->invoice_number ?? '-'); ?></td>
            </tr>
            <?php if($payment->invoice): ?>
            <tr>
                <td class="muted">Periode</td>
                <td class="val"><?php echo e($payment->invoice->billing_period ?: '-'); ?></td>
            </tr>
            <tr>
                <td class="muted">Paket</td>
                <td class="val"><?php echo e($payment->invoice->internetPackage->name ?? '-'); ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <div class="sep"></div>

        <table>
            <?php if($payment->invoice): ?>
            <tr>
                <td class="muted">Total Tagihan</td>
                <td class="val">Rp <?php echo e(number_format((float) $payment->invoice->total_amount, 0, ',', '.')); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total">
                <td>DIBAYAR</td>
                <td class="val">Rp <?php echo e(number_format((float) $payment->amount, 0, ',', '.')); ?></td>
            </tr>
            <?php if($payment->invoice): ?>
            <tr>
                <td class="muted">Sisa Tagihan</td>
                <td class="val">Rp <?php echo e(number_format((float) $payment->invoice->remaining_amount, 0, ',', '.')); ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <div class="sep"></div>

        <table>
            <tr>
                <td class="muted">Diterima oleh</td>
                <td class="val"><?php echo e($payment->receiver->name ?? '-'); ?></td>
            </tr>
            <?php if($payment->note): ?>
            <tr>
                <td class="muted">Catatan</td>
                <td class="val"><?php echo e($payment->note); ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <div class="sep"></div>

        <div class="center muted">
            Struk sah tanpa tanda tangan.<br>
            Dicetak <?php echo e(now()->format('d/m/Y H:i')); ?>

        </div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Cetak Struk</button>
        <a href="<?php echo e(route('payments.show', $payment->id)); ?>">Detail Pembayaran</a>
    </div>
</body>
</html>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/payments/receipt.blade.php ENDPATH**/ ?>