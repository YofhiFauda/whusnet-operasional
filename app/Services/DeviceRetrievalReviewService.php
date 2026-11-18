<?php

namespace App\Services;

use App\Enums\DeviceRetrievalReviewStatus;
use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\DeviceRetrievalComment;
use App\Models\FopTask;
use App\Models\TaskDeviceRetrieval;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Review Penarikan Alat — laporan DEAC GAGAL (modem tidak berhasil diambil)
 * diputuskan FOP: Approve (pelanggan dibebaskan, kasus ditutup tanpa modem
 * kembali) atau Tolak (deact diulang lewat FopTask DEAC BARU).
 *
 * Satu laporan gagal = satu percobaan. "Kasus" = semua laporan gagal ber-
 * `review_status` milik satu pelanggan sejak `customers.terminated_at`
 * (putus langganan terakhir). Nomor percobaan TIDAK disimpan — dihitung dari
 * urutan laporan, supaya tidak ada dua sumber yang bisa menyimpang.
 *
 * Angka 5 (TOLERANCE_ATTEMPTS) BUKAN kunci (revisi keputusan user K1
 * 2026-11-18): FOP boleh approve di percobaan berapa pun. Angka ini cuma
 * (a) syarat catatan wajib kalau approve sebelum percobaan ke-5 (K6) dan
 * (b) penanda "boleh ditoleransi" di UI. Jangan dijadikan blok server lagi.
 *
 * docs/plan/warehouse/analisa-review-penarikan-deac.md
 */
class DeviceRetrievalReviewService
{
    public const TOLERANCE_ATTEMPTS = 5;

    public const TAB_MENUNGGU = 'menunggu';

    public const TAB_DITOLAK = 'ditolak';

    public const TAB_DIBEBASKAN = 'dibebaskan';

    public const TABS = [self::TAB_MENUNGGU, self::TAB_DITOLAK, self::TAB_DIBEBASKAN];

    public function __construct(private readonly TicketService $tickets) {}

    /**
     * Mulai percobaan ke-5, alasan gagal boleh ditoleransi dan catatan
     * approve tidak wajib lagi (K1 + K6).
     */
    public static function isWithinTolerance(int $attempt): bool
    {
        return $attempt >= self::TOLERANCE_ATTEMPTS;
    }

    /**
     * Batasi query `task_device_retrievals` (lewat relasi dari Customer —
     * whereHas/withCount, tabel `customers` ada di query luar) ke kasus
     * penarikan yang sedang berjalan.
     *
     * Laporan tanpa `review_status` (laporan `diambil`, atau laporan gagal
     * dari sebelum fitur ini) sengaja tidak dihitung (keputusan K3 & Q2).
     * `terminated_at` NULL (data legacy yang putus sebelum kolom ada) →
     * tanpa batas bawah.
     */
    public function constrainToCase(Builder $query): Builder
    {
        return $query->whereNotNull('task_device_retrievals.review_status')
            ->where(function (Builder $q) {
                $q->whereNull('customers.terminated_at')
                    ->orWhereColumn('task_device_retrievals.created_at', '>=', 'customers.terminated_at');
            });
    }

    /**
     * Percobaan dalam kasus berjalan satu pelanggan, urut dari yang pertama.
     *
     * @return Collection<int, TaskDeviceRetrieval>
     */
    public function caseAttempts(Customer $customer): Collection
    {
        return $this->caseQuery($customer)
            ->with(['task.teamMembers.user', 'task.fopTask', 'reviewer', 'comments.user'])
            ->orderBy('task_device_retrievals.created_at')
            ->orderBy('task_device_retrievals.id')
            ->get();
    }

    /**
     * Laporan dalam kasus berjalan satu pelanggan. Join ke `customers` supaya
     * batas kasus memakai constrainToCase() yang SAMA dengan halaman list &
     * badge — satu definisi kasus di SQL, bukan salinan per method.
     *
     * @return Builder<TaskDeviceRetrieval>
     */
    private function caseQuery(Customer $customer): Builder
    {
        return $this->constrainToCase(
            TaskDeviceRetrieval::query()
                ->select('task_device_retrievals.*')
                ->join('tasks', 'tasks.id', '=', 'task_device_retrievals.task_id')
                ->join('customers', 'customers.id', '=', 'tasks.customer_id')
                ->where('customers.id', $customer->id)
        );
    }

    /**
     * Versi in-memory dari constrainToCase() untuk relasi yang sudah di-eager
     * load (query eager load tidak punya tabel `customers`, jadi batas
     * `terminated_at` tidak bisa dipasang di SQL-nya). Aturannya HARUS sama
     * dengan constrainToCase() & caseAttempts().
     *
     * @param  Collection<int, TaskDeviceRetrieval>  $retrievals
     * @return Collection<int, TaskDeviceRetrieval>
     */
    public function filterToCase(Customer $customer, Collection $retrievals): Collection
    {
        return $retrievals
            ->filter(fn (TaskDeviceRetrieval $r) => $r->review_status !== null
                && (! $customer->terminated_at || $r->created_at->gte($customer->terminated_at)))
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->values();
    }

    /**
     * Nomor percobaan laporan ini dalam kasusnya (1-based), null kalau laporan
     * ini tidak termasuk kasus (laporan `diambil` / data lama).
     */
    public function attemptNumber(TaskDeviceRetrieval $retrieval): ?int
    {
        $customer = $retrieval->task?->customer;

        if (! $customer || $retrieval->review_status === null) {
            return null;
        }

        // Satu COUNT, bukan memuat seluruh kasus + relasinya — method ini
        // dipanggil di dalam transaksi lockForUpdate approve/tolak, jadi
        // kueri yang tidak perlu cuma memperpanjang waktu baris terkunci.
        $inCase = $this->caseQuery($customer)->whereKey($retrieval->id)->exists();

        if (! $inCase) {
            return null;
        }

        return $this->caseQuery($customer)
            ->where(function (Builder $q) use ($retrieval) {
                $q->where('task_device_retrievals.created_at', '<', $retrieval->created_at)
                    ->orWhere(fn (Builder $same) => $same
                        ->where('task_device_retrievals.created_at', $retrieval->created_at)
                        ->where('task_device_retrievals.id', '<=', $retrieval->id));
            })
            ->count();
    }

    /**
     * Masih ada laporan gagal menunggu keputusan dalam kasus berjalan?
     * Penjaga tombol Ambil Alat (CustomerController::retrieveDevice() + List
     * Putus Langganan) — percobaan berikutnya harus lahir dari Tolak.
     */
    public function hasPendingReview(Customer $customer): bool
    {
        return $this->caseQuery($customer)
            ->where('task_device_retrievals.review_status', DeviceRetrievalReviewStatus::MENUNGGU->value)
            ->exists();
    }

    /**
     * Versi batch hasPendingReview() untuk satu halaman List Putus Langganan
     * (satu kueri, bukan per baris). Aturan batas kasus sama.
     *
     * @param  array<int, int>  $customerIds
     * @return array<int, true> customer_id => true
     */
    public function customerIdsWithPendingReview(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        return $this->constrainToCase(
            TaskDeviceRetrieval::query()
                ->join('tasks', 'tasks.id', '=', 'task_device_retrievals.task_id')
                ->join('customers', 'customers.id', '=', 'tasks.customer_id')
                ->whereIn('tasks.customer_id', $customerIds)
        )
            ->where('task_device_retrievals.review_status', DeviceRetrievalReviewStatus::MENUNGGU->value)
            ->distinct()
            ->pluck('tasks.customer_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * Pelanggan putus langganan dalam POP scope user yang punya kasus
     * penarikan sesuai tab. Dipakai halaman list (paginate) & hitungan chip tab.
     *
     * @return Builder<Customer>
     */
    public function customersForTab(User $user, string $tab): Builder
    {
        $query = Customer::applyUserScope($user)
            ->where('customers.status', WorkflowTransition::TERMINATED->value);

        $hasStatus = fn (DeviceRetrievalReviewStatus $status) => fn (Builder $q) => $this->constrainToCase($q)
            ->where('task_device_retrievals.review_status', $status->value);

        return match ($tab) {
            self::TAB_DIBEBASKAN => $query->whereHas('deviceRetrievals', $hasStatus(DeviceRetrievalReviewStatus::DISETUJUI)),
            // "Sedang diulang": sudah pernah ditolak, belum ada laporan baru
            // yang menunggu, belum dibebaskan, dan modem belum kembali lewat
            // percobaan berikutnya (kasus yang selesai lewat gudang tidak
            // perlu dipantau di sini lagi).
            self::TAB_DITOLAK => $query->whereHas('deviceRetrievals', $hasStatus(DeviceRetrievalReviewStatus::DITOLAK))
                ->whereDoesntHave('deviceRetrievals', $hasStatus(DeviceRetrievalReviewStatus::MENUNGGU))
                ->whereDoesntHave('deviceRetrievals', $hasStatus(DeviceRetrievalReviewStatus::DISETUJUI))
                ->whereDoesntHave('customerDevice', fn (Builder $q) => $q->whereNotNull('device_retrieved_at')),
            default => $query->whereHas('deviceRetrievals', $hasStatus(DeviceRetrievalReviewStatus::MENUNGGU)),
        };
    }

    /**
     * Laporan yang menunggu keputusan dalam POP scope user — basis badge
     * sidebar (ActionQueueBadgeService::pendingRetrievalReviews()).
     *
     * @return Builder<TaskDeviceRetrieval>
     */
    public function pendingForUser(User $user): Builder
    {
        return TaskDeviceRetrieval::query()
            ->where('review_status', DeviceRetrievalReviewStatus::MENUNGGU->value)
            // constrainToCase() di subquery `customers` — merujuk
            // task_device_retrievals milik query luar (subquery berkorelasi).
            ->whereHas('task.customer', fn (Builder $q) => $this->constrainToCase(
                $q->applyUserScope($user)->where('customers.status', WorkflowTransition::TERMINATED->value)
            ));
    }

    /**
     * Dipanggil setiap laporan DEAC disimpan teknisi
     * (TaskDeviceRetrievalController::store()). Laporan gagal masuk antrian
     * review; laporan `diambil` tidak (K3). Field keputusan selalu
     * dikosongkan — laporan yang dikirim ulang adalah laporan baru untuk FOP.
     */
    public function markSubmitted(TaskDeviceRetrieval $retrieval): void
    {
        $retrieval->update([
            'review_status' => $retrieval->outcome->isRetrieved() ? null : DeviceRetrievalReviewStatus::MENUNGGU,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ]);
    }

    /**
     * FOP menerima alasan: pelanggan dibebaskan, kasus ditutup tanpa modem
     * kembali. Boleh di percobaan berapa pun (K1); sebelum percobaan ke-5
     * catatan WAJIB (K6) — jejak kenapa dibebaskan lebih awal.
     *
     * @throws ValidationException
     */
    public function approve(TaskDeviceRetrieval $retrieval, User $actor, ?string $note): TaskDeviceRetrieval
    {
        $note = $this->cleanNote($note);

        $approved = DB::transaction(function () use ($retrieval, $actor, $note) {
            [$locked, $attempt] = $this->lockReviewable($retrieval);

            if (! self::isWithinTolerance($attempt) && $note === null) {
                throw ValidationException::withMessages([
                    'review_note' => 'Catatan wajib diisi: jelaskan kenapa pelanggan dibebaskan sebelum '.self::TOLERANCE_ATTEMPTS." percobaan (ini percobaan ke-{$attempt}).",
                ]);
            }

            $before = $locked->toArray();
            $locked->update([
                'review_status' => DeviceRetrievalReviewStatus::DISETUJUI,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            // Pelanggan legacy bisa belum punya baris customer_devices — pola
            // placeholder sama dengan CustomerController::retrieveDevice().
            $customer = $locked->task->customer;
            $device = $customer->customerDevice
                ?? $customer->customerDevice()->create(['device_type' => CustomerDevice::LEGACY_DEVICE_TYPE]);
            $device->update(['retrieval_waived_at' => now(), 'retrieval_waived_by' => $actor->id]);
            $this->syncTaskReviewStatus($locked, DeviceRetrievalReviewStatus::DISETUJUI);

            AuditLog::log($locked, 'device_retrieval_review_approved', $before, $locked->fresh()->toArray() + ['attempt' => $attempt]);

            return $locked;
        });

        $this->notifyTeam($approved, 'Laporan Penarikan Alat Disetujui', 'FOP menerima alasan alat tidak bisa diambil. Pelanggan dibebaskan dari penarikan alat.', NotificationType::SUCCESS);

        return $approved;
    }

    /**
     * FOP menolak alasan: deact diulang lewat FopTask DEAC BARU (bukan task
     * yang sama dikembalikan ke teknisi — tiap percobaan harus terhitung, Q6).
     * FopTask baru selalu DRAFT; "Langsung jadwalkan" cuma redirect ke form
     * assign /fop-tasks yang sudah ada (Q4/Q8), tanpa logic assign baru.
     *
     * @throws ValidationException
     */
    public function reject(TaskDeviceRetrieval $retrieval, User $actor, ?string $note): FopTask
    {
        $note = $this->cleanNote($note);

        if ($note === null) {
            throw ValidationException::withMessages(['review_note' => 'Alasan penolakan wajib diisi.']);
        }

        [$rejected, $fopTask] = DB::transaction(function () use ($retrieval, $actor, $note) {
            [$locked, $attempt] = $this->lockReviewable($retrieval);
            $customer = $locked->task->customer;

            // Syarat yang sama dengan tombol Ambil Alat
            // (CustomerController::retrieveDevice()) — FopTask DEAC butuh POP & Desa.
            if (! $customer->pop_id || ! $customer->village_id) {
                throw ValidationException::withMessages([
                    'review_note' => 'Pelanggan ini belum lengkap POP/Desa — lengkapi dulu datanya sebelum deact diulang.',
                ]);
            }

            $hasOpenTask = FopTask::query()
                ->where('customer_id', $customer->id)
                ->where('category', TaskType::AMBIL_MODEM->value)
                ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
                ->exists();

            if ($hasOpenTask) {
                throw ValidationException::withMessages([
                    'review_note' => 'Sudah ada task pengambilan alat yang masih berjalan untuk pelanggan ini.',
                ]);
            }

            $before = $locked->toArray();
            $locked->update([
                'review_status' => DeviceRetrievalReviewStatus::DITOLAK,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            // Task lama tetap SELESAI (pekerjaan lapangannya memang sudah
            // terjadi) — "ditolak" cuma di status review; ulangannya task baru.
            $this->syncTaskReviewStatus($locked, DeviceRetrievalReviewStatus::DITOLAK);

            $fopTask = $this->tickets->createDeviceRetrievalTask($customer, $actor, "Review Penarikan Alat (percobaan ke-{$attempt} ditolak)");

            AuditLog::log($locked, 'device_retrieval_review_rejected', $before, $locked->fresh()->toArray() + [
                'attempt' => $attempt,
                'new_fop_task_number' => $fopTask->task_number,
            ]);

            return [$locked, $fopTask];
        });

        $this->notifyTeam($rejected, 'Laporan Penarikan Alat Ditolak', "Alasan ditolak FOP: {$note}. Deact dijadwalkan ulang ({$fopTask->task_number}).", NotificationType::ERROR);

        return $fopTask;
    }

    /**
     * Komentar FOP/CS/BD. Tidak mengubah status apa pun dan boleh di laporan
     * yang sudah diputuskan (diskusi bisa berlanjut). Append-only.
     */
    public function comment(TaskDeviceRetrieval $retrieval, User $actor, string $body): DeviceRetrievalComment
    {
        $comment = $retrieval->comments()->create([
            'user_id' => $actor->id,
            'body' => trim($body),
        ]);

        AuditLog::log($comment, 'create', null, $comment->toArray());

        return $comment;
    }

    /**
     * Kunci baris & pastikan masih bisa diputuskan. Dicek DI DALAM transaksi
     * supaya dua FOP yang klik bersamaan tidak sama-sama lolos (task DEAC
     * dobel / status saling timpa).
     *
     * @return array{0: TaskDeviceRetrieval, 1: int} laporan terkunci + nomor percobaannya
     *
     * @throws ValidationException
     */
    private function lockReviewable(TaskDeviceRetrieval $retrieval): array
    {
        $locked = TaskDeviceRetrieval::query()->whereKey($retrieval->id)->lockForUpdate()->firstOrFail();
        $locked->load('task.customer.customerDevice');

        if ($locked->review_status !== DeviceRetrievalReviewStatus::MENUNGGU) {
            throw ValidationException::withMessages(['review_note' => 'Laporan ini sudah diputuskan atau ditutup sebelumnya.']);
        }

        $customer = $locked->task?->customer;

        // Langganan Lagi menghentikan kasus (Q5) — laporan lamanya tidak
        // boleh diputuskan lagi (membebaskan pelanggan aktif tidak bermakna).
        if (! $customer || $customer->status !== WorkflowTransition::TERMINATED->value) {
            throw ValidationException::withMessages(['review_note' => 'Pelanggan ini sudah tidak berstatus putus langganan — kasus penarikan alat berhenti.']);
        }

        // Modem sudah kembali lewat jalur lain (diantar pelanggan ke gudang):
        // Tolak akan membuat task DEAC untuk modem yang sudah di gudang, dan
        // Approve menandai "dibebaskan" alat yang sudah diambil. Normalnya
        // laporan ini sudah ditutup closeOpenCase(); ini penjaga lapis kedua.
        if ($customer->customerDevice?->device_retrieved_at) {
            throw ValidationException::withMessages(['review_note' => 'Modem pelanggan ini sudah kembali ke gudang — kasus penarikan alat selesai.']);
        }

        // Laporan dari masa putus langganan SEBELUMNYA (pelanggan Langganan
        // Lagi lalu putus lagi) — di luar kasus berjalan. Tanpa penjaga ini,
        // tab lama yang masih terbuka bisa membebaskan kasus baru sebagai
        // "percobaan ke-0".
        $attempt = $this->attemptNumber($locked);

        if ($attempt === null) {
            throw ValidationException::withMessages(['review_note' => 'Laporan ini dari masa putus langganan sebelumnya, bukan kasus penarikan yang sedang berjalan.']);
        }

        return [$locked, $attempt];
    }

    /**
     * Tutup laporan yang masih MENUNGGU tanpa keputusan FOP karena kasusnya
     * berhenti: modem diantar pelanggan ke gudang (walk-in) atau pelanggan
     * Langganan Lagi. Tanpa ini laporan tersebut bisa ditolak (task DEAC
     * untuk modem yang sudah kembali) atau jadi yatim — tidak tampil di
     * halaman review, sementara review generik di Detail Task diblokir.
     *
     * Sengaja semua laporan MENUNGGU pelanggan ini (tanpa batas kasus):
     * laporan siklus lama yang masih menggantung juga ikut tertutup.
     * Dipanggil di dalam transaksi pemanggil.
     */
    public function closeOpenCase(Customer $customer, string $reason, ?User $actor = null): int
    {
        $pending = TaskDeviceRetrieval::query()
            ->where('review_status', DeviceRetrievalReviewStatus::MENUNGGU->value)
            ->whereHas('task', fn (Builder $q) => $q->where('customer_id', $customer->id))
            ->lockForUpdate()
            ->get();

        foreach ($pending as $retrieval) {
            $before = $retrieval->toArray();
            $retrieval->update([
                'review_status' => DeviceRetrievalReviewStatus::DITUTUP,
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
                'review_note' => $reason,
            ]);
            $this->syncTaskReviewStatus($retrieval, DeviceRetrievalReviewStatus::DITUTUP);

            AuditLog::log($retrieval, 'device_retrieval_review_closed', $before, $retrieval->fresh()->toArray());
        }

        return $pending->count();
    }

    /**
     * Cerminkan keputusan review ke `tasks.fop_review_status` (keputusan user
     * #5-A): task tidak tertahan `pending`, panel review di Detail Task
     * hilang dengan sendirinya, dan TaskObserver mencatat SATU baris riwayat
     * FOP. Lewat update() biasa — observer memang harus jalan.
     */
    private function syncTaskReviewStatus(TaskDeviceRetrieval $retrieval, DeviceRetrievalReviewStatus $status): void
    {
        $retrieval->task?->update(['fop_review_status' => $status->taskReviewStatus()]);
    }

    private function cleanNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : $note;
    }

    private function notifyTeam(TaskDeviceRetrieval $retrieval, string $title, string $message, NotificationType $type): void
    {
        $task = $retrieval->task;
        $task->loadMissing('teamMembers.user');
        $url = route('tasks.show', $task->id, false);

        foreach ($task->teamMembers as $member) {
            $member->user?->notify(new AppNotification(
                title: "{$title}: {$task->task_number}",
                message: $message,
                actionUrl: $url,
                type: $type,
            ));
        }
    }
}
