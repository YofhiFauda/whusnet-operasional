<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Jobs\SendCustomerActivationNotification;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerStatusLog;
use App\Models\Task;
use App\Services\CustomerPortal\PortalAuthService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class CustomerWorkflowService
{
    /**
     * Transition a customer to the next workflow status.
     *
     * @param  WorkflowTransition|string  $nextStatus
     *
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

            // Fase 5.1 — stempel tanggal reject/terminate ke kolom nyata, supaya
            // daftar pelanggan tab Gagal/Putus bisa ORDER BY kolom biasa (bukan
            // subquery JSON berkorelasi ke audit_logs yang O(N²)).
            if ($nextStatus->value === 'rejected') {
                $customer->rejected_at = now();
            } elseif ($nextStatus->value === 'terminated') {
                $customer->terminated_at = now();
            }

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
                        'note' => $note,
                    ]),
                    'ip_address' => request() ? request()->ip() : null,
                    'user_agent' => request() ? request()->userAgent() : null,
                    'created_at' => now(),
                ]);

                CustomerStatusLog::create([
                    'customer_id' => $customer->id,
                    'from_status' => $currentStatusStr,
                    'to_status' => $nextStatus->value,
                    'changed_by' => Auth::id(), // Akan mereturn null secara otomatis jika di-run dari scheduler/CLI (OK)
                    'note' => $note,
                ]);

                // Sentralisasi Tiket: Auto-create Task antrean Survey & Pemasangan
                if (in_array($nextStatus->value, ['waiting_survey', 'waiting_installation'])) {
                    $taskType = $nextStatus->value === 'waiting_survey' ? TaskType::SURVEY->value : TaskType::PEMASANGAN->value;
                    $titlePrefix = $nextStatus->value === 'waiting_survey' ? 'Survey Pelanggan: ' : 'Pemasangan Baru: ';
                    $existingTask = Task::where('customer_id', $customer->id)
                        ->where('task_type', $taskType)
                        ->whereIn('status', [TaskStatus::PENDING->value, TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value])
                        ->exists();

                    if (! $existingTask) {
                        $year = date('Y');
                        $count = Task::whereYear('created_at', $year)->count() + 1;
                        Task::create([
                            'task_number' => sprintf('TASK-%s-%04d', $year, $count),
                            'task_type' => $taskType,
                            'title' => $titlePrefix.$customer->full_name,
                            'description' => null,
                            'pop_id' => $customer->pop_id ?? 1,
                            'customer_id' => $customer->id,
                            'status' => TaskStatus::PENDING->value,
                            'created_by' => Auth::id() ?? 1,
                            'updated_by' => Auth::id() ?? 1,
                        ]);
                    }

                    // FopTask lahir bareng antreannya, bukan menunggu papan
                    // /fop-tasks dibuka. Dia anchor wajib buat task_materials &
                    // task_work_tools — kalau belum ada saat teknisi mengisi
                    // laporan, estimasi material dan checklist alat dibuang senyap.
                    app(FopTaskProvisioningService::class)->ensureForCustomer(
                        $customer,
                        TaskType::from($taskType)
                    );
                }

                // S8.8-T005: Trigger notifikasi ke pelanggan setelah status Active
                if ($nextStatus->value === 'active') {
                    SendCustomerActivationNotification::dispatch($customer, Auth::id());
                }

                // QR + PIN pelanggan (docs/plan/qr-code/rancangan-qr-pelanggan-final.md
                // §7.2, Fase 2) — titik penerbitan resmi: begitu pelanggan
                // masuk WAITING_INSTALLATION, admin cetak stiker+kartu lalu
                // teknisi bawa ke lokasi. issue() sendiri IDEMPOTEN (§7.2
                // "sudah punya token aktif? BERHENTI, pakai yang lama"),
                // tapi PIN TIDAK — issuePin() SELALU menghasilkan PIN baru.
                // Makanya PIN cuma diterbitkan kalau issue() BENAR-BENAR
                // membuat token baru (wasRecentlyCreated) — instalasi yang
                // diulang (WorkflowTransition.php:37-40) tidak boleh
                // menerbitkan PIN kedua yang mematikan kartu lama.
                if ($nextStatus->value === 'waiting_installation') {
                    try {
                        $qrTokens = app(CustomerQrTokenService::class);
                        $token = $qrTokens->issue($customer, Auth::user());

                        if ($token->wasRecentlyCreated) {
                            $qrTokens->issuePin($token, Auth::user());
                        }

                        // Kartu yang bakal dicetak dari sini punya login_id +
                        // PIN — akun `customer_portal_accounts` (pending_claim)
                        // WAJIB sudah ada di titik yang sama, kalau tidak
                        // `/auth/claim` gagal 401 generik begitu pelanggan
                        // coba pakai kartunya (gejala nyata 2026-08-27, akun
                        // portal sebelumnya HANYA lahir lewat command backfill
                        // manual — lihat docblock `PortalAuthService::ensureAccountExists()`).
                        app(PortalAuthService::class)->ensureAccountExists($customer);
                    } catch (RuntimeException $e) {
                        // customer_code/pop_id belum lengkap saat transisi ini
                        // seharusnya jarang (keduanya wajib sejak registrasi),
                        // tapi kegagalannya TIDAK BOLEH menggagalkan transisi
                        // workflow inti. Admin bisa terbitkan manual belakangan
                        // dari halaman QR pelanggan begitu datanya lengkap.
                        Log::warning('QR/PIN auto-issue gagal saat WAITING_INSTALLATION', [
                            'customer_id' => $customer->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return $saved;
        }, 3);
    }
}
