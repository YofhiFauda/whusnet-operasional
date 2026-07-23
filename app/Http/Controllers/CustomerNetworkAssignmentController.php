<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Distribution;
use App\Models\Pop;
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
            'editable' => ! in_array($customer->status, self::BLOCKED_STATUSES, true),
            'mini_pops' => $miniPops,
            'distributions' => $distributions,
            'current' => [
                'mini_pop_id' => $customer->mini_pop_id,
                'distribution_id' => $customer->distribution_id,
            ],
        ]);
    }

    /**
     * Status yang belum boleh di-assign Mini POP/Distribusi — pemasangan
     * belum mulai, jadi belum ada dasar teknis buat nentuin OLT/Distribusi mana.
     */
    private const BLOCKED_STATUSES = [
        'registered',
        'waiting_survey',
        'survey_in_progress',
        'surveyed',
        'waiting_acc',
        'waiting_installation',
        'rejected',
    ];

    /**
     * Assign/ganti Mini POP (OLT) + Distribusi pelanggan — dipakai lewat modal
     * yang muncul saat klik CID/REQ ID di halaman detail pelanggan. Bisa
     * diganti-ganti kapan pun pasca pemasangan (menyesuaikan konfigurasi
     * Mikrotik aktual, karena belum ada integrasi hardware otomatis).
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);

        if (in_array($customer->status, self::BLOCKED_STATUSES, true)) {
            return back()->with('error', 'Mini POP & Distribusi cuma bisa di-assign setelah proses pemasangan dimulai.');
        }

        $validated = $request->validate([
            'mini_pop_id' => 'nullable|exists:pops,id',
            'distribution_id' => 'nullable|exists:distributions,id',
        ]);

        $miniPopId = $validated['mini_pop_id'] ?? null;
        $distributionId = $validated['distribution_id'] ?? null;

        if ($miniPopId) {
            $miniPop = Pop::where('id', $miniPopId)
                ->where('type', 'mini_pop')
                ->where('parent_id', $customer->pop_id)
                ->first();

            if (! $miniPop) {
                return back()->with('error', 'Mini POP yang dipilih tidak valid untuk Cabang POP pelanggan ini.');
            }
        }

        if ($distributionId) {
            $distribution = Distribution::where('id', $distributionId)
                ->where('pop_id', $miniPopId)
                ->first();

            if (! $distribution) {
                return back()->with('error', 'Distribusi yang dipilih tidak sesuai dengan Mini POP yang dipilih.');
            }
        }

        $oldValues = [
            'mini_pop_id' => $customer->mini_pop_id,
            'distribution_id' => $customer->distribution_id,
            'cid' => $customer->cid,
        ];

        $customer->mini_pop_id = $miniPopId;
        $customer->distribution_id = $distributionId;

        // Kalau pelanggan udah aktif/suspended (udah punya CID final), regenerate
        // CID sesuai Mini POP/Distribusi baru — CID gak auto-update sendiri karena
        // tersimpan statis di kolom customer.cid, bukan dihitung ulang tiap saat.
        if (in_array($customer->status, ['active', 'suspended'], true) && $customer->pop) {
            $customer->load('distribution');
            $customer->cid = $customer->pop->generateComplexCid($customer, $customer->distribution);
        }

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
