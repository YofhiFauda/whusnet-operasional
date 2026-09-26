

<?php ($isPdf = $isPdf ?? false); ?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Kwitansi — <?php echo e($kwitansi['nomor']); ?></title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            color: #000000;
            <?php if($isPdf): ?>
                padding: 0;
                background: #ffffff;
            <?php else: ?>
                padding: 16px;
                background: #f4f4f4;
            <?php endif; ?>
        }

        .toolbar {
            max-width: 700px;
            margin: 0 auto 16px;
            text-align: center;
        }

        .toolbar button,
        .toolbar a {
            display: inline-block;

            padding: 8px 18px;

            font: inherit;
            font-size: 13px;
            font-weight: 700;

            border: 1px solid #0284c7;
            border-radius: 6px;

            background: #0284c7;
            color: #ffffff;

            cursor: pointer;
            text-decoration: none;
        }

        .toolbar a {
            background: #ffffff;
            color: #000000;
            border-color: #cbd5e1;
            margin-left: 6px;
        }

        .sheet-frame {
            background: #ffffff;
            width: 100%;
            <?php if($isPdf): ?>
                max-width: 100%;
                
                padding: 4mm 4mm;
            <?php else: ?>
                max-width: 700px;
                margin: 0 auto;
                padding: 24px;
            <?php endif; ?>
        }

        /* Kertas NCR 2-ply — cuma jalur cetak fisik staf beneran nge-print
           (browser Ctrl+P/tombol Cetak, medianya `print`; PDF/iframe Portal
           lihat docblock $isPdf di atas). Persegi panjang landscape ~16:9
           (lebar > tinggi, dikonfirmasi user), sama seperti bentuk fisik
           continuous form 1/2 part-nya — lihat `ReceiptPaperSize`. */
        @media print {
            <?php if(! $isPdf): ?>
                @page {
                    size: <?php echo e(\App\Services\Receipts\ReceiptPaperSize::WIDTH_IN); ?>in <?php echo e(\App\Services\Receipts\ReceiptPaperSize::HEIGHT_IN); ?>in;
                    margin: 0;
                }

                .toolbar {
                    display: none !important;
                }

                body {
                    background: #ffffff !important;
                    padding: 0 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                .sheet-frame {
                    max-width: 100%;
                    padding: 4mm 4mm;
                }
            <?php endif; ?>
        }
    </style>
</head>

<body>

    <?php if (! ($isPdf)): ?>
        <div class="toolbar">
            <button type="button" onclick="window.print()">
                Cetak Kwitansi
            </button>

            <a href="<?php echo e(route('payments.show', $payment->id)); ?>">
                Detail Pembayaran
            </a>
        </div>
    <?php endif; ?>

    <div class="sheet-frame">
        <?php echo $__env->make('payments.partials.kwitansi', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>

</body>

</html>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/payments/receipt.blade.php ENDPATH**/ ?>