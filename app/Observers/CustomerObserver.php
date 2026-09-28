<?php

namespace App\Observers;

use App\Enums\WorkflowTransition;
use App\Events\CustomerVerificationStatusChanged;
use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerPortalToken;
use App\Models\Distribution;
use App\Models\Pop;
use App\Models\User;
use App\Services\CustomerPortal\PortalAuthService;
use App\Services\CustomerQrTokenService;
use App\Services\EffectiveAccessService;

class CustomerObserver
{
    /**
     * Hierarki jaringan pelanggan `Cabang → Mini POP → Distribusi` wajib
     * konsisten: mini_pop.parent_id = pop_id, distribution.pop_id = mini_pop_id.
     * Aturannya sama persis dengan modal "Atur Mini POP & Distribusi"
     * (CustomerNetworkAssignmentController) dan validasi Edit Pelanggan.
     *
     * Ketiganya disimpan sebagai kolom terpisah di customers. Sebelum guard
     * ini, pindah POP lewat Edit cuma mengganti `pop_id`; `mini_pop_id` tetap
     * menunjuk OLT cabang lama, dan Pop::resolveMiniPopSegment() mengambil
     * segmen CID dari situ duluan — hasilnya CID campuran: prefix cabang baru
     * + segmen OLT cabang lama (kasus D1X6… hasil pindah JETIS → SANDYA).
     *
     * Ditaruh di observer, bukan controller, supaya berlaku dari semua jalur
     * yang bisa mengganti pop_id (Edit, import, tinker). Yang tidak lagi
     * cocok DILEPAS, bukan ditebak penggantinya: pilihan OLT itu keputusan
     * teknis admin (dropdown Mini POP di Edit, atau modal assignment).
     *
     * Distribusi tanpa Mini POP yang cocok ikut dilepas — kalau dibiarkan,
     * segmen OLT CID jatuh ke fallback customerTechnicalDetail->olt_number
     * yang masih berisi nomor OLT cabang lama, dan CID campuran terbentuk lagi.
     *
     * CID sendiri TIDAK disentuh di sini: CID = POP + Mini POP + Distribusi,
     * boleh berubah dan dibuat ulang oleh penulisnya (CustomerController
     * ::update() / modal assignment). Yang permanen REQ ID (customer_code).
     */
    public function updating(Customer $customer): void
    {
        if (! $customer->isDirty(['pop_id', 'mini_pop_id', 'distribution_id'])) {
            return;
        }

        if ($customer->mini_pop_id) {
            $miniPopMatchesPop = Pop::whereKey($customer->mini_pop_id)
                ->where('type', 'mini_pop')
                ->where('parent_id', $customer->pop_id)
                ->exists();

            if (! $miniPopMatchesPop) {
                $customer->mini_pop_id = null;
            }
        }

        if ($customer->distribution_id) {
            $distributionMatchesMiniPop = $customer->mini_pop_id
                && Distribution::whereKey($customer->distribution_id)
                    ->where('pop_id', $customer->mini_pop_id)
                    ->exists();

            if (! $distributionMatchesMiniPop) {
                $customer->distribution_id = null;
            }
        }

        if ($customer->isDirty('pop_id')) {
            $this->releaseCollectorOutsideNewPop($customer);
        }

        // Relasi yang sudah ter-load masih berisi objek cabang/OLT lama —
        // kalau tidak dibuang, generate CID setelah save (CustomerController
        // ::update() langkah 1b) tetap membaca segmen lama dari cache relasi.
        $customer->unsetRelation('pop');
        $customer->unsetRelation('miniPop');
        $customer->unsetRelation('distribution');
    }

    /**
     * Kolektor yang tidak punya akses ke POP baru dilepas. Guard yang sama
     * dengan CollectorWorksheetController::assign() ("POP pelanggan wajib
     * masuk scope kolektor") — kalau dibiarkan, pelanggan tetap tercatat
     * milik kolektor lama padahal worklist-nya menyaring per POP scope, jadi
     * pelanggan pindahan tidak ditagih siapa pun. Admin SANDYA meng-assign
     * kolektor baru lewat Worksheet Kolektor.
     */
    private function releaseCollectorOutsideNewPop(Customer $customer): void
    {
        if (! $customer->collector_id) {
            return;
        }

        $collector = User::find($customer->collector_id);
        if (! $collector) {
            $customer->collector_id = null;

            return;
        }

        $access = app(EffectiveAccessService::class);
        if ($access->hasAllPopAccess($collector)) {
            return;
        }

        if (! in_array((int) $customer->pop_id, $access->getAllowedPopIds($collector), true)) {
            $customer->collector_id = null;
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

            // Tagihan TIDAK ikut dipindah (keputusan user 2026-09-28):
            // laporan pembayaran & piutang tetap milik cabang lama, tagihan
            // bulanan berikutnya terbit di cabang baru karena generator
            // memakai pop_id pelanggan. Pindah lewat Edit wajib lunas dulu
            // (CustomerController::update()), jadi normalnya memang tidak ada
            // tagihan berjalan yang tertinggal.
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
