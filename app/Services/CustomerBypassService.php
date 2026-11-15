<?php

namespace App\Services;

use App\Enums\WorkflowTransition;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerStatusLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Bypass Pelanggan (2026-11-15) — pelanggan legacy terlewat migrasi: sudah
 * terpasang & administrasinya beres DI LUAR sistem, jadi didaftarkan langsung
 * `active`.
 *
 * Yang SENGAJA tidak terjadi (beda dengan aktivasi normal di
 * CustomerVerificationController::finalVerify):
 * - Tidak ada Task/FopTask Survey/Pemasangan, tidak masuk antrean verifikasi
 *   apa pun — pekerjaan lapangannya sudah selesai di dunia nyata.
 * - Tidak menerbitkan Tagihan Awal — sudah dibayar di sistem lama. Tagihan
 *   bulanan jalan sendiri mulai periode SETELAH bulan `activation_date`
 *   (GenerateMonthlyInvoicesCommand melewati bulan aktivasi), jadi bulan
 *   berjalan tidak tertagih dobel.
 * - `data_completeness_status` TIDAK dipaksa `siap_billing`: data jaringan,
 *   dokumen, perangkat memang belum ada dan tampil sebagai "belum lengkap"
 *   sampai dilengkapi lewat Edit (keputusan user).
 *
 * Jejak: CustomerStatusLog + AuditLog dengan catatan tetap, supaya pelanggan
 * hasil bypass selalu bisa dibedakan dari aktivasi normal saat audit.
 */
class CustomerBypassService
{
    public const STATUS_NOTE = 'Aktif lewat Bypass (data migrasi terlewat)';

    public function __construct(private CustomerRegistrationService $registration) {}

    /**
     * @param  array<string, mixed>  $validated
     * @param  array{foto_kontrak?: ?UploadedFile, fab_document?: ?UploadedFile}  $files
     */
    public function register(array $validated, User $actor, array $files = []): Customer
    {
        return DB::transaction(function () use ($validated, $actor, $files) {
            $customer = $this->registration->create($validated, $actor, WorkflowTransition::ACTIVE->value, $files);

            // CID satu pintu (CustomerCidService). Null = Cabang belum punya
            // cid_prefix → biarkan kosong, jangan tulis CID rusak; observer
            // akan mengisinya begitu prefix ada & pelanggan disimpan ulang.
            $cid = CustomerCidService::resolve($customer);
            if ($cid !== null) {
                $customer->update(['cid' => $cid]);
            }

            $customer->customerService?->update([
                'activated_by_name' => $actor->name,
                'activated_by_user_id' => $actor->id,
                'activation_time' => now()->format('H:i:s'),
            ]);

            // Dicatat dari `registered` (titik lahir semua pelanggan di
            // workflow) supaya timeline status tetap terbaca "daftar → aktif".
            CustomerStatusLog::create([
                'customer_id' => $customer->id,
                'from_status' => WorkflowTransition::REGISTERED->value,
                'to_status' => WorkflowTransition::ACTIVE->value,
                'changed_by' => $actor->id,
                'note' => self::STATUS_NOTE,
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'module' => 'Data Pelanggan',
                'action' => 'activate_from_bypass',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'old_values' => null,
                'new_values' => [
                    'cid' => $cid,
                    'status' => WorkflowTransition::ACTIVE->value,
                    'activation_date' => $customer->customerService?->activation_date?->format('Y-m-d'),
                    'note' => self::STATUS_NOTE,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);

            return $customer->refresh();
        });
    }
}
