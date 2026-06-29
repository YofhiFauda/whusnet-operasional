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

                \App\Models\CustomerStatusLog::create([
                    'customer_id' => $customer->id,
                    'from_status' => $currentStatusStr,
                    'to_status'   => $nextStatus->value,
                    'changed_by'  => Auth::id(), // Akan mereturn null secara otomatis jika di-run dari scheduler/CLI (OK)
                    'note'        => $note,
                ]);

                // Sentralisasi Tiket: Auto-create Task antrean Survey & Pemasangan
                if (in_array($nextStatus->value, ['waiting_survey', 'waiting_installation'])) {
                    $taskType = $nextStatus->value === 'waiting_survey' ? \App\Enums\TaskType::SURVEY->value : \App\Enums\TaskType::PEMASANGAN->value;
                    $titlePrefix = $nextStatus->value === 'waiting_survey' ? 'Survey Pelanggan: ' : 'Pemasangan Baru: ';
                    $existingTask = \App\Models\Task::where('customer_id', $customer->id)
                        ->where('task_type', $taskType)
                        ->whereIn('status', [\App\Enums\TaskStatus::PENDING->value, \App\Enums\TaskStatus::TERJADWAL->value, \App\Enums\TaskStatus::IN_PROGRESS->value])
                        ->exists();

                    if (! $existingTask) {
                        $year  = date('Y');
                        $count = \App\Models\Task::whereYear('created_at', $year)->count() + 1;
                        \App\Models\Task::create([
                            'task_number' => sprintf('TASK-%s-%04d', $year, $count),
                            'task_type'   => $taskType,
                            'title'       => $titlePrefix . $customer->full_name,
                            'description' => null,
                            'pop_id'      => $customer->pop_id ?? 1,
                            'customer_id' => $customer->id,
                            'status'      => \App\Enums\TaskStatus::PENDING->value,
                            'created_by'  => Auth::id() ?? 1,
                            'updated_by'  => Auth::id() ?? 1,
                        ]);
                    }
                }

                // S8.8-T005: Trigger notifikasi ke pelanggan setelah status Active
                if ($nextStatus->value === 'active') {
                    \App\Jobs\SendCustomerActivationNotification::dispatch($customer, Auth::id());
                }
            }

            return $saved;
        });
    }
}
