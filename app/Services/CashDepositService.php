<?php

namespace App\Services;

use App\Enums\CashDepositChannel;
use App\Enums\CashDepositStatus;
use App\Enums\NotificationType;
use App\Models\AuditLog;
use App\Models\CashDeposit;
use App\Models\CollectorDeposit;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siklus hidup Setoran Kas Admin: setor → diperiksa → terverifikasi/selisih →
 * selisih dihapus buku.
 *
 * Semua transisi ada di sini, bukan tersebar di controller, karena tiap
 * transisi menyentuh uang dan punya invariant yang tak boleh bisa dilewati
 * lewat jalur masuk lain (artisan, import, tinker).
 *
 * docs/plan/kolektor/analisa-setoran-kas-admin.md §4.4.
 */
class CashDepositService
{
    public function __construct(private readonly AdminCashBalanceService $balance) {}

    /**
     * Admin menyetorkan SELURUH saldo tunainya ke owner/bank.
     *
     * Bukan sebagian — aturan yang sama dengan setoran kolektor: tidak boleh
     * ada saldo mengendap, dan itu membuang seluruh kebutuhan logika alokasi
     * setoran parsial.
     *
     * Yang disimpan adalah RELASI ke sumber, bukan totalnya. Admin boleh terus
     * menerima pembayaran & memverifikasi setoran kolektor selagi setoran
     * kasnya menunggu pemeriksaan; yang masuk sesudah submit menjadi saldo
     * baru, tidak menggeser angka yang sedang dihitung Owner.
     *
     * @param  array{channel: string, bank_name?: string|null, account_number?: string|null, reference_no?: string|null, proof_path?: string|null, note?: string|null}  $tujuan
     */
    public function submit(User $admin, array $tujuan, ?string $idempotencyKey = null): CashDeposit
    {
        if ($idempotencyKey) {
            $existing = CashDeposit::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $channel = CashDepositChannel::from($tujuan['channel']);

        // Setoran ke bank tanpa identitas tujuan tidak bisa dicocokkan dengan
        // mutasi rekening — catatan seperti itu tak lebih baik daripada tidak
        // ada catatan sama sekali.
        if ($channel->requiresBankDetails() && (blank($tujuan['bank_name'] ?? null) || blank($tujuan['reference_no'] ?? null))) {
            throw new RuntimeException('Setoran transfer wajib menyertakan nama bank dan nomor referensi.');
        }

        [$deposit, $sumberCount, $total] = DB::transaction(function () use ($admin, $channel, $tujuan, $idempotencyKey) {
            // Dikunci supaya dua submit yang lahir bersamaan tidak membagi
            // sumber yang sama ke dua setoran berbeda.
            $collectorDeposits = $this->balance->unsettledCollectorDepositsQuery($admin)->lockForUpdate()->get();
            $manualPayments = $this->balance->unsettledManualPaymentsQuery($admin)->lockForUpdate()->get();

            if ($collectorDeposits->isEmpty() && $manualPayments->isEmpty()) {
                throw new RuntimeException('Tidak ada uang tunai yang belum disetorkan. Saldo kas Anda sedang kosong.');
            }

            $total = Money::add(
                Money::sum($collectorDeposits->map(fn (CollectorDeposit $deposit) => $deposit->cashReceivedByOffice())),
                Money::sum($manualPayments->pluck('amount'))
            );

            $deposit = CashDeposit::create([
                'deposit_number' => $this->generateDepositNumber(),
                'depositor_id' => $admin->id,
                // POP representatif untuk listing saja. Otorisasi pemeriksaan
                // TIDAK bersandar ke kolom ini — lihat
                // assertVerifierCanSeeAllSources().
                'pop_id' => $collectorDeposits->first()?->pop_id ?? $manualPayments->first()?->pop_id,
                'status' => CashDepositStatus::MENUNGGU_VERIFIKASI->value,
                'channel' => $channel->value,
                'bank_name' => $tujuan['bank_name'] ?? null,
                'account_number' => $tujuan['account_number'] ?? null,
                'reference_no' => $tujuan['reference_no'] ?? null,
                'proof_path' => $tujuan['proof_path'] ?? null,
                'note' => $tujuan['note'] ?? null,
                'submitted_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($collectorDeposits->isNotEmpty()) {
                CollectorDeposit::whereIn('id', $collectorDeposits->pluck('id'))
                    ->update(['cash_deposit_id' => $deposit->id]);
            }

            if ($manualPayments->isNotEmpty()) {
                Payment::whereIn('id', $manualPayments->pluck('id'))
                    ->update(['cash_deposit_id' => $deposit->id]);
            }

            $this->audit($deposit, $admin, 'kas_disetorkan', [
                'setoran_kolektor' => $collectorDeposits->count(),
                'pembayaran_manual' => $manualPayments->count(),
                'total_tercatat' => $total,
                'channel' => $channel->value,
            ]);

            return [$deposit, $collectorDeposits->count() + $manualPayments->count(), $total];
        });

        // Kabar dikirim SESUDAH commit dan kegagalannya tak boleh membatalkan
        // setoran — pelajaran yang sama dengan jalur setoran kolektor: admin
        // tak boleh gagal menyerahkan uang cuma karena layanan kabar rusak.
        $this->safelyNotify(fn () => $this->notifyVerifiers($deposit, $admin, $sumberCount, $total));

        return $deposit;
    }

    /**
     * Owner/atasan menghitung uang fisik (atau mencocokkan mutasi bank) lalu
     * memutuskan.
     *
     * `difference = declared − computed`:
     *   = 0  → TERVERIFIKASI
     *   < 0  → SELISIH_KURANG — kewajiban admin
     *   > 0  → SELISIH_LEBIH  — uang yang asalnya belum jelas
     *
     * Untuk `difference` ≠ 0 apa pun arahnya, `note` WAJIB.
     */
    public function verify(CashDeposit $deposit, User $verifier, float $declaredAmount, ?string $note = null): CashDeposit
    {
        $this->assertNotSentinel($deposit);
        $this->assertPending($deposit);
        $this->assertVerifierIsNotDepositor($deposit, $verifier);
        $this->assertVerifierCanSeeAllSources($deposit, $verifier);

        $verified = DB::transaction(function () use ($deposit, $verifier, $declaredAmount, $note) {
            $locked = CashDeposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            $computed = $locked->computedAmount();
            // Ranah sen, bukan float: galat 0,0000008 di sini menentukan
            // setoran ditandai cocok atau selisih.
            $difference = Money::sub($declaredAmount, $computed);

            if (! Money::isZero($difference) && blank($note)) {
                throw new RuntimeException('Selisih wajib disertai catatan alasan sebelum setoran kas ditutup.');
            }

            $locked->update([
                'declared_amount' => round($declaredAmount, 2),
                'difference' => $difference,
                'note' => $note ?? $locked->note,
                'status' => match (true) {
                    Money::isZero($difference) => CashDepositStatus::TERVERIFIKASI->value,
                    Money::lessThan($difference, 0) => CashDepositStatus::SELISIH_KURANG->value,
                    default => CashDepositStatus::SELISIH_LEBIH->value,
                },
                'verified_by' => $verifier->id,
                'verified_at' => now(),
            ]);

            $this->audit($locked, $verifier, 'kas_diverifikasi', [
                'tercatat_sistem' => $computed,
                'uang_fisik' => round($declaredAmount, 2),
                'selisih' => $difference,
                'status' => $locked->status->value,
            ]);

            return $locked;
        });

        $this->safelyNotify(fn () => $this->notifyDepositorOnVerification($verified));

        return $verified;
    }

    /**
     * Hapus buku — titik di mana selisih kas diakui selesai. Owner saja
     * (digerbang permission di route), wajib beralasan.
     *
     * Berlaku untuk KEDUA arah selisih. Kelebihan kas juga butuh penutupan
     * resmi: kalau hanya kekurangan yang bisa ditutup, selisih lebih menggantung
     * selamanya di laporan dan orang berhenti membacanya.
     */
    public function writeOff(CashDeposit $deposit, User $approver, string $reason): CashDeposit
    {
        $this->assertNotSentinel($deposit);

        if (! $deposit->status->isOpenDifference()) {
            throw new RuntimeException('Hapus buku hanya berlaku untuk setoran kas yang berselisih.');
        }

        $this->assertVerifierIsNotDepositor($deposit, $approver);
        $this->assertVerifierCanSeeAllSources($deposit, $approver);

        $written = DB::transaction(function () use ($deposit, $approver, $reason) {
            $locked = CashDeposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isOpenDifference()) {
                throw new RuntimeException('Hapus buku hanya berlaku untuk setoran kas yang berselisih.');
            }

            $nilai = abs((float) $locked->difference);

            $locked->update([
                'status' => CashDepositStatus::DIHAPUS_BUKU->value,
                'written_off_by' => $approver->id,
                'written_off_at' => now(),
                'write_off_reason' => $reason,
            ]);

            $this->audit($locked, $approver, 'kas_dihapus_buku', [
                'nilai_dihapus' => $nilai,
                'alasan' => $reason,
            ]);

            return $locked;
        });

        $this->safelyNotify(fn () => $written->depositor?->notify(new AppNotification(
            title: "Setoran kas {$written->deposit_number}: ".$written->status->label(),
            message: 'Selisih Rp'.number_format(abs((float) $written->difference), 0, ',', '.')
                .' ditutup oleh Owner. Alasan: '.$reason,
            actionUrl: route('cash-deposits.index'),
            type: NotificationType::WARNING
        )));

        return $written;
    }

    /**
     * Sentinel titik nol bukan setoran — ia tidak pernah boleh diverifikasi,
     * dihapus buku, atau disentuh aksi apa pun.
     *
     * Guard-nya di service, bukan cuma di view: view yang menyembunyikan tombol
     * tidak menghalangi POST yang dirakit tangan, dan yang dipertaruhkan adalah
     * satu-satunya baris yang menahan seluruh data lama supaya tidak hidup lagi
     * sebagai kewajiban setor.
     */
    private function assertNotSentinel(CashDeposit $deposit): void
    {
        if ($deposit->status->isSentinel()) {
            throw new RuntimeException('Baris ini adalah penanda titik nol pencatatan kas, bukan setoran. Tidak ada aksi yang berlaku untuknya.');
        }
    }

    private function assertPending(CashDeposit $deposit): void
    {
        if ($deposit->status !== CashDepositStatus::MENUNGGU_VERIFIKASI) {
            throw new RuntimeException("Setoran kas {$deposit->deposit_number} sudah diperiksa sebelumnya ({$deposit->status->label()}).");
        }
    }

    /**
     * Admin tidak boleh memeriksa setorannya sendiri.
     *
     * Berlaku untuk SEMUA, termasuk Owner yang kebetulan juga menerima
     * pembayaran di kantor — cross check yang ditandatangani sendiri bukan
     * cross check.
     */
    private function assertVerifierIsNotDepositor(CashDeposit $deposit, User $verifier): void
    {
        if ((int) $deposit->depositor_id === $verifier->id) {
            throw new RuntimeException('Anda tidak boleh memeriksa setoran kas Anda sendiri. Minta Owner atau atasan lain yang memeriksa.');
        }
    }

    /**
     * Pemeriksa wajib bisa melihat SELURUH sumber uang di setoran ini, bukan
     * cuma `cash_deposits.pop_id`.
     *
     * Admin ber-scope pop_tree bisa memegang uang lintas POP; kalau cukup
     * mengecek satu kolom, atasan cabang lain bisa menutup setoran yang isinya
     * sebagian di luar wilayahnya.
     */
    private function assertVerifierCanSeeAllSources(CashDeposit $deposit, User $verifier): void
    {
        $totalSetoran = $deposit->collectorDeposits()->count();
        $terlihatSetoran = $deposit->collectorDeposits()->applyUserScope($verifier)->count();

        $totalPembayaran = $deposit->manualPayments()->count();
        $terlihatPembayaran = $deposit->manualPayments()->applyUserScope($verifier)->count();

        if ($totalSetoran !== $terlihatSetoran || $totalPembayaran !== $terlihatPembayaran) {
            throw new RuntimeException('Setoran kas ini memuat uang dari POP di luar scope Anda. Pemeriksaan harus dilakukan orang yang membawahi seluruh POP-nya.');
        }
    }

    /**
     * `SETKAS-{tahun}-{4 digit}` — pola penomoran repo (TKT/TFOP/TASK/SETOR).
     *
     * `lockForUpdate` pada baris terakhir menyerialkan dua setoran yang lahir
     * bersamaan; tanpa itu keduanya membaca nomor yang sama dan salah satunya
     * menabrak unique index tepat saat uang sedang diserahkan.
     *
     * Sentinel titik nol (`SETKAS-0000-0000`) tak pernah tertangkap `like`
     * ini karena tahunnya bukan tahun berjalan — itulah alasan nomornya
     * sengaja dibuat di luar deret normal.
     */
    private function generateDepositNumber(): string
    {
        $prefix = 'SETKAS-'.now()->year.'-';

        $last = CashDeposit::where('deposit_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('deposit_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function audit(CashDeposit $deposit, User $actor, string $action, array $values): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'module' => 'kas',
            'action' => $action,
            'auditable_type' => CashDeposit::class,
            'auditable_id' => $deposit->id,
            'new_values' => $values,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Bungkus pengiriman kabar supaya kegagalannya tak pernah merambat ke
     * transaksi uang. Satu tempat, dipakai semua jalur.
     */
    private function safelyNotify(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Kabar "ada setoran kas menunggu diperiksa" ke siapa pun yang berwenang
     * memeriksanya.
     *
     * Audiensnya diturunkan dari PERMISSION (`cash_deposit.validate`), bukan
     * dari daftar role yang ditulis di sini. Kalau daftarnya di-hardcode, Role
     * Matrix bisa memberikan kewenangan memeriksa ke role lain dan orang itu
     * tak pernah tahu ada uang yang menunggu diperiksa — kewenangan yang tak
     * terlihat sama saja dengan tak ada.
     */
    private function notifyVerifiers(CashDeposit $deposit, User $admin, int $sumberCount, float $total): void
    {
        $users = app(EffectiveAccessService::class)
            ->usersWithPermission('cash_deposit.validate')
            ->where('id', '!=', $admin->id)
            ->get();

        foreach ($users as $user) {
            $user->notify(new AppNotification(
                title: 'Setoran kas menunggu pemeriksaan: '.$admin->name,
                message: "{$deposit->deposit_number} — {$sumberCount} sumber, tercatat Rp"
                    .number_format($total, 0, ',', '.').'. Periksa lalu tutup setorannya.',
                actionUrl: route('cash-deposits.index'),
                type: NotificationType::WARNING
            ));
        }
    }

    private function notifyDepositorOnVerification(CashDeposit $deposit): void
    {
        $depositor = $deposit->depositor;

        if (! $depositor) {
            return;
        }

        $cocok = Money::isZero($deposit->difference);

        // Judul mengikuti LABEL STATUS, bukan kata "selisih" yang di-hardcode —
        // supaya admin yang menyerahkan uang LEBIH tidak menerima kabar
        // berjudul "kurang".
        $depositor->notify(new AppNotification(
            title: "Setoran kas {$deposit->deposit_number}: ".$deposit->status->label(),
            message: $cocok
                ? 'Uang yang diserahkan cocok dengan catatan sistem. Setoran ditutup.'
                : 'Selisih Rp'.number_format(abs((float) $deposit->difference), 0, ',', '.')
                    .' ('.((float) $deposit->difference < 0 ? 'kurang' : 'lebih').'). Catatan: '.($deposit->note ?? '-'),
            actionUrl: route('cash-deposits.index'),
            type: $cocok ? NotificationType::SUCCESS : NotificationType::WARNING
        ));
    }
}
