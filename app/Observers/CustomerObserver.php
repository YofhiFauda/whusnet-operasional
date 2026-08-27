<?php

namespace App\Observers;

use App\Enums\WorkflowTransition;
use App\Events\CustomerVerificationStatusChanged;
use App\Models\Customer;
use App\Models\CustomerPortalToken;
use App\Services\CustomerQrTokenService;

class CustomerObserver
{
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
