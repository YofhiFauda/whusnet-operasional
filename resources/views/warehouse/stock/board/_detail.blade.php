{{--
    Payload drawer rincian + URL aksi untuk SATU (gudang, barang) — dikeluarkan
    sebagai JSON mentah. Satu sumber untuk baris tabel (Task 5) dan sel matriks
    (Task 6): pemanggil merender partial ini, men-decode-nya untuk menu aksi
    server-side, dan menaruh string yang sama di `data-stock-detail` pemicu
    drawer. Aturan aksi ditulis di sini SAJA supaya baris, footer drawer, dan
    sel matriks tidak bisa menyimpang.

    Input: $pop, $row (baris WarehouseStockBoardService::build()), $canAdjust,
    $canTransfer, $canIssue, $canRequestStock.

    Aturan (spec §6, hasil audit form tujuan):
    - Transfer hanya dari gudang pusat; Minta Stok & Serah Teknisi hanya dari
      cabang; Opname/Sesuaikan & Atur Ambang butuh izin adjustment.
    - `lot_no` ikut di URL HANYA untuk QUANTITY satu harga. Form Opname mencari
      saldo `where lot_no = query('lot_no', '')` — tanpa lot_no yang tepat,
      opname barang yang seluruh stoknya di lot non-'' akan membaca saldo 0
      dan "menyesuaikan" stok yang salah.
    - QUANTITY dua harga: Opname baris utama = null (tidak tahu tingkat mana
      yang dimaksud); Opname tersedia per tingkat di priceTiers[].opnameUrl.
      Aksi lain tanpa lot_no — formnya memilih sendiri dari available-stock.
    - SERIALIZED/ROLL: tanpa lot_no.

    Semua URL dari route(); klien hanya memilih, tidak merakit (ADHOC-20).
--}}
@php
    $item = $row['item'];
    $tracking = $row['tracking'];
    $isQuantity = $tracking === \App\Enums\TrackingType::QUANTITY;
    $isPusat = $pop->isPusat();
    // Ditulis eksplisit (bukan `! $isPusat`), sama dengan tampilan lama: tipe
    // gudang baru kelak tidak otomatis mewarisi aksi milik cabang.
    $isCabang = $pop->type === 'cabang';
    $base = ['pop_id' => $pop->id, 'item_id' => $item->id];

    $hasTwoPrices = $isQuantity && count($row['price_tiers']) > 1;
    $singleLot = $isQuantity && ! $hasTwoPrices ? $row['lot_no'] : null;
    $params = $singleLot !== null ? $base + ['lot_no' => $singleLot] : $base;
    $opnameUrl = fn (array $query) => $canAdjust ? route('warehouse.adjustments.balance.create', $query) : null;

    // Dua tingkat harga: yang lebih lama = lot_no '' (atau yang pertama), sisanya
    // harga baru — ADHOC-75 membatasi maksimal 2 per barang. Istilah "lot" tidak
    // pernah tampil; pengguna mengenalnya sebagai harga lama/baru.
    $priceTiers = [];
    if ($hasTwoPrices) {
        $tiers = collect($row['price_tiers']);
        $old = $tiers->firstWhere('lot_no', '') ?? $tiers->first();
        $ordered = collect([$old])->merge($tiers->reject(fn ($tier) => $tier === $old));
        foreach ($ordered->values() as $index => $tier) {
            $priceTiers[] = [
                'label' => $index === 0 ? 'Harga lama' : 'Harga baru',
                'lotNo' => $tier['lot_no'],
                'qty' => (float) $tier['qty'],
                'price' => $tier['price'] !== null ? (float) $tier['price'] : null,
                'opnameUrl' => $opnameUrl($base + ['lot_no' => $tier['lot_no']]),
            ];
        }
    } elseif ($singleLot !== null) {
        // Satu harga: harga dari `row.price` (last-cost RECEIVE). Null bila
        // ledger tidak punya harga — drawer menulis "—" dan tidak menghitung
        // Nilai, bukan menebak.
        $priceTiers[] = [
            'label' => 'Harga',
            'lotNo' => $singleLot,
            'qty' => (float) $row['qty'],
            'price' => isset($row['price']) ? (float) $row['price'] : null,
            'opnameUrl' => $opnameUrl($params),
        ];
    }

    // Ringkasan kondisi SN — label mengikuti x-warehouse.condition-badge
    // (used_good dipecah sudah/belum dicek); null diperlakukan Baru, sama
    // dengan InventorySerial::isReadyForIssue().
    $conditionLabel = $tracking === \App\Enums\TrackingType::SERIALIZED
        ? collect($row['condition_summary'])->map(function (array $entry) {
            $value = $entry['condition'] instanceof \App\Enums\ItemCondition ? $entry['condition']->value : $entry['condition'];
            $label = match (true) {
                $value === \App\Enums\ItemCondition::USED_DAMAGED->value => 'Bekas Rusak',
                $value === \App\Enums\ItemCondition::USED_GOOD->value => $entry['checked'] ? 'Bekas Sudah Dicek' : 'Bekas Belum Dicek',
                default => 'Baru',
            };

            return ['label' => $label, 'count' => $entry['count']];
        })->groupBy('label')->map(fn ($rows, $label) => $rows->sum('count').' '.$label)->values()->implode(' · ')
        : '';

    $detail = [
        'popId' => (int) $pop->id,
        'itemId' => (int) $item->id,
        'itemName' => $item->name,
        'itemCode' => $item->code,
        'popName' => $pop->name,
        'popType' => $pop->type,
        'categoryName' => $item->category?->name,
        'tracking' => $tracking->value,
        'qty' => (float) $row['qty'],
        'unit' => $row['unit'],
        'rollCount' => $row['roll_count'],
        'isLow' => (bool) $row['is_low'],
        'conditionLabel' => $conditionLabel,
        'serialsUrl' => $tracking === \App\Enums\TrackingType::SERIALIZED ? route('warehouse.stock.serials', $base) : null,
        'rollsUrl' => $tracking === \App\Enums\TrackingType::ROLL ? route('warehouse.stock.rolls', $base) : null,
        'priceTiers' => $priceTiers,
        'actions' => [
            'transferUrl' => $canTransfer && $isPusat ? route('warehouse.transfers.create', $params) : null,
            'requestStockUrl' => $canRequestStock && $isCabang ? route('warehouse.stock-requests.create', $params) : null,
            'issueUrl' => $canIssue && $isCabang ? route('warehouse.issues.create', $params) : null,
            'adjustUrl' => $hasTwoPrices ? null : $opnameUrl($params),
            'thresholdUrl' => $canAdjust ? route('warehouse.stock.threshold.create', $params) : null,
        ],
        // Tanda untuk menu: Opname ada, tapi hanya per tingkat harga di drawer.
        'opnamePerTier' => $hasTwoPrices && $canAdjust,
    ];
@endphp
{!! json_encode($detail, JSON_THROW_ON_ERROR) !!}
