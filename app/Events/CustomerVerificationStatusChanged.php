<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * `ShouldBroadcastNow` (bukan `ShouldBroadcast`/queue) — dicek 2026-09-15,
 * SEMUA titik yang bikin `status` dirty (verifikasi CS/BD, mulai/selesai
 * survey & instalasi) itu aksi 1-klik per pelanggan, bukan loop bulk. Import
 * massal (`CustomerController::confirmImport()`) pakai `Customer::create()`
 * (bukan `update()`), jadi gak nyentuh observer `updated()` ini sama sekali
 * — aman gak jadi broadcast storm sinkron pas import ratusan baris
 * (docs/plan/analisa-broadcast-vs-lonceng-notif.md §2). KALAU ke depan ada
 * jalur bulk beneran yang manggil `$customer->update(['status' => ...])`
 * di loop, pertimbangkan exclude jalur itu dari observer / balik ke
 * `ShouldBroadcast` — JANGAN diam-diam ganti event ini balik ke queued.
 *
 * Broadcast "status workflow pelanggan berubah" — dipicu CustomerObserver::updated()
 * begitu kolom `status` dirty, BUKAN dispatch manual di tiap controller. Status
 * pelanggan diubah dari banyak jalur (CustomerWorkflowService::transition(),
 * tapi juga CustomerVerificationController::finalVerify() & CustomerInstallationController
 * yang update() langsung tanpa lewat service) — observer di model level satu-satunya
 * titik yang menjangkau semuanya (docs/plan/analisa-realtime-spa-operasional.md
 * §2.1 no. 10: dua admin verifikasi pelanggan yang sama tanpa realtime).
 *
 * SENGAJA gak bawa payload render lengkap (beda dari TaskStarted dkk) — baris
 * antrean verifikasi kompleks (banyak cabang tombol per status/permission),
 * jadi listener refetch partial row-nya sendiri lewat endpoint yang sudah lolos
 * scope & permission user (verifications.row), bukan percaya payload broadcast
 * mentah buat re-render.
 *
 * Dipakai JUGA oleh List Pelanggan (badge tab Aktif/Isolir + hapus baris yang
 * tak lagi cocok filter status aktif, docs/plan/rancangan-list-isolir.md §3.3)
 * lewat channel KEDUA `customers-list.{popId}` di `broadcastOn()` — event yang
 * sama, gate permission yang beda, bukan event baru.
 */
class CustomerVerificationStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Customer $customer) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('customers.'.$this->customer->pop_id),
            // Channel TERPISAH untuk List Pelanggan (badge tab Aktif/Isolir +
            // baris tabel, docs/plan/rancangan-list-isolir.md §3.3) — BUKAN
            // numpang `customers.{popId}` di atas. Gate channel itu
            // `customers.detail.installation.view` (punya antrean verifikasi),
            // sedangkan List Pelanggan digerbangi `customers.view` — dua
            // permission yang tidak selalu sama-sama dipegang (persis alasan
            // `registration-verifications.{popId}` dipisah dari channel ini,
            // lihat routes/channels.php).
            new PrivateChannel('customers-list.'.$this->customer->pop_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CustomerVerificationStatusChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'status' => $this->customer->status,
        ];
    }
}
