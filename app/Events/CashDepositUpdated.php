<?php

namespace App\Events;

use App\Models\CashDeposit;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Satu event untuk siklus hidup Setoran Kas Admin (admin → Owner/Bank):
 * diajukan → diverifikasi (cocok/kurang/lebih setor) → selisih ditutup.
 *
 * Rekan `CollectorDepositUpdated` satu tingkat di atas — kolektor→admin sudah
 * lama realtime, admin→Owner belum sama sekali sebelum ini (Setoran Kas gak
 * punya broadcast apa pun). `ShouldBroadcastNow` (bukan `ShouldBroadcast`)
 * dengan alasan yang sama: kabar tentang uang yang sedang diperiksa dua orang,
 * gak boleh nunggu antrean worker.
 *
 * **Tidak membawa saldo** — sama seperti `CollectorDepositUpdated`, saldo
 * turunan gak pernah disiarkan lewat payload. Klien menambal DOM-nya dengan
 * fetch-ulang halaman sekarang (lihat `partials/collector-realtime.blade.php`),
 * bukan menghitung dari payload event.
 */
class CashDepositUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  string  $aksi  `diajukan` | `diverifikasi` | `ditutup_selisih`
     */
    public function __construct(
        public CashDeposit $deposit,
        public User $depositor,
        public string $aksi,
    ) {}

    /**
     * Dua kanal — satu per audiens, sama pola `CollectorDepositUpdated`:
     *   - `cash-deposits` — pemeriksa (Owner/atasan), gerbang `cash_deposit.view`
     *     di routes/channels.php. Global, gak per-POP: Owner/atasan bypass
     *     scope POP (CLAUDE.md § RBAC).
     *   - `App.Models.User.{depositor_id}` — admin penyetor sendiri, kanal
     *     generik yang sudah ada, dipakai Worksheet Admin (lihat
     *     CollectorWorksheetController::activityChannels()).
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('cash-deposits'),
            new PrivateChannel('App.Models.User.'.$this->depositor->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CashDepositUpdated';
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
            'depositor_id' => $this->depositor->id,
            'depositor_name' => $this->depositor->name,
        ];
    }
}
