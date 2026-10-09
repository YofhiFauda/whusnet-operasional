<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Distribution;
use App\Models\Pop;
use App\Services\CustomerCidService;
use App\Services\NetworkAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerNetworkAssignmentController extends Controller
{
    /**
     * Data Mini POP + Distribusi (scoped ke Cabang POP pelanggan) buat modal
     * assignment yang dipanggil dari List Pelanggan (AJAX, 1 modal dipakai
     * bareng buat semua row — data-nya di-fetch per klik, bukan preload semua).
     */
    public function data(Customer $customer): JsonResponse
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);

        $miniPops = $customer->pop_id
            ? Pop::where('parent_id', $customer->pop_id)->where('type', 'mini_pop')->orderBy('name')->get(['id', 'name', 'pop_code'])
            : collect();

        $distributions = Distribution::whereIn('pop_id', $miniPops->pluck('id'))
            ->orderBy('code')
            ->get(['id', 'pop_id', 'code', 'name']);

        return response()->json([
            'customer_name' => $customer->full_name,
            'customer_cid' => $customer->cid,
            'pop_name' => $customer->pop->name ?? '-',
            'pop_code' => $customer->pop->pop_code ?? '',
            'editable' => ! in_array($customer->status, NetworkAssignmentService::BLOCKED_STATUSES, true),
            'mini_pops' => $miniPops,
            'distributions' => $distributions,
            'current' => [
                'mini_pop_id' => $customer->mini_pop_id,
                'distribution_id' => $customer->distribution_id,
            ],
        ]);
    }

    /**
     * Assign/ganti Mini POP (OLT) + Distribusi pelanggan — dipakai lewat modal
     * yang muncul saat klik CID/REQ ID di halaman detail pelanggan. Bisa
     * diganti-ganti kapan pun pasca pemasangan (menyesuaikan konfigurasi
     * Mikrotik aktual, karena belum ada integrasi hardware otomatis).
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);

        if (in_array($customer->status, NetworkAssignmentService::BLOCKED_STATUSES, true)) {
            return back()->with('error', 'Mini POP & Distribusi cuma bisa di-assign setelah proses pemasangan dimulai.');
        }

        $validated = $request->validate([
            'mini_pop_id' => 'nullable|exists:pops,id',
            'distribution_id' => 'nullable|exists:distributions,id',
        ]);

        $miniPopId = $validated['mini_pop_id'] ?? null;
        $distributionId = $validated['distribution_id'] ?? null;

        if ($miniPopId && ! NetworkAssignmentService::miniPopBelongsToPop($miniPopId, $customer->pop_id)) {
            return back()->with('error', 'Mini POP yang dipilih tidak valid untuk Cabang POP pelanggan ini.');
        }

        if ($distributionId && ! NetworkAssignmentService::distributionBelongsToMiniPop($distributionId, $miniPopId)) {
            return back()->with('error', 'Distribusi yang dipilih tidak sesuai dengan Mini POP yang dipilih.');
        }

        $oldValues = [
            'mini_pop_id' => $customer->mini_pop_id,
            'distribution_id' => $customer->distribution_id,
            'cid' => $customer->cid,
        ];

        // CID lewat rumus satu pintu (CustomerCidService, ADHOC-107 R3) — sama
        // dengan Edit, API, dan aktivasi. Dulu tiap jalur menghitung sendiri
        // dan hasilnya beda (Edit `C00RQ…` vs modal `C10RQ…`). sync()
        // dipanggil eksplisit (bukan cuma lewat observer) supaya simpan ulang
        // tanpa perubahan pilihan tetap membetulkan CID yang terlanjur
        // campuran — jalur perbaikan manual data lama.
        $customer->mini_pop_id = $miniPopId;
        $customer->distribution_id = $distributionId;
        CustomerCidService::sync($customer);
        $customer->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Data Pelanggan',
            'action' => 'update_network_assignment',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'old_values' => $oldValues,
            'new_values' => [
                'mini_pop_id' => $customer->mini_pop_id,
                'distribution_id' => $customer->distribution_id,
                'cid' => $customer->cid,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Mini POP & Distribusi pelanggan berhasil diperbarui.');
    }
}
