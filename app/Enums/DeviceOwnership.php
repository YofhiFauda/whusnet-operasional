<?php

namespace App\Enums;

/**
 * Siapa pemilik fisik unit SERIALIZED (`inventory_serials.ownership`).
 * `COMPANY` = aset gudang yang dipinjamkan ke pelanggan (Sewa). `CUSTOMER` =
 * sudah dijual ke pelanggan (Beli dari Gudang) dan tak lagi masuk aset
 * perusahaan. Beli Sendiri tidak punya baris di `inventory_serials` sama sekali.
 */
enum DeviceOwnership: string
{
    case COMPANY = 'company';

    case CUSTOMER = 'customer';
}
