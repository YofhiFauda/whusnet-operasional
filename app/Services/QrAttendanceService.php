<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md §6.3 — Fungsi C, absen
 * teknisi lewat QR.
 *
 * QR sendirian TIDAK membuktikan kehadiran (stiker bisa difoto). Yang
 * membuktikan adalah gabungan: penugasan tim + jadwal hari ini + radius GPS.
 * Service ini tidak pernah mengubah status task sendiri — transisi tetap di
 * `TaskService::start()`, supaya semua guard status/bentrok ikut berlaku.
 */
class QrAttendanceService
{
    private const EARTH_RADIUS_METERS = 6371000;

    public function __construct(private readonly TaskService $tasks) {}

    /**
     * Task terjadwal hari ini milik user ini untuk pelanggan ini.
     *
     * @return Collection<int, Task>
     */
    public function todaysTasks(Customer $customer, User $user): Collection
    {
        return Task::query()
            ->where('customer_id', $customer->id)
            ->where('status', TaskStatus::TERJADWAL->value)
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
            ->whereHas('teamMembers', fn ($query) => $query->where('user_id', $user->id))
            ->get();
    }

    /**
     * Hitung jarak dan kelayakan radius. Tidak melempar — keputusan (tolak
     * atau tidak) ada di pemanggil supaya pesan & log-nya bisa beda per jalur.
     *
     * `flag`:
     *  - `ok`               ≤ radius normal
     *  - `perlu_review`     di antara radius normal dan hard limit
     *  - `tanpa_koordinat`  koordinat pelanggan atau GPS teknisi tidak ada
     *  - `out_of_radius`    di atas hard limit → ditolak
     *
     * @return array{distance: int|null, flag: string}
     */
    public function evaluateRadius(Customer $customer, ?float $latitude, ?float $longitude): array
    {
        if ($customer->latitude === null || $customer->longitude === null || $latitude === null || $longitude === null) {
            return ['distance' => null, 'flag' => 'tanpa_koordinat'];
        }

        $distance = $this->haversineMeters(
            (float) $customer->latitude,
            (float) $customer->longitude,
            $latitude,
            $longitude,
        );

        if ($distance <= config('qr.attendance_radius_meters')) {
            return ['distance' => $distance, 'flag' => 'ok'];
        }

        if ($distance <= config('qr.attendance_hard_limit_meters')) {
            return ['distance' => $distance, 'flag' => 'perlu_review'];
        }

        return ['distance' => $distance, 'flag' => 'out_of_radius'];
    }

    /**
     * Mulai task lewat absen QR. Dipanggil SETELAH controller memastikan
     * token, POP scope, dan penugasan — service ini hanya menjaga radius &
     * mendelegasikan transisi ke TaskService.
     */
    public function start(Task $task, User $user, ?float $latitude, ?float $longitude, ?int $accuracyMeters): Task
    {
        $customer = $task->customer;
        $evaluation = $this->evaluateRadius($customer, $latitude, $longitude);

        // Penjaga kedua: controller sudah menolak, tapi service ini juga dipanggil
        // dari jalur lain. Tanpa koordinat, started_distance_meters kosong dan
        // tidak ada bukti lokasi — jadi tidak boleh memulai task.
        if ($evaluation['flag'] === 'tanpa_koordinat') {
            throw new \DomainException('Absen wajib dengan koordinat GPS pelanggan dan perangkat.');
        }

        return $this->tasks->start($task, $user, [
            'started_via' => 'qr_scan',
            'started_latitude' => $latitude,
            'started_longitude' => $longitude,
            'started_accuracy_meters' => $accuracyMeters,
            'started_distance_meters' => $evaluation['distance'],
        ]);
    }

    /**
     * Haversine — jarak lingkaran besar di permukaan bumi, cukup akurat untuk
     * radius puluhan–ratusan meter (kesalahan bola vs elipsoid < 0,5%).
     */
    public function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        return (int) round(2 * self::EARTH_RADIUS_METERS * asin(min(1, sqrt($a))));
    }
}
