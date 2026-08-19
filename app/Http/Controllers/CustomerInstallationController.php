<?php

namespace App\Http\Controllers;

use App\Enums\MaterialKind;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Events\InstallationCompleted;
use App\Events\InstallationStarted;
use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Task;
use App\Models\WorkTool;
use App\Services\CustomerWorkflowService;
use App\Services\FileUploadService;
use App\Services\FopTaskProvisioningService;
use App\Services\TaskMaterialService;
use App\Services\TaskService;
use App\Services\TaskWorkToolService;
use App\Services\TelegramBotService;
use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $items = Item::active()->with('category')->orderBy('name')->get();
        $itemCategories = ItemCategory::options();

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

        return view('installations.report', compact('customer', 'installation', 'items', 'itemCategories', 'materialRows', 'workTools', 'workToolRows', 'returnTo', 'pemasanganComplete'));
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
            'ip_address' => 'nullable|string|max:50',
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
                    'ip_address' => $validated['ip_address'] ?? null,
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

        $validated = $request->validate([
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
            'ip_address' => 'nullable|string|max:50',
            'router_number' => 'nullable|string|max:50',
            'odp_number' => 'nullable|string|max:100',
            'odp_port' => 'nullable|string|max:50',
            'olt_number' => 'nullable|string|max:50',
            'olt_slot' => 'nullable|string|max:20',
            'olt_port' => 'nullable|string|max:50',
            'vlan' => 'nullable|string|max:20',
            'initial_attenuation' => 'nullable|string|max:50',

            'installation_photo' => 'nullable|image|max:2048',
            'contract_photo' => 'nullable|image|max:2048',
            'signature_photo' => 'nullable|image|max:2048',
            'installation_note' => 'nullable|string',

            'started_at' => 'nullable|date',

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

        $installation = $customer->installations()->latest()->first();
        if (! $installation) {
            return redirect()->back()->withInput()->with('error', 'Data pemasangan belum dimulai — tekan "Start Proses" terlebih dahulu.');
        }

        // Sama seperti gerbang "completed" di store(): tiga foto + minimal
        // satu baris material adalah syarat wajib SEBELUM Laporan Speedtest
        // boleh dibuka — di sinilah gerbang itu sebenarnya ditegakkan.
        if (! $installation->installation_photo && ! $request->hasFile('installation_photo')) {
            return redirect()->back()->withInput()->withErrors(['installation_photo' => 'Foto pemasangan lapangan wajib diunggah.']);
        }
        if (! $installation->contract_photo && ! $request->hasFile('contract_photo')) {
            return redirect()->back()->withInput()->withErrors(['contract_photo' => 'Foto kontrak wajib diunggah.']);
        }
        if (! $installation->signature_photo && ! $request->hasFile('signature_photo')) {
            return redirect()->back()->withInput()->withErrors(['signature_photo' => 'Foto tanda tangan pelanggan wajib diunggah.']);
        }

        $hasMaterial = collect($validated['materials'] ?? [])->contains(
            fn ($row) => (float) ($row['qty'] ?? 0) > 0
                && (! empty($row['item_id']) || trim((string) ($row['item_name'] ?? '')) !== '')
        );
        if (! $hasMaterial) {
            return redirect()->back()->withInput()->withErrors(['materials' => 'Perangkat pasif terpakai wajib diisi minimal satu baris.']);
        }

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
                    'ip_address' => $validated['ip_address'] ?? null,
                ]
            );

            DB::commit();

            return redirect()->route('customers.installation.report', [
                'customer' => $customer->id,
                'return_to' => $request->input('return_to'),
                'activated' => 1,
            ])->with('success', 'Laporan Pemasangan & Perangkat tersimpan. Laporan Speedtest sudah bisa diisi.');
        } catch (\Exception $e) {
            DB::rollBack();

            // withInput() — tanpa ini, satu exception tak terduga (mis. disk
            // penuh saat upload foto) membuat SEMUA isian step 5 yang baru
            // diketik teknisi keliatan hilang begitu halaman reload.
            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
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

            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }
}
