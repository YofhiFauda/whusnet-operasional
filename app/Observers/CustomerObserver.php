<?php

namespace App\Observers;

use App\Events\CustomerVerificationStatusChanged;
use App\Models\Customer;

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
        }
    }
}
