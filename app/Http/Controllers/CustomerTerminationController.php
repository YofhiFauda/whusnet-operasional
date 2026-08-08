<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerTerminationController extends Controller
{
    /**
     * Terminate customer service.
     */
    public function __invoke(Request $request, Customer $customer)
    {
        // Sebelumnya gak ada guard permission sama sekali di sini — cuma
        // numpang middleware `customers.update` di routes/web.php, jadi role
        // mana pun yang bisa edit field pelanggan biasa (Helpdesk/Sales) juga
        // otomatis bisa putus langganan. Aksi destruktif/service-impacting,
        // wajib permission sendiri (customers.deactivate).
        abort_unless(auth()->user()->hasPermission('customers.deactivate'), 403);

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

        // Customer Lifecycle: pendaftar asli dikasih tau pelanggannya
        // diterminasi — sebelumnya nol notif buat transisi besar status
        // pelanggan (docs/plan/analisa-status-implementasi-notifikasi.md §5).
        $creator = $customer->creator ?? ($customer->created_by ? User::find($customer->created_by) : null);
        if ($creator && $creator->id !== auth()->id()) {
            $creator->notify(new AppNotification(
                title: 'Pelanggan Diterminasi: '.$customer->full_name,
                message: "Layanan pelanggan {$customer->full_name} dihentikan oleh ".auth()->user()->name.". Alasan: {$request->reason}",
                actionUrl: route('customers.show', $customer->id),
                type: NotificationType::ERROR
            ));
        }

        return redirect()->back()->with('success', 'Layanan pelanggan berhasil dihentikan (terminasi).');
    }
}
