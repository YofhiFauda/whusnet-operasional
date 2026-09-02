<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\NotificationType;
use App\Enums\ScopeType;
use App\Events\CollectorDepositUpdated;
use App\Models\AuditLog;
use App\Models\CollectorDeposit;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siklus hidup Setoran Kolektor: setor → cross check → terverifikasi/selisih →
 * selisih lunas / dihapus buku.
 *
 * Semua transisi ada di sini, bukan tersebar di controller, karena tiap
 * transisi menyentuh uang dan punya invariant yang tak boleh bisa dilewati
 * lewat jalur masuk lain.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §11.4, §11.5, §11.6, §14.2.
 */
class CollectorDepositService
{
    public function __construct(private readonly CollectorBalanceService $balance) {}

    /**
     * Kolektor menyetorkan SELURUH saldonya. Bukan sebagian: "tidak boleh ada
     * saldo mengendap" — setelah setor, Saldo Belum Disetor kembali 0. Ini
     * juga membuang seluruh kebutuhan logika alokasi setoran parsial.
     *
     * Yang disimpan adalah RELASI ke payment, bukan totalnya. Kolektor boleh
     * terus menagih selagi setoran menunggu verifikasi; penagihan sesudah
     * submit masuk saldo baru, tidak menggeser angka yang sedang dihitung
     * admin.
     */
    public function submit(User $collector, ?string $idempotencyKey = null): CollectorDeposit
    {
        if ($idempotencyKey) {
            $existing = CollectorDeposit::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        [$deposit, $paymentCount, $total] = DB::transaction(function () use ($collector, $idempotencyKey) {
            $payments = $this->balance->unsettledPaymentsQuery($collector)
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                throw new RuntimeException('Tidak ada pembayaran yang belum disetorkan. Saldo Anda sedang kosong.');
            }

            $deposit = CollectorDeposit::create([
                'deposit_number' => $this->generateDepositNumber(),
                'collector_id' => $collector->id,
                // POP representatif buat listing. Otorisasi verifikasi tidak
                // bersandar ke kolom ini — lihat assertVerifierCanSeeAllPayments().
                'pop_id' => $payments->first()->pop_id,
                'status' => DepositStatus::MENUNGGU_VERIFIKASI->value,
                'submitted_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            Payment::whereIn('id', $payments->pluck('id'))->update([
                'collector_deposit_id' => $deposit->id,
            ]);

            $this->audit($deposit, $collector, 'disetorkan', [
                'jumlah_pembayaran' => $payments->count(),
                'total_tercatat' => round((float) $payments->sum('amount'), 2),
            ]);

            return [$deposit, $payments->count(), round((float) $payments->sum('amount'), 2)];
        });

        // Notifikasi SESUDAH commit, dan kegagalannya tak boleh menggagalkan
        // setoran — pelajaran yang sama dengan jalur pembayaran (review #2).
        //
        // Waktu masih di dalam transaksi, satu exception dispatch (broadcast
        // mati, queue penuh) membatalkan seluruh setoran: kolektor tak bisa
        // menyerahkan uangnya cuma karena layanan kabar sedang rusak. Dan
        // kalau dispatch berhasil lalu transaksinya rollback karena hal lain,
        // admin menerima kabar setoran yang tak pernah ada.
        $this->safelyNotify(fn () => $this->notifyVerifiers($deposit, $collector, $paymentCount, $total));
        $this->safelyNotify(fn () => CollectorDepositUpdated::dispatch($deposit, $collector, 'diajukan'));

        return $deposit;
    }

    /**
     * Admin menghitung uang fisik lalu memutuskan.
     *
     * `difference = declared − (computed + settlement)`:
     *   = 0  → TERVERIFIKASI
     *   < 0  → SELISIH (kurang setor) — kewajiban kolektor, wajib punya jalan
     *          pulang: dilunasi setoran berikutnya atau dihapus buku Owner.
     *   > 0  → LEBIH_SETOR — uang dikembalikan fisik saat itu juga, jadi
     *          terminal. Bukan piutang balik kolektor.
     *
     * Untuk `difference` ≠ 0 apa pun arahnya, `note` WAJIB — selisih tak boleh
     * lewat begitu saja sebagai angka tanpa penjelasan.
     *
     * Uang pelunasan selisih setoran lama masuk lewat `$settles` +
     * `$settlementAmount`, BUKAN dilebur ke `declared`. Kalau dilebur,
     * hasilnya `difference` positif alias "lebih setor": selisih baru yang
     * menggantung, dan laporan selisih tak pernah nol.
     */
    public function verify(
        CollectorDeposit $deposit,
        User $verifier,
        float $declaredAmount,
        ?CollectorDeposit $settles = null,
        float $settlementAmount = 0.0,
        ?string $note = null,
    ): CollectorDeposit {
        $this->assertPending($deposit);
        $this->assertVerifierIsNotDepositor($deposit, $verifier);
        $this->assertVerifierCanSeeAllPayments($deposit, $verifier);

        // Pemeriksaan di luar transaksi ini CUMA optimasi UX — supaya pesan
        // gagal muncul cepat tanpa membuka transaksi. Yang otoritatif adalah
        // pemeriksaan ulang di bawah `lockForUpdate` (pola sama dengan
        // CollectorPaymentService).
        if ($settles) {
            $this->assertSettlementIsValid($deposit, $settles, $settlementAmount);
        } elseif ($settlementAmount > 0) {
            throw new RuntimeException('Nominal pelunasan diisi tapi setoran yang dilunasi belum dipilih.');
        }

        [$verified, $difference] = DB::transaction(function () use ($deposit, $verifier, $declaredAmount, $settles, $settlementAmount, $note) {
            $locked = CollectorDeposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            // Re-validasi pelunasan TERHADAP BARIS YANG SUDAH DIKUNCI.
            //
            // Tanpa ini: dua verifikasi yang melunasi selisih sama bisa
            // over-credit, dan — lebih buruk — kalau setoran targetnya
            // dihapus-buku di sela pemeriksaan tadi dan transaksi ini, uang
            // pelunasan masuk ke baris DIHAPUS_BUKU yang
            // `outstandingShortfall()`-nya selalu 0. Uangnya lenyap dari semua
            // laporan tanpa satu pun error.
            $lockedSettles = null;
            if ($settles && $settlementAmount > 0) {
                $lockedSettles = CollectorDeposit::whereKey($settles->id)->lockForUpdate()->firstOrFail();
                $this->assertSettlementIsValid($locked, $lockedSettles, $settlementAmount);

                // Setoran TARGET juga wajib terlihat penuh oleh verifikator.
                // Tanpa ini, sesudah kolektor dipindah cabang, admin cabang B
                // bisa menuliskan pelunasan ke setoran lama yang seluruh
                // pembayarannya ada di cabang A — menulis ke catatan yang tak
                // boleh dia baca.
                $this->assertVerifierCanSeeAllPayments($lockedSettles, $verifier);
            }

            $computed = $locked->computedAmount();
            // Dihitung di ranah sen (Money), bukan float: galat sekecil
            // 0,0000008 di sini menentukan setoran ditandai TERVERIFIKASI atau
            // SELISIH — dan setoran yang salah ditandai selisih berarti
            // kolektor ditagih uang yang sudah dia serahkan.
            $difference = Money::sub($declaredAmount, Money::add($computed, $settlementAmount));

            // Dulu: `abs($difference) > 0.001`. Epsilon karangan itu ditulis
            // ulang di beberapa tempat dengan angka yang bebas dipilih
            // masing-masing; sekarang perbandingannya eksak.
            if (! Money::isZero($difference) && blank($note)) {
                throw new RuntimeException('Selisih wajib disertai catatan alasan sebelum setoran ditutup.');
            }

            $locked->update([
                'declared_amount' => round($declaredAmount, 2),
                'settlement_amount' => round($settlementAmount, 2),
                'settles_deposit_id' => $settles?->id,
                'difference' => $difference,
                'note' => $note,
                // Tiga hasil, bukan dua. Kurang setor (`SELISIH`) adalah
                // kewajiban yang harus ditagih pulang; lebih setor
                // (`LEBIH_SETOR`) adalah uang yang dikembalikan fisik saat itu
                // juga, jadi terminal. Menyatukan keduanya bikin lebih setor
                // nyangkut sebagai "Kurang setor Rp0" tanpa jalan keluar.
                'status' => match (true) {
                    Money::isZero($difference) => DepositStatus::TERVERIFIKASI->value,
                    Money::lessThan($difference, 0) => DepositStatus::SELISIH->value,
                    default => DepositStatus::LEBIH_SETOR->value,
                },
                'verified_by' => $verifier->id,
                'verified_at' => now(),
            ]);

            if ($lockedSettles) {
                $this->applySettlement($lockedSettles, $settlementAmount, $verifier, $locked);
            }

            $this->audit($locked, $verifier, 'diverifikasi', [
                'tercatat_sistem' => $computed,
                'pelunasan_selisih' => round($settlementAmount, 2),
                'uang_fisik' => round($declaredAmount, 2),
                'selisih' => $difference,
                'status' => $locked->status->value,
            ]);

            return [$locked, $difference];
        });

        // Sama seperti submit(): kabar hasil verifikasi dikirim SESUDAH commit
        // dan tak boleh membatalkannya. Verifikasi adalah serah-terima uang
        // yang sudah disepakati dua orang di meja — kegagalan mengirim
        // notifikasi tidak membatalkan kesepakatan itu.
        $this->safelyNotify(fn () => $this->notifyCollectorOnVerification($verified, $difference));
        $this->safelyNotify(fn () => CollectorDepositUpdated::dispatch($verified, $verified->collector, 'diverifikasi'));

        // Setoran LAMA yang ikut terlunasi punya audiens yang sama tapi kabar
        // yang berbeda — tanpa ini, kolektor melihat kewajiban lamanya
        // berkurang tanpa penjelasan apa pun di layar.
        if ($settles) {
            $this->safelyNotify(fn () => CollectorDepositUpdated::dispatch(
                $settles->refresh(),
                $settles->collector,
                'dilunasi'
            ));
        }

        return $verified;
    }

    /**
     * Hapus buku — titik di mana kerugian diakui. Owner saja (digerbang
     * permission di route), wajib beralasan, dan tetap tak boleh dilakukan
     * oleh kolektor yang bersangkutan.
     *
     * Guard POP-nya SAMA dengan verify(). Hari ini hanya Owner yang memegang
     * `collector_worksheet.approve` (lewat wildcard `*`), jadi terasa
     * mubazir — tapi permission itu digenerate dan bisa diberikan ke role
     * ber-scope kapan saja lewat Role Matrix. Begitu diberikan, tanpa guard ini
     * role tersebut bisa menghapus-buku kerugian atas setoran yang isinya
     * pembayaran dari cabang yang tak boleh dia lihat. Menutup kerugian adalah
     * kewenangan yang lebih besar dari memverifikasi, bukan lebih kecil.
     */
    public function writeOff(CollectorDeposit $deposit, User $approver, string $reason): CollectorDeposit
    {
        if ($deposit->status !== DepositStatus::SELISIH) {
            throw new RuntimeException('Hapus buku hanya berlaku untuk setoran berstatus Kurang Setor.');
        }

        $this->assertVerifierIsNotDepositor($deposit, $approver);
        $this->assertVerifierCanSeeAllPayments($deposit, $approver);

        [$written, $outstanding] = DB::transaction(function () use ($deposit, $approver, $reason) {
            $locked = CollectorDeposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DepositStatus::SELISIH) {
                throw new RuntimeException('Hapus buku hanya berlaku untuk setoran berstatus Kurang Setor.');
            }

            $outstanding = $locked->outstandingShortfall();

            $locked->update([
                'status' => DepositStatus::DIHAPUS_BUKU->value,
                'written_off_by' => $approver->id,
                'written_off_at' => now(),
                'write_off_reason' => $reason,
            ]);

            $this->audit($locked, $approver, 'dihapus_buku', [
                'nilai_dihapus' => $outstanding,
                'alasan' => $reason,
            ]);

            return [$locked, $outstanding];
        });

        // Kolektor WAJIB diberi tahu. Hapus buku menutup kewajibannya — itu
        // kabar yang menyangkut dirinya secara langsung, dan sebelum ini
        // satu-satunya cara dia tahu adalah kebetulan membuka Worklist dan
        // melihat angkanya berubah sendiri.
        $this->safelyNotify(fn () => $written->collector?->notify(new AppNotification(
            title: "Setoran {$written->deposit_number}: ".$written->status->label(),
            message: 'Kekurangan Rp'.number_format($outstanding, 0, ',', '.').' dihapus buku oleh kantor. Alasan: '.$reason,
            actionUrl: route('collector-worklist.index'),
            type: NotificationType::WARNING
        )));

        $this->safelyNotify(fn () => CollectorDepositUpdated::dispatch($written, $written->collector, 'dihapus_buku'));

        return $written;
    }

    /**
     * Pelunasan selisih: setoran lama menerima uang, sisa kewajibannya
     * berkurang. Habis → SELISIH_LUNAS. Belum habis → tetap SELISIH dengan
     * sisa lebih kecil, jadi pelunasan bertahap tetap terlacak.
     */
    private function applySettlement(CollectorDeposit $target, float $amount, User $verifier, CollectorDeposit $source): void
    {
        // `$target` sudah dikunci & divalidasi ulang oleh verify() di dalam
        // transaksi yang sama — jangan kunci ulang di sini, karena mengambil
        // baris kedua kalinya berarti bekerja di atas salinan yang bisa
        // berbeda dari yang sudah diperiksa.
        $locked = $target;

        $locked->settled_amount = Money::add($locked->settled_amount, $amount);
        $locked->save();
        $locked->refresh();

        if (Money::isZero($locked->outstandingShortfall())) {
            $locked->update(['status' => DepositStatus::SELISIH_LUNAS->value]);
        }

        $this->audit($locked, $verifier, 'selisih_dilunasi', [
            'nominal' => round($amount, 2),
            'lewat_setoran' => $source->deposit_number,
            'sisa_kewajiban' => $locked->fresh()->outstandingShortfall(),
        ]);
    }

    private function assertPending(CollectorDeposit $deposit): void
    {
        if ($deposit->status !== DepositStatus::MENUNGGU_VERIFIKASI) {
            throw new RuntimeException("Setoran {$deposit->deposit_number} sudah diverifikasi sebelumnya ({$deposit->status->label()}).");
        }
    }

    /**
     * §B-8 no. 4 mengizinkan pop_admin merangkap role kolektor. Tanpa guard
     * ini, orang yang sama bisa menagih, mencatat, menyetor, DAN memverifikasi
     * setorannya sendiri — cross check jadi tanda tangan di atas kertas
     * sendiri. Berlaku untuk semua, termasuk Owner.
     */
    private function assertVerifierIsNotDepositor(CollectorDeposit $deposit, User $verifier): void
    {
        if ((int) $deposit->collector_id === $verifier->id) {
            throw new RuntimeException('Anda tidak boleh memverifikasi setoran Anda sendiri. Minta admin lain yang memeriksa.');
        }
    }

    /**
     * Otorisasi POP: admin wajib bisa melihat SELURUH payment di setoran,
     * bukan cuma `deposits.pop_id`. Kolektor ber-scope pop_tree bisa punya
     * setoran lintas POP; kalau cukup mengecek satu kolom, admin cabang lain
     * bisa menutup setoran yang isinya sebagian di luar wilayahnya.
     */
    private function assertVerifierCanSeeAllPayments(CollectorDeposit $deposit, User $verifier): void
    {
        $total = $deposit->payments()->count();
        $visible = $deposit->payments()->applyUserScope($verifier)->count();

        if ($total !== $visible) {
            throw new RuntimeException('Setoran ini memuat pembayaran di luar scope POP Anda. Verifikasi harus dilakukan admin yang membawahi seluruh POP-nya.');
        }
    }

    private function assertSettlementIsValid(CollectorDeposit $deposit, CollectorDeposit $settles, float $amount): void
    {
        if ((int) $settles->collector_id !== (int) $deposit->collector_id) {
            throw new RuntimeException('Setoran yang dilunasi harus milik kolektor yang sama.');
        }

        if ($settles->id === $deposit->id) {
            throw new RuntimeException('Setoran tidak bisa melunasi dirinya sendiri.');
        }

        if ($settles->status !== DepositStatus::SELISIH) {
            throw new RuntimeException("Setoran {$settles->deposit_number} tidak sedang berstatus Kurang Setor.");
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pelunasan selisih harus lebih dari nol.');
        }

        if (Money::greaterThan($amount, $settles->outstandingShortfall())) {
            throw new RuntimeException("Nominal pelunasan melebihi sisa kewajiban setoran {$settles->deposit_number} (Rp".number_format($settles->outstandingShortfall(), 0, ',', '.').').');
        }
    }

    /**
     * `SETOR-{tahun}-{4 digit}` — pola penomoran repo (TKT/TFOP/TASK).
     * Unique index di `deposit_number` yang jadi jaring pengaman kalau dua
     * setoran lahir bersamaan.
     */
    private function generateDepositNumber(): string
    {
        $year = now()->year;
        $prefix = "SETOR-{$year}-";

        // `lockForUpdate` pada baris terakhir menyerialkan dua setoran yang
        // lahir bersamaan. Tanpa itu keduanya membaca nomor yang sama, salah
        // satu menabrak unique index, dan kolektor menerima error 500 tepat
        // saat dia sedang menyerahkan uang.
        //
        // Dipanggil dari dalam transaksi submit(), jadi kuncinya bertahan
        // sampai baris barunya benar-benar tersimpan.
        $last = CollectorDeposit::where('deposit_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('deposit_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function audit(CollectorDeposit $deposit, User $actor, string $action, array $values): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'module' => 'kolektor',
            'action' => $action,
            'auditable_type' => CollectorDeposit::class,
            'auditable_id' => $deposit->id,
            'new_values' => $values,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Notif "ada setoran menunggu diverifikasi" ke admin/pop_admin di POP
     * setoran. Beda dari notif batch pembayaran yang murni informatif — yang
     * ini memang menuntut tindakan: uang fisik sudah berpindah tangan dan
     * belum ada yang menghitungnya.
     */
    private function notifyVerifiers(CollectorDeposit $deposit, User $collector, int $paymentCount, float $total): void
    {
        if (! $deposit->pop_id) {
            return;
        }

        $users = User::whereHas('role', fn ($q) => $q->whereIn('code', ['admin', 'pop_admin']))
            ->where('id', '!=', $collector->id)
            ->where(function ($query) use ($deposit) {
                $query->whereHas('roleScopes', fn ($q) => $q->where('scope_type', ScopeType::ALL_POP->value))
                    ->orWhereHas('roleScopes', fn ($q) => $q->whereIn('scope_type', [ScopeType::SELECTED_POP->value, ScopeType::POP_TREE->value])
                        ->whereHas('targets', fn ($t) => $t->where('pop_id', $deposit->pop_id))
                    );
            })
            ->get();

        foreach ($users as $user) {
            $user->notify(new AppNotification(
                title: 'Setoran menunggu verifikasi: '.$collector->name,
                message: "{$deposit->deposit_number} — {$paymentCount} pembayaran, tercatat Rp".number_format($total, 0, ',', '.').'. Hitung uang fisiknya lalu verifikasi.',
                actionUrl: route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'setoran']),
                type: NotificationType::WARNING
            ));
        }
    }

    /**
     * Bungkus pengiriman notifikasi supaya kegagalannya tak pernah merambat ke
     * transaksi uang. Satu tempat, dipakai semua jalur — kalau tiap pemanggil
     * menulis try/catch-nya sendiri, cepat atau lambat ada satu yang lupa
     * (persis yang terjadi di jalur pembayaran, review #2).
     */
    private function safelyNotify(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyCollectorOnVerification(CollectorDeposit $deposit, float $difference): void
    {
        $collector = $deposit->collector;

        if (! $collector) {
            return;
        }

        $isClean = Money::isZero($difference);

        // Judul diambil dari LABEL STATUS, bukan kata "SELISIH" yang
        // di-hardcode. Sejak lebih setor punya status sendiri, judul tetap
        // harus ikut statusnya — kalau tidak, kolektor yang menyerahkan uang
        // LEBIH menerima kabar berjudul "SELISIH" dan mengira dirinya kurang.
        $collector->notify(new AppNotification(
            title: "Setoran {$deposit->deposit_number}: ".$deposit->status->label(),
            message: $isClean
                ? 'Uang fisik cocok dengan catatan sistem. Setoran ditutup.'
                : 'Selisih Rp'.number_format(abs($difference), 0, ',', '.').' ('.($difference < 0 ? 'kurang setor' : 'lebih setor').'). Catatan admin: '.($deposit->note ?? '-'),
            actionUrl: route('collector-worklist.index'),
            type: $isClean ? NotificationType::SUCCESS : NotificationType::WARNING
        ));
    }
}
