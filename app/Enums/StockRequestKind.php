<?php

namespace App\Enums;

/**
 * Jenis `stock_requests.request_kind`. Operasional = permintaan stok Cabang yang
 * menunggu Pusat; Marketing = permintaan merchandise yang langsung terpenuhi
 * dalam satu transaksi (lihat MarketingMerchandiseService) dan tidak boleh
 * muncul di antrean Pusat.
 */
enum StockRequestKind: string
{
    case OPERATIONAL = 'operational';
    case MARKETING = 'marketing';
}
