<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerTechnicalDetail;
use App\Services\CustomerWorkflowService;
use App\Events\InstallationStarted;
use App\Events\InstallationCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerInstallationController extends Controller
{
    public function start(Request $request, Customer $customer, CustomerWorkflowService $workflowService, \App\Services\TaskService $taskService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.update'), 403);

        if ($customer->status !== 'waiting_installation') {
            return redirect()->back()->with('error', 'Pelanggan tidak dalam status menunggu pemasangan.');
        }

        $task = \App\Models\Task::where('customer_id', $customer->id)
            ->where('task_type', \App\Enums\TaskType::PEMASANGAN->value)
            ->where('status', \App\Enums\TaskStatus::TERJADWAL->value)
            ->latest('id')
            ->first();

        if ($task) {
            abort_unless($task->teamMembers->pluck('user_id')->contains(auth()->id()), 403);
        }

        $memberIds = $task ? $task->teamMembers()->pluck('user_id')->toArray() : [];
        if (!in_array(auth()->id(), $memberIds)) {
            $memberIds[] = auth()->id();
        }

        $activeTask = \App\Models\Task::where('status', \App\Enums\TaskStatus::IN_PROGRESS->value)
            ->whereHas('teamMembers', fn ($q) => $q->whereIn('user_id', $memberIds))
            ->when($task, fn ($q) => $q->where('id', '!=', $task->id))
            ->first();

        if ($activeTask) {
            return redirect()->back()->with('error', "Tidak dapat memulai pemasangan karena teknisi sedang mengerjakan task lain [{$activeTask->task_number}]. Selesaikan atau laporkan (pending) task sebelumnya terlebih dahulu.");
        }

        try {
            DB::transaction(function() use ($customer, $workflowService, $taskService, $task) {
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
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
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
            'Pemasangan pelanggan ini tidak bisa dibatalkan dari status saat ini: ' . $customer->status
        );

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($customer, $validated, $workflowService) {
            $task = \App\Models\Task::where('customer_id', $customer->id)
                ->where('task_type', \App\Enums\TaskType::PEMASANGAN->value)
                ->whereNotIn('status', [\App\Enums\TaskStatus::SELESAI->value, \App\Enums\TaskStatus::DIBATALKAN->value])
                ->latest('id')
                ->first();

            if ($task) {
                app(\App\Services\TaskService::class)->cancel($task, auth()->user(), $validated['reason']);
            }

            $installation = $customer->installations()->latest()->first();
            if ($installation) {
                $installation->installation_status = 'failed';
                $installation->notes = trim(($installation->notes ? $installation->notes . "\n" : '') . 'Dibatalkan: ' . $validated['reason']);
                $installation->save();
            }

            $workflowService->transition($customer, \App\Enums\WorkflowTransition::REJECTED, $validated['reason']);
        });

        return redirect()->back()->with('success', 'Pemasangan pelanggan berhasil dibatalkan: tidak layak lanjut.');
    }

    public function report(Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.update'), 403);

        if (!in_array($customer->status, ['installation_in_progress', 'revision_installation'])) {
            return redirect()->route('verifications.queue')->with('error', 'Status pelanggan tidak valid untuk pelaporan pemasangan.');
        }

        $installation = $customer->installations()->latest()->first();
        if (!$installation || !$installation->started_at) {
            return redirect()->route('verifications.queue')->with('error', 'Anda harus menekan tombol "Start Proses" terlebih dahulu untuk memulai waktu pemasangan.');
        }

        $customer->loadMissing(['customerDevice', 'customerTechnicalDetail', 'latestSurvey', 'internetPackage']);

        return view('installations.report', compact('customer', 'installation'));
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

        $validated = $request->validate([
            // Device info
            'device_type'          => 'required|string|in:modem,ont,onu,router,other',
            'brand'                => 'nullable|string|max:100',
            'model'                => 'nullable|string|max:100',
            'serial_number'        => 'nullable|string|max:100',
            'mac_address'          => ['nullable', 'string', 'max:17', 'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/'],
            'wifi_ssid'            => 'nullable|string|max:150',
            'wifi_password'        => 'nullable|string|max:150',
            'connection_mode'      => 'nullable|string|in:bridge,router,pppoe,static,dhcp,other',
            'pppoe_username'       => 'nullable|string|max:150',
            'pppoe_password'       => 'nullable|string|max:150',
            'ip_address'           => 'nullable|string|max:50',
            'router_number'        => 'nullable|string|max:50',
            'odp_number'           => 'nullable|string|max:100',
            'odp_port'             => 'nullable|string|max:50',
            'olt_number'           => 'nullable|string|max:50',
            'olt_slot'             => 'nullable|string|max:20',
            'olt_port'             => 'nullable|string|max:50',
            'vlan'                 => 'nullable|string|max:20',
            
            // Speedtest
            'test_upload'          => 'nullable|numeric',
            'test_download'        => 'nullable|numeric',
            'jitter_ms'            => 'nullable|numeric',
            'latency_ms'           => 'nullable|numeric',
            'packet_loss_percent'  => 'nullable|numeric',
            'speedtest_photo'      => 'nullable|image|max:2048',
            'actual_attenuation'   => 'nullable|string|max:50',
            'initial_attenuation'  => 'nullable|string|max:50',

            // Installation details
            'installation_status'  => 'required|string|in:scheduled,in_progress,completed,failed',
            'installation_photo'   => 'nullable|image|max:2048',
            'contract_photo'       => 'nullable|image|max:2048',
            'signature_photo'      => 'nullable|image|max:2048',
            'installation_note'    => 'nullable|string',

            // Timer fields (dari countdown JS di form)
            'started_at'           => 'nullable|date',
            'completed_at'         => 'nullable|date',
        ]);

        $installation = $customer->installations()->latest()->first();

        // Server-side validation for completed status (checking files only when completing)
        if ($validated['installation_status'] === 'completed') {
            $existingPhoto = $installation ? $installation->installation_photo : null;
            if (!$existingPhoto && !$request->hasFile('installation_photo')) {
                return redirect()->back()->withInput()->withErrors(['installation_photo' => 'Foto pemasangan lapangan wajib diunggah saat status selesai.']);
            }

            $existingContract = $installation ? $installation->contract_photo : null;
            if (!$existingContract && !$request->hasFile('contract_photo')) {
                return redirect()->back()->withInput()->withErrors(['contract_photo' => 'Foto kontrak wajib diunggah saat status selesai.']);
            }

            $existingSignature = $installation ? $installation->signature_photo : null;
            if (!$existingSignature && !$request->hasFile('signature_photo')) {
                return redirect()->back()->withInput()->withErrors(['signature_photo' => 'Foto tanda tangan pelanggan wajib diunggah saat status selesai.']);
            }

            $techDetail = $customer->customerTechnicalDetail;
            $existingSpeedtest = $techDetail ? $techDetail->speedtest_photo : null;
            if (!$existingSpeedtest && !$request->hasFile('speedtest_photo')) {
                return redirect()->back()->withInput()->withErrors(['speedtest_photo' => 'Foto hasil speedtest wajib diunggah saat status selesai.']);
            }
        }

        // Calculate speed conformity if package exists
        $package = $customer->internetPackage;
        $speed_conformity_percent = null;
        if ($package && $package->download_speed_mbps > 0 && !empty($validated['test_download'])) {
            $speed_conformity_percent = ($validated['test_download'] / $package->download_speed_mbps) * 100;
        }

        try {
            DB::beginTransaction();

            if ($installation) {
                if ($request->hasFile('installation_photo')) {
                    $photoPath = \App\Services\FileUploadService::uploadInstallationPhoto($request->file('installation_photo'), $customer, 'pemasangan');
                    $installation->installation_photo = $photoPath;
                }
                if ($request->hasFile('contract_photo')) {
                    $contractPath = \App\Services\FileUploadService::uploadInstallationPhoto($request->file('contract_photo'), $customer, 'kontrak');
                    $installation->contract_photo = $contractPath;
                }
                if ($request->hasFile('signature_photo')) {
                    $signaturePath = \App\Services\FileUploadService::uploadInstallationPhoto($request->file('signature_photo'), $customer, 'ttd');
                    $installation->signature_photo = $signaturePath;
                }
                $installation->installation_note = $validated['installation_note'] ?? null;
                $installation->installation_status = $validated['installation_status'];

                // Gunakan waktu dari timer JS jika tersedia, fallback ke now()
                if (!empty($validated['started_at'])) {
                    $installation->started_at = $validated['started_at'];
                }

                $task = \App\Models\Task::where('customer_id', $customer->id)
                    ->where('task_type', \App\Enums\TaskType::PEMASANGAN->value)
                    ->whereIn('status', [\App\Enums\TaskStatus::IN_PROGRESS->value, \App\Enums\TaskStatus::PENDING->value])
                    ->latest('id')
                    ->first();
                if ($task && !$installation->fop_id) {
                    $installation->fop_id = $task->fop_id ?? $task->created_by;
                }

                if (!$installation->completed_at) {
                    $completedAt = !empty($validated['completed_at'])
                        ? \Illuminate\Support\Carbon::parse($validated['completed_at'])
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
                $speedtestPhoto = \App\Services\FileUploadService::uploadInstallationPhoto($request->file('speedtest_photo'), $customer, 'speedtest');
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
                $task = \App\Models\Task::where('customer_id', $customer->id)
                    ->where('task_type', \App\Enums\TaskType::PEMASANGAN->value)
                    ->whereIn('status', [\App\Enums\TaskStatus::IN_PROGRESS->value, \App\Enums\TaskStatus::PENDING->value])
                    ->latest('id')
                    ->first();
                
                if ($task) {
                    app(\App\Services\TaskService::class)->complete($task, auth()->user());
                }

                $workflowService->transition($customer, 'installed');
                // Then move to verification_admin
                $workflowService->transition($customer, 'verification_admin');

                // Broadcast
                broadcast(new InstallationCompleted($customer))->toOthers();

                try {
                    $telegram = app(\App\Services\TelegramBotService::class);
                    $message = "🛠 <b>Pemasangan Selesai</b>\n";
                    $message .= "Pelanggan: {$customer->full_name}\n";
                    $message .= "No. HP: {$customer->phone}\n";
                    $message .= "POP: {$customer->pop->name}\n";
                    $message .= "Menunggu Verifikasi Admin untuk Aktivasi & Penagihan.";
                    $telegram->sendMessage($message);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Gagal mengirim notifikasi Telegram: ' . $e->getMessage());
                }
                
                $successMessage = 'Data pemasangan berhasil disimpan. Status beralih ke Verifikasi Admin.';
            } else if ($validated['installation_status'] === 'failed') {
                $workflowService->transition($customer, 'waiting_installation', 'Instalasi gagal/butuh revisi. Menunggu penjadwalan ulang.');
                $successMessage = 'Data pemasangan berhasil disimpan. Status kembali ke Menunggu Pemasangan (Revisi).';
            } else {
                $successMessage = 'Data pemasangan berhasil disimpan (Progress).';
            }

            DB::commit();

            return redirect()->route('verifications.queue')->with('success', $successMessage);


        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
