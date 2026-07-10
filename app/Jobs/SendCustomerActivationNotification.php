<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerStatusLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCustomerActivationNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Customer $customer,
        public ?int $adminId = null
    ) {}

    public function handle(): void
    {
        // Update customer_status_logs dengan note (Fase 1 - Simulasi Telegram)
        CustomerStatusLog::create([
            'customer_id' => $this->customer->id,
            'from_status' => 'verification_admin',
            'to_status'   => 'active',
            'changed_by'  => $this->adminId,
            'note'        => '✓ Notifikasi aktivasi: ' . $this->customer->full_name . ' (Simulasi Telegram dikirim ke ' . $this->customer->phone_number . ')',
        ]);
    }
}
