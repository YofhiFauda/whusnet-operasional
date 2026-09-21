<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentClass;
use App\Enums\MaterialKind;
use App\Enums\OwnershipMode;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TrackingType;
use App\Enums\WorkflowTransition;
use App\Events\InstallationActivated;
use App\Events\InstallationCompleted;
use App\Events\InstallationStarted;
use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Task;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\WorkTool;
use App\Services\CustomerWorkflowService;
use App\Services\FileUploadService;
use App\Services\FopTaskProvisioningService;
use App\Services\InventoryService;
use App\Services\TaskMaterialService;
use App\Services\TaskService;
use App\Services\TaskWorkToolService;
use App\Services\TelegramBotService;
use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CustomerInstallationController extends Controller
{
    public function start(Request $request, Customer $customer, CustomerWorkflowService $workflowService, TaskService $taskService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.update'), 403);

        if ($customer->status !== 'waiting_installation') {
            return redirect()->back()->with('error', 'Pelanggan tidak dalam status menunggu pemasangan.');
        }

        $task = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::PEMASANGAN->value)
            ->where('status', TaskStatus::TERJADWAL->value)
            ->latest('id')
            ->first();

        // WAJIB — sebelumnya cuma dicek kalau $task ketemu (`if ($task) {...}`),
        // jadi teknisi mana pun yang punya permission generik
        // customers.detail.installation.update bisa mulai pemasangan pelanggan
        // MANA PUN tanpa pernah dijadwalkan FOP (bug RBAC — null Task paling
        // sering kejadian justru karena Task-nya masih Draft, BELUM
        // dijadwalkan, bukan berarti "gak perlu dicek"). hasFullAccess() tetap
        // boleh override buat intervensi manual Owner/Admin.
        abort_unless(
            auth()->user()->hasFullAccess()
                || ($task && $task->teamMembers->pluck('user_id')->contains(auth()->id())),
            403,
            'Anda belum dijadwalkan untuk pemasangan pelanggan ini — tunggu penjadwalan dari FOP sebelum memulai pemasangan.'
        );

        $memberIds = $task ? $task->teamMembers()->pluck('user_id')->toArray() : [auth()->id()];
        if (! in_array(auth()->id(), $memberIds)) {
            $memberIds[] = auth()->id();
        }

        $activeTask = Task::where('status', TaskStatus::IN_PROGRESS->value)
            ->whereHas('teamMembers', fn ($q) => $q->whereIn('user_id', $memberIds))
            ->when($task, fn ($q) => $q->where('id', '!=', $task->id))
            ->first();

        if ($activeTask) {
            return redirect()->back()->with('error', "Tidak dapat memulai pemasangan karena teknisi sedang mengerjakan task lain [{$activeTask->task_number}]. Selesaikan atau laporkan (pending) task sebelumnya terlebih dahulu.");
        }

        try {
            DB::transaction(function () use ($customer, $workflowService, $taskService, $task) {
                $installation = $customer->installations()->latest()->first();

                $updateData = [
                    'started_at' => now(),
                    'start_time' => now()->toTimeString(),
                    'installation_status' => 'in_progress',
                ];
                if ($task) {
                    $updateData['fop_id'] = $task->fop_id ?? $task->created_by;
                }

                if ($installation) {
                    $installation->update($updateData);
                } else {
                    $customer->installations()->create($updateData);
                }

                $workflowService->transition($customer, 'installation_in_progress', 'Mulai proses pemasangan lapangan');

                if ($task) {
                    $taskService->start($task, auth()->user());
                }

                // Broadcast Event
                broadcast(new InstallationStarted($customer))->toOthers();
            }, 3);

            return redirect()->back()->with('success', 'Waktu pemasangan berhasil dimulai.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    /**
     * Batalkan pemasangan — satu-satunya jalur cancel Task PEMASANGAN
     * (Task.cancel dikunci di TaskPolicy buat task_type ini, lihat
     * TaskPolicy::cancel()). Pola sama persis CustomerSurveyController::cancel().
     */
    public function cancel(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.reject'), 403);

        abort_unless(
            in_array($customer->status, ['waiting_installation', 'installation_in_progress', 'revision_installation']),
            422,
            'Pemasangan pelanggan ini tidak bisa dibatalkan dari status saat ini: '.$customer->status
        );

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($customer, $validated, $workflowService) {
            $task = Task::where('customer_id', $customer->id)
                ->where('task_type', TaskType::PEMASANGAN->value)
                ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
                ->latest('id')
                ->first();

            if ($task) {
                app(TaskService::class)->cancel($task, auth()->user(), $validated['reason']);
            }

            $installation = $customer->installations()->latest()->first();
            if ($installation) {
                $installation->installation_status = 'failed';
                $installation->notes = trim(($installation->notes ? $installation->notes."\n" : '').'Dibatalkan: '.$validated['reason']);
                $installation->save();
            }

            $workflowService->transition($customer, WorkflowTransition::REJECTED, $validated['reason']);
        });

        return redirect()->back()->with('success', 'Pemasangan pelanggan berhasil dibatalkan: tidak layak lanjut.');
    }

    /**
     * SN Perangkat Aktif yang boleh dipasang ke pelanggan ini — custody
     * anggota tim task yang sedang berjalan, status ISSUED, item-nya
     * installable (bukan company_asset kayak OTDR). SATU-SATUNYA sumber SN
     * yang boleh disimpan (koreksi lanjutan ADHOC-54, permintaan eksplisit
     * user): teknisi TANPA SN di custody TIDAK BISA mengisi SN sama sekali
     * lagi — fallback teks manual yang dulu ada buat device belum ke-track
     * Inventory sengaja DICABUT, supaya SN yang tersimpan selalu bisa
     * ditelusuri balik ke barang yang benar-benar diserahkan Gudang.
     * Dipakai report() (render dropdown) & storePemasangan() (validasi
     * keanggotaan) — satu query, dua pemakai, biar gak menyimpang.
     */
    private function eligibleSerialsForTeam(?Task $task)
    {
        $teamTechnicianIds = $task?->teamMembers->pluck('user_id')->all() ?? [];

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
     * Padanan `eligibleSerialsForTeam()` buat barang PASIF (kabel, RJ45, dst)
     * — versi QUANTITY/BATCH dari custody, bukan SN per-unit. Dikelompokkan
     * per item, sisa custody digabung SELURUH anggota tim (sama prinsipnya
     * dengan FIFO lintas anggota di `InventoryService::consumeFromCustody()`
     * — siapa pun di tim boleh submit laporan, jadi sisa custody yang
     * ditampilkan pun gabungan tim, bukan per-orang).
     *
     * Cuma pagar UI/UX (nunjuk barang mana yang ADA di custody + sisanya
     * berapa, supaya ketauan dari awal kalau cabang belum nge-issue kabel/RJ
     * ke teknisi) — penegakan SEBENARNYA tetap di
     * `InventoryService::consumeFromCustody()` saat `storeSpeedtest()`
     * (lihat komentar di sana kenapa potongnya di titik itu, bukan di sini).
     *
     * @return Collection<int, array{item_id:int, code:?string, name:string, unit:string, type:?string, available:float}>
     */
    private function eligiblePassiveCustodyForTeam(?Task $task)
    {
        $teamTechnicianIds = $task?->teamMembers->pluck('user_id')->all() ?? [];

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

    /**
     * Padanan `eligibleSerialsForTeam()` buat roll kabel
     * (App\Enums\TrackingType::ROLL) — list INDIVIDUAL roll (bukan agregat
     * kayak `eligiblePassiveCustodyForTeam()`), karena teknisi harus milih
     * roll FISIK mana yang dipotong (tiap roll punya `length_remaining`
     * sendiri, gak bisa digabung kayak custody QUANTITY/BATCH). Penegakan
     * sebenarnya tetap di `InventoryService::consumeFromRoll()` saat
     * `storeSpeedtest()`, sama pola `eligiblePassiveCustodyForTeam()`.
     */
    private function eligibleRollsForTeam(?Task $task)
    {
        $teamTechnicianIds = $task?->teamMembers->pluck('user_id')->all() ?? [];

        return $teamTechnicianIds === []
            ? collect()
            : InventoryRoll::query()
                ->whereIn('current_technician_id', $teamTechnicianIds)
                ->whereIn('status', [RollStatus::ISSUED->value, RollStatus::IN_USE->value])
                ->where('length_remaining', '>', 0)
                ->with('item')
                ->get();
    }

    public function report(Customer $customer, Request $request)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.update'), 403);

        // Halaman ini diakses dari beberapa entry point (Detail Task teknisi,
        // Dashboard Task Saya, Verifikasi Queue, Detail Pelanggan) — lihat
        // SafeUrl::resolveReturnTo() kenapa gak pakai url()->previous().
        $returnTo = SafeUrl::resolveReturnTo($request->query('return_to'), 'verifications.queue');

        if (! in_array($customer->status, ['installation_in_progress', 'revision_installation'])) {
            return redirect()->route('verifications.queue')->with('error', 'Status pelanggan tidak valid untuk pelaporan pemasangan.');
        }

        // Guard assignment sama kayak start() — permission generik
        // customers.detail.installation.update gak cukup, wajib jadi anggota
        // tim Task yang lagi jalan. Berlaku buat SEMUA non-full-access,
        // termasuk NOC (keputusan eksplisit: no exemption).
        $task = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::PEMASANGAN->value)
            ->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
            ->latest('id')
            ->first();

        abort_unless(
            auth()->user()->hasFullAccess()
                || ($task && $task->teamMembers->pluck('user_id')->contains(auth()->id())),
            403,
            'Anda bukan anggota tim yang ditugaskan untuk pemasangan pelanggan ini.'
        );

        $installation = $customer->installations()->latest()->first();
        if (! $installation || ! $installation->started_at) {
            return redirect()->route('verifications.queue')->with('error', 'Anda harus menekan tombol "Start Proses" terlebih dahulu untuk memulai waktu pemasangan.');
        }

        $customer->loadMissing(['customerDevice', 'customerTechnicalDetail', 'latestSurvey', 'internetPackage']);

        $materialService = app(TaskMaterialService::class);

        // Split Aktif/Pasif (ADHOC-54, rancangan-ui.md §3.2-3.3) — form REALISASI
        // (fase ini) dibatasi ke item PASIF doang; Perangkat Aktif dipilih lewat
        // dropdown SN custody terpisah di bawah, bukan lewat picker material
        // generik ini. Filter di PHP (bukan query DB) karena klasifikasi efektif
        // butuh resolusi dua-level (`Item::getEffectiveEquipmentClassAttribute()`)
        // yang gak bisa diterjemahkan jadi satu klausa where.
        $items = Item::active()->with('category')->orderBy('name')->get()
            ->filter(fn (Item $item) => $item->effective_equipment_class === EquipmentClass::PASIF)
            ->values();
        $itemCategories = ItemCategory::active()->ordered()->where('equipment_class', EquipmentClass::PASIF->value)->get();

        // Dropdown "Perangkat Aktif" — SN yang lagi di custody anggota tim
        // task ini, item-nya boleh dipasang ke pelanggan (bukan company_asset
        // kayak OTDR). Kosong = teknisi belum ambil barang dari Gudang —
        // storePemasangan() menolak submit-nya, lihat eligibleSerialsForTeam().
        $eligibleSerials = $this->eligibleSerialsForTeam($task);

        // Material Terpakai (Perangkat Pasif) — SEKARANG dibatasi custody tim
        // ini juga, sama prinsipnya dengan SN Perangkat Aktif di atas (ADHOC,
        // 2026-09-12: sebelumnya dropdown ini nampilin SEMUA item master PASIF
        // tanpa peduli teknisi beneran pegang barangnya atau tidak, jadi
        // laporan bisa diklaim biarpun cabang belum nge-issue kabel/RJ ke
        // teknisi — ketauannya baru di storeSpeedtest() lewat
        // InsufficientCustodyException, telat & bikin teknisi harus ngulang
        // dari step 5). Lihat eligiblePassiveCustodyForTeam().
        $eligiblePassiveCustody = $this->eligiblePassiveCustodyForTeam($task);

        // Dropdown "Roll Kabel" — sama prinsip eligibleSerials di atas, roll
        // INDIVIDUAL (bukan agregat) yang lagi di custody tim ini.
        $eligibleRolls = $this->eligibleRollsForTeam($task);

        // Prefill: baris terpakai yang sudah pernah disimpan (laporan dibuka
        // ulang / revisi) menang; kalau belum ada, pakai estimasi dari survey.
        // Tanpa prefill teknisi cenderung mengosongkan seksi ini, dan
        // perbandingan estimasi-vs-realisasi jadi tak ada gunanya.
        $installFopTask = $materialService->resolveTaskFor($customer, TaskType::PEMASANGAN);
        $existingUsage = $installFopTask
            ? $installFopTask->materials()->terpakai()->orderBy('id')->get()
            : collect();

        $sourceRows = $existingUsage->isNotEmpty()
            ? $existingUsage
            : $materialService->estimatesForCustomer($customer);

        // Baris freeform ("Lainnya" — item_id null) TIDAK BISA di-prefill lagi
        // ke sini (koreksi lanjutan ADHOC-54, 2026-09-12) — dropdown Material
        // Terpakai udah gak punya opsi "Lainnya", jadi baris begini bakal
        // ke-render dengan Barang KOSONG/gak kepilih di layar tapi qty-nya
        // TETAP ke-submit diam-diam (input hidden di belakang dropdown yang
        // gak match), lolos ke request tanpa disadari teknisi — nabrak
        // validasi `item_id required_with:qty` walau teknisi belum nyentuh
        // section ini sama sekali (bug nyata, ketemu 2026-09-14: submit
        // Aktivasi gagal padahal cuma isi device+ODP). Estimasi survey lama
        // yang gak nunjuk item master emang gak bisa diwakili custody — biar
        // hilang dari prefill daripada diam-diam gagal.
        $droppedFreeformEstimateNames = $sourceRows->whereNull('item_id')->pluck('item_name')->filter()->values();
        $sourceRows = $sourceRows->whereNotNull('item_id');

        $materialRows = $sourceRows->map(fn ($row) => [
            'item_id' => $row->item_id,
            'item_name' => $row->item_name,
            'item_type' => $row->item_type,
            'qty' => (float) $row->qty,
            'unit' => $row->unit,
            'note' => $row->note,
        ])->all();

        // Checklist alat: yang sudah tersimpan di task PEMASANGAN menang; kalau
        // belum ada, prefill dari survey — surveyor yang menilai medan, teknisi
        // pemasangan tinggal menyesuaikan.
        $workToolService = app(TaskWorkToolService::class);
        $workTools = WorkTool::options();
        $installWorkTools = $workToolService->rowsFor($installFopTask);
        $workToolRows = ! empty($installWorkTools)
            ? $installWorkTools
            : $workToolService->surveyRowsForCustomer($customer);

        // Gerbang Laporan Speedtest (ADHOC): panel step 6 cuma kebuka kalau
        // Laporan Pemasangan & Perangkat sudah lengkap — 3 foto wajib +
        // minimal satu baris material terpakai. Dihitung dari data
        // tersimpan, BUKAN dari status installation, supaya tahan reload
        // & tidak butuh kolom baru buat menandai "tahap pemasangan selesai".
        $pemasanganComplete = $installation
            && $installation->installation_photo
            && $installation->contract_photo
            && $installation->signature_photo
            && $installFopTask
            && $installFopTask->materials()->terpakai()->exists();

        return view('installations.report', compact('customer', 'installation', 'items', 'itemCategories', 'materialRows', 'workTools', 'workToolRows', 'returnTo', 'pemasanganComplete', 'eligibleSerials', 'eligiblePassiveCustody', 'eligibleRolls', 'droppedFreeformEstimateNames'));
    }

    public function store(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.update'), 403);

        // Data pemasangan hanya boleh diubah selama tahap pemasangan berjalan atau
        // sedang direvisi. Setelah lewat tahap ini (terverifikasi/aktif dst),
        // perubahan hanya diizinkan untuk role dengan permission validate (Admin/Verifikator).
        abort_unless(
            in_array($customer->status, ['installation_in_progress', 'revision_installation'], true)
                || auth()->user()->hasPermission('customers.detail.installation.validate'),
            403,
            'Data pemasangan pelanggan ini sudah melewati tahap pemasangan dan tidak dapat diubah oleh role Anda.'
        );

        // Guard assignment sama kayak start()/report() — SEMUA non-full-access
        // wajib jadi anggota tim Task yang lagi jalan, termasuk NOC (gak ada
        // pengecualian, keputusan eksplisit biar konsisten satu alur).
        $assignmentTask = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::PEMASANGAN->value)
            ->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
            ->latest('id')
            ->first();

        abort_unless(
            auth()->user()->hasFullAccess()
                || ($assignmentTask && $assignmentTask->teamMembers->pluck('user_id')->contains(auth()->id())),
            403,
            'Anda bukan anggota tim yang ditugaskan untuk pemasangan pelanggan ini.'
        );

        $validated = $request->validate([
            // Device info
            'device_type' => 'required|string|in:modem,ont,onu,router,other',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'mac_address' => ['nullable', 'string', 'max:17', 'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/'],
            'wifi_ssid' => 'nullable|string|max:150',
            'wifi_password' => 'nullable|string|max:150',
            'connection_mode' => 'nullable|string|in:bridge,router,pppoe,static,dhcp,other',
            'pppoe_username' => 'nullable|string|max:150',
            'pppoe_password' => 'nullable|string|max:150',
            'router_number' => 'nullable|string|max:50',
            'odp_number' => 'nullable|string|max:100',
            'odp_port' => 'nullable|string|max:50',
            'olt_number' => 'nullable|string|max:50',
            'olt_slot' => 'nullable|string|max:20',
            'olt_port' => 'nullable|string|max:50',
            'vlan' => 'nullable|string|max:20',

            // Speedtest
            'test_upload' => 'nullable|numeric',
            'test_download' => 'nullable|numeric',
            'jitter_ms' => 'nullable|numeric',
            'latency_ms' => 'nullable|numeric',
            'packet_loss_percent' => 'nullable|numeric',
            'speedtest_photo' => 'nullable|image|max:2048',
            'actual_attenuation' => 'nullable|string|max:50',
            'initial_attenuation' => 'nullable|string|max:50',

            // Installation details
            'installation_status' => 'required|string|in:scheduled,in_progress,completed,failed',
            'installation_photo' => 'nullable|image|max:2048',
            'contract_photo' => 'nullable|image|max:2048',
            'signature_photo' => 'nullable|image|max:2048',
            'installation_note' => 'nullable|string',

            // Timer fields (dari countdown JS di form)
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',

            // Perangkat pasif yang benar-benar terpakai — daftar baris, bukan
            // teks bebas. Baris kosong dibuang di TaskMaterialService.
            'materials' => 'nullable|array',
            'materials.*.item_id' => 'nullable|integer|exists:items,id',
            'materials.*.item_name' => 'nullable|string|max:150',
            // Kategori divalidasi ke master, bukan ke daftar enum yang beku —
            // kategori buatan admin harus langsung bisa dipakai tanpa deploy.
            'materials.*.item_type' => ['nullable', 'string', Rule::exists('item_categories', 'code')->where('is_active', true)],
            'materials.*.qty' => 'nullable|numeric|min:0',
            'materials.*.unit' => 'nullable|string|max:20',
            'materials.*.note' => 'nullable|string|max:255',
            // Checklist alat kerja — lihat catatan di CustomerSurveyController.
            'work_tools_ids' => 'nullable|array',
            'work_tools_ids.*' => 'nullable|integer|exists:work_tools,id',
            'work_tools_manual' => 'nullable|array',
            'work_tools_manual.*.tool_name' => 'nullable|string|max:100',
            'work_tools_manual.*.note' => 'nullable|string|max:255',
        ]);

        $installation = $customer->installations()->latest()->first();

        // Server-side validation for completed status (checking files only when completing)
        if ($validated['installation_status'] === 'completed') {
            $existingPhoto = $installation ? $installation->installation_photo : null;
            if (! $existingPhoto && ! $request->hasFile('installation_photo')) {
                return redirect()->back()->withInput()->withErrors(['installation_photo' => 'Foto pemasangan lapangan wajib diunggah saat status selesai.']);
            }

            $existingContract = $installation ? $installation->contract_photo : null;
            if (! $existingContract && ! $request->hasFile('contract_photo')) {
                return redirect()->back()->withInput()->withErrors(['contract_photo' => 'Foto kontrak wajib diunggah saat status selesai.']);
            }

            $existingSignature = $installation ? $installation->signature_photo : null;
            if (! $existingSignature && ! $request->hasFile('signature_photo')) {
                return redirect()->back()->withInput()->withErrors(['signature_photo' => 'Foto tanda tangan pelanggan wajib diunggah saat status selesai.']);
            }

            $techDetail = $customer->customerTechnicalDetail;
            $existingSpeedtest = $techDetail ? $techDetail->speedtest_photo : null;
            if (! $existingSpeedtest && ! $request->hasFile('speedtest_photo')) {
                return redirect()->back()->withInput()->withErrors(['speedtest_photo' => 'Foto hasil speedtest wajib diunggah saat status selesai.']);
            }

            // Pemasangan selesai tanpa satupun material tercatat hampir pasti
            // laporan yang belum diisi, bukan pemasangan tanpa material.
            // Dicek di sini (bukan di rules) supaya baris qty 0 / nama kosong
            // ikut terhitung tidak valid — aturan yang sama dipakai
            // TaskMaterialService waktu menyimpan.
            $hasMaterial = collect($validated['materials'] ?? [])->contains(
                fn ($row) => (float) ($row['qty'] ?? 0) > 0
                    && (! empty($row['item_id']) || trim((string) ($row['item_name'] ?? '')) !== '')
            );

            if (! $hasMaterial) {
                return redirect()->back()->withInput()->withErrors(['materials' => 'Perangkat pasif terpakai wajib diisi minimal satu baris saat status selesai.']);
            }
        }

        // Calculate speed conformity if package exists
        $package = $customer->internetPackage;
        $speed_conformity_percent = null;
        if ($package && $package->download_speed_mbps > 0 && ! empty($validated['test_download'])) {
            $speed_conformity_percent = ($validated['test_download'] / $package->download_speed_mbps) * 100;
        }

        try {
            DB::beginTransaction();

            if ($installation) {
                if ($request->hasFile('installation_photo')) {
                    $photoPath = FileUploadService::uploadInstallationPhoto($request->file('installation_photo'), $customer, 'pemasangan');
                    $installation->installation_photo = $photoPath;
                }
                if ($request->hasFile('contract_photo')) {
                    $contractPath = FileUploadService::uploadInstallationPhoto($request->file('contract_photo'), $customer, 'kontrak');
                    $installation->contract_photo = $contractPath;
                }
                if ($request->hasFile('signature_photo')) {
                    $signaturePath = FileUploadService::uploadInstallationPhoto($request->file('signature_photo'), $customer, 'ttd');
                    $installation->signature_photo = $signaturePath;
                }
                $installation->installation_note = $validated['installation_note'] ?? null;
                $installation->installation_status = $validated['installation_status'];

                // Gunakan waktu dari timer JS jika tersedia, fallback ke now()
                if (! empty($validated['started_at'])) {
                    $installation->started_at = $validated['started_at'];
                }

                $task = Task::where('customer_id', $customer->id)
                    ->where('task_type', TaskType::PEMASANGAN->value)
                    ->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
                    ->latest('id')
                    ->first();
                if ($task && ! $installation->fop_id) {
                    $installation->fop_id = $task->fop_id ?? $task->created_by;
                }

                if (! $installation->completed_at) {
                    $completedAt = ! empty($validated['completed_at'])
                        ? Carbon::parse($validated['completed_at'])
                        : now();
                    $installation->completed_at = $completedAt;
                    $installation->finished_date = $completedAt->toDateString();
                    $installation->end_time = $completedAt->toTimeString();
                }

                $installation->save();
            }

            // Save technical details
            $speedtestPhoto = null;
            if ($request->hasFile('speedtest_photo')) {
                $speedtestPhoto = FileUploadService::uploadInstallationPhoto($request->file('speedtest_photo'), $customer, 'speedtest');
            }

            CustomerTechnicalDetail::updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    // This stores both device details and speedtest as per the schema
                    'ssid' => $validated['wifi_ssid'] ?? null,
                    'router_mac' => $validated['mac_address'] ?? null,
                    'router_or_ont_serial' => $validated['serial_number'] ?? null,
                    'odp_number' => $validated['odp_number'] ?? null,
                    'odp_port' => $validated['odp_port'] ?? null,
                    'olt_number' => $validated['olt_number'] ?? null,
                    'olt_slot' => $validated['olt_slot'] ?? null,
                    'olt_port' => $validated['olt_port'] ?? null,
                    'vlan' => $validated['vlan'] ?? null,
                    'router_number' => $validated['router_number'] ?? null,
                    'test_upload' => $validated['test_upload'] ?? null,
                    'test_download' => $validated['test_download'] ?? null,
                    'jitter_ms' => $validated['jitter_ms'] ?? null,
                    'latency_ms' => $validated['latency_ms'] ?? null,
                    'packet_loss_percent' => $validated['packet_loss_percent'] ?? null,
                    'actual_attenuation' => $validated['actual_attenuation'] ?? null,
                    'initial_attenuation' => $validated['initial_attenuation'] ?? null,
                    'speed_conformity_percent' => $speed_conformity_percent,
                    'speedtest_photo' => $speedtestPhoto,
                ]
            );

            // Perangkat pasif terpakai — menempel di FopTask PEMASANGAN.
            // Ini KONSUMSI material, beda dari customer_technical_details.passive_device*
            // yang mencatat aset terpasang permanen di sisi pelanggan. Dua-duanya
            // tetap ada dan tidak digabung.
            // Anchor dibuat kalau belum ada — alasannya sama dengan jalur survey:
            // melewatkannya membuang material terpakai + checklist alat yang
            // barusan diisi teknisi tanpa pesan error apa pun.
            $materialService = app(TaskMaterialService::class);
            $installFopTask = $materialService->resolveTaskFor($customer, TaskType::PEMASANGAN)
                ?? app(FopTaskProvisioningService::class)->ensureForCustomer($customer, TaskType::PEMASANGAN);

            if ($installFopTask) {
                $materialService->sync(
                    $installFopTask,
                    MaterialKind::TERPAKAI,
                    $validated['materials'] ?? [],
                    auth()->id()
                );

                // Checklist alat menempel di task PEMASANGAN sendiri, bukan
                // menimpa daftar survey — daftar survey tetap jadi rekaman apa
                // yang surveyor nilai perlu waktu itu.
                $workToolService = app(TaskWorkToolService::class);
                $workToolService->sync(
                    $installFopTask,
                    $workToolService->rowsFromRequest(
                        $validated['work_tools_ids'] ?? [],
                        $validated['work_tools_manual'] ?? []
                    ),
                    auth()->id()
                );
            }

            // Also keep backward compatibility for CustomerDevice for now
            $customer->customerDevice()->updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'device_type' => $validated['device_type'] ?? null,
                    'brand' => $validated['brand'] ?? null,
                    'model' => $validated['model'] ?? null,
                    'serial_number' => $validated['serial_number'] ?? null,
                    'mac_address' => $validated['mac_address'] ?? null,
                    'wifi_ssid' => $validated['wifi_ssid'] ?? null,
                    'wifi_password' => $validated['wifi_password'] ?? null,
                    'connection_mode' => $validated['connection_mode'] ?? null,
                    'pppoe_username' => $validated['pppoe_username'] ?? null,
                    'pppoe_password' => $validated['pppoe_password'] ?? null,
                ]
            );

            if ($validated['installation_status'] === 'completed') {
                // Selesaikan task pemasangan jika ada
                $task = Task::where('customer_id', $customer->id)
                    ->where('task_type', TaskType::PEMASANGAN->value)
                    ->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
                    ->latest('id')
                    ->first();

                if ($task) {
                    app(TaskService::class)->complete($task, auth()->user());
                }

                $workflowService->transition($customer, 'installed');
                // Then move to verification_admin
                $workflowService->transition($customer, 'verification_admin');

                // Broadcast
                broadcast(new InstallationCompleted($customer))->toOthers();

                try {
                    $telegram = app(TelegramBotService::class);
                    $message = "🛠 <b>Pemasangan Selesai</b>\n";
                    $message .= "Pelanggan: {$customer->full_name}\n";
                    $message .= "No. HP: {$customer->primary_phone}\n";
                    $message .= "POP: {$customer->pop->name}\n";
                    $message .= 'Menunggu Verifikasi Admin untuk Aktivasi & Penagihan.';
                    $telegram->sendMessage($message);
                } catch (\Exception $e) {
                    Log::error('Gagal mengirim notifikasi Telegram: '.$e->getMessage());
                }

                $successMessage = 'Data pemasangan berhasil disimpan. Status beralih ke Verifikasi Admin.';
            } elseif ($validated['installation_status'] === 'failed') {
                $workflowService->transition($customer, 'waiting_installation', 'Instalasi gagal/butuh revisi. Menunggu penjadwalan ulang.');
                $successMessage = 'Data pemasangan berhasil disimpan. Status kembali ke Menunggu Pemasangan (Revisi).';
            } else {
                $successMessage = 'Data pemasangan berhasil disimpan (Progress).';
            }

            DB::commit();

            return redirect(SafeUrl::resolveReturnTo($request->input('return_to'), 'verifications.queue'))
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    /**
     * Simpan Laporan Pemasangan & Perangkat (step 5 wizard) — TIDAK
     * menyelesaikan task/transisi workflow. Ini "tombol Aktivasi" yang
     * membuka Laporan Speedtest (step 6): tanpa data pemasangan lengkap
     * di sini, panel speedtest di report() tetap terkunci.
     *
     * Sengaja endpoint TERPISAH dari store() (dipakai modal admin
     * customers/tabs/_installation.blade.php) — store() tetap dipertahankan
     * apa adanya supaya alur admin manual (radio installation_status) tidak
     * ikut berubah perilaku.
     */
    public function storePemasangan(Request $request, Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.update'), 403);

        abort_unless(
            in_array($customer->status, ['installation_in_progress', 'revision_installation'], true),
            403,
            'Data pemasangan pelanggan ini sudah melewati tahap pemasangan dan tidak dapat diubah oleh role Anda.'
        );

        $assignmentTask = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::PEMASANGAN->value)
            ->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
            ->latest('id')
            ->first();

        abort_unless(
            auth()->user()->hasFullAccess()
                || ($assignmentTask && $assignmentTask->teamMembers->pluck('user_id')->contains(auth()->id())),
            403,
            'Anda bukan anggota tim yang ditugaskan untuk pemasangan pelanggan ini.'
        );

        // SN Perangkat Aktif dihitung SEBELUM $request->validate() supaya
        // aturan 'selected_inventory_serial_id' bisa dibatasi ke custody tim
        // ini (Rule::in) — lihat eligibleSerialsForTeam(). Ambil dari task
        // yang sama dengan $assignmentTask di atas, bukan query baru.
        $eligibleSerialIds = $this->eligibleSerialsForTeam($assignmentTask)->pluck('id');

        // Roll kabel — OPSIONAL (beda dari SN Perangkat Aktif yang wajib):
        // gak semua pemasangan pakai kabel yang ke-track per-roll, jadi
        // submit tanpa pilih roll tetap harus jalan biasa.
        $eligibleRollIds = $this->eligibleRollsForTeam($assignmentTask)->pluck('id');

        $validated = $request->validate([
            // Informasi Perangkat Aktif + Nomor/Port ODP — SATU-SATUNYA syarat
            // wajib buat tombol Aktivasi (ADHOC). Nomor/Slot/Port OLT sengaja
            // TETAP nullable — banyak titik gak lewat OLT bernomor. Foto &
            // material juga sengaja TETAP nullable di sini: itu syarat buka
            // Fase 6 (lihat gerbang $pemasanganComplete di report() &
            // abort_unless di storeSpeedtest()), bukan syarat menyimpan Fase 5
            // / menekan Aktivasi.
            'device_type' => 'required|string|in:modem,ont,onu,router,other',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            // Teks manual DICABUT (koreksi lanjutan ADHOC-54) — klien tidak
            // boleh lagi ngirim SN sendiri, SN cuma boleh datang dari
            // selected_inventory_serial_id (override di bawah). 'prohibited'
            // jaga-jaga kalau ada jalur lama yang masih ngirim field ini.
            'serial_number' => 'prohibited',
            'mac_address' => ['nullable', 'string', 'max:17', 'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/'],
            'wifi_ssid' => 'required|string|max:150',
            'wifi_password' => 'required|string|max:150',
            'connection_mode' => 'required|string|in:bridge,router,pppoe,static,dhcp,other',
            'pppoe_username' => 'nullable|string|max:150',
            'pppoe_password' => 'nullable|string|max:150',
            'router_number' => 'nullable|string|max:50',
            'odp_number' => 'required|string|max:100',
            'odp_port' => 'required|string|max:50',
            'olt_number' => 'nullable|string|max:50',
            'olt_slot' => 'nullable|string|max:20',
            'olt_port' => 'nullable|string|max:50',
            'vlan' => 'nullable|string|max:20',
            'initial_attenuation' => 'nullable|string|max:50',

            // WAJIB & dibatasi ke custody tim ini (koreksi lanjutan ADHOC-54)
            // — kalau $eligibleSerialIds kosong (teknisi belum ambil barang
            // dari Gudang), Rule::in([]) selalu gagal: submit ditolak dengan
            // pesan custom di bawah, bukan cuma "format salah". Draft
            // pointer doang — aksi INSTALL sungguhan baru jalan di
            // storeSpeedtest(), lihat komentar di sana.
            'selected_inventory_serial_id' => ['required', 'integer', Rule::in($eligibleSerialIds)],

            // OPSIONAL — sama pola draft pointer di atas (dibatasi custody
            // tim ini), aksi potong-meter sungguhan (consumeFromRoll()) baru
            // jalan di storeSpeedtest().
            'selected_inventory_roll_id' => ['nullable', 'integer', Rule::in($eligibleRollIds)],
            'roll_meters_used' => ['nullable', 'numeric', 'min:0.01', 'required_with:selected_inventory_roll_id'],

            'installation_photo' => 'nullable|image|max:2048',
            'contract_photo' => 'nullable|image|max:2048',
            'signature_photo' => 'nullable|image|max:2048',
            'installation_note' => 'nullable|string',

            'started_at' => 'nullable|date',

            // Opsi "Lainnya (isi manual)" DICABUT dari dropdown Material
            // Terpakai (koreksi lanjutan ADHOC-54, 2026-09-12) — sama alasan
            // serial_number di atas: barang yang dipakai musti bisa ditelusuri
            // balik ke custody Gudang, gak boleh lagi ada nama karangan tanpa
            // dasar sistem. `required_with:qty`, BUKAN `required` polos — form
            // repeatable selalu menyisakan satu baris kosong terakhir (lihat
            // catatan di material-rows.blade.php), baris itu harus tetap boleh
            // lolos validasi (dibuang diam-diam belakangan oleh normalizeRow()
            // di TaskMaterialService, bukan digagalkan di sini). Kecukupan
            // sisa custody per-item dicek SETELAH validate() ini lolos (butuh
            // agregasi qty per item lintas baris, gak bisa satu Rule::in()),
            // lihat blok setelah $validated.
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
            // Pesan sama buat 'required' maupun 'in' (Rule::in([]) gagal
            // dengan kode 'in', bukan 'required', begitu custody kosong) —
            // dari sudut pandang teknisi keduanya berarti sama: gak ada SN
            // yang bisa dipilih, harus ambil barang dari Gudang dulu.
            'selected_inventory_serial_id.required' => 'SN Perangkat Aktif wajib dipilih dari Gudang. Anda tidak memiliki SN di custody — ambil barang (Issue) dari Gudang terlebih dahulu sebelum bisa mengisi Laporan Pemasangan.',
            'selected_inventory_serial_id.in' => 'SN yang dipilih bukan bagian dari custody tim Anda saat ini. Pilih ulang dari daftar SN yang tersedia.',
            'selected_inventory_roll_id.in' => 'Roll kabel yang dipilih bukan bagian dari custody tim Anda saat ini. Pilih ulang dari daftar roll yang tersedia.',
            'roll_meters_used.required_with' => 'Meter terpakai wajib diisi kalau roll kabel dipilih.',
        ]);

        // selected_inventory_serial_id sudah divalidasi wajib & anggota
        // custody tim ini di atas — SN yang disimpan SELALU berasal dari sini,
        // gak ada lagi teks manual yang bisa menyimpang. Mencegah dua sumber
        // kebenaran: installSerial() di storeSpeedtest() jalan dari
        // selected_inventory_serial_id yang sama persis.
        $validated['serial_number'] = InventorySerial::findOrFail($validated['selected_inventory_serial_id'])->serial_number;

        // Sisa custody Material Terpakai (koreksi lanjutan ADHOC-54,
        // 2026-09-12) — CUMA peringatan informasional di flash message, BUKAN
        // gerbang blocking. Sempat ditulis pakai throw ValidationException
        // (koreksi 2026-09-12 versi awal), tapi itu SALAH & langsung bikin 2
        // bug nyata (2026-09-14): (1) Aktivasi jadi bisa gagal gara-gara
        // Material Terpakai, padahal aturan tegasnya "Aktivasi cuma butuh
        // Informasi Perangkat Aktif + Distribusi Jaringan (ODP/OLT) — foto,
        // material, alat kerja itu syarat BUKA STEP 6, bukan syarat submit
        // step 5" (ditegaskan user dua kali); (2) baris material LAMA (SN
        // Perangkat Aktif yang salah ke-klasifikasi PASIF di master, atau
        // custody yang sudah berubah sejak submit sebelumnya) yang ke-resubmit
        // otomatis lewat prefill bikin Aktivasi ke-block PADAHAL teknisi belum
        // nyentuh Material Terpakai sama sekali — dan gagal validasi bikin
        // redirect balik TANPA ?activated=1, jadi wizard keliatan "reset ke
        // step 1". Penegakan SUNGGUHAN tetap di
        // `InventoryService::consumeFromCustody()` (storeSpeedtest(), lock+FIFO
        // beneran) — di sini cuma info dini, TIDAK menghentikan penyimpanan.
        // Qty digabung per item_id dulu (satu barang bisa muncul di lebih dari
        // satu baris).
        $custodyWarnings = [];
        $requestedQtyByItem = collect($validated['materials'] ?? [])
            ->filter(fn ($row) => ! empty($row['item_id']) && (float) ($row['qty'] ?? 0) > 0)
            ->groupBy('item_id')
            ->map(fn ($rows) => (float) $rows->sum('qty'));

        if ($requestedQtyByItem->isNotEmpty()) {
            $eligiblePassiveCustody = $this->eligiblePassiveCustodyForTeam($assignmentTask)->keyBy('item_id');

            foreach ($requestedQtyByItem as $itemId => $qtyRequested) {
                $available = (float) ($eligiblePassiveCustody[$itemId]['available'] ?? 0);

                if ($qtyRequested > $available) {
                    $itemName = $eligiblePassiveCustody[$itemId]['name'] ?? Item::find($itemId)?->name ?? "Barang #{$itemId}";

                    $custodyWarnings[] = "{$itemName} (diklaim ".number_format($qtyRequested, 2).', tersedia '.number_format($available, 2).')';
                }
            }
        }

        $installation = $customer->installations()->latest()->first();
        if (! $installation) {
            return redirect()->back()->with('error', 'Data pemasangan belum dimulai — tekan "Start Proses" terlebih dahulu.');
        }

        // Foto & material TIDAK menahan penyimpanan di sini (ADHOC — beda dari
        // perilaku lama). Aktivasi sekarang boleh berhasil dengan data device +
        // jaringan saja; kelengkapan foto+material cuma menentukan kapan Fase 6
        // beneran kebuka ($pemasanganComplete di report(), abort_unless di
        // storeSpeedtest()) — teknisi tetap harus balik tekan Aktivasi lagi
        // setelah upload foto & catat material supaya Fase 6 kebuka.
        try {
            DB::beginTransaction();

            if ($request->hasFile('installation_photo')) {
                $installation->installation_photo = FileUploadService::uploadInstallationPhoto($request->file('installation_photo'), $customer, 'pemasangan');
            }
            if ($request->hasFile('contract_photo')) {
                $installation->contract_photo = FileUploadService::uploadInstallationPhoto($request->file('contract_photo'), $customer, 'kontrak');
            }
            if ($request->hasFile('signature_photo')) {
                $installation->signature_photo = FileUploadService::uploadInstallationPhoto($request->file('signature_photo'), $customer, 'ttd');
            }
            $installation->installation_note = $validated['installation_note'] ?? null;
            // Draft pointer doang — resubmit-safe, gak ada side effect ke
            // inventory_serials di sini (cuma nunjuk, belum diinstall).
            $installation->selected_inventory_serial_id = $validated['selected_inventory_serial_id'] ?? null;
            // Roll kabel — draft pointer sama, konsumsi sungguhan (potong
            // meter) baru jalan di storeSpeedtest().
            $installation->selected_inventory_roll_id = $validated['selected_inventory_roll_id'] ?? null;
            $installation->roll_meters_used = $validated['roll_meters_used'] ?? null;

            // Status TETAP in_progress di sini — completed baru ditetapkan di
            // storeSpeedtest(), begitu Laporan Speedtest ikut tersimpan.
            $installation->installation_status = 'in_progress';

            if (! empty($validated['started_at'])) {
                $installation->started_at = $validated['started_at'];
            }

            $task = Task::where('customer_id', $customer->id)
                ->where('task_type', TaskType::PEMASANGAN->value)
                ->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
                ->latest('id')
                ->first();
            if ($task && ! $installation->fop_id) {
                $installation->fop_id = $task->fop_id ?? $task->created_by;
            }

            $installation->save();

            // Redaman awal disimpan di customer_technical_details, bukan
            // kolom installation — konsisten dengan store() & modal admin.
            CustomerTechnicalDetail::updateOrCreate(
                ['customer_id' => $customer->id],
                array_filter([
                    'ssid' => $validated['wifi_ssid'] ?? null,
                    'router_mac' => $validated['mac_address'] ?? null,
                    'router_or_ont_serial' => $validated['serial_number'] ?? null,
                    'odp_number' => $validated['odp_number'] ?? null,
                    'odp_port' => $validated['odp_port'] ?? null,
                    'olt_number' => $validated['olt_number'] ?? null,
                    'olt_slot' => $validated['olt_slot'] ?? null,
                    'olt_port' => $validated['olt_port'] ?? null,
                    'vlan' => $validated['vlan'] ?? null,
                    'router_number' => $validated['router_number'] ?? null,
                    'initial_attenuation' => $validated['initial_attenuation'] ?? null,
                ], fn ($v) => $v !== null)
            );

            $materialService = app(TaskMaterialService::class);
            $installFopTask = $materialService->resolveTaskFor($customer, TaskType::PEMASANGAN)
                ?? app(FopTaskProvisioningService::class)->ensureForCustomer($customer, TaskType::PEMASANGAN);

            if ($installFopTask) {
                $materialService->sync(
                    $installFopTask,
                    MaterialKind::TERPAKAI,
                    $validated['materials'] ?? [],
                    auth()->id()
                );

                $workToolService = app(TaskWorkToolService::class);
                $workToolService->sync(
                    $installFopTask,
                    $workToolService->rowsFromRequest(
                        $validated['work_tools_ids'] ?? [],
                        $validated['work_tools_manual'] ?? []
                    ),
                    auth()->id()
                );
            }

            $customer->customerDevice()->updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'device_type' => $validated['device_type'] ?? null,
                    'brand' => $validated['brand'] ?? null,
                    'model' => $validated['model'] ?? null,
                    'serial_number' => $validated['serial_number'] ?? null,
                    'mac_address' => $validated['mac_address'] ?? null,
                    'wifi_ssid' => $validated['wifi_ssid'] ?? null,
                    'wifi_password' => $validated['wifi_password'] ?? null,
                    'connection_mode' => $validated['connection_mode'] ?? null,
                    'pppoe_username' => $validated['pppoe_username'] ?? null,
                    'pppoe_password' => $validated['pppoe_password'] ?? null,
                ]
            );

            // Satu-satunya titik pemicu API 1 (webhook pemasangan) — tombol
            // Aktivasi Laporan Speedtest. Di dalam transaksi, sebelum commit:
            // listener SendInstallationActivatedWebhooks cuma menulis baris
            // webhook_outbox, HTTP-nya baru jalan setelah commit sukses. Kalau
            // transaksi ini rollback, event ini pun batal — tidak ada baris
            // outbox yang tertinggal. Lihat docs/api/business-logic.md.
            InstallationActivated::dispatch($customer);

            DB::commit();

            // Pesan sukses beda tergantung apakah foto+material sudah lengkap
            // (Fase 6 kebuka) atau belum (Aktivasi tersimpan, tapi teknisi masih
            // harus balik lagi upload foto & catat material). Hitung ulang di
            // sini (bukan pakai $pemasanganComplete dari report(), request beda)
            // — logika sama persis, lihat catatan di report().
            $hasMaterialTerpakai = $installFopTask && $installFopTask->materials()->terpakai()->exists();
            $fase6Unlocked = $installation->installation_photo
                && $installation->contract_photo
                && $installation->signature_photo
                && $hasMaterialTerpakai;

            // Kasus nyata (2026-09-12, laporan Siti Nuryani 2): teknisi menekan
            // Aktivasi berkali-kali yakin sudah isi foto+material, tapi
            // hasFile()/materials-nya kosong tiap kali — flash generik "lengkapi
            // foto & material" gak nunjuk mana yang sebenarnya belum nyangkut,
            // jadi teknisi gak sadar submit-nya gak membawa apa-apa. Sebutkan
            // persis yang kosong di sini supaya ketauan dari pesan sukses ini
            // sendiri, bukan cuma dari status "Fase 6 terkunci" yang generik.
            if (! $fase6Unlocked) {
                $missingParts = array_filter([
                    ! $installation->installation_photo ? 'Foto Pemasangan' : null,
                    ! $installation->contract_photo ? 'Foto Kontrak' : null,
                    ! $installation->signature_photo ? 'Foto TTD Pelanggan' : null,
                    ! $hasMaterialTerpakai ? 'Material Terpakai (minimal 1 baris, jumlah > 0)' : null,
                ]);

                $message = 'Data Pemasangan & Perangkat tersimpan, TAPI belum lengkap untuk membuka Laporan Speedtest — belum tersimpan: '
                    .implode(', ', $missingParts)
                    .'. Cek lagi isian di atas (foto harus dipilih ulang, file tidak bisa dipertahankan otomatis oleh browser), lalu tekan Aktivasi lagi.';
            } else {
                $message = 'Laporan Pemasangan & Perangkat tersimpan. Laporan Speedtest sudah bisa diisi.';
            }

            // Info sisa custody (non-blocking, lihat komentar di atas
            // $custodyWarnings) — ditempel di message SUKSES yang sama, bukan
            // flash 'error' terpisah: submit ini tetap berhasil, ini cuma
            // ngingetin sebelum kejadian beneran ketolak di storeSpeedtest().
            if (! empty($custodyWarnings)) {
                $message .= ' ⚠ Sisa custody tim mungkin tidak cukup untuk: '.implode('; ', $custodyWarnings).' — perbaiki sebelum menyelesaikan Laporan Speedtest, kalau tidak submit itu akan ditolak.';
            }

            return redirect()->route('customers.installation.report', [
                'customer' => $customer->id,
                'return_to' => $request->input('return_to'),
                'activated' => 1,
            ])->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    /**
     * Simpan Laporan Uji Koneksi (Speedtest, step 6 wizard) — SATU-SATUNYA
     * titik penyelesaian pemasangan di alur wizard teknisi (task complete +
     * transisi workflow ke verification_admin). Ditolak kalau Laporan
     * Pemasangan & Perangkat belum lengkap — lihat gerbang di report().
     */
    public function storeSpeedtest(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.update'), 403);

        abort_unless(
            in_array($customer->status, ['installation_in_progress', 'revision_installation'], true),
            403,
            'Data pemasangan pelanggan ini sudah melewati tahap pemasangan dan tidak dapat diubah oleh role Anda.'
        );

        $assignmentTask = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::PEMASANGAN->value)
            ->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
            ->latest('id')
            ->first();

        abort_unless(
            auth()->user()->hasFullAccess()
                || ($assignmentTask && $assignmentTask->teamMembers->pluck('user_id')->contains(auth()->id())),
            403,
            'Anda bukan anggota tim yang ditugaskan untuk pemasangan pelanggan ini.'
        );

        $installation = $customer->installations()->latest()->first();

        $materialService = app(TaskMaterialService::class);
        $installFopTask = $materialService->resolveTaskFor($customer, TaskType::PEMASANGAN);

        abort_unless(
            $installation
                && $installation->installation_photo
                && $installation->contract_photo
                && $installation->signature_photo
                && $installFopTask
                && $installFopTask->materials()->terpakai()->exists(),
            422,
            'Selesaikan dan simpan Laporan Pemasangan & Perangkat (tekan Aktivasi) terlebih dahulu sebelum mengisi Laporan Speedtest.'
        );

        $validated = $request->validate([
            'test_upload' => 'required|numeric',
            'test_download' => 'required|numeric',
            'jitter_ms' => 'nullable|numeric',
            'latency_ms' => 'nullable|numeric',
            'packet_loss_percent' => 'nullable|numeric',
            'speedtest_photo' => 'nullable|image|max:2048',
            'actual_attenuation' => 'nullable|string|max:50',
            'completed_at' => 'nullable|date',
        ]);

        $techDetail = $customer->customerTechnicalDetail;
        if (! ($techDetail?->speedtest_photo) && ! $request->hasFile('speedtest_photo')) {
            return redirect()->back()->withInput()->withErrors(['speedtest_photo' => 'Foto hasil speedtest wajib diunggah.']);
        }

        $package = $customer->internetPackage;
        $speed_conformity_percent = null;
        if ($package && $package->download_speed_mbps > 0 && ! empty($validated['test_download'])) {
            $speed_conformity_percent = ($validated['test_download'] / $package->download_speed_mbps) * 100;
        }

        try {
            DB::beginTransaction();

            $speedtestData = [
                'test_upload' => $validated['test_upload'] ?? null,
                'test_download' => $validated['test_download'] ?? null,
                'jitter_ms' => $validated['jitter_ms'] ?? null,
                'latency_ms' => $validated['latency_ms'] ?? null,
                'packet_loss_percent' => $validated['packet_loss_percent'] ?? null,
                'actual_attenuation' => $validated['actual_attenuation'] ?? null,
                'speed_conformity_percent' => $speed_conformity_percent,
            ];
            if ($request->hasFile('speedtest_photo')) {
                $speedtestData['speedtest_photo'] = FileUploadService::uploadInstallationPhoto($request->file('speedtest_photo'), $customer, 'speedtest');
            }

            CustomerTechnicalDetail::updateOrCreate(['customer_id' => $customer->id], $speedtestData);

            $completedAt = ! empty($validated['completed_at'])
                ? Carbon::parse($validated['completed_at'])
                : now();

            $installation->installation_status = 'completed';
            $installation->completed_at = $completedAt;
            $installation->finished_date = $completedAt->toDateString();
            $installation->end_time = $completedAt->toTimeString();
            $installation->save();

            $task = Task::where('customer_id', $customer->id)
                ->where('task_type', TaskType::PEMASANGAN->value)
                ->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
                ->latest('id')
                ->first();

            // Reconcile custody Gudang/Inventory (ADHOC-54) DI SINI — storeSpeedtest()
            // itu SATU-SATUNYA titik penyelesaian pemasangan (ADHOC-41, gak bisa
            // dipanggil dua kali buat customer yang sama karena status pelanggan
            // udah pindah dari installation_in_progress/revision_installation
            // begitu transaksi ini commit). Custody teknisi TIDAK boleh dipotong
            // di storePemasangan() — itu bisa disubmit berkali-kali (edit foto,
            // tambah material) sebelum beneran selesai, potong custody di situ
            // bakal dobel-potong tiap resubmit. Lihat rancangan-ui.md §3.4/§3.7.
            if ($task && $installFopTask && $task->teamMembers->isNotEmpty()) {
                $teamTechnicians = User::whereIn('id', $task->teamMembers->pluck('user_id'))->get();

                app(InventoryService::class)->reconcileMaterialsAgainstCustody($installFopTask, $customer, $teamTechnicians, auth()->user());

                // Perangkat Aktif (ADHOC-54) — draft pointer dari storePemasangan()
                // dieksekusi jadi INSTALL beneran DI SINI (sama alasan reconcile
                // material di atas: titik penyelesaian tunggal, resubmit-safe).
                // Field opsional — mayoritas instalasi belum punya device
                // ke-track Inventory, gak wajib diisi.
                if ($installation->selected_inventory_serial_id) {
                    $serial = InventorySerial::findOrFail($installation->selected_inventory_serial_id);
                    app(InventoryService::class)->installSerial($serial, $customer, $installFopTask, $teamTechnicians, auth()->user());
                }

                // Roll kabel — draft pointer dari storePemasangan() dieksekusi
                // jadi potong-meter beneran DI SINI, sama alasan Perangkat
                // Aktif di atas (titik penyelesaian tunggal, resubmit-safe).
                if ($installation->selected_inventory_roll_id && $installation->roll_meters_used) {
                    $roll = InventoryRoll::findOrFail($installation->selected_inventory_roll_id);
                    app(InventoryService::class)->consumeFromRoll($roll, (float) $installation->roll_meters_used, $teamTechnicians, $installFopTask, $customer, auth()->user());
                }
            }

            if ($task) {
                app(TaskService::class)->complete($task, auth()->user());
            }

            $workflowService->transition($customer, 'installed');
            $workflowService->transition($customer, 'verification_admin');

            broadcast(new InstallationCompleted($customer))->toOthers();

            try {
                $telegram = app(TelegramBotService::class);
                $message = "🛠 <b>Pemasangan Selesai</b>\n";
                $message .= "Pelanggan: {$customer->full_name}\n";
                $message .= "No. HP: {$customer->primary_phone}\n";
                $message .= "POP: {$customer->pop->name}\n";
                $message .= 'Menunggu Verifikasi Admin untuk Aktivasi & Penagihan.';
                $telegram->sendMessage($message);
            } catch (\Exception $e) {
                Log::error('Gagal mengirim notifikasi Telegram: '.$e->getMessage());
            }

            DB::commit();

            return redirect(SafeUrl::resolveReturnTo($request->input('return_to'), 'verifications.queue'))
                ->with('success', 'Laporan Speedtest tersimpan. Status beralih ke Verifikasi Admin.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }
}
