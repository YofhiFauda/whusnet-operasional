<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Enums\WorkflowTransition;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CustomerTerminationService;
use App\Support\RupiahInput;
use Illuminate\Http\Request;

class CustomerTerminationController extends Controller
{
    /**
     * "Request Putus Langganan" (ADHOC-69, label ADHOC-87 — cuma label,
     * tanpa persetujuan bertingkat) — invoice denda (kalau eligible) terbit
     * otomatis. Pembebasan tagihan periode (kalau perlu) SENGAJA bukan
     * urusan form ini (disederhanakan 2026-09-24) — pakai aksi terpisah
     * "Bebaskan Tagihan Periode" di Detail Pelanggan, sebelum atau sesudah
     * putus. Lihat CustomerTerminationService.
     */
    public function __invoke(Request $request, Customer $customer, CustomerTerminationService $service)
    {
        // Aksi destruktif/service-impacting, wajib permission sendiri
        // (customers.deactivate) — bukan numpang customers.update.
        abort_unless(auth()->user()->hasPermission('customers.deactivate'), 403);

        $request->merge(RupiahInput::parseKeys(
            $request->only(['penalty_amount']),
            'penalty_amount',
        ));

        $validated = $request->validate([
            'termination_reason_id' => 'required|exists:customer_termination_reasons,id',
            'termination_note' => 'nullable|string|max:1000',
            // Server yang menentukan wajib/tidaknya (masa <=1 tahun) —
            // §3.1 rancangan. Di sini cuma dibatasi rentang wajar; guard
            // eligibilitas ada di Service.
            'penalty_amount' => 'nullable|numeric|min:0|max:99999999.99',
        ]);

        // Guard state machine. POST manual tidak boleh memutus pelanggan yang
        // masih waiting_survey/rejected, atau memutus ulang yang sudah
        // terminated (menimpa terminated_at & menambah baris audit).
        $oldStatus = (string) $customer->status;

        if (! WorkflowTransition::tryFrom($oldStatus)?->canTransitionTo(WorkflowTransition::TERMINATED)) {
            return redirect()->back()->with('error', "Pelanggan berstatus '{$oldStatus}' tidak bisa diputus langganan. Hanya pelanggan aktif atau terisolir.");
        }

        $invoice = $service->terminate($customer, $validated, auth()->id());

        // Customer Lifecycle: pendaftar asli dikasih tau pelanggannya
        // diterminasi.
        $creator = $customer->creator ?? ($customer->created_by ? User::find($customer->created_by) : null);
        if ($creator && $creator->id !== auth()->id()) {
            $penaltyMessage = $invoice
                ? 'Denda putus langganan '.format_rupiah($invoice->total_amount).' diterbitkan ('.$invoice->invoice_number.').'
                : 'Tidak ada denda putus langganan.';

            $creator->notify(new AppNotification(
                title: 'Pelanggan Diterminasi: '.$customer->full_name,
                message: "Layanan pelanggan {$customer->full_name} dihentikan oleh ".auth()->user()->name.". {$penaltyMessage}",
                actionUrl: route('customers.show', $customer->id),
                type: NotificationType::ERROR
            ));
        }

        return redirect()->back()->with('success', 'Layanan pelanggan berhasil dihentikan (terminasi).');
    }
}
