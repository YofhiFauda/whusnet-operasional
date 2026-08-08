<?php

namespace App\Http\Controllers;

use App\Enums\MaterialKind;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Task;
use App\Models\WorkTool;
use App\Services\FileUploadService;
use App\Services\TaskMaterialService;
use App\Services\TaskService;
use App\Services\TaskWorkToolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskMaintenanceController extends Controller
{
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

        $items = Item::active()->with('category')->orderBy('name')->get();
        $itemCategories = ItemCategory::options();
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

        return view('tasks.maintenance-report', compact('task', 'items', 'itemCategories', 'materialRows', 'workTools', 'workToolRows'));
    }

    public function store(Request $request, Task $task, TaskService $taskService)
    {
        $this->authorize('statusComplete', $task);

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
            // Material terpakai — bentuk payload sama persis dengan Laporan
            // Survey & Pemasangan supaya satu komponen form bisa dipakai tiga
            // halaman dan agregasinya membandingkan hal yang setara.
            'materials' => 'nullable|array',
            'materials.*.item_id' => 'nullable|integer|exists:items,id',
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
        ]);

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
