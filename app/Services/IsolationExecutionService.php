<?php

namespace App\Services;

use App\Enums\IsolationCaseStatus;
use App\Exceptions\IsolationExecutionException;
use App\Models\AuditLog;
use App\Models\Customer;
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
 * `markTrackingFailed()` SENGAJA tidak dipanggil di sini kalau
 * `markIsolationSucceeded()` gagal (beda dari `toggleSuspend()`) — method itu
 * belum ada di riwayat git yang di-commit (bagian WIP terpisah, tidak
 * terkait task ini), dan tidak boleh di-commit di sini tanpa izin. Kegagalan
 * tracking cuma di-log; aksi isolir yang sudah commit di atas tetap sah.
 */
class IsolationExecutionService
{
    public function suspendFromWebA(Customer $customer): array
    {
        if ($customer->status === 'suspended') {
            return ['already_suspended' => true];
        }

        $gateError = null;

        DB::transaction(function () use ($customer, &$gateError) {
            if (! $customer->customerService) {
                $gateError = 'Pelanggan ini tidak punya layanan aktif.';

                return;
            }

            // lockForUpdate() DI DALAM transaksi yang sama dengan
            // $customer->update() — persis pola toggleSuspend() pasca fix
            // 2026-11-20. Mencegah dua eksekusi hampir-bersamaan (retry Web A
            // vs NOC klik manual) lolos gerbang berdua.
            $activeCase = IsolationCase::where('service_id', $customer->customerService->id)
                ->where('active_slot', 1)
                ->lockForUpdate()
                ->first();

            if (! $activeCase || ! in_array($activeCase->status, IsolationCaseStatus::readyForIsolationGroup(), true)) {
                $gateError = 'Kasus ini tidak lagi siap dieksekusi.';

                return;
            }

            if ($customer->status !== 'active') {
                $gateError = 'Layanan pelanggan ini tidak dalam status yang bisa diisolir.';

                return;
            }

            $oldStatus = $customer->status;
            $customer->update(['status' => 'suspended']);
            $customer->customerService->update(['service_status' => 'isolir']);

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

        try {
            app(IsolationCaseService::class)->markIsolationSucceeded(
                $customer->customerService,
                null,
                'external_web_a'
            );
        } catch (Throwable $e) {
            report($e);
            Log::error('IsolationExecutionService: markIsolationSucceeded gagal setelah isolir Web A sukses.', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }

        return ['already_suspended' => false];
    }
}
