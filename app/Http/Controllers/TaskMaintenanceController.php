<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentClass;
use App\Enums\MaterialKind;
use App\Enums\OwnershipMode;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TrackingType;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Task;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\WorkTool;
use App\Services\FileUploadService;
use App\Services\InventoryService;
use App\Services\TaskMaterialService;
use App\Services\TaskService;
use App\Services\TaskWorkToolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskMaintenanceController extends Controller
{
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

        return $teamTechnicianIds === []
            ? collect()
            : InventorySerial::query()
                ->whereIn('current_technician_id', $teamTechnicianIds)
                ->where('status', SerialStatus::ISSUED->value)
                ->whereHas('item', fn ($q) => $q->where('ownership_mode', OwnershipMode::INSTALLABLE->value))
                ->with('item')
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

        return TechnicianCustody::query()
            ->whereIn('technician_id', $teamTechnicianIds)
            ->active()
            ->with('item.category')
            ->get()
            ->filter(fn (TechnicianCustody $custody) => $custody->item
                && $custody->item->tracking_type !== TrackingType::SERIALIZED
                && $custody->item->effective_equipment_class === EquipmentClass::PASIF)
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

    public function report(Task $task)
    {
        $this->authorize('statusComplete', $task);

        if (! in_array($task->status->value, [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])) {
            return redirect()->route('tasks.show', $task)->with('error', 'Status task tidak valid untuk pelaporan maintenance.');
        }

        // Pastikan bukan task survey atau pemasangan (yang punya form laporan khusus)
        if (in_array($task->task_type, [TaskType::SURVEY, TaskType::PEMASANGAN])) {
            return redirect()->route('tasks.show', $task)->with('error', 'Gunakan form laporan khusus untuk Survey / Pemasangan.');
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

        return view('tasks.maintenance-report', compact('task', 'items', 'itemCategories', 'materialRows', 'workTools', 'workToolRows', 'eligibleSerials', 'eligiblePassiveCustody'));
    }

    public function store(Request $request, Task $task, TaskService $taskService)
    {
        $this->authorize('statusComplete', $task);

        $task->loadMissing('teamMembers');

        // Dihitung SEBELUM validate() supaya 'selected_inventory_serial_id'
        // bisa dibatasi Rule::in ke custody tim ini — pola sama
        // CustomerInstallationController::storePemasangan(), tapi field ini
        // NULLABLE di sini (lihat eligibleSerialsForTeam()).
        $eligibleSerialIds = $this->eligibleSerialsForTeam($task)->pluck('id');

        $validated = $request->validate([
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
            'opm_photo' => 'required|image|max:2048',
            'speedtest_photo' => 'required|image|max:2048',
            // Modem/Perangkat Aktif — OPSIONAL (beda dari Laporan Pemasangan
            // yang wajib). Maintenance gak selalu ganti modem; kalau teknisi
            // gak bawa/ganti, dibiarkan kosong dan gak nyentuh SN sama
            // sekali. Kalau diisi, wajib dari custody tim ini (Rule::in) —
            // SATU-SATUNYA sumber, gak ada teks manual (pola sama ADHOC-54).
            'selected_inventory_serial_id' => ['nullable', 'integer', Rule::in($eligibleSerialIds)],
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
        ], [
            'selected_inventory_serial_id.in' => 'SN yang dipilih bukan bagian dari custody tim Anda saat ini. Pilih ulang dari daftar SN yang tersedia.',
        ]);

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

            $task->loadMissing('customer');
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

            // Material & alat menempel di FopTask, bukan di maintenance_reports.
            // Alasannya sama dengan ADHOC-11: FopTask entitas yang dimiliki
            // SEMUA jenis pekerjaan, jadi laporan pemakaian material lintas
            // kategori (PSB + MTN + C-REQ) cukup satu query.
            //
            // Kalau anchor-nya belum ada (task dibuat manual tanpa FopTask),
            // baris dilewat — laporan maintenance tetap tersimpan. Menggagalkan
            // laporan yang fotonya sudah diunggah cuma karena anchor belum
            // terbentuk jelas lebih merugikan.
            $workToolService = app(TaskWorkToolService::class);
            $fopTask = $workToolService->resolveTaskFor($task);

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
                    if (! empty($validated['selected_inventory_serial_id'])) {
                        $serial = InventorySerial::findOrFail($validated['selected_inventory_serial_id']);
                        app(InventoryService::class)->installSerial($serial, $task->customer, $fopTask, $teamTechnicians, auth()->user());
                    }
                }
            }

            // Selesaikan task
            $taskService->complete($task, auth()->user());

            DB::commit();

            return redirect()->route('tasks.show', $task)->with('success', 'Laporan maintenance berhasil disimpan dan task diselesaikan.');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }
}
