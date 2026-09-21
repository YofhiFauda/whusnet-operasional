<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Enums\WorkflowTransition;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CustomerWorkflowService;
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

        // Guard state machine. Sebelumnya endpoint ini update() status langsung
        // tanpa cek apa pun — POST manual bisa memutus pelanggan yang masih
        // waiting_survey / rejected, atau memutus ulang yang sudah terminated
        // (menimpa terminated_at & menambah baris audit). Pre-check di sini,
        // bukan menangkap Exception dari transition(), supaya error DB/lain
        // tidak ikut tertelan jadi pesan "status tidak valid".
        $oldStatus = (string) $customer->status;

        if (! WorkflowTransition::tryFrom($oldStatus)?->canTransitionTo(WorkflowTransition::TERMINATED)) {
            return redirect()->back()->with('error', "Pelanggan berstatus '{$oldStatus}' tidak bisa diputus langganan. Hanya pelanggan aktif atau terisolir.");
        }

        DB::transaction(function () use ($customer, $request, $oldStatus) {
            // Lewat state machine: transition() mengisi terminated_at (Fase
            // 5.1, supaya tab "Putus Langganan" bisa ORDER BY kolom), menulis
            // customer_status_logs, dan audit 'Customer Workflow'.
            app(CustomerWorkflowService::class)->transition($customer, WorkflowTransition::TERMINATED, $request->reason);

            // Update service status if it exists
            if ($customer->customerService) {
                $customer->customerService->update([
                    'service_status' => 'berhenti',
                ]);
            }

            // Audit 'customers'/'terminate' TETAP ditulis walau transition()
            // sudah menulis audit sendiri: RendersCustomerList membaca alasan
            // Putus Langganan dari baris ini (module=customers,
            // action=terminate), begitu juga import legacy yang menulis baris
            // sintetis dengan format sama. Dihapus = kolom Alasan di list
            // kosong. old_values = status SEBENARNYA (bukan hardcode 'active'
            // seperti dulu — pelanggan isolir tercatat "dari active").
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'customers',
                'action' => 'terminate',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'old_values' => ['status' => $oldStatus],
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
