<?php

namespace App\Models;

use App\Enums\FopTaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketBucket;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Enums\TicketHistoryAction;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ticket — tiket internal PERUSAHAAN (helpdesk/NOC/sales/admin), beda dari
 * FopTask yang internal FOP. Tiket adalah *permintaan*; FopTask adalah
 * *penugasan* — TAPI BEDA dari versi lama modul ini, FopTask sekarang TIDAK
 * lagi auto-dibuat saat tiket disubmit. Tiket mulai di tangan Helpdesk
 * (`handler` = HELPDESK), dan cuma nyampe FOP (FopTask kebentuk) kalau
 * eksplisit dieskalasi — lihat TicketService::create()/escalateToFop().
 *
 * Cuma berlaku buat tipe MTN & C-REQ — lihat TaskType::ticketValues().
 */
#[Fillable([
    'ticket_number',
    'type',
    'issue_category_id',
    'customer_id',
    'pop_id',
    'customer_name',
    'customer_address',
    'customer_village',
    'customer_phone',
    'customer_odp',
    'customer_package',
    'customer_device',
    'customer_latitude',
    'customer_longitude',
    'detail_keluhan',
    'catatan_teknis',
    'priority',
    'created_by',
    'fop_task_id',
    'handler',
    'status',
    'resolved_at',
])]
class Ticket extends Model
{
    use HasPopScope;

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'priority' => FopTaskPriority::class,
            'customer_latitude' => 'decimal:7',
            'customer_longitude' => 'decimal:7',
            'handler' => TicketHandler::class,
            'status' => TicketHandlingStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Tiket "Terputus" — pernah dieskalasi ke FOP tapi FopTask-nya udah dihapus
     * FOP (`fop_task_id` nullOnDelete). Beda dari tiket yang MEMANG belum pernah
     * ke FOP, makanya pembedanya `handler`, bukan cuma cek null `fop_task_id`.
     *
     * bucket() menjatuhkan kasus ini ke `dibatalkan` (biar submenu arsip tetap
     * nemu), TAPI di halaman History dia wajib dilabeli sendiri — kalau ikut
     * kehitung pembatalan, angka "berapa tiket dibatalkan" jadi salah.
     */
    public function isOrphan(): bool
    {
        return $this->handler === TicketHandler::FOP && $this->fop_task_id === null;
    }

    /**
     * Lama tiket berada di meja Ticketing, dalam menit — `resolved_at` −
     * `created_at`.
     *
     * `resolved_at` = kapan tiket LEPAS dari Ticketing, dan itu punya dua arti
     * tergantung jalurnya (lihat docs/plan/analisa-halaman-history-ticketing.md §4.1):
     *   - ditutup Helpdesk/NOC → beneran selesai
     *   - diserahkan ke FOP    → waktu penyerahan; progres lapangan setelahnya
     *                            TIDAK mengubah nilai ini
     *
     * null (BUKAN 0) kalau belum ada titik lepas itu — mis. tiket masih
     * dikerjakan, atau dibatalkan (dibatalkan bukan diselesaikan). Nol dan
     * "tidak ada" dua hal berbeda waktu dirata-rata di halaman History.
     */
    public function resolutionMinutes(): ?int
    {
        if (! $this->resolved_at) {
            return null;
        }

        return (int) $this->created_at->diffInMinutes($this->resolved_at);
    }

    /**
     * Durasi format H:MM:SS — meniru kolom SOLVING TIME di sheet lama yang
     * digantikan halaman History (docs/plan/analisa-halaman-history-ticketing.md §5).
     *
     * Dihitung dari DETIK, bukan dari resolutionMinutes(): tiket yang ditangani
     * di bawah satu menit (sering, mis. Helpdesk langsung selesaikan atau
     * langsung lempar ke FOP) tampil "0:00:00" kalau dibulatkan ke menit —
     * kelihatan seperti data rusak.
     */
    public function solvingTimeLabel(): ?string
    {
        if (! $this->resolved_at) {
            return null;
        }

        $seconds = (int) $this->created_at->diffInSeconds($this->resolved_at);

        return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * Link Google Maps dari koordinat snapshot — null kalau salah satu
     * kosong (pelanggan belum punya titik koordinat saat ticket dibuat).
     */
    public function customerMapsUrl(): ?string
    {
        if (! $this->customer_latitude || ! $this->customer_longitude) {
            return null;
        }

        return "https://www.google.com/maps/search/?api=1&query={$this->customer_latitude},{$this->customer_longitude}";
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function issueCategory(): BelongsTo
    {
        return $this->belongsTo(TicketIssueCategory::class, 'issue_category_id');
    }

    /**
     * Pengirim tiket — ini yang tampil sebagai "Assign by" di UI.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fopTask(): BelongsTo
    {
        return $this->belongsTo(FopTask::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /**
     * Riwayat sisi Ticketing. Kembaran FopTask::statusHistories() — satu
     * pembatalan nulis ke dua-duanya (lihat FopTaskObserver).
     */
    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class)->orderByDesc('happened_at');
    }

    /**
     * Saring tiket per bucket submenu Ticketing. Dua rezim, tergantung `handler`:
     *  - handler=FOP  → turunan FopTask.status (existing, sama kayak sebelumnya).
     *  - handler=HELPDESK/NOC → belum pernah ke FOP, turunan `status`/`handler`
     *    kolom sendiri (lihat bucket()).
     *
     * `handler=FOP` + `fop_task_id` null = FopTask-nya udah dihapus FOP
     * (orphan/"Terputus", nullOnDelete) — beda dari tiket yang MEMANG belum
     * pernah dieskalasi, makanya pembeda dua kasus null itu pakai `handler`,
     * BUKAN `fop_task_id` doang.
     */
    public function scopeInBucket(Builder $query, TicketBucket $bucket): Builder
    {
        return $query->where(function (Builder $q) use ($bucket) {
            $q->where(function (Builder $fopQ) use ($bucket) {
                $fopQ->where('handler', TicketHandler::FOP->value)
                    ->whereHas('fopTask', fn (Builder $f) => $f->whereIn('status', $bucket->statusValues()));
            });

            if ($bucket->includesOrphans()) {
                $q->orWhere(function (Builder $orphanQ) {
                    $orphanQ->where('handler', TicketHandler::FOP->value)->whereNull('fop_task_id');
                });
            }

            if ($bucket === TicketBucket::SELESAI) {
                $q->orWhere(function (Builder $internalQ) {
                    $internalQ->whereIn('handler', [TicketHandler::HELPDESK->value, TicketHandler::NOC->value])
                        ->where('status', TicketHandlingStatus::CLOSED->value);
                });
            }

            if ($bucket === TicketBucket::DIBATALKAN) {
                $q->orWhere(function (Builder $internalQ) {
                    $internalQ->whereIn('handler', [TicketHandler::HELPDESK->value, TicketHandler::NOC->value])
                        ->where('status', TicketHandlingStatus::CANCELLED->value);
                });
            }

            if ($bucket === TicketBucket::MASUK) {
                $q->orWhere(function (Builder $internalQ) {
                    $internalQ->where('handler', TicketHandler::HELPDESK->value)
                        ->where('status', TicketHandlingStatus::OPEN->value);
                });
            }

            if ($bucket === TicketBucket::DIPROSES) {
                $q->orWhere(function (Builder $internalQ) {
                    $internalQ->where('handler', TicketHandler::NOC->value)
                        ->where('status', TicketHandlingStatus::OPEN->value);
                });
            }
        });
    }

    /**
     * Tiket yang masih "aktif" buat panel worksheet (List Task Ticketing) —
     * exclude Selesai & Dibatalkan dari akar query, BUKAN cuma disaring tab
     * client-side. Helpdesk/NOC fokus cuma ke kerjaan yang masih jalan;
     * tiket yang udah kelar cari lewat /tickets/selesai atau /tickets/dibatalkan.
     */
    public function scopeActiveForWorksheet(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where(function (Builder $internal) {
                $internal->whereIn('handler', [TicketHandler::HELPDESK->value, TicketHandler::NOC->value])
                    ->where('status', TicketHandlingStatus::OPEN->value);
            })->orWhere(function (Builder $fop) {
                $fop->where('handler', TicketHandler::FOP->value)
                    ->whereHas('fopTask', fn (Builder $f) => $f->whereNotIn('status', [
                        TaskStatus::SELESAI->value,
                        TaskStatus::DIBATALKAN->value,
                    ]));
            });
        });
    }

    /**
     * Bucket submenu tiket ini — versi single-instance dari `scopeInBucket()`
     * (gak query ulang, tinggal cocokin status yang udah di-load). Dipakai
     * buat aksen visual di daftar inbox; gunakan `fopTask` yang udah
     * eager-loaded, JANGAN dipanggil di loop tanpa eager load atau kena N+1.
     */
    public function bucket(): TicketBucket
    {
        if ($this->handler === TicketHandler::FOP) {
            $status = $this->fopTask?->status;

            if (! $status) {
                return TicketBucket::DIBATALKAN;
            }

            foreach (TicketBucket::cases() as $bucket) {
                if (in_array($status, $bucket->statuses(), true)) {
                    return $bucket;
                }
            }

            return TicketBucket::DIBATALKAN;
        }

        if ($this->status === TicketHandlingStatus::CLOSED) {
            return TicketBucket::SELESAI;
        }

        if ($this->status === TicketHandlingStatus::CANCELLED) {
            return TicketBucket::DIBATALKAN;
        }

        return $this->handler === TicketHandler::NOC ? TicketBucket::DIPROSES : TicketBucket::MASUK;
    }

    /**
     * Role yang sah bertindak atas tiket ini SEKARANG — satu-satunya sumber
     * dipakai TicketService::assertActorOwnsTicket() (real authz) DAN
     * actionFlagsFor() (UI gate), biar dua-duanya konsisten.
     *
     * Tiket di tangan NOC dipegang BERSAMA `helpdesk` + `noc` (ADHOC-06,
     * 2026-07-29). Dulu kepemilikan itu bergantung window "Pending NOC"
     * (`noc_checked_at` null = berdua, terisi = NOC saja); window itu dihapus
     * bareng aksi Oncheck NOC, dan kepemilikan bersamanya dipertahankan
     * permanen — Helpdesk yang mengirim tiket tetap boleh menutup/membatalkan
     * tiketnya sendiri walau NOC sedang memprosesnya.
     *
     * @return string[]
     */
    public function holderRoles(): array
    {
        return match ($this->handler) {
            TicketHandler::HELPDESK => ['helpdesk'],
            TicketHandler::NOC => ['helpdesk', 'noc'],
            TicketHandler::FOP => [],
        };
    }

    /**
     * Status FopTask kalau tiket udah nyampe FOP (`handler` = FOP). null
     * kalau belum pernah ke FOP SAMA SEKALI, ATAU udah tapi FopTask-nya
     * kehapus (orphan) — dua kasus itu dibedain lewat `handler`, bukan cuma
     * cek null di sini (lihat statusLabel()/bucket()).
     *
     * SENGAJA dinamai `resolveStatus()`, BUKAN `status()` — `status` udah
     * jadi nama kolom (`TicketHandlingStatus`), method zero-argument bernama
     * sama bakal ketiban logika relasi Eloquent kalau suatu saat konflik.
     * Riwayat masalah yang sama pernah beneran kejadian (500 di /tickets/masuk).
     */
    public function resolveStatus(): ?TaskStatus
    {
        return $this->handler === TicketHandler::FOP ? $this->fopTask?->status : null;
    }

    /**
     * Label status buat UI — dua rezim sama kayak bucket()/scopeInBucket().
     */
    public function statusLabel(): string
    {
        if ($this->handler === TicketHandler::FOP) {
            $status = $this->resolveStatus();

            return $status ? $status->displayLabel() : 'Terputus';
        }

        if ($this->status === TicketHandlingStatus::CLOSED) {
            return 'Selesai ('.$this->handler->label().')';
        }

        if ($this->status === TicketHandlingStatus::CANCELLED) {
            return 'Dibatalkan ('.$this->handler->label().')';
        }

        // Label "Pending NOC" & "OnCheck NOC" dihapus (ADHOC-06) — tiket yang
        // dikirim ke NOC langsung "Diproses NOC", gak ada state antara.
        if ($this->handler === TicketHandler::NOC) {
            return 'Diproses NOC';
        }

        return 'Ditangani '.$this->handler->label();
    }

    public function statusBadgeClasses(): string
    {
        if ($this->handler === TicketHandler::FOP) {
            $status = $this->resolveStatus();

            return $status ? $status->displayBadgeClasses() : 'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700';
        }

        if ($this->status === TicketHandlingStatus::CLOSED) {
            return 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-400';
        }

        if ($this->status === TicketHandlingStatus::CANCELLED) {
            return 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
        }

        return $this->handler === TicketHandler::NOC
            ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50 text-amber-700 dark:text-amber-400'
            : 'bg-sky-50 dark:bg-sky-900/20 border-sky-200 dark:border-sky-800/50 text-sky-700 dark:text-sky-400';
    }

    /**
     * Atribusi "siapa ngapain" — dibuat siapa (creator(), udah ada), diselesaikan
     * siapa, dikirim ke NOC siapa, dikirim ke FOP siapa. Diturunkan dari
     * `histories` yang UDAH eager-loaded (orderByDesc happened_at bawaan
     * relasi), BUKAN query baru — pemanggil WAJIB eager-load 'histories.actor'
     * dulu, sama kayak aturan bucket()/fopTask di atas.
     */
    public function closedBy(): ?User
    {
        return $this->histories->firstWhere('action', TicketHistoryAction::DISELESAIKAN)?->actor;
    }

    /**
     * Siapa yang dulu meng-Oncheck tiket ini, buat tiket LAMA saja. Aksi
     * Oncheck NOC sudah dihapus (ADHOC-06, 2026-07-29) sehingga baris riwayat
     * `dicek_noc` tidak pernah lahir lagi — method ini dipertahankan supaya
     * riwayat tiket lama tetap terbaca utuh, bukan buat alur baru.
     */
    public function checkedBy(): ?User
    {
        return $this->histories->firstWhere('action', TicketHistoryAction::DICEK_NOC)?->actor;
    }

    /**
     * Siapa yang membatalkan tiket. Sisi Ticketing (`TicketService::cancel()`)
     * dan sisi FOP (`FopTaskObserver`) sama-sama menulis action `dibatalkan`,
     * jadi satu pencarian ini menutup dua jalur.
     */
    public function cancelledBy(): ?User
    {
        return $this->histories->firstWhere('action', TicketHistoryAction::DIBATALKAN)?->actor;
    }

    public function escalatedToNocBy(): ?User
    {
        return $this->histories
            ->first(fn (TicketHistory $h) => $h->action === TicketHistoryAction::DIESKALASI && $h->to_status === TicketHandler::NOC->value)
            ?->actor;
    }

    public function escalatedToFopBy(): ?User
    {
        return $this->histories
            ->first(fn (TicketHistory $h) => $h->action === TicketHistoryAction::DIESKALASI && $h->to_status === TicketHandler::FOP->value)
            ?->actor;
    }

    /**
     * Siapa yang ngembaliin tiket ke Helpdesk (Gap #7 — jalur pemulihan salah
     * kirim, docs/plan/analisa-efektivitas-worksheet-ticketing.md). Cuma
     * relevan kalau tiket PERNAH balik dari NOC ke Helpdesk lagi.
     */
    public function returnedToHelpdeskBy(): ?User
    {
        return $this->histories->firstWhere('action', TicketHistoryAction::DIKEMBALIKAN)?->actor;
    }

    /**
     * Flag tombol Selesaikan Sendiri/Kirim ke NOC/Kirim ke FOP/Kembalikan ke
     * Helpdesk — SATU-SATUNYA sumber buat UI (worksheet panel, index bucket,
     * halaman detail). Cuma gerbang tampilan; otorisasi sungguhan tetap di
     * TicketService::assertActorOwnsTicket()/assertTicketStillOpen().
     *
     * can_return_to_helpdesk cuma buat NOC — Helpdesk gak punya "turun"
     * lagi (dia asal-usulnya), dan FOP udah terminal buat sisi Ticketing
     * (assertTicketStillOpen() nolak semua aksi begitu handler=FOP, gak ada
     * "kembali dari FOP" lewat Ticketing — pembatalan FOP tetap lewat
     * /fop-tasks, lihat CLAUDE.md § Sinkronisasi Ticket ↔ FopTask).
     *
     * @return array{can_close: bool, can_escalate_noc: bool, can_escalate_fop: bool, can_return_to_helpdesk: bool, can_cancel: bool}
     */
    public function actionFlagsFor(User $user): array
    {
        $isHolder = $user->hasFullAccess()
            || collect($this->holderRoles())->contains(fn ($role) => $user->hasRole($role));

        $canAct = $this->handler !== TicketHandler::FOP
            && $this->status === TicketHandlingStatus::OPEN
            && $user->hasPermission('tickets.update')
            && $isHolder;

        // `can_oncheck_noc` dihapus bareng aksi Oncheck NOC (ADHOC-06) —
        // tiket yang dikirim ke NOC langsung berstatus diproses, gak ada lagi
        // langkah "terima dulu". Jangan dihidupkan lagi tanpa mengembalikan
        // window Pending NOC utuh (kolom + guard + label + halaman).
        return [
            'can_close' => $canAct,
            'can_escalate_noc' => $canAct && $this->handler === TicketHandler::HELPDESK,
            'can_escalate_fop' => $canAct,
            'can_return_to_helpdesk' => $canAct && $this->handler === TicketHandler::NOC,
            'can_cancel' => $canAct && $user->hasPermission('tickets.cancel'),
        ];
    }
}
