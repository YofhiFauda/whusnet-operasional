<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Jobs\SendCustomerActivationNotification;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerStatusLog;
use App\Models\FopTask;
use App\Models\FopTaskStatusHistory;
use App\Models\Task;
use App\Models\User;
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
                        ->whereIn('status', [TaskStatus::PENDING->value, TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value, TaskStatus::LAPOR_NANTI->value])
                        ->exists();

                    if (! $existingTask) {
                        Task::create([
                            'task_number' => app(NumberSequenceService::class)->taskNumber(),
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

                $this->closeTasksLeftBehind($customer, $currentStatusStr, $nextStatus, $note);

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

    /**
     * Status pelanggan yang masih "dalam tahap" Survey / Pemasangan — selama
     * pelanggan di sini, Task tipe itu memang boleh (dan harus) tetap terbuka.
     *
     * @var array<string, list<string>>
     */
    private const STAGE_STATUSES = [
        'SURVEY' => ['waiting_survey', 'survey_in_progress'],
        'PSB' => ['waiting_installation', 'installation_in_progress', 'revision_installation'],
    ];

    /**
     * Tutup Task + FopTask Survey/PSB yang tertinggal begitu pelanggan
     * meninggalkan tahapnya — ditolak (Gagal), atau tahapnya sudah beres
     * dilaporkan lewat jalur lain.
     *
     * Bug 2026-09-29 (Testing 7 & 10): status pelanggan bisa berubah dari
     * banyak pintu — Tolak di Verifikasi, Batalkan Survey/Pemasangan, laporan
     * survey yang diisi Admin langsung dari Antrean Survey — dan tiap pintu
     * dulu cuma menutup SATU task yang kebetulan ia kenal (mis. cuma yang
     * `in_progress`, atau cuma yang sudah `selesai`). Task yang sudah
     * dijadwalkan FOP ke teknisi lain tertinggal `terjadwal` selamanya di Task
     * Saya teknisi itu, padahal pelanggannya sudah Gagal / sudah disurvey.
     * Ditaruh di sini (bukan di tiap controller) supaya pintu yang ditambah
     * nanti otomatis ikut benar.
     *
     * Dibatalkan, BUKAN diselesaikan (keputusan user 2026-09-29): teknisi yang
     * ditinggal tidak mengerjakan apa pun, jadi tidak boleh terhitung selesai
     * atau masuk SLA-nya. Task yang barusan dilaporkan sendiri sudah `selesai`
     * sebelum transisi dipanggil (CustomerSurveyController::store(),
     * CustomerInstallationController), jadi tidak tersentuh.
     */
    private function closeTasksLeftBehind(Customer $customer, string $fromStatus, WorkflowTransition $nextStatus, ?string $note): void
    {
        // Transisi dari scheduler/CLI tidak punya user login — pola fallback
        // sama dengan `created_by => Auth::id() ?? 1` di atas.
        $actor = Auth::user() ?? User::query()->orderBy('id')->first();

        if (! $actor) {
            return;
        }

        foreach (self::STAGE_STATUSES as $taskTypeValue => $stageStatuses) {
            $leftStage = $nextStatus === WorkflowTransition::REJECTED
                || (in_array($fromStatus, $stageStatuses, true) && ! in_array($nextStatus->value, $stageStatuses, true));

            if (! $leftStage) {
                continue;
            }

            $taskType = TaskType::from($taskTypeValue);
            $reason = $nextStatus === WorkflowTransition::REJECTED
                ? 'Pelanggan masuk Gagal'.($note ? ": {$note}" : '.')
                : "{$taskType->label()} sudah dilaporkan oleh {$actor->name} — task ini tidak lagi diperlukan.";

            $openTasks = Task::where('customer_id', $customer->id)
                ->where('task_type', $taskType->value)
                ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
                ->get();

            // TaskService::cancel() → TaskObserver ikut membatalkan FopTask yang
            // tertaut + menulis fop_task_status_history (penulis sisi FOP untuk
            // jalur Task, CLAUDE.md § sinkronisasi aturan 7) + notif ke tim.
            foreach ($openTasks as $task) {
                app(TaskService::class)->cancel($task, $actor, $reason);
            }

            // FopTask yang belum pernah dijadwalkan (Draft, tanpa Task) tidak
            // punya Task untuk menembus TaskObserver — tanpa ini ia menggantung
            // di antrean /fop-tasks untuk pelanggan yang sudah Gagal. Riwayatnya
            // ditulis di sini karena tidak ada penulis lain untuk kasus ini
            // (FopTask tanpa Task & tanpa Ticket), jadi tidak mungkin dobel.
            $orphanFopTasks = FopTask::where('customer_id', $customer->id)
                ->where('category', $taskType->value)
                ->whereNull('task_id')
                ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
                ->get();

            foreach ($orphanFopTasks as $fopTask) {
                $fromFopStatus = $fopTask->status->value;

                $fopTask->update([
                    'status' => TaskStatus::DIBATALKAN,
                    'cancelled_at' => now(),
                    'cancel_reason' => $reason,
                ]);

                FopTaskStatusHistory::create([
                    'fop_task_id' => $fopTask->id,
                    'from_status' => $fromFopStatus,
                    'to_status' => TaskStatus::DIBATALKAN->value,
                    'changed_by' => $actor?->id,
                    'changed_at' => now(),
                ]);
            }
        }
    }
}
