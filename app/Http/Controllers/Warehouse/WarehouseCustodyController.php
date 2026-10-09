<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\ResolvesSelectedPop;
use App\Models\DeviceRetrievalLog;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Support\LikeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Custody — Lihat Semua Teknisi (ADHOC-54, rancangan-ui.md §2.6). Read-only.
 *
 * Discope lewat `issued_from_pop_id` (kolom baru — lihat migration
 * `add_issued_from_pop_id_for_custody_scoping`), BUKAN teknisinya sendiri:
 * repo ini gak punya pemetaan "teknisi ini anggota cabang mana" yang bisa
 * dipercaya (`user_pops` legacy, gak paham pop_tree — sama alasan
 * `EffectiveAccessService` dipilih di atas jalur lama di seluruh modul lain).
 */
class WarehouseCustodyController extends Controller
{
    use ResolvesSelectedPop;

    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();
        $hasAllAccess = $access->hasAllPopAccess($user);
        $allowedPopIds = $hasAllAccess ? [] : $access->getAllowedPopIds($user);

        $pops = Pop::query()
            ->warehouse()
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('id', $allowedPopIds))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $technicianFilter = $request->query('technician_id');
        // Konteks cabang global (analisa-ui-ux-warehouse.md §S1).
        $popFilter = $this->resolveSelectedPopId($request, $access, $user);
        $search = LikeSearch::sanitize((string) $request->query('search', ''));

        // Base query builder per daftar — BELUM dieksekusi, dipakai ulang buat
        // KPI (agregat) DAN daftar (paginate/get). Scope + filter + search
        // identik dengan versi sebelumnya; cuma pemisahan builder-vs-eksekusi
        // yang berubah supaya KPI tetap total penuh walau daftar dipaginasi
        // (analisa-ui-ux §A1 — paginasi Custody).
        $custodyQuery = TechnicianCustody::query()
            ->active()
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->when($technicianFilter, fn ($q) => $q->where('technician_id', $technicianFilter))
            ->when($popFilter, fn ($q) => $q->where('issued_from_pop_id', $popFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('lot_no', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->with(['technician', 'item.category', 'issuedFromPop'])
            ->orderBy('issued_at');

        // Sort kolom per tab (analisa-ui-ux §A5 — sisa ditunda sebelumnya karena
        // 4 tab terpisah; sekarang satu param PER TAB, `?tab=` nentuin yang
        // aktif, sisanya gak kesentuh). Whitelist; parameter asing/absen =
        // urutan default lama (zero-regression).
        //
        // Kolom relasi (teknisi/item/pelanggan) di-sort lewat subquery
        // korelasi (`orderByRaw`), BUKAN leftJoin — `InventorySerial::status()`
        // scope nulis `where('status', ...)` TANPA prefix tabel; begitu
        // leftJoin ke `users` ditambah (users JUGA punya kolom `status`,
        // akun user), kolomnya jadi ambigu dan SQLite nolak querynya
        // ("ambiguous column name: status"). Subquery gak nambah tabel ke
        // FROM/JOIN, jadi ambiguitas itu gak pernah muncul.
        $materialSort = in_array($request->query('material_sort'), ['teknisi', 'item', 'qty'], true) ? $request->query('material_sort') : null;
        $materialDir = $request->query('material_dir') === 'desc' ? 'desc' : 'asc';
        // reorder() buang `orderBy('issued_at')` default dulu — custom sort
        // jadi primary, bukan nambah jadi tiebreaker kedua di belakangnya.
        match ($materialSort) {
            'teknisi' => $custodyQuery->reorder()->orderByRaw('(select name from users where users.id = technician_custody.technician_id) '.$materialDir),
            'item' => $custodyQuery->reorder()->orderByRaw('(select name from items where items.id = technician_custody.item_id) '.$materialDir),
            'qty' => $custodyQuery->reorder()->orderBy('qty_remaining', $materialDir),
            default => null,
        };

        $serialQuery = InventorySerial::query()
            ->status(SerialStatus::ISSUED)
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->when($technicianFilter, fn ($q) => $q->where('current_technician_id', $technicianFilter))
            ->when($popFilter, fn ($q) => $q->where('issued_from_pop_id', $popFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->with(['item.category', 'currentTechnician', 'issuedFromPop']);

        $serialSort = in_array($request->query('serial_sort'), ['teknisi', 'item'], true) ? $request->query('serial_sort') : null;
        $serialDir = $request->query('serial_dir') === 'desc' ? 'desc' : 'asc';
        match ($serialSort) {
            'teknisi' => $serialQuery->orderByRaw('(select name from users where users.id = inventory_serials.current_technician_id) '.$serialDir),
            'item' => $serialQuery->orderByRaw('(select name from items where items.id = inventory_serials.item_id) '.$serialDir),
            default => null,
        };

        // Roll kabel (App\Enums\TrackingType::ROLL) aktif di custody teknisi
        // — status ISSUED (belum dipotong) atau IN_USE (sisa sebagian).
        // DEPLETED sengaja gak muncul di sini (custody-nya sendiri udah
        // habis, gak ada lagi yang perlu dipantau).
        $rollQuery = InventoryRoll::query()
            ->whereIn('status', [RollStatus::ISSUED->value, RollStatus::IN_USE->value])
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->when($technicianFilter, fn ($q) => $q->where('current_technician_id', $technicianFilter))
            ->when($popFilter, fn ($q) => $q->where('issued_from_pop_id', $popFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('roll_code', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->with(['item.category', 'currentTechnician', 'issuedFromPop']);

        $rollSort = in_array($request->query('roll_sort'), ['teknisi', 'item', 'remaining'], true) ? $request->query('roll_sort') : null;
        $rollDir = $request->query('roll_dir') === 'desc' ? 'desc' : 'asc';
        match ($rollSort) {
            'teknisi' => $rollQuery->orderByRaw('(select name from users where users.id = inventory_rolls.current_technician_id) '.$rollDir),
            'item' => $rollQuery->orderByRaw('(select name from items where items.id = inventory_rolls.item_id) '.$rollDir),
            'remaining' => $rollQuery->orderBy('length_remaining', $rollDir),
            default => null,
        };

        // Return dari pelanggan (ADHOC-88): modem hasil pengambilan alat (DEAC)
        // yang MASIH dipegang teknisi — status `RETURNED` (transit), belum
        // diterima gudang. Terpisah dari tab "Perangkat Serial Number" (ISSUED,
        // barang yang DIBAWA teknisi ke lapangan) karena arahnya berlawanan:
        // ini barang yang dibawa PULANG dan menunggu konfirmasi gudang di
        // Terima Retur. Scope lewat `issued_from_pop_id` = gudang tujuan.
        // ADHOC-108: `status=RETURNED` TIDAK LAGI otomatis berarti "di tangan
        // teknisi" — begitu gudang Cabang menerima (Tahap 1), status TETAP
        // RETURNED (menunggu dikirim ke Pusat), cuma `current_technician_id`
        // yang jadi null. Filter eksplisit biar tab ini (namanya "MASIH
        // dipegang teknisi") tidak ikut menampilkan retur yang sudah di
        // Cabang — itu urusan antrean "Kirim ke Pusat", bukan di sini.
        $returnedQuery = InventorySerial::query()
            ->status(SerialStatus::RETURNED)
            ->whereNotNull('current_technician_id')
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->when($technicianFilter, fn ($q) => $q->where('current_technician_id', $technicianFilter))
            ->when($popFilter, fn ($q) => $q->where('issued_from_pop_id', $popFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('customer', fn ($cq) => $cq->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->with(['item.category', 'currentTechnician', 'issuedFromPop', 'customer'])
            ->orderBy('updated_at');

        $returnSort = in_array($request->query('return_sort'), ['teknisi', 'item', 'pelanggan'], true) ? $request->query('return_sort') : null;
        $returnDir = $request->query('return_dir') === 'desc' ? 'desc' : 'asc';
        match ($returnSort) {
            'teknisi' => $returnedQuery->reorder()->orderByRaw('(select name from users where users.id = inventory_serials.current_technician_id) '.$returnDir),
            'item' => $returnedQuery->reorder()->orderByRaw('(select name from items where items.id = inventory_serials.item_id) '.$returnDir),
            'pelanggan' => $returnedQuery->reorder()->orderByRaw('(select full_name from customers where customers.id = inventory_serials.customer_id) '.$returnDir),
            default => null,
        };

        // Mode tampilan (analisa-ui-ux §U6): "barang" = daftar per item/SN
        // (default), "teknisi" = saldo per teknisi. Mode teknisi butuh data
        // PENUH buat agregasi per teknisi, jadi di sana daftar di-get() utuh;
        // mode barang dipaginasi per tab (pageName sendiri biar 4 tab gak
        // saling nabrak parameter page-nya).
        $viewMode = $request->query('view') === 'teknisi' ? 'teknisi' : 'barang';

        if ($viewMode === 'teknisi') {
            $custodies = $custodyQuery->get();
            $serials = $serialQuery->get();
            $rolls = $rollQuery->get();
            $returned = $returnedQuery->get();
        } else {
            $perPage = 25;
            $custodies = $custodyQuery->paginate($perPage, ['*'], 'material_page')->withQueryString();
            $serials = $serialQuery->paginate($perPage, ['*'], 'serial_page')->withQueryString();
            $rolls = $rollQuery->paginate($perPage, ['*'], 'roll_page')->withQueryString();
            $returned = $returnedQuery->paginate($perPage, ['*'], 'return_page')->withQueryString();
        }

        // Tanggal pengambilan per SN dari log (yang belum diterima) — kolom
        // `updated_at` SN bisa berubah karena hal lain, log tidak. Cukup buat
        // baris retur yang tampil di halaman ini (page saat ini).
        $returnedRetrievedAt = DeviceRetrievalLog::query()
            ->whereIn('serial_id', $returned->pluck('id'))
            ->whereNull('received_at')
            ->orderBy('retrieved_at')
            ->get(['serial_id', 'retrieved_at'])
            ->pluck('retrieved_at', 'serial_id');

        // "Diinput oleh & sejak" per SN (analisa-ui-ux §U3) — dari transaksi
        // ISSUE (siapa yang menyerahkan ke teknisi, kapan). Dibatasi ke SN yang
        // tampil di halaman ini (page saat ini) biar murah. Kalau ada >1 ISSUE
        // (jarang), yang terakhir (id tertinggi) menang.
        $serialIssuers = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::ISSUE->value)
            ->whereIn('serial_id', $serials->pluck('id'))
            ->with('createdBy:id,name')
            ->orderBy('id')
            ->get(['id', 'serial_id', 'created_by', 'created_at'])
            ->keyBy('serial_id');

        // Penginput per ROLL — padanan serialIssuers lewat roll_id (roll
        // diissue sekali, jadi 1:1). "Sejak" diambil dari created_at ISSUE.
        $rollIssuers = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::ISSUE->value)
            ->whereIn('roll_id', $rolls->pluck('id'))
            ->with('createdBy:id,name')
            ->orderBy('id')
            ->get(['id', 'roll_id', 'created_by', 'created_at'])
            ->keyBy('roll_id');

        // Penginput per baris custody QUANTITY — tidak ada FK custody→ledger,
        // tapi tiap ISSUE membuat satu baris custody dengan issued_at =
        // created_at transaksi (request sama). Dicocokkan lewat kunci
        // teknisi|item|lot|detik-waktu. Kalau bentrok (ISSUE sama persis di
        // detik yang sama), operatornya memang sama, jadi aman.
        $custodyKey = fn ($techId, $itemId, $lotNo, $at) => $techId.'|'.$itemId.'|'.($lotNo ?: '').'|'.($at?->timestamp ?? 0);
        $custodyIssuers = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::ISSUE->value)
            ->whereNull('serial_id')->whereNull('roll_id')
            ->whereIn('to_technician_id', $custodies->pluck('technician_id')->unique()->filter())
            ->with('createdBy:id,name')
            ->get(['id', 'to_technician_id', 'item_id', 'lot_no', 'created_by', 'created_at'])
            ->keyBy(fn ($t) => $custodyKey($t->to_technician_id, $t->item_id, $t->lot_no, $t->created_at));

        // Dropdown filter teknisi + KPI — dari query AGREGAT (bukan koleksi
        // yang dipaginasi), supaya tetap mencerminkan TOTAL penuh dalam scope,
        // bukan cuma isi halaman yang lagi dibuka. technician_id sengaja tidak
        // ikut difilter di sini supaya dropdown tidak menyusut begitu 1
        // teknisi dipilih.
        $technicianIds = collect()
            ->merge((clone $custodyQuery)->reorder()->distinct()->pluck('technician_id'))
            ->merge((clone $serialQuery)->reorder()->distinct()->pluck('current_technician_id'))
            ->merge((clone $rollQuery)->reorder()->distinct()->pluck('current_technician_id'))
            ->merge((clone $returnedQuery)->reorder()->distinct()->pluck('current_technician_id'))
            ->unique()->filter();
        $technicians = User::whereIn('id', $technicianIds)->orderBy('name')->get();

        // KPI ringkasan (rancangan-layout.md §8.1) — SENGAJA dipisah per
        // SATUAN, bukan dijumlah rata lintas kategori: kabel/material meteran
        // (unit "meter") dijumlah qty jadi "Total Kabel Lapangan", sisanya
        // (pcs/batch non-meter — connector, patchcord, dst) cuma DIHITUNG
        // BARISNYA ("Material Pasif"), karena satuannya macem-macem dan gak
        // valid dijumlah. Dihitung dari agregat DB biar tetap total penuh.
        $meterSum = (float) (clone $custodyQuery)->reorder()->whereHas('item', fn ($iq) => $iq->where('unit', 'meter'))->sum('qty_remaining');
        $materialBatchCount = (clone $custodyQuery)->reorder()->whereHas('item', fn ($iq) => $iq->where('unit', '!=', 'meter'))->count();
        $rollMeterSum = (float) (clone $rollQuery)->reorder()->sum('length_remaining');

        $kpi = [
            'serial_count' => (clone $serialQuery)->reorder()->count(),
            'meter_total' => $meterSum + $rollMeterSum,
            'material_batch_count' => $materialBatchCount,
            'roll_count' => (clone $rollQuery)->reorder()->count(),
            'technician_count' => $technicianIds->count(),
        ];

        $perTechnician = $viewMode === 'teknisi'
            ? $this->buildPerTechnician($custodies, $serials, $rolls, $returned)
            : collect();

        return view('warehouse.custody.index', compact(
            'custodies', 'serials', 'rolls', 'returned', 'returnedRetrievedAt', 'serialIssuers', 'rollIssuers', 'custodyIssuers', 'custodyKey', 'technicians', 'technicianFilter', 'pops', 'popFilter', 'search', 'kpi', 'viewMode', 'perTechnician',
            'serialSort', 'serialDir', 'materialSort', 'materialDir', 'rollSort', 'rollDir', 'returnSort', 'returnDir'
        ));
    }

    /**
     * Saldo lapangan per teknisi (analisa-ui-ux §U6) — menjawab "siapa pegang
     * apa" tanpa harus menyusuri daftar per barang. Dibangun dari koleksi yang
     * sudah di-scope & di-filter di index(), jadi TIDAK ada query baru dan
     * TIDAK ada risiko bocor lintas POP.
     *
     * @param  Collection<int, TechnicianCustody>  $custodies
     * @param  Collection<int, InventorySerial>  $serials
     * @param  Collection<int, InventoryRoll>  $rolls
     * @param  Collection<int, InventorySerial>  $returned
     * @return Collection<int, array{technician: ?User, pops: Collection<int, string>, items: Collection<int, array{name: string, qty: float, unit: string, is_serial: bool}>, serial_count: int, returned_count: int, oldest_issued_at: ?Carbon}>
     */
    private function buildPerTechnician($custodies, $serials, $rolls, $returned): Collection
    {
        $buckets = [];

        $touch = function (int $techId, ?User $tech, ?Pop $pop) use (&$buckets) {
            if (! isset($buckets[$techId])) {
                $buckets[$techId] = [
                    'technician' => $tech,
                    'pops' => collect(),
                    'items' => collect(),
                    'serial_count' => 0,
                    'returned_count' => 0,
                    'oldest_issued_at' => null,
                ];
            }
            if ($pop && ! $buckets[$techId]['pops']->contains($pop->name)) {
                $buckets[$techId]['pops']->push($pop->name);
            }

            return $techId;
        };

        $addItem = function (int $techId, int $itemId, string $name, float $qty, string $unit, bool $isSerial) use (&$buckets) {
            $items = $buckets[$techId]['items'];
            $existing = $items->get($itemId);
            $items->put($itemId, [
                'name' => $name,
                'qty' => ($existing['qty'] ?? 0) + $qty,
                'unit' => $unit,
                'is_serial' => $isSerial,
            ]);
        };

        // Custody QUANTITY/batch: qty_remaining, satuan item.
        foreach ($custodies as $c) {
            $techId = $touch((int) $c->technician_id, $c->technician, $c->issuedFromPop);
            $addItem($techId, (int) $c->item_id, $c->item->name, (float) $c->qty_remaining, $c->item->unit, false);
            $at = $c->issued_at;
            if ($at && ($buckets[$techId]['oldest_issued_at'] === null || $at->lt($buckets[$techId]['oldest_issued_at']))) {
                $buckets[$techId]['oldest_issued_at'] = $at;
            }
        }

        // SN perangkat (ISSUED): dihitung per unit.
        foreach ($serials as $s) {
            if ($s->current_technician_id === null) {
                continue;
            }
            $techId = $touch((int) $s->current_technician_id, $s->currentTechnician, $s->issuedFromPop);
            $addItem($techId, (int) $s->item_id, $s->item->name, 1, 'unit', true);
            $buckets[$techId]['serial_count']++;
        }

        // Roll kabel: sisa meter.
        foreach ($rolls as $r) {
            if ($r->current_technician_id === null) {
                continue;
            }
            $techId = $touch((int) $r->current_technician_id, $r->currentTechnician, $r->issuedFromPop);
            $addItem($techId, (int) $r->item_id, $r->item->name, (float) $r->length_remaining, 'meter', false);
        }

        // Retur dari pelanggan yang masih di tangan teknisi (menunggu gudang).
        foreach ($returned as $s) {
            if ($s->current_technician_id === null) {
                continue;
            }
            $techId = $touch((int) $s->current_technician_id, $s->currentTechnician, $s->issuedFromPop);
            $buckets[$techId]['returned_count']++;
        }

        return collect($buckets)
            ->map(function ($bucket) {
                $bucket['items'] = $bucket['items']->sortByDesc('qty')->values();

                return $bucket;
            })
            ->sortBy(fn ($b) => $b['technician']?->name ?? '')
            ->values();
    }
}
