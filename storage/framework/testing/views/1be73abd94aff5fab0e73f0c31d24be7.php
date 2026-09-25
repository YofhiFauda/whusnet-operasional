

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
            padding: 16px;
            background: #f4f4f4;
            color: #000000;
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
            max-width: 700px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Kertas NCR 2-ply — cuma jalur cetak fisik staf (bukan PDF/iframe
           Portal). Lihat docblock $isPdf di atas. */
        @media print {
            <?php if(! $isPdf): ?>
                @page {
                    size: 9.5in 5.5in;
                    margin: 0;
                }
            <?php endif; ?>

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
                padding: 6mm 5mm;
            }
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