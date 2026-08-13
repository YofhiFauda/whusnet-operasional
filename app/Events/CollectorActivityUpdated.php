<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Aktivitas kas kolektor DI LUAR siklus setoran — pelengkap
 * `CollectorDepositUpdated`, memakai dua kanal yang sama.
 *
 * Tiga kejadian yang sebelumnya mengubah angka orang lain tanpa suara:
 *
 * | Aksi | Yang berubah diam-diam |
 * |---|---|
 * | `pembayaran_dicatat` | saldo kolektor NAIK, daftar tunggakan di Worksheet berkurang |
 * | `pembayaran_ditolak` | saldo kolektor TURUN — notifikasi ada, layarnya diam |
 * | `pelanggan_diassign` / `pelanggan_dilepas` | rute kolektor berubah; tak ada notifikasi sama sekali |
 *
 * Yang terakhir paling berbahaya di lapangan: pelanggan yang dilepas setelah
 * kolektor berangkat berarti dia menagih orang yang bukan lagi tanggungannya.
 *
 * `ShouldBroadcastNow` dan payload tanpa saldo — alasannya sama persis dengan
 * `CollectorDepositUpdated`, lihat docblock di sana. Event ini ABA-ABA, bukan
 * penambal DOM: halaman yang menghitung uang tidak boleh mengganti angkanya
 * sendiri saat orangnya sedang menghitung.
 */
class CollectorActivityUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  string  $aksi  `pembayaran_dicatat` | `pembayaran_ditolak` | `pelanggan_diassign` | `pelanggan_dilepas`
     * @param  int  $jumlah  banyaknya baris yang terpengaruh (pembayaran / pelanggan)
     * @param  float  $total  nominal terkait, 0 untuk aksi non-uang
     * @param  string|null  $keterangan  nomor pembayaran / nama pelanggan — pemanis pesan, bukan data otoritatif
     */
    public function __construct(
        public User $collector,
        public int $popId,
        public string $aksi,
        public int $jumlah = 1,
        public float $total = 0.0,
        public ?string $keterangan = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('collector-activity.'.$this->popId),
            new PrivateChannel('App.Models.User.'.$this->collector->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CollectorActivityUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'aksi' => $this->aksi,
            'collector_id' => $this->collector->id,
            'collector_name' => $this->collector->name,
            'pop_id' => $this->popId,
            'jumlah' => $this->jumlah,
            'total' => $this->total,
            'keterangan' => $this->keterangan,
        ];
    }
}
