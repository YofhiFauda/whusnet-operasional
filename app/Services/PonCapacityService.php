<?php

namespace App\Services;

use App\Enums\InfrastructureCapacityUnit;
use App\Enums\InfrastructurePortKind;
use App\Models\AuditLog;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructurePort;
use App\Models\NetworkNodePort;
use App\Models\NetworkPortAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kapasitas PON manual + Red Zone (§16.3, keputusan user 21-10-2026).
 *
 * Satuan = PELANGGAN AKTIF: satu pelanggan dengan assignment port ODP aktif
 * pada ODP yang uplink-nya ke PON ini aktif DAN terverifikasi dihitung
 * satu unit — berapa pun ONT-nya (ONT tidak pernah jadi dasar hitung).
 * Assignment aktif ganda untuk pelanggan yang sama sudah ditolak di DB
 * (`network_customer_active_unique`). Kapasitas ODP (port pelanggan) adalah
 * ukuran lain dan TIDAK dicampur di sini.
 *
 * `NetworkNode::isInRedZone()` & `monitors_capacity` node `pon_port` lama
 * BUKAN sumber rumus ini.
 */
class PonCapacityService
{
    public function used(InfrastructurePort $pon): int
    {
        return $this->customersOn($pon, verifiedOnly: true);
    }

    /**
     * @return array{used: int, unverified: int, capacity: ?int, threshold: ?int, unit: string, unit_label: string, red_zone: ?bool, is_full: bool, remaining: ?int}
     */
    public function summary(InfrastructurePort $pon): array
    {
        $used = $this->used($pon);
        $unverified = $this->customersOn($pon, verifiedOnly: false) - $used;
        $capacity = $pon->capacity;
        $threshold = $pon->red_zone_threshold;

        // Red Zone hanya boleh diklaim bila kapasitas & ambang sama-sama
        // valid — null berarti "belum bisa dinilai", BUKAN "aman".
        $redZone = ($capacity !== null && $capacity > 0 && $threshold !== null && $threshold > 0)
            ? $used * 100 >= $threshold * $capacity
            : null;

        return [
            'used' => $used,
            'unverified' => $unverified,
            'capacity' => $capacity,
            'threshold' => $threshold,
            'unit' => InfrastructureCapacityUnit::PELANGGAN_AKTIF->value,
            'unit_label' => InfrastructureCapacityUnit::PELANGGAN_AKTIF->label(),
            'red_zone' => $redZone,
            'is_full' => $capacity !== null && $capacity > 0 && $used >= $capacity,
            'remaining' => $capacity !== null ? max(0, $capacity - $used) : null,
        ];
    }

    public function updateCapacity(InfrastructurePort $pon, int $capacity, int $threshold, User $actor): InfrastructurePort
    {
        if ($pon->kind !== InfrastructurePortKind::PON) {
            throw ValidationException::withMessages(['capacity' => 'Kapasitas pelanggan aktif hanya untuk PON.']);
        }
        if ($capacity < 1) {
            throw ValidationException::withMessages(['capacity' => 'Kapasitas PON minimal 1 pelanggan aktif.']);
        }
        if ($threshold < 1 || $threshold > 100) {
            throw ValidationException::withMessages(['red_zone_threshold' => 'Ambang Red Zone bernilai 1–100 persen.']);
        }

        return DB::transaction(function () use ($pon, $capacity, $threshold, $actor): InfrastructurePort {
            $pon = InfrastructurePort::query()->lockForUpdate()->findOrFail($pon->id);
            $used = $this->used($pon);

            if ($capacity < $used) {
                throw ValidationException::withMessages([
                    'capacity' => "Kapasitas {$capacity} di bawah pemakaian aktif {$used} pelanggan. Tangani koneksi terdampak dahulu.",
                ]);
            }

            $old = ['capacity' => $pon->capacity, 'red_zone_threshold' => $pon->red_zone_threshold];
            $pon->forceFill(['capacity' => $capacity, 'red_zone_threshold' => $threshold]);
            if (! $pon->isDirty()) {
                return $pon;
            }

            // saveQuietly: audit ditulis eksplisit di bawah dengan pelaku +
            // pemakaian saat itu; trait RecordsAuditLogs tidak boleh menulis
            // baris kedua untuk perubahan yang sama.
            $pon->saveQuietly();

            AuditLog::create([
                'user_id' => $actor->id,
                'module' => 'Port Infrastruktur',
                'action' => 'capacity_change',
                'auditable_type' => InfrastructurePort::class,
                'auditable_id' => $pon->id,
                'old_values' => $old,
                'new_values' => ['capacity' => $capacity, 'red_zone_threshold' => $threshold, 'used_at_change' => $used],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);

            return $pon;
        });
    }

    private function customersOn(InfrastructurePort $pon, bool $verifiedOnly): int
    {
        $odpIds = InfrastructureConnection::query()->active()
            ->when($verifiedOnly, fn ($query) => $query->verified())
            ->where('source_infrastructure_port_id', $pon->id)
            ->whereNotNull('target_network_node_id')
            ->select('target_network_node_id');

        return NetworkPortAssignment::query()
            ->where('active_key', 1)
            ->whereNotNull('customer_id')
            ->whereIn('network_node_port_id', NetworkNodePort::query()->whereIn('network_node_id', $odpIds)->select('id'))
            ->distinct()
            ->count('customer_id');
    }
}
