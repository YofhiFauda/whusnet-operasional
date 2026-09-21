<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\InventoryTransferService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Transfer Pusat→Cabang (ADHOC-54, rancangan-ui.md §2.2-2.3). Dua fase:
 * `create()`/`store()` = dispatch (Pusat), `show()`+`receive()` = confirm
 * (Cabang) — di HALAMAN YANG SAMA (`show`), bukan dua halaman terpisah.
 *
 * Controller SENGAJA tipis — validasi request + delegasi penuh ke
 * `InventoryTransferService`. Semua invariant bisnis (tipe pop, cukup
 * stok, dst) ditegakkan di Service, bukan diulang di sini.
 */
class WarehouseTransferController extends Controller
{
    use AuthorizesWarehousePop;

    public function create(EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $fromPops = Pop::query()->where('type', 'pusat')->orderBy('name')->get();

        $toPops = Pop::query()
            ->where('type', 'cabang')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')
            ->get();

        $items = Item::active()->with('category')->orderBy('name')->get();

        return view('warehouse.transfers.create', compact('fromPops', 'toPops', 'items'));
    }

    /**
     * Referensi read-only "apa aja yang beneran ada di Pusat ini sekarang" —
     * pola sama `WarehouseIssueController::availableStock()` (gap yang sama:
     * dispatch Transfer sebelumnya sama-sama "ketik SN blind, taunya salah
     * pas submit" kayak Issue dulu, cuma belum ke-tutup barengan waktu itu).
     */
    public function availableStock(Request $request, EffectiveAccessService $access): JsonResponse
    {
        $validated = $request->validate(['pop_id' => 'required|integer|exists:pops,id']);
        $this->assertPopIdInScope((int) $validated['pop_id'], auth()->user(), $access);

        $balanceItems = InventoryBalance::query()
            ->where('pop_id', $validated['pop_id'])
            ->where('qty', '>', 0)
            ->with('item')
            ->get()
            ->groupBy('item_id')
            ->map(function ($rows) {
                $item = $rows->first()->item;

                return [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'tracking_type' => $item->tracking_type->value,
                    'lots' => $rows->map(fn ($b) => ['lot_no' => $b->lot_no, 'qty' => (float) $b->qty])->values(),
                ];
            })
            ->values();

        $serialItems = InventorySerial::query()
            ->where('current_pop_id', $validated['pop_id'])
            ->where('status', SerialStatus::AVAILABLE->value)
            ->with('item')
            ->get()
            ->groupBy('item_id')
            ->map(function ($rows) {
                $item = $rows->first()->item;

                return [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'tracking_type' => $item->tracking_type->value,
                    'serials' => $rows->pluck('serial_number')->values(),
                ];
            })
            ->values();

        $rollItems = InventoryRoll::query()
            ->where('current_pop_id', $validated['pop_id'])
            ->where('status', RollStatus::AVAILABLE->value)
            ->with('item')
            ->get()
            ->groupBy('item_id')
            ->map(function ($rows) {
                $item = $rows->first()->item;

                return [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'tracking_type' => $item->tracking_type->value,
                    'rolls' => $rows->map(fn ($r) => ['roll_code' => $r->roll_code, 'length_remaining' => (float) $r->length_remaining])->values(),
                ];
            })
            ->values();

        return response()->json(['items' => $balanceItems->concat($serialItems)->concat($rollItems)->values()]);
    }

    /**
     * Halaman khusus "Konfirmasi Barang Transfer" (permintaan eksplisit
     * user 2026-09-16) — sebelumnya transfer yang lagi `in_transit` cuma
     * kelihatan kececer di antara ledger Dashboard/Riwayat Mutasi, gak ada
     * satu tempat buat Pusat/Cabang langsung liat "apa yang masih nunggu
     * dikonfirmasi". Dua daftar, BEDA tindakan:
     *
     *   - `actionable` — `to_pop_id` dalam scope aktor (sama syarat `$canReceive`
     *     di `show()`) — tombol "Tinjau & Konfirmasi" beneran mengarah ke
     *     `show()` (form scan/centang yang UDAH ada, TIDAK diduplikasi di
     *     sini — halaman ini murni hub/daftar, bukan form baru).
     *   - `awaitingOtherSide` — `from_pop_id` dalam scope TAPI `to_pop_id`
     *     DI LUAR scope — buat Pusat mantau kiriman sendiri yang masih
     *     nunggu cabang tujuan konfirmasi, read-only (gak ada tombol aksi,
     *     scope penerima bukan urusan aktor ini).
     *
     * User full access (`hasAllPopAccess`) otomatis py `to_pop_id` "dalam
     * scope" buat SEMUA transfer, jadi `awaitingOtherSide` struktural
     * selalu kosong buat mereka — udah kebagian di `actionable` semua,
     * bukan bug.
     */
    public function pending(EffectiveAccessService $access): View
    {
        $user = auth()->user();
        $hasAllAccess = $access->hasAllPopAccess($user);
        $allowedPopIds = $hasAllAccess ? [] : $access->getAllowedPopIds($user);

        $transfers = InventoryTransfer::query()
            ->where('status', TransferStatus::IN_TRANSIT->value)
            ->when(! $hasAllAccess, fn ($q) => $q->where(
                fn ($qq) => $qq->whereIn('from_pop_id', $allowedPopIds)->orWhereIn('to_pop_id', $allowedPopIds)
            ))
            ->withCount(['transactions as line_count' => fn ($q) => $q->whereNotNull('from_pop_id')])
            ->with(['fromPop', 'toPop', 'createdBy'])
            ->oldest('id') // yang paling lama nunggu duluan — itu yang paling mendesak dikonfirmasi
            ->get();

        $canReceiveTransfer = fn (InventoryTransfer $t) => $hasAllAccess || in_array($t->to_pop_id, $allowedPopIds, true);

        $actionable = $transfers->filter($canReceiveTransfer)->values();
        $awaitingOtherSide = $transfers->reject($canReceiveTransfer)->values();

        return view('warehouse.transfers.pending', compact('actionable', 'awaitingOtherSide'));
    }

    public function store(Request $request, InventoryTransferService $service): RedirectResponse
    {
        $validated = $request->validate([
            'from_pop_id' => 'required|integer|exists:pops,id',
            'to_pop_id' => 'required|integer|exists:pops,id|different:from_pop_id',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer|exists:items,id',
            'lines.*.qty' => 'nullable|numeric|min:0.01',
            'lines.*.lot_no' => 'nullable|string|max:50',
            'lines.*.serial_numbers' => 'nullable|string',
            'lines.*.roll_codes' => 'nullable|string',
        ]);

        $fromPop = Pop::findOrFail($validated['from_pop_id']);
        $toPop = Pop::findOrFail($validated['to_pop_id']);

        try {
            $lines = $this->normalizeLines($validated['lines']);
            $transfer = $service->createTransfer($fromPop, $toPop, $lines, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.transfers.show', $transfer)
            ->with('success', "Transfer {$transfer->reference_number} berhasil dibuat, menunggu konfirmasi terima di {$toPop->name}.");
    }

    public function show(InventoryTransfer $transfer, EffectiveAccessService $access): View
    {
        $transfer->load(['fromPop', 'toPop', 'createdBy', 'receivedBy']);

        $this->assertViewableByEitherSide($transfer, $access);

        $user = auth()->user();
        $dispatchLines = $transfer->transactions()->whereNotNull('from_pop_id')->with(['item.category', 'serial', 'roll', 'fromPop', 'toPop', 'fromTechnician', 'toTechnician'])->get();
        $confirmedLines = $transfer->transactions()->whereNotNull('to_pop_id')->with(['item.category', 'serial', 'roll', 'fromPop', 'toPop', 'fromTechnician', 'toTechnician'])->get();

        // Tombol "Konfirmasi Penerimaan" cuma buat sisi Cabang TUJUAN — beda
        // dari show() di atas yang boleh dua sisi. Sebelumnya view cuma cek
        // permission (bukan scope), jadi admin Pusat/cabang lain yang
        // kebetulan bisa buka halaman ini (dari sisi Pusat) tetap ngeliat
        // tombol Konfirmasi walau `receive()` bakal nolak 403 pas beneran
        // diklik — dirapikan biar tombolnya emang gak nongol kalau bakal
        // ditolak (ketauan audit tata-letak 2026-09-03).
        $canReceive = $access->hasAllPopAccess($user) || in_array($transfer->to_pop_id, $access->getAllowedPopIds($user), true);

        return view('warehouse.transfers.show', compact('transfer', 'dispatchLines', 'confirmedLines', 'canReceive'));
    }

    /**
     * Konfirmasi SEKALIGUS — bukan per-item lagi (koreksi 2026-09-18,
     * keputusan eksplisit user: checkbox per-SN/roll yang tadinya kontrol
     * anti-manipulasi jadi beban di kiriman ratusan/ribuan item). Form gak
     * lagi kirim `confirmed_*` manual — SEMUA baris dispatch otomatis
     * dianggap cocok 100% sesuai daftar begitu tombol ditekan (sudah
     * dilewati modal warning di FE yang minta staf cek fisik dulu, lihat
     * `warehouse.transfers.show`). Gak ada lagi partial-receive dari sini —
     * selisih fisik yang ketauan belakangan lewat jalur Adjustment/opname
     * terpisah (kontrol-anti-manipulasi.md §7), BUKAN parameter di sini.
     */
    public function receive(InventoryTransfer $transfer, InventoryTransferService $service, EffectiveAccessService $access): RedirectResponse
    {
        // Cuma Cabang TUJUAN yang boleh konfirmasi terima — beda dari show()
        // yang boleh dua sisi, receive() itu aksi tulis milik satu sisi doang.
        $this->assertPopInScope($transfer->toPop, auth()->user(), $access);

        $dispatchLines = $transfer->transactions()->whereNotNull('from_pop_id')->with(['serial', 'roll'])->get();

        $confirmedSerialNumbers = $dispatchLines->pluck('serial.serial_number')->filter()->values()->all();
        $confirmedRollCodes = $dispatchLines->pluck('roll.roll_code')->filter()->values()->all();

        $confirmedQuantities = [];
        foreach ($dispatchLines as $line) {
            if ($line->serial_id !== null || $line->roll_id !== null) {
                continue;
            }

            $lotNo = $line->lot_no ?? '';
            if ($lotNo === '') {
                $confirmedQuantities[$line->item_id] = ($confirmedQuantities[$line->item_id] ?? 0) + (float) $line->qty;
            } else {
                $confirmedQuantities[$line->item_id][$lotNo] = ($confirmedQuantities[$line->item_id][$lotNo] ?? 0) + (float) $line->qty;
            }
        }

        try {
            $transfer = $service->receiveTransfer(
                $transfer,
                $confirmedSerialNumbers,
                $confirmedQuantities,
                auth()->user(),
                $confirmedRollCodes,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.transfers.show', $transfer)
            ->with('success', "Transfer {$transfer->reference_number} dikonfirmasi: {$transfer->status->label()}.");
    }

    /**
     * Invoice (berharga) — PDF A4, cuma dispatch leg (nilai barang keluar
     * dari sisi Pusat, gak nunggu fase confirm — lihat rancangan §5). Akses
     * digerbangi permission root TERPISAH `warehouse_transfer_invoice.view`
     * di routes/web.php (BUKAN `warehouse_transfer.view`, itu juga dipegang
     * pop_admin cabang yang gak boleh liat harga — lihat config/rbac.php).
     *
     * docs/plan/warehouse/rancangan-invoice-surat-jalan-transfer.md §4.1, §6.
     */
    public function invoice(InventoryTransfer $transfer, EffectiveAccessService $access, Request $request): HttpResponse
    {
        $transfer->load(['fromPop', 'toPop', 'createdBy']);
        $this->assertViewableByEitherSide($transfer, $access);

        $lines = $transfer->transactions()->whereNotNull('from_pop_id')->with(['item'])->get();
        $total = $lines->sum(fn ($line) => (float) $line->qty * (float) $line->unit_price_snapshot);

        // Invoice cuma nampilin HASIL AKHIR per barang (Qty/Nama/Harga/Jumlah)
        // — daftar per-SN/kode/lot udah ada di Surat Jalan, di sini bikin
        // redundan (keputusan user 2026-09-17). Digabung per item+harga
        // (BUKAN cuma per item — kalau satu item py dua lot beda harga,
        // subtotal harus ngikutin harga masing-masing, gak boleh dirata-rata).
        //
        // Barang ROLL (kabel per roll) tampil dalam satuan ROLL di sini —
        // BUKAN meter kayak internal tracking-nya (keputusan user
        // 2026-09-18): staf beli/nilai barang per roll (mis. Rp 777.000/roll
        // @1.000 meter), pencatatan gudang/pemakaian tetap meter (buat FIFO
        // & sisa roll kepotong), tapi dokumen KE LUAR (Invoice) harus balik
        // ke satuan roll biar cocok sama cara staf beli. `unit_price_snapshot`
        // di ledger SELALU per-meter (lihat InventoryReceiveService::receiveRoll())
        // — dikonversi balik ke per-roll DI SINI doang, murni presentasi,
        // gak nyentuh data tersimpan.
        $summaryLines = $lines
            ->groupBy(fn ($line) => $line->item_id.'|'.$line->unit_price_snapshot)
            ->map(function ($group) {
                $item = $group->first()->item;

                if ($item->tracking_type === TrackingType::ROLL) {
                    $meterPerRoll = (float) $item->meter_per_roll;

                    return (object) [
                        'item' => $item,
                        'qty' => $group->count(), // jumlah ROLL, bukan total meter
                        'unit' => 'roll',
                        'unit_price_snapshot' => (float) $group->first()->unit_price_snapshot * $meterPerRoll,
                    ];
                }

                return (object) [
                    'item' => $item,
                    'qty' => $group->sum('qty'),
                    'unit' => $item->unit,
                    'unit_price_snapshot' => $group->first()->unit_price_snapshot,
                ];
            })
            ->values();

        $pdf = Pdf::loadView('warehouse.transfers.invoice', [
            'transfer' => $transfer,
            'lines' => $summaryLines,
            'total' => $total,
            'kepalaGudang' => config('warehouse.kepala_gudang'),
            'fromPopAddress' => $this->formatPopAddress($transfer->fromPop),
            'suratJalanNumber' => $this->suratJalanNumber($transfer),
        ])->setPaper('a4');

        $filename = "invoice-{$transfer->reference_number}.pdf";

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /**
     * Surat Jalan (tanpa harga) — PDF A4. `$lines` SENGAJA cuma diisi
     * item/qty/kode, `unit_price_snapshot` gak pernah diquery ke view ini
     * sama sekali (bukan cuma disembunyikan di Blade) — lihat §4.2
     * rancangan. TTD kedua pihak murni nama + garis kosong, diisi tangan di
     * kertas, gak ada apa pun ditulis balik ke sistem (§5, §7 keputusan #2).
     *
     * Reuse permission `warehouse_transfer.view` yang sudah ada — sama
     * dengan `show()`, gak ada data sensitif yang perlu digerbangi lebih
     * ketat di dokumen ini.
     */
    public function suratJalan(InventoryTransfer $transfer, EffectiveAccessService $access, Request $request): HttpResponse
    {
        $transfer->load(['fromPop', 'toPop', 'createdBy']);
        $this->assertViewableByEitherSide($transfer, $access);

        $lines = $transfer->transactions()->whereNotNull('from_pop_id')
            ->with(['item', 'serial', 'roll'])
            ->get(['id', 'item_id', 'lot_no', 'serial_id', 'roll_id', 'qty', 'inventory_transfer_id']);

        $pdf = Pdf::loadView('warehouse.transfers.surat-jalan', [
            'transfer' => $transfer,
            'lines' => $lines,
            'suratJalanNumber' => $this->suratJalanNumber($transfer),
            'fromPopAddress' => $this->formatPopAddress($transfer->fromPop),
            'toPopAddress' => $this->formatPopAddress($transfer->toPop),
        ])->setPaper('a4');

        $filename = "surat-jalan-{$transfer->reference_number}.pdf";

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /**
     * Nomor Surat Jalan format `SJ/WHUS/{tahun}/{bulan}/{urut}` (template
     * resmi `docs/plan/warehouse/laporan/Surat_Jalan_Transfer_Gudang_WHUSNET.*`)
     * — field TERPISAH dari `reference_number` transfer (yang dicantumkan
     * apa adanya di baris "Referensi WO/Tiket"). Diturunkan dari `id`+
     * `created_at` transfer, BUKAN counter baru yang disimpan — deterministik
     * & reprint-safe (nomor sama tiap kali dicetak ulang) tanpa migration.
     */
    private function suratJalanNumber(InventoryTransfer $transfer): string
    {
        return sprintf(
            'SJ/WHUS/%s/%s/%03d',
            $transfer->created_at->format('Y'),
            $transfer->created_at->format('m'),
            $transfer->id,
        );
    }

    /**
     * Gabung `address`+`village`+`district`+`city` Pop jadi satu baris,
     * lewati bagian yang kosong — dipakai blok Pengirim/Penerima Surat Jalan
     * & header Invoice (template resmi nunjukin alamat gudang lengkap).
     */
    private function formatPopAddress(Pop $pop): string
    {
        $parts = array_filter([$pop->address, $pop->village, $pop->district, $pop->city]);

        return $parts === [] ? '-' : implode(', ', $parts);
    }

    /**
     * Transfer harus kelihatan dari DUA sisi (Pusat pengirim & Cabang
     * penerima) — scope lolos kalau salah satu pop-nya ada di allowed scope
     * aktor, bukan cuma to_pop_id. Dipakai `show()`, `invoice()`, `suratJalan()`.
     */
    private function assertViewableByEitherSide(InventoryTransfer $transfer, EffectiveAccessService $access): void
    {
        $user = auth()->user();
        if ($access->hasAllPopAccess($user)) {
            return;
        }

        $allowed = $access->getAllowedPopIds($user);
        if (! in_array($transfer->from_pop_id, $allowed, true) && ! in_array($transfer->to_pop_id, $allowed, true)) {
            abort(403, 'Anda tidak memiliki akses ke Transfer ini.');
        }
    }

    /**
     * Baris form (item_id + qty/lot_no ATAU serial_numbers teks multi-baris)
     * → bentuk array yang diharapkan `InventoryTransferService::createTransfer()`.
     *
     * Cabang diputuskan dari `tracking_type` BARANG-nya (query balik ke DB),
     * BUKAN dari ada/enggaknya key `serial_numbers` di request — sebelumnya
     * item serialized yang textarea SN-nya dikosongin (lupa isi) kepeleset
     * ke cabang qty (`filled()` gagal → qty=0 diam-diam), gak ada error
     * jelas "SN wajib diisi" (ketauan audit 2026-09-02).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{item_id:int, qty?:float, lot_no?:?string, serial_numbers?:list<string>, roll_codes?:list<string>}>
     */
    private function normalizeLines(array $rows): array
    {
        $itemIds = collect($rows)->pluck('item_id')->map(fn ($id) => (int) $id)->unique();
        $trackingTypes = Item::whereIn('id', $itemIds)->pluck('tracking_type', 'id');

        return collect($rows)->map(function (array $row) use ($trackingTypes) {
            $itemId = (int) $row['item_id'];
            $trackingType = $trackingTypes[$itemId] ?? null;

            if ($trackingType === TrackingType::SERIALIZED) {
                $serials = collect(preg_split('/[\r\n,]+/', (string) ($row['serial_numbers'] ?? '')))
                    ->map(fn ($s) => trim($s))
                    ->filter()
                    ->values()
                    ->all();

                if ($serials === []) {
                    throw new InvalidArgumentException("Barang #{$itemId} bertipe Serial Number — daftar SN wajib diisi, gak boleh kosong.");
                }

                return ['item_id' => $itemId, 'serial_numbers' => $serials];
            }

            if ($trackingType === TrackingType::ROLL) {
                $rollCodes = collect(preg_split('/[\r\n,]+/', (string) ($row['roll_codes'] ?? '')))
                    ->map(fn ($s) => trim($s))
                    ->filter()
                    ->values()
                    ->all();

                if ($rollCodes === []) {
                    throw new InvalidArgumentException("Barang #{$itemId} bertipe Roll Kabel — daftar Roll ID wajib diisi, gak boleh kosong.");
                }

                return ['item_id' => $itemId, 'roll_codes' => $rollCodes];
            }

            if (filled($row['serial_numbers'] ?? null)) {
                throw new InvalidArgumentException("Barang #{$itemId} bukan tipe Serial Number — kosongkan kolom Serial Number, isi Qty.");
            }

            return [
                'item_id' => $itemId,
                'qty' => (float) ($row['qty'] ?? 0),
                'lot_no' => $row['lot_no'] ?? null,
            ];
        })->all();
    }
}
