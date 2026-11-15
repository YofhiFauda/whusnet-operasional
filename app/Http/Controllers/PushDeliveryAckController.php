<?php

namespace App\Http\Controllers;

use App\Enums\PushDeliveryStatus;
use App\Models\PushDelivery;
use Illuminate\Http\Response;

/**
 * Endpoint ack delivery push (ADHOC-288, Tahap 6) — dipanggil `sw.js` dari
 * dalam event `push`, SEBELUM notifikasi ditampilkan. Signed URL (bukan
 * auth session) karena Service Worker manggil ini di luar konteks halaman
 * yang login; signature-nya sendiri sudah cukup membuktikan ini delivery
 * yang sah (dibuat server, bukan ditebak klien).
 *
 * Terima ack dari status `Sent` MAUPUN `Escalated` — `EscalateIfNotDeliveredJob`
 * jalan tepat 5 menit setelah dikirim, tapi push service (FCM/dst) kadang
 * telat nyampe ke device lebih dari itu (battery saver, device sleep,
 * jaringan lambat). Kalau cuma terima dari `Sent`, ack yang nyampe setelah
 * job eskalasi jalan diabaikan diam-diam — delivery yang BENERAN sampai ke
 * device tetap nyangkut selamanya di status Escalated pada Dashboard,
 * padahal notifikasinya kebukti tampil (laporan user 2026-11-10).
 */
class PushDeliveryAckController extends Controller
{
    public function __invoke(PushDelivery $delivery): Response
    {
        if (in_array($delivery->status, [PushDeliveryStatus::Sent, PushDeliveryStatus::Escalated], true)) {
            $delivery->update([
                'status' => PushDeliveryStatus::Delivered,
                'delivered_at' => now(),
            ]);
        }

        return response()->noContent();
    }
}
