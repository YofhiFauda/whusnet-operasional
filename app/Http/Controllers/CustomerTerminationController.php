<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerTerminationController extends Controller
{
    /**
     * Terminate customer service.
     */
    public function __invoke(Request $request, Customer $customer)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($customer, $request) {
            // Update customer status. terminated_at (Fase 5.1) diisi supaya tab
            // "Putus Langganan" bisa ORDER BY kolom, bukan subquery JSON audit.
            $customer->update([
                'status' => 'terminated',
                'terminated_at' => now(),
            ]);

            // Update service status if it exists
            if ($customer->customerService) {
                $customer->customerService->update([
                    'service_status' => 'berhenti',
                ]);
            }

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'customers',
                'action' => 'terminate',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'old_values' => ['status' => 'active'],
                'new_values' => ['status' => 'terminated', 'reason' => $request->reason],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Layanan pelanggan berhasil dihentikan (terminasi).');
    }
}
