<?php

namespace App\Events;

use App\Models\CollectorDeposit;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Satu event untuk SELURUH siklus hidup setoran kolektor: diajukan →
 * diverifikasi (cocok / kurang / lebih setor) → dilunasi → dihapus buku.
 *
 * Dua sisi yang saling menunggu, dan sebelum ini dua-duanya buta:
 *   - **Admin** tidak tahu kolektor mana yang baru menyetor sampai dia
 *     memuat ulang Worksheet.
 *   - **Kolektor** tidak tahu setorannya sudah diperiksa atau belum sampai dia
 *     membuka Worklist — sementara saldonya bisa berubah kapan saja.
 *
 * `ShouldBroadcastNow`, BUKAN `ShouldBroadcast`/queue. Alasannya sama dengan
 * `AppNotification` (§6.3/§8) dan `NotificationsMarkedRead`: ini kabar tentang
 * UANG yang sedang dihitung dua orang di dua layar. Menggantungnya pada worker
 * berarti menambah satu cara lagi untuk gagal diam-diam — dan justru
 * kegagalan senyap itu yang sedang kita berantas. Volumenya pun kecil
 * (beberapa setoran per kolektor per hari), jadi tak ada alasan biaya untuk
 * mengantre.
 *
 * **Tidak membawa saldo.** Saldo adalah angka turunan (§11.2) yang harus
 * dihitung ulang dari sumbernya; menyiarkannya lewat payload berarti dua
 * sumber kebenaran yang gampang menyimpang. Klien memakai event ini sebagai
 * ABA-ABA — "ada yang berubah, ini apa" — lalu memuat ulang halamannya
 * sendiri. Halaman yang menghitung uang tidak boleh mengganti angkanya
 * diam-diam saat admin sedang menghitung uang fisik di meja.
 */
class CollectorDepositUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  string  $aksi  `diajukan` | `diverifikasi` | `dilunasi` | `dihapus_buku`
     */
    public function __construct(
        public CollectorDeposit $deposit,
        public User $collector,
        public string $aksi,
    ) {}

    /**
     * Dua kanal sekaligus — satu per audiens.
     *
     * Sisi kolektor menumpang `App.Models.User.{id}` yang SUDAH dipakai
     * broadcast notifikasi dan `NotificationsMarkedRead`. Tak perlu kanal baru:
     * penerimanya persis satu orang, dan otorisasinya sudah terdefinisi.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('collector-activity.'.$this->deposit->pop_id),
            new PrivateChannel('App.Models.User.'.$this->collector->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CollectorDepositUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'aksi' => $this->aksi,
            'deposit_id' => $this->deposit->id,
            'deposit_number' => $this->deposit->deposit_number,
            'status' => $this->deposit->status->value,
            'status_label' => $this->deposit->status->label(),
            'pop_id' => $this->deposit->pop_id,
            'collector_id' => $this->collector->id,
            'collector_name' => $this->collector->name,
            'declared_amount' => (float) $this->deposit->declared_amount,
            'recorded_amount' => (float) $this->deposit->computedAmount(),
            'worksheet_url' => route('collector-worksheet.show', [
                'collector' => $this->collector->id,
                'tab' => 'setoran',
            ]),
        ];
    }
}
