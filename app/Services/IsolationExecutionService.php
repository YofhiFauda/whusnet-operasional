<?php

namespace App\Services;

use App\Enums\IsolationCaseStatus;
use App\Exceptions\IsolationExecutionException;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerStatusLog;
use App\Models\IsolationCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Eksekusi isolir dari Web A (docs/superpowers/specs/2026-11-20-webhook-
 * isolir-web-a-design.md) — BERDIRI SENDIRI dari `CustomerController::
 * toggleSuspend()` (tidak saling panggil): jalur Web A tidak punya session
 * user, tidak punya arah aktivasi-kembali, dan atribusi aktornya beda
 * (`external_web_a`, bukan user login). Meniru struktur dua-fase
 * `toggleSuspend()` (lock+gate di dalam transaksi, tracking kasus di luar
 * transaksi) supaya race condition yang sudah di-fix di jalur NOC tidak
 * terulang di sini.
 *
 * Directional ("pastikan suspended"), BUKAN toggle — double-call (retry
 * jaringan Web A, atau NOC sudah isolir manual duluan) aman: kalau pelanggan
 * sudah `suspended`, balas idempotent no-op, tidak pernah membalik ke aktif.
 *
 * Menerima `IsolationCase`, bukan `Customer` — gerbangnya harus menolak kasus
 * BASI (webhook lama diputar ulang Web A setelah kasus dibatalkan/selesai,
 * atau setelah kasus baru menggantikannya sebagai `active_slot`), bukan cuma
 * "apakah pelanggan punya kasus siap apa pun". Gerbang lock+cek dilakukan
 * pada BARIS kasus yang sama persis dengan `case_number` yang dikirim Web A,
 * dimuat ulang dari DB di dalam transaksi (bukan objek lama di memori) —
 * dua eksekusi hampir-bersamaan untuk kasus yang sama (retry Web A, atau NOC
 * isolir manual duluan) sama-sama melihat baris pelanggan yang di-lock, bukan
 * cuma status kasus di memori yang belum tentu terbarui.
 *
 * `markTrackingFailed()` SENGAJA tidak dipanggil di sini kalau
 * `markIsolationSucceeded()` gagal (beda dari `toggleSuspend()`) — method itu
 * belum ada di riwayat git yang di-commit (bagian WIP terpisah, tidak
 * terkait task ini), dan tidak boleh di-commit di sini tanpa izin. Kegagalan
 * tracking cuma di-log; aksi isolir yang sudah commit di atas tetap sah.
 */
class IsolationExecutionService
{
    /**
     * @return array{already_suspended: bool}
     */
    public function suspendFromWebA(IsolationCase $case): array
    {
        $gateError = null;
        $alreadySuspended = false;
        $customerServiceId = $case->service_id;

        DB::transaction(function () use ($case, $customerServiceId, &$gateError, &$alreadySuspended) {
            $service = CustomerService::find($customerServiceId);

            if (! $service) {
                $gateError = 'Pelanggan ini tidak punya layanan aktif.';

                return;
            }

            // Baris pelanggan dikunci di transaksi yang sama — gerbang
            // idempotensi ("sudah suspended?") dibaca di SINI, bukan sebelum
            // transaksi, supaya dua eksekusi hampir-bersamaan tidak lolos
            // berdua lewat jendela antara baca status dan commit. Dicek
            // SEBELUM gerbang status kasus supaya panggilan kedua (retry Web
            // A setelah kasus sendiri sudah berpindah ke SUSPENDED oleh
            // panggilan pertama) tetap balas idempotent, bukan 422.
            $customer = Customer::whereKey($service->customer_id)->lockForUpdate()->first();

            if (! $customer) {
                $gateError = 'Pelanggan ini tidak punya layanan aktif.';

                return;
            }

            if ($customer->status === 'suspended') {
                $alreadySuspended = true;

                return;
            }

            // Dikunci & dimuat ulang dari DB pakai ID kasus yang SAMA PERSIS
            // dengan yang dikirim Web A — bukan "kasus aktif layanan ini apa
            // pun itu". Kasus basi (sudah dibatalkan, atau sudah digantikan
            // kasus baru sebagai active_slot) gagal di gerbang ini, tidak
            // ikut mengeksekusi kasus lain yang kebetulan aktif sekarang.
            $lockedCase = IsolationCase::whereKey($case->id)->lockForUpdate()->first();

            if (! $lockedCase
                || (int) $lockedCase->active_slot !== 1
                || ! in_array($lockedCase->status, IsolationCaseStatus::readyForIsolationGroup(), true)
            ) {
                $gateError = 'Kasus ini tidak lagi siap dieksekusi.';

                return;
            }

            if ($customer->status !== 'active') {
                $gateError = 'Layanan pelanggan ini tidak dalam status yang bisa diisolir.';

                return;
            }

            $oldStatus = $customer->status;
            $customer->update(['status' => 'suspended']);
            $service->update(['service_status' => 'isolir']);

            AuditLog::create([
                'user_id' => null,
                'module' => 'customers',
                'action' => 'isolir',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'suspended', 'note' => 'Isolir dieksekusi otomatis via Web A'],
                'ip_address' => request()->ip(),
                'user_agent' => 'Web A (API)',
                'created_at' => now(),
            ]);

            CustomerStatusLog::create([
                'customer_id' => $customer->id,
                'from_status' => $oldStatus,
                'to_status' => 'suspended',
                'changed_by' => null,
                'note' => 'Isolir dieksekusi otomatis via Web A',
            ]);
        });

        if ($gateError) {
            throw new IsolationExecutionException($gateError);
        }

        if ($alreadySuspended) {
            return ['already_suspended' => true];
        }

        try {
            $service = CustomerService::find($customerServiceId);
            app(IsolationCaseService::class)->markIsolationSucceeded(
                $service,
                null,
                'external_web_a'
            );
        } catch (Throwable $e) {
            report($e);
            Log::error('IsolationExecutionService: markIsolationSucceeded gagal setelah isolir Web A sukses.', [
                'service_id' => $customerServiceId,
                'error' => $e->getMessage(),
            ]);
        }

        return ['already_suspended' => false];
    }
}
