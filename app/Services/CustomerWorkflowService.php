<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\AuditLog;
use App\Enums\WorkflowTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Exception;

class CustomerWorkflowService
{
    /**
     * Transition a customer to the next workflow status.
     *
     * @param Customer $customer
     * @param WorkflowTransition|string $nextStatus
     * @param string|null $note
     * @return bool
     * @throws Exception|InvalidArgumentException
     */
    public function transition(Customer $customer, $nextStatus, ?string $note = null): bool
    {
        if (is_string($nextStatus)) {
            $nextStatusEnum = WorkflowTransition::tryFrom($nextStatus);
            if (! $nextStatusEnum) {
                throw new InvalidArgumentException("Invalid workflow status provided: {$nextStatus}");
            }
            $nextStatus = $nextStatusEnum;
        }

        $currentStatusStr = $customer->status ?? 'registered';
        $currentStatus = WorkflowTransition::tryFrom($currentStatusStr);

        if (! $currentStatus) {
            throw new Exception("Current customer status '{$currentStatusStr}' is invalid.");
        }

        if (! $currentStatus->canTransitionTo($nextStatus)) {
            throw new Exception("Cannot transition from {$currentStatus->value} to {$nextStatus->value}.");
        }

        return DB::transaction(function () use ($customer, $currentStatusStr, $nextStatus, $note) {
            $customer->status = $nextStatus->value;
            $saved = $customer->save();

            if ($saved) {
                AuditLog::create([
                    'user_id' => Auth::id() ?? 1, // Fallback to system user if no auth
                    'module' => 'Customer Workflow',
                    'action' => 'status_transition',
                    'auditable_type' => Customer::class,
                    'auditable_id' => $customer->id,
                    'old_values' => ['status' => $currentStatusStr],
                    'new_values' => array_filter([
                        'status' => $nextStatus->value,
                        'note' => $note
                    ]),
                    'ip_address' => request() ? request()->ip() : null,
                    'user_agent' => request() ? request()->userAgent() : null,
                    'created_at' => now(),
                ]);
            }

            return $saved;
        });
    }
}
