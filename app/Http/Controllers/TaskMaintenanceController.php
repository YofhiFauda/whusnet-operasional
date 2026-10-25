<?php

namespace App\Http\Controllers;

use App\Enums\CReqCategory;
use App\Enums\CReqVerificationStatus;
use App\Enums\DeviceOwnership;
use App\Enums\EquipmentClass;
use App\Enums\FopTaskPriority;
use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureSlotKind;
use App\Enums\InventoryTransactionType;
use App\Enums\MaterialKind;
use App\Enums\OwnershipMode;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TrackingType;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\FopTask;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\Pop;
use App\Models\Task;
use App\Models\TaskCreqDetail;
use App\Models\TaskMaintenanceBillingDetail;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\WorkTool;
use App\Services\EffectiveAccessService;
use App\Services\FileUploadService;
use App\Services\FopTaskInfrastructureScopeService;
use App\Services\FopTaskProvisioningService;
use App\Services\InfrastructureLocationOptionsService;
use App\Services\InfrastructureLocationResolver;
use App\Services\InfrastructureMaterialService;
use App\Services\InfrastructureReportLocationParser;
use App\Services\InventoryService;
use App\Services\NumberSequenceService;
use App\Services\TaskInfrastructureActionService;
use App\Services\TaskMaterialService;
use App\Services\TaskService;
use App\Services\TaskWorkToolService;
use App\Support\InfrastructureCutover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TaskMaintenanceController extends Controller
{
    use AuthorizesWarehousePop;

    /**
     * SN Perangkat Aktif yang boleh dipasang lewat laporan Maintenance ini —
     * custody anggota tim task, status ISSUED, item-nya installable (bukan
     * company_asset kayak OTDR). Pola SAMA persis dengan
     * `CustomerInstallationController::eligibleSerialsForTeam()` (ADHOC-54),
     * tapi di sini BEDA sifat: OPSIONAL, bukan wajib — teknisi maintenance
     * gak selalu ganti modem, kadang cuma cek/betulin sambungan. Kalau gak
     * bawa/ganti modem, dropdown dibiarkan kosong dan `store()` gak nyentuh
     * SN sama sekali (lihat validasi `nullable` di `store()`).
     */
    private function eligibleSerialsForTeam(Task $task)
    {
        $teamTechnicianIds = $task->teamMembers->pluck('user_id')->all();

        if ($teamTechnicianIds === []) {
            return collect();
        }

        // Cuma tampilkan SN yang asal cabangnya sama dengan cabang task ini
        // (laporan user: modem Jetis kepakai di Sandya). Penegakan SEBENARNYA
        // tetap di `InventoryService::installSerial()` — ini cuma UX.
        $targetCabangId = Pop::resolveCabangId($task->pop_id);

        return InventorySerial::query()
            ->whereIn('current_technician_id', $teamTechnicianIds)
            ->where('status', SerialStatus::ISSUED->value)
            ->whereHas('item', fn ($q) => $q->where('ownership_mode', OwnershipMode::INSTALLABLE->value))
            // AP/STB (`is_customer_extra_device`) bukan pengganti modem — kalau
            // dipilih di sini, installSerial(returnExistingSerial: true)
            // meretur ONT asli dan AP-nya menyamar jadi ONT (ADHOC-190, final
            // review). Sama pengecualian dengan
            // `CustomerInstallationController::eligibleSerialsForTeam()`;
            // penegakan sebenarnya tetap di `InventoryService::installSerial()`.
            // whereDoesntHave (bukan whereHas is_extra=false) supaya item tanpa
            // kategori tetap muncul.
            ->whereDoesntHave('item.category', fn ($q) => $q->where('is_customer_extra_device', true))
            ->when($targetCabangId !== null, fn ($q) => $q->where(function ($q2) use ($targetCabangId) {
                $q2->whereNull('issued_from_pop_id')->orWhere('issued_from_pop_id', $targetCabangId);
            }))
            ->with('item.infrastructureNodeType')
            ->get();
    }

    /**
     * Padanan `CustomerInstallationController::eligiblePassiveCustodyForTeam()`
     * (koreksi lanjutan ADHOC-54, 2026-09-12) — dipakai form ini juga karena
     * `report()`/`store()` SATU controller buat MTN, C-REQ, O-REQ, DAN
     * INFR REQ sekaligus (lihat guard `report()`: cuma SURVEY & PEMASANGAN
     * yang dikecualikan, sisanya semua lewat sini). Duplikasi method
     * (bukan diekstrak ke service bersama) SENGAJA konsisten dengan
     * `eligibleSerialsForTeam()` di atas, yang juga diduplikasi persis dari
     * `CustomerInstallationController` alih-alih diekstrak.
     */
    private function eligiblePassiveCustodyForTeam(Task $task)
    {
        $teamTechnicianIds = $task->teamMembers->pluck('user_id')->all();

        if ($teamTechnicianIds === []) {
            return collect();
        }

        $targetCabangId = Pop::resolveCabangId($task->pop_id);

        return TechnicianCustody::query()
            ->whereIn('technician_id', $teamTechnicianIds)
            ->active()
            ->when($targetCabangId !== null, fn ($q) => $q->where(function ($q2) use ($targetCabangId) {
                $q2->whereNull('issued_from_pop_id')->orWhere('issued_from_pop_id', $targetCabangId);
            }))
            ->with('item.category')
            ->get()
            // Aset infrastruktur dikecualikan — punya picker sendiri
            // (`eligibleInfraCustodyForTeam()`) dan `consumeFromCustody()`
            // menolaknya; kalau ikut muncul di sini, submit gagal di service.
            ->filter(fn (TechnicianCustody $custody) => $custody->item
                && $custody->item->tracking_type !== TrackingType::SERIALIZED
                && $custody->item->effective_equipment_class === EquipmentClass::PASIF
                && $custody->item->ownership_mode !== OwnershipMode::INFRASTRUCTURE_ASSET)
            ->groupBy('item_id')
            ->map(function ($rows) {
                $item = $rows->first()->item;

                return [
                    'item_id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'type' => $item->category?->code,
                    'available' => (float) $rows->sum('qty_remaining'),
                ];
            })
            ->values();
    }

    /**
     * Padanan `CustomerInstallationController::eligibleRollsForTeam()` —
     * duplikasi sengaja, sama pola dua method di atas (`eligibleSerialsForTeam`/
     * `eligiblePassiveCustodyForTeam`). OPSIONAL sama seperti SN Perangkat
     * Aktif di controller ini.
     */
    private function eligibleRollsForTeam(Task $task)
    {
        $teamTechnicianIds = $task->teamMembers->pluck('user_id')->all();

        if ($teamTechnicianIds === []) {
            return collect();
        }

        $targetCabangId = Pop::resolveCabangId($task->pop_id);

        return InventoryRoll::query()
            ->whereIn('current_technician_id', $teamTechnicianIds)
            ->whereIn('status', [RollStatus::ISSUED->value, RollStatus::IN_USE->value])
            ->where('length_remaining', '>', 0)
            // Roll infrastruktur (feeder) punya picker sendiri
            // (`eligibleInfraRollsForTeam()`); `consumeFromRoll()` menolaknya.
            ->whereHas('item', fn ($q) => $q->where('ownership_mode', '!=', OwnershipMode::INFRASTRUCTURE_ASSET->value))
            ->when($targetCabangId !== null, fn ($q) => $q->where(function ($q2) use ($targetCabangId) {
                $q2->whereNull('issued_from_pop_id')->orWhere('issued_from_pop_id', $targetCabangId);
            }))
            ->with('item')
            ->get();
    }

    /**
     * Padanan `eligibleSerialsForTeam()` buat seksi "Pasang/Ganti
     * Infrastruktur" (O-REQ/INFR REQ). SATU perbedaan: ownership_mode
     * INFRASTRUCTURE_ASSET, bukan INSTALLABLE — SN yang dipasang netap ke
     * titik jaringan, bukan ke pelanggan. Dua daftar ini SENGAJA saling
     * lepas: modem pelanggan yang nyasar ke ODC bakal hilang dari jalur
     * install/retur pelanggan (guard yang sama ada di
     * `InventoryService::deploySerial()`, ini cuma UX + Rule::in).
     */
    private function eligibleInfraSerialsForTeam(Task $task)
    {
        $teamTechnicianIds = $task->teamMembers->pluck('user_id')->all();

        if ($teamTechnicianIds === []) {
            return collect();
        }

        $targetCabangId = Pop::resolveCabangId($task->pop_id);

        return InventorySerial::query()
            ->whereIn('current_technician_id', $teamTechnicianIds)
            ->where('status', SerialStatus::ISSUED->value)
            // Aset perusahaan (Switch/SFP) ikut HANYA bila Master Barang
            // menyatakan posisi pemasangan — aturan sama dengan
            // InventoryService::assertSerialItemFitsLocation().
            ->whereHas('item', fn ($q) => $q->where('ownership_mode', OwnershipMode::INFRASTRUCTURE_ASSET->value)
                ->orWhere(fn ($q2) => $q2->where('ownership_mode', OwnershipMode::COMPANY_ASSET->value)->whereNotNull('infrastructure_location_kind')))
            ->when($targetCabangId !== null, fn ($q) => $q->where(function ($q2) use ($targetCabangId) {
                $q2->whereNull('issued_from_pop_id')->orWhere('issued_from_pop_id', $targetCabangId);
            }))
            ->with('item.infrastructureNodeType')
            ->get();
    }

    /**
     * Padanan `eligiblePassiveCustodyForTeam()` buat aset infrastruktur
     * QUANTITY (closure, aksesoris ODP, dst). BEDA filter: tracking_type
     * QUANTITY (bukan "bukan SERIALIZED" — ROLL punya jalur sendiri lewat
     * `eligibleInfraRollsForTeam()`) + ownership_mode INFRASTRUCTURE_ASSET
     * (bukan equipment_class PASIF — konsep aktif/pasif gak relevan buat
     * aset yang netap di titik jaringan).
     */
    private function eligibleInfraCustodyForTeam(Task $task)
    {
        $teamTechnicianIds = $task->teamMembers->pluck('user_id')->all();

        if ($teamTechnicianIds === []) {
            return collect();
        }

        $targetCabangId = Pop::resolveCabangId($task->pop_id);

        return TechnicianCustody::query()
            ->whereIn('technician_id', $teamTechnicianIds)
            ->active()
            ->when($targetCabangId !== null, fn ($q) => $q->where(function ($q2) use ($targetCabangId) {
                $q2->whereNull('issued_from_pop_id')->orWhere('issued_from_pop_id', $targetCabangId);
            }))
            ->with('item.category')
            ->get()
            ->filter(fn (TechnicianCustody $custody) => $custody->item
                && $custody->item->tracking_type === TrackingType::QUANTITY
                && $custody->item->ownership_mode === OwnershipMode::INFRASTRUCTURE_ASSET)
            ->groupBy('item_id')
            ->map(function ($rows) {
                $item = $rows->first()->item;

                return [
                    'item_id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'type' => $item->category?->code,
                    'available' => (float) $rows->sum('qty_remaining'),
                ];
            })
            ->values();
    }

    /**
     * Padanan `eligibleRollsForTeam()` + filter eksplisit ownership_mode
     * INFRASTRUCTURE_ASSET. Yang lama gak filter ownership_mode sama sekali
     * (semua roll saat itu kebetulan kabel yang netap) — sekarang dua
     * konteks (pelanggan vs titik jaringan) pakai method service beda, jadi
     * filter-nya harus tertulis di sini.
     */
    private function eligibleInfraRollsForTeam(Task $task)
    {
        $teamTechnicianIds = $task->teamMembers->pluck('user_id')->all();

        if ($teamTechnicianIds === []) {
            return collect();
        }

        $targetCabangId = Pop::resolveCabangId($task->pop_id);

        return InventoryRoll::query()
            ->whereIn('current_technician_id', $teamTechnicianIds)
            ->whereIn('status', [RollStatus::ISSUED->value, RollStatus::IN_USE->value])
            ->where('length_remaining', '>', 0)
            ->whereHas('item', fn ($q) => $q->where('ownership_mode', OwnershipMode::INFRASTRUCTURE_ASSET->value))
            ->when($targetCabangId !== null, fn ($q) => $q->where(function ($q2) use ($targetCabangId) {
                $q2->whereNull('issued_from_pop_id')->orWhere('issued_from_pop_id', $targetCabangId);
            }))
            ->with('item')
            ->get();
    }

    /**
     * Titik jaringan milik Cabang task ini — node yang ditambatkan ke Cabang
     * itu SENDIRI maupun ke Mini POP di bawahnya, plus semua turunannya.
     * Bukan `inPops([$task->pop_id])` polos: `task.pop_id` bisa Mini POP,
     * dan node yang ditambatkan ke Cabang induknya (ODC utama) bakal hilang
     * dari daftar; sebaliknya task di Cabang bakal kehilangan node yang
     * ditambatkan ke Mini POP-nya. Sama definisi "cabang node" yang dipakai
     * `NetworkNode::resolvePop()` (Mini POP → Cabang induk) — jadi daftar
     * ini dan cek di validasi `store()` gak bisa menyimpang.
     *
     * Null kalau task gak punya POP — caller memperlakukannya "tidak ada
     * node", bukan "semua node" (deny-by-default).
     */
    private function infraNodeQueryForTask(Task $task)
    {
        $cabangId = Pop::resolveCabangId($task->pop_id);

        if ($cabangId === null) {
            return null;
        }

        $popIds = Pop::query()->where('parent_id', $cabangId)->pluck('id')
            ->push($cabangId)
            ->map(fn ($id) => (int) $id)
            ->all();

        return NetworkNode::query()->inPops($popIds);
    }

    /**
     * Kandidat "Cabut SN Lama" (ganti aset) — SN yang SUDAH DEPLOYED di
     * titik jaringan mana pun di cabang task ini, BUKAN custody tim. Teknisi
     * bisa mencabut SN yang dipasang orang lain sebelumnya; begitu dicabut,
     * SN balik ke custody aktor (`retrieveSerialFromLocation()`).
     */
    private function eligibleInfraRetrievableSerialsForTeam(Task $task)
    {
        $nodeQuery = $this->infraNodeQueryForTask($task);

        if ($nodeQuery === null) {
            return collect();
        }

        // Posisi kanonis (Task 6): SN bisa terpasang di Mini POP, node, Slot,
        // atau Port — semua posisi dalam Cabang task ikut kandidat. Filter
        // ke lokasi TEPAT aksi tetap ditegakkan service saat simpan.
        $nodeIds = $nodeQuery->pluck('id');
        $cabangId = Pop::resolveCabangId($task->pop_id);
        $popIds = Pop::query()->where('parent_id', $cabangId)->pluck('id');
        // Slot = anak langsung perangkat, PON/Port = anak langsung Slot (semuanya node bertipe).
        $slotTypes = array_map(fn (InfrastructureSlotKind $kind) => $kind->typeCode(), InfrastructureSlotKind::cases());
        $slotIds = NetworkNode::query()->whereIn('parent_id', $nodeIds)
            ->whereHas('nodeType', fn ($query) => $query->whereIn('code', $slotTypes))->pluck('id');
        $portIds = NetworkNode::query()->whereIn('parent_id', $slotIds)
            ->whereHas('nodeType', fn ($query) => $query->whereIn('code', array_map(fn (InfrastructurePortKind $kind) => $kind->typeCode(), InfrastructurePortKind::cases())))->pluck('id');

        return InventorySerial::query()
            ->where('status', SerialStatus::DEPLOYED->value)
            ->where(fn ($query) => $query->whereIn('current_network_node_id', $nodeIds)
                ->orWhereIn('current_infrastructure_pop_id', $popIds)
                ->orWhereIn('current_mini_pop_id', $popIds)
                ->orWhereIn('current_infrastructure_slot_id', $slotIds)
                ->orWhereIn('current_infrastructure_port_id', $portIds))
            ->with('item', 'currentNetworkNode', 'currentMiniPop', 'currentInfrastructureSlot', 'currentInfrastructurePort')
            ->get();
    }

    public function report(Task $task)
    {
        $this->authorize('statusComplete', $task);

        if (! $task->status->acceptsReport()) {
            return redirect()->route('tasks.show', $task)->with('error', 'Status task tidak valid untuk pelaporan maintenance.');
        }

        // Pastikan bukan task survey atau pemasangan (yang punya form laporan khusus)
        if (in_array($task->task_type, [TaskType::SURVEY, TaskType::PEMASANGAN])) {
            return redirect()->route('tasks.show', $task)->with('error', 'Gunakan form laporan khusus untuk Survey / Pemasangan.');
        }

        // DEAC (ADHOC-86): punya form sendiri. Form Maintenance menuntut foto
        // OPM/speedtest dan menawarkan "pasang modem" — keduanya salah untuk
        // pencabutan alat. URL lama (bookmark/tombol lama) dialihkan, bukan 404.
        if ($task->task_type === TaskType::AMBIL_MODEM) {
            return redirect()->route('tasks.device-retrieval.report', $task);
        }

        $workToolService = app(TaskWorkToolService::class);

        // Anchor dicari lewat fop_tasks.task_id, BUKAN customer+kategori:
        // satu pelanggan bisa punya banyak task MTN sepanjang tahun, dan
        // "MTN terakhir milik pelanggan ini" akan menempel ke task yang salah.
        $fopTask = $workToolService->resolveTaskFor($task);

        // Split Aktif/Pasif (ADHOC-54) — form terpakai Maintenance dibatasi ke
        // item PASIF, sama pola Laporan Pemasangan (rancangan-ui.md §3.2-3.3).
        $items = Item::active()->with('category')->orderBy('name')->get()
            ->filter(fn (Item $item) => $item->effective_equipment_class === EquipmentClass::PASIF)
            ->values();
        $itemCategories = ItemCategory::active()->ordered()->where('equipment_class', EquipmentClass::PASIF->value)->get();
        $materialRows = $fopTask
            ? $fopTask->materials()->terpakai()->orderBy('id')->get()->map(fn ($row) => [
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'item_type' => $row->item_type,
                'qty' => (float) $row->qty,
                'unit' => $row->unit,
                'note' => $row->note,
            ])->all()
            : [];

        $workTools = WorkTool::options();
        $workToolRows = $workToolService->rowsFor($fopTask);

        $task->loadMissing('teamMembers');
        $eligibleSerials = $this->eligibleSerialsForTeam($task);
        $eligiblePassiveCustody = $this->eligiblePassiveCustodyForTeam($task);
        $eligibleRolls = $this->eligibleRollsForTeam($task);

        // Seksi "Pasang/Ganti Infrastruktur" — cuma ditampilkan view untuk
        // O-REQ/INFR REQ, jadi query-nya juga cuma jalan di dua tipe itu
        // (form ini dipakai MTN/C-REQ juga, gak ada gunanya nge-query pohon
        // node tiap buka laporan MTN).
        $showsInfraSection = in_array($task->task_type, [TaskType::OREQ, TaskType::INFR], true);
        $eligibleInfraSerials = $showsInfraSection ? $this->eligibleInfraSerialsForTeam($task) : collect();
        $eligibleInfraCustody = $showsInfraSection ? $this->eligibleInfraCustodyForTeam($task) : collect();
        $eligibleInfraRolls = $showsInfraSection ? $this->eligibleInfraRollsForTeam($task) : collect();
        $eligibleInfraRetrievableSerials = $showsInfraSection ? $this->eligibleInfraRetrievableSerialsForTeam($task) : collect();
        $infraNodeQuery = $showsInfraSection ? $this->infraNodeQueryForTask($task) : null;
        $infraNodeOptions = $infraNodeQuery
            ? $infraNodeQuery->with(['nodeType', 'anchorPop.parent', 'parent.anchorPop.parent'])->orderBy('code')->get()
            : collect();
        $infraMaterialSources = $showsInfraSection
            ? InventoryTransaction::query()
                ->where('type', InventoryTransactionType::DEPLOY->value)
                ->whereNull('serial_id')
                ->whereNotNull('to_network_node_id')
                ->whereIn('to_network_node_id', $infraNodeOptions->pluck('id'))
                ->with(['item', 'toNetworkNode'])
                ->orderByDesc('id')
                ->get()
            : collect();

        $infraNodeIds = $infraNodeOptions->pluck('id');
        $infraPortOptions = $showsInfraSection
            ? NetworkNodePort::query()->whereIn('network_node_id', $infraNodeIds)
                ->orderBy('network_node_id')->orderBy('number')->get()
            : collect();

        $infraMaterialActionKey = (string) Str::uuid();

        // Akar scope untuk dropdown bertingkat (Task 9). Task tanpa anchor
        // memakai FopTask sementara (tidak disimpan) — akar = Cabang task.
        $infraScope = $showsInfraSection
            ? app(InfrastructureLocationOptionsService::class)->rootPathFor(
                $fopTask ?? new FopTask(['pop_id' => $task->pop_id, 'category' => $task->task_type])
            )
            : null;

        // Dropdown Kategori C-REQ (docs/plan/task-teknisi/
        // rancangan-biaya-creq-verifikasi-cs.md §2) — cuma dipakai view kalau
        // task_type = CREQ, tapi selalu dioper biar view gak perlu query lagi.
        $creqCategories = CReqCategory::options();

        // Dropdown POP tujuan — kategori Migrasi (ADHOC-108). Semua Cabang
        // aktif, TANPA batas scope POP teknisi — keputusan pindah POP
        // datang dari CS/NOC, teknisi cuma eksekusi fisiknya.
        $cabangPops = Pop::query()->where('type', 'cabang')->where('status', 'active')
            ->orderBy('name')->get(['id', 'name']);

        return view($showsInfraSection ? 'tasks.infrastructure-report' : 'tasks.maintenance-report', compact('task', 'items', 'itemCategories', 'materialRows', 'workTools', 'workToolRows', 'eligibleSerials', 'eligiblePassiveCustody', 'eligibleRolls', 'creqCategories', 'cabangPops', 'eligibleInfraSerials', 'eligibleInfraCustody', 'eligibleInfraRolls', 'eligibleInfraRetrievableSerials', 'infraNodeOptions', 'infraPortOptions', 'infraMaterialSources', 'infraMaterialActionKey', 'infraScope'));
    }

    public function store(Request $request, Task $task, TaskService $taskService)
    {
        $this->authorize('statusComplete', $task);

        // Sama alasan `report()`: POST langsung ke sini untuk task DEAC tidak
        // boleh menyelesaikan task tanpa laporan alat.
        if ($task->task_type === TaskType::AMBIL_MODEM) {
            return redirect()->route('tasks.device-retrieval.report', $task);
        }

        $task->loadMissing('teamMembers');

        // Dihitung SEBELUM validate() supaya 'selected_inventory_serial_id'
        // bisa dibatasi Rule::in ke custody tim ini — pola sama
        // CustomerInstallationController::storePemasangan(), tapi field ini
        // NULLABLE di sini (lihat eligibleSerialsForTeam()).
        $eligibleSerialIds = $this->eligibleSerialsForTeam($task)->pluck('id');
        $eligibleRollIds = $this->eligibleRollsForTeam($task)->pluck('id');

        // Kategori C-REQ dibaca SEBELUM validate() supaya rule kondisional
        // (tikor wajib, custom name wajib, dst — docs/plan/task-teknisi/
        // rancangan-biaya-creq-verifikasi-cs.md §2) bisa dibentuk per kategori
        // yang benar-benar dikirim, bukan ditebak setelah validasi lolos.
        $isCreq = $task->task_type === TaskType::CREQ;
        $creqCategory = $isCreq ? CReqCategory::tryFrom((string) $request->input('creq_category')) : null;

        // SN custody cuma boleh ikut kalau pilihan ownership-nya memang
        // memasang modem dari gudang. Combobox SN cuma disembunyikan x-show —
        // nilai lama tetap ter-POST walau radio sudah dipindah ke 'Tidak
        // Ganti'/'Beli Sendiri'; kalau lolos, installSerial() tetap jalan
        // (Sewa/COMPANY) di atas pilihan yang bilang "tidak ada SN gudang".
        $modemOwnershipChoiceInput = $request->input('modem_ownership_choice');
        $serialProhibited = in_array($modemOwnershipChoiceInput, ['tidak_ganti', 'beli_sendiri'], true);
        $serialRequired = in_array($modemOwnershipChoiceInput, ['sewa_gudang', 'beli_gudang'], true);

        $task->loadMissing('customer');
        $currentPopId = $task->customer?->pop_id;

        $rules = [
            'kendala_teknis' => 'required|string',
            // Lima kolom teks di bawah adalah pencatatan material versi lama —
            // satu kolom per jenis barang, hardcode. Dipertahankan karena ada
            // laporan maintenance lama yang memakainya, tapi TIDAK lagi
            // ditampilkan di form: material sekarang lewat `materials[]` yang
            // terstruktur dan bisa diagregasi. Jangan hidupkan lagi sebagian.
            'kabel' => 'nullable|string|max:100',
            'modem' => 'nullable|string|max:100',
            'patchcord' => 'nullable|string|max:100',
            'sleeve' => 'nullable|string|max:100',
            'lainnya' => 'nullable|string|max:255',
            'opm_photo' => 'nullable|image|max:2048',
            'speedtest_photo' => 'nullable|image|max:2048',
            // Modem/Perangkat Aktif — OPSIONAL (beda dari Laporan Pemasangan
            // yang wajib). Maintenance gak selalu ganti modem; kalau teknisi
            // gak bawa/ganti, dibiarkan kosong dan gak nyentuh SN sama
            // sekali. Kalau diisi, wajib dari custody tim ini (Rule::in) —
            // SATU-SATUNYA sumber, gak ada teks manual (pola sama ADHOC-54).
            'selected_inventory_serial_id' => [
                Rule::requiredIf($serialRequired),
                Rule::prohibitedIf($serialProhibited),
                'nullable',
                'integer',
                Rule::in($eligibleSerialIds),
            ],
            // Ownership modem (rancangan ownership modem sewa/beli, 2026-10-09)
            // — radio di atas combobox SN existing. `required_with` (bukan
            // `required` polos): wajib begitu teknisi benar-benar pilih SN
            // dari custody, supaya `installSerial()` tau ownership apa yang
            // ditulis (Sewa Gudang = COMPANY, Beli dari Gudang = CUSTOMER).
            // 'beli_sendiri' JUSTRU gak perlu SN sama sekali (modem pelanggan
            // sendiri, gak lewat gudang — lihat `recordCustomerOwnedDevice()`),
            // makanya bukan required_if kebalik dari SN.
            'modem_ownership_choice' => ['nullable', 'required_with:selected_inventory_serial_id', Rule::in(['tidak_ganti', 'sewa_gudang', 'beli_sendiri', 'beli_gudang'])],
            'manual_device_brand' => ['nullable', 'string', 'max:100', 'required_if:modem_ownership_choice,beli_sendiri'],
            'manual_device_model' => ['nullable', 'string', 'max:100', 'required_if:modem_ownership_choice,beli_sendiri'],
            'manual_device_serial_number' => ['nullable', 'string', 'max:100', 'required_if:modem_ownership_choice,beli_sendiri'],
            'modem_sale_price' => ['nullable', 'numeric', 'min:0.01', 'required_if:modem_ownership_choice,beli_gudang'],
            // Roll kabel — OPSIONAL sama seperti SN di atas.
            'selected_inventory_roll_id' => ['nullable', 'integer', Rule::in($eligibleRollIds)],
            'roll_meters_used' => ['nullable', 'numeric', 'min:0.01', 'required_with:selected_inventory_roll_id'],
            // Material terpakai — bentuk payload sama persis dengan Laporan
            // Survey & Pemasangan supaya satu komponen form bisa dipakai tiga
            // halaman dan agregasinya membandingkan hal yang setara.
            //
            // Opsi "Lainnya (isi manual)" DICABUT dari dropdown Material
            // Terpakai (koreksi lanjutan ADHOC-54, 2026-09-12) — sama alasan
            // & pola persis `CustomerInstallationController::storePemasangan()`.
            // `required_with:qty`, BUKAN `required` polos — baris repeatable
            // kosong terakhir harus tetap lolos, dibuang diam-diam belakangan
            // oleh normalizeRow(). Kecukupan sisa custody per-item dicek
            // SETELAH validate() ini lolos, lihat blok setelah $validated.
            'materials' => 'nullable|array',
            'materials.*.item_id' => 'nullable|required_with:materials.*.qty|integer|exists:items,id',
            'materials.*.item_name' => 'nullable|string|max:150',
            'materials.*.item_type' => ['nullable', 'string', Rule::exists('item_categories', 'code')->where('is_active', true)],
            'materials.*.qty' => 'nullable|numeric|min:0',
            'materials.*.unit' => 'nullable|string|max:20',
            'materials.*.note' => 'nullable|string|max:255',
            'work_tools_ids' => 'nullable|array',
            'work_tools_ids.*' => 'nullable|integer|exists:work_tools,id',
            'work_tools_manual' => 'nullable|array',
            'work_tools_manual.*.tool_name' => 'nullable|string|max:100',
            'work_tools_manual.*.note' => 'nullable|string|max:255',
            // Task Berbayar untuk MTN polos (bukan C-REQ, yang punya field
            // `creq_is_billable`/`creq_billing_note` sendiri di dalam blok
            // $isCreq di bawah). Rule ini SENGAJA tetap permisif untuk SEMUA
            // task_type (bukan dikondisikan ke task_type === MAINTENANCE) —
            // yang menjaga kebenaran bukan validasi ini, tapi gerbang
            // eksekusi di blok updateOrCreate di bawah, yang diperketat ke
            // exact match `TaskType::MAINTENANCE` (final review 2026-10-10,
            // finding #1: `! $isCreq` dulu juga true untuk O-REQ/INFR REQ,
            // baris `TaskMaintenanceBillingDetail` kebentuk tapi permanen
            // tak kelihatan di antrean Verifikasi Biaya — lihat komentar di
            // sana). Checkbox sumber field ini juga sudah disembunyikan di
            // view untuk task_type selain MAINTENANCE, jadi path ini aman
            // dibiarkan permisif: tidak ada pengguna nyata yang bisa
            // memicunya untuk O-REQ/INFR REQ lewat UI.
            'mtn_is_billable' => ['nullable', 'boolean'],
            'mtn_billing_note' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->boolean('mtn_is_billable'))],
        ];

        $messages = [
            'mtn_billing_note.required' => 'Catatan biaya wajib diisi kalau task ini ditandai berbayar.',
            'selected_inventory_serial_id.in' => 'SN yang dipilih bukan bagian dari custody tim Anda saat ini. Pilih ulang dari daftar SN yang tersedia.',
            'selected_inventory_roll_id.in' => 'Roll kabel yang dipilih bukan bagian dari custody tim Anda saat ini. Pilih ulang dari daftar roll yang tersedia.',
            'roll_meters_used.required_with' => 'Meter terpakai wajib diisi kalau roll kabel dipilih.',
            'modem_ownership_choice.required_with' => 'Pilih cara pemilikan modem (Sewa Gudang/Beli dari Gudang) begitu SN dipilih dari custody.',
            'manual_device_brand.required_if' => 'Brand modem wajib diisi untuk Beli Sendiri.',
            'manual_device_model.required_if' => 'Model modem wajib diisi untuk Beli Sendiri.',
            'manual_device_serial_number.required_if' => 'Serial number modem wajib diisi untuk Beli Sendiri.',
            'modem_sale_price.required_if' => 'Harga jual modem wajib diisi untuk Beli dari Gudang.',
            'selected_inventory_serial_id.required' => 'Pilih SN modem dari custody tim untuk Sewa Gudang/Beli dari Gudang.',
            'selected_inventory_serial_id.prohibited' => 'SN custody hanya boleh dipilih untuk Sewa Gudang/Beli dari Gudang — kosongkan pilihan SN.',
        ];

        if ($isCreq) {
            $rules['creq_category'] = ['required', Rule::enum(CReqCategory::class)];
            $rules['creq_category_custom_name'] = ['nullable', 'string', 'max:150', Rule::requiredIf($creqCategory === CReqCategory::LAINNYA)];
            $requiresTikor = $creqCategory?->requiresTikor() ?? false;
            $rules['creq_tikor_lama_lat'] = ['nullable', 'numeric', 'between:-90,90', Rule::requiredIf($requiresTikor)];
            $rules['creq_tikor_lama_lng'] = ['nullable', 'numeric', 'between:-180,180', Rule::requiredIf($requiresTikor)];
            $rules['creq_tikor_baru_lat'] = ['nullable', 'numeric', 'between:-90,90', Rule::requiredIf($requiresTikor)];
            $rules['creq_tikor_baru_lng'] = ['nullable', 'numeric', 'between:-180,180', Rule::requiredIf($requiresTikor)];
            $rules['creq_is_billable'] = ['nullable', 'boolean'];
            $rules['creq_billing_note'] = ['nullable', 'string', 'max:1000', Rule::requiredIf($request->boolean('creq_is_billable'))];

            // Migrasi: POP tujuan wajib, harus Cabang aktif, dan beda dari
            // POP pelanggan sekarang — pindah ke POP yang sama bukan migrasi.
            if ($creqCategory?->requiresTargetPop()) {
                $rules['creq_target_pop_id'] = [
                    'required',
                    'integer',
                    Rule::exists('pops', 'id')->where('type', 'cabang')->where('status', 'active'),
                    function ($attribute, $value, $fail) use ($currentPopId) {
                        if ((int) $value === (int) $currentPopId) {
                            $fail('POP tujuan harus berbeda dari POP pelanggan saat ini.');
                        }
                    },
                ];
            }

            // Tambah Modem: perketat field SN yang di form ini defaultnya
            // opsional (Maintenance biasa boleh gak ganti modem) — kategori
            // ini WAJIB pilih dari custody tim, gak ada "Tidak ganti modem".
            if ($creqCategory?->requiresModem()) {
                $rules['selected_inventory_serial_id'] = ['required', Rule::prohibitedIf($serialProhibited), 'integer', Rule::in($eligibleSerialIds)];
            }

            $messages['creq_category_custom_name.required'] = 'Nama kategori wajib diisi untuk kategori Lainnya.';
            $messages['creq_tikor_lama_lat.required'] = 'Titik koordinat lama wajib diisi untuk kategori ini.';
            $messages['creq_tikor_lama_lng.required'] = 'Titik koordinat lama wajib diisi untuk kategori ini.';
            $messages['creq_tikor_baru_lat.required'] = 'Titik koordinat baru wajib diisi untuk kategori ini.';
            $messages['creq_tikor_baru_lng.required'] = 'Titik koordinat baru wajib diisi untuk kategori ini.';
            $messages['creq_billing_note.required'] = 'Catatan biaya wajib diisi kalau task ini ditandai berbayar.';
            $messages['selected_inventory_serial_id.required'] = 'Kategori Tambah Modem wajib memilih SN modem dari custody tim.';
            $messages['creq_target_pop_id.required'] = 'Kategori Migrasi wajib memilih POP tujuan.';
            $messages['creq_target_pop_id.exists'] = 'POP tujuan tidak valid.';
        }

        // Seksi "Pasang/Ganti Infrastruktur" — cuma O-REQ/INFR REQ. Rule-nya
        // SENGAJA cuma didaftarkan untuk dua tipe itu: field `infra_*` yang
        // ikut ter-POST di task MTN/C-REQ gak lolos ke `$validated`, jadi
        // blok eksekusi di bawah (yang cuma membaca `$validated`) gak pernah
        // jalan buat tipe lain.
        if (in_array($task->task_type, [TaskType::OREQ, TaskType::INFR], true)) {
            $rules['infra_actions'] = ['nullable', 'array', 'max:30'];
            $rules['infra_actions.*.action_key'] = ['required', 'uuid'];
            $rules['infra_actions.*.action_type'] = ['required', Rule::in(['inspect', 'install', 'retrieve', 'replace', 'port_connect', 'port_release'])];
            $rules['infra_actions.*.network_node_id'] = ['nullable', 'integer', 'exists:network_nodes,id'];
            $rules['infra_actions.*.serial_id'] = ['nullable', 'integer', 'exists:inventory_serials,id'];
            $rules['infra_actions.*.old_serial_id'] = ['nullable', 'integer', 'exists:inventory_serials,id'];
            $rules['infra_actions.*.item_id'] = ['nullable', 'integer', 'exists:items,id'];
            $rules['infra_actions.*.qty'] = ['nullable', 'numeric', 'min:0.01'];
            $rules['infra_actions.*.roll_id'] = ['nullable', 'integer', 'exists:inventory_rolls,id'];
            $rules['infra_actions.*.meters'] = ['nullable', 'numeric', 'min:0.01'];
            $rules['infra_actions.*.port_id'] = ['nullable', 'integer', 'exists:network_node_ports,id'];
            $rules['infra_actions.*.target_node_id'] = ['nullable', 'integer', 'exists:network_nodes,id'];
            $rules['infra_actions.*.target_port_id'] = ['nullable', 'integer', 'exists:network_node_ports,id'];
            $rules['infra_actions.*.result_note'] = ['nullable', 'string', 'max:1000'];
            // Dropdown lokasi bertingkat (§10.2): ID angka, `none`
            // (Dikosongkan), atau kosong (Belum dipilih). Hubungan induk–anak
            // & scope divalidasi InfrastructureReportLocationParser — `regex`
            // di sini hanya menolak bentuk nilai asing.
            foreach (InfrastructureReportLocationParser::LEVELS as $level) {
                $rules["infra_actions.*.{$level}"] = ['nullable', 'regex:/^(\d+|none)$/'];
            }
            $rules['infra_actions.*.end_from'] = ['nullable', 'string', 'max:40'];
            $rules['infra_actions.*.end_to'] = ['nullable', 'string', 'max:40'];
            $rules['infra_actions.*.work_latitude'] = ['nullable', 'numeric', 'between:-90,90'];
            $rules['infra_actions.*.work_longitude'] = ['nullable', 'numeric', 'between:-180,180'];

            $eligibleInfraSerialIds = $this->eligibleInfraSerialsForTeam($task)->pluck('id');
            $eligibleInfraRetrievableSerialIds = $this->eligibleInfraRetrievableSerialsForTeam($task)->pluck('id');
            $eligibleInfraCustodyItemIds = $this->eligibleInfraCustodyForTeam($task)->pluck('item_id');
            $eligibleInfraRollIds = $this->eligibleInfraRollsForTeam($task)->pluck('id');

            $anyInfraFieldFilled = $request->filled('infra_selected_serial_id')
                || $request->filled('infra_retrieve_old_serial_id')
                || $request->filled('infra_quantity_item_id')
                || $request->filled('infra_selected_roll_id');

            $taskCabangId = Pop::resolveCabangId($task->pop_id);
            $access = app(EffectiveAccessService::class);
            $actor = $request->user();

            $rules['infra_target_network_node_id'] = [
                Rule::requiredIf($anyInfraFieldFilled || $request->input('infra_material_action') === 'correct'),
                'nullable',
                'integer',
                Rule::exists('network_nodes', 'id'),
                // Dua gerbang, dua-duanya wajib:
                // 1) node harus di Cabang TASK ini — deploy untuk task Cabang
                //    A ke ODC Cabang B salah secara bisnis, walau aktornya
                //    ALL_POP (scope user gak akan menangkap ini);
                // 2) node harus di scope POP AKTOR (`assertNetworkNodeInScope()`,
                //    satu-satunya titik penegakan scope node).
                // `assertNetworkNodeInScope()` lempar HttpException (abort 403);
                // ditangkap di sini dan diubah jadi $fail() supaya muncul
                // sebagai error field (redirect back + old input), bukan
                // halaman 403 mentah yang membuang seluruh isian laporan.
                // JANGAN panggil method itu lagi di luar closure ini.
                function ($attribute, $value, $fail) use ($taskCabangId, $access, $actor, $task) {
                    if (! InfrastructureCutover::legacyWritesEnabled()) {
                        $fail('Form laporan node tunggal sudah ditutup. Gunakan dropdown lokasi bertingkat.');

                        return;
                    }

                    $node = NetworkNode::find($value);

                    if (! $node) {
                        return; // sudah ditolak Rule::exists
                    }

                    if ($taskCabangId === null || $node->resolvePop()?->id !== $taskCabangId) {
                        $fail('Titik jaringan yang dipilih bukan milik cabang task ini.');

                        return;
                    }

                    try {
                        $this->assertNetworkNodeInScope($node, $actor, $access);
                    } catch (HttpException $e) {
                        $fail($e->getMessage() ?: 'Titik jaringan yang dipilih di luar scope POP Anda.');

                        return;
                    }

                    // Form tunggal lama memakai guard yang SAMA dengan baris
                    // aksi (Task 8): node wajib di subtree akar scope task.
                    // Task tanpa anchor FopTask belum punya akar — anchor
                    // dibuat saat simpan dengan akar = Cabang task, yang
                    // sudah dicek di atas.
                    $anchor = FopTask::query()->where('task_id', $task->id)->first();
                    if ($anchor !== null) {
                        try {
                            app(FopTaskInfrastructureScopeService::class)->assertLocationInTaskScope(
                                $anchor, app(InfrastructureLocationResolver::class)->forNode($node), $actor, $attribute
                            );
                        } catch (ValidationException $e) {
                            $fail(collect($e->errors())->flatten()->first());
                        }
                    }
                },
            ];
            $rules['infra_selected_serial_id'] = ['nullable', 'integer', Rule::in($eligibleInfraSerialIds)];
            // Sumber BEDA dari SN pasang: kandidat cabut = SN yang sudah
            // DEPLOYED di titik jaringan cabang ini, bukan custody tim.
            $rules['infra_retrieve_old_serial_id'] = [
                'nullable', 'integer', Rule::in($eligibleInfraRetrievableSerialIds),
                function ($attribute, $value, $fail) use ($request): void {
                    if ($value !== null && $value !== '' && ! InventorySerial::query()
                        ->whereKey($value)->where('current_network_node_id', $request->input('infra_target_network_node_id'))
                        ->where('status', SerialStatus::DEPLOYED->value)->exists()) {
                        $fail('SN lama tidak terpasang pada titik jaringan yang dipilih.');
                    }
                },
            ];
            $rules['infra_quantity_item_id'] = ['nullable', 'integer', Rule::in($eligibleInfraCustodyItemIds)];
            $rules['infra_quantity_qty'] = ['nullable', 'numeric', 'min:0.01', 'required_with:infra_quantity_item_id'];
            $rules['infra_selected_roll_id'] = ['nullable', 'integer', Rule::in($eligibleInfraRollIds)];
            $rules['infra_roll_meters_used'] = ['nullable', 'numeric', 'min:0.01', 'required_with:infra_selected_roll_id'];
            $rules['infra_material_action_key'] = ['nullable', 'uuid'];
            $rules['infra_material_action'] = [
                'nullable',
                Rule::in(['correct', 'retrieve']),
                function ($attribute, $value, $fail) use ($anyInfraFieldFilled): void {
                    if ($anyInfraFieldFilled && $value !== null && $value !== '') {
                        $fail('Koreksi atau cabut qty/roll harus dilaporkan terpisah dari pemasangan barang baru.');
                    }
                },
            ];
            $rules['infra_material_correct_roll_id'] = [
                'nullable', 'integer', Rule::in($eligibleInfraRollIds),
                function ($attribute, $value, $fail) use ($request): void {
                    if ($value !== null && $value !== '' && $request->input('infra_material_action') !== 'correct') {
                        $fail('Roll pengganti hanya berlaku untuk koreksi administratif.');
                    }
                },
            ];
            $rules['infra_material_source_transaction_id'] = ['nullable', 'integer', 'exists:inventory_transactions,id', 'required_with:infra_material_action'];
            $rules['infra_material_qty'] = [
                'nullable', 'numeric', 'min:0', 'required_with:infra_material_action',
                function ($attribute, $value, $fail) use ($request): void {
                    if ($request->input('infra_material_action') === 'retrieve' && (float) $value <= 0) {
                        $fail('Jumlah cabut harus lebih besar dari nol.');
                    }
                },
            ];
            $rules['infra_material_reason'] = ['nullable', 'string', 'max:500', 'required_with:infra_material_action'];

            $messages['infra_target_network_node_id.required'] = 'Pilih titik jaringan tujuan kalau mengisi Pasang/Ganti Infrastruktur.';
            $messages['infra_selected_serial_id.in'] = 'SN infrastruktur yang dipilih bukan bagian dari custody tim Anda saat ini.';
            $messages['infra_retrieve_old_serial_id.in'] = 'SN lama yang dipilih tidak sedang terpasang di titik jaringan cabang ini.';
            $messages['infra_quantity_item_id.in'] = 'Barang yang dipilih tidak ada di custody aset infrastruktur tim Anda.';
            $messages['infra_quantity_qty.required_with'] = 'Qty terpakai wajib diisi kalau barang qty dipilih.';
            $messages['infra_selected_roll_id.in'] = 'Roll yang dipilih bukan bagian dari custody aset infrastruktur tim Anda.';
            $messages['infra_roll_meters_used.required_with'] = 'Meter terpakai wajib diisi kalau roll dipilih.';
        }

        $validated = $request->validate($rules, $messages);

        if (! empty($validated['infra_actions']) && (
            ! empty($validated['infra_selected_serial_id'])
            || ! empty($validated['infra_retrieve_old_serial_id'])
            || ! empty($validated['infra_quantity_item_id'])
            || ! empty($validated['infra_selected_roll_id'])
            || ! empty($validated['infra_material_action'])
        )) {
            throw ValidationException::withMessages(['infra_actions' => 'Pilih satu jalur laporan Infrastruktur: baris aksi atau form lama.']);
        }

        if (in_array($task->task_type, [TaskType::OREQ, TaskType::INFR], true)) {
            $infraRows = collect($validated['infra_actions'] ?? []);
            $infraItemIds = $infraRows->pluck('item_id')->filter()
                ->merge(array_filter([$validated['infra_quantity_item_id'] ?? null]))->map(fn ($id) => (int) $id);
            $genericItemIds = collect($validated['materials'] ?? [])->pluck('item_id')->filter()->map(fn ($id) => (int) $id);
            $infraSerialIds = $infraRows->pluck('serial_id')->filter()
                ->merge(array_filter([$validated['infra_selected_serial_id'] ?? null]))->map(fn ($id) => (int) $id);
            $infraRollIds = $infraRows->pluck('roll_id')->filter()
                ->merge(array_filter([$validated['infra_selected_roll_id'] ?? null]))->map(fn ($id) => (int) $id);

            if ($infraItemIds->intersect($genericItemIds)->isNotEmpty()
                || $infraSerialIds->contains((int) ($validated['selected_inventory_serial_id'] ?? 0))
                || $infraRollIds->contains((int) ($validated['selected_inventory_roll_id'] ?? 0))) {
                throw ValidationException::withMessages([
                    'infra_actions' => 'Barang, SN, atau roll yang sama tidak boleh dicatat melalui seksi material umum dan Infrastruktur.',
                ]);
            }
        }

        // Default 'tidak_ganti' kalau field kosong (SN gak dipilih) — perilaku
        // lama (ownership modem sewa/beli, 2026-10-09). Dipakai di dua titik
        // eksekusi di bawah: cabang "beli_sendiri" (gak butuh SN) dan cabang
        // SN-install (ownership COMPANY/CUSTOMER + sale).
        $modemOwnershipChoice = $validated['modem_ownership_choice'] ?? 'tidak_ganti';

        // Sisa custody Material Terpakai (koreksi lanjutan ADHOC-54,
        // 2026-09-12) — pagar UI/UX, SEBELUM data disimpan. Penegakan final
        // tetap `InventoryService::consumeFromCustody()` di bawah (dalam
        // request yang SAMA — Maintenance/C-REQ/O-REQ/INFR one-shot, beda
        // dari Pemasangan yang dua fase — tapi cek di sini tetap berguna:
        // gagal SEBELUM DB::beginTransaction() dan foto ke-upload, bukan
        // gagal di tengah lalu rollback).
        $requestedQtyByItem = collect($validated['materials'] ?? [])
            ->filter(fn ($row) => ! empty($row['item_id']) && (float) ($row['qty'] ?? 0) > 0)
            ->groupBy('item_id')
            ->map(fn ($rows) => (float) $rows->sum('qty'));

        if ($requestedQtyByItem->isNotEmpty()) {
            $eligiblePassiveCustody = $this->eligiblePassiveCustodyForTeam($task)->keyBy('item_id');

            foreach ($requestedQtyByItem as $itemId => $qtyRequested) {
                $available = (float) ($eligiblePassiveCustody[$itemId]['available'] ?? 0);

                if ($qtyRequested > $available) {
                    $itemName = $eligiblePassiveCustody[$itemId]['name'] ?? Item::find($itemId)?->name ?? "Barang #{$itemId}";

                    throw ValidationException::withMessages([
                        'materials' => "Sisa custody tim untuk {$itemName} tidak cukup: diklaim ".number_format($qtyRequested, 2).', tersedia '.number_format($available, 2).'. Ambil tambahan barang dari Gudang (Issue) atau kurangi jumlah yang dicatat.',
                    ]);
                }
            }
        }

        try {
            DB::beginTransaction();

            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            $this->authorize('statusComplete', $task);
            $task->loadMissing('teamMembers', 'customer');
            $opmPhotoPath = null;
            if ($request->hasFile('opm_photo')) {
                $opmPhotoPath = FileUploadService::uploadMaintenancePhoto($request->file('opm_photo'), $task->customer, 'opm');
            }

            $speedtestPhotoPath = null;
            if ($request->hasFile('speedtest_photo')) {
                $speedtestPhotoPath = FileUploadService::uploadMaintenancePhoto($request->file('speedtest_photo'), $task->customer, 'speedtest');
            }

            $task->maintenanceReport()->create([
                'kendala_teknis' => $validated['kendala_teknis'],
                'kabel' => $validated['kabel'] ?? null,
                'modem' => $validated['modem'] ?? null,
                'patchcord' => $validated['patchcord'] ?? null,
                'sleeve' => $validated['sleeve'] ?? null,
                'lainnya' => $validated['lainnya'] ?? null,
                'opm_photo' => $opmPhotoPath,
                'speedtest_photo' => $speedtestPhotoPath,
            ]);

            // Detail kategori C-REQ + antrean verifikasi biaya (§3-4 rancangan)
            // — cuma dibuat untuk task_type CREQ, gak nyentuh MTN/O-REQ/INFR REQ.
            //
            // updateOrCreate (bukan create): laporan C-REQ bisa dikirim ulang
            // setelah FOP menolak (review reject → in_progress). Dulu tiap
            // kirim ulang menambah baris baru, sementara relasi hasOne
            // creqDetail tetap membaca baris lama yang sudah diproses — CS
            // kena 422 "sudah diproses" terus dan task nyangkut di antrean.
            // Laporan baru = klaim biaya baru, jadi status verifikasi ikut
            // dikembalikan ke Menunggu. Dijaga unique index task_id.
            if ($isCreq) {
                TaskCreqDetail::updateOrCreate(['task_id' => $task->id], [
                    'category' => $validated['creq_category'],
                    'category_custom_name' => $validated['creq_category_custom_name'] ?? null,
                    'target_pop_id' => $validated['creq_target_pop_id'] ?? null,
                    'tikor_lama_lat' => $validated['creq_tikor_lama_lat'] ?? null,
                    'tikor_lama_lng' => $validated['creq_tikor_lama_lng'] ?? null,
                    'tikor_baru_lat' => $validated['creq_tikor_baru_lat'] ?? null,
                    'tikor_baru_lng' => $validated['creq_tikor_baru_lng'] ?? null,
                    'is_billable' => $request->boolean('creq_is_billable'),
                    'billing_note' => $validated['creq_billing_note'] ?? null,
                    'verification_status' => CReqVerificationStatus::PENDING->value,
                    'verified_by' => null,
                    'verified_at' => null,
                    'rejection_reason' => null,
                ]);
            }

            // Antrean verifikasi biaya untuk MTN polos SAJA (bukan C-REQ,
            // yang sudah ditangani blok TaskCreqDetail di atas). Review akhir
            // (2026-10-10, finding #1): SEMPAT dipakai juga untuk O-REQ/INFR
            // REQ lewat guard `! $isCreq` (true untuk MAINTENANCE, OREQ,
            // DAN INFR) — tapi antrean Verifikasi Biaya
            // (`TaskCreqBillingController::index()`/`authorizeBillable()`)
            // CUMA mengenali CREQ & MAINTENANCE, O-REQ/INFR jatuh ke
            // `default => false`. Akibatnya baris `is_billable=true` buat
            // O-REQ/INFR kebentuk tapi PERMANEN gak kelihatan di antrean CS —
            // kebocoran uang senyap. Diperketat ke exact match MAINTENANCE;
            // dropping O-REQ/INFR REQ di sini SENGAJA, bukan widening antrean
            // (butuh keputusan produk soal kategori invoice yang belum ada).
            // Beda dari C-REQ yang SELALU updateOrCreate tiap submit — baris
            // MTN cuma dibuat kalau benar-benar ditandai berbayar. MTN polos
            // jauh lebih sering TIDAK berbayar, baris kosong is_billable=false
            // tiap laporan cuma nambah data percuma di antrean verifikasi CS.
            if ($task->task_type === TaskType::MAINTENANCE && $request->boolean('mtn_is_billable')) {
                TaskMaintenanceBillingDetail::updateOrCreate(['task_id' => $task->id], [
                    'is_billable' => true,
                    'billing_note' => $validated['mtn_billing_note'],
                    'verification_status' => CReqVerificationStatus::PENDING->value,
                    'verified_by' => null,
                    'verified_at' => null,
                    'rejection_reason' => null,
                ]);
            }

            // Eksekusi pindah POP untuk kategori Migrasi (ADHOC-108). Cuma
            // `$customer->update(['pop_id' => ...])` biasa — SEMUA invariant
            // (guard piutang, lepas kolektor, hitung ulang CID, pindah
            // tagihan bulan berjalan) sudah ditegakkan `CustomerObserver`
            // (lihat CLAUDE.md § Pindah Cabang), satu sumber yang sama
            // dengan Edit Pelanggan. Guard piutang lempar
            // `CustomerRelocationBlockedException` yang ditangkap catch
            // umum di bawah dan tampil sebagai pesan error laporan — task
            // TIDAK selesai, teknisi harus minta CS lunaskan piutang dulu.
            if ($isCreq && $creqCategory?->requiresTargetPop() && $task->customer) {
                $task->customer->update(['pop_id' => (int) $validated['creq_target_pop_id']]);
            }

            // Material & alat menempel di FopTask, bukan di maintenance_reports.
            // Alasannya sama dengan ADHOC-11: FopTask entitas yang dimiliki
            // SEMUA jenis pekerjaan, jadi laporan pemakaian material lintas
            // kategori (PSB + MTN + C-REQ) cukup satu query.
            //
            // Kalau anchor-nya belum ada (task dibuat manual tanpa FopTask),
            // baris dilewat — laporan maintenance tetap tersimpan. Menggagalkan
            // laporan yang fotonya sudah diunggah cuma karena anchor belum
            // terbentuk jelas lebih merugikan.
            // Beli Sendiri (rancangan ownership modem sewa/beli, 2026-10-09)
            // — modem milik pelanggan sendiri, TIDAK PERNAH lewat gudang:
            // TIDAK memanggil `installSerial()` sama sekali, cuma menulis
            // `customer_devices` manual. Gak gantung custody tim/FopTask
            // (beda dari cabang SN-install di bawah), jadi dieksekusi di
            // sini, bukan di dalam blok `if ($fopTask)`.
            if ($modemOwnershipChoice === 'beli_sendiri' && $task->customer) {
                app(InventoryService::class)->recordCustomerOwnedDevice($task->customer, [
                    'brand' => $validated['manual_device_brand'],
                    'model' => $validated['manual_device_model'],
                    'serial_number' => $validated['manual_device_serial_number'],
                ], auth()->user());
            }

            $workToolService = app(TaskWorkToolService::class);
            $fopTask = $workToolService->resolveTaskFor($task);

            if (! $fopTask && in_array($task->task_type, [TaskType::OREQ, TaskType::INFR], true)) {
                $fopTask = app(FopTaskProvisioningService::class)->ensureForTask($task);
            }

            if (! $fopTask && $task->customer) {
                $fopTask = FopTask::create([
                    'task_number' => app(NumberSequenceService::class)->fopTaskNumber(),
                    'task_id' => $task->id,
                    'task_date' => $task->scheduled_at ?? now(),
                    'category' => $task->task_type,
                    'tugas' => $task->customer->display_id.'_'.$task->customer->full_name,
                    'village_id' => $task->customer->village_id,
                    'pop_id' => $task->pop_id,
                    'customer_id' => $task->customer->id,
                    'issue' => $task->description ?? $task->title,
                    'status' => TaskStatus::SELESAI,
                    'priority' => FopTaskPriority::MEDIUM,
                ]);
            }

            if ($fopTask) {
                app(TaskMaterialService::class)->sync(
                    $fopTask,
                    MaterialKind::TERPAKAI,
                    $validated['materials'] ?? [],
                    auth()->id()
                );

                $workToolService->sync(
                    $fopTask,
                    $workToolService->rowsFromRequest(
                        $validated['work_tools_ids'] ?? [],
                        $validated['work_tools_manual'] ?? []
                    ),
                    auth()->id()
                );

                // Reconcile custody Gudang/Inventory (ADHOC-54) — SETELAH sync(),
                // SEBELUM complete(). Beda dari Pemasangan (dua fase, resubmit-safe):
                // Maintenance one-shot — sync()+complete() dalam SATU request yang
                // gak bisa diulang (begitu task selesai, statusComplete policy
                // nolak store() lagi) — jadi aman rekonsiliasi langsung di sini,
                // gak perlu titik penyelesaian terpisah kayak storeSpeedtest().
                if ($task->customer && $task->teamMembers->isNotEmpty()) {
                    $teamTechnicians = User::whereIn('id', $task->teamMembers->pluck('user_id'))->get();
                    app(InventoryService::class)->reconcileMaterialsAgainstCustody($fopTask, $task->customer, $teamTechnicians, auth()->user());

                    // Modem/Perangkat Aktif — OPSIONAL, beda dari Pemasangan.
                    // Cuma jalan kalau teknisi benar-benar pilih SN dari
                    // custody-nya (ganti/pasang modem saat maintenance).
                    // Gak ada draft pointer terpisah kayak
                    // `customer_installations.selected_inventory_serial_id`
                    // — Maintenance one-shot (sync()+complete() satu request,
                    // gak bisa resubmit), jadi aman langsung INSTALL di sini.
                    //
                    // Kategori C-REQ TAMBAH_MODEM = pelanggan beneran NAMBAH
                    // (>1 modem aktif bersamaan), BUKAN ganti — modem lama
                    // TIDAK BOLEH ketarik otomatis (koreksi 2026-10-08, beda
                    // dari semua task_type/kategori lain yang defaultnya
                    // ganti/swap).
                    if (! empty($validated['selected_inventory_serial_id'])) {
                        $serial = InventorySerial::findOrFail($validated['selected_inventory_serial_id']);
                        $returnExistingSerial = ! ($isCreq && $creqCategory?->addsModemWithoutReturningExisting());

                        // Ownership modem (rancangan ownership modem sewa/beli,
                        // 2026-10-09) — 'beli_gudang' = SN dijual ke pelanggan
                        // (ownership CUSTOMER di `inventory_serials`), selain
                        // itu ('sewa_gudang'/'tidak_ganti' tapi SN tetap
                        // kepilih) = perilaku lama (ownership COMPANY).
                        $ownership = $modemOwnershipChoice === 'beli_gudang' ? DeviceOwnership::CUSTOMER : DeviceOwnership::COMPANY;

                        app(InventoryService::class)->installSerial($serial, $task->customer, $fopTask, $teamTechnicians, auth()->user(), $returnExistingSerial, $ownership);

                        // Ledger penjualan — CUMA kalau beneran "Beli dari
                        // Gudang". `modem_sale_price` sudah wajib diisi lewat
                        // validasi (`required_if:modem_ownership_choice,beli_gudang`)
                        // begitu lolos ke sini.
                        if ($modemOwnershipChoice === 'beli_gudang') {
                            app(InventoryService::class)->recordSerialSale($serial, $task->customer, $fopTask, (float) $validated['modem_sale_price'], auth()->user());
                        }
                    }

                    // Roll kabel — OPSIONAL sama pola SN di atas, one-shot
                    // langsung potong-meter di sini (gak ada draft pointer).
                    if (! empty($validated['selected_inventory_roll_id']) && ! empty($validated['roll_meters_used'])) {
                        $roll = InventoryRoll::findOrFail($validated['selected_inventory_roll_id']);
                        app(InventoryService::class)->consumeFromRoll($roll, (float) $validated['roll_meters_used'], $teamTechnicians, $fopTask, $task->customer, auth()->user());
                    }
                }

                if (! empty($validated['infra_actions'])) {
                    $infraTechnicians = $teamTechnicians ?? User::whereIn('id', $task->teamMembers->pluck('user_id'))->get();
                    app(TaskInfrastructureActionService::class)->record(
                        $task, $fopTask, $validated['infra_actions'], $infraTechnicians, auth()->user()
                    );
                }

                // Pasang/Ganti Infrastruktur (O-REQ/INFR REQ) — SENGAJA di luar
                // blok `if ($task->customer && ...)` di atas: INFR REQ lazimnya
                // pekerjaan jaringan murni TANPA pelanggan, dan deploy ke titik
                // jaringan gak butuh pelanggan sama sekali. Kalau ditaruh di
                // dalam blok itu, isian yang sudah lolos validasi bakal
                // DILEWATI DIAM-DIAM untuk task tanpa pelanggan — teknisi
                // mengira aset sudah tercatat terpasang, padahal SN masih di
                // custody-nya. `$teamTechnicians` dipakai ulang kalau blok di
                // atas sudah me-resolve-nya.
                if (! empty($validated['infra_target_network_node_id']) && empty($validated['infra_material_action'])) {
                    $infraTechnicians = $teamTechnicians ?? User::whereIn('id', $task->teamMembers->pluck('user_id'))->get();
                    $this->deployInfrastructure($validated, $fopTask, $infraTechnicians);
                }

                if (! empty($validated['infra_material_action'])) {
                    $source = InventoryTransaction::findOrFail($validated['infra_material_source_transaction_id']);
                    $materialService = app(InfrastructureMaterialService::class);
                    $actionKey = hash('sha256', implode('|', [
                        'task-infra-material', $task->id,
                        $validated['infra_material_action_key'] ?? implode(':', [$source->id, $validated['infra_material_action']]),
                    ]));
                    $reason = $validated['infra_material_reason'];
                    $qty = (float) $validated['infra_material_qty'];

                    if ($validated['infra_material_action'] === 'correct') {
                        $target = NetworkNode::findOrFail($validated['infra_target_network_node_id']);
                        if (! empty($validated['infra_material_correct_roll_id'])) {
                            $replacementRoll = InventoryRoll::findOrFail($validated['infra_material_correct_roll_id']);
                            $materialService->correctRoll($source, $replacementRoll, $target, $qty, $fopTask, auth()->user(), $reason, $actionKey);
                        } else {
                            $materialService->correct($source, $target, $qty, $fopTask, auth()->user(), $reason, $actionKey);
                        }
                    } else {
                        $materialService->retrieve($source, $qty, $fopTask, auth()->user(), $reason, $actionKey);
                    }
                }
            } elseif (! empty($validated['infra_target_network_node_id']) || ! empty($validated['infra_material_action'])) {
                // Gak ada FopTask (task manual tanpa pelanggan & tanpa anchor)
                // — semua method deploy butuh FopTask buat ledger. Gagal keras
                // (rollback seluruh laporan) lebih baik daripada laporan
                // selesai tapi aset gak pernah tercatat terpasang.
                throw new RuntimeException('Task ini belum punya Task FOP — pemasangan infrastruktur tidak bisa dicatat. Hubungi FOP.');
            }

            // Selesaikan task
            $taskService->complete($task, auth()->user());

            DB::commit();

            return redirect()->route('tasks.show', $task)->with('success', 'Laporan maintenance berhasil disimpan dan task diselesaikan.');

        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    /**
     * Eksekusi seksi "Pasang/Ganti Infrastruktur" — dipanggil dari DALAM
     * `DB::beginTransaction()` milik `store()`, jadi gagal di langkah mana
     * pun (mis. custody kurang) me-rollback seluruh laporan, termasuk SN
     * lama yang sudah sempat dicabut.
     *
     * Urutan: cabut SN lama DULU, baru pasang — ganti aset = satu kejadian
     * fisik, dan ledger RETRIEVE_INFRA sebaiknya mendahului DEPLOY penggantinya.
     *
     * @param  array<string, mixed>  $validated
     * @param  iterable<User>  $technicians
     */
    private function deployInfrastructure(array $validated, FopTask $fopTask, iterable $technicians): void
    {
        $inventory = app(InventoryService::class);
        $actor = auth()->user();
        $node = NetworkNode::findOrFail($validated['infra_target_network_node_id']);

        if (! empty($validated['infra_retrieve_old_serial_id'])) {
            $oldSerial = InventorySerial::findOrFail($validated['infra_retrieve_old_serial_id']);
            $inventory->retrieveSerialFromLocation($oldSerial, $fopTask, $actor, $node);
        }

        if (! empty($validated['infra_selected_serial_id'])) {
            $infraSerial = InventorySerial::findOrFail($validated['infra_selected_serial_id']);
            $inventory->deploySerial($infraSerial, $node, $fopTask, $technicians, $actor);
        }

        if (! empty($validated['infra_quantity_item_id']) && ! empty($validated['infra_quantity_qty'])) {
            $infraItem = Item::findOrFail($validated['infra_quantity_item_id']);
            $inventory->deployQuantity($technicians, $infraItem, (float) $validated['infra_quantity_qty'], $node, $fopTask, $actor);
        }

        if (! empty($validated['infra_selected_roll_id']) && ! empty($validated['infra_roll_meters_used'])) {
            $infraRoll = InventoryRoll::findOrFail($validated['infra_selected_roll_id']);
            $inventory->deployRoll($infraRoll, (float) $validated['infra_roll_meters_used'], $node, $technicians, $fopTask, $actor);
        }
    }
}
