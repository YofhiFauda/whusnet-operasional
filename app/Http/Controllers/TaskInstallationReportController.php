<?php
 
namespace App\Http\Controllers;
 
use App\Models\CustomerInstallation;
use App\Models\CustomerTechnicalDetail;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Task;
use App\Services\TaskService;
use App\Services\CustomerWorkflowService;
use App\Enums\WorkflowTransition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
 
class TaskInstallationReportController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly CustomerWorkflowService $workflowService
    ) {}
 
    /**
     * Simpan laporan pemasangan 4-step dan selesaikan task.
     * Guard: task.status.complete + anggota tim
     */
    public function store(Request $request, Task $task): JsonResponse
    {
        $this->authorize('statusComplete', $task);
 
        $validated = $request->validate([
            // Step 2 — Data Teknis
            'internet_package_id'  => 'required|exists:internet_packages,id',
            'router_or_ont_serial' => 'required|string|max:255',
            'router_mac'           => 'required|string|max:255',
            'vlan'                 => 'required|integer|min:1',
            'ip_address'           => 'required|ip',
 
            // Step 3 — Kontrak & TTD (TTD dikirim base64)
            'contract_file'        => 'required|file|image|max:5120',
            'signature_customer'   => 'required|string',
            'signature_technician' => 'required|string',
 
            // Opsional
            'installation_note'    => 'nullable|string|max:1000',
        ]);
 
        // Pastikan teknisi memang anggota tim task ini
        abort_unless(
            $task->isMember(auth()->id()),
            403,
            'Anda bukan anggota tim task ini.'
        );
 
        // Pastikan task masih in_progress
        abort_unless(
            $task->status->value === 'in_progress',
            422,
            'Task hanya bisa diselesaikan dari status In Progress.'
        );
 
        // Pastikan minimal 2 foto bukti (ONT terpasang & kabel routing) sudah diupload di Step 1
        abort_unless(
            $task->evidences()->count() >= 2,
            422,
            'Minimal 2 foto bukti pemasangan (ONT & kabel) harus diupload sebelum menyelesaikan task.'
        );
 
        $customer = $task->customer;
        abort_unless($customer instanceof \App\Models\Customer, 422, 'Pelanggan tidak ditemukan pada task ini.');
 
        $service = $customer->customerService;
        abort_unless($service instanceof \App\Models\CustomerService, 422, 'Layanan pelanggan tidak ditemukan.');
 
        DB::transaction(function () use ($task, $customer, $service, $validated, $request) {
            $user = auth()->user();
 
            // 1. Simpan Tanda Tangan Pelanggan & Teknisi sebagai file PNG
            $customerSigPath = $this->saveSignature(
                $validated['signature_customer'],
                "signatures/install-{$task->id}/customer"
            );
            $techSigPath = $this->saveSignature(
                $validated['signature_technician'],
                "signatures/install-{$task->id}/tech"
            );
 
            // 2. Simpan Kontrak Fisik ke Storage
            $contractPath = null;
            if ($request->hasFile('contract_file')) {
                $file = $request->file('contract_file');
                $contractPath = $file->storeAs(
                    "contracts/task-{$task->id}",
                    uniqid('contract_', true) . '.' . $file->getClientOriginalExtension(),
                    'public'
                );
            }
 
            // 3. Simpan / Update Data Teknis Perangkat
            $techDetail = CustomerTechnicalDetail::where('customer_id', $customer->id)->first();
            if (!$techDetail) {
                $techDetail = new CustomerTechnicalDetail();
                $techDetail->customer_id = $customer->id;
            }
            $techDetail->fill([
                'router_or_ont_serial' => $validated['router_or_ont_serial'],
                'router_mac'           => $validated['router_mac'],
                'antenna_mac'          => $validated['router_mac'], // redundancy
                'vlan'                 => $validated['vlan'],
                'ip_address'           => $validated['ip_address'],
                'note'                 => $validated['installation_note'],
            ]);
            $techDetail->save();
 
            // 4. Update Paket Internet pada Layanan Customer
            $package = InternetPackage::findOrFail($validated['internet_package_id']);
            $monthlyPrice = (float) $package->monthly_price;
            $discount = (float) ($service->discount ?? 0);
            $ppn = (float) ($package->ppn ?? 0);
            $otherFee = (float) ($service->other_fee ?? 0);
            $discountedPrice = $monthlyPrice - $discount;
            $totalBill = $discountedPrice * (1 + $ppn / 100) + $otherFee;
 
            $downLabel = isset($package->download_speed_mbps) ? $package->download_speed_mbps . ' Mbps' : null;
            $upLabel = isset($package->upload_speed_mbps) ? $package->upload_speed_mbps . ' Mbps' : null;
 
            $service->update([
                'internet_package_id'    => $package->id,
                'package_name_snapshot'  => $package->name,
                'download_speed_snapshot'=> $downLabel,
                'upload_speed_snapshot'  => $upLabel,
                'monthly_price'          => $monthlyPrice,
                'ppn'                    => $ppn,
                'total_monthly_bill'     => $totalBill,
            ]);
 
            // 5. Update / Buat Record CustomerInstallation untuk merekam hasil kerja lapangan
            $installation = CustomerInstallation::where('customer_id', $customer->id)
                ->whereIn('installation_status', ['scheduled', 'in_progress'])
                ->latest()
                ->first();
 
            $note = $validated['installation_note'] ?? '';
            if ($techSigPath) {
                $note .= "\nTTD Teknisi: /storage/{$techSigPath}";
            }
 
            if ($installation) {
                $installation->update([
                    'installation_status' => 'completed',
                    'finished_date'       => now()->toDateString(),
                    'completed_at'        => now(),
                    'contract_photo'      => $contractPath ? "storage/{$contractPath}" : null,
                    'signature_photo'     => $customerSigPath ? "storage/{$customerSigPath}" : null,
                    'installation_note'   => $note,
                ]);
            }
 
            // 6. Selesaikan Task
            $this->taskService->complete($task, $user);
 
            // 7. PENTING (S8.9-T004): 
            // Transisi Status Customer ke Verification Admin HANYA dipicu saat 
            // FOP melakukan Approval di TaskController::review(). (No Auto-Update)
        });
 
        return response()->json([
            'success' => true,
            'message' => 'Laporan pemasangan berhasil disimpan. Status pelanggan berpindah ke Verifikasi Admin.',
        ]);
    }
 
    /**
     * Simpan base64 signature ke storage public disk.
     */
    private function saveSignature(string $dataUri, string $directory): ?string
    {
        try {
            if (!str_contains($dataUri, ',')) {
                return null;
            }
 
            $base64 = explode(',', $dataUri, 2)[1];
            $binary = base64_decode($base64);
 
            if (!$binary) {
                return null;
            }
 
            $filename = $directory . '/' . uniqid('sig_', true) . '.png';
            Storage::disk('public')->put($filename, $binary);
 
            return $filename;
        } catch (\Exception $e) {
            Log::error('Gagal simpan signature pemasangan: ' . $e->getMessage());
            return null;
        }
    }
}
