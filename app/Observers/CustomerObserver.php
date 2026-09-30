<?php

namespace App\Observers;

use App\Enums\WorkflowTransition;
use App\Events\CustomerVerificationStatusChanged;
use App\Exceptions\CustomerRelocationBlockedException;
use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerPortalToken;
use App\Services\CustomerCidService;
use App\Services\CustomerPortal\PortalAuthService;
use App\Services\CustomerQrTokenService;
use App\Services\CustomerRelocationService;
use App\Services\NetworkAssignmentService;

class CustomerObserver
{
    /**
     * Invariant pindah Cabang & jaringan pelanggan — di observer, bukan
     * controller, supaya berlaku dari SEMUA jalur yang bisa mengganti pop_id /
     * mini_pop_id / distribution_id (Edit, modal staf, API, import, tinker).
     * Rancangan lengkap: docs/plan/rancangan-pindah-pop-lanjutan.md (ADHOC-107).
     *
     * Urutan di bawah penting:
     * 1. Guard piutang (R4) dulu — kalau ditolak, tidak ada yang berubah.
     * 2. Hierarki Cabang → Mini POP → Distribusi (R6) — yang tidak cocok
     *    DILEPAS, bukan ditebak: pilihan OLT itu keputusan teknis admin.
     *    Sebelum ini, pindah POP cuma mengganti pop_id sementara mini_pop_id
     *    tetap menunjuk OLT cabang lama → CID campuran (kasus D1X6… JETIS →
     *    SANDYA).
     * 3. Kolektor dilepas (R8).
     * 4. CID dihitung ulang (R3) — SETELAH hierarki dibereskan, supaya CID
     *    dibentuk dari Mini POP/Distribusi yang sudah sah.
     *
     * Semua perubahan terjadi pada save yang sama, jadi RecordsAuditLogs
     * mencatat satu baris audit berisi POP, Mini POP, Distribusi, kolektor,
     * dan CID lama/baru sekaligus.
     */
    public function updating(Customer $customer): void
    {
        $networkChanging = $customer->isDirty(['pop_id', 'mini_pop_id', 'distribution_id']);
        $popChanging = $customer->isDirty('pop_id');

        // 1. Pindah Cabang wajib lunas piutang dulu (keputusan user
        // 2026-09-28, K6/K8). Lapis kedua di belakang validasi Edit — tanpa
        // ini import/tinker/command bisa memindah pelanggan yang masih punya
        // tunggakan, dan tunggakan itu yatim: laporannya di cabang lama,
        // kolektornya sudah dilepas. Pelanggan yang belum punya POP (NULL →
        // terisi pertama kali) bukan "pindah", jadi tidak dicegat.
        if ($popChanging && $customer->getOriginal('pop_id') !== null) {
            $summary = CustomerRelocationService::blockingSummary($customer);
            if ($summary['count'] > 0) {
                throw new CustomerRelocationBlockedException(CustomerRelocationService::blockingMessage($summary));
            }
        }

        // 2. Hierarki jaringan. Distribusi tanpa Mini POP yang cocok ikut
        // dilepas — Distribusi tanpa Mini POP tidak punya sumber segmen OLT
        // untuk CID. Hanya dicek kalau salah satu kolomnya berubah: nilai
        // legacy yang tidak disentuh (di luar hierarki, hasil migrasi lama)
        // dibiarkan apa adanya (keputusan user no. 8).
        if ($networkChanging) {
            if ($customer->mini_pop_id && ! NetworkAssignmentService::miniPopBelongsToPop($customer->mini_pop_id, $customer->pop_id)) {
                $customer->mini_pop_id = null;
            }

            if ($customer->distribution_id && ! NetworkAssignmentService::distributionBelongsToMiniPop($customer->distribution_id, $customer->mini_pop_id)) {
                $customer->distribution_id = null;
            }
        }

        // 3. Kolektor SELALU dilepas saat pindah Cabang (keputusan user
        // 2026-09-28, R8) — termasuk kolektor yang punya akses ke kedua
        // cabang. Pelanggan tidak terikat kolektor siapa pun sampai admin
        // cabang baru meng-assign lewat Worksheet Kolektor. Aman terhadap
        // uang: piutang lama sudah lunas (langkah 1) dan tagihan bulan
        // berjalan ikut pindah ke cabang baru (updated()).
        if ($popChanging) {
            $customer->collector_id = null;
        }

        // Relasi yang sudah ter-load masih berisi objek cabang/OLT lama.
        $customer->unsetRelation('pop');
        $customer->unsetRelation('miniPop');
        $customer->unsetRelation('distribution');

        // 4. CID — satu rumus (CustomerCidService, K3). Dibuat ulang kalau
        // bahannya (POP/Mini POP/Distribusi) berubah, atau pelanggan yang
        // seharusnya punya CID belum punya. Tidak dihitung ulang di setiap
        // simpan: CID tercetak di kwitansi/QR/PPPoE, jadi CID legacy yang
        // jaringannya tidak disentuh tetap stabil.
        if ($networkChanging || blank($customer->cid)) {
            CustomerCidService::sync($customer);
        }
    }

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

            // Pelanggan terminated → akun portal dinonaktifkan & semua token
            // dicabut (docs/api/api-portal-pelanggan/business-logic.md
            // §Token). `WorkflowTransition::TERMINATED->value`, bukan
            // literal string — kolom `status` sendiri tidak native-cast ke
            // enum ini di Eloquent, tapi perbandingannya tetap wajib lewat
            // enum (CLAUDE.md: jangan pakai string literal).
            if ($customer->status === WorkflowTransition::TERMINATED->value && $customer->portalAccount) {
                $customer->portalAccount->update(['status' => 'disabled']);
                CustomerPortalToken::revokeAllForCustomer($customer->id);
            }

            // Pelanggan terminated → token QR ikut dicabut. Tidak ada
            // gunanya lagi menerima scan (tagihan/tiket/absen) buat
            // pelanggan yang sudah putus (docs/plan/qr-code/
            // rancangan-qr-pelanggan-final.md §7.3 Kasus 6).
            if ($customer->status === WorkflowTransition::TERMINATED->value) {
                $this->revokeActiveQrToken($customer, 'Pelanggan terminated');
            }

            // Kebalikan dua blok di atas — "Langganan Lagi" (TERMINATED →
            // ACTIVE atau TERMINATED → WAITING_SURVEY kalau alat sudah
            // diambil, lihat CustomerController::reactivate()). Ditaruh di
            // observer, bukan di controller, dengan alasan yang sama seperti
            // penonaktifannya: invariant "akun portal & QR mengikuti status
            // pelanggan" harus jalan dari semua jalur masuk (transition(),
            // tinker, import), bukan cuma dari tombol di List Putus
            // Langganan. Portal dipulihkan begitu pelanggan MULAI berlangganan
            // lagi, bukan menunggu instalasi ulang selesai — pelanggan yang
            // masih di tengah survey/pemasangan tetap butuh akses portal buat
            // pantau progres. getOriginal() di hook updated masih berisi
            // nilai SEBELUM save.
            if (in_array($customer->status, [WorkflowTransition::ACTIVE->value, WorkflowTransition::WAITING_SURVEY->value], true)
                && $customer->getOriginal('status') === WorkflowTransition::TERMINATED->value) {
                app(PortalAuthService::class)->restoreAfterReactivation($customer, auth()->user());
            }

            // Modul Customer Acquisition (List Pelanggan <30 hari, dipakai
            // tim Busdev) — dicatat SEKALI seumur hidup pelanggan, persis
            // saat pertama kali menyentuh ACTIVE. firstOrCreate (bukan
            // updateOrCreate) sengaja: kalau pelanggan nanti suspended lalu
            // diaktifkan lagi, baris lama TIDAK dibuat ulang/ditimpa — modul
            // ini nyatet "akuisisi baru", bukan tiap kali status balik ke
            // active (lihat migration customer_acquisitions).
            if ($customer->status === WorkflowTransition::ACTIVE->value) {
                CustomerAcquisition::firstOrCreate(
                    ['customer_id' => $customer->id],
                    ['periode' => now()->format('Y-m'), 'verified_at' => now()]
                );
            }
        }

        // Re-homing POP — QR lama ditandatangani buat pop_id LAMA, jadi
        // otomatis gagal pop_mismatch begitu pop_id berubah. Token dicabut
        // eksplisit di sini supaya kegagalannya tercatat sebagai "token
        // perlu diterbitkan ulang", bukan menunggu scan berikutnya nemuin
        // sendiri (§2.1, §7.3 Kasus 5). Re-homing dikonfirmasi sangat
        // jarang terjadi — biaya cetak ulang stiker diterima.
        //
        // TODO (Fase lanjutan): notifikasi ke admin POP belum dikirim di
        // sini — §2.1 mensyaratkannya, tapi kanal notifikasi (in-app/
        // Telegram) belum diputuskan untuk modul ini. Token tetap tercabut
        // & tercatat di riwayat (revoke_reason) walau notifikasinya menyusul.
        if ($customer->wasChanged('pop_id')) {
            $this->revokeActiveQrToken($customer, 'Pelanggan pindah POP — token lama tidak lagi cocok dengan pop_id baru');

            // Tagihan bulan berjalan yang BELUM dibayar sama sekali ikut
            // pindah ke cabang baru, supaya pembayarannya tercatat di cabang
            // baru (keputusan user 2026-09-28, K6). Tagihan periode lalu
            // tidak pernah dipindah — guard di updating() menjamin semuanya
            // sudah lunas — jadi laporan pembayaran & piutang cabang lama
            // tidak berubah. Tagihan bulan berikutnya terbit di cabang baru
            // dengan sendirinya (GenerateMonthlyInvoicesCommand memakai
            // pop_id pelanggan). Aturan lengkap: CustomerRelocationService.
            CustomerRelocationService::moveCurrentInvoices($customer, (int) $customer->pop_id);
        }
    }

    private function revokeActiveQrToken(Customer $customer, string $reason): void
    {
        $activeToken = $customer->qrTokens()->whereNull('revoked_at')->first();

        if ($activeToken) {
            app(CustomerQrTokenService::class)->revoke($activeToken, $reason);
        }
    }
}
