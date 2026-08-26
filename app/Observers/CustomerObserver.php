<?php

namespace App\Observers;

use App\Enums\WorkflowTransition;
use App\Events\CustomerVerificationStatusChanged;
use App\Models\Customer;
use App\Models\CustomerPortalToken;

class CustomerObserver
{
    /**
     * Satu titik broadcast buat SEMUA jalur yang mengubah status pelanggan —
     * CustomerWorkflowService::transition() maupun update() langsung
     * (CustomerVerificationController::finalVerify, CustomerInstallationController)
     * — lihat CustomerVerificationStatusChanged.
     */
    public function updated(Customer $customer): void
    {
        if ($customer->wasChanged('status')) {
            CustomerVerificationStatusChanged::dispatch($customer);

            // Pelanggan terminated → akun portal dinonaktifkan & semua token
            // dicabut (docs/api/api-portal-pelanggan/business-logic.md
            // §Token). `WorkflowTransition::TERMINATED->value`, bukan
            // literal string — kolom `status` sendiri tidak native-cast ke
            // enum ini di Eloquent, tapi perbandingannya tetap wajib lewat
            // enum (CLAUDE.md: jangan pakai string literal).
            if ($customer->status === WorkflowTransition::TERMINATED->value && $customer->portalAccount) {
                $customer->portalAccount->update(['status' => 'disabled']);
                CustomerPortalToken::revokeAllForCustomer($customer->id);
            }
        }
    }
}
