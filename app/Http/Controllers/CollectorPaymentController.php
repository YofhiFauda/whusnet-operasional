<?php

namespace App\Http\Controllers;

use App\Traits\RecordsCollectorBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Jalur KOLEKTOR mencatat pembayarannya sendiri, dari Worklist-nya.
 *
 * Rutenya SENGAJA tanpa parameter `{collector}` — kolektor diambil dari
 * `auth()->user()`, tak pernah dari URL maupun body. Kalau kolektor boleh
 * mengirim id kolektor, kolektor A bisa mencatat pembayaran atas nama
 * kolektor B, dan saldo/setoran keduanya langsung tak bisa dipercaya.
 *
 * Batas kewenangan `kolektor.pay` (bukan `payments.create`):
 *   - cuma invoice pelanggan ber-`collector_id = dirinya` — ditegakkan
 *     CollectorPaymentService::validateRows();
 *   - cuma dalam POP scope dirinya — `applyUserScope($actor)` di service yang
 *     sama, jadi pelanggan yang terlanjur ter-assign lalu POP-nya keluar dari
 *     scope tetap ditolak;
 *   - nominal BOLEH melebihi sisa tagihan (ADHOC-84 §2.5, ganti aturan lama
 *     "kelebihan dikembalikan fisik") — kelebihannya otomatis kredit saldo
 *     pelanggan, sama seperti jalur admin `PaymentService::record()`. Yang
 *     beda dua jalur ini cuma SIAPA yang membayar (admin di kantor vs
 *     kolektor di lapangan), bukan cara kelebihan bayarnya diperlakukan.
 *

 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §9, §14.
 */
class CollectorPaymentController extends Controller
{
    use RecordsCollectorBatch;

    public function store(Request $request): JsonResponse
    {
        $collector = $request->user();

        // Permission `kolektor.pay` sudah digerbang middleware. Cek role di
        // sini menutup kasus permission itu terlanjur diberikan ke role lain
        // lewat Role Matrix: yang dicatat sebagai `collected_by` harus benar
        // -benar kolektor, kalau tidak laporan setoran mencatat uang atas nama
        // orang yang bukan penagih.
        abort_unless($collector->hasRole('kolektor'), 403, 'Hanya kolektor yang bisa mencatat pembayaran dari worklist.');

        $this->normalizeBatchAmounts($request);

        $validated = $request->validate($this->batchValidationRules());

        return $this->recordBatch($collector, $collector, $validated);
    }
}
