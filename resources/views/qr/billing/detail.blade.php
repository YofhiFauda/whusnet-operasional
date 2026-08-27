{{--
    Fungsi A — langkah 2, SUDAH lolos verifikasi PIN/4-digit HP
    (docs/plan/qr-code/rancangan-qr-pelanggan-final.md §6.1). Fase 2 TANPA
    gateway — tombol "Bayar" jadi rekening+salin+WhatsApp admin (§0), bukan
    QRIS dinamis (itu Fase 4, DITAHAN sampai perintah resmi).
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Tagihan {{ $customer->full_name }} — Whusnet</title>
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            margin: 0;
            background: #0f172a;
            color: #e2e8f0;
            padding: 20px 16px 40px;
        }

        .wrap { max-width: 480px; margin: 0 auto; }

        .head { margin-bottom: 20px; }
        .head .name { font-size: 19px; font-weight: 800; margin: 0 0 2px; }
        .head .meta { font-size: 12px; color: #94a3b8; }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 14px;
        }

        .invoice-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .invoice-head .period { font-weight: 700; font-size: 14px; }
        .invoice-head .code { font-size: 11px; color: #64748b; font-family: ui-monospace, monospace; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-lunas { background: rgba(16,185,129,.15); color: #34d399; }
        .badge-sebagian { background: rgba(245,158,11,.15); color: #fbbf24; }
        .badge-belum_dibayar { background: rgba(244,63,94,.15); color: #fb7185; }
        .badge-batal { background: rgba(100,116,139,.2); color: #94a3b8; }

        .amounts { font-size: 13px; color: #cbd5e1; margin-top: 8px; }
        .amounts .remaining { font-weight: 800; color: #f1f5f9; font-size: 16px; margin-top: 4px; }

        .pay-box { margin-top: 12px; padding: 12px; border-radius: 8px; background: #0f172a; border: 1px dashed #334155; }
        .pay-box .row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; margin-bottom: 6px; }
        .pay-box .row:last-child { margin-bottom: 0; }
        .pay-box button {
            font: inherit; font-size: 11px; font-weight: 700;
            padding: 4px 10px; border-radius: 6px; border: 1px solid #0284c7;
            background: transparent; color: #38bdf8; cursor: pointer;
        }

        .actions { display: flex; gap: 8px; margin-top: 12px; }
        .actions a {
            flex: 1; text-align: center; padding: 10px; border-radius: 8px;
            font-size: 13px; font-weight: 700; text-decoration: none;
        }
        .actions .wa { background: #16a34a; color: #fff; }
        .actions .empty { color: #64748b; font-size: 13px; text-align: center; padding: 20px 0; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <p class="name">{{ $customer->full_name }}</p>
        <p class="meta">{{ $customer->display_id }} · {{ $customer->address }}</p>
    </div>

    @forelse ($invoices as $invoice)
        @php
            $badgeClass = 'badge-'.$invoice->invoice_status->value;
            $remaining = (float) $invoice->remaining_amount;
        @endphp
        <div class="card">
            <div class="invoice-head">
                <div>
                    <div class="period">{{ $invoice->billing_period }}</div>
                    <div class="code">{{ $invoice->invoice_number }}</div>
                </div>
                <span class="badge {{ $badgeClass }}">{{ $invoice->invoice_status->label() }}</span>
            </div>

            <div class="amounts">
                Total: @rupiah($invoice->total_amount) · Terbayar: @rupiah($invoice->paid_amount)
                @if ($remaining > 0)
                    <div class="remaining">Sisa: @rupiah($invoice->remaining_amount)</div>
                @endif
            </div>

            @if ($remaining > 0)
                <div class="pay-box">
                    @if ($bankAccount['account_number'])
                        <div class="row">
                            <span>{{ $bankAccount['bank_name'] }} — {{ $bankAccount['account_number'] }}</span>
                            <button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $bankAccount['account_number'] }}')">Salin</button>
                        </div>
                        <div class="row"><span>a/n {{ $bankAccount['account_holder'] }}</span></div>
                    @else
                        <div class="row"><span>Hubungi admin untuk info rekening pembayaran.</span></div>
                    @endif
                </div>
            @endif
        </div>
    @empty
        <p class="empty">Belum ada tagihan.</p>
    @endforelse

    @if ($waUrl)
        <div class="actions">
            <a class="wa" href="{{ $waUrl }}" target="_blank" rel="noopener">Hubungi Admin via WhatsApp</a>
        </div>
    @endif
</div>
</body>
</html>
