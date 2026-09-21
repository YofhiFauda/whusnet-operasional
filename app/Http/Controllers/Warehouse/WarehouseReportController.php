<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\InventoryAdjustmentService;
use App\Services\WarehouseStockAsOfService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Laporan Gudang — agregat periodik (Fase 2 P2, fase-2-adaptasi-wms.md).
 * Read-only murni, reuse data yang UDAH tercatat di `inventory_transactions`
 * — cuma nyusun ulang jadi angka per periode, BUKAN sumber kebenaran baru.
 *
 * Realtime SENGAJA SKIP (keputusan sadar Fase 1, rancangan-ui.md §2.1) —
 * volume transaksi gudang ISP lokal gak sebanding kompleksitas broadcast
 * channel tambahan. Reload manual cukup, sama pola Dashboard Gudang.
 *
 * SATU halaman, DUA tab (movement + adjustment) — bukan dua route terpisah,
 * biar filter periode/POP-nya konsisten satu form buat dua-duanya.
 */
class WarehouseReportController extends Controller
{
    public function index(Request $request, EffectiveAccessService $access, WarehouseStockAsOfService $stockAsOf): View
    {
        $user = auth()->user();

        $pops = Pop::query()
            ->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $popIds = $pops->pluck('id');

        $period = $request->query('period') ?: now()->format('Y-m');
        $popFilter = $request->integer('pop_id') ?: null;

        $periodStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $movementRows = $this->buildMovementSummary($popIds, $popFilter, $periodStart, $periodEnd, $stockAsOf);
        $adjustmentRows = $this->buildAdjustmentSummary($popIds, $popFilter, $periodStart, $periodEnd);
        $movementCounts = $this->buildMovementCounts($popIds, $popFilter, $periodStart, $periodEnd);

        // Total Nilai Kerugian (ADHOC-79) — cuma RUSAK+HILANG yang dihitung
        // (keputusan eksplisit user 2026-09-17), reason lain (quarantine/
        // shrinkage_on_return/stock_opname_diff/scrapped) BUKAN klaim
        // kerugian finansial dalam lingkup task ini, `loss_value`-nya null.
        $totalLossValue = collect($adjustmentRows)->sum(fn ($row) => $row['loss_value'] ?? 0);

        // KPI ringkasan periode — SENGAJA hitung JUMLAH TRANSAKSI, bukan
        // SUM(qty), dengan alasan yang sama kayak di atas: campur item beda
        // satuan gak valid dijumlah jadi satu angka headline.
        $scopedPops = $popFilter ? collect([$popFilter])->intersect($popIds) : $popIds;
        $kpi = [
            'receive_count' => InventoryTransaction::query()->where('type', InventoryTransactionType::RECEIVE->value)
                ->whereIn('to_pop_id', $scopedPops)->whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'transfer_out_count' => InventoryTransaction::query()->where('type', InventoryTransactionType::TRANSFER->value)
                ->whereIn('from_pop_id', $scopedPops)->whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'issue_count' => InventoryTransaction::query()->where('type', InventoryTransactionType::ISSUE->value)
                ->whereIn('from_pop_id', $scopedPops)->whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'adjustment_count' => collect($adjustmentRows)->sum('count'),
            'total_loss_value' => $totalLossValue,
        ];

        // Chart Kerugian: JUMLAH KEJADIAN per lokasi per kategori — turunan
        // dari $adjustmentRows (yang granularitasnya per-item), aman dijumlah
        // ulang lintas item di sini karena metriknya COUNT, bukan qty.
        $lossChartData = collect($adjustmentRows)
            ->groupBy('pop_label')
            ->map(function ($rows, $popLabel) {
                $byReason = $rows->groupBy('reason')->map(fn ($g) => $g->sum('count'));

                return array_merge(['pop_label' => $popLabel], $byReason->all());
            })
            ->values()
            ->all();

        return view('warehouse.reports.index', compact(
            'pops', 'period', 'popFilter', 'movementRows', 'adjustmentRows', 'movementCounts', 'kpi', 'lossChartData'
        ));
    }

    /**
     * Download Excel (ADHOC-79, direvisi 2026-09-17 — laporan DI-PLOT PER
     * POP, satu SHEET per POP, bukan satu sheet flat gabungan semua gudang.
     * Niru cara baca laporan manual lama (`docs/plan/warehouse/laporan/
     * laporan_admin_gudang_per_pop.md`) — satu blok/tabel per cabang, baris
     * TOTAL di bawahnya, SEKARANG termasuk **Stok Awal & Stok Akhir**
     * (ronde ke-3 klarifikasi: ini "Laporan Akhir Bulan", wajib cover
     * SEMUA tipe barang, bukan cuma pergerakan dalam periode).
     *
     * Stok Awal/Akhir dihitung `WarehouseStockAsOfService::balanceAsOf()` —
     * replay ledger per-tanggal (Stok Awal = saldo SEBELUM periode mulai,
     * Stok Akhir = saldo SAMPAI akhir periode), bukan `InventoryBalance`
     * (itu cuma saldo TERKINI, gak bisa mundur ke tanggal lalu). QUANTITY
     * dapat slot Lama/Baru (2-slot harga), SERIALIZED/ROLL satu harga.
     *
     * Kolom `HUTANG` template lama TETAP belum ada — gak ada konsep utang-
     * distributor di sistem, keputusan eksplisit user buat skip dulu.
     *
     * Baris kerugian custody teknisi (`pop_id` null — barang lagi di tangan
     * teknisi, bukan gudang manapun, lihat docblock `buildAdjustmentSummary()`)
     * gak bisa diplot ke sheet POP manapun — dikumpulkan ke SATU sheet
     * terpisah "Custody Teknisi" di akhir, bukan dipaksa masuk salah satu
     * POP (nebak gudang asalnya salah lebih bahaya daripada jujur pisah).
     */
    public function export(Request $request, EffectiveAccessService $access, WarehouseStockAsOfService $stockAsOf)
    {
        $user = auth()->user();

        $pops = Pop::query()
            ->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $popIds = $pops->pluck('id');
        $popsById = $pops->keyBy('id');

        $period = $request->query('period') ?: now()->format('Y-m');
        $popFilter = $request->integer('pop_id') ?: null;
        $scopedPops = $popFilter ? collect([$popFilter])->intersect($popIds) : $popIds;

        $periodStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $nextPeriodStart = $periodStart->copy()->addMonthNoOverflow();

        $movementRows = $this->buildMovementSummary($popIds, $popFilter, $periodStart, $periodEnd);
        $adjustmentRows = $this->buildAdjustmentSummary($popIds, $popFilter, $periodStart, $periodEnd);

        $zeroBalance = ['lama' => ['qty' => 0.0, 'harga' => null], 'baru' => ['qty' => 0.0, 'harga' => null], 'nilai' => 0.0];
        $emptyItemRow = fn (string $unit) => [
            'unit' => $unit, 'issue' => 0.0,
            'qty_rusak' => 0.0, 'nilai_rusak' => 0.0, 'qty_hilang' => 0.0, 'nilai_hilang' => 0.0,
            'barang_masuk' => $zeroBalance, 'stok_awal' => $zeroBalance, 'stok_akhir' => $zeroBalance,
        ];

        // Gabung movement + kerugian (RUSAK/HILANG doang, sejalan tab
        // Kerugian di layar) jadi SATU baris per item per POP — kategori
        // lain (quarantine/scrapped/shrinkage/opname) SENGAJA gak diplot ke
        // sini (bukan nilai rugi, di luar lingkup ADHOC-79), tetap kebaca
        // lengkap di layar tab Kerugian. Item dikunci `item_id`, bukan nama
        // (nama gak unik jaminan, dan dibutuhkan buat query Barang Masuk/
        // Stok Awal/Akhir). `receive`/`transfer_in`/`transfer_out` dari
        // `buildMovementSummary()` SENGAJA gak dipakai lagi di export —
        // "Barang Masuk" versi export dihitung ulang lewat
        // `WarehouseStockAsOfService::receivedInPeriod()` (gabung RECEIVE+
        // TRANSFER-confirm jadi satu angka 2-slot Lama/Baru, niru laporan
        // manual — `Transfer Masuk`/`Transfer Keluar` TETAP ada di tab
        // Pergerakan Barang on-screen, cuma gak diplot lagi ke Excel).
        $popSheets = [];
        $custodyRows = [];

        foreach ($movementRows as $mrow) {
            $popId = $mrow['pop']->id;
            $popSheets[$popId]['pop'] = $mrow['pop'];
            foreach ($mrow['items'] as $item) {
                $popSheets[$popId]['items'][$item['item_id']] = array_merge($emptyItemRow($item['unit']), [
                    'item_name' => $item['item_name'],
                    'issue' => $item['issue'],
                ], $popSheets[$popId]['items'][$item['item_id']] ?? []);
            }
        }

        foreach ($adjustmentRows as $arow) {
            if (! in_array($arow['reason'], ['lost', 'damaged'], true)) {
                continue;
            }

            if ($arow['pop_id'] === null) {
                $custodyRows[] = $arow;

                continue;
            }

            $popId = $arow['pop_id'];
            $popSheets[$popId]['pop'] ??= $popsById->get($popId);
            $itemId = $arow['item_id'];
            $popSheets[$popId]['items'][$itemId] ??= array_merge($emptyItemRow($arow['unit']), ['item_name' => $arow['item_name']]);

            if ($arow['reason'] === 'damaged') {
                $popSheets[$popId]['items'][$itemId]['qty_rusak'] += $arow['total_qty'];
                $popSheets[$popId]['items'][$itemId]['nilai_rusak'] += $arow['loss_value'] ?? 0;
            } else {
                $popSheets[$popId]['items'][$itemId]['qty_hilang'] += $arow['total_qty'];
                $popSheets[$popId]['items'][$itemId]['nilai_hilang'] += $arow['loss_value'] ?? 0;
            }
        }

        // Item universe TAMBAHAN — barang yang gak py pergerakan/kerugian
        // BULAN INI tapi tetap py saldo (nongkrong di rak, gak disentuh)
        // tetap wajib keliatan Stok Awal/Akhirnya (ini laporan closing
        // bulanan, bukan cuma daftar aktivitas). Dicari dari SELURUH
        // riwayat ledger POP itu sampai akhir periode — bukan cuma bulan
        // ini — supaya barang idle ikut ketemu.
        $itemIdsEverTouchedByPop = [];
        foreach ($scopedPops as $popId) {
            $itemIdsEverTouchedByPop[$popId] = InventoryTransaction::query()
                ->where(fn ($q) => $q->where('to_pop_id', $popId)->orWhere('from_pop_id', $popId))
                ->where('created_at', '<', $nextPeriodStart)
                ->distinct()
                ->pluck('item_id');
        }

        // Satu batch fetch Item buat SELURUH item yang bakal disentuh
        // (movement + kerugian + idle-universe di atas) — bukan `find()`
        // berulang per item per POP.
        $allItemIds = collect($itemIdsEverTouchedByPop)->flatten()
            ->merge(collect($popSheets)->flatMap(fn ($sheet) => array_keys($sheet['items'] ?? [])))
            ->unique();
        $itemsById = Item::query()->whereIn('id', $allItemIds)->get()->keyBy('id');

        foreach ($itemIdsEverTouchedByPop as $popId => $itemIds) {
            foreach ($itemIds as $itemId) {
                if (isset($popSheets[$popId]['items'][$itemId])) {
                    continue;
                }
                $item = $itemsById->get($itemId);
                if (! $item) {
                    continue;
                }
                $popSheets[$popId]['pop'] ??= $popsById->get($popId);
                $popSheets[$popId]['items'][$itemId] = array_merge($emptyItemRow($item->unit), ['item_name' => $item->name]);
            }
        }

        // Barang Masuk/Stok Awal/Akhir dihitung buat SETIAP item yang
        // sekarang ada di tiap sheet POP (baik dari movement/kerugian bulan
        // ini maupun item idle di atas).
        foreach ($popSheets as $popId => &$sheet) {
            $pop = $sheet['pop'];
            foreach ($sheet['items'] as $itemId => &$row) {
                $item = $itemsById->get($itemId);
                if (! $item) {
                    continue;
                }
                $row['barang_masuk'] = $stockAsOf->receivedInPeriod($pop, $item, $periodStart, $nextPeriodStart);
                $row['stok_awal'] = $stockAsOf->balanceAsOf($pop, $item, $periodStart, true);
                $row['stok_akhir'] = $stockAsOf->balanceAsOf($pop, $item, $nextPeriodStart, false);
            }
            unset($row);
        }
        unset($sheet);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laporan-gudang-'.uniqid().'.xlsx';
        $writer = SimpleExcelWriter::create($path);

        $header = [
            'No', 'Nama Barang', 'Satuan',
            'Barang Masuk Qty (Awal)', 'Harga Awal Satuan (Rp)', 'Barang Masuk Qty (Baru)', 'Harga Baru Satuan (Rp)', 'Nilai Barang Masuk (Rp)',
            'Stok Awal Qty (Awal)', 'Harga Awal Satuan (Rp) ', 'Stok Awal Qty (Baru)', 'Harga Baru Satuan (Rp) ', 'Nilai Stok Awal (Rp)',
            'Stok Terpakai (ke Teknisi)',
            'Stok Akhir Qty (Awal)', 'Harga Awal Satuan (Rp)  ', 'Stok Akhir Qty (Baru)', 'Harga Baru Satuan (Rp)  ', 'Nilai Stok Akhir (Rp)',
            'Qty Rusak', 'Nilai Rugi Rusak (Rp)', 'Qty Hilang', 'Nilai Rugi Hilang (Rp)',
        ];

        // Urutan sheet ngikut urutan $pops (type lalu name) — bukan urutan
        // ketemunya di ledger, biar konsisten tiap generate.
        $isFirstSheet = true;
        foreach ($pops as $pop) {
            if (! isset($popSheets[$pop->id])) {
                continue; // POP tanpa pergerakan/kerugian/saldo apapun — gak usah bikin sheet kosong.
            }

            if (! $isFirstSheet) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $isFirstSheet = false;

            // Nama sheet Excel maks 31 karakter — dipotong, bukan error.
            $writer->nameCurrentSheet(mb_substr($pop->name, 0, 31));
            $this->writeStockReportHeader($writer);

            $no = 1;
            $totalNilaiMasuk = 0.0;
            $totalNilaiAwal = 0.0;
            $totalNilaiAkhir = 0.0;
            $totalRusak = 0.0;
            $totalHilang = 0.0;

            $blackBorder = new Border(
                new BorderPart(Border::TOP, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
                new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
                new BorderPart(Border::LEFT, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
                new BorderPart(Border::RIGHT, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            );

            $noStyle = (new Style)
                ->setCellAlignment(CellAlignment::CENTER)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);

            $dataStyle = (new Style)
                ->setBackgroundColor('FFF2CC')
                ->setFontColor('1403F3')
                ->setBorder($blackBorder)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);

            $nominalDataStyle = (new Style)
                ->setBackgroundColor('FFF2CC')
                ->setFontColor('1403F3')
                ->setBorder($blackBorder)
                ->setFormat('"Rp "#,##0')
                ->setCellAlignment(CellAlignment::RIGHT)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);

            $nominalColumns = [4, 6, 7, 9, 11, 12, 15, 17, 18, 20, 22];

            $dataRowStyles = [
                0 => $noStyle,
            ];
            for ($c = 1; $c < 23; $c++) {
                $dataRowStyles[$c] = in_array($c, $nominalColumns, true) ? $nominalDataStyle : $dataStyle;
            }

            foreach ($popSheets[$pop->id]['items'] as $itemId => $row) {
                // Barang ROLL (kabel per roll) tampil satuan ROLL di
                // Laporan Bulanan — BUKAN meter kayak tracking internal
                // (koreksi 2026-09-18, keputusan user, sejalan Invoice —
                // lihat `WarehouseTransferController::invoice()`). Semua
                // angka `qty`/`unit` di ledger SELALU meter; `nilai`/
                // `nilai_rusak`/`nilai_hilang` (Rp) TIDAK disentuh — udah
                // benar otomatis dari fix `InventoryReceiveService::receiveRoll()`
                // (harga tersimpan per-meter). Cuma presentasi qty/harga
                // yang dikonversi balik ke roll DI SINI, gak nyentuh data.
                $item = $itemsById->get($itemId);
                if ($item && $item->tracking_type === TrackingType::ROLL && (float) $item->meter_per_roll > 0) {
                    $row = $this->convertRollRowToRollUnit($row, (float) $item->meter_per_roll);
                }

                // Barang idle yang semua nilainya nol (gak pernah beneran
                // ada aktivitas/saldo di POP ini) gak usah tampil — "item
                // tanpa transaksi/saldo gak muncul, bukan baris nol"
                // (fase-2-adaptasi-wms.md P2, prinsip sama movement).
                $adaData = $row['barang_masuk']['nilai'] + $row['stok_awal']['nilai'] + $row['issue']
                    + $row['stok_akhir']['nilai'] + $row['qty_rusak'] + $row['qty_hilang'] > 0;
                if (! $adaData) {
                    continue;
                }

                $writer->addRow(Row::fromValuesWithStyles([
                    $no++,
                    $row['item_name'],
                    $row['unit'],
                    $row['barang_masuk']['lama']['qty'],
                    $row['barang_masuk']['lama']['harga'] ?? '',
                    $row['barang_masuk']['baru']['qty'],
                    $row['barang_masuk']['baru']['harga'] ?? '',
                    $row['barang_masuk']['nilai'],
                    $row['stok_awal']['lama']['qty'],
                    $row['stok_awal']['lama']['harga'] ?? '',
                    $row['stok_awal']['baru']['qty'],
                    $row['stok_awal']['baru']['harga'] ?? '',
                    $row['stok_awal']['nilai'],
                    $row['issue'],
                    $row['stok_akhir']['lama']['qty'],
                    $row['stok_akhir']['lama']['harga'] ?? '',
                    $row['stok_akhir']['baru']['qty'],
                    $row['stok_akhir']['baru']['harga'] ?? '',
                    $row['stok_akhir']['nilai'],
                    $row['qty_rusak'],
                    $row['nilai_rusak'],
                    $row['qty_hilang'],
                    $row['nilai_hilang'],
                ], null, $dataRowStyles));
                $totalNilaiMasuk += $row['barang_masuk']['nilai'];
                $totalNilaiAwal += $row['stok_awal']['nilai'];
                $totalNilaiAkhir += $row['stok_akhir']['nilai'];
                $totalRusak += $row['nilai_rusak'];
                $totalHilang += $row['nilai_hilang'];
            }

            $footerStyle = (new Style)
                ->setBackgroundColor('D9E1F2')
                ->setFontColor('1403F3')
                ->setFontBold()
                ->setBorder($blackBorder)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);

            $footerNominalStyle = (new Style)
                ->setBackgroundColor('D9E1F2')
                ->setFontColor('1403F3')
                ->setFontBold()
                ->setBorder($blackBorder)
                ->setFormat('"Rp "#,##0')
                ->setCellAlignment(CellAlignment::RIGHT)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);

            $footerStyles = [];
            for ($c = 0; $c < 23; $c++) {
                $footerStyles[$c] = in_array($c, [7, 12, 18, 20, 22], true) ? $footerNominalStyle : $footerStyle;
            }

            $writer->addRow(Row::fromValuesWithStyles([
                '', 'TOTAL', '',
                '', '', '', '', $totalNilaiMasuk,
                '', '', '', '', $totalNilaiAwal,
                '',
                '', '', '', '', $totalNilaiAkhir,
                '', $totalRusak, '', $totalHilang,
            ], $footerStyle, $footerStyles));
        }

        if (! empty($custodyRows)) {
            if (! $isFirstSheet) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $writer->nameCurrentSheet('Custody Teknisi (Tanpa POP)');
            $writer->addHeader(['Kategori', 'Barang', 'Satuan', 'Jumlah Transaksi', 'Total Qty', 'Harga Satuan Terakhir (Rp)', 'Nilai Rugi (Rp)']);
            foreach ($custodyRows as $row) {
                $writer->addRow([
                    $row['reason_label'],
                    $row['item_name'],
                    $row['unit'],
                    $row['count'],
                    $row['total_qty'],
                    $row['unit_cost'] ?? '',
                    $row['loss_value'] ?? '',
                ]);
            }
        }

        // Gak ada pergerakan/kerugian sama sekali di periode+filter ini —
        // sheet default kosongan tetap dikasih header, biar file yang
        // didownload gak nol-kolom/nol-sheet sama sekali.
        if ($isFirstSheet) {
            $writer->nameCurrentSheet('Laporan Gudang');
            $writer->addHeader($header);
        }

        $writer->close();

        $filename = 'laporan-gudang-'.$period.($popFilter ? '-pop'.$popFilter : '').'.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Header 2-baris berwarna+merge per grup kolom (ADHOC-79, revisi
     * 2026-09-18 — persis layout+warna yang dikasih user, `test1.html`).
     * Ditulis manual pakai `OpenSpout\Common\Entity\Row::fromValuesWithStyles()`
     * + `mergeCells()` (bukan `SimpleExcelWriter::addHeader()` biasa yang
     * cuma 1 baris flat) — `SimpleExcelWriter::addRow()` TERIMA `Row`
     * mentah, jadi bisa dipakai bareng tanpa fork/duplikasi writer.
     *
     * Grup kolom BUKAN replika persis template lama (Stok Beli/Hutang/Stok
     * Awal/Stok Terpakai/Stok Akhir) — HUTANG gak ada (gak ada datanya di
     * sistem, keputusan eksplisit user buat skip). "Barang Masuk" gabung
     * RECEIVE+TRANSFER-confirm jadi satu (bukan 2 kolom Transfer Masuk/
     * Keluar terpisah kayak revisi sebelumnya) — niru cara laporan manual
     * lama nyatet "Stok Beli" buat SEMUA cabang (bukan cuma Pusat yang
     * RECEIVE langsung dari distributor).
     *
     * 23 kolom (indeks 0-22) — urutan HARUS PERSIS sama dengan `addRow()`
     * data di `export()`, gak ada validasi silang otomatis kalau salah satu
     * diubah tanpa mengubah yang lain.
     */
    private function writeStockReportHeader(SimpleExcelWriter $writer): void
    {
        $white = 'FFFFFF';
        $colors = [
            'identitas' => '2B70B3',
            'barang_masuk' => '4F7928',
            'stok_awal' => 'B8860B',
            'stok_terpakai' => 'B30000',
            'stok_akhir' => '7A00CC',
            'rusak' => 'D9534F',
            'hilang' => '333333',
        ];

        $whiteBorder = new Border(
            new BorderPart(Border::TOP, Color::WHITE, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, Color::WHITE, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::LEFT, Color::WHITE, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, Color::WHITE, Border::WIDTH_THIN, Border::STYLE_SOLID),
        );

        $styleFor = function (string $group) use ($colors, $white, $whiteBorder): Style {
            $style = new Style;
            $style->setBackgroundColor($colors[$group]);
            $style->setFontColor($white);
            $style->setFontBold();
            $style->setCellAlignment(CellAlignment::CENTER);
            $style->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
            $style->setShouldWrapText();
            $style->setBorder($whiteBorder);

            return $style;
        };

        // Kolom → grup warna, buat baris 1 (label grup) DAN baris 2 (label
        // sub-kolom) — sengaja satu array dipakai dua kali, biar dua baris
        // itu gak pernah kepisah warnanya kalau kolom ditambah/diubah nanti.
        $columnGroups = [
            'identitas', 'identitas', 'identitas',
            'barang_masuk', 'barang_masuk', 'barang_masuk', 'barang_masuk', 'barang_masuk',
            'stok_awal', 'stok_awal', 'stok_awal', 'stok_awal', 'stok_awal',
            'stok_terpakai',
            'stok_akhir', 'stok_akhir', 'stok_akhir', 'stok_akhir', 'stok_akhir',
            'rusak', 'rusak',
            'hilang', 'hilang',
        ];
        $columnStyles = array_map($styleFor, $columnGroups);

        // Subtitle 2 baris (niru `<br>` template — Excel gak kenal HTML,
        // "\n" di dalam sel + wrap-text yang jadi gantinya).
        $groupLabels = [
            'No', 'Nama Barang', 'Satuan',
            'Barang Masuk', '', '', '', '',
            "STOK AWAL\n(Stok di Gudang dari Barang Beli)", '', '', '', '',
            "STOK TERPAKAI\n(Terpakai Teknisi)",
            "STOK AKHIR\n(Setelah Transfer / Diserahkan)", '', '', '', '',
            'Barang Rusak', '',
            'Hilang', '',
        ];
        $subLabels = [
            '', '', '',
            'Qty', 'Harga Awal Satuan (Rp)', 'Qty', 'Harga Baru Satuan (Rp)', 'Nilai (Rp)',
            'Qty', 'Harga Awal Satuan (Rp)', 'Qty', 'Harga Baru Satuan (Rp)', 'Nilai (Rp)',
            '',
            'Qty', 'Harga Awal Satuan (Rp)', 'Qty', 'Harga Baru Satuan (Rp)', 'Nilai (Rp)',
            'Qty', 'Nilai Rugi (Rp)',
            'Qty', 'Nilai Rugi (Rp)',
        ];

        $writer->addRow(Row::fromValuesWithStyles($groupLabels, null, $columnStyles));
        $writer->addRow(Row::fromValuesWithStyles($subLabels, null, $columnStyles));

        // mergeCells(kolomKiri, barisAtas, kolomKanan, barisBawah, sheetIndex)
        // — kolom 0-indexed, baris 1-indexed relatif ke SHEET AKTIF saat ini
        // (bukan workbook), jadi ambil index-nya SETELAH nameCurrentSheet()
        // dipanggil buat sheet ini.
        $sheetIndex = $writer->getWriter()->getCurrentSheet()->getIndex();
        $options = $writer->getWriter()->getOptions();

        // Atur lebar kolom menyesuaikan panjang nama header
        $options->setColumnWidth(6, 1);
        $options->setColumnWidth(26, 2);
        $options->setColumnWidth(10, 3);
        $options->setColumnWidth(8, 4, 6, 9, 11, 15, 17, 20, 22);
        $options->setColumnWidth(24, 5, 7, 10, 12, 16, 18);
        $options->setColumnWidth(18, 8, 13, 19, 21, 23);
        $options->setColumnWidth(20, 14);

        $rowspanColumns = [0, 1, 2, 13]; // No/Nama/Satuan/Stok Terpakai — 1 kolom, gak butuh gabung horizontal, cuma vertikal (baris 1-2).
        foreach ($rowspanColumns as $col) {
            $options->mergeCells($col, 1, $col, 2, $sheetIndex);
        }

        $colspanRanges = [
            [3, 7],   // Barang Masuk
            [8, 12],  // Stok Awal
            [14, 18], // Stok Akhir
            [19, 20], // Barang Rusak
            [21, 22], // Hilang
        ];
        foreach ($colspanRanges as [$start, $end]) {
            $options->mergeCells($start, 1, $end, 1, $sheetIndex);
        }
    }

    /**
     * Versi COUNT(*) dari movement summary — khusus buat bahan bakar bar
     * chart. Aman digabung lintas item (ngitung JUMLAH TRANSAKSI, bukan
     * qty), beda dari `buildMovementSummary()` yang qty-nya cuma valid
     * ditampilkan per-item (lihat komentar di sana).
     *
     * @param  Collection<int, int>  $popIds
     * @return array<int, array{pop_name: string, receive: int, transfer_in: int, transfer_out: int, issue: int}>
     */
    private function buildMovementCounts($popIds, ?int $popFilter, Carbon $start, Carbon $end): array
    {
        $scopedPops = $popFilter ? collect([$popFilter])->intersect($popIds) : $popIds;

        $countBy = fn (string $type, string $column) => InventoryTransaction::query()
            ->where('type', $type)
            ->whereIn($column, $scopedPops)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("{$column} as pop_id, COUNT(*) as total")
            ->groupBy($column)
            ->pluck('total', 'pop_id');

        $receive = $countBy(InventoryTransactionType::RECEIVE->value, 'to_pop_id');
        $transferIn = $countBy(InventoryTransactionType::TRANSFER->value, 'to_pop_id');
        $transferOut = $countBy(InventoryTransactionType::TRANSFER->value, 'from_pop_id');
        $issue = $countBy(InventoryTransactionType::ISSUE->value, 'from_pop_id');

        return Pop::query()
            ->whereIn('id', $scopedPops)
            ->orderBy('type')->orderBy('name')
            ->get()
            ->map(fn (Pop $pop) => [
                'pop_name' => $pop->name,
                'receive' => (int) ($receive[$pop->id] ?? 0),
                'transfer_in' => (int) ($transferIn[$pop->id] ?? 0),
                'transfer_out' => (int) ($transferOut[$pop->id] ?? 0),
                'issue' => (int) ($issue[$pop->id] ?? 0),
            ])
            ->filter(fn ($row) => $row['receive'] + $row['transfer_in'] + $row['transfer_out'] + $row['issue'] > 0)
            ->values()
            ->all();
    }

    /**
     * Agregat qty RECEIVE/TRANSFER(masuk+keluar)/ISSUE per gudang per
     * periode. Kolom `from_pop_id`/`to_pop_id` udah lengkap di SEMUA baris
     * tipe ini (beda dari ADJUSTMENT custody, lihat catatan `buildAdjustmentSummary()`)
     * — jadi atribusi per-gudang di sini akurat penuh, bukan perkiraan.
     *
     * @param  Collection<int, int>  $popIds
     * @return array<int, array{pop: Pop, receive: float, transfer_in: float, transfer_out: float, issue: float}>
     */
    private function buildMovementSummary($popIds, ?int $popFilter, Carbon $start, Carbon $end, ?WarehouseStockAsOfService $stockAsOf = null): array
    {
        $scopedPops = $popFilter ? collect([$popFilter])->intersect($popIds) : $popIds;
        $nextPeriodStart = $start->copy()->addMonthNoOverflow();

        $receive = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->whereIn('to_pop_id', $scopedPops)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('to_pop_id as pop_id, SUM(qty) as total')
            ->groupBy('to_pop_id')
            ->pluck('total', 'pop_id');

        $transferIn = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->whereIn('to_pop_id', $scopedPops)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('to_pop_id as pop_id, SUM(qty) as total')
            ->groupBy('to_pop_id')
            ->pluck('total', 'pop_id');

        $transferOut = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->whereIn('from_pop_id', $scopedPops)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('from_pop_id as pop_id, SUM(qty) as total')
            ->groupBy('from_pop_id')
            ->pluck('total', 'pop_id');

        $issue = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::ISSUE->value)
            ->whereIn('from_pop_id', $scopedPops)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('from_pop_id as pop_id, SUM(qty) as total')
            ->groupBy('from_pop_id')
            ->pluck('total', 'pop_id');

        // Rincian PER ITEM
        $itemBreakdown = function (string $type, string $direction) use ($scopedPops, $start, $end) {
            $popColumn = $direction === 'to' ? 'to_pop_id' : 'from_pop_id';

            return InventoryTransaction::query()
                ->where('type', $type)
                ->whereIn($popColumn, $scopedPops)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw("{$popColumn} as pop_id, item_id, SUM(qty) as total")
                ->groupBy($popColumn, 'item_id')
                ->get()
                ->keyBy(fn ($row) => $row->pop_id.'-'.$row->item_id);
        };

        $receiveByItem = $itemBreakdown(InventoryTransactionType::RECEIVE->value, 'to');
        $transferInByItem = $itemBreakdown(InventoryTransactionType::TRANSFER->value, 'to');
        $transferOutByItem = $itemBreakdown(InventoryTransactionType::TRANSFER->value, 'from');
        $issueByItem = $itemBreakdown(InventoryTransactionType::ISSUE->value, 'from');

        // Item universe: ambil semua item yang pernah ada transaksi di POP ini sampai akhir periode
        $itemIdsEverTouchedByPop = [];
        foreach ($scopedPops as $popId) {
            $itemIdsEverTouchedByPop[$popId] = InventoryTransaction::query()
                ->where(fn ($q) => $q->where('to_pop_id', $popId)->orWhere('from_pop_id', $popId))
                ->where('created_at', '<', $nextPeriodStart)
                ->distinct()
                ->pluck('item_id');
        }

        $itemIds = collect([$receiveByItem, $transferInByItem, $transferOutByItem, $issueByItem])
            ->flatMap(fn ($c) => $c->pluck('item_id'))
            ->unique();
        $itemsById = Item::query()->whereIn('id', $itemIds)->get()->keyBy('id');

        return Pop::query()
            ->whereIn('id', $scopedPops)
            ->orderBy('type')->orderBy('name')
            ->get()
            ->map(function (Pop $pop) use ($receive, $transferIn, $transferOut, $issue, $receiveByItem, $transferInByItem, $transferOutByItem, $issueByItem, $itemsById, $stockAsOf, $start, $nextPeriodStart) {
                $itemIdsForPop = collect([$receiveByItem, $transferInByItem, $transferOutByItem, $issueByItem])
                    ->flatMap(fn ($c) => $c->filter(fn ($r) => $r->pop_id === $pop->id)->pluck('item_id'))
                    ->unique();

                $items = $itemIdsForPop->map(function ($itemId) use ($pop, $receiveByItem, $transferInByItem, $transferOutByItem, $issueByItem, $itemsById, $stockAsOf, $start, $nextPeriodStart) {
                    $key = $pop->id.'-'.$itemId;
                    $item = $itemsById->get($itemId);
                    if (! $item) {
                        return null;
                    }

                    $unit = $item->unit ?? '';
                    $receiveQty = (float) ($receiveByItem[$key]->total ?? 0);
                    $transferInQty = (float) ($transferInByItem[$key]->total ?? 0);
                    $transferOutQty = (float) ($transferOutByItem[$key]->total ?? 0);
                    $issueQty = (float) ($issueByItem[$key]->total ?? 0);

                    $stokAwalQty = 0.0;
                    $stokAwalNilai = 0.0;
                    $stokAkhirQty = 0.0;
                    $stokAkhirNilai = 0.0;

                    if ($stockAsOf) {
                        $stokAwal = $stockAsOf->balanceAsOf($pop, $item, $start, true);
                        $stokAkhir = $stockAsOf->balanceAsOf($pop, $item, $nextPeriodStart, false);

                        $stokAwalQty = (float) ($stokAwal['lama']['qty'] + $stokAwal['baru']['qty']);
                        $stokAwalNilai = (float) $stokAwal['nilai'];
                        $stokAkhirQty = (float) ($stokAkhir['lama']['qty'] + $stokAkhir['baru']['qty']);
                        $stokAkhirNilai = (float) $stokAkhir['nilai'];
                    }

                    // Barang ROLL tampil dalam satuan ROLL di Laporan Bulanan
                    if ($item->tracking_type === TrackingType::ROLL && (float) $item->meter_per_roll > 0) {
                        $meterPerRoll = (float) $item->meter_per_roll;
                        $unit = 'roll';
                        $receiveQty = $receiveQty / $meterPerRoll;
                        $transferInQty = $transferInQty / $meterPerRoll;
                        $transferOutQty = $transferOutQty / $meterPerRoll;
                        $issueQty = $issueQty / $meterPerRoll;
                        $stokAwalQty = $stokAwalQty / $meterPerRoll;
                        $stokAkhirQty = $stokAkhirQty / $meterPerRoll;
                    }

                    return [
                        'item_id' => $itemId,
                        'item_name' => $item->name,
                        'unit' => $unit,
                        'stok_awal_qty' => $stokAwalQty,
                        'stok_awal_nilai' => $stokAwalNilai,
                        'receive' => $receiveQty,
                        'transfer_in' => $transferInQty,
                        'transfer_out' => $transferOutQty,
                        'issue' => $issueQty,
                        'stok_akhir_qty' => $stokAkhirQty,
                        'stok_akhir_nilai' => $stokAkhirNilai,
                    ];
                })->filter()->values();

                return [
                    'pop' => $pop,
                    'receive' => (float) ($receive[$pop->id] ?? 0),
                    'transfer_in' => (float) ($transferIn[$pop->id] ?? 0),
                    'transfer_out' => (float) ($transferOut[$pop->id] ?? 0),
                    'issue' => (float) ($issue[$pop->id] ?? 0),
                    'items' => $items,
                ];
            })
            ->filter(fn ($row) => $row['items']->isNotEmpty() && ($row['receive'] + $row['transfer_in'] + $row['transfer_out'] + $row['issue'] > 0))
            ->values()
            ->all();
    }

    /**
     * Rekap kerugian (LOST/DAMAGED/SCRAPPED/QUARANTINE/dst) per kategori per periode.
     *
     * @param  Collection<int, int>  $popIds
     * @return array<int, array{reason: string, reason_label: string, pop_label: string, item_name: string, unit: string, count: int, total_qty: float, unit_cost: ?float, loss_value: ?float}>
     */
    private function buildAdjustmentSummary($popIds, ?int $popFilter, Carbon $start, Carbon $end): array
    {
        $scopedPops = $popFilter ? collect([$popFilter])->intersect($popIds) : $popIds;

        $rows = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::ADJUSTMENT->value)
            ->where(function ($q) use ($scopedPops, $popFilter) {
                $q->whereIn('to_pop_id', $scopedPops)
                    ->orWhereIn('from_pop_id', $scopedPops)
                    // Custody teknisi (from_pop_id null) TETAP ikut kalau gak
                    // ada filter POP eksplisit — kerugian custody tetap
                    // relevan dipantau HQ walau gak bisa diatribusi ke cabang.
                    ->when(! $popFilter, fn ($qq) => $qq->orWhere(fn ($qqq) => $qqq->whereNull('to_pop_id')->whereNull('from_pop_id')));
            })
            ->whereBetween('created_at', [$start, $end])
            ->with(['toPop', 'fromPop', 'item'])
            ->get();

        $reasonLabels = InventoryAdjustmentService::REASON_CATEGORIES + [
            // `resulting_status` SCRAPPED gak ada di REASON_CATEGORIES —
            // itu daftar `reason` buat adjustCustody(), SCRAPPED bukan
            // pilihan reason di sana (cuma tujuan status serial/roll).
            'scrapped' => 'Dimusnahkan (Scrapped)',
        ];

        // Reason yang dihitung sbg NILAI RUGI finansial (keputusan eksplisit
        // user 2026-09-17: RUSAK + HILANG, BUKAN scrapped/quarantine/selisih
        // — dua terakhir itu bukan "barang hancur/hilang", beda sifat).
        $lossReasons = ['lost', 'damaged'];

        // Grouping key WAJIB ikut item_id (2026-09-07) — sebelumnya cuma
        // reason+lokasi, jadi `total_qty` bisa gabung ONT (unit) + kabel
        // (meter) kalau kebetulan sama-sama "damaged" di gudang yang sama
        // bulan itu. Satu baris = satu kategori + satu lokasi + satu item =
        // satu satuan, aman dijumlah.
        return $rows->groupBy(function (InventoryTransaction $row) {
            $popLabel = $row->toPop->name ?? $row->fromPop->name ?? '— (Custody Teknisi)';
            $category = $row->resulting_status ?? $row->reason;

            return $category.'|'.$popLabel.'|'.$row->item_id;
        })->map(function ($group) use ($reasonLabels, $lossReasons) {
            $first = $group->first();
            $popLabel = $first->toPop->name ?? $first->fromPop->name ?? '— (Custody Teknisi)';
            $category = $first->resulting_status ?? $first->reason;
            $totalQty = (float) $group->sum(fn ($r) => abs((float) $r->qty));

            $unitCost = null;
            $lossValue = null;
            $unit = $first->item->unit ?? '';
            $displayQty = $totalQty;

            if (in_array($category, $lossReasons, true)) {
                $unitCost = $this->resolveLastCostForItem($first->item_id);
                $lossValue = $unitCost !== null ? $totalQty * $unitCost : null;
            }

            // Barang ROLL tampil dalam satuan ROLL di Laporan Bulanan
            if ($first->item && $first->item->tracking_type === TrackingType::ROLL && (float) $first->item->meter_per_roll > 0) {
                $meterPerRoll = (float) $first->item->meter_per_roll;
                $displayQty = $totalQty / $meterPerRoll;
                $unit = 'roll';
                $unitCost = $unitCost !== null ? $unitCost * $meterPerRoll : null;
            }

            return [
                'reason' => $category,
                'reason_label' => $reasonLabels[$category] ?? $category,
                'pop_id' => $first->to_pop_id ?? $first->from_pop_id, // null = custody teknisi, gak bisa diatribusi ke POP mana pun (dipakai export() buat plotting per-POP)
                'pop_label' => $popLabel,
                'item_id' => $first->item_id,
                'item_name' => $first->item->name ?? '(item dihapus)',
                'unit' => $unit,
                'count' => $group->count(),
                'total_qty' => $displayQty,
                'unit_cost' => $unitCost,
                'loss_value' => $lossValue,
            ];
        })->sortBy('reason_label')->values()->all();
    }

    /**
     * Last-cost item buat nilai rugi (ADHOC-79) — `unit_price_snapshot`
     * RECEIVE TERAKHIR buat item itu, LINTAS LOT/gudang (beda dari
     * `resolveLastCost()` di Issue/Transfer Service yang filter EXACT
     * lot_no, karena baris ADJUSTMENT gak selalu nyimpen lot_no yang match
     * balance lot mana persisnya — SERIALIZED/custody malah gak py lot_no
     * sama sekali). Harga master (`items.harga_beli`) sengaja TIDAK dipakai
     * — kolom itu gak ada, sistem cuma nyimpen harga per-transaksi RECEIVE
     * (konfirmasi user 2026-09-17: "kalo input barang itu ada Input
     * Harganya" — itu `unit_price_snapshot` RECEIVE ini).
     */
    private function resolveLastCostForItem(int $itemId): ?float
    {
        $price = InventoryTransaction::query()
            ->where('item_id', $itemId)
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->whereNotNull('unit_price_snapshot')
            ->latest('id')
            ->value('unit_price_snapshot');

        return $price !== null ? (float) $price : null;
    }

    /**
     * Konversi satu baris item ROLL dari satuan METER (tracking internal,
     * konsisten `unit_price_snapshot` per-meter di seluruh ledger — lihat
     * docblock `InventoryRoll`) ke satuan ROLL buat tampilan Laporan
     * Bulanan (keputusan user 2026-09-18, sejalan Invoice). Cuma dipanggil
     * kalau `item->tracking_type === ROLL`.
     *
     * Yang dikonversi: `qty` (÷ meterPerRoll) & `harga` (× meterPerRoll)
     * tiap slot 'baru' (ROLL selalu satu-slot, lihat `WarehouseStockAsOfService`).
     * `issue`/`qty_rusak`/`qty_hilang` sudah dikonversi di buildMovementSummary /
     * buildAdjustmentSummary. `nilai`/`nilai_rusak`/`nilai_hilang` (Rp) SENGAJA TIDAK
     * disentuh — sudah benar apa adanya (qty-meter × harga-per-meter),
     * konversi cuma soal presentasi, bukan re-hitung nilai.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function convertRollRowToRollUnit(array $row, float $meterPerRoll): array
    {
        $row['unit'] = 'roll';

        foreach (['barang_masuk', 'stok_awal', 'stok_akhir'] as $key) {
            foreach (['lama', 'baru'] as $slot) {
                if (isset($row[$key][$slot])) {
                    $row[$key][$slot]['qty'] = (float) $row[$key][$slot]['qty'] / $meterPerRoll;
                    if ($row[$key][$slot]['harga'] !== null) {
                        $row[$key][$slot]['harga'] = (float) $row[$key][$slot]['harga'] * $meterPerRoll;
                    }
                }
            }
        }

        return $row;
    }
}
