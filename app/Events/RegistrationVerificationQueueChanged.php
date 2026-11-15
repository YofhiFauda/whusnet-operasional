<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sinyal "isi antrean Verifikasi Registrasi di POP ini berubah" — buat badge
 * sidebar realtime. Dulu badge cuma dihitung View Composer saat halaman
 * dirender, jadi registrasi baru baru kelihatan setelah user pindah halaman.
 *
 * Dipicu CustomerObserver (created/updated/deleted), bukan controller —
 * pelanggan masuk/keluar status `registered` dari banyak jalur (Registrasi,
 * approve/reject, Edit pindah POP, tinker).
 *
 * Payload SENGAJA cuma pop_id: listener mengambil ulang angkanya sendiri lewat
 * `customer-registration-verifications.badge-count`, yang sudah lolos
 * permission + POP scope user. Jangan tambahkan data pelanggan ke sini.
 *
 * `ShouldBroadcast` (queued, bukan Now): Customer::create() juga dipanggil
 * import massal — broadcast sinkron per baris bikin import lambat.
 * `ShouldDispatchAfterCommit`: Registrasi jalan di dalam transaksi; kalau
 * event terkirim sebelum commit, fetch angka dari klien masih membaca nilai
 * lama dan badge-nya gak berubah.
 */
class RegistrationVerificationQueueChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $popId) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('registration-verifications.'.$this->popId)];
    }

    public function broadcastAs(): string
    {
        return 'RegistrationVerificationQueueChanged';
    }

    /**
     * @return array{pop_id: int}
     */
    public function broadcastWith(): array
    {
        return ['pop_id' => $this->popId];
    }
}
