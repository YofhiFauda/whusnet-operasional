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
    public function start(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('fill_installation'), 403);

        if ($customer->status !== 'waiting_installation') {
            return redirect()->back()->with('error', 'Pelanggan tidak dalam status menunggu pemasangan.');
        }

        try {
            DB::beginTransaction();

            $installation = $customer->installations()->latest()->first();

            if ($installation) {
                $installation->update([
                    'started_at' => now(),
                    'installation_status' => 'in_progress'
                ]);
            }

            $workflowService->transition($customer, 'installation_in_progress');

            // Broadcast Event
            broadcast(new InstallationStarted($customer))->toOthers();

            DB::commit();

            return redirect()->back()->with('success', 'Waktu pemasangan berhasil dimulai.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function report(Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('fill_installation'), 403);

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
        abort_unless(auth()->user()->hasPermission('fill_installation'), 403);

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
                    $photoPath = $request->file('installation_photo')->store('installations', 'public');
                    $installation->installation_photo = $photoPath;
                }
                if ($request->hasFile('contract_photo')) {
                    $contractPath = $request->file('contract_photo')->store('contracts', 'public');
                    $installation->contract_photo = $contractPath;
                }
                if ($request->hasFile('signature_photo')) {
                    $signaturePath = $request->file('signature_photo')->store('signatures', 'public');
                    $installation->signature_photo = $signaturePath;
                }
                $installation->installation_note = $validated['installation_note'] ?? null;
                $installation->installation_status = $validated['installation_status'];

                // Gunakan waktu dari timer JS jika tersedia, fallback ke now()
                if (!empty($validated['started_at'])) {
                    $installation->started_at = $validated['started_at'];
                }

                if ($validated['installation_status'] === 'completed') {
                    $installation->completed_at = !empty($validated['completed_at'])
                        ? $validated['completed_at']
                        : now();
                    $installation->finished_date = now()->toDateString();
                }

                $installation->save();
            }

            // Save technical details
            $speedtestPhoto = null;
            if ($request->hasFile('speedtest_photo')) {
                $speedtestPhoto = $request->file('speedtest_photo')->store('speedtests', 'public');
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
